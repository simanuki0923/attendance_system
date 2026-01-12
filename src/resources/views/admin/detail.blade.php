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
    $isApproved        = (bool) ($isApproved ?? false);

    // approveUrl が空なら承認不可扱い（画面崩さない）
    $canApprove = (! $isApproved && is_string($approveUrl) && $approveUrl !== '');

    // 承認済みの場合はヘッダーを非表示にする
    $showHeader = ! $isApproved;
@endphp

<main class="attendance-detail">
  <div class="attendance-detail__inner">

    @if ($showHeader)
      <header class="attendance-detail__header">
        <h1 class="attendance-detail__title">
          <span class="attendance-detail__title-bar" aria-hidden="true"></span>
          <span>勤怠詳細</span>
        </h1>
      </header>
    @endif

    @if (session('status'))
      <p class="attendance-detail__flash" role="status">{{ session('status') }}</p>
    @endif

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
            <span class="attendance-detail__time-text">{{ $workStartLabel }}</span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-text">{{ $workEndLabel }}</span>
          </div>
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">休憩</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__time-range">
            <span class="attendance-detail__time-text">{{ $break1StartLabel }}</span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-text">{{ $break1EndLabel }}</span>
          </div>
        </dd>
      </dl>

      <dl class="attendance-detail__row">
        <dt class="attendance-detail__label">休憩2</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__time-range">
            <span class="attendance-detail__time-text">{{ $break2StartLabel }}</span>
            <span class="attendance-detail__tilde">~</span>
            <span class="attendance-detail__time-text">{{ $break2EndLabel }}</span>
          </div>
        </dd>
      </dl>

      <dl class="attendance-detail__row attendance-detail__row--note">
        <dt class="attendance-detail__label">備考</dt>
        <dd class="attendance-detail__value">
          <span class="attendance-detail__text">{{ $noteLabel }}</span>
        </dd>
      </dl>
    </section>

    <div class="attendance-detail__actions">
      @if ($canApprove)
        <form method="POST" action="{{ $approveUrl }}">
          @csrf
          <button type="submit" class="attendance-detail__button">承認</button>
        </form>

      @elseif ($isApproved)
        {{-- 承認済みの場合：ボタン表示＋操作不能 --}}
        <button
          type="button"
          class="attendance-detail__button attendance-detail__button--disabled"
          disabled
          aria-disabled="true"
        >
          承認済み
        </button>

      @else
        <p class="attendance-detail__warning">承認できません。</p>
      @endif
    </div>

  </div>
</main>
@endsection
