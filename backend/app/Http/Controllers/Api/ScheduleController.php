<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexScheduleRequest;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ScheduleController extends Controller
{
    public function index(IndexScheduleRequest $request): AnonymousResourceCollection
    {
        $start = $request->validated('start');
        $end = $request->validated('end');

        $allDayStart = CarbonImmutable::createFromFormat('!Y-m-d', $start, 'UTC');
        $allDayEnd = CarbonImmutable::createFromFormat('!Y-m-d', $end, 'UTC');
        $timedStart = CarbonImmutable::createFromFormat('!Y-m-d', $start, 'Asia/Tokyo')->utc();
        $timedEndExclusive = CarbonImmutable::createFromFormat('!Y-m-d', $end, 'Asia/Tokyo')
            ->addDay()
            ->utc();

        $schedules = $request->user()
            ->schedules()
            ->with('target')
            ->where(function (Builder $query) use (
                $allDayStart,
                $allDayEnd,
                $timedStart,
                $timedEndExclusive
            ) {
                $query
                    ->where(function (Builder $query) use ($allDayStart, $allDayEnd) {
                        $query
                            ->where('is_all_day', true)
                            ->where('starts_at', '<=', $allDayEnd)
                            ->where('ends_at', '>=', $allDayStart);
                    })
                    ->orWhere(function (Builder $query) use ($timedStart, $timedEndExclusive) {
                        $query
                            ->where('is_all_day', false)
                            ->where('starts_at', '<', $timedEndExclusive)
                            ->where(function (Builder $query) use ($timedStart) {
                                $query
                                    ->where('ends_at', '>=', $timedStart)
                                    ->orWhere(function (Builder $query) use ($timedStart) {
                                        $query
                                            ->whereNull('ends_at')
                                            ->where('starts_at', '>=', $timedStart);
                                    });
                            });
                    });
            })
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        return ScheduleResource::collection($schedules);
    }

    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($data['is_all_day']) {
            $data['starts_at'] = CarbonImmutable::createFromFormat('!Y-m-d', $data['starts_at'], 'UTC');
            $data['ends_at'] = CarbonImmutable::createFromFormat('!Y-m-d', $data['ends_at'], 'UTC');
        } else {
            $data['starts_at'] = CarbonImmutable::parse($data['starts_at'])->utc();
            $data['ends_at'] = isset($data['ends_at'])
                ? CarbonImmutable::parse($data['ends_at'])->utc()
                : null;
        }

        $schedule = $request->user()
            ->schedules()
            ->create($data);

        $schedule->load('target');

        return (new ScheduleResource($schedule))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Schedule $schedule): ScheduleResource
    {
        Gate::authorize('view', $schedule);

        $schedule->load('target');

        return new ScheduleResource($schedule);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): ScheduleResource
    {
        Gate::authorize('update', $schedule);

        $data = $request->validated();

        if ($data['is_all_day']) {
            $data['starts_at'] = CarbonImmutable::createFromFormat('!Y-m-d', $data['starts_at'], 'UTC');
            $data['ends_at'] = CarbonImmutable::createFromFormat('!Y-m-d', $data['ends_at'], 'UTC');
        } else {
            $data['starts_at'] = CarbonImmutable::parse($data['starts_at'])->utc();
            $data['ends_at'] = isset($data['ends_at'])
                ? CarbonImmutable::parse($data['ends_at'])->utc()
                : null;
        }

        $schedule->update($data);
        $schedule->load('target');

        return new ScheduleResource($schedule);
    }

    public function destroy(Schedule $schedule): Response
    {
        Gate::authorize('delete', $schedule);

        $schedule->delete();

        return response()->noContent();
    }
}
