
@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/request.css') }}">
@endsection

@section('content')
@php
    /**
     * 申請一覧画面 想定パラメータ
     *
     * @var string $pageTitle
     * @var string $activeTab           'pending' | 'approved'
     * @var string|null $pendingTabUrl
     * @var string|null $approvedTabUrl
     *
     * @var \Illuminate\Support\Collection|array<array{
     *   status_label:string,
     *   name_label:string,
     *   target_date_label:string,
     *   reason_label:string,
     *   applied_date_label:string,
     *   detail_url:string|null
     * }> $requests
     */

    $pageTitle = $pageTitle ?? '申請一覧';
    $activeTab = $activeTab ?? 'pending';

    $pendingTabUrl  = $pendingTabUrl  ?? '#';
    $approvedTabUrl = $approvedTabUrl ?? '#';

    if (!isset($requests)) {
        $requests = collect(range(1, 9))->map(function () {
            return [
                'status_label'       => '承認待ち',
                'name_label'         => '西 作香',
                'target_date_label'  => '2023/06/01',
                'reason_label'       => '遅刻のため',
                'applied_date_label' => '2023/06/02',
                'detail_url'         => '#',
            ];
        });
    }
@endphp

<main class="request-list">
  <div class="request-list__inner">

    {{-- タイトル --}}
    <header class="request-list__header">
      <h1 class="request-list__title">
        <span class="request-list__title-bar"></span>
        <span class="request-list__title-text">{{ $pageTitle }}</span>
      </h1>
    </header>

    {{-- タブ（承認待ち / 承認済み） --}}
    <nav class="request-list__tabs" aria-label="申請ステータス切替">
      <a
        href="{{ $pendingTabUrl }}"
        class="request-list__tab {{ $activeTab === 'pending' ? 'is-active' : '' }}"
      >
        承認待ち
      </a>
      <a
        href="{{ $approvedTabUrl }}"
        class="request-list__tab {{ $activeTab === 'approved' ? 'is-active' : '' }}"
      >
        承認済み
      </a>
    </nav>

    {{-- 一覧テーブル --}}
    <section class="request-list__table-wrapper">
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
            <tr>
              <td class="request-table__td">{{ $row['status_label'] ?? '' }}</td>
              <td class="request-table__td">{{ $row['name_label'] ?? '' }}</td>
              <td class="request-table__td">{{ $row['target_date_label'] ?? '' }}</td>
              <td class="request-table__td">{{ $row['reason_label'] ?? '' }}</td>
              <td class="request-table__td">{{ $row['applied_date_label'] ?? '' }}</td>
              <td class="request-table__td request-table__td--link">
                @if (!empty($row['detail_url']))
                  <a href="{{ $row['detail_url'] }}" class="request-table__detail-link">詳細</a>
                @else
                  <span class="request-table__detail-link request-table__detail-link--disabled">詳細</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td class="request-table__td request-table__td--empty" colspan="6">
                表示する申請はありません。
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </section>
  </div>
</main>
@endsection
