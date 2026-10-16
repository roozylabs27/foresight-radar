{{-- Link styled like the app's Button, for the Blade error pages. --}}
@props(['href', 'variant' => 'default', 'size' => 'lg'])

@php
    $variants = [
        'default' => 'bg-primary text-primary-foreground hover:bg-primary-hover',
        'outline' => 'border border-input bg-card text-foreground hover:bg-accent',
    ];
    $sizes = [
        'sm' => 'h-9 px-3.5',
        'lg' => 'h-10 min-w-[150px] px-5',
    ];
@endphp

<a href="{{ $href }}" {{ $attributes->class([
    'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
    $variants[$variant] ?? $variants['default'],
    $sizes[$size] ?? $sizes['lg'],
]) }}>{{ $slot }}</a>
