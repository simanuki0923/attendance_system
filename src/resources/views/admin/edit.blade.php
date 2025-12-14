{{-- resources/views/admin/edit.blade.php --}}

@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/admin-edit.css') }}">
@endsection

@section('content')
@php
    $employeeName      = $employeeName      ?? '';
    $dateYearLabel     = $dateYearLabel     ?? '';
    $dateDayLabel      = $dateDayLabel      ?? '';

    $workStartLabel    = $workStartLabel    ?? '';
    $workEndLabel      = $workEndLabel      ?? '';

    $break1StartLabel  = $break1StartLabel  ?? '';
    $break1EndLabel    = $break1EndLabel    ?? '';

    $break2StartLabel  = $break2StartLabel  ?? '';
    $break2EndLabel    = $break2EndLabel    ?? '';

    $noteLabel         = $noteLabel         ?? '';

    /**
     * ★管理者画面のロックは hasPendingApplication では判定しない
     *  - lockByPending が true の時だけロック
     *  - Controller から渡されない場合は false
     */
    $lockByPending = $lockByPending ?? false;

    $isLocked     = (bool) $lockByPending;
    $readOnlyAttr = $isLocked ? 'readonly' : '';
@endphp

@isset($attendance)
@php
    /**
     * ★更新先ルートの安全決定
     *  - 管理者用があれば優先
     *  - なければ既存の update を利用
     */
    $updateAction = null;

    if (\Illuminate\Support\Facades\Route::has('admin.attendance.detail.update')) {
        $updateAction = route('admin.attendance.detail.update', $attendance->id);
    } elseif (\Illuminate\Support\Facades\Route::has('attendance.detail.update')) {
        $updateAction = route('attendance.detail.update', $attendance->id);
    } else {
        $updateAction = '#';
    }

    // -----------------------------
    // ★行ごとの“まとめ表示”用エラー
    //  - 同じ文言が2行出るのを防ぐ
    // -----------------------------
    $workTimeError  = $errors->first('start_time') ?: $errors->first('end_time');
    $break1Error    = $errors->first('break1_start') ?: $errors->first('break1_end');
    $break2Error    = $errors->first('break2_start') ?: $errors->first('break2_end');
    $noteError      = $errors->first('note');
@endphp

