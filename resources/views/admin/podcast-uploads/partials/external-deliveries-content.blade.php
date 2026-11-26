@php
    $pendingExternalDeliveries = $pendingExternalDeliveries ?? collect();
    $resolvedExternalDeliveries = $resolvedExternalDeliveries ?? collect();
    $allMasterPrograms = $allMasterPrograms ?? collect();
    $externalReceptionSettings = $externalReceptionSettings ?? [
        'mailbox_email' => '',
        'telegram_chat_id' => '',
        'since_date' => '',
        'has_telegram_token' => false,
    ];
    $sinceDateForInput = '';
    if (! empty($externalReceptionSettings['since_date'])) {
        try {
            $sinceDateForInput = \Illuminate\Support\Carbon::parse((string) $externalReceptionSettings['since_date'])->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            $sinceDateForInput = '';
        }
    }
@endphp

<div x-data="{ tab: 'pending', manualOpen: false, settingsOpen: false }" class="space-y-4">
    {{-- Pestañas + configuración + registro manual --}}
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-3">
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                @click="tab = 'pending'"
                class="h-8 rounded-lg px-3 text-xs font-semibold transition-colors"
                :class="tab === 'pending' ? 'bg-[#c32720] text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10'"
            >
                Pendientes ({{ $pendingExternalDeliveries->count() }})
            </button>
            <button
                type="button"
                @click="tab = 'resolved'"
                class="h-8 rounded-lg px-3 text-xs font-semibold transition-colors"
                :class="tab === 'resolved' ? 'bg-white/15 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10'"
            >
                Historial ({{ $resolvedExternalDeliveries->count() }})
            </button>
        </div>

        <div class="flex items-center gap-1.5">
            <button
                type="button"
                @click="settingsOpen = !settingsOpen; if (settingsOpen) manualOpen = false"
                class="h-8 rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-gray-200 hover:bg-white/10 hover:text-white transition-colors"
            >
                <span x-text="settingsOpen ? 'Ocultar configuración' : 'Configuración'"></span>
            </button>
            <button
                type="button"
                @click="manualOpen = !manualOpen; if (manualOpen) settingsOpen = false"
                class="h-8 rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-gray-200 hover:bg-white/10 hover:text-white transition-colors"
            >
                <span x-text="manualOpen ? 'Ocultar manual' : '+ Registrar enlace'"></span>
            </button>
        </div>
    </div>

    {{-- Configuración administrable (buzón, destino Telegram y fecha de inicio) --}}
    <form
        x-show="settingsOpen"
        x-cloak
        action="{{ route('admin.podcast-uploads.external-deliveries.settings') }}"
        method="POST"
        class="rounded-xl border border-white/10 bg-[#141418] p-4 space-y-3"
    >
        @csrf
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-xs font-semibold text-white">Configuración de recepción externa</div>
            <span class="rounded-md px-2 py-0.5 text-[10px] font-semibold {{ ! empty($externalReceptionSettings['has_telegram_token']) ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                Bot Telegram: {{ ! empty($externalReceptionSettings['has_telegram_token']) ? 'Token configurado' : 'Sin token' }}
            </span>
        </div>
        <p class="text-[11px] text-gray-400">
            Estos parámetros se aplican al instante sin cambios de código ni reinicios. Los remitentes se activan por programa maestro.
        </p>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-[11px] font-medium text-gray-400">Buzón receptor (IMAP)</label>
                <input
                    type="email"
                    name="mailbox_email"
                    value="{{ old('mailbox_email', $externalReceptionSettings['mailbox_email']) }}"
                    placeholder="prog.sevenrockradio@gmail.com"
                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                >
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium text-gray-400">Destino Telegram administrativo (chat_id)</label>
                <input
                    type="text"
                    name="telegram_chat_id"
                    value="{{ old('telegram_chat_id', $externalReceptionSettings['telegram_chat_id']) }}"
                    placeholder="Ej. -1001234567890"
                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                >
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-[11px] font-medium text-gray-400">Fecha y hora de inicio de consulta ({{ config('app.timezone', 'Europe/Madrid') }})</label>
                <input
                    type="datetime-local"
                    name="since_date"
                    value="{{ old('since_date', $sinceDateForInput) }}"
                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                >
                <p class="mt-1 text-[11px] text-gray-500">Solo se procesarán correos recibidos a partir de esta fecha para evitar avisos históricos.</p>
            </div>
        </div>
        <div class="flex justify-end pt-1">
            <button
                type="submit"
                class="h-8 rounded-lg bg-[#c32720] hover:bg-[#d63028] px-3.5 text-xs font-semibold text-white transition-colors"
            >
                Guardar configuración
            </button>
        </div>
    </form>

    {{-- Formulario de registro manual de enlace y asociación de remitente --}}
    <form
        x-show="manualOpen"
        x-cloak
        action="{{ route('admin.podcast-uploads.external-deliveries.manual') }}"
        method="POST"
        class="rounded-xl border border-white/10 bg-[#141418] p-4 space-y-3"
    >
        @csrf
        <div class="text-xs font-semibold text-white">Registrar enlace externo manualmente</div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-[11px] font-medium text-gray-400">Enlace de entrega (Google Drive, Smash, TransferNow, WeTransfer u otro)</label>
                <input
                    type="url"
                    name="delivery_url"
                    required
                    placeholder="https://we.tl/t-..."
                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                >
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium text-gray-400">Programa (opcional)</label>
                <select name="master_program_id" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                    <option value="">Pendiente de identificar</option>
                    @foreach ($allMasterPrograms as $prog)
                        <option value="{{ $prog->id }}">{{ $prog->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium text-gray-400">Correo del remitente (opcional)</label>
                <input
                    type="email"
                    name="sender_email"
                    placeholder="productor@ejemplo.com"
                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                >
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium text-gray-400">Caducidad conocida (opcional)</label>
                <input
                    type="datetime-local"
                    name="expires_at"
                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                >
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium text-gray-400">Asunto / nota (opcional)</label>
                <input
                    type="text"
                    name="subject"
                    placeholder="Ej. Episodio de esta semana"
                    class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                >
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <label class="inline-flex items-center gap-2 text-xs text-gray-300 cursor-pointer">
                <input type="checkbox" name="associate_sender" value="1" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                <span>Asociar este remitente al programa seleccionado</span>
            </label>
            <button
                type="submit"
                class="h-8 rounded-lg bg-[#c32720] hover:bg-[#d63028] px-3.5 text-xs font-semibold text-white transition-colors"
            >
                Guardar entrega
            </button>
        </div>
    </form>

    {{-- Lista de pendientes --}}
    <div x-show="tab === 'pending'" class="space-y-3">
        @forelse ($pendingExternalDeliveries as $delivery)
            @php
                $isNeedsReview = $delivery->status === \App\Models\ExternalProgramDelivery::STATUS_NEEDS_REVIEW;
                $isSnoozed = $delivery->isSnoozed();
                $telegramFailed = $delivery->telegram_status === \App\Models\ExternalProgramDelivery::TELEGRAM_FAILED;
                $isExpired = $delivery->expires_at !== null && $delivery->expires_at->isPast();
                $isExpiringSoon = $delivery->expires_at !== null && $delivery->expires_at->isFuture() && $delivery->expires_at->lte(now()->addHours(48));
                $preferredPlatformLabel = $delivery->masterProgram?->preferredDeliveryPlatformLabel();
                $preferredPlatformKey = $delivery->masterProgram?->preferred_delivery_platform;
                $cardBorder = $isExpired
                    ? 'border-rose-500/40 bg-rose-950/15'
                    : ($isExpiringSoon
                        ? 'border-amber-400/45 bg-amber-950/15'
                        : ($isNeedsReview ? 'border-amber-500/30 bg-amber-950/10' : 'border-white/10 bg-[#141418]'));
            @endphp
            <article class="rounded-xl border {{ $cardBorder }} p-4 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-bold text-white">
                                {{ $delivery->programDisplayName() }}
                            </h3>
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $isNeedsReview ? 'bg-amber-500/20 text-amber-300' : ($isSnoozed ? 'bg-sky-500/20 text-sky-300' : 'bg-[#c32720]/20 text-rose-300') }}">
                                {{ $delivery->statusLabel() }}
                            </span>
                            @if ($isExpired)
                                <span class="inline-flex items-center rounded-md bg-rose-500/25 px-2 py-0.5 text-[11px] font-semibold text-rose-200">
                                    Enlace caducado
                                </span>
                            @elseif ($isExpiringSoon)
                                <span class="inline-flex items-center rounded-md bg-amber-500/25 px-2 py-0.5 text-[11px] font-semibold text-amber-200">
                                    Próximo a vencer
                                </span>
                            @endif
                            <span class="inline-flex items-center rounded-md bg-white/10 px-2 py-0.5 text-[11px] font-medium text-gray-200">
                                {{ $delivery->platformLabel() }}
                            </span>
                            @if ($preferredPlatformLabel !== null && $preferredPlatformKey !== $delivery->platform)
                                <span class="inline-flex items-center rounded-md border border-white/10 bg-white/5 px-2 py-0.5 text-[10px] text-gray-400">
                                    Habitual: {{ $preferredPlatformLabel }}
                                </span>
                            @endif
                        </div>
                        @if (filled($delivery->subject))
                            <p class="mt-1 text-xs text-gray-300">{{ $delivery->subject }}</p>
                        @endif
                    </div>
                    <span class="text-[11px] text-gray-400 font-mono">
                        #{{ $delivery->id }}
                    </span>
                </div>

                <dl class="grid gap-1.5 text-xs text-gray-300 sm:grid-cols-2">
                    <div>
                        <dt class="text-[11px] text-gray-500">Remitente</dt>
                        <dd class="truncate font-mono text-[11px] text-gray-200">
                            {{ $delivery->sender_email ?: ($delivery->raw_from_email ?: 'No especificado') }}
                            @if (filled($delivery->sender_name))
                                <span class="font-sans text-gray-400">({{ $delivery->sender_name }})</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-gray-500">Recepción</dt>
                        <dd class="text-gray-200">{{ $delivery->formattedReceivedAt() }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-gray-500">Caducidad</dt>
                        <dd class="{{ $delivery->expires_at ? 'text-amber-300 font-medium' : 'text-gray-400' }}">
                            {{ $delivery->formattedExpiresAt() }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-gray-500">Enlace</dt>
                        <dd class="truncate">
                            <a href="{{ $delivery->delivery_url }}" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline break-all">
                                {{ \Illuminate\Support\Str::limit($delivery->delivery_url, 55) }}
                            </a>
                        </dd>
                    </div>
                </dl>

                @if ($isSnoozed)
                    <div class="rounded-lg border border-sky-500/20 bg-sky-500/10 px-3 py-1.5 text-[11px] text-sky-200">
                        Recordatorios aplazados hasta {{ $delivery->formattedSnoozedUntil() }}.
                    </div>
                @endif

                @if (filled($delivery->incident_reason))
                    <div class="rounded-lg border border-amber-500/25 bg-amber-500/10 px-3 py-2 text-xs text-amber-200">
                        <strong>Incidencia / observación:</strong> {{ $delivery->incident_reason }}
                    </div>
                @endif

                @if ($telegramFailed)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">
                        <span><strong>Fallo aviso Telegram:</strong> {{ $delivery->telegram_last_error ?: 'Error al enviar aviso.' }}</span>
                        <form action="{{ route('admin.podcast-uploads.external-deliveries.retry-telegram', $delivery) }}" method="POST">
                            @csrf
                            <button type="submit" class="rounded-md bg-rose-500/20 hover:bg-rose-500/30 px-2.5 py-1 text-[11px] font-semibold text-rose-100">
                                Reintentar Telegram
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Asignación rápida de programa si está pendiente de identificar --}}
                @if ($delivery->master_program_id === null)
                    <form
                        action="{{ route('admin.podcast-uploads.external-deliveries.assign-program', $delivery) }}"
                        method="POST"
                        class="rounded-lg border border-white/10 bg-black/30 p-3 space-y-2"
                    >
                        @csrf
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select name="master_program_id" required class="flex-1 rounded-lg border border-white/10 bg-black/50 px-2.5 py-1.5 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                                <option value="">Seleccionar programa...</option>
                                @foreach ($allMasterPrograms as $prog)
                                    <option value="{{ $prog->id }}">{{ $prog->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="h-8 rounded-lg bg-white/10 hover:bg-white/20 px-3 text-xs font-semibold text-white">
                                Asignar programa
                            </button>
                        </div>
                        @if (filled($delivery->sender_email ?: $delivery->raw_from_email))
                            <label class="inline-flex items-center gap-2 text-[11px] text-gray-300 cursor-pointer">
                                <input type="checkbox" name="associate_sender" value="1" class="h-3.5 w-3.5 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                                <span>Asociar remitente ({{ $delivery->sender_email ?: $delivery->raw_from_email }}) a este programa</span>
                            </label>
                        @endif
                    </form>
                @endif

                {{-- Acciones obligatorias --}}
                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-white/10">
                    <a
                        href="{{ $delivery->delivery_url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-white/10 hover:bg-white/15 px-3 text-xs font-semibold text-white transition-colors"
                    >
                        Abrir enlace ↗
                    </a>

                    <form action="{{ route('admin.podcast-uploads.external-deliveries.snooze', $delivery) }}" method="POST" class="inline-flex items-center gap-1">
                        @csrf
                        <select name="hours" class="h-8 rounded-lg border border-white/10 bg-black/40 px-2 text-[11px] text-gray-200 focus:border-[#c32720] focus:outline-none">
                            <option value="4">4 h</option>
                            <option value="24" selected>24 h</option>
                            <option value="48">48 h</option>
                        </select>
                        <button
                            type="submit"
                            class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 px-2.5 text-xs font-medium text-gray-200 transition-colors"
                        >
                            Recordar más tarde
                        </button>
                    </form>

                    <form action="{{ route('admin.podcast-uploads.external-deliveries.mark-downloaded', $delivery) }}" method="POST">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex h-8 items-center rounded-lg bg-emerald-600/80 hover:bg-emerald-600 px-3 text-xs font-semibold text-white transition-colors"
                        >
                            Marcar como descargado
                        </button>
                    </form>

                    <form action="{{ route('admin.podcast-uploads.external-deliveries.discard', $delivery) }}" method="POST" onsubmit="return confirm('¿Descartar este aviso? Se conservará en el historial.');">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex h-8 items-center rounded-lg border border-rose-500/25 bg-rose-500/10 hover:bg-rose-500/20 px-2.5 text-xs font-medium text-rose-300 transition-colors"
                        >
                            Descartar aviso
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-white/10 bg-white/[0.02] p-6 text-center text-xs text-gray-400">
                No hay programas externos pendientes en este momento.
            </div>
        @endforelse
    </div>

    {{-- Historial de resueltos / descartados (trazabilidad) --}}
    <div x-show="tab === 'resolved'" x-cloak class="space-y-2.5">
        @forelse ($resolvedExternalDeliveries as $delivery)
            <article class="rounded-xl border border-white/5 bg-white/[0.02] p-3.5 text-xs space-y-1.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-gray-200">{{ $delivery->programDisplayName() }}</span>
                        <span class="rounded-md px-2 py-0.5 text-[10px] font-semibold {{ $delivery->status === \App\Models\ExternalProgramDelivery::STATUS_DOWNLOADED ? 'bg-emerald-500/20 text-emerald-300' : 'bg-white/10 text-gray-400' }}">
                            {{ $delivery->statusLabel() }}
                        </span>
                        <span class="text-[11px] text-gray-500">{{ $delivery->platformLabel() }}</span>
                    </div>
                    <span class="text-[11px] text-gray-500">
                        {{ $delivery->resolved_at ? $delivery->resolved_at->copy()->setTimezone($delivery->effectiveTimezone())->format('d/m/Y H:i') : '' }}
                    </span>
                </div>
                <div class="text-[11px] text-gray-400">
                    Remitente: <span class="font-mono text-gray-300">{{ $delivery->sender_email ?: 'No especificado' }}</span> · Recibido: {{ $delivery->formattedReceivedAt() }}
                </div>
                @if (filled($delivery->resolution_notes))
                    <div class="text-[11px] text-gray-500">{{ $delivery->resolution_notes }}</div>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-white/5 bg-white/[0.02] p-5 text-center text-xs text-gray-500">
                Todavía no hay entregas resueltas o descartadas en el historial.
            </div>
        @endforelse
    </div>
</div>

