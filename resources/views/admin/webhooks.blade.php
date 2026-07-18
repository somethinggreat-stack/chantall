@extends('admin.layout')
@section('title', 'Webhooks')

@section('content')
  <div class="page-head">
    <span class="crumb">Event Log</span>
    <h1>Webhooks</h1>
    <p class="sub">Every event Authorize.Net has sent to <span class="mono">/webhooks/authorize-net</span>.</p>
  </div>

  <div class="health-bar">
    <span>Today: <b>{{ $stats['today'] }}</b></span>
    <span>Last hour: <b>{{ $stats['hour'] }}</b></span>
    <span>7 days: <b>{{ $stats['week'] }}</b></span>
    <span>Signatures: <b style="color:#7BD88F">{{ $stats['sig_ok'] }} OK</b> · <b class="{{ $stats['sig_bad'] ? 'amt-neg' : '' }}">{{ $stats['sig_bad'] }} invalid</b></span>
    <span>Last received: <b>{{ $stats['last']?->diffForHumans() ?? 'none yet' }}</b></span>
  </div>

  <div class="toolbar">
    <form method="GET" action="{{ route('admin.webhooks') }}">
      <input type="text" name="q" value="{{ $q }}" placeholder="Search description, email, entity id, invoice…" />
      <select name="event_type" class="status-select">
        <option value="">All event types</option>
        @foreach ($eventTypes as $et => $c)
          <option value="{{ $et }}" {{ $eventType === $et ? 'selected' : '' }}>{{ $et }} ({{ $c }})</option>
        @endforeach
      </select>
      <select name="sig" class="status-select">
        <option value="">Any signature</option>
        <option value="ok" {{ $sig === 'ok' ? 'selected' : '' }}>Valid</option>
        <option value="invalid" {{ $sig === 'invalid' ? 'selected' : '' }}>Invalid</option>
        <option value="unverified" {{ $sig === 'unverified' ? 'selected' : '' }}>Unverified</option>
      </select>
      <button type="submit" class="btn btn-gold">Search</button>
      @if ($q || $eventType || $sig)<a href="{{ route('admin.webhooks') }}" class="btn btn-ghost">Clear</a>@endif
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Event</th><th>Customer</th><th class="t-right">Amount</th><th>Status</th><th>When</th><th class="t-right">Details</th></tr>
      </thead>
      <tbody>
        @forelse ($paginator as $w)
          <tr>
            <td>
              <div class="t-name"><span class="cat-pill">{{ $w->category() }}</span></div>
              <div class="t-sub">{{ $w->description }}</div>
              <div class="t-sub mono" style="opacity:.7">{{ $w->event_type }}</div>
            </td>
            <td>
              <div class="t-name">{{ $w->customerDisplayName() }}</div>
              <div class="t-sub mono">{{ $w->entity_id ?: '—' }}</div>
            </td>
            <td class="t-right">{{ $w->amount !== null ? '$' . number_format((float) $w->amount, 2) : '—' }}</td>
            <td>
              <span class="status-pill st-{{ $w->statusBadge()['kind'] }}">{{ $w->statusBadge()['label'] }}</span>
              @if ($w->signature_valid === true)<span class="status-pill st-success" title="signature valid">&#128274;</span>
              @elseif ($w->signature_valid === false)<span class="status-pill st-failed" title="signature invalid">&#9888;</span>
              @endif
            </td>
            <td class="t-time">{{ $w->received_at?->format('M j · g:ia') }}</td>
            <td class="t-right"><a href="{{ route('admin.webhook', $w->id) }}" class="btn btn-ghost">View</a></td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--muted)">No webhook events yet.</td></tr>
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
