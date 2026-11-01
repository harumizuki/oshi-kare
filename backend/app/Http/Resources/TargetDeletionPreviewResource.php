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
        return [
            'target' => [
                'id' => $this->id,
                'name' => $this->name,
            ],
            'schedule_count' => $this->schedules->count(),
            'schedules' => TargetSchedulePreviewResource::collection(
                $this->whenLoaded('schedules')
            ),
        ];
    }
}
