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

            'note'         => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $start = $this->parseInputTime($this->input('start_time'));
            $end   = $this->parseInputTime($this->input('end_time'));

            $b1s = $this->parseInputTime($this->input('break1_start'));
            $b2s = $this->parseInputTime($this->input('break2_start'));

            // -----------------------------------------
            // 出勤 > 退勤
            // -----------------------------------------
            if ($start && $end && $start->greaterThan($end)) {
                $msg = '出勤時間もしくは退勤時間が不適切な値です';
                $v->errors()->add('start_time', $msg);
                $v->errors()->add('end_time', $msg);
            }

            // -----------------------------------------
            // 休憩開始が出勤より前 / 退勤より後
            // -----------------------------------------
            if ($b1s && $start && $b1s->lessThan($start)) {
                $v->errors()->add('break1_start', '休憩時間が不適切な値です');
            }
            if ($b1s && $end && $b1s->greaterThan($end)) {
                $v->errors()->add('break1_start', '休憩時間が不適切な値です');
            }

            if ($b2s && $start && $b2s->lessThan($start)) {
                $v->errors()->add('break2_start', '休憩時間が不適切な値です');
            }
            if ($b2s && $end && $b2s->greaterThan($end)) {
                $v->errors()->add('break2_start', '休憩時間が不適切な値です');
            }

            // -----------------------------------------
            // 休憩終了が退勤より後
            // -----------------------------------------
            $b1e = $this->parseInputTime($this->input('break1_end'));
            $b2e = $this->parseInputTime($this->input('break2_end'));

            if ($b1e && $end && $b1e->greaterThan($end)) {
                $v->errors()->add('break1_end', '休憩時間もしくは退勤時間が不適切な値です');
            }
            if ($b2e && $end && $b2e->greaterThan($end)) {
                $v->errors()->add('break2_end', '休憩時間もしくは退勤時間が不適切な値です');
            }

            // 追加の安全策：休憩終了 < 休憩開始
            if ($b1s && $b1e && $b1e->lessThan($b1s)) {
                $v->errors()->add('break1_start', '休憩時間が不適切な値です');
            }
            if ($b2s && $b2e && $b2e->lessThan($b2s)) {
                $v->errors()->add('break2_start', '休憩時間が不適切な値です');
            }
        });
    }

    /**
     * H:i の入力を Carbon に
     */
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
