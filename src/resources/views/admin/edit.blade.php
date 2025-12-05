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

    $hasPendingApplication = $hasPendingApplication ?? false;
    $isLocked              = (bool)$hasPendingApplication;
    $readOnlyAttr          = $isLocked ? 'readonly' : '';
@endphp

@isset($attendance)
@php
    $updateAction = route('attendance.detail.update', $attendance->id);
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

    @if ($errors->any())
      <div class="attendance-detail__errors">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
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
                       {{ $readOnlyAttr }}>
              </div>

              <span class="attendance-detail__tilde">〜</span>

              <div class="attendance-detail__time-input">
                <input type="text"
                       name="end_time"
                       placeholder="18:00"
                       value="{{ old('end_time', $workEndLabel) }}"
                       {{ $readOnlyAttr }}>
              </div>

            </div>
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
                       {{ $readOnlyAttr }}>
              </div>

              <span class="attendance-detail__tilde">〜</span>

              <div class="attendance-detail__time-input">
                <input type="text"
                       name="break1_end"
                       placeholder="13:00"
                       value="{{ old('break1_end', $break1EndLabel) }}"
                       {{ $readOnlyAttr }}>
              </div>

            </div>
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
                       {{ $readOnlyAttr }}>
              </div>

              <span class="attendance-detail__tilde">〜</span>

              <div class="attendance-detail__time-input">
                <input type="text"
                       name="break2_end"
                       placeholder="15:15"
                       value="{{ old('break2_end', $break2EndLabel) }}"
                       {{ $readOnlyAttr }}>
              </div>

            </div>
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
                     {{ $readOnlyAttr }}>
            </div>
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
