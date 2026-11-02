@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert alert-success fixed top-20 right-5 left-4 z-[90] shadow-lg sm:left-auto sm:max-w-md']) }} role="status">
        <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
        <span>{{ $status }}</span>
    </div>
@endif
