{{-- resources/views/admin/detail.blade.php --}}

@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/admin-detail.css') }}">
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
    $statusLabel       = $statusLabel       ?? '';
    $statusCode        = $statusCode        ?? null;
    $approveUrl        = $approveUrl        ?? '';
    $isApproved        = (bool)($isApproved ?? false);

    // approveUrl が空なら承認不可扱い（画面崩さない）
    $canApprove = (!$isApproved && is_string($approveUrl) && $approveUrl !== '');
@endphp

<main class="attendance-detail">
  <div class="attendance-detail__inner">

    <header class="attendance-detail__header">
      <h1 class="attendance-detail__title">
        <span class="attendance-detail__title-bar" aria-hidden="true"></span>
        <span>勤怠詳細</span>
      </h1>
    </header>

    <section class="attendance-detail__card" aria-label="勤怠詳細">
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
              <input type="text" value="{{ $workStartLabel }}" readonly>
            </span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-input">
              <input type="text" value="{{ $workEndLabel }}" readonly>
            </span>
          </div>
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">休憩</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__time-range">
            <span class="attendance-detail__time-input">
              <input type="text" value="{{ $break1StartLabel }}" readonly>
            </span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-input">
              <input type="text" value="{{ $break1EndLabel }}" readonly>
            </span>
          </div>
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">休憩2</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__time-range">
            <span class="attendance-detail__time-input">
              <input type="text" value="{{ $break2StartLabel }}" readonly>
            </span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-input">
              <input type="text" value="{{ $break2EndLabel }}" readonly>
            </span>
          </div>
        </dd>
      </dl>

      <dl class="attendance-detail__row attendance-detail__row--note">
        <dt class="attendance-detail__label">備考</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__note">
            <input type="text" value="{{ $noteLabel }}" readonly>
          </div>
        </dd>
      </dl>
    </section>

    <div class="attendance-detail__actions">
      @if ($isApproved)
        <button type="button"
                class="attendance-detail__button attendance-detail__button--disabled"
                disabled>承認済み</button>
      @else
        @if ($canApprove)
          <form method="POST" action="{{ $approveUrl }}">
            @csrf
            <button type="submit" class="attendance-detail__button">承認</button>
          </form>
        @else
          {{-- approveUrl が渡ってこない/承認不可のときもレイアウトを崩さない --}}
          <button type="button"
                  class="attendance-detail__button attendance-detail__button--disabled"
                  disabled>承認不可</button>
        @endif
      @endif
    </div>

  </div>
</main>
@endsection
