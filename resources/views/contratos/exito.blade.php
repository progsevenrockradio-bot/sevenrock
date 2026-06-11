<x-layouts.site :title="'Firma Exitosa - ' . $contract->title">
    <section class="mx-auto max-w-2xl px-5 py-16">
        <div class="border border-white/10 bg-gradient-to-b from-[#10151a]/95 to-[#070a0d]/98 backdrop-blur-md rounded-[20px] p-8 md:p-12 text-center shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
            {{-- Success Badge/Icon --}}
            <div class="h-16 w-16 mx-auto rounded-full bg-[rgba(16,64,30,.2)] border border-[#1e4d2b] flex items-center justify-center text-3xl shadow-[0_0_20px_rgba(30,77,43,0.3)] animate-pulse">
                ✓
            </div>

            <span class="text-[10px] uppercase tracking-[.25em] text-[#b8e6c3] font-semibold font-display mt-6 block">Operación Completada</span>
            <h1 class="font-display text-2xl md:text-3xl uppercase tracking-[.12em] text-white mt-1">¡Contrato Firmado con Éxito!</h1>
            
            <p class="mt-4 text-sm text-gray-400 leading-relaxed">
                El contrato <strong>«{{ $contract->title }}»</strong> ha sido formalizado electrónicamente. Hemos enviado una copia en formato PDF con la firma y el reporte de auditoría a tu correo electrónico: <strong>{{ $contract->signer_email }}</strong>.
            </p>

            {{-- Audit Details Card --}}
            <div class="mt-8 border border-white/5 bg-white/[0.01] rounded-[12px] p-5 text-left space-y-3 font-sans text-xs">
                <div class="border-b border-white/5 pb-2 text-[10px] uppercase tracking-wider text-gray-500 font-semibold font-display">Resumen del Handshake de Auditoría</div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Firmante:</span>
                    <span class="text-gray-300 font-semibold">{{ $contract->signer_name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Correo Electrónico:</span>
                    <span class="text-gray-300 font-mono">{{ $contract->signer_email }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Ubicación Declarada:</span>
                    <span class="text-gray-300 font-semibold">{{ $contract->city }}, {{ $contract->country }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Fecha y Hora de Firma (UTC):</span>
                    <span class="text-gray-300 font-mono">{{ $contract->signed_at ? $contract->signed_at->format('d/m/Y H:i:s') : '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Dirección IP de Origen:</span>
                    <span class="text-gray-300 font-mono">{{ $contract->signing_ip }}</span>
                </div>
            </div>

            {{-- Download Button --}}
            <div class="mt-10 flex flex-col sm:flex-row justify-center gap-4">
                <a href="{{ route('contratos.download', ['token' => $contract->token]) }}" class="lucille-button-solid text-xs uppercase py-3 px-8 tracking-[.12em] rounded-full bg-[#1e4d2b] hover:bg-[#153a20] border-[#1e4d2b] text-white shadow-[0_4px_15px_rgba(30,77,43,0.3)]">
                    Descargar Copia PDF
                </a>
                <a href="{{ route('home') }}" class="lucille-button text-xs uppercase py-3 px-8 tracking-[.12em] rounded-full">
                    Volver a Inicio
                </a>
            </div>
        </div>
    </section>
</x-layouts.site>
