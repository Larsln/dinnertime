<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a period of at most a month given as first and last day.
 */
class PeriodRequest extends FormRequest
{
    private const int MAX_DAYS = 31;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before:'.$this->latestEnd()],
        ];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('from'));
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('to'));
    }

    /**
     * First day that lies beyond the longest allowed period.
     */
    private function latestEnd(): string
    {
        $from = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('from')), report: false);

        return ($from ?: CarbonImmutable::today())->addDays(self::MAX_DAYS)->toDateString();
    }
}
