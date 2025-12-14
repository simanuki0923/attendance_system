
{{-- resources/views/admin/detail.blade.php --}}

@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/admin-detail.css') }}">
@endsection

@section('content')
@php
    /**
     * 勤怠詳細（管理者用 修正申請承認画面）想定パラメータ
     *
     * コントローラ(AdminRequestController@showApprove)側で
     * ラベル文字列・承認状態を作って渡す前提。
     *
     * @var string      $employeeName        名前ラベル
     * @var string      $dateYearLabel       年部分ラベル   例: '2023年'
     * @var string      $dateDayLabel        月日部分ラベル 例: '6月1日'
     * @var string|null $workStartLabel      出勤時刻       例: '09:00'
     * @var string|null $workEndLabel        退勤時刻       例: '18:00'
     * @var string|null $break1StartLabel    休憩1開始      例: '12:00'
     * @var string|null $break1EndLabel      休憩1終了      例: '13:00'
     * @var string|null $break2StartLabel    休憩2開始
     * @var string|null $break2EndLabel      休憩2終了
     * @var string|null $noteLabel           備考
     * @var string|null $statusLabel         ステータス表示用ラベル（承認待ち／承認済み など）
     * @var string|null $statusCode          ステータスコード（pending / approved など・必要なら）
     * @var string|null $approveUrl          承認ボタンのPOST先URL（未承認時のみセット）
     * @var bool        $isApproved          既に承認済みなら true
     */

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
@endphp

<main class="attendance-detail">
  <div class="attendance-detail__inner">

    {{-- タイトル --}}
    <header class="attendance-detail__header">
      <h1 class="attendance-detail__title">
        <span class="attendance-detail__title-bar" aria-hidden="true"></span>
        <span>勤怠詳細</span>
      </h1>
    </header>

    {{-- 詳細カード（全て readonly 表示専用） --}}
    <section class="attendance-detail__card" aria-label="勤怠詳細">
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

      {{-- 休憩1 --}}
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

      {{-- 休憩2 --}}
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

      {{-- 備考 --}}
      <dl class="attendance-detail__row attendance-detail__row--note">
        <dt class="attendance-detail__label">備考</dt>
        <dd class="attendance-detail__value">
          <div class="attendance-detail__note">
            <input type="text" value="{{ $noteLabel }}" readonly>
          </div>
        </dd>
      </dl>
    </section>

    {{-- 承認 or 承認済みボタン --}}
    <div class="attendance-detail__actions">
      @if ($isApproved)
        {{-- 既に承認済み：押せないボタン --}}
        <button
          type="button"
          class="attendance-detail__button attendance-detail__button--disabled"
          disabled
        >
          承認済み
        </button>
      @else
        {{-- 未承認で、承認URLがある場合のみ承認ボタンを表示 --}}
        @if ($approveUrl !== '')
          <form method="POST" action="{{ $approveUrl }}">
            @csrf
            <button type="submit" class="attendance-detail__button">
              承認
            </button>
          </form>
        @endif
      @endif
    </div>

  </div>
</main>
@endsection
