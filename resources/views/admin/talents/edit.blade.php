<x-layouts.admin :title="'Editar banda: ' . $talent->band_name">
    @php
        $planLabels = [
            'free' => 'Gratuito (Free)',
            'basic' => 'Básico',
            'pro' => 'Pro',
            'premium' => 'Premium',
        ];
        $statusLabels = [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
            'cancelled' => 'Cancelada',
        ];
        $allStatusLabels = [
            'active' => 'Activa',
            'pending' => 'Pendiente',
            'expired' => 'Vencida',
            'inactive' => 'Inactiva',
            'cancelled' => 'Cancelada',
        ];
        $isPubliclyVisible = !$talent->is_hidden && $talent->subscription_status === 'active';
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div class="flex items-center gap-3.5">
                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-white/10 bg-[#151518] flex items-center justify-center">
                    @if ($talent->logoUrl())
                        <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" loading="lazy" class="h-full w-full object-cover">
                    @else
                        <span class="font-display text-sm uppercase text-gray-400">{{ mb_substr($talent->band_name, 0, 2) }}</span>
                    @endif
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas del Muro · Edición de cuenta</div>
                    <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">{{ $talent->band_name }}</h1>
                    <p class="mt-0.5 text-xs text-gray-400">{{ $talent->email }} · Alta: {{ $talent->created_at?->format('d/m/Y') ?? 'N/D' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" target="_blank" rel="noreferrer" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Ver perfil público</span>
                </a>
                <a href="{{ route('admin.talents.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Volver a bandas</span>
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                <p class="font-semibold">Revisa los campos señalados:</p>
                <ul class="mt-1 list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Resumen de estado actual (Plan · Suscripción · Visibilidad pública) --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Plan contratado</div>
                <div class="mt-1 text-sm font-semibold text-white">{{ $planLabels[$talent->plan] ?? ucfirst((string) $talent->plan) }}</div>
                <div class="mt-0.5 text-[11px] text-gray-400">Define cuotas de archivos y espacio</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Estado de suscripción</div>
                <div class="mt-1 text-sm font-semibold text-white">{{ $allStatusLabels[$talent->subscription_status] ?? ucfirst((string) $talent->subscription_status) }}</div>
                <div class="mt-0.5 text-[11px] text-gray-400">Vigencia administrativa de la cuenta</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Visibilidad pública</div>
                <div class="mt-1 flex items-center gap-1.5 text-sm font-semibold {{ $isPubliclyVisible ? 'text-emerald-300' : 'text-gray-300' }}">
                    <span class="h-2 w-2 rounded-full {{ $isPubliclyVisible ? 'bg-emerald-400' : 'bg-gray-500' }}"></span>
                    <span>{{ $isPubliclyVisible ? 'Visible en el Muro del Rock' : 'Oculta en el Muro del Rock' }}</span>
                </div>
                <div class="mt-0.5 text-[11px] text-gray-400">{{ $talent->is_featured ? 'Marcada como banda destacada' : 'Listado estándar' }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.talents.update', $talent) }}" class="grid gap-5 lg:grid-cols-[1.2fr_.8fr] items-start">
            @csrf
            @method('PUT')

            {{-- Panel principal de edición --}}
            <div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
                <h2 class="font-display text-sm uppercase tracking-wider text-white border-b border-white/5 pb-2.5">Datos de la banda y suscripción</h2>

                <div>
                    <label for="talent-band-name" class="mb-1.5 block text-xs font-medium text-gray-300">Nombre de la banda</label>
                    <input
                        id="talent-band-name"
                        name="band_name"
                        value="{{ old('band_name', $talent->band_name) }}"
                        required
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                </div>

                <div>
                    <label for="talent-bio" class="mb-1.5 block text-xs font-medium text-gray-300">Biografía y presentación</label>
                    <textarea
                        id="talent-bio"
                        name="bio"
                        rows="6"
                        class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs leading-relaxed text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >{{ old('bio', $talent->bio) }}</textarea>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="talent-plan" class="mb-1.5 block text-xs font-medium text-gray-300">Plan</label>
                        <select
                            id="talent-plan"
                            name="plan"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        >
                            @foreach ($planLabels as $plan => $label)
                                <option value="{{ $plan }}" @selected(old('plan', $talent->plan) === $plan)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="talent-subscription-status" class="mb-1.5 block text-xs font-medium text-gray-300">Estado de suscripción</label>
                        <select
                            id="talent-subscription-status"
                            name="subscription_status"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        >
                            @foreach ($statusLabels as $key => $label)
                                <option value="{{ $key }}" @selected(old('subscription_status', $talent->subscription_status) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="flex items-center gap-2.5 rounded-lg border border-white/5 bg-[#151518]/70 px-3 py-2.5 text-xs text-gray-200 cursor-pointer">
                    <input
                        type="checkbox"
                        name="is_featured"
                        value="1"
                        @checked(old('is_featured', $talent->is_featured))
                        class="h-4 w-4 rounded border-white/20 bg-[#101012] text-[#c32720] focus:ring-[#c32720]"
                    >
                    <span>Destacar banda en las primeras posiciones del Muro del Rock</span>
                </label>

                <div class="flex flex-wrap items-center gap-2.5 pt-2 border-t border-white/5">
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Guardar cambios
                    </button>
                    <a href="{{ route('admin.talents.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Cancelar
                    </a>
                </div>
            </div>

            {{-- Historial de suscripciones y pagos --}}
            <div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-white/5 pb-2.5">
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Historial de suscripciones</h2>
                    <span class="text-[11px] text-gray-400">{{ $talent->subscriptions->count() }} registros</span>
                </div>

                <div class="space-y-2.5">
                    @forelse ($talent->subscriptions as $subscription)
                        @php
                            $subPlan = $planLabels[$subscription->plan] ?? ucfirst((string) $subscription->plan);
                            $subState = $statusLabels[$subscription->status] ?? ($subscription->status === 'pending' ? 'Pendiente' : ($subscription->status === 'expired' ? 'Vencida' : ucfirst((string) $subscription->status)));
                        @endphp
                        <div class="rounded-lg border border-white/5 bg-[#151518] p-3 text-xs space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <strong class="text-white font-semibold">{{ $subPlan }}</strong>
                                <span class="inline-flex items-center rounded border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] font-medium text-gray-300">
                                    {{ $subState }}
                                </span>
                            </div>
                            <div class="text-[11px] text-gray-400">
                                €{{ number_format((float) $subscription->amount, 2) }} · {{ $subscription->currency }} · Pasarela: {{ ucfirst((string) ($subscription->payment_provider ?: 'manual')) }}
                            </div>
                            <div class="text-[11px] text-gray-400">
                                Vigencia: {{ $subscription->start_date?->format('d/m/Y') ?? 'N/D' }} — {{ $subscription->end_date?->format('d/m/Y') ?? 'N/D' }}
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-white/5 bg-[#151518]/50 p-4 text-center text-xs text-gray-400">
                            No hay suscripciones registradas para esta banda.
                        </div>
                    @endforelse
                </div>
            </div>
        </form>
    </div>
</x-layouts.admin>
