<x-layouts.admin :title="'Editar talento'">
    <section class="space-y-6">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.18)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="border border-white/10 bg-[#10161b] p-6">
            <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Talento</div>
            <h1 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-white">Editar {{ $talent->band_name }}</h1>
        </div>

        <form method="POST" action="{{ route('admin.talents.update', $talent) }}" class="grid gap-6 lg:grid-cols-[1.1fr_.9fr]">
            @csrf
            @method('PUT')

            <div class="space-y-5 border border-white/10 bg-[#10161b] p-6">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre</label>
                    <input name="band_name" value="{{ old('band_name', $talent->band_name) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Bio</label>
                    <textarea name="bio" rows="8" class="lucille-product-field w-full">{{ old('bio', $talent->bio) }}</textarea>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Plan</label>
                        <select name="plan" class="lucille-product-field w-full">
                            @foreach (['free', 'basic', 'pro', 'premium'] as $plan)
                                <option value="{{ $plan }}" @selected(old('plan', $talent->plan) === $plan)>{{ ucfirst($plan) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Estado</label>
                        <select name="subscription_status" class="lucille-product-field w-full">
                            @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'cancelled' => 'Cancelled'] as $key => $label)
                                <option value="{{ $key }}" @selected(old('subscription_status', $talent->subscription_status) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label class="flex items-center gap-3 text-sm text-[#d8d8d8]">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $talent->is_featured))>
                    <span>Marcar como destacado</span>
                </label>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="lucille-button-solid">Guardar</button>
                    <a href="{{ route('admin.talents.index') }}" class="lucille-button">Volver</a>
                </div>
            </div>

            <div class="space-y-5 border border-white/10 bg-[#10161b] p-6">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-white">Historial de pagos</h2>
                <div class="space-y-3">
                    @forelse ($talent->subscriptions as $subscription)
                        <div class="border border-white/10 bg-[#151515] p-4 text-sm text-[#cfcfcf]">
                            <div class="flex items-center justify-between gap-3">
                                <strong class="text-white">{{ ucfirst($subscription->plan) }}</strong>
                                <span>{{ ucfirst($subscription->status) }}</span>
                            </div>
                            <div class="mt-2 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                                €{{ number_format((float) $subscription->amount, 2) }} · {{ $subscription->currency }} · {{ ucfirst((string) $subscription->payment_provider) }}
                            </div>
                            <div class="mt-1 text-xs text-[#8b8b8b]">
                                {{ $subscription->start_date?->format('d/m/Y') ?? 'N/D' }} - {{ $subscription->end_date?->format('d/m/Y') ?? 'N/D' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-[#8b8b8b]">No hay suscripciones registradas.</div>
                    @endforelse
                </div>
            </div>
        </form>
    </section>
</x-layouts.admin>
