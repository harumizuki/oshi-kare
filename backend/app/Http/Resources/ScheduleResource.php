<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'schedule_type' => $this->schedule_type,
            'starts_at' => $this->is_all_day
                ? $this->starts_at->toDateString()
                : $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at === null
                ? null
                : ($this->is_all_day
                    ? $this->ends_at->toDateString()
                    : $this->ends_at->toIso8601String()),
            'is_all_day' => $this->is_all_day,
            'location' => $this->location,
            'url' => $this->url,
            'memo' => $this->memo,
            'target' => $this->whenLoaded(
                'target',
                fn () => $this->target === null ? null : [
                    'id' => $this->target->id,
                    'name' => $this->target->name,
                ]
            ),
        ];
    }
}
