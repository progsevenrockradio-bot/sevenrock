@props(['id' => null, 'narrow' => false])

<section id="{{ $id }}" {{ $attributes->merge(['class' => 'section-band']) }}>
    <div class="{{ $narrow ? 'max-w-[898px]' : 'max-w-[1180px]' }} mx-auto px-6 lg:px-8">
        {{ $slot }}
    </div>
</section>
