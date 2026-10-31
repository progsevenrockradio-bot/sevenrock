@props(['title', 'accent' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'text-center']) }}>
    <h2 class="lucille-heading text-[30px] leading-tight md:text-[34px]">
        {!! formatear_titulo(trim($title . ($accent ? ' ' . $accent : ''))) !!}
    </h2>
    @if ($subtitle)
        <p class="lucille-subtitle mt-4 text-[12px] leading-6 md:text-sm">{{ $subtitle }}</p>
    @endif
</div>
