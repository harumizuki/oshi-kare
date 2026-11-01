<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DestroyTargetRequest extends FormRequest
{
    public const MODE_DELETE_ALL = 'delete_all';

    public const MODE_KEEP_ALL = 'keep_all';

    public const MODE_DELETE_SELECTED = 'delete_selected';

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
        $deletesSelectedSchedules = $this->input('deletion_mode') === self::MODE_DELETE_SELECTED;

        return [
            'deletion_mode' => [
                'required',
                'string',
                Rule::in([
                    self::MODE_DELETE_ALL,
                    self::MODE_KEEP_ALL,
                    self::MODE_DELETE_SELECTED,
                ]),
            ],
            'expected_schedule_ids' => ['present', 'array'],
            'expected_schedule_ids.*' => ['integer', 'distinct'],
            'selected_schedule_ids' => $deletesSelectedSchedules
                ? ['present', 'array']
                : ['prohibited'],
            'selected_schedule_ids.*' => ['integer', 'distinct'],
        ];
    }
}
