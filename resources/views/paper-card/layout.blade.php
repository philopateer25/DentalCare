<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Scan paper card') }} · DOXamigo</title>
    <style>
        :root { --bg: #f4f6fb; --card: #fff; --text: #0f172a; --muted: #64748b; --primary: #3859a8; --ok: #009e83; --bad: #d82c1e; --line: #e2e8f0; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b1220; --card: #111a2e; --text: #f1f5f9; --muted: #94a3b8; --line: #1e293b; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif; background: var(--bg); color: var(--text); }
        main { max-width: 28rem; margin: 0 auto; padding: 24px 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 20px; }
        h1 { font-size: 1.25rem; margin: 0 0 4px; }
        p { line-height: 1.5; }
        .muted { color: var(--muted); font-size: .9rem; }
        .brand { font-weight: 700; color: var(--primary); margin-bottom: 16px; }
        .pick { display: block; width: 100%; padding: 28px 16px; border: 2px dashed var(--primary); border-radius: 14px; text-align: center; font-size: 1.1rem; font-weight: 600; color: var(--primary); cursor: pointer; }
        .pick input { display: none; }
        .btn { display: block; width: 100%; margin-top: 16px; padding: 16px; border: 0; border-radius: 12px; background: var(--primary); color: #fff; font-size: 1.1rem; font-weight: 600; }
        .btn:disabled { opacity: .5; }
        .thumbs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 12px; }
        .thumbs img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 8px; }
        .error { color: var(--bad); }
        .big { font-size: 3rem; text-align: center; margin: 8px 0; }
    </style>
</head>
<body>
<main>
    <div class="brand">DOXamigo</div>
    <div class="card">
        @yield('content')
    </div>
</main>
</body>
</html>
