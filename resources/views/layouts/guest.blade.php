<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#34B182">
    <link rel="icon" type="image/png" href="{{ asset('LOGO.png') }}">

    <title>{{ $title ?? config('app.name', 'SignTech') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-surface text-ink">
<div class="flex min-h-screen">

    {{-- Panel brand (desktop) --}}
    <aside class="relative hidden w-[44%] overflow-hidden bg-gradient-to-br from-primary via-[#289c72] to-accent p-12 text-white lg:flex lg:flex-col shadow-lift">
        <div class="pointer-events-none absolute -top-24 -right-24 h-80 w-80 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-white/10"></div>

        <a href="/" class="relative flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white p-2 shadow-sm">
                <x-application-logo class="h-full w-full object-contain" />
            </div>
            <span class="text-2xl font-extrabold tracking-tight">SignTech</span>
        </a>

        <div class="relative my-auto max-w-md">
            <h2 class="text-4xl font-extrabold leading-tight tracking-tight">
                Belajar bahasa isyarat jadi lebih interaktif, mudah, dan terarah.
            </h2>
            <p class="mt-4 text-lg text-white/90 leading-relaxed">
                Latihan langsung dengan kamera AI, peroleh evaluasi real-time, kumpulkan XP & badge — guru dapat memantau perkembangan kelas secara komprehensif.
            </p>

            <ul class="mt-8 space-y-3 text-sm font-semibold">
                <li class="flex items-center gap-3 rounded-xl bg-white/15 px-4 py-3 backdrop-blur border border-white/20">
                    <x-icon name="camera" class="h-5 w-5 text-secondary" />
                    <span>AI Practice dengan deteksi gesture otomatis</span>
                </li>
                <li class="flex items-center gap-3 rounded-xl bg-white/15 px-4 py-3 backdrop-blur border border-white/20">
                    <x-icon name="trophy" class="h-5 w-5 text-secondary" />
                    <span>XP, level berjenjang, streak & tantangan harian</span>
                </li>
                <li class="flex items-center gap-3 rounded-xl bg-white/15 px-4 py-3 backdrop-blur border border-white/20">
                    <x-icon name="users" class="h-5 w-5 text-secondary" />
                    <span>Monitoring performa kelas dan penugasan guru</span>
                </li>
            </ul>
        </div>

        <p class="relative text-xs text-white/75">SignTech © 2026 · SIBI & BISINDO Indonesia</p>
    </aside>

    {{-- Form --}}
    <main class="flex flex-1 flex-col items-center justify-center bg-surface px-4 py-10 sm:px-8">
        <a href="/" class="mb-8 flex items-center gap-3 lg:hidden">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white p-1.5 shadow-sm ring-1 ring-surface-border">
                <x-application-logo class="h-full w-full object-contain" />
            </div>
            <span class="text-2xl font-extrabold tracking-tight text-ink">Sign<span class="text-primary">Tech</span></span>
        </a>

        <div class="w-full max-w-md rounded-card border border-surface-border bg-white p-6 shadow-card sm:p-8">
            {{ $slot }}
        </div>

        <a href="/" class="mt-6 text-sm font-semibold text-ink-muted hover:text-primary inline-flex items-center gap-1.5">
            <x-icon name="arrow-right" class="h-4 w-4 rotate-180" /> Kembali ke beranda
        </a>
    </main>
</div>
</body>
</html>
