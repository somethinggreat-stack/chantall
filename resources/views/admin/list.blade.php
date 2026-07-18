@extends('admin.layout')
@section('title', $meta['label'])

@section('content')
  <div class="page-head">
    <span class="crumb">{{ $meta['label'] }}</span>
    <h1>{{ $meta['label'] }}</h1>
    <p class="sub">{{ $total }} total {{ \Illuminate\Support\Str::plural('submission', $total) }}</p>
  </div>

  <div class="toolbar">
    <form method="GET" action="{{ route('admin.list', $meta['slug']) }}">
      <input type="text" name="q" value="{{ $q }}" placeholder="Search name, email, phone, message…" />
      <select name="status">
        <option value="">All statuses</option>
        @foreach ($statuses as $s)
          <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn btn-gold">Search</button>
      @if ($q || $status)
        <a href="{{ route('admin.list', $meta['slug']) }}" class="btn btn-ghost">Clear</a>
      @endif
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>From</th>
          <th>Details</th>
          <th>Status</th>
          <th>Submitted</th>
          <th class="t-right">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rows as $row)
          <tr>
            <td>
              <div class="t-name">{{ $row['name'] }}</div>
              <div class="t-sub">{{ trim(($row['email'] ?? '') . (($row['email'] && $row['phone']) ? ' · ' : '') . ($row['phone'] ?? '')) ?: '—' }}</div>
            </td>
            <td>{{ $row['summary'] ?: '—' }}</td>
            <td>
              <form method="POST" action="{{ route('admin.status', [$meta['slug'], $row['id']]) }}">
                @csrf
                <select name="status" class="status-select" onchange="this.form.submit()">
                  @foreach ($statuses as $s)
                    <option value="{{ $s }}" {{ $row['status'] === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                  @endforeach
                </select>
              </form>
            </td>
            <td class="t-time">{{ $row['created_at']?->format('M j · g:ia') }}</td>
            <td class="t-right">
              <a href="{{ route('admin.show', [$meta['slug'], $row['id']]) }}" class="btn btn-ghost">Open</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align:center;padding:3rem;color:var(--muted)">No submissions found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($paginator->hasPages())
    <div class="pager">
      @if ($paginator->onFirstPage())<span>&larr; Prev</span>@else<a href="{{ $paginator->previousPageUrl() }}">&larr; Prev</a>@endif
      <span aria-current="page">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
      @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Next &rarr;</a>@else<span>Next &rarr;</span>@endif
    </div>
  @endif
@endsection
