<x-layouts.talent :title="'Talentos - Pago cancelado'" :talent="auth('talent')->user()">
    <section class="border border-white/10 bg-[#10161b] p-8">
        <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Operación cancelada</div>
        <h1 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-white">No se completó el pago</h1>
        <p class="mt-4 max-w-2xl text-sm text-[#8b8b8b]">
            No se ha efectuado ningún cobro. Puedes volver a seleccionar un plan cuando quieras.
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('talents.subscriptions.plans') }}" class="lucille-button-solid">Reintentar</a>
            <a href="{{ route('talents.dashboard') }}" class="lucille-button">Volver al panel</a>
        </div>
    </section>
</x-layouts.talent>
