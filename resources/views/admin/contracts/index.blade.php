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

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4">Contrato / Título</th>
                    <th class="px-5 py-4">Firmante / Destinatario</th>
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
                            <div class="flex flex-wrap gap-2">
                                @if ($contract->status === 'pending')
                                    <form action="{{ route('admin.contracts.send', $contract) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="lucille-button">Reenviar Correo</button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.contracts.download', $contract) }}" class="lucille-button-solid bg-[#1e4d2b] hover:bg-[#153a20] border-[#1e4d2b] text-white">Descargar PDF</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-[#7b7b7b]">No se han registrado contratos todavía.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $contracts->links() }}
    </div>
</x-layouts.admin>
