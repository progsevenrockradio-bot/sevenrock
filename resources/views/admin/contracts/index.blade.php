@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Gestión de Contratos - '.$themeSettings->site_name">
    @if (session('success'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 border border-[#c32720]/20 bg-[#c32720]/5 px-4 py-3 text-sm text-[#ff7875]">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Contratos de Artistas</h1>
            <p class="mt-2 text-[#7b7b7b]">Crea, envía y descarga contratos firmados electrónicamente mediante el sistema Clickwrap.</p>
        </div>
        <a href="{{ route('admin.contracts.create') }}" class="lucille-button-solid">Nuevo Contrato</a>
    </div>

    <div class="mb-6 border border-[#2b2b2b] bg-[rgba(16,16,18,.6)] p-4 rounded-[8px]">
        <form action="{{ route('admin.contracts.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="mb-1 block text-[10px] uppercase tracking-wider text-[#7b7b7b] font-semibold">Buscar</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nombre, email, título, país, ciudad..." class="lucille-product-field w-full text-xs py-1.5 px-3">
            </div>
            
            <div class="w-[150px]">
                <label class="mb-1 block text-[10px] uppercase tracking-wider text-[#7b7b7b] font-semibold">Estado</label>
                <select name="status" class="lucille-product-field w-full text-xs py-1.5 px-2">
                    <option value="">Todos</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                    <option value="signed" {{ request('status') === 'signed' ? 'selected' : '' }}>Firmado</option>
                </select>
            </div>

            <div class="w-[180px]">
                <label class="mb-1 block text-[10px] uppercase tracking-wider text-[#7b7b7b] font-semibold">Filtrar por País</label>
                <select name="country" class="lucille-product-field w-full text-xs py-1.5 px-2">
                    <option value="">Todos los países</option>
                    @foreach($countries as $country)
                        <option value="{{ $country }}" {{ request('country') === $country ? 'selected' : '' }}>{{ $country }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="lucille-button-solid text-xs py-1.5 px-4">Filtrar</button>
                @if(request()->anyFilled(['search', 'status', 'country']))
                    <a href="{{ route('admin.contracts.index') }}" class="lucille-button text-xs py-1.5 px-4 flex items-center justify-center">Limpiar</a>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] font-sans">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4">Contrato / Título</th>
                    <th class="px-5 py-4">Firmante / Destinatario</th>
                    <th class="px-5 py-4">Ubicación</th>
                    <th class="px-5 py-4">Estado</th>
                    <th class="px-5 py-4">Fecha de Firma</th>
                    <th class="px-5 py-4">Dirección IP</th>
                    <th class="px-5 py-4">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                @forelse ($contracts as $contract)
                    <tr class="hover:bg-[rgba(255,255,255,.02)]">
                        <td class="px-5 py-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">
                            {{ $contract->title }}
                            <div class="text-[10px] text-[#7b7b7b] lowercase font-mono mt-0.5">Token: {{ $contract->token }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <strong class="text-[#dcdcdc]">{{ $contract->signer_name }}</strong>
                            <div class="text-xs text-[#7b7b7b]">{{ $contract->signer_email }}</div>
                        </td>
                        <td class="px-5 py-4 font-display text-xs uppercase tracking-wider text-[#dcdcdc]">
                            @if($contract->city && $contract->country)
                                {{ $contract->city }}, {{ $contract->country }}
                            @else
                                <span class="text-[#7b7b7b]">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($contract->status === 'signed')
                                <span class="rounded border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-2.5 py-1 text-xs font-semibold text-[#b8e6c3]">Firmado</span>
                            @else
                                <span class="rounded border border-yellow-500/20 bg-yellow-500/5 px-2.5 py-1 text-xs font-semibold text-yellow-400">Pendiente</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            {{ $contract->signed_at ? $contract->signed_at->format('d/m/Y H:i:s') : '-' }}
                        </td>
                        <td class="px-5 py-4 font-mono text-xs">
                            {{ $contract->signing_ip ?? '-' }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2 items-center">
                                <a href="{{ route('admin.contracts.show', $contract) }}" class="lucille-button hover:text-white text-xs py-1 px-3">Ver Detalles</a>
                                @if ($contract->status === 'pending')
                                    <form action="{{ route('admin.contracts.send', $contract) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="lucille-button text-xs py-1 px-3">Reenviar</button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.contracts.download', $contract) }}" class="lucille-button-solid bg-[#1e4d2b] hover:bg-[#153a20] border-[#1e4d2b] text-white text-xs py-1 px-3">PDF</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-[#7b7b7b]">No se han registrado contratos todavía.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $contracts->links() }}
    </div>
</x-layouts.admin>
