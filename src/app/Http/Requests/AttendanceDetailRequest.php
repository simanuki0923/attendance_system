<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Attendance;

class AttendanceDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * 基本の入力形式チェック
     * ※時間の前後関係は withValidator で行う
     */
    public function rules(): array
    {
        return [
            'start_time'   => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'end_time'     => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],

            'break1_start' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'break1_end'   => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],

            'break2_start' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'break2_end'   => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],

            // 4-4 備考未入力でメッセージ表示
            'note'         => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            // 4-4
            'note.required' => '備考を記入してください',
        ];
    }

    /**
     * 時刻の整合性 + 承認待ちロック
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {

            $user = Auth::user();
            if (!$user) {
                return;
            }

            // ルート {id}
            $attendanceId = (int) $this->route('id');
            if ($attendanceId <= 0) {
                return;
            }

            // 対象勤怠（自分のもの）
            $attendance = Attendance::with(['applications.status'])
                ->where('user_id', $user->id)
                ->find($attendanceId);

            if (!$attendance) {
                return;
            }

            // -----------------------------------------
            // ① 承認待ちロック（UIの @error('application') に合わせる）
            // -----------------------------------------
            $latestApp = $attendance->applications
                ->sortByDesc('applied_at')
                ->first();

            if ($latestApp && $latestApp->status && $latestApp->status->code === 'pending') {
                $v->errors()->add('application', '承認待ちのため修正はできません。');
                return;
            }

            // -----------------------------------------
            // ② 出勤・退勤の前後関係（4-1）
            //   ★重複対策：
            //     start_time / end_time 両方に同じ文言を入れない
            // -----------------------------------------
            $start = $this->parseInputTime($this->input('start_time'));
            $end   = $this->parseInputTime($this->input('end_time'));

            if ($start && $end && $start->greaterThanOrEqualTo($end)) {
                $message = '出勤時間もしくは退勤時間が不適切な値です';

                // ★どちらか片方だけに付与（Bladeは両方表示でも1行になる）
                $v->errors()->add('start_time', $message);

                // end_time に付けたい場合はこちらに変更
                // $v->errors()->add('end_time', $message);
            }

            // -----------------------------------------
            // ③ 休憩開始の範囲（4-2）
            // -----------------------------------------
            $b1s = $this->parseInputTime($this->input('break1_start'));
            $b2s = $this->parseInputTime($this->input('break2_start'));

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
            // ④ 休憩終了 > 退勤（4-3）
            // -----------------------------------------
            $b1e = $this->parseInputTime($this->input('break1_end'));
            $b2e = $this->parseInputTime($this->input('break2_end'));

            if ($b1e && $end && $b1e->greaterThan($end)) {
                $v->errors()->add('break1_end', '休憩時間もしくは退勤時間が不適切な値です');
            }

            if ($b2e && $end && $b2e->greaterThan($end)) {
                $v->errors()->add('break2_end', '休憩時間もしくは退勤時間が不適切な値です');
            }
        });
    }

    /**
     * H:i の入力を Carbon に
     */
    private function parseInputTime($value): ?Carbon
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
