@props(['class' => 'h-10 w-auto'])

<img src="{{ asset('LOGO.png') }}" alt="{{ config('app.name', 'SignTech') }}" {{ $attributes->merge(['class' => $class . ' object-contain']) }}>
