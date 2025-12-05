{{-- resources/views/list.blade.php --}}
{{-- 一般ユーザー用 勤怠一覧（月別） --}}

@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/list.css') }}">
@endsection

{{-- ヘッダーの状態（必要に応じて変更 OK） --}}
@section('header_type', 'working')

@section('content')
@php
    /**
     * @var string $currentMonthLabel 例: '2025/11'
     * @var string $prevMonthUrl      前月URL
     * @var string|null $nextMonthUrl 翌月URL（未来の月は null）
     * @var array<array{
     *   date_label:string,
     *   start_label:string,
     *   end_label:string,
     *   break_label:string,
     *   total_label:string,
     *   detail_url:string,
     *   is_active:bool
     * }> $attendances
     */
@endphp

<main class="attendance-list">
  <div class="attendance-list__inner">

    {{-- タイトル周り --}}
    <header class="attendance-list__header">
      <h1 class="attendance-list__title">
        <span class="attendance-list__title-bar"></span>
        勤怠一覧
      </h1>

      {{-- 月切替ナビ（CSS に合わせた構造） --}}
      <div class="attendance-list__month-nav">
        {{-- 前月ボタン --}}
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

        {{-- 真ん中のカレンダー表示 --}}
        <div class="month-nav__current">
          <span class="month-nav__icon" aria-hidden="true"></span>
          <span class="month-nav__label">{{ $currentMonthLabel }}</span>
        </div>

        {{-- 翌月ボタン（未来すぎる月は null なので disabled） --}}
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

    {{-- 一覧本体（list.css に合わせて div＋grid 構造） --}}
    <section class="attendance-list__table">

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
            $rowClasses = 'attendance-list__row';
            if (!empty($row['is_active'])) {
                $rowClasses .= ' attendance-list__row--active';
            }
        @endphp
        <div class="{{ $rowClasses }}">
          <div class="attendance-list__cell attendance-list__cell--date">
            {{ $row['date_label'] }}
          </div>
          <div class="attendance-list__cell">
            {{ $row['start_label'] }}
          </div>
          <div class="attendance-list__cell">
            {{ $row['end_label'] }}
          </div>
          <div class="attendance-list__cell">
            {{ $row['break_label'] }}
          </div>
          <div class="attendance-list__cell">
            {{ $row['total_label'] }}
          </div>
          <div class="attendance-list__cell attendance-list__cell--detail">
            @if(!empty($row['detail_url']))
              <a href="{{ $row['detail_url'] }}" class="attendance-list__detail-link">
                詳細
              </a>
            @else
              {{-- 万が一 detail_url が空の場合（今の設計だと基本は発生しない想定） --}}
              <span class="attendance-list__detail-link attendance-list__detail-link--disabled">
                詳細
              </span>
            @endif
          </div>
        </div>
      @empty
        {{-- データ無しメッセージ --}}
        <p class="attendance-list__empty">
          該当月の勤怠情報はありません。
        </p>
      @endforelse

    </section>

  </div>
</main>
@endsection
