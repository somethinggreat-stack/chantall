@extends('admin.layout')
@section('title', 'Declines')

@section('content')
  <div class="page-head">
    <span class="crumb">Recovery</span>
    <h1>Declines &amp; Failed Charges</h1>
    <p class="sub">{{ $total }} declined, errored or held transaction(s) worth following up.</p>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Customer</th><th class="t-right">Attempted</th><th>Reason</th><th>AVS</th><th>When</th><th class="t-right">Details</th></tr>
      </thead>
      <tbody>
        @forelse ($paginator as $w)
          @php
            $p = $w->payload['payload'] ?? [];
            $reason = data_get($w->payload, 'payload.errors.0.errorText')
                ?? ($w->responseCodeLabel() ?? 'Declined');
            $avs = $p['avsResponse'] ?? '—';
          @endphp
          <tr>
            <td>
              <div class="t-name">{{ $w->customerDisplayName() }}</div>
              <div class="t-sub mono">{{ $w->entity_id ?: '—' }}</div>
            </td>
            <td class="t-right">{{ $w->amount !== null ? '$' . number_format((float) $w->amount, 2) : '—' }}</td>
            <td><span class="status-pill st-failed">{{ $reason }}</span></td>
            <td class="mono">{{ $avs }}</td>
            <td class="t-time">{{ $w->received_at?->format('M j · g:ia') }}</td>
            <td class="t-right"><a href="{{ route('admin.webhook', $w->id) }}" class="btn btn-ghost">View</a></td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--muted)">No declines — every charge went through. 🎉</td></tr>
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
