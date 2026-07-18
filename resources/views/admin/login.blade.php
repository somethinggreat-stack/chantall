<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin Login · Prestige Financial Concierge</title>
<meta name="robots" content="noindex" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="{{ asset('assets/admin.css') }}" />
</head>
<body class="login-body">
  <form class="login-card" method="POST" action="{{ route('admin.login.submit') }}">
    @csrf
    <div class="l-logo"><img src="{{ asset('assets/logo.jpg') }}" alt="Prestige" /></div>
    <h1>Admin <span class="gold">Dashboard</span></h1>
    <p class="l-tag">Sign in to manage your leads &amp; submissions.</p>

    @if ($errors->any())
      <div class="login-err">{{ $errors->first() }}</div>
    @endif

    <div class="field">
      <label>Email Address</label>
      <input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus />
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••" required />
    </div>
    <button type="submit" class="btn btn-gold">Sign In &rarr;</button>
    <p class="login-foot">🔒 Authorized access only · Prestige Financial Concierge</p>
  </form>
</body>
</html>
