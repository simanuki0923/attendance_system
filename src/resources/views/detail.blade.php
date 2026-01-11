{{-- resources/views/detail.blade.php --}}
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/detail.css') }}">

<div class="attendance-detail">
  <h1 class="attendance-detail__title">勤怠詳細</h1>

  @if (session('status'))
    <p class="status-message">{{ session('status') }}</p>
  @endif

  @error('application')
    <p class="form-error">{{ $message }}</p>
  @enderror

  <form
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
            <input
              type="text"
              name="start_time"
              value="{{ old('start_time', $workStartLabel) }}"
              placeholder="例) 09:00"
              inputmode="numeric"
              pattern="\d{1,2}:\d{2}"
              {{ $isPending ? 'readonly' : '' }}
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
              {{ $isPending ? 'readonly' : '' }}
            >
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
            <input
              type="text"
              name="break1_start"
              value="{{ old('break1_start', $break1StartLabel) }}"
              placeholder="例) 12:00"
              inputmode="numeric"
              pattern="\d{1,2}:\d{2}"
              {{ $isPending ? 'readonly' : '' }}
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
              {{ $isPending ? 'readonly' : '' }}
            >
          </span>
        </div>
        @error('break1_start') <p class="form-error">{{ $message }}</p> @enderror
        @error('break1_end')   <p class="form-error">{{ $message }}</p> @enderror

        <div class="attendance-detail__time-range">
          <span class="attendance-detail__time-input">
            <input
              type="text"
              name="break2_start"
              value="{{ old('break2_start', $break2StartLabel) }}"
              placeholder="例) 15:00"
              inputmode="numeric"
              pattern="\d{1,2}:\d{2}"
              {{ $isPending ? 'readonly' : '' }}
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
              {{ $isPending ? 'readonly' : '' }}
            >
          </span>
        </div>
        @error('break2_start') <p class="form-error">{{ $message }}</p> @enderror
        @error('break2_end')   <p class="form-error">{{ $message }}</p> @enderror
      </dd>
    </dl>

    <dl class="attendance-detail__row">
      <dt class="attendance-detail__label">備考</dt>
      <dd class="attendance-detail__value">
        <textarea
          name="note"
          class="attendance-detail__textarea"
          {{ $isPending ? 'readonly' : '' }}
        >{{ old('note', $noteLabel) }}</textarea>
        @error('note') <p class="form-error">{{ $message }}</p> @enderror
      </dd>
    </dl>

    <div class="attendance-detail__actions">
      <button type="submit" class="attendance-detail__button" {{ $isPending ? 'disabled' : '' }}>
        保存
      </button>
      <p class="attendance-detail__status">申請状況：{{ $statusLabel }}</p>
    </div>
  </form>
</div>
@endsection
