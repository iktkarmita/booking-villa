@props(['title' => null])
@php
    $tenant = app(\App\Support\CurrentTenant::class)->get();
    $brand = $tenant?->name ?? 'Booking Villa';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' - ' : '' }}{{ $brand }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#1f2937;background:#f8fafc;line-height:1.5}
        a{color:#0f766e;text-decoration:none}a:hover{text-decoration:underline}
        .wrap{max-width:60rem;margin:0 auto;padding:1rem}
        header.site{background:#fff;border-bottom:1px solid #e5e7eb}
        header.site .wrap{display:flex;justify-content:space-between;align-items:center;gap:1rem}
        header.site .brand{font-weight:700;font-size:1.15rem;color:#0f766e}
        .lang a{margin-left:.5rem;font-size:.85rem}.lang a.on{font-weight:700}
        h1{font-size:1.6rem;margin:1rem 0}h2{font-size:1.2rem;margin:1.25rem 0 .5rem}
        .grid{display:grid;gap:1rem;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr))}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:.75rem;overflow:hidden}
        .card .body{padding:1rem}.card img{width:100%;height:11rem;object-fit:cover;display:block;background:#e5e7eb}
        .row{display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end}
        label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.25rem}
        input,select,textarea{width:100%;padding:.55rem .7rem;border:1px solid #d1d5db;border-radius:.5rem;font:inherit;background:#fff}
        .field{flex:1;min-width:9rem;margin-bottom:.75rem}
        .btn{display:inline-block;background:#0f766e;color:#fff;border:0;border-radius:.5rem;padding:.6rem 1.1rem;font:inherit;font-weight:600;cursor:pointer}
        .btn:hover{background:#115e59;text-decoration:none}.btn.gray{background:#6b7280}
        .muted{color:#6b7280;font-size:.9rem}.price{font-weight:700;color:#0f766e}
        .err{color:#b91c1c;font-size:.85rem;margin-top:.2rem}.ok{color:#047857}
        .badge{display:inline-block;padding:.15rem .6rem;border-radius:999px;font-size:.8rem;font-weight:600;background:#e5e7eb}
        .badge.warning{background:#fef3c7;color:#92400e}.badge.success{background:#d1fae5;color:#065f46}
        .badge.danger{background:#fee2e2;color:#991b1b}.badge.info{background:#dbeafe;color:#1e40af}
        .tags span{display:inline-block;background:#ecfdf5;color:#065f46;border-radius:999px;padding:.1rem .6rem;margin:0 .25rem .25rem 0;font-size:.85rem}
        .photos{display:grid;gap:.5rem;grid-template-columns:repeat(auto-fill,minmax(12rem,1fr))}.photos img{width:100%;height:9rem;object-fit:cover;border-radius:.5rem}
        table.info td{padding:.25rem 1rem .25rem 0;vertical-align:top}
        footer{color:#9ca3af;text-align:center;font-size:.8rem;padding:2rem 1rem}
    </style>
    @livewireStyles
</head>
<body>
<header class="site">
    <div class="wrap">
        <a class="brand" href="{{ url('/') }}">{{ $brand }}</a>
        <nav class="lang">
            @foreach (config('booking.locales') as $code)
                <a href="{{ url('/lang/'.$code) }}" class="{{ app()->getLocale() === $code ? 'on' : '' }}">{{ __('site.lang.'.$code) }}</a>
            @endforeach
        </nav>
    </div>
</header>
<main class="wrap">
    {{ $slot }}
</main>
<footer>{{ $brand }}</footer>
@livewireScripts
</body>
</html>
