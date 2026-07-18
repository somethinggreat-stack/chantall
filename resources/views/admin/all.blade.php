@extends('admin.layout')
@section('title', 'All Leads')

@section('content')
  <div class="page-head">
    <span class="crumb">Combined Feed</span>
    <h1>All Leads</h1>
    <p class="sub">{{ $total }} recent {{ \Illuminate\Support\Str::plural('submission', $total) }} across every form</p>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>From</th>
          <th>Type</th>
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
            <td><span class="status-pill st-new" style="border-color:var(--line)">{{ $row['type_label'] }}</span></td>
            <td>{{ $row['summary'] ?: '—' }}</td>
            <td><span class="status-pill st-{{ $row['status'] }}">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</span></td>
            <td class="t-time">{{ $row['created_at']?->format('M j · g:ia') }}</td>
            <td class="t-right"><a href="{{ route('admin.show', [$row['type'], $row['id']]) }}" class="btn btn-ghost">Open</a></td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--muted)">No leads yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
