<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyTargetRequest;
use App\Http\Requests\IndexTargetRequest;
use App\Http\Requests\StoreTargetRequest;
use App\Http\Requests\UpdateTargetRequest;
use App\Http\Resources\TargetDeletionPreviewResource;
use App\Http\Resources\TargetResource;
use App\Models\Schedule;
use App\Models\Target;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TargetController extends Controller
{
    public function index(IndexTargetRequest $request): AnonymousResourceCollection
    {
        $keyword = $request->validated('keyword');

        $targets = $request->user()
            ->targets()
            ->with('category')
            ->when(
                filled($keyword),
                fn ($query) => $query->where('name', 'like', '%'.$keyword.'%')
            )
            ->get();

        return TargetResource::collection($targets);
    }

    public function store(StoreTargetRequest $request): JsonResponse
    {
        $target = $request->user()
            ->targets()
            ->create($request->validated());

        $target->load('category');

        return (new TargetResource($target))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Target $target): TargetResource
    {
        Gate::authorize('view', $target);

        $target->load('category');

        return new TargetResource($target);
    }

    public function deletionPreview(Request $request, Target $target): TargetDeletionPreviewResource
    {
        Gate::authorize('delete', $target);

        $schedules = $target
            ->pivotSchedules()
            ->where('schedules.user_id', $request->user()->id)
            ->with(['targets' => fn ($query) => $query->orderBy('targets.id')])
            ->withCount('targets')
            ->orderBy('schedules.starts_at')
            ->orderBy('schedules.id')
            ->get();

        $target->setRelation('schedules', $schedules);
        $target->setAttribute(
            'expected_schedule_state',
            $this->makeExpectedScheduleState($target, $schedules)
        );

        return new TargetDeletionPreviewResource($target);
    }

    public function update(UpdateTargetRequest $request, Target $target): TargetResource
    {
        Gate::authorize('update', $target);

        $target->update($request->validated());
        $target->load('category');

        return new TargetResource($target);
    }

    public function destroy(DestroyTargetRequest $request, Target $target): Response
    {
        Gate::authorize('delete', $target);

        $data = $request->validated();

        DB::transaction(function () use ($request, $target, $data) {
            $lockedTarget = $request->user()
                ->targets()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $discoveredScheduleIds = $this->getRelatedScheduleIds(
                $lockedTarget,
                $request->user()->id
            );

            $lockedSchedules = $request->user()
                ->schedules()
                ->whereIn('id', $discoveredScheduleIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedSchedules->isNotEmpty()) {
                DB::table('schedule_target')
                    ->whereIn('schedule_id', $lockedSchedules->modelKeys())
                    ->orderBy('schedule_id')
                    ->orderBy('target_id')
                    ->lockForUpdate()
                    ->get();

                $lockedSchedules->load([
                    'targets' => fn ($query) => $query->orderBy('targets.id'),
                ]);
            }

            $currentScheduleIds = $this->getRelatedScheduleIds(
                $lockedTarget,
                $request->user()->id
            );

            $currentSchedules = $lockedSchedules
                ->filter(fn (Schedule $schedule) => $schedule->targets->contains(
                    fn (Target $relatedTarget) => $relatedTarget->id === $lockedTarget->id
                ))
                ->values();

            $hasTargetOwnerMismatch = $currentSchedules->contains(
                fn (Schedule $schedule) => $schedule->targets->contains(
                    fn (Target $relatedTarget) => $relatedTarget->user_id !== $request->user()->id
                )
            );

            if ($hasTargetOwnerMismatch) {
                $this->throwTargetScheduleStateChanged();
            }

            $lockedRelatedScheduleIds = $currentSchedules
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            $expectedScheduleIds = collect($data['expected_schedule_ids'])
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            $currentScheduleState = $this->makeExpectedScheduleState(
                $lockedTarget,
                $currentSchedules
            );

            if (
                $discoveredScheduleIds->all() !== $currentScheduleIds->all()
                || $lockedRelatedScheduleIds->all() !== $currentScheduleIds->all()
                || $expectedScheduleIds->all() !== $currentScheduleIds->all()
                || ! hash_equals($data['expected_schedule_state'], $currentScheduleState)
            ) {
                $this->throwTargetScheduleStateChanged();
            }

            $selectedScheduleIds = collect($data['selected_schedule_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            if ($selectedScheduleIds->diff($currentScheduleIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'selected_schedule_ids' => [
                        '対象の登録情報に紐づく予定だけを指定してください。',
                    ],
                ]);
            }

            $singleScheduleIds = $currentSchedules
                ->filter(fn (Schedule $schedule) => $schedule->targets->count() === 1)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();
            $sharedScheduleIds = $currentScheduleIds->diff($singleScheduleIds)->values();

            if ($selectedScheduleIds->intersect($sharedScheduleIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'selected_schedule_ids' => [
                        '共有予定はTarget削除による削除対象にできません。',
                    ],
                ]);
            }

            $scheduleIdsToDelete = match ($data['deletion_mode']) {
                DestroyTargetRequest::MODE_DELETE_ALL => $singleScheduleIds,
                DestroyTargetRequest::MODE_KEEP_ALL => collect(),
                DestroyTargetRequest::MODE_DELETE_SELECTED => $selectedScheduleIds,
            };

            if ($scheduleIdsToDelete->isNotEmpty()) {
                $request->user()
                    ->schedules()
                    ->whereIn('id', $scheduleIdsToDelete)
                    ->delete();
            }

            $keptSchedules = $currentSchedules
                ->reject(fn (Schedule $schedule) => $scheduleIdsToDelete->contains($schedule->id));

            foreach ($keptSchedules as $keptSchedule) {
                $remainingTargetId = $keptSchedule->targets
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->reject(fn ($id) => $id === $lockedTarget->id)
                    ->sort()
                    ->first();
                $currentLegacyTargetId = $keptSchedule->target_id === null
                    ? null
                    : (int) $keptSchedule->target_id;

                if ($currentLegacyTargetId !== $remainingTargetId) {
                    $keptSchedule->update(['target_id' => $remainingTargetId]);
                }
            }

            if ($keptSchedules->isNotEmpty()) {
                DB::table('schedule_target')
                    ->where('target_id', $lockedTarget->id)
                    ->whereIn('schedule_id', $keptSchedules->modelKeys())
                    ->delete();
            }

            $lockedTarget->delete();
        });

        return response()->noContent();
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     */
    private function makeExpectedScheduleState(Target $target, Collection $schedules): string
    {
        $state = [
            'target_id' => (int) $target->id,
            'schedules' => $schedules
                ->sortBy('id')
                ->map(fn (Schedule $schedule) => [
                    'schedule_id' => (int) $schedule->id,
                    'updated_at' => $schedule->updated_at
                        ? $schedule->updated_at->copy()->utc()->format('Y-m-d\TH:i:s.u\Z')
                        : null,
                    'target_ids' => $schedule->targets
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->sort()
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    /**
     * @return Collection<int, int>
     */
    private function getRelatedScheduleIds(Target $target, int $userId): Collection
    {
        return DB::table('schedule_target')
            ->join('schedules', 'schedules.id', '=', 'schedule_target.schedule_id')
            ->where('schedule_target.target_id', $target->id)
            ->where('schedules.user_id', $userId)
            ->orderBy('schedule_target.schedule_id')
            ->pluck('schedule_target.schedule_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function throwTargetScheduleStateChanged(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => '関連予定が変更されています。削除確認をやり直してください。',
            'code' => 'target_schedule_state_changed',
        ], Response::HTTP_CONFLICT));
    }
}
