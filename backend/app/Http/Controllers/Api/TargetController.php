<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyTargetRequest;
use App\Http\Requests\IndexTargetRequest;
use App\Http\Requests\StoreTargetRequest;
use App\Http\Requests\UpdateTargetRequest;
use App\Http\Resources\TargetDeletionPreviewResource;
use App\Http\Resources\TargetResource;
use App\Models\Target;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

        $schedules = $request->user()
            ->schedules()
            ->where('target_id', $target->id)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $target->setRelation('schedules', $schedules);

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

            $currentScheduleIds = $request->user()
                ->schedules()
                ->where('target_id', $lockedTarget->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            $expectedScheduleIds = collect($data['expected_schedule_ids'])
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            if ($expectedScheduleIds->all() !== $currentScheduleIds->all()) {
                throw new HttpResponseException(response()->json([
                    'message' => '関連予定が変更されています。削除確認をやり直してください。',
                    'code' => 'target_schedule_state_changed',
                ], Response::HTTP_CONFLICT));
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

            $scheduleIdsToDelete = match ($data['deletion_mode']) {
                DestroyTargetRequest::MODE_DELETE_ALL => $currentScheduleIds,
                DestroyTargetRequest::MODE_KEEP_ALL => collect(),
                DestroyTargetRequest::MODE_DELETE_SELECTED => $selectedScheduleIds,
            };

            if ($scheduleIdsToDelete->isNotEmpty()) {
                $request->user()
                    ->schedules()
                    ->where('target_id', $lockedTarget->id)
                    ->whereIn('id', $scheduleIdsToDelete)
                    ->delete();
            }

            $lockedTarget->delete();
        });

        return response()->noContent();
    }
}
