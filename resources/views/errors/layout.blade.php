{{--
    Error pages for non-Inertia requests (Inertia visits render Pages/Error.jsx).
    Same design tokens and theme as the app: app.css through Vite, and the
    inline theme script so dark users get the dark page.

    Sections: title, code, badge, tone (warning | danger | info), heading,
    description, actions (use <x-error-button>).
--}}
@php
    $tones = [
        'warning' => 'bg-warning-soft text-warning',
        'danger' => 'bg-danger-soft text-danger',
        'info' => 'bg-accent text-primary',
    ];
    $tone = $tones[trim($__env->yieldContent('tone', 'danger'))] ?? $tones['danger'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @include('partials.theme-script')

    <title>@yield('title') - Foresight Radar</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet" />

    @vite('resources/css/app.css')
</head>
<body class="flex min-h-svh flex-col bg-background font-sans text-foreground antialiased">
    <header class="flex h-16 items-center justify-between gap-3 border-b border-border bg-card px-4 sm:px-6">
        <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3 rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50">
            <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-md bg-night text-scan dark:ring-1 dark:ring-border">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="size-5">
                    <circle cx="12" cy="12" r="9" />
                    <circle cx="12" cy="12" r="5" />
                    <path d="M12 12 L18.4 5.6" />
                    <circle cx="12" cy="12" r="1" fill="currentColor" />
                </svg>
            </span>
            <span class="flex min-w-0 flex-col">
                <span class="text-[15px] leading-tight font-bold text-foreground">Foresight Radar</span>
                <span class="text-[11px] font-medium tracking-[0.05em] text-subtle-foreground uppercase">Strategic Intel</span>
            </span>
        </a>

        @auth
            <x-error-button :href="route('dashboard.')" variant="outline" size="sm">Dashboard</x-error-button>
        @else
            <x-error-button :href="route('login')" size="sm">Masuk</x-error-button>
        @endauth
    </header>

    <main class="flex flex-1 items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-[580px] rounded-lg border border-border bg-card px-6 pt-10 pb-6 text-center shadow-md sm:px-10">
            <span class="inline-block rounded-full px-3 py-1 text-xs font-bold tracking-[0.04em] {{ $tone }}">
                @yield('badge')
            </span>

            <h1 class="mt-5 text-2xl font-bold text-foreground">@yield('heading')</h1>
            <p class="mx-auto mt-3 max-w-[460px] text-sm leading-relaxed text-muted-foreground">
                @yield('description')
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                @yield('actions')
            </div>

            <p class="mt-8 border-t border-border pt-4 text-xs text-muted-foreground">
                Kode Status HTTP:
                <code class="rounded-sm bg-muted px-1.5 py-0.5 font-mono font-semibold text-foreground">@yield('code')</code>
                | Foresight Radar Strategic Intelligence System
            </p>
        </div>
    </main>

    <footer class="border-t border-border bg-card px-6 py-4 text-center text-xs text-muted-foreground">
        Foresight Radar &copy; {{ date('Y') }} Strategic Horizon Scanning. All Rights Reserved.
    </footer>
</body>
</html>