<main class="attendance-detail attendance-detail--admin">
  <div class="attendance-detail__inner">

    <header class="attendance-detail__header">
      <h1 class="attendance-detail__title">
        <span class="attendance-detail__title-bar" aria-hidden="true"></span>
        <span>勤怠詳細</span>
      </h1>
    </header>

    {{-- フラッシュメッセージ --}}
    @if (session('success'))
      <p class="attendance-detail__flash">{{ session('success') }}</p>
    @endif
    @if (session('error'))
      <p class="attendance-detail__flash attendance-detail__flash--error">
        {{ session('error') }}
      </p>
    @endif


    <form method="POST" action="{{ $updateAction }}">
      @csrf
      @method('PATCH')

      <section class="attendance-detail__card" aria-label="勤怠詳細（管理者編集）">

        {{-- 名前 --}}
        <dl class="attendance-detail__row">
          <dt class="attendance-detail__label">名前</dt>
          <dd class="attendance-detail__value">
            <span class="attendance-detail__text">{{ $employeeName }}</span>
          </dd>
        </dl>

        {{-- 日付 --}}
        <dl class="attendance-detail__row">
          <dt class="attendance-detail__label">日付</dt>
          <dd class="attendance-detail__value attendance-detail__value--date">
            <span class="attendance-detail__text">{{ $dateYearLabel }}</span>
            <span class="attendance-detail__text">{{ $dateDayLabel }}</span>
          </dd>
        </dl>

        {{-- 出勤・退勤 --}}
        <dl class="attendance-detail__row">
          <dt class="attendance-detail__label">出勤・退勤</dt>
          <dd class="attendance-detail__value">
            <div class="attendance-detail__time-range">

              <div class="attendance-detail__time-input">
                <input type="text"
                       name="start_time"
                       placeholder="09:00"
                       value="{{ old('start_time', $workStartLabel) }}"
                       class="{{ $errors->has('start_time') ? 'is-invalid' : '' }}"
                       {{ $readOnlyAttr }}>
              </div>

              <span class="attendance-detail__tilde">~</span>

              <div class="attendance-detail__time-input">
                <input type="text"
                       name="end_time"
                       placeholder="18:00"
                       value="{{ old('end_time', $workEndLabel) }}"
                       class="{{ $errors->has('end_time') ? 'is-invalid' : '' }}"
                       {{ $readOnlyAttr }}>
              </div>

            </div>

            @if ($workTimeError)
              <p class="form-error">{{ $workTimeError }}</p>
            @endif
          </dd>
        </dl>

        {{-- 休憩1 --}}
        <dl class="attendance-detail__row">
          <dt class="attendance-detail__label">休憩</dt>
          <dd class="attendance-detail__value">
            <div class="attendance-detail__time-range">

              <div class="attendance-detail__time-input">
                <input type="text"
                       name="break1_start"
                       placeholder="12:00"
                       value="{{ old('break1_start', $break1StartLabel) }}"
                       class="{{ $errors->has('break1_start') ? 'is-invalid' : '' }}"
                       {{ $readOnlyAttr }}>
              </div>

              <span class="attendance-detail__tilde">~</span>

              <div class="attendance-detail__time-input">
                <input type="text"
                       name="break1_end"
                       placeholder="13:00"
                       value="{{ old('break1_end', $break1EndLabel) }}"
                       class="{{ $errors->has('break1_end') ? 'is-invalid' : '' }}"
                       {{ $readOnlyAttr }}>
              </div>

            </div>

            @if ($break1Error)
              <p class="form-error">{{ $break1Error }}</p>
            @endif
          </dd>
        </dl>

        {{-- 休憩2 --}}
        <dl class="attendance-detail__row">
          <dt class="attendance-detail__label">休憩2</dt>
          <dd class="attendance-detail__value">
            <div class="attendance-detail__time-range">

              <div class="attendance-detail__time-input">
                <input type="text"
                       name="break2_start"
                       placeholder="15:00"
                       value="{{ old('break2_start', $break2StartLabel) }}"
                       class="{{ $errors->has('break2_start') ? 'is-invalid' : '' }}"
                       {{ $readOnlyAttr }}>
              </div>

              <span class="attendance-detail__tilde">~</span>

              <div class="attendance-detail__time-input">
                <input type="text"
                       name="break2_end"
                       placeholder="15:15"
                       value="{{ old('break2_end', $break2EndLabel) }}"
                       class="{{ $errors->has('break2_end') ? 'is-invalid' : '' }}"
                       {{ $readOnlyAttr }}>
              </div>

            </div>

            @if ($break2Error)
              <p class="form-error">{{ $break2Error }}</p>
            @endif
          </dd>
        </dl>

        {{-- 備考 --}}
        <dl class="attendance-detail__row attendance-detail__row--note">
          <dt class="attendance-detail__label">備考</dt>
          <dd class="attendance-detail__value">
            <div class="attendance-detail__note">
              <input type="text"
                     name="note"
                     placeholder="備考を入力"
                     value="{{ old('note', $noteLabel) }}"
                     class="{{ $errors->has('note') ? 'is-invalid' : '' }}"
                     {{ $readOnlyAttr }}>
            </div>

            @if ($noteError)
              <p class="form-error">{{ $noteError }}</p>
            @endif
          </dd>
        </dl>

      </section>

      {{-- ボタン or ロック表示 --}}
      <div class="attendance-detail__actions attendance-detail__actions--admin">
        @if ($isLocked)
          <p class="attendance-detail__warning">
            承認待ちのため修正はできません。
          </p>
        @else
          <button type="submit"
                  class="attendance-detail__button attendance-detail__button--approve">
            修正
          </button>
        @endif
      </div>

    </form>

  </div>
</main>

@else
  <p style="padding:20px;">attendance が渡されていません。</p>
@endisset
@endsection
