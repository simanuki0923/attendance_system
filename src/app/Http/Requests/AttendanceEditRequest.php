<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceEditRequest extends FormRequest
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
            $start = $this->toCarbon($this->input('start_time'));
            $end   = $this->toCarbon($this->input('end_time'));

            $break1Start = $this->toCarbon($this->input('break1_start'));
            $break1End = $this->toCarbon($this->input('break1_end'));
            $break2Start = $this->toCarbon($this->input('break2_start'));
            $break2End = $this->toCarbon($this->input('break2_end'));

            if ($start && $end && $start->greaterThan($end)) {
                $workTimeMessage = '出勤時間もしくは退勤時間が不適切な値です';
                $afterValidator->errors()->add('start_time', $workTimeMessage);
                $afterValidator->errors()->add('end_time', $workTimeMessage);
            }

            if ($break1Start && $start && $break1Start->lessThan($start)) {
                $afterValidator->errors()->add('break1_start', '休憩時間が不適切な値です');
            }
            if ($break1Start && $end && $break1Start->greaterThan($end)) {
                $afterValidator->errors()->add('break1_start', '休憩時間が不適切な値です');
            }

            if ($break2Start && $start && $break2Start->lessThan($start)) {
                $afterValidator->errors()->add('break2_start', '休憩時間が不適切な値です');
            }
            if ($break2Start && $end && $break2Start->greaterThan($end)) {
                $afterValidator->errors()->add('break2_start', '休憩時間が不適切な値です');
            }

            if ($break1End && $end && $break1End->greaterThan($end)) {
                $afterValidator->errors()->add('break1_end', '休憩時間もしくは退勤時間が不適切な値です');
            }
            if ($break2End && $end && $break2End->greaterThan($end)) {
                $afterValidator->errors()->add('break2_end', '休憩時間もしくは退勤時間が不適切な値です');
            }

            if ($break1Start && $break1End && $break1End->lessThan($break1Start)) {
                $afterValidator->errors()->add('break1_start', '休憩時間が不適切な値です');
            }
            if ($break2Start && $break2End && $break2End->lessThan($break2Start)) {
                $afterValidator->errors()->add('break2_start', '休憩時間が不適切な値です');
            }
        });
    }

    private function toCarbon(mixed $value): ?Carbon
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
