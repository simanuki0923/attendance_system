{{-- resources/views/attendance.blade.php --}}
@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('content')
@php
    /** @var string|null $status  勤怠状態: before_work / working / on_break / after_work */
    /** @var string|null $displayDate  表示用日付 */
    /** @var string|null $displayTime  表示用時刻 */

    $status      = $status      ?? 'before_work';
    $displayDate = $displayDate
        ?? now()->locale('ja')->isoFormat('YYYY年M月D日(ddd)');
    $displayTime = $displayTime ?? now()->format('H:i');

    // 仕様のステータス名称：
    // 勤務外 / 出勤中 / 休憩中 / 退勤済
    $statusLabelMap = [
        'before_work' => '勤務外',
        'working'     => '出勤中',
        'on_break'    => '休憩中',
        'after_work'  => '退勤済',
    ];

    $statusLabel = $statusLabelMap[$status] ?? '';
@endphp

<main class="attendance">
  <div class="attendance__inner">

    {{-- ステータスバッジ --}}
    @if($statusLabel !== '')
      <p class="attendance__badge attendance__badge--{{ $status }}">
        {{ $statusLabel }}
      </p>
    @endif

    {{-- 日付・時刻表示 --}}
    <p class="attendance__date">{{ $displayDate }}</p>
    <p class="attendance__time">{{ $displayTime }}</p>

    {{-- ボタン／メッセージ切り替え --}}
    @if ($status === 'before_work')
      {{-- 勤務外 → 出勤ボタンのみ --}}
      <form
        class="attendance__actions attendance__actions--single"
        method="POST"
        action="{{ route('attendance.clockIn') }}"
      >
        @csrf
        <button type="submit" class="attendance__btn attendance__btn--primary">
          出勤
        </button>
      </form>

    @elseif ($status === 'working')
      {{-- 出勤中 → 「退勤」「休憩入」 --}}
      <div class="attendance__actions attendance__actions--double">
        <form method="POST" action="{{ route('attendance.clockOut') }}">
          @csrf
          <button type="submit" class="attendance__btn attendance__btn--primary">
            退勤
          </button>
        </form>

        <form method="POST" action="{{ route('attendance.breakIn') }}">
          @csrf
          <button type="submit" class="attendance__btn attendance__btn--secondary">
            休憩入
          </button>
        </form>
      </div>

    @elseif ($status === 'on_break')
      {{-- 休憩中 → 「休憩戻」だけ表示 --}}
      <form
        class="attendance__actions attendance__actions--single"
        method="POST"
        action="{{ route('attendance.breakOut') }}"
      >
        @csrf
        <button type="submit" class="attendance__btn attendance__btn--secondary">
          休憩戻
        </button>
      </form>

    @elseif ($status === 'after_work')
      {{-- 退勤済 → メッセージのみ（残す） --}}
      <p class="attendance__message">
        お疲れ様でした。
      </p>
    @endif
  </div>
</main>
@endsection
