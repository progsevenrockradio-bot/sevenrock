<x-layouts.talent :title="'Talentos - Notificaciones'">
    <section class="space-y-6">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.18)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="border border-white/10 bg-[#10161b] p-8">
            <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Preferencias</div>
            <h1 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-white">Notificaciones por email</h1>
        </div>

        <form method="POST" action="{{ route('talents.notifications.update') }}" class="border border-white/10 bg-[#10161b] p-8 space-y-5">
            @csrf
            @method('PUT')

            <label class="flex items-center gap-3 text-sm text-[#d8d8d8]">
                <input type="checkbox" name="likes" value="1" @checked($talent?->notificationPreferenceEnabled('likes'))>
                <span>Recibir email cuando alguien me da like</span>
            </label>

            <label class="flex items-center gap-3 text-sm text-[#d8d8d8]">
                <input type="checkbox" name="comments" value="1" @checked($talent?->notificationPreferenceEnabled('comments'))>
                <span>Recibir email por comentarios</span>
            </label>

            <label class="flex items-center gap-3 text-sm text-[#d8d8d8]">
                <input type="checkbox" name="renewals" value="1" @checked($talent?->notificationPreferenceEnabled('renewals'))>
                <span>Recibir recordatorios de pago</span>
            </label>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="lucille-button-solid">Guardar cambios</button>
                <a href="{{ route('talents.dashboard') }}" class="lucille-button">Volver</a>
            </div>
        </form>
    </section>
</x-layouts.talent>
