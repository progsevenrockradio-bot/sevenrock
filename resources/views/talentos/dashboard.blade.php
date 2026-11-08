<x-layouts.talent :title="'Talentos - Dashboard'" :talent="$talent" :plan="$plan" :limits="$limits" :usage="$usage" :subscription="$subscription" :storageProgress="$storageProgress">
    <section class="space-y-6">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (window.SevenRockAnalytics) {
                        window.SevenRockAnalytics.track('band_registration_completed', {
                            category: 'band',
                            plan: '{{ $talent->plan ?? "free" }}'
                        });
                    }
                });
            </script>
        @endif

        <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between gap-4 border border-white/10 bg-[#10161b] p-4 sm:p-8">
            <div class="w-full sm:w-auto">
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $talent->band_name }}</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">{{ $talent->bio ?: 'Sin biografía.' }}</p>
            </div>
            <div class="text-left sm:text-right w-full sm:w-auto text-sm text-[#7b7b7b]">
                <div class="font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">Plan {{ ucfirst($talent->plan) }}</div>
                <div>Estado: {{ match($talent->subscription_status) { 'active' => 'Activo', 'expired' => 'Pausado (Vencido)', 'pending' => 'Pendiente', 'cancelled' => 'Cancelado', default => ucfirst($talent->subscription_status) } }}</div>
                <div>Servicio incluido hasta: {{ $subscription?->end_date ? $subscription->end_date->format('d/m/Y') . ' inclusive' : 'Sin fecha' }}</div>
                @if ($talent->isPaidPlan() && ($talent->is_hidden || $talent->subscription_status === 'expired'))
                    <div class="text-amber-400 font-medium">Recuperación hasta: {{ $talent->recoveryPeriodEndsAt()?->format('d/m/Y') ?? 'N/D' }}</div>
                @endif
            </div>
        </div>

        {{-- Tarjeta informativa del Período de Recuperación de 30 días para Básico y Pro --}}
        @if ($talent->isPaidPlan() && ($talent->is_hidden || $talent->subscription_status === 'expired'))
            @php
                $recoveryEndsAt = $talent->recoveryPeriodEndsAt();
            @endphp
            <div class="rounded-sm border border-amber-500/30 bg-amber-500/10 p-5 text-sm text-amber-200 space-y-3">
                <div class="flex items-center gap-2.5 font-display text-base uppercase tracking-wider text-amber-300">
                    <span class="text-xl">⚠️</span>
                    <span>Perfil pausado en el Muro del Rock</span>
                </div>
                <div class="text-xs text-amber-100/90 leading-relaxed space-y-1.5">
                    <p>
                        Tu suscripción del Plan {{ ucfirst($talent->plan) }} finalizó el <strong class="text-white">{{ $subscription?->end_date ? $subscription->end_date->format('d/m/Y') : 'recientemente' }}</strong>.
                        Tu ficha pública se encuentra oculta en el Muro del Rock y se han pausado nuevas subidas de archivos.
                    </p>
                    <p>
                        <strong class="text-amber-300">Período de recuperación (30 días):</strong>
                        Tienes hasta el <strong class="text-white">{{ $recoveryEndsAt ? $recoveryEndsAt->format('d/m/Y') : 'próximamente' }} inclusive</strong> para gestionar tu cuenta.
                    </p>
                    <p class="text-[11px] text-amber-200/80">
                        Conservas acceso completo a tu panel. Te recomendamos <strong>descargar tus canciones y archivos</strong> de forma individual desde la sección de medios o <strong>renovar tu suscripción</strong> para reactivar tu perfil público de inmediato.
                    </p>
                </div>
                <div class="pt-1 flex flex-wrap gap-3">
                    <a href="{{ route('talents.subscriptions.plans') }}" class="lucille-button-solid text-xs py-2.5 px-4 uppercase tracking-wider">
                        Renovar suscripción
                    </a>
                    <a href="{{ route('talents.media.index') }}" class="lucille-button text-xs py-2.5 px-4 uppercase tracking-wider">
                        Descargar mis archivos
                    </a>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1.1fr_.9fr]">
            <div class="border border-white/10 bg-[#10161b] p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Mi suscripción</div>
                        <h2 class="mt-2 font-display text-2xl uppercase tracking-[.12em] text-white">{{ ucfirst($talent->plan) }}</h2>
                        <p class="mt-1 text-sm text-[#8b8b8b]">
                            Estado: <span class="text-[#dcdcdc]">{{ match($talent->subscription_status) { 'active' => 'Activo', 'expired' => 'Pausado (Vencido)', 'pending' => 'Pendiente', 'cancelled' => 'Cancelado', default => ucfirst($talent->subscription_status) } }}</span>
                            · Servicio incluido hasta: <span class="text-[#dcdcdc]">{{ $subscription?->end_date ? $subscription->end_date->format('d/m/Y') . ' inclusive' : 'Sin fecha' }}</span>
                            @if ($talent->isPaidPlan() && ($talent->is_hidden || $talent->subscription_status === 'expired'))
                                · <span class="text-amber-400">Fin recuperación: {{ $talent->recoveryPeriodEndsAt()?->format('d/m/Y') ?? 'N/D' }}</span>
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('talents.subscriptions.plans') }}" class="lucille-button-solid w-full sm:w-auto text-center shrink-0">
                        {{ $talent->plan === 'free' ? 'Actualizar plan' : 'Gestionar suscripción' }}
                    </a>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                        <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Pasarela</div>
                        <div class="mt-2 text-sm text-white">{{ ucfirst((string) ($subscription?->payment_provider ?? $talent->payment_provider ?? 'manual')) }}</div>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                        <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                            Servicio incluido hasta
                        </div>
                        <div class="mt-2 text-sm text-white">{{ $subscription?->end_date ? $subscription->end_date->format('d/m/Y') . ' inclusive' : 'Sin fecha' }}</div>
                    </div>
                </div>
            </div>

            <div class="border border-white/10 bg-[#10161b] p-4 sm:p-6">
                <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Uso del plan</div>
                <div class="mt-4 space-y-3 text-sm text-[#7b7b7b]">
                    <div class="flex items-center justify-between">
                        <span>Fotos</span>
                        <span class="text-white">{{ $usage['photos'] }}/{{ $limits['photos'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Canciones</span>
                        <span class="text-white">{{ $usage['songs'] }}/{{ $limits['songs'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Almacenamiento</span>
                        <span class="text-white">{{ number_format($usage['storage_used_mb'], 2) }} / {{ $limits['storage_mb'] }} MB</span>
                    </div>
                </div>
                @if ($talent->plan === 'free')
                    <a href="{{ route('talents.subscriptions.plans') }}" class="mt-5 inline-flex w-full justify-center rounded-[4px] border border-white/15 bg-white px-4 py-3 text-sm font-semibold uppercase tracking-[.12em] text-black">
                        Actualizar a Básico
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="border border-white/10 bg-[#10161b] p-4 sm:p-5">
                <div class="text-[10px] sm:text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Visitas totales</div>
                <div class="mt-2 font-display text-2xl sm:text-3xl text-white">{{ $usage['visits'] }}</div>
            </div>
            <div class="border border-white/10 bg-[#10161b] p-4 sm:p-5">
                <div class="text-[10px] sm:text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Canciones subidas</div>
                <div class="mt-2 font-display text-2xl sm:text-3xl text-white">{{ $usage['songs'] }}</div>
            </div>
            <div class="border border-white/10 bg-[#10161b] p-4 sm:p-5">
                <div class="text-[10px] sm:text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fotos subidas</div>
                <div class="mt-2 font-display text-2xl sm:text-3xl text-white">{{ $usage['photos'] }}</div>
            </div>
            <div class="border border-white/10 bg-[#10161b] p-4 sm:p-5">
                <div class="text-[10px] sm:text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Días como miembro</div>
                <div class="mt-2 font-display text-2xl sm:text-3xl text-white">{{ (int) floor($talent->created_at?->diffInDays(now()) ?? 0) }}</div>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[1.2fr_.8fr]">
            <div class="border border-white/10 bg-[#10161b] p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Media reciente</h2>
                    <a href="{{ route('talents.media.index') }}" class="lucille-button w-full sm:w-auto text-center shrink-0">Gestionar media</a>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($media as $item)
                        <div class="border border-[#2b2b2b] bg-[#151515] p-4 text-sm text-[#7b7b7b]">
                            <div class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $item->title ?: $item->filename }}</div>
                            <div class="mt-1">{{ ucfirst($item->type) }} · {{ number_format($item->size / 1024, 1) }} KB</div>
                        </div>
                    @empty
                        <div class="text-sm text-[#7b7b7b]">No media uploaded yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="border border-white/10 bg-[#10161b] p-4 sm:p-6">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Límites del plan</h2>
                <div class="mt-4 space-y-3 text-sm text-[#7b7b7b]">
                    <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-2">
                        <span>Canciones MP3 <span class="text-xs text-[#6b6b6b]">(máx. {{ (int) ($talent->maxFileSizeKb('mp3') / 1024) }} MB c/u)</span></span>
                        <span class="text-[#dcdcdc]">{{ $limits['songs'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-2">
                        <span>Fotografías <span class="text-xs text-[#6b6b6b]">(máx. {{ (int) ($talent->maxFileSizeKb('photo') / 1024) }} MB c/u)</span></span>
                        <span class="text-[#dcdcdc]">{{ $limits['photos'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-2">
                        <span>Documentos PDF @if (($limits['documents'] ?? 0) > 0)<span class="text-xs text-[#6b6b6b]">(máx. {{ (int) ($talent->maxFileSizeKb('document') / 1024) }} MB c/u)</span>@endif</span>
                        <span class="text-[#dcdcdc]">{{ ($limits['documents'] ?? 0) === 0 ? 'No incluido (0)' : $limits['documents'] }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-2">
                        @if (($limits['videos'] ?? 0) > 0)
                            <span>Vídeos MP4/WebM <span class="text-xs text-[#6b6b6b]">(máx. {{ (int) ($talent->maxFileSizeKb('video') / 1024) }} MB c/u · enlaces a YouTube permitidos en perfil)</span></span>
                        @else
                            <span>Archivos de vídeo <span class="text-xs text-[#6b6b6b]">(enlaces a YouTube permitidos en perfil)</span></span>
                        @endif
                        <span class="text-[#dcdcdc]">{{ ($limits['videos'] ?? 0) === 0 ? 'No incluido (0)' : $limits['videos'] }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-2">
                        <span>Almacenamiento total</span>
                        <span class="text-[#dcdcdc]">{{ $limits['storage_mb'] ?? 0 }} MB</span>
                    </div>
                </div>
                <div class="mt-5 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('talents.profile') }}" class="lucille-button-solid w-full sm:w-auto text-center">Editar perfil</a>
                    <a href="{{ route('talents.media.index') }}" class="lucille-button w-full sm:w-auto text-center">Mis archivos</a>
                </div>
            </div>
        </div>

        @if ($talent && ! $talent->hasVerifiedEmail())
            <div class="border border-amber-500/20 bg-amber-500/5 p-4 rounded-sm flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-xl">✉️</span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-300">Cuenta pendiente de verificación de correo</p>
                        <p class="text-xs text-[#8b8b8b] mt-0.5">Para vincular y gestionar tus maquetas enviadas con este correo ({{ $talent->email }}), debes acreditar la titularidad verificando tu correo.</p>
                    </div>
                </div>
            </div>
        @endif

        @if (($submissions ?? collect())->isNotEmpty())
            <div class="border border-white/10 bg-[#10161b] p-4 sm:p-6">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <div>
                        <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Tus Maquetas Enviadas a la Radio</h2>
                        <p class="mt-1 text-xs text-[#7b7b7b]">Envíos de audio registrados con tu dirección de correo ({{ $talent->email }}).</p>
                    </div>
                    <span class="rounded-sm bg-white/5 px-2.5 py-1 text-xs text-[#dcdcdc] font-mono">{{ $submissions->count() }} envío(s)</span>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($submissions as $sub)
                        <div class="border border-[#2b2b2b] bg-[#151515] p-4 rounded-sm flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs text-[var(--lucille-accent)] uppercase tracking-wider font-semibold font-display">Maqueta</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-sm font-mono {{ match($sub->status) { 'approved' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30', 'rejected' => 'bg-red-500/20 text-red-300 border border-red-500/30', default => 'bg-amber-500/20 text-amber-300 border border-amber-500/30' } }}">
                                        {{ match($sub->status) { 'approved' => 'Aprobada', 'rejected' => 'Rechazada', default => 'En revisión' } }}
                                    </span>
                                </div>
                                <h3 class="mt-2 font-display text-base text-white uppercase tracking-wide truncate" title="{{ $sub->song_title }}">{{ $sub->song_title }}</h3>
                                <p class="text-xs text-[#7b7b7b] mt-0.5">Enviada el {{ $sub->created_at?->format('d/m/Y') }}</p>
                            </div>
                            <div class="mt-3 pt-2 border-t border-[#2b2b2b] flex items-center justify-between text-xs">
                                @if ($sub->published_to_hub && $sub->release)
                                    <a href="{{ route('releases.show', $sub->release->slug) }}" target="_blank" class="text-[var(--lucille-accent)] hover:underline inline-flex items-center gap-1 font-semibold">
                                        Escuchar en Catálogo ↗
                                    </a>
                                @elseif ($sub->status === 'approved')
                                    <span class="text-[#7b7b7b] text-[11px]">En cola de publicación</span>
                                @else
                                    <span class="text-[#7b7b7b] text-[11px]">En revisión editorial</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</x-layouts.talent>
