@extends('admin.layout')
@section('title', $meta['singular'] . ' · ' . $title)

@section('content')
  <div class="detail-top">
    <div>
      <span class="crumb">{{ $meta['label'] }}</span>
      <h1 style="margin-top:.5rem">{{ $title }}</h1>
      <div class="meta-line">
        #{{ str_pad($record->id, 4, '0', STR_PAD_LEFT) }} ·
        submitted {{ $record->created_at?->format('M j, Y \a\t g:ia') }}
        ({{ $record->created_at?->diffForHumans() }})
      </div>
    </div>
    <a href="{{ route('admin.list', $meta['slug']) }}" class="btn btn-ghost">&larr; Back to list</a>
  </div>

  <div class="d-section">
    <h3>
      Status
      <form method="POST" action="{{ route('admin.status', [$meta['slug'], $record->id]) }}">
        @csrf
        <select name="status" class="status-select" onchange="this.form.submit()">
          @foreach ($statuses as $s)
            <option value="{{ $s }}" {{ $record->status === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
          @endforeach
        </select>
      </form>
    </h3>
    <div class="d-row">
      <div class="d-label">Current Status</div>
      <div class="d-val"><span class="status-pill st-{{ $record->status }}">{{ ucfirst(str_replace('_', ' ', $record->status)) }}</span></div>
    </div>
  </div>

  @foreach ($sections as $sectionLabel => $fields)
    <div class="d-section">
      <h3>{{ $sectionLabel }}</h3>
      @foreach ($fields as $label => $value)
        <div class="d-row">
          <div class="d-label">{{ $label }}</div>
          <div class="d-val {{ strlen($value) > 60 ? 'long' : '' }}">{{ $value }}</div>
        </div>
      @endforeach
    </div>
  @endforeach
@endsection
