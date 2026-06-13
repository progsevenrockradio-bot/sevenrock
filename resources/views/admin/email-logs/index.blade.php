<x-layouts.admin :title="'Historial de Correos - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="mb-8 flex flex-wrap items-start justify-between gap-4 border-b border-[#2b2b2b] pb-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">
                    Historial de Correos
                </h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">
                    Registro de todos los correos electrónicos enviados por la plataforma.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-[#cbcbcb]">
                <thead class="bg-[rgba(255,255,255,0.02)] text-[10px] uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="px-4 py-3 font-medium">Fecha</th>
                        <th class="px-4 py-3 font-medium">Destinatario</th>
                        <th class="px-4 py-3 font-medium">Asunto</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#2b2b2b]">
                    @forelse($logs as $log)
                        <tr class="transition-colors hover:bg-[rgba(255,255,255,0.02)]">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $log->to_email }}</td>
                            <td class="px-4 py-3 font-medium text-[#dcdcdc]">{{ $log->subject }}</td>
                            <td class="px-4 py-3">
                                @if($log->status === 'sent')
                                    <span class="inline-flex items-center rounded-full bg-green-500/10 px-2 py-1 text-xs font-medium text-green-400 border border-green-500/20">Enviado</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-red-500/10 px-2 py-1 text-xs font-medium text-red-400 border border-red-500/20">Fallido</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-[#7b7b7b]">
                                No hay registros de correos enviados todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $logs->links() }}
        </div>
    </section>
</x-layouts.admin>
