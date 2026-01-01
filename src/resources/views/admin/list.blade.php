@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/admin-list.css') }}">
@endsection

@section('content')
@php
    $now = now()->locale('ja');

    $currentDateLabel = $currentDateLabel
        ?? $now->isoFormat('YYYY年M月D日(ddd)');

    $currentDateYmd = $currentDateYmd
        ?? $now->format('Y/m/d');

    // Collection / array / null どれでもOKにする
    if (!isset($attendances) || $attendances === null) {
        $attendances = [];
    } elseif ($attendances instanceof \Illuminate\Support\Collection) {
        $attendances = $attendances->all();
    }

    $prevDateUrl = $prevDateUrl ?? null;
    $nextDateUrl = $nextDateUrl ?? null;
@endphp

<main class="attendance-list attendance-list--admin">
  <div class="attendance-list__inner">

    <header class="attendance-list__header">
      <h1 class="attendance-list__title">
        <span class="attendance-list__title-bar"></span>
        {{ $currentDateLabel }}の勤怠
      </h1>

      <div class="attendance-list__month-nav">
        @if (!empty($prevDateUrl))
          <a href="{{ $prevDateUrl }}" class="month-nav__btn month-nav__btn--prev">
            <span class="month-nav__arrow" aria-hidden="true">&larr;</span>
            前日
          </a>
        @else
          <span class="month-nav__btn month-nav__btn--prev month-nav__btn--disabled">
            <span class="month-nav__arrow" aria-hidden="true">&larr;</span>
            前日
          </span>
        @endif

        <div class="month-nav__current">
          <span class="month-nav__icon" aria-hidden="true"></span>
          <span class="month-nav__label">{{ $currentDateYmd }}</span>
        </div>

        @if (!empty($nextDateUrl))
          <a href="{{ $nextDateUrl }}" class="month-nav__btn month-nav__btn--next">
            翌日
            <span class="month-nav__arrow" aria-hidden="true">&rarr;</span>
          </a>
        @else
          <span class="month-nav__btn month-nav__btn--next month-nav__btn--disabled">
            翌日
            <span class="month-nav__arrow" aria-hidden="true">&rarr;</span>
          </span>
        @endif
      </div>
    </header>

    <section class="attendance-list__table" aria-label="勤怠一覧（管理者）">
      <div class="attendance-list__row attendance-list__row--head">
        <div class="attendance-list__cell attendance-list__cell--name">名前</div>
        <div class="attendance-list__cell">出勤</div>
        <div class="attendance-list__cell">退勤</div>
        <div class="attendance-list__cell">休憩</div>
        <div class="attendance-list__cell">合計</div>
        <div class="attendance-list__cell attendance-list__cell--detail">詳細</div>
      </div>

      @forelse($attendances as $row)
        @php
          // $row が object の可能性も吸収
          $row = is_array($row) ? $row : (array) $row;

          $isActive = !empty($row['is_active']);

          // id キーの揺れ吸収（attendance_id / id）
          $attendanceId = $row['attendance_id'] ?? ($row['id'] ?? null);

          // 管理者詳細：/admin/attendance/{id}
          $detailUrl = $attendanceId
              ? route('admin.attendance.detail', ['id' => $attendanceId])
              : null;
        @endphp

        <div class="attendance-list__row {{ $isActive ? 'attendance-list__row--active' : '' }}">
          <div class="attendance-list__cell attendance-list__cell--name">
            {{ $row['name_label'] ?? '' }}
          </div>
          <div class="attendance-list__cell">{{ $row['start_label'] ?? '' }}</div>
          <div class="attendance-list__cell">{{ $row['end_label'] ?? '' }}</div>
          <div class="attendance-list__cell">{{ $row['break_label'] ?? '' }}</div>
          <div class="attendance-list__cell">{{ $row['total_label'] ?? '' }}</div>

          <div class="attendance-list__cell attendance-list__cell--detail">
            @if (!empty($detailUrl))
              <a href="{{ $detailUrl }}" class="attendance-list__detail-link">詳細</a>
            @else
              <span class="attendance-list__detail-link attendance-list__detail-link--disabled">詳細</span>
            @endif
          </div>
        </div>
      @empty
        {{-- CSSの行レイアウトを崩さないため、row構造で空表示 --}}
        <div class="attendance-list__row">
          <div class="attendance-list__cell attendance-list__cell--name"></div>
          <div class="attendance-list__cell"></div>
          <div class="attendance-list__cell"></div>
          <div class="attendance-list__cell"></div>
          <div class="attendance-list__cell"></div>
          <div class="attendance-list__cell attendance-list__cell--detail"></div>
        </div>
        <p class="attendance-list__empty">表示できる勤怠データがありません。</p>
      @endforelse
    </section>

  </div>
</main>
@endsection
