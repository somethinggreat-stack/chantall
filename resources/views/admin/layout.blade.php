<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>@yield('title', 'Dashboard') · Prestige Admin</title>
<meta name="robots" content="noindex" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="{{ asset('assets/admin.css') }}" />
</head>
<body>
@php
    $navType = request()->route('type');
    $items = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'ic' => '▦', 'active' => request()->routeIs('admin.dashboard')],
        ['route' => 'admin.all', 'label' => 'All Leads', 'ic' => '≡', 'active' => request()->routeIs('admin.all')],
        ['route' => ['admin.list', 'enrollments'], 'label' => 'Paid Credit Repair Clients', 'ic' => '⚑', 'active' => $navType === 'enrollments'],
        ['route' => 'admin.payments', 'label' => 'Payments', 'ic' => '$', 'active' => request()->routeIs('admin.payments')],
        ['route' => 'admin.declines', 'label' => 'Declines', 'ic' => '⚠', 'active' => request()->routeIs('admin.declines')],
        ['route' => 'admin.webhooks', 'label' => 'Webhooks', 'ic' => '⟳', 'active' => request()->routeIs('admin.webhooks') || request()->routeIs('admin.webhook')],
        ['route' => ['admin.list', 'funding'], 'label' => 'Funding Leads', 'ic' => '◇', 'active' => $navType === 'funding'],
        ['route' => ['admin.list', 'contacts'], 'label' => 'Contact Us Submissions', 'ic' => '✉', 'active' => $navType === 'contacts'],
        ['route' => ['admin.list', 'tax'], 'label' => 'Tax Leads', 'ic' => '▤', 'active' => $navType === 'tax'],
        ['route' => ['admin.list', 'popups'], 'label' => 'Popup Submissions', 'ic' => '★', 'active' => $navType === 'popups'],
    ];
@endphp
<div class="admin-shell">
  <aside class="sidebar" id="sidebar">
    <div class="side-brand">
      <img src="{{ asset('assets/logo.jpg') }}" alt="Prestige" />
    </div>
    <nav class="side-nav">
      @foreach ($items as $it)
        @php $href = is_array($it['route']) ? route($it['route'][0], $it['route'][1]) : route($it['route']); @endphp
        <a class="side-link {{ $it['active'] ? 'active' : '' }}" href="{{ $href }}">
          <span class="ic">{{ $it['ic'] }}</span> {{ $it['label'] }}
        </a>
      @endforeach
      <div class="side-sep"></div>
      <a class="side-link" href="{{ url('/') }}" target="_blank"><span class="ic">↗</span> View Site</a>
    </nav>
    <div class="signout">
      <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit">Sign Out</button>
      </form>
    </div>
  </aside>

  <main class="main">
    <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰ Menu</button>
    @yield('content')
  </main>
</div>
</body>
</html>
