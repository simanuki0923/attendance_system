{{-- resources/views/admin/list.blade.php --}}

@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/admin_list.css') }}">
@endsection

@section('content')
@php
    /**
     * 管理者用 勤怠一覧（1日分）の想定パラメータ
     *
     * @var string $currentDateLabel 表示用: '2023年6月1日(木)' など
     * @var string $currentDateYmd   日付ナビ中央の表示用: '2023/06/01'
     * @var string|null $prevDateUrl 前日へのURL。なければ null
     * @var string|null $nextDateUrl 翌日へのURL。なければ null
     *
     * @var \Illuminate\Support\Collection|array<array{
     *   name_label:string,   // 例: '山田 太郎'
     *   start_label:string,  // 例: '09:00'
     *   end_label:string,    // 例: '18:00'
     *   break_label:string,  // 例: '1:00'
     *   total_label:string,  // 例: '8:00'
     *   detail_url:?string,  // 詳細画面へのURL / null
     *   is_active?:bool      // true の行は青枠で強調表示
     * }> $attendances
     */

    $currentDateLabel = $currentDateLabel
        ?? now()->locale('ja')->isoFormat('YYYY年M月D日(ddd)');
    $currentDateYmd   = $currentDateYmd   ?? now()->format('Y/m/d');
    $attendances      = $attendances      ?? [];
    $prevDateUrl      = $prevDateUrl      ?? null;
    $nextDateUrl      = $nextDateUrl      ?? null;
@endphp

<main class="attendance-list attendance-list--admin">

  <div class="attendance-list__inner">

    {{-- タイトル --}}
    <header class="attendance-list__header">
      <h1 class="attendance-list__title">
        <span class="attendance-list__title-bar"></span>
        {{ $currentDateLabel }}の勤怠
      </h1>

      {{-- 日付切替ナビ（layouts/list.blade.php と同じクラス構成） --}}
      <div class="attendance-list__month-nav">
        @if(!empty($prevDateUrl))
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

        @if(!empty($nextDateUrl))
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

    {{-- 一覧テーブル --}}
    <section class="attendance-list__table" aria-label="勤怠一覧（管理者）">
      {{-- ヘッダー行 --}}
      <div class="attendance-list__row attendance-list__row--head">
        <div class="attendance-list__cell attendance-list__cell--name">名前</div>
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
          <div class="attendance-list__cell attendance-list__cell--name">
            {{ $row['name_label'] ?? '' }}
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

  </div>
</main>
@endsection
