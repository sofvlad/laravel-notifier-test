<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Notification\Report;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'      => ['nullable', 'integer', 'exists:users,id'],
            'period_start' => ['required', 'date', 'before_or_equal:period_end'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'             => 'User ID is required',
            'period_start.required'        => 'Start date is required',
            'period_start.date'            => 'Start date must be a valid date',
            'period_start.before_or_equal' => 'Start date must be before or equal to end date',
            'period_end.required'          => 'End date is required',
            'period_end.date'              => 'End date must be a valid date',
            'period_end.after_or_equal'    => 'End date must be after or equal to start date',
        ];
    }

    public function attributes(): array
    {
        return [
            'period_start' => 'start date',
            'period_end'   => 'end date',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('period_start') && $this->has('period_end')) {
                $periodStart = $this->input('period_start');
                $periodEnd   = $this->input('period_end');

                try {
                    $start = Carbon::parse($periodStart);
                    $end   = Carbon::parse($periodEnd);
                } catch (InvalidArgumentException) {
                    return;
                }

                if ($start->diffInDays($end) > 90) {
                    $validator->errors()->add(
                        'period_end',
                        'Period cannot exceed 90 days'
                    );
                }
            }
        });
    }
}
