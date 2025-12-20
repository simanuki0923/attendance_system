@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/staff-id.css') }}">
@endsection

@section('content')
@php
    $staffNameLabel    = $staffNameLabel    ?? '';
    $currentMonthLabel = $currentMonthLabel ?? '';
    $prevMonthUrl      = $prevMonthUrl      ?? null;
    $nextMonthUrl      = $nextMonthUrl      ?? null;
    $attendances       = $attendances       ?? collect();
    $csvDownloadUrl    = $csvDownloadUrl    ?? null;
@endphp

<main class="attendance-list attendance-list--staff">
  <div class="attendance-list__inner">

    <header class="attendance-list__header">
      <h1 class="attendance-list__title">
        <span class="attendance-list__title-bar"></span>
        <span class="attendance-list__title-text">
          {{ $staffNameLabel }}さんの勤怠
        </span>
      </h1>

      <div class="attendance-list__month-nav">
        @if (! empty($prevMonthUrl))
          <a href="{{ $prevMonthUrl }}" class="month-nav__btn month-nav__btn--prev">
            <span class="month-nav__arrow" aria-hidden="true">&larr;</span>
            前月
          </a>
        @else
          <span class="month-nav__btn month-nav__btn--prev month-nav__btn--disabled">
            <span class="month-nav__arrow" aria-hidden="true">&larr;</span>
            前月
          </span>
        @endif

        <div class="month-nav__current">
          <span class="month-nav__icon" aria-hidden="true"></span>
          <span class="month-nav__label">{{ $currentMonthLabel }}</span>
        </div>

        @if (! empty($nextMonthUrl))
          <a href="{{ $nextMonthUrl }}" class="month-nav__btn month-nav__btn--next">
            翌月
            <span class="month-nav__arrow" aria-hidden="true">&rarr;</span>
          </a>
        @else
          <span class="month-nav__btn month-nav__btn--next month-nav__btn--disabled">
            翌月
            <span class="month-nav__arrow" aria-hidden="true">&rarr;</span>
          </span>
        @endif
      </div>
    </header>

    <section class="attendance-list__table attendance-list__table--staff" aria-label="スタッフ別勤怠一覧">

      <div class="attendance-list__row attendance-list__row--head">
        <div class="attendance-list__cell attendance-list__cell--date">日付</div>
        <div class="attendance-list__cell">出勤</div>
        <div class="attendance-list__cell">退勤</div>
        <div class="attendance-list__cell">休憩</div>
        <div class="attendance-list__cell">合計</div>
        <div class="attendance-list__cell attendance-list__cell--detail">詳細</div>
      </div>

      @forelse($attendances as $row)
        @php $isActive = !empty($row['is_active']); @endphp

        <div class="attendance-list__row {{ $isActive ? 'attendance-list__row--active' : '' }}">
          <div class="attendance-list__cell attendance-list__cell--date">
            {{ $row['date_label'] ?? '' }}
          </div>
          <div class="attendance-list__cell">
            {{ $row['start_label'] ?? '' }}
          </div>
          <div class="attendance-list__cell">
            {{ $row['end_label'] ?? '' }}
          </div>
          <div class="attendance-list__cell">
            {{ $row['break_label'] ?? '' }}
          </div>
          <div class="attendance-list__cell">
            {{ $row['total_label'] ?? '' }}
          </div>
          <div class="attendance-list__cell attendance-list__cell--detail">
            @if (!empty($row['detail_url']))
              <a href="{{ $row['detail_url'] }}" class="attendance-list__detail-link">
                詳細
              </a>
            @else
              <span class="attendance-list__detail-link attendance-list__detail-link--disabled">
                詳細
              </span>
            @endif
          </div>
        </div>
      @empty
        <p class="attendance-list__empty">表示できる勤怠データがありません。</p>
      @endforelse
    </section>

    <div class="attendance-list__footer">
      @if (!empty($csvDownloadUrl))
        <a href="{{ $csvDownloadUrl }}" class="attendance-list__csv-btn">CSV出力</a>
      @else
        <button type="button" class="attendance-list__csv-btn" disabled>CSV出力</button>
      @endif
    </div>
  </div>
</main>
@endsection
