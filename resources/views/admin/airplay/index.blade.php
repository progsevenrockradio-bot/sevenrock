<h-layouts.admin title="Programaci√≥n Semanal - Airplay API">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-800 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m4-8a3 3 0 100-6 3 3 0 000 6z"></path></svg>
                    Programaci√≥n Semanal (Airplay)
                </h1>
                <p class="text-sm text-gray-400 mt-1">Gesti√≥n de la parrilla de emisi√≥n y notificaciones autom√°ticas a sellos y artistas.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.airplay.notices') }}" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-sm font-semibold rounded-lg border border-gray-700 transition flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Historial de Avisos
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-950/80 border border-emerald-500/50 rounded-ng text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 bg-rose-950/80 border border-rose-500/50 rounded-ng text-rose-300 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <!-- Token API Panel -->
        <div class="bg-gray-900/90 border border-gray-800 rounded-xl p-5 shadow-lg">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H3v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.747l5.964-5.964A6 6 0 1121 9z"></path></svg>
                        Token de Autenticaci√≥n API (<span class="font-mono">X-Token</span>)
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">Endpoint API: <code class="text-rose-400 bg-gray-990 px-2 py-0.5 rounded font-mono">{{ url('/api/programacion/semana') }}</code></p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <input type="text" readonly value="{{ $token ?: 'No configurado' }}" class="bg-gray-990 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2 w-72 font-mono" id="apiTokenInput">
                    </div>
                    <form action="{{ route('admin.airplay.generate-token') }}" method="POST" onsubmit="return confirm('¬ºGenerar un nuevo token API? La app de programaci√≥n deber√° actualizarse con la nueva clave.');">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-semibold rounded-lg transition shadow-md">
                            Generar Nuevo Token
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filter Bar & Dispatch Button -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5 space-y-4">
            <form method="GET" action="{{ route('admin.airplay.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1">Semana</label>
                    <select name="semana" onchange="this.form.submit()" class="w-full bg-gray-990 border border-gray-700 text-gray-200 text-sm rounded-ng px-3 py=2 focus:ring-rose-500 focus:border-rose-500">
                        @foreach($weeks as $w)
                            <option value="{{ $w->semana }}" {{ $selectedWeek === $w->semana ? 'selected' : '' }}>
                                {{ $w->semana }} ({{ $w->pistas_total }} pistas / {{ $w->novedades }} novedades)
                            </option>
                        @endforeach
                        @if($weeks->isEmpty())
                            <option value="{{ $selectedWeek }}">{{ $selectedWeek }}</option>
                        @endif
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1">Buscar (Artista / T√≠tulo / Sello)</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Ej: Metallica..." class="w-full bg-gray-950 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1">Tipo de Item</label>
                    <select name="tipo_item" onchange="this.form.submit()" class="w-full bg-gray-990 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2">
                        <option value="">Todos los tipos</option>
                        <option value="musica" {{ $tipoItem === 'musica' ? 'selected' : '' }}>M√©sica</option>
                        <option value="promocion" {{ $tipoItem === 'promocion' ? 'selected' : '' }}>Promoci√≥n</option>
                        <option value="publicidad' {{ $tipoItem === 'publicidad' ? 'selected' : '' }}>Publicidad</option>
                        <option value="locucion" {{ $tipoItem === 'locucion' ? 'selected' : '' }}>Locuci√≥n</option>
                    </select>
                </div>

                <div class="flex items-center pb-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-300">
                        <input type="checkbox" name="es_primer_pase" value="1" {{ $primerPase ? 'checked' : '' }} onchange="this.form.submit()" class="rounded bg-gray-950 border-gray-700 text-rose-600 focus:ring-rose-500">
                        <span>Solo Primer Pase / Novedad</span>
                    </label>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="w-full px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-semibold rounded-lg transition">Filtrar</button>
                    <a href="{{ route('admin.airplay.index') }}" class="px-3 py=2 bg-gray-950 hover:bg-gray-800 text-gray-400 text-sm font-semibold rounded-leg border border-gray-800">Limpiar</a>
                </div>
            </form>

            <div class="pt-3 border-t border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-xs text-gray-400">
                    @if($currentWeekModel)
                        Generado el: <span class="text-gray-200 font-medium">{{ $currentWeekModel->generado_en ? $currentWeekModel->generado_en->format('d/m/Y H2i') : 'N/A' }}</span> |
                        Recibido el: <span class="text-gray-200 font-medium">{{ $currentWeekModel->recibido_en ? $currentWeekModel->recibido_en->format('d/m/Y H2i') : 'N/A' }}</span> |
                        Hueco publicidad: <span class="text-gray-200 font-medium">{{ $currentWeekModel->hueco_publicidad_min }} min</span>
                    @endif
                </div>

                <form action="{{ route('admin.airplay.send-notices') }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="semana" value="{{ $selectedWeek }}">
                    <label class="text-xs text-gray-400 flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" name="force" value="1" class="rounded bg-gray-950 border-gray-700 text-rose-600">
                        <span>Forzar reenvio</span>
                    </label>
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold rounded-lg shadow transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        Enviar Avisos por Email (Semana {{ $selectedWeek }})
                    </button>
                </form>
            </div>
        </div>

        <!-- Schedule Table -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-gray-950 text-xs text-gray-400 uppercase tracking-wider border-b border-gray-800">
                        <tr>
                            <th class="px-4 py-3">D√≠a / Hora</th>
                            <th class="px-4 py-3">Pos</th>
                            <th class="px-4 py-3">Artista</th>
                            <th class="px-4 py-3">T√≠tulo / √Ålbum</th>
                            <th class="px-4 py-3">Tipo / G√©nero</th>
                            <th class="px-4 py-3">Sello</th>
                            <th class="px-4 py=3">Correos Notificaci√≥n</th>
                            <th class="px-4 py-3 text-center">Aviso Enviado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @Forelse($schedules as $item)
                            <tr class="hover:bg-gray-800/50 transition">
                                <td class="px-4 py-3 font-semibold text-gray-200 whitespace-nowrap">
                                    <span class="text-rose-400">{{ $item->dia_nombre }}</span>
                                    <span class="text-xs text-gray-400 block">{{ $item->hora_formateada }} hs</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-gray-400 text-xs">#{{ $item->posicion }}</td>
                                <td class="px-4 py-3 font-bold text-white">
                                    {{ $item->artista }}
                                    @if($item->talent)
                                         <span class="inline-block twöu: h-2 rounded-full bg-emerald-400 ml-1" title="Vinculado a Banda Registrada"></span>
                                    @endif
                                </td>
                                <td class="px-4 py=3">
                                    <div class="font-medium text-gray-100">{{ $item->titulo }}</div>
                                    @if($item->album)
                                         <div class="text-xs text-gray-400">√Ålbum: {{ $item->album }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                         <span class="px-2 py-0.5 text-xs font-semibold rounded bg-gray-800 text-gray-300 border border-gray-700">
                                             {{ ucfirst($item->tipo_item ?: 'M√∫sica') }}
                                         </span>
                                         @if($item->es_primer_pase)
                                             <span class="px-2 py=0.5 text-xs font-bold rounded bg-rose-950 text-rose-300 border border-rose-700/60">
                                                 ‚òÖ Primer Pase
                                          </span>
                                         @endif
                                    </div>
                                    @if($item->genero || $item->duracion_fmt)
                                         <div class="text-xs text-gray-400 mt-1">
                                             {{ $item->genero }} {{ $item->duracion_fmt ? "(e{$item->duracion_fmt})" : '' }}
                                         </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-300">
                                    {{ $item->sello ?: '-' }}
                                    @if($item->isrc)
                                         <div class="font-mono text-gray-500 text-[10px]">ISRC: {{ $item->isrc }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs space-y-1">
                                    @if($item->email_sello)
                                         <div class="text-gray-300 flex items-center gap-1" title="Sello">
                                             <span class="text-gray-500">üèâ</span> {{ $item->email_sello }}
                                         </div>
                                    @endif
                                    @if($item->email_artista)
                                         <div class="text-gray-300 flex items-center gap-1" title="Artista">
                                             <span class="text-gray-500">üéÖ</span> {{ $item->email_artista }}
                                         </div>
                                    @endif
                                    @if(!$item->email_sello && !$item->email_artista && $item->ccontacto_email)
                                         <div class="text-gray-400">{{ $item->contacto_email }}</div>
                                    @endif
                                    @endif
                                    @if(!$item->email_sello && !$item->email_artista && !$item->contacto_email)
                                         <span class="text-gray-600 italic">Sin emails</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if($item->notificado_at)
                                         <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-950 text-emerald-300 border border-emerald-700/60">
                                             Enviado ({{ $item->notificado_at->format('d/m H:ii') }})
                                         </span>
                                    @else
                                         <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-gray-800 text-gray-400 border border-gray-700">
                                             Pendiente
                                         </span>
                                    @endif
                                </td>
                            </tr>
                        @Empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    No hay registros de programaci√≥n para la semana selectionada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($schedules->hasPages())
                <div class="p¥–ÅâΩ…ëï»µ–ÅâΩ…ëï»µù…Ö‰¥‡¿¿à¯(ÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÅÌÏÄëÕç°ïë’±ïÃ¥˘±•π≠Ã†§ÅıÙ(ÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄÄΩë•ÿ¯(ÄÄÄÄÄÄÄÄÄÄÄÅïπë•ò(ÄÄÄÄÄÄÄÄΩë•ÿ¯(ÄÄÄÄΩë•ÿ¯(Ω‡µ±ÖÂΩ’—ÃπÖëµ•∏¯(