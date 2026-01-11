@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/list.css') }}">
@endsection

@section('header_type', 'working')

@section('content')
<main class="attendance-list">
  <div class="attendance-list__inner">

    <header class="attendance-list__header">
      <h1 class="attendance-list__title">
        <span class="attendance-list__title-bar"></span>
        勤怠一覧
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

    <section class="attendance-list__table" aria-label="勤怠一覧">
      <table class="attendance-list__table-inner">
        <colgroup>
          <col style="width:22%">
          <col style="width:14%">
          <col style="width:14%">
          <col style="width:14%">
          <col style="width:14%">
          <col style="width:10%">
        </colgroup>

        <thead>
          <tr class="attendance-list__row attendance-list__row--head">
            <th scope="col" class="attendance-list__cell attendance-list__cell--date">日付</th>
            <th scope="col" class="attendance-list__cell">出勤</th>
            <th scope="col" class="attendance-list__cell">退勤</th>
            <th scope="col" class="attendance-list__cell">休憩</th>
            <th scope="col" class="attendance-list__cell">合計</th>
            <th scope="col" class="attendance-list__cell attendance-list__cell--detail">詳細</th>
          </tr>
        </thead>

        <tbody>
          @forelse($attendances as $row)
            <tr @class([
              'attendance-list__row',
              'attendance-list__row--active' => !empty($row['is_active']),
            ])>
              <td class="attendance-list__cell attendance-list__cell--date">{{ $row['date_label'] }}</td>
              <td class="attendance-list__cell">{{ $row['start_label'] }}</td>
              <td class="attendance-list__cell">{{ $row['end_label'] }}</td>
              <td class="attendance-list__cell">{{ $row['break_label'] }}</td>
              <td class="attendance-list__cell">{{ $row['total_label'] }}</td>
              <td class="attendance-list__cell attendance-list__cell--detail">
                @if (! empty($row['detail_url']))
                  <a href="{{ $row['detail_url'] }}" class="attendance-list__detail-link">詳細</a>
                @else
                  <span class="attendance-list__detail-link attendance-list__detail-link--disabled">詳細</span>
                @endif
              </td>
            </tr>
          @empty
            <tr class="attendance-list__row">
              <td class="attendance-list__cell attendance-list__cell--empty" colspan="6">
                <p class="attendance-list__empty">今月の勤怠情報はありません。</p>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </section>

  </div>
</main>
@endsection
