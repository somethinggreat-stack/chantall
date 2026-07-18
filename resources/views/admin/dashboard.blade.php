@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
  <div class="welcome">
    <div>
      <span class="w-kicker">Welcome back · {{ now()->format('l, M j, Y') }}</span>
      <h1>Prestige <span class="gold">Concierge</span></h1>
      <p>Everything that came through your forms — credit repair enrollments, funding leads, contact &amp; tax requests, and popups — lives below.</p>
    </div>
    <div class="w-stats">
      <div class="w-stat"><b>{{ $total }}</b><span>Total Submissions</span></div>
      <div class="w-stat"><b>{{ $totalToday }}</b><span>Today</span></div>
    </div>
  </div>

  {{-- ===== Payment KPIs ===== --}}
  @isset($kpis)
  <div class="kpi-grid">
    <a class="kpi" href="{{ route('admin.payments') }}" style="text-decoration:none">
      <div class="k-label">Captured Today</div>
      <div class="k-num">${{ number_format($kpis['captures_today_sum'], 2) }}</div>
      <div class="k-sub">{{ $kpis['captures_today_count'] }} payment(s)</div>
    </a>
    <a class="kpi" href="{{ route('admin.declines') }}" style="text-decoration:none">
      <div class="k-label">Declines Today</div>
      <div class="k-num">{{ $kpis['declines_today'] }}</div>
      <div class="k-sub">failed attempts</div>
    </a>
    <div class="kpi">
      <div class="k-label">Refunds / Voids Today</div>
      <div class="k-num">${{ number_format($kpis['refunds_today_sum'], 2) }}</div>
      <div class="k-sub">{{ $kpis['refunds_today_count'] }} item(s)</div>
    </div>
    <div class="kpi">
      <div class="k-label">Held for Review</div>
      <div class="k-num">{{ $kpis['held_review'] }}</div>
      <div class="k-sub">fraud queue</div>
    </div>
    <div class="kpi">
      <div class="k-label">Gross Lifetime</div>
      <div class="k-num">${{ number_format($kpis['gross_lifetime'], 2) }}</div>
      <div class="k-sub">captured · before refunds</div>
    </div>
  </div>

  <div class="health-bar">
    @php $idle = ! $health['last_webhook_at']; @endphp
    <span><span class="health-dot {{ $idle ? 'idle' : 'ok' }}"></span>
      Last webhook: <b>{{ $health['last_webhook_at']?->diffForHumans() ?? 'none yet' }}</b></span>
    <span>Signatures today: <b style="color:#7BD88F">{{ $health['sig_ok_today'] }} OK</b> ·
      <b class="{{ $health['sig_bad_today'] ? 'amt-neg' : '' }}">{{ $health['sig_bad_today'] }} invalid</b></span>
    <span>Webhooks today: <b>{{ $health['webhooks_today'] }}</b></span>
    <span><a href="{{ route('admin.webhooks') }}" style="color:var(--gold-soft)">View event log &rarr;</a></span>
  </div>
  @endisset

  <div class="stat-grid">
    @foreach ($cards as $card)
      <a class="stat-card" href="{{ route('admin.list', $card['slug']) }}">
        <div class="label">{{ $card['label'] }}</div>
        <div class="num">{{ $card['count'] }}</div>
        <div class="meta">+{{ $card['today'] }} today · <span class="new">{{ $card['new'] }} new</span></div>
      </a>
    @endforeach
  </div>

  <div class="panel-grid">
    @foreach ($latest as $slug => $block)
      <div class="panel">
        <div class="panel-head">
          <h3>Latest {{ $block['label'] }}</h3>
          <a href="{{ route('admin.list', $slug) }}">View all &rarr;</a>
        </div>
        @forelse ($block['rows'] as $row)
          <a class="l-row" href="{{ route('admin.show', [$slug, $row['id']]) }}">
            <div>
              <div class="l-name">{{ $row['name'] }}</div>
              <div class="l-sub">{{ trim(($row['email'] ?? '') . (($row['email'] && $row['summary'] && $row['summary'] !== '—') ? ' · ' : '') . ($row['summary'] !== '—' ? $row['summary'] : '')) ?: '—' }}</div>
            </div>
            <div class="l-time">{{ $row['created_at']?->diffForHumans() }}</div>
          </a>
        @empty
          <div class="l-empty"><b>Nothing yet</b>No submissions in this category yet.</div>
        @endforelse
      </div>
    @endforeach
  </div>

  {{-- ===== Recent payments + webhooks ===== --}}
  @isset($recentPayments)
  <div class="panel-grid">
    <div class="panel">
      <div class="panel-head">
        <h3>Recent Payments</h3>
        <a href="{{ route('admin.payments') }}">View all &rarr;</a>
      </div>
      @forelse ($recentPayments as $p)
        <div class="l-row">
          <div>
            <div class="l-name">{{ $p->customer_name ?: ($p->customer_email ?: '—') }}</div>
            <div class="l-sub">{{ ucfirst($p->type) }} · <span class="status-pill st-{{ $p->statusKind() }}">{{ ucfirst($p->status) }}</span></div>
          </div>
          <div class="l-time" style="text-align:right">
            <div class="{{ $p->signedAmount() < 0 ? 'amt-neg' : '' }}" style="font-weight:600">{{ $p->signedAmount() < 0 ? '-' : '' }}${{ number_format(abs($p->signedAmount()), 2) }}</div>
            <div>{{ $p->charged_at?->diffForHumans() }}</div>
          </div>
        </div>
      @empty
        <div class="l-empty"><b>No payments yet</b>Captured transactions will appear here.</div>
      @endforelse
    </div>

    <div class="panel">
      <div class="panel-head">
        <h3>Recent Webhooks</h3>
        <a href="{{ route('admin.webhooks') }}">View all &rarr;</a>
      </div>
      @forelse ($recentWebhooks as $w)
        <a class="l-row" href="{{ route('admin.webhook', $w->id) }}">
          <div>
            <div class="l-name"><span class="cat-pill">{{ $w->category() }}</span> {{ $w->customerDisplayName() }}</div>
            <div class="l-sub">{{ $w->description }}</div>
          </div>
          <div class="l-time" style="text-align:right">
            <span class="status-pill st-{{ $w->statusBadge()['kind'] }}">{{ $w->statusBadge()['label'] }}</span>
            <div>{{ $w->received_at?->diffForHumans() }}</div>
          </div>
        </a>
      @empty
        <div class="l-empty"><b>No webhooks yet</b>Register your endpoint in Authorize.Net to start receiving events.</div>
      @endforelse
    </div>
  </div>
  @endisset
@endsection
