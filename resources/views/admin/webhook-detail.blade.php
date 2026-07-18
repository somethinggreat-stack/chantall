@extends('admin.layout')
@section('title', 'Webhook · ' . $event->event_type)

@section('content')
  <div class="detail-top">
    <div>
      <span class="crumb">Webhook Event</span>
      <h1 style="margin-top:.5rem" class="mono" style="font-size:1.3rem">{{ $event->event_type }}</h1>
      <div class="meta-line">
        <span class="status-pill st-{{ $event->statusBadge()['kind'] }}">{{ $event->statusBadge()['label'] }}</span>
        @if ($event->signature_valid === true)<span class="status-pill st-success">&#128274; Signature valid</span>
        @elseif ($event->signature_valid === false)<span class="status-pill st-failed">&#9888; Signature invalid</span>
        @else<span class="status-pill st-info">Signature unverified</span>@endif
        @if ($event->responseCodeLabel())<span class="status-pill st-info">{{ $event->responseCodeLabel() }}</span>@endif
      </div>
    </div>
    <a href="{{ route('admin.webhooks') }}" class="btn btn-ghost">&larr; Back to log</a>
  </div>

  <div class="d-section">
    <h3>Summary</h3>
    <div class="d-row"><div class="d-label">Description</div><div class="d-val">{{ $event->description }}</div></div>
  </div>

  <div class="d-section">
    <h3>Metadata</h3>
    <div class="d-row"><div class="d-label">Customer</div><div class="d-val">{{ $event->customerDisplayName() }}</div></div>
    <div class="d-row"><div class="d-label">Amount</div><div class="d-val">{{ $event->amount !== null ? '$' . number_format((float) $event->amount, 2) : '—' }}</div></div>
    <div class="d-row"><div class="d-label">Received</div><div class="d-val">{{ $event->received_at?->format('M j, Y \a\t g:ia') }} ({{ $event->received_at?->diffForHumans() }})</div></div>
    <div class="d-row"><div class="d-label">Source IP</div><div class="d-val mono">{{ $event->source_ip ?: '—' }}</div></div>
    <div class="d-row"><div class="d-label">Notification ID</div><div class="d-val mono">{{ $event->notification_id }}</div></div>
    <div class="d-row"><div class="d-label">Entity ID</div><div class="d-val mono">{{ $event->entity_id ?: '—' }}</div></div>
    <div class="d-row"><div class="d-label">Invoice #</div><div class="d-val mono">{{ $event->invoice_number ?: '—' }}</div></div>
    <div class="d-row"><div class="d-label">ARB Status</div><div class="d-val">{{ $event->arb_status ?: '—' }}</div></div>
    <div class="d-row"><div class="d-label">Recorded</div><div class="d-val">{{ $event->created_at?->diffForHumans() }}</div></div>
  </div>

  @if ($enrollment)
    <div class="d-section">
      <h3>Matched Client</h3>
      <div class="d-row"><div class="d-label">Name</div><div class="d-val">{{ trim($enrollment->first_name . ' ' . $enrollment->last_name) }}</div></div>
      <div class="d-row"><div class="d-label">Plan</div><div class="d-val">{{ $enrollment->plan }}</div></div>
      <div class="d-row"><div class="d-label">&nbsp;</div><div class="d-val"><a href="{{ route('admin.show', ['enrollments', $enrollment->id]) }}" class="btn btn-ghost">Open client &rarr;</a></div></div>
    </div>
  @endif

  <div class="d-section">
    <h3>Raw Payload</h3>
    <div class="mask-note">&#128274; Card data is masked here — what you see is PCI-redacted.</div>
    <div class="raw-box">{{ json_encode($event->sanitizedPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</div>
  </div>
@endsection
