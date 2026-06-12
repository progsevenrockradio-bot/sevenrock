<x-layouts.agency :title="'Agencia - Panel de Control'">
    <section class="space-y-6">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3] rounded-[8px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4 border border-white/10 bg-[#10161b] p-8 rounded-[8px]">
            <div>
                <span class="text-xs uppercase tracking-[.18em] text-[var(--lucille-accent)] font-semibold">Bienvenido al Portal de Agencias</span>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc] mt-1">{{ $agency->name }}</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">Aquí puedes gestionar el perfil público de tu agencia y registrar nuevas bandas musicales.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('agency.bands.create') }}" class="lucille-button-solid">
                    ➕ Registrar Banda
                </a>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Tarjeta 1: Perfil de Agencia -->
            <div class="border border-white/10 bg-[#10161b] p-6 rounded-[8px] flex flex-col justify-between">
                <div>
                    <h2 class="font-display text-lg uppercase tracking-[.10em] text-white">Mi Perfil</h2>
                    <p class="mt-2 text-sm text-[#7b7b7b]">Personaliza el nombre, sitio web y logotipo oficial de tu agencia que se mostrará en los banners de colaboradores.</p>
                </div>
                <div class="mt-6 border-t border-white/5 pt-4">
                    <a href="{{ route('agency.profile') }}" class="lucille-button text-xs">Configurar Perfil &rarr;</a>
                </div>
            </div>

            <!-- Tarjeta 2: Bandas Representadas -->
            <div class="border border-white/10 bg-[#10161b] p-6 rounded-[8px] flex flex-col justify-between">
                <div>
                    <h2 class="font-display text-lg uppercase tracking-[.10em] text-white">Bandas Representadas</h2>
                    <p class="mt-2 text-sm text-[#7b7b7b]">Actualmente tienes <strong>{{ $bandsCount }}</strong> bandas asociadas. Administra la biografía, enlaces y la información que ven los oyentes.</p>
                </div>
                <div class="mt-6 border-t border-white/5 pt-4">
                    <a href="{{ route('agency.bands') }}" class="lucille-button text-xs">Ver Mis Bandas &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Listado de Bandas Recientes -->
        <div class="border border-white/10 bg-[#10161b] p-6 rounded-[8px]">
            <h3 class="font-display text-md uppercase tracking-[.10em] text-white border-b border-white/5 pb-3">Bandas Registradas Recientemente</h3>
            <div class="mt-4">
                @if($bands->isEmpty())
                    <p class="text-sm text-[#7b7b7b] py-4 text-center border border-dashed border-white/5 rounded-[6px]">Aún no has registrado ninguna banda musical.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-[#c7d0d8]">
                            <thead>
                                <tr class="border-b border-white/10 text-xs uppercase tracking-wider text-[#7b7b7b]">
                                    <th class="py-3 px-4">Banda</th>
                                    <th class="py-3 px-4">Género</th>
                                    <th class="py-3 px-4">País</th>
                                    <th class="py-3 px-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bands as $band)
                                    <tr class="border-b border-white/5 hover:bg-white/[0.01] transition-colors">
                                        <td class="py-3 px-4 font-semibold text-white">
                                            @if($band->logo_path)
                                                <img src="{{ $band->logo_path }}" alt="{{ $band->name }}" class="h-6 w-6 rounded-full inline-block mr-2 object-cover border border-white/10">
                                            @endif
                                            {{ $band->name }}
                                        </td>
                                        <td class="py-3 px-4 text-xs text-[#8f9aa3]">{{ $band->genre ?: 'N/D' }}</td>
                                        <td class="py-3 px-4 text-xs">{{ $band->country ?: 'N/D' }}</td>
                                        <td class="py-3 px-4 text-right">
                                            <a href="{{ route('agency.bands.edit', $band->id) }}" class="text-[var(--lucille-accent)] hover:underline text-xs">Editar</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.agency>
