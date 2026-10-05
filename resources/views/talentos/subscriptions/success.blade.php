<x-layouts.talent :title="'Talentos - Suscripción confirmada'" :talent="auth('talent')->user()">
    <section class="border border-white/10 bg-[#10161b] p-4 sm:p-8">
        <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Pago recibido</div>
        <h1 class="mt-2 font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-white">Suscripción en proceso</h1>
        <p class="mt-4 max-w-2xl text-sm text-[#8b8b8b]">
            Hemos recibido la confirmación de la pasarela. Si la suscripción depende de webhook, se activará en cuanto llegue la notificación.
        </p>
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a href="{{ route('talents.dashboard') }}" class="lucille-button-solid w-full sm:w-auto text-center">Ir al panel</a>
            <a href="{{ route('talents.subscriptions.plans') }}" class="lucille-button w-full sm:w-auto text-center">Ver planes</a>
        </div>
    </section>
</x-layouts.talent>
