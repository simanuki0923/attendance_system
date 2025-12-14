
{{-- resources/views/admin/staff.blade.php --}}

@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
@endsection

@section('content')
@php
    $pageTitle = $pageTitle ?? 'スタッフ一覧';
    $staffList = $staffList ?? collect(); // ★ダミーを削除して Controller の値を使う
@endphp

<main class="staff-list">
  <div class="staff-list__inner">
    <header class="staff-list__header">
      <h1 class="staff-list__title">
        <span class="staff-list__title-bar"></span>
        <span class="staff-list__title-text">{{ $pageTitle }}</span>
      </h1>
    </header>

    <section class="staff-list__card">
      <table class="staff-list__table">
        <thead>
        <tr>
          <th class="staff-list__th staff-list__th--name">名前</th>
          <th class="staff-list__th staff-list__th--email">メールアドレス</th>
          <th class="staff-list__th staff-list__th--monthly">月次勤怠</th>
        </tr>
        </thead>

        <tbody>
        @forelse ($staffList as $row)
          <tr>
            <td class="staff-list__td staff-list__td--name">
              {{ $row['name_label'] ?? '' }}
            </td>
            <td class="staff-list__td staff-list__td--email">
              {{ $row['email_label'] ?? '' }}
            </td>
            <td class="staff-list__td staff-list__td--monthly">
              @if (!empty($row['detail_url']))
                <a href="{{ $row['detail_url'] }}" class="staff-list__detail-link">
                  {{ $row['detail_text'] ?? '詳細' }}
                </a>
              @else
                <span class="staff-list__detail-text">
                  {{ $row['detail_text'] ?? '詳細' }}
                </span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td class="staff-list__td staff-list__td--empty" colspan="3">
              スタッフ情報が登録されていません。
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </section>
  </div>
</main>
@endsection
