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
                        <th class="px-5 py-4">Banda / Artista</th>
                        <th class="px-5 py-4">Canción</th>
                        <th class="px-5 py-4">Contacto</th>
                        <th class="px-5 py-4">Audio</th>
                        <th class="px-5 py-4">Fecha</th>
                        <th class="px-5 py-4">Estado</th>
                        <th class="px-5 py-4">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                    @forelse ($submissions as $submission)
                        <tr class="hover:bg-[rgba(255,255,255,.02)]">
                            <td class="px-5 py-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">{{ $submission->band_name }}</td>
                            <td class="px-5 py-4 text-[#dcdcdc]">{{ $submission->song_title }}</td>
                            <td class="px-5 py-4 text-xs">
                                <a href="mailto:{{ $submission->contact_email }}" class="text-[#b8e6c3] hover:underline break-all">{{ $submission->contact_email }}</a>
                                @if($submission->phone_number)
                                    <br><span class="text-[#7b7b7b]">{{ $submission->phone_number }}</span>
                                @endif
                                @if($submission->social_link)
                                    <br><a href="{{ $submission->social_link }}" target="_blank" class="text-lucille-accent hover:underline">Ver enlace &nearr;</a>
                                @endif
                            </td>
                            <td class="px-5 py-4 min-w-[150px]">
                                @if($submission->file_path)
                                    <audio controls class="h-8 w-full max-w-[200px]" controlsList="nodownload">
                                        <source src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($submission->file_path) }}" type="audio/mpeg">
                                        Tu navegador no soporta el audio.
                                    </audio>
                                @else
                                    <span class="text-xs text-[#555]">Sin archivo</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                {{ $submission->created_at?->format('d M Y H:i') }}
                            </td>
                            <td class="px-5 py-4">
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
                                <span class="inline-block px-2 py-1 text-[10px] uppercase tracking-wider border {{ $color }} rounded-sm">
                                    {{ $label }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @if($submission->status !== 'approved')
                                    <form action="{{ route('admin.submissions.updateStatus', $submission) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="lucille-button text-[10px] px-2 py-1" style="border-color: #1e4d2b; color: #b8e6c3;">Aprobar</button>
                                    </form>
                                    @endif

                                    @if($submission->status !== 'rejected')
                                    <form action="{{ route('admin.submissions.updateStatus', $submission) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="lucille-button text-[10px] px-2 py-1" style="border-color: #4d1e1e; color: #e6b8b8;">Rechazar</button>
                                    </form>
                                    @endif

                                    <form
                                        action="{{ route('admin.submissions.destroy', $submission) }}"
                                        method="POST"
                                        data-confirm="¿Seguro que quieres eliminar esta maqueta? Esto borrará el MP3 permanentemente."
                                        data-confirm-title="Eliminar Maqueta"
                                        data-confirm-action="Eliminar"
                                        data-confirm-tone="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="lucille-button-solid text-[10px] px-2 py-1">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @if($submission->message)
                        <tr class="border-b border-[#2b2b2b]/50 bg-[#161618]">
                            <td colspan="7" class="px-5 py-3 text-xs text-[#999]">
                                <span class="text-[#555] uppercase tracking-wider text-[10px]">Mensaje de la banda:</span><br>
                                {{ $submission->message }}
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-[#555]">No hay maquetas recibidas todavía.</td>
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
