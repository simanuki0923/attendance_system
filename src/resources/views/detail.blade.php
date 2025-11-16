{{-- resources/views/detail.blade.php --}}

@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/detail.css') }}">
@endsection

@section('content')
@php
    /**
     * 勤怠詳細画面 想定パラメータ
     *
     * @var string      $employeeName       名前ラベル
     * @var string      $dateYearLabel      年部分ラベル   例: '2023年'
     * @var string      $dateDayLabel       月日部分ラベル 例: '6月1日'
     * @var string|null $workStartLabel     出勤時刻       例: '09:00'
     * @var string|null $workEndLabel       退勤時刻       例: '18:00'
     * @var string|null $break1StartLabel   休憩1開始      例: '12:00'
     * @var string|null $break1EndLabel     休憩1終了      例: '13:00'
     * @var string|null $break2StartLabel   休憩2開始
     * @var string|null $break2EndLabel     休憩2終了
     * @var string|null $noteLabel          備考
     * @var string|null $editUrl            「修正」ボタンの遷移先URL
     * @var bool        $isPending          承認待ちフラグ（true のとき修正不可）
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
    $editUrl           = $editUrl           ?? '#';
    $isPending         = (bool)($isPending  ?? false);
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

    {{-- 詳細カード --}}
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
            <span class="attendance-detail__tilde">〜</span>
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
            <span class="attendance-detail__tilde">〜</span>
            <span class="attendance-detail__time-input">
              <input type="text" value="{{ $break1EndLabel }}" readonly>
            </span>
          </div>
        </dd>
      </dl>

      {{-- 休憩2（値がある場合だけ表示） --}}
      @if ($break2StartLabel !== '' || $break2EndLabel !== '')
        <dl class="attendance-detail__row">
          <dt class="attendance-detail__label">休憩2</dt>
          <dd class="attendance-detail__value">
            <div class="attendance-detail__time-range">
              <span class="attendance-detail__time-input">
                <input type="text" value="{{ $break2StartLabel }}" readonly>
              </span>
              <span class="attendance-detail__tilde">〜</span>
              <span class="attendance-detail__time-input">
                <input type="text" value="{{ $break2EndLabel }}" readonly>
              </span>
            </div>
          </dd>
        </dl>
      @endif

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

    {{-- 下部エリア（承認待ちかどうかで出し分け） --}}
    <div class="attendance-detail__actions">
      @if ($isPending)
        <p class="attendance-detail__warning">
          ※承認待ちのため修正はできません。
        </p>
      @else
        <a href="{{ $editUrl }}" class="attendance-detail__button">
          修正
        </a>
      @endif
    </div>

  </div>
</main>
@endsection
