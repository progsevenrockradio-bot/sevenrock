<x-layouts.talent :title="'Talentos - Dashboard'" :talent="$talent" :plan="$plan" :limits="$limits" :usage="$usage" :subscription="$subscription" :storageProgress="$storageProgress">
    <section class="space-y-6">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-end justify-between gap-4 border border-white/10 bg-[#10161b] p-8">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $talent->band_name }}</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">{{ $talent->bio ?: 'Sin biografía.' }}</p>
            </div>
            <div class="text-right text-sm text-[#7b7b7b]">
                <div class="font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">{{ ucfirst($talent->plan) }} plan</div>
                <div>{{ ucfirst($talent->subscription_status) }}</div>
                <div>Renueva: {{ $subscription?->end_date?->format('d M Y') ?? 'Sin fecha' }}</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1.1fr_.9fr]">
            <div class="border border-white/10 bg-[#10161b] p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Mi suscripción</div>
                        <h2 class="mt-2 font-display text-2xl uppercase tracking-[.12em] text-white">{{ ucfirst($talent->plan) }}</h2>
                        <p class="mt-1 text-sm text-[#8b8b8b]">
                            Estado: <span class="text-[#dcdcdc]">{{ ucfirst($talent->subscription_status) }}</span>
                            · Renovación: <span class="text-[#dcdcdc]">{{ $subscription?->end_date?->format('d/m/Y') ?? 'Sin fecha' }}</span>
                        </p>
                    </div>
                    <a href="{{ route('talents.subscriptions.plans') }}" class="lucille-button-solid">
                        {{ $talent->plan === 'free' ? 'Actualizar plan' : 'Gestionar suscripción' }}
                    </a>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                        <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Pasarela</div>
                        <div class="mt-2 text-sm text-white">{{ ucfirst((string) ($subscription?->payment_provider ?? $talent->payment_provider ?? 'manual')) }}</div>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                        <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Próximo cobro</div>
                        <div class="mt-2 text-sm text-white">{{ $subscription?->end_date?->format('d M Y') ?? 'Sin fecha' }}</div>
                    </div>
                </div>
            </div>

            <div class="border border-white/10 bg-[#10161b] p-6">
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

        <div class="mt-6 grid gap-4 md:grid-cols-4">
            <div class="border border-white/10 bg-[#10161b] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Visitas totales</div>
                <div class="mt-2 font-display text-3xl text-white">{{ $usage['visits'] }}</div>
            </div>
            <div class="border border-white/10 bg-[#10161b] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Canciones subidas</div>
                <div class="mt-2 font-display text-3xl text-white">{{ $usage['songs'] }}</div>
            </div>
            <div class="border border-white/10 bg-[#10161b] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fotos subidas</div>
                <div class="mt-2 font-display text-3xl text-white">{{ $usage['photos'] }}</div>
            </div>
            <div class="border border-white/10 bg-[#10161b] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Días como miembro</div>
                <div class="mt-2 font-display text-3xl text-white">{{ $talent->created_at?->diffInDays(now()) ?? 0 }}</div>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
            <div class="border border-white/10 bg-[#10161b] p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Media reciente</h2>
                    <a href="{{ route('talents.media.index') }}" class="lucille-button">Gestionar media</a>
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

            <div class="border border-white/10 bg-[#10161b] p-6">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Límites del plan</h2>
                <div class="mt-4 space-y-3 text-sm text-[#7b7b7b]">
                    @foreach ($limits as $type => $limit)
                        <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-2">
                            <span>{{ ucfirst($type) }}</span>
                            <span class="text-[#dcdcdc]">{{ $limit === 0 ? '0' : $limit }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('talents.profile') }}" class="lucille-button-solid">Edit profile</a>
                    <a href="{{ route('talents.media.index') }}" class="lucille-button">Media library</a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.talent>
