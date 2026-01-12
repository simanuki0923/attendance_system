@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/detail.css') }}">
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
    $isPending         = (bool) ($isPending ?? false);
    $attendanceId      = $attendanceId      ?? null;

    // id が無い場合も編集不可扱いにする（route生成エラー防止）
    $canEdit = (! $isPending) && (! empty($attendanceId));
    $readOnlyAttr = $canEdit ? '' : 'readonly';

    // route の必須パラメータ不足で落ちないように action を安全に生成
    $updateAction = ! empty($attendanceId)
        ? route('attendance.detail.update', ['id' => $attendanceId])
        : '#';

    $workTimeError  = $errors->first('start_time') ?: $errors->first('end_time');
    $break1Error    = $errors->first('break1_start') ?: $errors->first('break1_end');
    $break2Error    = $errors->first('break2_start') ?: $errors->first('break2_end');
    $noteError      = $errors->first('note');
    $applicationError = $errors->first('application');
@endphp

<main class="attendance-detail {{ $isPending ? 'attendance-detail--pending' : '' }}">
  <div class="attendance-detail__inner">

    <header class="attendance-detail__header">
      <h1 class="attendance-detail__title">
        <span class="attendance-detail__title-bar" aria-hidden="true"></span>
        <span>勤怠詳細</span>
      </h1>
    </header>

    @if (session('status'))
      <p class="attendance-detail__flash" role="status">{{ session('status') }}</p>
    @endif

    <form
      id="attendance-detail-form"
      class="attendance-detail__card"
      aria-label="勤怠詳細"
      method="POST"
      action="{{ $updateAction }}"
    >
      @csrf
      @method('PATCH')

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">名前</dt>
        <dd class="attendance-detail__value">
          <span class="attendance-detail__text">{{ $employeeName }}</span>
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">日付</dt>
        <dd class="attendance-detail__value attendance-detail__value--date">
          <span class="attendance-detail__text">{{ $dateYearLabel }}</span>
          <span class="attendance-detail__text">{{ $dateDayLabel }}</span>
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">出勤・退勤</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__time-range">
            <span class="attendance-detail__time-input">
              <input
                type="text"
                name="start_time"
                value="{{ old('start_time', $workStartLabel) }}"
                placeholder="例) 09:00"
                inputmode="numeric"
                pattern="\d{1,2}:\d{2}"
                class="{{ $errors->has('start_time') ? 'is-invalid' : '' }}"
                {{ $readOnlyAttr }}
              >
            </span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-input">
              <input
                type="text"
                name="end_time"
                value="{{ old('end_time', $workEndLabel) }}"
                placeholder="例) 18:00"
                inputmode="numeric"
                pattern="\d{1,2}:\d{2}"
                class="{{ $errors->has('end_time') ? 'is-invalid' : '' }}"
                {{ $readOnlyAttr }}
              >
            </span>
          </div>

          @if ($workTimeError)
            <p class="form-error">{{ $workTimeError }}</p>
          @endif
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">休憩</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__time-range">
            <span class="attendance-detail__time-input">
              <input
                type="text"
                name="break1_start"
                value="{{ old('break1_start', $break1StartLabel) }}"
                placeholder="例) 12:00"
                inputmode="numeric"
                pattern="\d{1,2}:\d{2}"
                class="{{ $errors->has('break1_start') ? 'is-invalid' : '' }}"
                {{ $readOnlyAttr }}
              >
            </span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-input">
              <input
                type="text"
                name="break1_end"
                value="{{ old('break1_end', $break1EndLabel) }}"
                placeholder="例) 13:00"
                inputmode="numeric"
                pattern="\d{1,2}:\d{2}"
                class="{{ $errors->has('break1_end') ? 'is-invalid' : '' }}"
                {{ $readOnlyAttr }}
              >
            </span>
          </div>

          @if ($break1Error)
            <p class="form-error">{{ $break1Error }}</p>
          @endif
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">休憩2</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__time-range">
            <span class="attendance-detail__time-input">
              <input
                type="text"
                name="break2_start"
                value="{{ old('break2_start', $break2StartLabel) }}"
                placeholder="例) 15:00"
                inputmode="numeric"
                pattern="\d{1,2}:\d{2}"
                class="{{ $errors->has('break2_start') ? 'is-invalid' : '' }}"
                {{ $readOnlyAttr }}
              >
            </span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-input">
              <input
                type="text"
                name="break2_end"
                value="{{ old('break2_end', $break2EndLabel) }}"
                placeholder="例) 15:15"
                inputmode="numeric"
                pattern="\d{1,2}:\d{2}"
                class="{{ $errors->has('break2_end') ? 'is-invalid' : '' }}"
                {{ $readOnlyAttr }}
              >
            </span>
          </div>

          @if ($break2Error)
            <p class="form-error">{{ $break2Error }}</p>
          @endif
        </dd>
      </dl>

      <dl class="attendance-detail__row attendance-detail__row--note">
        <dt class="attendance-detail__label">備考</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__note">
            <input
              type="text"
              name="note"
              value="{{ old('note', $noteLabel) }}"
              class="{{ $errors->has('note') ? 'is-invalid' : '' }}"
              {{ $readOnlyAttr }}
            >
          </div>

          @if ($noteError)
            <p class="form-error">{{ $noteError }}</p>
          @endif
        </dd>
      </dl>
    </form>

    <div class="attendance-detail__actions">

      @if ($applicationError)
        <p class="attendance-detail__warning">{{ $applicationError }}</p>
      @endif

      @if (empty($attendanceId))
        <p class="attendance-detail__warning">勤怠データが見つかりません。</p>
      @elseif ($isPending)
        <p class="attendance-detail__warning">承認待ちのため修正はできません。</p>
      @else
        <button type="submit" class="attendance-detail__button" form="attendance-detail-form">
          修正
        </button>
      @endif
    </div>

  </div>
</main>
@endsection
