<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Resources\ScheduleResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ScheduleController extends Controller
{
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
}
