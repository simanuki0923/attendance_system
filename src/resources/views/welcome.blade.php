{{-- resources/views/admin/staff_id.blade.php --}}

@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/staff_id.css') }}">
@endsection

@section('content')
@php
    /**
     * 管理者用 スタッフ別勤怠一覧（1ヶ月分）の想定パラメータ
     *
     * @var string $staffNameLabel    表示用スタッフ名   例: '西谷奈'
     * @var string $currentMonthLabel 月表示              例: '2023/06'
     * @var string|null $prevMonthUrl 前月へのURL。なければ null
     * @var string|null $nextMonthUrl 翌月へのURL。なければ null
     *
     * @var \Illuminate\Support\Collection|array<array{
     *   date_label:string,   // 例: '06/01(木)'
     *   start_label:string,  // 例: '09:00'
     *   end_label:string,    // 例: '18:00'
     *   break_label:string,  // 例: '1:00'
     *   total_label:string,  // 例: '8:00'
     *   detail_url:?string,  // 詳細画面へのURL / null
     *   is_active?:bool      // true の行は青枠で強調表示
     * }> $attendances
     */

    $staffNameLabel    = $staffNameLabel    ?? 'スタッフ名';
    $currentMonthLabel = $currentMonthLabel ?? now()->format('Y/m');
    $attendances       = $attendances       ?? [];
    $prevMonthUrl      = $prevMonthUrl      ?? null;
    $nextMonthUrl      = $nextMonthUrl      ?? null;
@endphp

<main class="attendance-list attendance-list--staff">

  <div class="attendance-list__inner">

    {{-- タイトル --}}
    <header class="attendance-list__header">
      <h1 class="attendance-list__title">
        <span class="attendance-list__title-bar"></span>
        {{ $staffNameLabel }}さんの勤務
      </h1>

      {{-- 月切替ナビ（list.blade.php と同じ構成） --}}
      <div class="attendance-list__month-nav">
        @if(!empty($prevMonthUrl))
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

        @if(!empty($nextMonthUrl))
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

    {{-- 一覧テーブル --}}
    <section class="attendance-list__table attendance-list__table--staff"
             aria-label="スタッフ別勤怠一覧">
      {{-- ヘッダー行 --}}
      <div class="attendance-list__row attendance-list__row--head">
        <div class="attendance-list__cell attendance-list__cell--date">日付</div>
        <div class="attendance-list__cell">出勤</div>
        <div class="attendance-list__cell">退勤</div>
        <div class="attendance-list__cell">休憩</div>
        <div class="attendance-list__cell">合計</div>
        <div class="attendance-list__cell attendance-list__cell--detail">詳細</div>
      </div>

      {{-- データ行 --}}
      @forelse($attendances as $row)
        @php
          $isActive = !empty($row['is_active']);
        @endphp

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
              <a href="{{ $row['detail_url'] }}"
                 class="attendance-list__detail-link">
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

    {{-- CSV出力ボタン --}}
    <div class="attendance-list__footer">
      <button type="button" class="attendance-list__csv-btn">
        CSV出力
      </button>
    </div>

  </div>
</main>
@endsection
