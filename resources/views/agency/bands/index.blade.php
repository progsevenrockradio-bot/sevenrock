<x-layouts.agency :title="'Agencia - Mis Bandas'">
    <section class="space-y-6">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3] rounded-[8px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4 border border-white/10 bg-[#10161b] p-8 rounded-[8px]">
            <div>
                <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Bandas Representadas</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">Listado de perfiles musicales vinculados a tu cuenta de agencia.</p>
            </div>
            <a href="{{ route('agency.bands.create') }}" class="lucille-button-solid">
                ➕ Registrar Banda
            </a>
        </div>

        <div class="border border-white/10 bg-[#10161b] p-6 rounded-[8px]">
            @if($bands->isEmpty())
                <div class="text-center py-12 border border-dashed border-white/5 rounded-[6px]">
                    <p class="text-sm text-[#7b7b7b]">No tienes ninguna banda registrada.</p>
                    <a href="{{ route('agency.bands.create') }}" class="mt-4 inline-block lucille-button text-xs">Añadir Primera Banda</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-[#c7d0d8]">
                        <thead>
                            <tr class="border-b border-white/10 text-xs uppercase tracking-wider text-[#7b7b7b]">
                                <th class="py-3 px-4">Banda</th>
                                <th class="py-3 px-4">Género</th>
                                <th class="py-3 px-4">País</th>
                                <th class="py-3 px-4">Estado</th>
                                <th class="py-3 px-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bands as $band)
                                <tr class="border-b border-white/5 hover:bg-white/[0.01] transition-colors">
                                    <td class="py-3 px-4 font-semibold text-white">
                                        @if($band->logo_path)
                                            <img src="{{ $band->logo_path }}" alt="{{ $band->name }}" class="h-8 w-8 rounded-full inline-block mr-3 object-cover border border-white/10" loading="lazy">
                                        @else
                                            <div class="h-8 w-8 rounded-full bg-white/5 border border-white/10 text-white font-bold font-display text-xs inline-flex items-center justify-center mr-3">
                                                {{ strtoupper(substr($band->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        {{ $band->name }}
                                    </td>
                                    <td class="py-3 px-4 text-xs text-[#8f9aa3]">{{ $band->genre ?: 'N/D' }}</td>
                                    <td class="py-3 px-4 text-xs">{{ $band->country ?: 'N/D' }}</td>
                                    <td class="py-3 px-4 text-xs">
                                        <span class="rounded px-2 py-0.5 text-[10px] font-semibold tracking-wider uppercase border border-white/10 bg-white/5">
                                            {{ $band->status ?: 'Activo' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <a href="{{ route('agency.bands.edit', $band->id) }}" class="text-[var(--lucille-accent)] hover:underline text-xs">Editar Perfil</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</x-layouts.agency>
