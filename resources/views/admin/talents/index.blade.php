<x-layouts.admin :title="'Talents'">
    <section class="space-y-6">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.18)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-4">
            <div class="border border-white/10 bg-[#10161b] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Total talentos</div>
                <div class="mt-2 font-display text-3xl text-white">{{ $stats['total'] ?? 0 }}</div>
                <div class="mt-1 text-xs text-[#8b8b8b]">Activos: {{ $stats['active'] ?? 0 }} · Inactivos: {{ $stats['inactive'] ?? 0 }}</div>
            </div>
            <div class="border border-white/10 bg-[#10161b] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Ingresos mensuales</div>
                <div class="mt-2 font-display text-3xl text-white">€{{ number_format((float) ($stats['monthly_revenue'] ?? 0), 2) }}</div>
            </div>
            <div class="border border-white/10 bg-[#10161b] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Plan más popular</div>
                <div class="mt-2 font-display text-3xl text-white">{{ $stats['most_popular_plan'] ?? 'N/D' }}</div>
            </div>
            <div class="border border-white/10 bg-[#10161b] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Almacenamiento B2</div>
                <div class="mt-2 font-display text-3xl text-white">{{ number_format((float) ($stats['storage_mb'] ?? 0), 2) }} MB</div>
            </div>
        </div>

        <div class="border border-white/10 bg-[#10161b] p-6">
            <form method="GET" action="{{ route('admin.talents.index') }}" class="grid gap-3 md:grid-cols-[1.2fr_.7fr_.7fr_auto]">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar talento" class="lucille-product-field w-full">
                <select name="plan" class="lucille-product-field w-full">
                    <option value="">Todos los planes</option>
                    @foreach (['free' => 'Free', 'basic' => 'Basic', 'pro' => 'Pro', 'premium' => 'Premium'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['plan'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="state" class="lucille-product-field w-full">
                    <option value="">Todos los estados</option>
                    <option value="active" @selected(($filters['state'] ?? '') === 'active')>Activo</option>
                    <option value="inactive" @selected(($filters['state'] ?? '') === 'inactive')>Inactivo</option>
                </select>
                <button type="submit" class="lucille-button-solid">Filtrar</button>
            </form>
        </div>

        <div class="overflow-x-auto border border-white/10 bg-[#10161b]">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-black/20 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="px-4 py-3">Logo</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Suscripción</th>
                        <th class="px-4 py-3">Interacciones</th>
                        <th class="px-4 py-3">Destacado</th>
                        <th class="px-4 py-3">Creado</th>
                        <th class="px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @foreach ($talents as $talent)
                        <tr class="text-[#d8d8d8]">
                            <td class="px-4 py-4">
                                <div class="h-14 w-14 overflow-hidden border border-white/10 bg-[#151515]">
                                    @if ($talent->logoUrl())
                                        <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover">
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-white">{{ $talent->band_name }}</div>
                                <div class="text-xs text-[#8b8b8b]">{{ $talent->email }}</div>
                            </td>
                            <td class="px-4 py-4">{{ ucfirst($talent->plan) }}</td>
                            <td class="px-4 py-4">{{ ucfirst($talent->subscription_status) }}</td>
                            <td class="px-4 py-4">{{ $talent->interacts }}</td>
                            <td class="px-4 py-4">
                                <form method="POST" action="{{ route('admin.talents.toggle-featured', $talent) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="talent-admin-toggle {{ $talent->is_featured ? 'is-on' : '' }}">
                                        {{ $talent->is_featured ? 'Sí' : 'No' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-4">{{ $talent->created_at?->format('d/m/Y') ?? 'N/D' }}</td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.talents.edit', $talent) }}" class="lucille-button">Editar</a>
                                    <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" target="_blank" rel="noreferrer" class="lucille-button">Ver perfil público</a>
                                    @if ($talent->subscription_status === 'active')
                                        <form method="POST" action="{{ route('admin.talents.suspend', $talent) }}">
                                            @csrf
                                            <button type="submit" class="lucille-button-solid">Suspender</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.talents.activate', $talent) }}">
                                            @csrf
                                            <button type="submit" class="lucille-button-solid">Activar</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.talents.update', $talent) }}">
                                        @csrf
                                        <input type="hidden" name="band_name" value="{{ $talent->band_name }}">
                                        <input type="hidden" name="plan" value="{{ $talent->plan }}">
                                        <input type="hidden" name="subscription_status" value="{{ $talent->subscription_status }}">
                                        <button type="submit" class="lucille-button">Guardar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            {{ $talents->links() }}
        </div>
    </section>
</x-layouts.admin>
