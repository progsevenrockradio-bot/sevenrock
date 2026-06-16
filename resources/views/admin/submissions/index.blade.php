@php $admin = $themeAppearance['admin_texts'] ?? []; @endphp
<x-layouts.admin :title="'Maquetas Recibidas - '.$themeSettings->site_name">
    @if (session('success'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('success') }}
        </div>
    @endif
    
    @if (session('error'))
        <div class="mb-6 border border-[#4d1e1e] bg-[rgba(64,16,16,.2)] px-4 py-3 text-sm text-[#e6b8b8]">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Maquetas Recibidas</h1>
            <p class="mt-2 text-[#7b7b7b]">Buzón de recepción de maquetas enviadas por las bandas.</p>
        </div>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-[#2b2b2b] text-[#dcdcdc] whitespace-nowrap">
                    <tr>
                        <th class="px-5 py-4">Información</th>
                        <th class="px-5 py-4 w-1/3 min-w-[280px]">Audio</th>
                        <th class="px-5 py-4">Contacto</th>
                        <th class="px-5 py-4">Estado</th>
                        <th class="px-5 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                    @forelse ($submissions as $submission)
                        <tr class="hover:bg-[rgba(255,255,255,.02)] transition-colors">
                            <td class="px-5 py-5 align-top">
                                <div class="font-display text-[16px] uppercase tracking-[.08em] text-[#dcdcdc] mb-1">
                                    {{ $submission->band_name }}
                                </div>
                                <div class="text-[#a7a093]">
                                    🎵 {{ $submission->song_title }}
                                </div>
                            </td>
                            <td class="px-5 py-5 align-top">
                                @if($submission->file_path)
                                    <div class="bg-[#1a1a1c] p-2 rounded-md border border-[#2b2b2b] min-w-[260px]">
                                        <audio controls class="h-10 w-full" controlsList="nodownload">
                                            <source src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($submission->file_path) }}" type="audio/mpeg">
                                            Tu navegador no soporta el audio.
                                        </audio>
                                    </div>
                                @else
                                    <span class="inline-block rounded border border-[#4d1e1e] bg-[rgba(64,16,16,.2)] px-3 py-1 text-xs text-[#e6b8b8]">Sin archivo adjunto</span>
                                @endif
                            </td>
                            <td class="px-5 py-5 align-top text-xs leading-relaxed">
                                <div class="mb-2">
                                    <a href="mailto:{{ $submission->contact_email }}" class="text-[#b8e6c3] hover:underline break-all block">{{ $submission->contact_email }}</a>
                                    @if($submission->phone_number)
                                        <span class="text-[#7b7b7b] block">{{ $submission->phone_number }}</span>
                                    @endif
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    @if($submission->social_link)
                                        <a href="{{ $submission->social_link }}" target="_blank" class="text-lucille-accent hover:underline flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm-2 16h-2v-6h2v6zm-1-6.891c-.607 0-1.1-.496-1.1-1.109 0-.612.492-1.109 1.1-1.109s1.1.497 1.1 1.109c0 .613-.493 1.109-1.1 1.109zm8 6.891h-1.998v-2.861c0-1.881-2.002-1.722-2.002 0v2.861h-2v-6h2v1.093c.872-1.616 4-1.736 4 1.548v3.359z"/></svg>
                                            Enlace
                                        </a>
                                    @endif
                                    <span class="text-[#555] whitespace-nowrap">📅 {{ $submission->created_at?->format('d M Y') }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-5 align-top">
                                @php
                                    $statusColors = [
                                        'pending' => 'text-[#e6d8b8] bg-[#4d451e]/30 border-[#4d451e]',
                                        'approved' => 'text-[#b8e6c3] bg-[#1e4d2b]/30 border-[#1e4d2b]',
                                        'rejected' => 'text-[#e6b8b8] bg-[#4d1e1e]/30 border-[#4d1e1e]',
                                    ];
                                    $statusLabels = [
                                        'pending' => 'Pendiente',
                                        'approved' => 'Aprobado',
                                        'rejected' => 'Rechazado',
                                    ];
                                    $color = $statusColors[$submission->status] ?? $statusColors['pending'];
                                    $label = $statusLabels[$submission->status] ?? 'Pendiente';
                                @endphp
                                <span class="inline-block px-2 py-1 text-[11px] font-medium uppercase tracking-wider border {{ $color }} rounded-sm shadow-sm">
                                    {{ $label }}
                                </span>
                            </td>
                            <td class="px-5 py-5 align-top">
                                <div class="flex flex-col gap-2 items-end">
                                    <div class="flex gap-2">
                                        @if($submission->status !== 'approved')
                                        <form action="{{ route('admin.submissions.updateStatus', $submission) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wider rounded border transition-colors bg-[#1e4d2b]/20 hover:bg-[#1e4d2b]/60" style="border-color: #1e4d2b; color: #b8e6c3;">Aprobar</button>
                                        </form>
                                        @endif

                                        @if($submission->status !== 'rejected')
                                        <form action="{{ route('admin.submissions.updateStatus', $submission) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wider rounded border transition-colors bg-[#4d1e1e]/20 hover:bg-[#4d1e1e]/60" style="border-color: #4d1e1e; color: #e6b8b8;">Rechazar</button>
                                        </form>
                                        @endif
                                    </div>

                                    <form
                                        action="{{ route('admin.submissions.destroy', $submission) }}"
                                        method="POST"
                                        class="mt-1"
                                        data-confirm="¿Seguro que quieres eliminar esta maqueta? Esto borrará el MP3 permanentemente."
                                        data-confirm-title="Eliminar Maqueta"
                                        data-confirm-action="Eliminar"
                                        data-confirm-tone="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1 text-xs font-medium uppercase tracking-wider text-[#999] hover:text-[#ff4444] transition-colors underline decoration-dotted underline-offset-4">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @if($submission->message)
                        <tr class="border-b border-[#2b2b2b]/50 bg-[rgba(20,20,22,0.6)]">
                            <td colspan="5" class="px-5 py-4 text-sm text-[#999]">
                                <div class="flex gap-3">
                                    <svg class="w-5 h-5 text-[#555] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                    <div>
                                        <span class="text-[#555] font-semibold uppercase tracking-wider text-[11px] block mb-1">Mensaje de la banda:</span>
                                        <p class="italic text-[#888] leading-relaxed">{{ $submission->message }}</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center">
                                <svg class="w-12 h-12 text-[#333] mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                <span class="text-[#555] font-medium">No hay maquetas recibidas todavía.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($submissions->hasPages())
            <div class="border-t border-[#2b2b2b] px-5 py-4">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
