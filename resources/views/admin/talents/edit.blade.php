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

        <div class="grid gap-5 lg:grid-cols-[1.2fr_.8fr] items-start">
            {{-- Panel principal de edición de datos (NO altera fechas de vigencia) --}}
            <form method="POST" action="{{ route('admin.talents.update', $talent) }}" class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
                @csrf
                @method('PUT')

                <div class="flex items-center justify-between border-b border-white/5 pb-2.5">
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Datos de la banda y perfil</h2>
                    <span class="text-[11px] text-gray-400">Edición de perfil</span>
                </div>

                <div class="rounded-lg border border-white/5 bg-white/[0.02] p-3 text-[11px] text-gray-400 leading-relaxed">
                    ℹ️ <strong>Nota administrativa:</strong> Guardar cambios en este formulario actualiza únicamente los datos del perfil y estado general. <strong>No modifica fechas de vencimiento ni confirma pagos.</strong> Para renovar el período, utiliza el panel de confirmación de pago manual.
                </div>

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
                        <label for="talent-plan" class="mb-1.5 block text-xs font-medium text-gray-300">Plan asignado</label>
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
                        <label for="talent-subscription-status" class="mb-1.5 block text-xs font-medium text-gray-300">Estado general</label>
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

                {{-- Enlaces externos propios de la banda (Apoyo y Merch) --}}
                <div class="rounded-lg border border-white/5 bg-[#151518]/50 p-3.5 space-y-3">
                    <div class="border-b border-white/5 pb-2">
                        <div class="text-xs font-semibold text-gray-200 uppercase tracking-wide">Enlaces externos de apoyo y venta directa</div>
                        <p class="mt-0.5 text-[11px] text-gray-400">
                            Enlaces propios de la banda (PayPal.Me, Ko-fi, Bandcamp, tienda oficial). Los pagos se realizan externamente sin mediación de Seven Rock Radio.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="admin-link-support" class="mb-1 block text-[11px] font-medium text-gray-300">
                                Apoyar a la banda <span class="text-gray-500 font-normal">(PayPal.Me, Ko-fi...)</span>
                            </label>
                            <input
                                type="url"
                                id="admin-link-support"
                                name="payment_links[support]"
                                value="{{ old('payment_links.support', $talent->supportPaymentUrl()) }}"
                                placeholder="https://paypal.me/tubanda"
                                class="h-8 w-full rounded-lg border border-white/10 bg-[#101012] px-2.5 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                            >
                        </div>
                        <div>
                            <label for="admin-link-merch" class="mb-1 block text-[11px] font-medium text-gray-300">
                                Comprar música / Merch <span class="text-gray-500 font-normal">(Bandcamp, tienda...)</span>
                            </label>
                            <input
                                type="url"
                                id="admin-link-merch"
                                name="payment_links[merch]"
                                value="{{ old('payment_links.merch', $talent->merchPaymentUrl()) }}"
                                placeholder="https://tubanda.bandcamp.com"
                                class="h-8 w-full rounded-lg border border-white/10 bg-[#101012] px-2.5 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                            >
                        </div>
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
                        Guardar cambios de perfil
                    </button>
                    <a href="{{ route('admin.talents.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Cancelar
                    </a>
                </div>
            </form>

            {{-- Columna derecha: Acción de renovación de pago manual + Historial --}}
            <div class="space-y-5">
                {{-- Tarjeta explícita: Confirmar pago y renovar (Básico / Pro) --}}
                @if (in_array($talent->plan, ['basic', 'pro'], true))
                    <div class="rounded-xl border border-emerald-500/30 bg-[#101012] p-5 space-y-4 shadow-lg shadow-black/40">
                        <div class="flex items-center justify-between border-b border-white/5 pb-2.5">
                            <div>
                                <span class="inline-flex items-center rounded-full bg-emerald-500/10 border border-emerald-500/30 px-2 py-0.5 text-[10px] font-semibold text-emerald-400 uppercase tracking-wider">
                                    Gestión manual
                                </span>
                                <h2 class="mt-1 font-display text-sm uppercase tracking-wider text-white">Confirmar pago y renovar</h2>
                            </div>
                            <span class="font-display text-base font-bold text-emerald-400">
                                {{ $renewalPreview['formatted_amount'] }}
                            </span>
                        </div>

                        @error('renewal')
                            <div class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-300">
                                {{ $message }}
                            </div>
                        @enderror

                        <div class="rounded-lg border border-white/5 bg-[#151518] p-3 text-xs space-y-2 text-gray-300">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Plan contratado:</span>
                                <strong class="text-white">{{ ucfirst($talent->plan) }} ({{ $renewalPreview['formatted_amount'] }})</strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Vigencia actual:</span>
                                @if ($renewalPreview['is_currently_active'] && $renewalPreview['current_end_date'])
                                    <span class="text-emerald-300 font-medium">Hasta el {{ $renewalPreview['current_end_date']->format('d/m/Y') }} inclusive</span>
                                @elseif ($renewalPreview['current_end_date'])
                                    <span class="text-red-400">Vencida el {{ $renewalPreview['current_end_date']->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-gray-400">Sin período registrado</span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between border-t border-white/5 pt-1.5">
                                <span class="text-gray-400">Nueva vigencia (+1 mes):</span>
                                <strong class="text-white bg-emerald-500/20 px-1.5 py-0.5 rounded border border-emerald-500/30">
                                    Hasta el {{ $renewalPreview['new_end_date']->format('d/m/Y') }} inclusive
                                </strong>
                            </div>
                            @if ($renewalPreview['is_currently_active'])
                                <p class="text-[11px] text-gray-400 leading-normal">
                                    ✓ <strong>Renovación anticipada:</strong> se añade 1 mes de calendario completo a partir del vencimiento actual. La banda no pierde días pagados.
                                </p>
                            @else
                                <p class="text-[11px] text-gray-400 leading-normal">
                                    ✓ <strong>Activación / Reactivación:</strong> el nuevo mes de calendario inicia hoy y finaliza el {{ $renewalPreview['new_end_date']->format('d/m/Y') }}.
                                </p>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('admin.talents.renew', $talent) }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="idempotency_token" value="{{ $idempotencyToken }}">
                            <input type="hidden" name="payment_provider" value="paypal">

                            <div>
                                <label for="payment-reference" class="mb-1 block text-xs font-medium text-gray-300">
                                    ID de operación en PayPal (recibido en Seven Rock Radio) <span class="text-emerald-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="payment-reference"
                                    name="payment_reference"
                                    value="{{ old('payment_reference') }}"
                                    required
                                    placeholder="Ej: 9X123456789012345"
                                    class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 font-mono uppercase"
                                >
                                <p class="mt-1 text-[11px] text-gray-400 leading-tight">
                                    Identificador canónico recibido en la cuenta vendedora de PayPal. No se exige coincidencia entre el nombre o correo del pagador y la cuenta de la banda.
                                </p>
                            </div>

                            <div>
                                <label for="payment-notes" class="mb-1 block text-xs font-medium text-gray-300">
                                    Notas internas / Referencia aportada por el pagador <span class="text-[11px] text-gray-400">(opcional)</span>
                                </label>
                                <input
                                    type="text"
                                    id="payment-notes"
                                    name="notes"
                                    value="{{ old('notes') }}"
                                    placeholder="Ej: Pagado por representante Carlos Gómez (carlos@...) vía PayPal"
                                    class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                >
                            </div>

                            <button
                                type="submit"
                                onclick="return confirm('¿Confirmar pago de {{ $renewalPreview['formatted_amount'] }} para {{ $talent->band_name }}?\n\nNueva vigencia: Hasta el {{ $renewalPreview['new_end_date']->format('d/m/Y') }} inclusive.');"
                                class="w-full inline-flex h-9 items-center justify-center rounded-lg bg-emerald-600 px-4 text-xs font-semibold text-white hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400 transition-colors shadow-sm"
                            >
                                Confirmar pago y renovar (+1 mes)
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Tarjeta explícita: Conceder cortesía (Plan Básico) --}}
                <div class="rounded-xl border border-blue-500/30 bg-[#101012] p-5 space-y-4 shadow-lg shadow-black/40">
                    <div class="flex items-center justify-between border-b border-white/5 pb-2.5">
                        <div>
                            <span class="inline-flex items-center rounded-full bg-blue-500/10 border border-blue-500/30 px-2 py-0.5 text-[10px] font-semibold text-blue-400 uppercase tracking-wider">
                                Cortesía institucional
                            </span>
                            <h2 class="mt-1 font-display text-sm uppercase tracking-wider text-white">Conceder cortesía (Básico)</h2>
                        </div>
                        <span class="font-display text-xs font-bold text-blue-300 uppercase tracking-wider">
                            0,00 € (Sin cobro)
                        </span>
                    </div>

                    @error('courtesy')
                        <div class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-300">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="rounded-lg border border-white/5 bg-[#151518] p-3 text-xs space-y-1.5 text-gray-300">
                        <p class="text-[11px] text-gray-400 leading-normal">
                            ✓ <strong>Sin inventar cobros:</strong> no genera registro de ingresos ni requiere identificador de PayPal.<br>
                            ✓ <strong>Acumulativa en Básico:</strong> si la banda ya tiene Plan Básico activo, añade los meses al final de su período sin perder días.<br>
                            ✓ <strong>Seguridad:</strong> al vencer, entra en período de recuperación de 30 días, sin purga de archivos.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.talents.courtesy', $talent) }}" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="courtesy-months" class="mb-1 block text-xs font-medium text-gray-300">Meses de cortesía</label>
                                <select id="courtesy-months" name="months" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-blue-400 focus:outline-none">
                                    <option value="2">2 meses (Cortesía inicial colaborador)</option>
                                    <option value="1">1 mes (Recompensa / Cortesía regular)</option>
                                </select>
                            </div>

                            <div>
                                <label for="courtesy-type" class="mb-1 block text-xs font-medium text-gray-300">Tipo de cortesía</label>
                                <select id="courtesy-type" name="courtesy_type" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-blue-400 focus:outline-none">
                                    <option value="initial_collaborator">Colaborador invitado (2 meses iniciales)</option>
                                    <option value="referral_reward">Recompensa por referidos</option>
                                    <option value="other">Otra cortesía autorizada</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="courtesy-notes" class="mb-1 block text-xs font-medium text-gray-300">Motivo / Notas internas</label>
                            <input
                                type="text"
                                id="courtesy-notes"
                                name="notes"
                                placeholder="Ej: Concedido por programa de radio o acuerdo con conductor..."
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-blue-400 focus:outline-none"
                            >
                        </div>

                        <button
                            type="submit"
                            class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-blue-500/30 bg-blue-500/20 px-4 text-xs font-semibold text-blue-200 hover:bg-blue-500/30 transition-colors"
                            data-confirm="¿Conceder la cortesía de Plan Básico a '{{ $talent->band_name }}'?"
                            data-confirm-action="Conceder cortesía"
                            data-confirm-tone="primary"
                        >
                            Conceder meses de cortesía
                        </button>
                    </form>
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
                            <div class="rounded-lg border border-white/5 bg-[#151518] p-3 text-xs space-y-1.5 {{ $subscription->is_refunded ? 'opacity-70 border-red-500/30' : '' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <strong class="text-white font-semibold">{{ $subPlan }}</strong>
                                        @if ($subscription->is_courtesy)
                                            <span class="rounded bg-blue-500/20 border border-blue-500/30 px-1.5 py-0.2 text-[10px] text-blue-300">
                                                Cortesía ({{ $subscription->courtesy_type === 'initial_collaborator' ? '2m iniciales colaborador' : 'Recompensa referidos' }})
                                            </span>
                                        @endif
                                        @if ($subscription->is_refunded)
                                            <span class="rounded bg-red-500/20 border border-red-500/30 px-1.5 py-0.2 text-[10px] text-red-300 font-semibold">
                                                ⚠️ Reembolsado/Anulado
                                            </span>
                                        @endif
                                    </div>
                                    <span class="inline-flex items-center rounded border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] font-medium {{ $subscription->status === 'active' ? 'text-emerald-300 border-emerald-500/30 bg-emerald-500/10' : 'text-gray-300' }}">
                                        {{ $subState }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-gray-400">
                                    €{{ number_format((float) $subscription->amount, 2) }} · {{ $subscription->currency }} · Modo: {{ ucfirst((string) ($subscription->payment_provider ?: 'manual')) }}
                                </div>
                                <div class="text-[11px] text-gray-300">
                                    Vigencia: {{ $subscription->start_date?->format('d/m/Y') ?? 'N/D' }} — {{ $subscription->end_date ? $subscription->end_date->format('d/m/Y') . ' inclusive' : 'N/D' }}
                                </div>
                                @if ($subscription->confirmed_at)
                                    <div class="text-[10px] text-emerald-400/90 pt-1 border-t border-white/5 flex flex-wrap items-center justify-between gap-1">
                                        <span>Confirmado: {{ $subscription->confirmed_at->format('d/m/Y H:i') }} ({{ $subscription->confirmedBy?->name ?? 'Admin' }})</span>
                                        @if ($subscription->payment_reference)
                                            <span class="font-mono bg-white/[0.04] px-1.5 py-0.5 rounded text-gray-300">Ref: {{ $subscription->payment_reference }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if ($subscription->is_refunded && $subscription->refund_reason)
                                    <div class="text-[10px] text-red-300 bg-red-500/10 p-1.5 rounded">
                                        Motivo anulación: {{ $subscription->refund_reason }} ({{ $subscription->refunded_at?->format('d/m/Y H:i') }})
                                    </div>
                                @endif

                                {{-- Botón para anular/reembolsar un pago real no anulado --}}
                                @if ($subscription->amount > 0 && ! $subscription->is_refunded)
                                    <div class="pt-1 border-t border-white/5 flex justify-end">
                                        <form method="POST" action="{{ route('admin.talents.subscriptions.refund', [$talent, $subscription]) }}" class="inline-flex items-center gap-1">
                                            @csrf
                                            <input type="text" name="refund_reason" placeholder="Motivo de reembolso..." class="h-7 rounded border border-white/10 bg-[#101012] px-2 text-[10px] text-white placeholder-gray-500 w-36">
                                            <button type="submit" class="h-7 rounded border border-red-500/30 bg-red-500/10 px-2 text-[10px] font-medium text-red-200 hover:bg-red-500/20" data-confirm="¿Marcar esta suscripción como anulada/reembolsada? Esto señalará las recompensas de referidos asociadas para revisión." data-confirm-action="Anular pago" data-confirm-tone="danger">
                                                Anular pago
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-lg border border-white/5 bg-[#151518]/50 p-4 text-center text-xs text-gray-400">
                                No hay suscripciones registradas para esta banda.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
