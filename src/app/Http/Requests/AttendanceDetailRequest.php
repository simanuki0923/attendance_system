<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

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
        $validator->after(function (Validator $v) {
            $start = $this->parseInputTime($this->input('start_time'));
            $end   = $this->parseInputTime($this->input('end_time'));

            if ($start && $end && $start->greaterThan($end)) {
                $v->errors()->add('start_time', '出勤時間が不適切な値です');
            }

            $this->validateBreak($v, 1, $start, $end);
            $this->validateBreak($v, 2, $start, $end);
        });
    }

    private function validateBreak(Validator $v, int $no, ?Carbon $workStart, ?Carbon $workEnd): void
    {
        $bs = $this->parseInputTime($this->input("break{$no}_start"));
        $be = $this->parseInputTime($this->input("break{$no}_end"));

        if (! $bs && ! $be) {
            return;
        }

        if ($bs && $be && $be->lessThan($bs)) {
            $v->errors()->add("break{$no}_start", '休憩時間が不適切な値です');
            return;
        }

        if ($bs && $workStart && $bs->lessThan($workStart)) {
            $v->errors()->add("break{$no}_start", '休憩時間が不適切な値です');
        }
        if ($bs && $workEnd && $bs->greaterThan($workEnd)) {
            $v->errors()->add("break{$no}_start", '休憩時間が不適切な値です');
        }

        if ($be && $workStart && $be->lessThan($workStart)) {
            $v->errors()->add("break{$no}_end", '休憩時間が不適切な値です');
        }
        if ($be && $workEnd && $be->greaterThan($workEnd)) {
            $v->errors()->add("break{$no}_end", '休憩時間もしくは退勤時間が不適切な値です');
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
        } catch (\Throwable $e) {
            return null;
        }
    }
}
