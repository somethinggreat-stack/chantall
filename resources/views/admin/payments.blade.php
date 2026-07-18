@extends('admin.layout')
@section('title', 'Payments')

@section('content')
  <div class="page-head">
    <span class="crumb">Money In &amp; Out</span>
    <h1>Payments</h1>
    <p class="sub">Every captured charge, refund and void — pulled from Authorize.Net.</p>
  </div>

  {{-- Type tabs --}}
  <div class="tabs-row">
    @php $cur = $type ?: 'all'; @endphp
    @foreach (['all' => 'All', 'initial' => 'Initial', 'recurring' => 'Recurring', 'refund' => 'Refund', 'void' => 'Void'] as $k => $lbl)
      <a class="tab-chip {{ $cur === $k ? 'active' : '' }}"
         href="{{ route('admin.payments', array_filter(['type' => $k === 'all' ? null : $k, 'q' => $q])) }}">
        {{ $lbl }}<span class="c">{{ $tabs[$k] ?? 0 }}</span>
      </a>
    @endforeach
  </div>

  {{-- Summary tiles --}}
  <div class="kpi-grid">
    <div class="kpi"><div class="k-label">Rows Shown</div><div class="k-num">{{ $summary['count'] }}</div></div>
    <div class="kpi"><div class="k-label">Gross Captured</div><div class="k-num">${{ number_format($summary['gross'], 2) }}</div></div>
    <div class="kpi"><div class="k-label">Refunds / Voids</div><div class="k-num amt-neg">${{ number_format($summary['refunds'], 2) }}</div></div>
    <div class="kpi"><div class="k-label">Net</div><div class="k-num">${{ number_format($summary['net'], 2) }}</div></div>
  </div>

  {{-- Filters --}}
  <div class="toolbar">
    <form method="GET" action="{{ route('admin.payments') }}">
      <input type="hidden" name="type" value="{{ $type }}" />
      <input type="text" name="q" value="{{ $q }}" placeholder="Search customer, email, invoice, txn id…" />
      <input type="date" name="from" value="{{ $from }}" class="status-select" />
      <input type="date" name="to" value="{{ $to }}" class="status-select" />
      <button type="submit" class="btn btn-gold">Search</button>
      @if ($q || $from || $to)<a href="{{ route('admin.payments', array_filter(['type' => $type])) }}" class="btn btn-ghost">Clear</a>@endif
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>When</th><th>Customer</th><th>Type</th><th>Status</th>
          <th class="t-right">Amount</th><th>Invoice</th><th>Txn ID</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($paginator as $p)
          <tr>
            <td class="t-time">{{ $p->charged_at?->format('M j · g:ia') }}</td>
            <td>
              <div class="t-name">{{ $p->customer_name ?: '—' }}</div>
              <div class="t-sub">{{ $p->customer_email ?: '—' }}</div>
            </td>
            <td><span class="cat-pill">{{ ucfirst($p->type) }}</span></td>
            <td><span class="status-pill st-{{ $p->statusKind() }}">{{ ucfirst($p->status) }}</span></td>
            <td class="t-right {{ $p->signedAmount() < 0 ? 'amt-neg' : '' }}" style="font-weight:600">
              {{ $p->signedAmount() < 0 ? '-' : '' }}${{ number_format(abs($p->signedAmount()), 2) }}
            </td>
            <td class="mono">{{ $p->invoice_number ?: '—' }}</td>
            <td class="mono">{{ $p->transaction_id }}</td>
          </tr>
        @empty
          <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--muted)">No payments found.</td></tr>
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
