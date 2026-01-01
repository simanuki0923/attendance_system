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
    $isPending         = (bool)($isPending  ?? false);
    $attendanceId      = $attendanceId      ?? null;

    $canEdit = ! $isPending;
@endphp

<main class="attendance-detail {{ $isPending ? 'attendance-detail--pending' : '' }}">
  <div class="attendance-detail__inner">

    <header class="attendance-detail__header">
      <h1 class="attendance-detail__title">
        <span class="attendance-detail__title-bar" aria-hidden="true"></span>
        <span>勤怠詳細</span>
      </h1>
    </header>

    <form
      id="attendance-detail-form"
      class="attendance-detail__card"
      aria-label="勤怠詳細"
      method="POST"
      action="{{ route('attendance.detail.update', ['id' => $attendanceId]) }}"
    >
      @csrf
      @method('PATCH')

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">氏名</dt>
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
              <input type="text" name="start_time" value="{{ old('start_time', $workStartLabel) }}"
                     placeholder="例) 09:00" inputmode="numeric" pattern="\d{1,2}:\d{2}"
                     {{ $canEdit ? '' : 'readonly' }}>
            </span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-input">
              <input type="text" name="end_time" value="{{ old('end_time', $workEndLabel) }}"
                     placeholder="例) 18:00" inputmode="numeric" pattern="\d{1,2}:\d{2}"
                     {{ $canEdit ? '' : 'readonly' }}>
            </span>
          </div>
          @error('start_time') <p class="form-error">{{ $message }}</p> @enderror
          @error('end_time')   <p class="form-error">{{ $message }}</p> @enderror
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">休憩</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__time-range">
            <span class="attendance-detail__time-input">
              <input type="text" name="break1_start" value="{{ old('break1_start', $break1StartLabel) }}"
                     placeholder="例) 12:00" inputmode="numeric" pattern="\d{1,2}:\d{2}"
                     {{ $canEdit ? '' : 'readonly' }}>
            </span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-input">
              <input type="text" name="break1_end" value="{{ old('break1_end', $break1EndLabel) }}"
                     placeholder="例) 13:00" inputmode="numeric" pattern="\d{1,2}:\d{2}"
                     {{ $canEdit ? '' : 'readonly' }}>
            </span>
          </div>
          @error('break1_start') <p class="form-error">{{ $message }}</p> @enderror
          @error('break1_end')   <p class="form-error">{{ $message }}</p> @enderror
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">休憩2</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__time-range">
            <span class="attendance-detail__time-input">
              <input type="text" name="break2_start" value="{{ old('break2_start', $break2StartLabel) }}"
                     placeholder="例) 15:00" inputmode="numeric" pattern="\d{1,2}:\d{2}"
                     {{ $canEdit ? '' : 'readonly' }}>
            </span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-input">
              <input type="text" name="break2_end" value="{{ old('break2_end', $break2EndLabel) }}"
                     placeholder="例) 15:15" inputmode="numeric" pattern="\d{1,2}:\d{2}"
                     {{ $canEdit ? '' : 'readonly' }}>
            </span>
          </div>
          @error('break2_start') <p class="form-error">{{ $message }}</p> @enderror
          @error('break2_end')   <p class="form-error">{{ $message }}</p> @enderror
        </dd>
      </dl>

      <dl class="attendance-detail__row attendance-detail__row--note">
        <dt class="attendance-detail__label">備考</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__note">
            <input type="text" name="note" value="{{ old('note', $noteLabel) }}" {{ $canEdit ? '' : 'readonly' }}>
          </div>
          @error('note') <p class="form-error">{{ $message }}</p> @enderror
        </dd>
      </dl>
    </form>

    <div class="attendance-detail__actions">
      @error('application')
        <p class="attendance-detail__warning">{{ $message }}</p>
      @enderror

      @if ($isPending)
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
