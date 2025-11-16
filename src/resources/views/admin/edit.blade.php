{{-- resources/views/admin/edit.blade.php --}}

@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/edit.css') }}">
@endsection

@section('content')
@php
    /**
     * 修正申請承認画面（管理者） 想定パラメータ
     *
     * @var string      $employeeName       名前
     * @var string      $dateYearLabel      年部分   例: '2023年'
     * @var string      $dateDayLabel       月日部分 例: '6月1日'
     * @var string|null $workStartLabel     出勤時刻 例: '09:00'
     * @var string|null $workEndLabel       退勤時刻 例: '18:00'
     * @var string|null $break1StartLabel   休憩1開始 例: '12:00'
     * @var string|null $break1EndLabel     休憩1終了 例: '13:00'
     * @var string|null $break2StartLabel   休憩2開始
     * @var string|null $break2EndLabel     休憩2終了
     * @var string|null $noteLabel          備考
     *
     * @var bool        $canApprove         承認ボタンを表示するかどうか
     * @var string|null $approveAction      承認POST先URL
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

    $canApprove        = (bool)($canApprove ?? true);
    $approveAction     = $approveAction     ?? '#';
@endphp

<main class="approval-detail">
  <div class="approval-detail__inner">

    {{-- タイトル --}}
    <header class="approval-detail__header">
      <h1 class="approval-detail__title">
        <span class="approval-detail__title-bar" aria-hidden="true"></span>
        <span>勤怠詳細</span>
      </h1>
    </header>

    {{-- 詳細カード --}}
    <section class="approval-detail__card" aria-label="勤怠詳細">

      {{-- 名前 --}}
      <dl class="approval-detail__row">
        <dt class="approval-detail__label">名前</dt>
        <dd class="approval-detail__value approval-detail__value--center">
          <span class="approval-detail__text">{{ $employeeName }}</span>
        </dd>
      </dl>

      {{-- 日付 --}}
      <dl class="approval-detail__row">
        <dt class="approval-detail__label">日付</dt>
        <dd class="approval-detail__value approval-detail__value--date">
          <span class="approval-detail__text">{{ $dateYearLabel }}</span>
          <span class="approval-detail__text">{{ $dateDayLabel }}</span>
        </dd>
      </dl>

      {{-- 出勤・退勤 --}}
      <dl class="approval-detail__row">
        <dt class="approval-detail__label">出勤・退勤</dt>
        <dd class="approval-detail__value approval-detail__value--time">
          <div class="approval-detail__time-range">
            <span class="approval-detail__time">{{ $workStartLabel }}</span>
            <span class="approval-detail__time-separator">〜</span>
            <span class="approval-detail__time">{{ $workEndLabel }}</span>
          </div>
        </dd>
      </dl>

      {{-- 休憩 --}}
      <dl class="approval-detail__row">
        <dt class="approval-detail__label">休憩</dt>
        <dd class="approval-detail__value approval-detail__value--time">
          <div class="approval-detail__time-range">
            <span class="approval-detail__time">{{ $break1StartLabel }}</span>
            <span class="approval-detail__time-separator">〜</span>
            <span class="approval-detail__time">{{ $break1EndLabel }}</span>
          </div>
        </dd>
      </dl>

      {{-- 休憩2（値が空でも行は常に表示） --}}
      <dl class="approval-detail__row">
        <dt class="approval-detail__label">休憩2</dt>
        <dd class="approval-detail__value approval-detail__value--time">
          <div class="approval-detail__time-range">
            <span class="approval-detail__time">
              {{ $break2StartLabel !== '' ? $break2StartLabel : '　' }}
            </span>
            <span class="approval-detail__time-separator">〜</span>
            <span class="approval-detail__time">
              {{ $break2EndLabel !== '' ? $break2EndLabel : '　' }}
            </span>
          </div>
        </dd>
      </dl>

      {{-- 備考 --}}
      <dl class="approval-detail__row approval-detail__row--note">
        <dt class="approval-detail__label">備考</dt>
        <dd class="approval-detail__value approval-detail__value--center">
          <div class="approval-detail__note-text">
            {{ $noteLabel }}
          </div>
        </dd>
      </dl>
    </section>

    {{-- 下部：承認ボタン or 承認済み表示 --}}
    <div class="approval-detail__actions">
      @if ($canApprove && $approveAction !== '')
        {{-- まだ承認していない場合：承認ボタンを表示 --}}
        <form method="POST" action="{{ $approveAction }}" class="approval-detail__form">
          @csrf
          <button type="submit" class="approval-detail__button">
            承認
          </button>
        </form>
      @else
        {{-- 承認済みの場合：押せないグレーボタンで「承認済み」表示 --}}
        <button type="button"
                class="approval-detail__button approval-detail__button--done"
                disabled>
          承認済み
        </button>
      @endif
    </div>

  </div>
</main>
@endsection
