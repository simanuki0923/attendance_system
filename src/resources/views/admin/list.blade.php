@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/admin-list.css') }}">
@endsection

@section('content')
@php
  $now = now()->locale('ja');

  $currentDateLabel = $currentDateLabel ?? $now->isoFormat('YYYY年M月D日');
  $currentDateYmd = $currentDateYmd ?? $now->format('Y/m/d');

  $attendances = $attendances ?? collect();
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
        <a href="{{ $prevDateUrl }}" class="month-nav__btn month-nav__btn--prev">
          <span class="month-nav__arrow" aria-hidden="true">&larr;</span>
          前日
        </a>

        <div class="month-nav__current">
          <span class="month-nav__icon" aria-hidden="true"></span>
          <span class="month-nav__label">{{ $currentDateYmd }}</span>
        </div>

        <a href="{{ $nextDateUrl }}" class="month-nav__btn month-nav__btn--next">
          翌日
          <span class="month-nav__arrow" aria-hidden="true">&rarr;</span>
        </a>
      </div>
    </header>

    <section class="attendance-list__table" aria-label="勤怠一覧（管理者）">
      <table class="attendance-list__table-inner">
        <colgroup>
          <col style="width: 25%">
          <col style="width: 16%">
          <col style="width: 16%">
          <col style="width: 16%">
          <col style="width: 16%">
          <col style="width: 11%">
        </colgroup>

        <thead>
          <tr class="attendance-list__row attendance-list__row--head">
            <th scope="col" class="attendance-list__cell attendance-list__cell--name">名前</th>
            <th scope="col" class="attendance-list__cell">出勤</th>
            <th scope="col" class="attendance-list__cell">退勤</th>
            <th scope="col" class="attendance-list__cell">休憩</th>
            <th scope="col" class="attendance-list__cell">合計</th>
            <th scope="col" class="attendance-list__cell attendance-list__cell--detail">詳細</th>
          </tr>
        </thead>

        <tbody>
          @forelse($attendances as $row)
            @php
              $row = is_array($row) ? $row : (array) $row;

              $isActive = !empty($row['is_active']);
              $attendanceId = $row['attendance_id'] ?? null;

              $detailUrl = $attendanceId
                  ? route('admin.attendance.detail', ['id' => $attendanceId])
                  : null;
            @endphp

            <tr @class(['attendance-list__row', 'attendance-list__row--active' => $isActive])>
              <td class="attendance-list__cell attendance-list__cell--name">{{ $row['name_label'] ?? '' }}</td>
              <td class="attendance-list__cell">{{ $row['start_label'] ?? '' }}</td>
              <td class="attendance-list__cell">{{ $row['end_label'] ?? '' }}</td>
              <td class="attendance-list__cell">{{ $row['break_label'] ?? '' }}</td>
              <td class="attendance-list__cell">{{ $row['total_label'] ?? '' }}</td>

              <td class="attendance-list__cell attendance-list__cell--detail">
                @if (!empty($detailUrl))
                  <a href="{{ $detailUrl }}" class="attendance-list__detail-link">詳細</a>
                @else
                  <span class="attendance-list__detail-link attendance-list__detail-link--disabled">詳細</span>
                @endif
              </td>
            </tr>
          @empty
            <tr class="attendance-list__row">
              <td class="attendance-list__cell attendance-list__cell--empty" colspan="6">
                <p class="attendance-list__empty">表示できる勤怠データがありません。</p>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </section>

  </div>
</main>
@endsection
