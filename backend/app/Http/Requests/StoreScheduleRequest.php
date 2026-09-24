<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isAllDay = $this->boolean('is_all_day');

        return [
            'target_id' => [
                'nullable',
                'integer',
                Rule::exists('targets', 'id')->where(
                    fn (Builder $query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'title' => ['required', 'string', 'max:255'],
            'schedule_type' => ['required', 'string', 'max:100'],
            'is_all_day' => ['required', 'boolean'],
            'starts_at' => [
                'required',
                Rule::when(
                    $isAllDay,
                    ['date_format:Y-m-d'],
                    ['date_format:Y-m-d\TH:i:sP']
                ),
            ],
            'ends_at' => [
                Rule::requiredIf($isAllDay),
                'nullable',
                Rule::when(
                    $isAllDay,
                    ['date_format:Y-m-d'],
                    ['date_format:Y-m-d\TH:i:sP']
                ),
                'after_or_equal:starts_at',
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'memo' => ['nullable', 'string'],
        ];
    }
}
