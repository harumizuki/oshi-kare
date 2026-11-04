<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TargetDeletionPreviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $singleScheduleCount = $this->schedules
            ->filter(fn ($schedule) => (int) $schedule->targets_count === 1)
            ->count();
        $sharedScheduleCount = $this->schedules
            ->filter(fn ($schedule) => (int) $schedule->targets_count >= 2)
            ->count();

        return [
            'target' => [
                'id' => $this->id,
                'name' => $this->name,
            ],
            'schedule_count' => $this->schedules->count(),
            'single_schedule_count' => $singleScheduleCount,
            'shared_schedule_count' => $sharedScheduleCount,
            'expected_schedule_state' => $this->expected_schedule_state,
            'schedules' => TargetSchedulePreviewResource::collection(
                $this->whenLoaded('schedules')
            ),
        ];
    }
}
