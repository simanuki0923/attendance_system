@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/request.css') }}">
@endsection

@section('content')
@php
    $pageTitle = $pageTitle ?? '申請一覧';

    $activeTab = $activeTab ?? 'pending';
    $pendingTabUrl  = $pendingTabUrl  ?? '#';
    $approvedTabUrl = $approvedTabUrl ?? '#';

    $requests = $requests ?? collect();
@endphp

<main class="request-list">
  <div class="request-list__inner">

    <header class="request-list__header">
      <h1 class="request-list__title">
        <span class="request-list__title-bar" aria-hidden="true"></span>
        <span class="request-list__title-text">{{ $pageTitle }}</span>
      </h1>
    </header>

    <nav class="request-list__tabs" aria-label="申請ステータス切替">
      <a href="{{ $pendingTabUrl }}"
         class="request-list__tab {{ $activeTab === 'pending' ? 'is-active' : '' }}">
        承認待ち
      </a>
      <a href="{{ $approvedTabUrl }}"
         class="request-list__tab {{ $activeTab === 'approved' ? 'is-active' : '' }}">
        承認済み
      </a>
    </nav>

    <section class="request-list__table-wrapper" aria-label="申請一覧">
      <table class="request-table">
        <thead>
          <tr>
            <th class="request-table__th">状態</th>
            <th class="request-table__th">名前</th>
            <th class="request-table__th">対象日時</th>
            <th class="request-table__th">申請理由</th>
            <th class="request-table__th">申請日時</th>
            <th class="request-table__th request-table__th--narrow">詳細</th>
          </tr>
        </thead>

        <tbody>
          @forelse ($requests as $row)
            @php
                $detailUrl = $row['detail_url'] ?? null;
            @endphp
            <tr>
              <td class="request-table__td">{{ $row['status_label'] ?? '' }}</td>
              <td class="request-table__td">{{ $row['name_label'] ?? '' }}</td>
              <td class="request-table__td">{{ $row['target_date_label'] ?? '' }}</td>
              <td class="request-table__td">{{ $row['reason_label'] ?? '' }}</td>
              <td class="request-table__td">{{ $row['applied_date_label'] ?? '' }}</td>
              <td class="request-table__td request-table__td--narrow">
                @if (!empty($detailUrl))
                  <a href="{{ $detailUrl }}" class="request-table__detail-link">詳細</a>
                @else
                  <span class="request-table__detail-link request-table__detail-link--disabled">詳細</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td class="request-table__td request-table__td--empty" colspan="6">
                申請情報がありません。
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </section>

  </div>
</main>
@endsection
