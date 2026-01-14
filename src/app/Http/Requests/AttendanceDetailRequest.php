<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time'   => ['nullable', 'date_format:H:i'],
            'end_time'     => ['nullable', 'date_format:H:i'],

            'break1_start' => ['nullable', 'date_format:H:i'],
            'break1_end'   => ['nullable', 'date_format:H:i'],

            'break2_start' => ['nullable', 'date_format:H:i'],
            'break2_end'   => ['nullable', 'date_format:H:i'],

            'note'         => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => '備考を記入してください',
            'note.string'   => '備考を記入してください',
            'note.max'      => '備考は1000文字以内で入力してください',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $afterValidator) {
            $start = $this->parseInputTime($this->input('start_time'));
            $end   = $this->parseInputTime($this->input('end_time'));

            if ($start && $end && $start->greaterThan($end)) {
                $afterValidator->errors()->add('start_time', '出勤時間が不適切な値です');
            }

            $this->validateBreak($afterValidator, 1, $start, $end);
            $this->validateBreak($afterValidator, 2, $start, $end);
        });
    }

    private function validateBreak(
        Validator $validator,
        int $breakNumber,
        ?Carbon $workStart,
        ?Carbon $workEnd
    ): void {
        $breakStart = $this->parseInputTime($this->input("break{$breakNumber}_start"));
        $breakEnd   = $this->parseInputTime($this->input("break{$breakNumber}_end"));

        if (! $breakStart && ! $breakEnd) {
            return;
        }

        // 休憩終了 < 休憩開始
        if ($breakStart && $breakEnd && $breakEnd->lessThan($breakStart)) {
            $validator->errors()->add("break{$breakNumber}_start", '休憩時間が不適切な値です');
            return;
        }

        // 休憩開始の範囲チェック
        if ($breakStart && $workStart && $breakStart->lessThan($workStart)) {
            $validator->errors()->add("break{$breakNumber}_start", '休憩時間が不適切な値です');
        }
        if ($breakStart && $workEnd && $breakStart->greaterThan($workEnd)) {
            $validator->errors()->add("break{$breakNumber}_start", '休憩時間が不適切な値です');
        }

        // 休憩終了の範囲チェック
        if ($breakEnd && $workStart && $breakEnd->lessThan($workStart)) {
            $validator->errors()->add("break{$breakNumber}_end", '休憩時間が不適切な値です');
        }
        if ($breakEnd && $workEnd && $breakEnd->greaterThan($workEnd)) {
            $validator->errors()->add("break{$breakNumber}_end", '休憩時間もしくは退勤時間が不適切な値です');
        }
    }

    private function parseInputTime(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
