@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Detalle de Contrato - '.$themeSettings->site_name">
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

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $contract->title }}</h1>
            <p class="mt-2 text-[#7b7b7b]">Consulta web detallada del contrato digital.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.contracts.index') }}" class="lucille-button">Volver al Listado</a>
            @if ($contract->status === 'pending')
                <form action="{{ route('admin.contracts.send', $contract) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="lucille-button-solid">Reenviar Correo de Firma</button>
                </form>
            @else
                <a href="{{ route('admin.contracts.download', $contract) }}" class="lucille-button-solid bg-[#1e4d2b] hover:bg-[#153a20] border-[#1e4d2b] text-white">Descargar PDF Firmado</a>
            @endif
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <!-- Tarjeta de Información General -->
        <div class="md:col-span-1 space-y-6">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 rounded-[8px]">
                <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-4 font-semibold">Datos del Firmante</h3>
                <div class="space-y-3.5 text-xs text-[#7b7b7b]">
                    <div>
                        <span class="block text-[10px] uppercase text-gray-500 font-mono mb-0.5">Nombre Completo</span>
                        <strong class="text-[#dcdcdc] text-sm font-sans font-medium">{{ $contract->signer_name }}</strong>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase text-gray-500 font-mono mb-0.5">Correo Electrónico</span>
                        <a href="mailto:{{ $contract->signer_email }}" class="text-[var(--lucille-accent)] hover:underline">{{ $contract->signer_email }}</a>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase text-gray-500 font-mono mb-0.5">Ubicación Declarada</span>
                        @if($contract->city && $contract->country)
                            <strong class="text-[#dcdcdc]">{{ $contract->city }}, {{ $contract->country }}</strong>
                        @else
                            <span class="italic">No declarada (pendiente de firma)</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 rounded-[8px]">
                <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-4 font-semibold">Registro de Auditoría (Clickwrap)</h3>
                <div class="space-y-3.5 text-xs text-[#7b7b7b]">
                    <div>
                        <span class="block text-[10px] uppercase text-gray-500 font-mono mb-0.5">Estado del Documento</span>
                        @if ($contract->status === 'signed')
                            <span class="inline-block rounded border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-2.5 py-0.5 text-[10px] font-semibold text-[#b8e6c3] uppercase tracking-wide">Firmado</span>
                        @else
                            <span class="inline-block rounded border border-yellow-500/20 bg-yellow-500/5 px-2.5 py-0.5 text-[10px] font-semibold text-yellow-400 uppercase tracking-wide">Pendiente de Firma</span>
                        @endif
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase text-gray-500 font-mono mb-0.5">Token Único de Acceso</span>
                        <code class="font-mono text-[10px] text-gray-400 select-all block bg-white/5 p-1.5 border border-white/10 rounded">{{ $contract->token }}</code>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase text-gray-500 font-mono mb-0.5">Enlace de Firma Pública</span>
                        <a href="{{ $contract->getSigningUrl() }}" target="_blank" class="text-[var(--lucille-accent)] hover:underline block truncate">{{ $contract->getSigningUrl() }}</a>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase text-gray-500 font-mono mb-0.5">Fecha de Creación</span>
                        <span class="font-sans text-[#dcdcdc]">{{ $contract->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>
                    @if($contract->status === 'signed')
                        <div>
                            <span class="block text-[10px] uppercase text-gray-500 font-mono mb-0.5">Fecha y Hora de Firma (UTC)</span>
                            <span class="font-sans text-[#dcdcdc] font-medium">{{ $contract->signed_at->format('d/m/Y H:i:s') }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] uppercase text-gray-500 font-mono mb-0.5">Dirección IP de Registro</span>
                            <span class="font-mono text-[#dcdcdc]">{{ $contract->signing_ip }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Tarjeta del Contenido del Contrato -->
        <div class="md:col-span-2">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 md:p-8 rounded-[8px] h-full flex flex-col">
                <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-4 font-semibold">Cuerpo Contractual</h3>
                <div class="flex-1 overflow-y-auto max-h-[600px] border border-[#2b2b2b] bg-black/45 p-6 rounded-[8px] text-sm leading-relaxed text-gray-300 font-sans prose prose-invert max-w-none">
                    {!! $contract->formatted_content !!}
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
