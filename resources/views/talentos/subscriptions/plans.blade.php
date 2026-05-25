<x-layouts.talent :title="'Talentos - Planes'" :talent="auth('talent')->user()">
    <section class="space-y-6">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="border border-white/10 bg-[#10161b] p-8">
            <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Suscripciones</div>
            <h1 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-white">Elige tu plan</h1>
            <p class="mt-3 max-w-3xl text-sm text-[#8b8b8b]">
                Selecciona el plan que mejor se ajusta a tu banda y elige la pasarela que prefieras para completar la suscripción.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
            @foreach ($plans as $key => $plan)
                @php
                    $isCurrent = $currentPlan === $key;
                @endphp

                <article class="border {{ $isCurrent ? 'border-white' : 'border-white/10' }} bg-[#10161b] p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $plan['label'] }}</div>
                            <div class="mt-2 font-display text-3xl text-white">{{ $plan['monthly_label'] }}</div>
                        </div>
                        @if ($isCurrent)
                            <span class="border border-white/20 px-3 py-1 text-[11px] uppercase tracking-[.18em] text-white">Actual</span>
                        @endif
                    </div>

                    <p class="mt-4 text-sm leading-6 text-[#8b8b8b]">{{ $plan['summary'] }}</p>

                    <ul class="mt-5 space-y-2 text-sm text-[#d5d5d5]">
                        @foreach ($plan['features'] as $feature)
                            <li class="flex gap-2">
                                <span class="text-white">•</span>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-6 border-t border-white/10 pt-5">
                        @if ($key === 'free')
                            <form method="POST" action="{{ route('talents.subscriptions.checkout') }}">
                                @csrf
                                <input type="hidden" name="plan" value="free">
                                <input type="hidden" name="gateway" value="stripe">
                                <button type="submit" class="lucille-button-solid w-full justify-center">Comenzar gratis</button>
                            </form>
                        @else
                            <details class="lucille-admin-dropdown">
                                <summary class="cursor-pointer list-none border border-white/15 bg-white px-4 py-3 text-center text-sm font-semibold uppercase tracking-[.12em] text-black">
                                    Elegir plan
                                </summary>
                                <div class="mt-3 space-y-2">
                                    @foreach ($gateways as $gatewayKey => $gateway)
                                        <form method="POST" action="{{ route('talents.subscriptions.checkout') }}">
                                            @csrf
                                            <input type="hidden" name="plan" value="{{ $key }}">
                                            <input type="hidden" name="gateway" value="{{ $gatewayKey }}">
                                            <button type="submit" class="lucille-button w-full justify-center">
                                                Pagar con {{ $gateway['label'] ?? ucfirst($gatewayKey) }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-layouts.talent>
