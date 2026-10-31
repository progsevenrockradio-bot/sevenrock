@php
    $typeSpecs = $typeSpecs ?? ($talent ? \App\Http\Controllers\Talent\MediaController::getMediaTypeSpecs($talent)['types'] : []);
    $storageInfo = $storageInfo ?? ($talent ? \App\Http\Controllers\Talent\MediaController::getMediaTypeSpecs($talent)['storage'] : ['limit_mb' => 0, 'used_mb' => 0, 'remaining_mb' => 0]);
@endphp

<x-layouts.talent :title="'Portal de bandas - Mis archivos'" :talent="$talent">
    <section class="space-y-4 sm:space-y-6" x-data="talentMediaUploader({{ Js::from($typeSpecs) }}, {{ Js::from($storageInfo) }}, '{{ addslashes($talent->plan) }}')">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 rounded-[6px] text-xs sm:text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div x-show="serverSuccess" x-cloak class="border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 rounded-[6px] text-xs sm:text-sm text-[#b8e6c3]">
            <span x-text="serverSuccess"></span>
        </div>

        @if ($errors->any())
            <div class="border border-[#7f1d1d] bg-[rgba(127,29,29,.2)] px-4 py-3 rounded-[6px] text-xs sm:text-sm text-[#fca5a5]">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div x-show="generalError" x-cloak class="border border-[#7f1d1d] bg-[rgba(127,29,29,.2)] px-4 py-3 rounded-[6px] text-xs sm:text-sm text-[#fca5a5]">
            <span x-text="generalError"></span>
        </div>

        {{-- Barra de navegación en pestañas para móviles (< lg) --}}
        <div class="lg:hidden flex border border-white/10 bg-[#10161b] rounded-[8px] p-1 gap-1">
            <button type="button" 
                    @click="activeMobileTab = 'upload'" 
                    :class="activeMobileTab === 'upload' ? 'bg-[var(--lucille-accent)] text-white font-semibold shadow-md' : 'text-[#8b8b8b] hover:text-white hover:bg-white/5'" 
                    class="flex-1 min-h-[44px] py-2.5 px-3 text-xs uppercase tracking-wider rounded-[6px] text-center transition-colors flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-[var(--lucille-accent)]">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <span>Subir archivo</span>
                <template x-if="isUploading">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                </template>
            </button>
            <button type="button" 
                    @click="activeMobileTab = 'library'" 
                    :class="activeMobileTab === 'library' ? 'bg-[var(--lucille-accent)] text-white font-semibold shadow-md' : 'text-[#8b8b8b] hover:text-white hover:bg-white/5'" 
                    class="flex-1 min-h-[44px] py-2.5 px-3 text-xs uppercase tracking-wider rounded-[6px] text-center transition-colors flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-[var(--lucille-accent)]">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <span>Mi biblioteca</span>
                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-black/40 text-zinc-300">{{ $media->count() }}</span>
            </button>
        </div>

        {{-- Rejilla de contenido: Ambas vistas existen en el DOM simultáneamente para preservar inputs y subidas en curso --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[.95fr_1.05fr]">
            
            {{-- PANEL 1: Formulario de Subida --}}
            <div :class="activeMobileTab === 'upload' ? 'block' : 'hidden lg:block'" class="border border-white/10 bg-[#10161b] p-4 sm:p-6 lg:p-7 rounded-[8px]">
                <div class="flex items-center justify-between gap-2 border-b border-white/5 pb-3">
                    <div>
                        <h1 class="font-display text-xl sm:text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Mis archivos</h1>
                        <p class="mt-1 text-xs text-[#7b7b7b]">Sube fotos, MP3, documentos o videos al almacenamiento seguro.</p>
                    </div>
                    <template x-if="isUploading">
                        <span class="px-2 py-1 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[10px] font-mono uppercase tracking-wider animate-pulse shrink-0">
                            Subida en curso
                        </span>
                    </template>
                </div>
                
                {{-- Resumen de Límites con capacidades por tipo obtenidas de las reglas del servidor --}}
                <div class="mt-4 border border-white/5 bg-white/[0.02] p-4 rounded-[6px] text-xs space-y-2.5 text-[#7b7b7b] font-sans">
                    <div class="font-display uppercase tracking-wider text-[#dcdcdc] font-semibold text-xs border-b border-white/5 pb-2 flex items-center justify-between">
                        <span>Resumen de Límites (Plan: {{ strtoupper($talent->plan) }})</span>
                        <span class="text-[10px] text-zinc-400 font-mono font-normal">Máx. por archivo</span>
                    </div>
                    <div class="flex justify-between items-center text-[11px] sm:text-xs">
                        <span>Canciones (MP3):</span>
                        <span class="text-[#dcdcdc] font-mono">{{ $usage['songs'] }} / {{ $limits['songs'] ?? 0 }} <span class="text-[10px] text-gray-500 font-sans ml-1">(Máx. {{ $typeSpecs['mp3']['max_mb'] ?? 12 }}MB c/u)</span></span>
                    </div>
                    <div class="flex justify-between items-center text-[11px] sm:text-xs">
                        <span>Fotografías:</span>
                        <span class="text-[#dcdcdc] font-mono">{{ $usage['photos'] }} / {{ $limits['photos'] ?? 0 }} <span class="text-[10px] text-gray-500 font-sans ml-1">(Máx. {{ $typeSpecs['photo']['max_mb'] ?? 10 }}MB c/u)</span></span>
                    </div>
                    <div class="flex justify-between items-center text-[11px] sm:text-xs">
                        <span>Documentos:</span>
                        <span class="text-[#dcdcdc] font-mono">{{ $usage['documents'] }} / {{ $limits['documents'] ?? 0 }} <span class="text-[10px] text-gray-500 font-sans ml-1">(Máx. {{ $typeSpecs['document']['max_mb'] ?? 10 }}MB c/u)</span></span>
                    </div>
                    <div class="flex justify-between items-center text-[11px] sm:text-xs">
                        <span>Videos:</span>
                        <span class="text-[#dcdcdc] font-mono">{{ $usage['videos'] }} / {{ $limits['videos'] ?? 0 }} <span class="text-[10px] text-gray-500 font-sans ml-1">(Máx. {{ $typeSpecs['video']['max_mb'] ?? 100 }}MB c/u)</span></span>
                    </div>
                    <div class="flex justify-between items-center border-t border-white/5 pt-2 font-semibold text-[11px] sm:text-xs">
                        <span>Almacenamiento Total Usado:</span>
                        <span class="text-[#dcdcdc] font-mono">{{ number_format((float) ($usage['storage_used_mb'] ?? 0), 2) }} MB / {{ (int) ($limits['storage_mb'] ?? 0) }} MB</span>
                    </div>
                </div>

                <form x-ref="uploadForm" action="{{ route('talents.media.upload') }}" method="POST" enctype="multipart/form-data" @submit.prevent="handleSubmit" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Tipo de contenido</label>
                        <div class="relative w-full">
                            <input type="hidden" name="type" :value="selectedType">
                            
                            <button type="button" @click="dropdownOpen = !dropdownOpen" @click.away="dropdownOpen = false" 
                                class="lucille-product-field w-full flex items-center justify-between text-left rounded-[6px] min-h-[44px]" 
                                style="color: #dcdcdc; background-color: rgba(0, 0, 0, .25); cursor: pointer; border: 1px solid rgba(255,255,255,0.08); padding: 0 14px; font-size: 13px;">
                                <span class="flex items-center gap-2 truncate">
                                    <span class="font-medium" x-text="selectedLabel"></span>
                                    <template x-if="currentSpec && !currentSpec.included">
                                        <span class="text-[9px] font-mono uppercase bg-amber-950/80 text-amber-400 border border-amber-800/60 px-1.5 py-0.5 rounded">No incluido</span>
                                    </template>
                                    <template x-if="currentSpec && currentSpec.included && currentSpec.quota_full">
                                        <span class="text-[9px] font-mono uppercase bg-zinc-800 text-zinc-300 border border-zinc-700 px-1.5 py-0.5 rounded" x-text="currentSpec.used + '/' + currentSpec.limit"></span>
                                    </template>
                                </span>
                                <svg class="w-4 h-4 ml-2 transition-transform duration-200 shrink-0" :class="dropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <ul x-show="dropdownOpen" x-cloak x-transition.opacity 
                                class="absolute left-0 z-30 mt-1 w-full border border-white/10 bg-[#141416] rounded-[6px] py-1 shadow-2xl space-y-0.5" 
                                style="max-height: 250px; overflow-y: auto; list-style: none; padding: 4px; margin: 0;">
                                <template x-for="(spec, key) in typeSpecs" :key="key">
                                    <li>
                                        <button type="button" 
                                            @click="selectType(key)" 
                                            class="w-full text-left px-3 py-2.5 rounded-[4px] text-xs flex items-center justify-between transition-colors duration-150 min-h-[44px]"
                                            :class="selectedType === key ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                            <span class="font-medium" x-text="spec.name"></span>
                                            <span class="flex items-center gap-1.5 shrink-0">
                                                <template x-if="!spec.included">
                                                    <span class="text-[9px] font-mono uppercase tracking-wider text-amber-400/90 bg-amber-950/60 border border-amber-800/60 px-1.5 py-0.5 rounded">
                                                        No incluido
                                                    </span>
                                                </template>
                                                <template x-if="spec.included && spec.quota_full">
                                                    <span class="text-[9px] font-mono text-zinc-300 bg-zinc-800/80 px-1.5 py-0.5 rounded border border-zinc-700" x-text="'Completo (' + spec.used + '/' + spec.limit + ')'">
                                                    </span>
                                                </template>
                                                <template x-if="spec.included && !spec.quota_full">
                                                    <span class="text-[9px] font-mono text-emerald-400/90 bg-emerald-950/50 border border-emerald-800/40 px-1.5 py-0.5 rounded" x-text="spec.remaining + ' disponibles'">
                                                    </span>
                                                </template>
                                            </span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título del archivo</label>
                        <input name="title" x-model="title" class="lucille-product-field w-full min-h-[44px] rounded-[6px] px-3.5 text-xs sm:text-sm" placeholder="Título descriptivo (opcional)">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Descripción</label>
                        <textarea name="description" x-model="description" rows="3" class="lucille-product-field w-full rounded-[6px] p-3 text-xs sm:text-sm" placeholder="Notas adicionales o créditos (opcional)"></textarea>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                                Archivo <span class="text-[var(--lucille-accent)]">*</span>
                            </label>
                            <template x-if="currentSpec">
                                <span class="text-[11px] font-mono text-[#8b8b8b]" x-text="'Tipo: ' + currentSpec.name"></span>
                            </template>
                        </div>

                        {{-- Límites visibles antes de seleccionar obtenidos de las reglas del backend --}}
                        <template x-if="currentSpec">
                            <div class="mb-2.5 rounded-[6px] border border-white/10 bg-[#12181d] p-3 text-xs space-y-1.5 font-sans">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 pb-1 text-[11px] sm:text-xs">
                                    <span class="text-[#8b8b8b]">Formatos permitidos:</span>
                                    <span class="font-mono text-[#dcdcdc] font-semibold" x-text="currentSpec.label"></span>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 pb-1 text-[11px] sm:text-xs">
                                    <span class="text-[#8b8b8b]">Tamaño máximo por archivo:</span>
                                    <span class="font-mono text-[#dcdcdc] font-semibold" x-text="'Hasta ' + currentSpec.max_mb + ' MB'"></span>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 pb-1 text-[11px] sm:text-xs">
                                    <span class="text-[#8b8b8b]">Cupos disponibles:</span>
                                    <template x-if="!currentSpec.included">
                                        <span class="font-mono text-amber-400 font-semibold">No incluido en tu plan</span>
                                    </template>
                                    <template x-if="currentSpec.included && currentSpec.quota_full">
                                        <span class="font-mono text-amber-400 font-semibold" x-text="'Has utilizado ' + currentSpec.used + ' de ' + currentSpec.limit + ' ' + currentSpec.plural"></span>
                                    </template>
                                    <template x-if="currentSpec.included && !currentSpec.quota_full">
                                        <span class="font-mono text-emerald-400 font-semibold" x-text="currentSpec.remaining + ' disponibles (de ' + currentSpec.limit + ' ' + currentSpec.plural + ')'"></span>
                                    </template>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] sm:text-xs">
                                    <span class="text-[#8b8b8b]">Espacio restante del plan:</span>
                                    <span class="font-mono text-[#dcdcdc] font-semibold" x-text="storageInfo.remaining_mb + ' MB restantes (de ' + storageInfo.limit_mb + ' MB)'"></span>
                                </div>
                            </div>
                        </template>

                        {{-- Avisos contextuales si el tipo no está incluido o la cuota está llena --}}
                        <template x-if="currentSpec && !currentSpec.included">
                            <div class="mb-2.5 p-3 bg-amber-950/40 border border-amber-800/60 rounded-[6px] text-xs text-amber-200 flex items-start gap-2.5" role="alert" aria-live="polite">
                                <svg class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <div>
                                    <p class="font-semibold text-amber-300">Tipo no incluido en tu plan</p>
                                    <p class="mt-0.5 text-amber-200/80">Tu plan actual no incluye la subida de <span x-text="currentSpec.plural"></span>. Elige otro tipo de contenido disponible para subir.</p>
                                </div>
                            </div>
                        </template>

                        <template x-if="currentSpec && currentSpec.included && currentSpec.quota_full">
                            <div class="mb-2.5 p-3 bg-amber-950/40 border border-amber-800/60 rounded-[6px] text-xs text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2" role="alert" aria-live="polite">
                                <div class="flex items-start gap-2.5">
                                    <svg class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div>
                                        <p class="font-semibold text-amber-300" x-text="'Has utilizado ' + currentSpec.used + ' de ' + currentSpec.limit + ' ' + currentSpec.plural"></p>
                                        <p class="mt-0.5 text-amber-200/80">Para subir otra <span x-text="currentSpec.singular"></span>, primero debes gestionar o eliminar la existente en tu biblioteca.</p>
                                    </div>
                                </div>
                                <button type="button" @click="goToLibrary()" class="self-start sm:self-auto shrink-0 min-h-[44px] px-3 py-2 text-xs font-semibold rounded-[6px] bg-amber-500/20 text-amber-300 hover:bg-amber-500/30 border border-amber-500/40 transition-colors flex items-center justify-center">
                                    Ver en biblioteca
                                </button>
                            </div>
                        </template>

                        {{-- Selector de Archivo con accept dinámico --}}
                        <input type="file" 
                               name="file" 
                               x-ref="fileInput" 
                               :accept="currentSpec ? currentSpec.accept : ''" 
                               @change="onFileChanged" 
                               :disabled="!currentSpec || !currentSpec.included || currentSpec.quota_full || isUploading" 
                               class="lucille-product-field w-full min-h-[44px] rounded-[6px] p-2 text-xs"
                               :class="(!currentSpec || !currentSpec.included || currentSpec.quota_full) ? 'opacity-50 cursor-not-allowed' : ''">
                        
                        {{-- Mensaje reactivo de validación anticipada accesible --}}
                        <div x-show="fileError" 
                             x-cloak 
                             role="alert" 
                             aria-live="polite" 
                             class="mt-2 flex items-start gap-2 rounded-[6px] border border-red-500/40 bg-red-950/40 p-2.5 text-xs text-red-200 font-sans">
                            <svg class="w-4 h-4 shrink-0 text-red-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="leading-relaxed">
                                <span class="font-bold text-red-300 uppercase tracking-wide text-[10px] mr-1">[Aviso de validación]</span>
                                <span x-text="fileError"></span>
                            </div>
                        </div>

                        {{-- Error de validación del servidor --}}
                        @error('file')
                            <div class="mt-2 flex items-start gap-2 rounded-[6px] border border-red-500/40 bg-red-950/40 p-2.5 text-xs text-red-200 font-sans" role="alert">
                                <svg class="w-4 h-4 shrink-0 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <div>
                                    <span class="font-bold text-red-300 uppercase tracking-wide text-[10px] mr-1">[Error del servidor]</span>
                                    <span>{{ $message }}</span>
                                </div>
                            </div>
                        @enderror
                    </div>

                    <div class="flex items-center gap-2 py-1 select-none">
                        <input type="hidden" name="is_exclusive" value="0">
                        <input type="checkbox" name="is_exclusive" id="is_exclusive" value="1" x-model="isExclusive" class="h-4 w-4 rounded border-[#2b2b2b] bg-[#151515] text-[#c32720] focus:ring-[#c32720] cursor-pointer">
                        <label for="is_exclusive" class="text-xs uppercase tracking-[.18em] text-[#dcdcdc] cursor-pointer">¿Exclusivo para Afiliados?</label>
                    </div>

                    {{-- Indicador y Barra de Progreso Visual Real --}}
                    <div x-show="isUploading" x-cloak class="space-y-2 p-3.5 rounded-[6px] bg-black/40 border border-white/10">
                        <div class="flex justify-between items-center text-xs font-mono">
                            <span class="text-[#dcdcdc] flex items-center gap-2">
                                <svg class="animate-spin h-3.5 w-3.5 text-[var(--lucille-accent)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <template x-if="isProcessing || uploadProgress >= 100">
                                    <span class="text-amber-400 font-semibold animate-pulse">Guardando y verificando…</span>
                                </template>
                                <template x-if="!isProcessing && uploadProgress < 100">
                                    <span>Subiendo archivo…</span>
                                </template>
                            </span>
                            <template x-if="hasComputableProgress">
                                <span class="text-[var(--lucille-accent)] font-semibold" x-text="uploadProgress + '%'"></span>
                            </template>
                            <template x-if="!hasComputableProgress">
                                <span class="text-[#7b7b7b]">En progreso…</span>
                            </template>
                        </div>
                        <div class="w-full bg-white/5 h-2 rounded-full overflow-hidden border border-white/10">
                            <template x-if="hasComputableProgress">
                                <div class="bg-[var(--lucille-accent)] h-full transition-all duration-150 ease-out" :style="'width: ' + uploadProgress + '%'"></div>
                            </template>
                            <template x-if="!hasComputableProgress">
                                <div class="bg-[var(--lucille-accent)] h-full w-1/3 animate-pulse"></div>
                            </template>
                        </div>
                    </div>

                    <button type="submit" 
                        :disabled="!isSubmittable" 
                        :class="!isSubmittable ? 'opacity-50 cursor-not-allowed' : ''" 
                        class="lucille-button-solid w-full min-h-[44px] flex items-center justify-center gap-2 rounded-[6px] text-xs font-semibold uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-[var(--lucille-accent)]">
                        <template x-if="isUploading">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="isProcessing || uploadProgress >= 100 ? 'Guardando y verificando…' : 'Subiendo…'"></span>
                            </span>
                        </template>
                        <template x-if="!isUploading">
                            <span>Subir archivo</span>
                        </template>
                    </button>
                </form>
            </div>

            {{-- PANEL 2: Biblioteca de Archivos --}}
            <div id="biblioteca" :class="activeMobileTab === 'library' ? 'block' : 'hidden lg:block'" class="border border-white/10 bg-[#10161b] p-4 sm:p-6 lg:p-7 rounded-[8px]">
                <div class="flex items-center justify-between gap-3 border-b border-white/5 pb-3">
                    <div>
                        <h2 class="font-display text-xl sm:text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Mi biblioteca</h2>
                        <p class="mt-0.5 text-xs text-[#7b7b7b]">Archivos disponibles en tu perfil público y catálogo.</p>
                    </div>
                    <span class="text-xs font-mono px-2.5 py-1 rounded bg-black/40 border border-white/10 text-[#dcdcdc] shrink-0">
                        {{ $media->count() }} {{ $media->count() === 1 ? 'archivo' : 'archivos' }}
                    </span>
                </div>

                {{-- Lista de tarjetas de biblioteca --}}
                <div class="mt-5 space-y-3.5">
                    @forelse ($media as $item)
                        <div x-data="{ confirmDelete: false }" class="border border-[#2b2b2b] bg-[#151515] p-3.5 sm:p-4 rounded-[8px] flex flex-col justify-between gap-3 hover:border-white/20 transition-colors">
                            {{-- Fila superior: Miniatura / Icono + Detalles --}}
                            <div class="flex items-start gap-3.5 min-w-0">
                                {{-- Miniatura de imagen o Icono de tipo --}}
                                <div class="shrink-0">
                                    @if ($item->type === 'photo')
                                        <img src="{{ $item->url }}" alt="{{ $item->title ?: $item->filename }}" class="w-14 h-14 sm:w-16 sm:h-16 rounded-[6px] object-cover bg-black/50 border border-white/10" loading="lazy" onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">
                                        <div class="hidden w-14 h-14 sm:w-16 sm:h-16 rounded-[6px] bg-zinc-900 border border-white/10 text-zinc-400 flex items-center justify-center">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @elseif ($item->type === 'mp3')
                                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-[6px] bg-red-950/30 border border-red-800/40 text-[var(--lucille-accent)] flex items-center justify-center">
                                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                        </div>
                                    @elseif ($item->type === 'document')
                                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-[6px] bg-blue-950/30 border border-blue-800/40 text-blue-400 flex items-center justify-center">
                                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        </div>
                                    @else
                                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-[6px] bg-purple-950/30 border border-purple-800/40 text-purple-400 flex items-center justify-center">
                                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                    @endif
                                </div>

                                {{-- Textos y Metadatos --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc] break-words line-clamp-2 leading-snug">
                                            {{ $item->title ?: $item->filename }}
                                        </h3>
                                        @if ($item->status === 'approved')
                                            <span class="shrink-0 px-2 py-0.5 text-[9px] uppercase font-mono font-semibold rounded bg-emerald-950/80 text-emerald-400 border border-emerald-800/60">Publicado</span>
                                        @else
                                            <span class="shrink-0 px-2 py-0.5 text-[9px] uppercase font-mono font-semibold rounded bg-amber-950/80 text-amber-400 border border-amber-800/60">Pendiente</span>
                                        @endif
                                    </div>

                                    <div class="mt-1 text-[11px] font-mono text-[#8b8b8b] flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="font-semibold text-zinc-300 uppercase">{{ $item->type }}</span>
                                        <span>·</span>
                                        <span>
                                            @if ($item->size >= 1048576)
                                                {{ number_format($item->size / 1048576, 2) }} MB
                                            @else
                                                {{ number_format($item->size / 1024, 1) }} KB
                                            @endif
                                        </span>
                                        @if ($item->is_exclusive)
                                            <span>·</span>
                                            <span class="text-[var(--lucille-accent)] font-semibold uppercase text-[10px]">Exclusivo</span>
                                        @endif
                                    </div>

                                    @if ($item->description)
                                        <p class="mt-1.5 text-xs text-[#8b8b8b] break-words line-clamp-2 leading-relaxed">
                                            {{ $item->description }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Fila inferior: Acciones unificadas táctiles >= 44px --}}
                            <div class="pt-2.5 border-t border-white/5">
                                {{-- Estado normal de botones --}}
                                <div x-show="!confirmDelete" class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full">
                                    {{-- Acción Principal: Abrir / Descargar --}}
                                    @if ($item->type === 'document')
                                        <a href="{{ route('talents.media.download', $item->id) }}" 
                                           class="flex-1 min-h-[44px] px-3.5 py-2.5 rounded-[6px] bg-[var(--lucille-accent)] text-white font-medium text-xs uppercase tracking-wider flex items-center justify-center gap-2 hover:bg-[#a8201a] transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--lucille-accent)] focus:ring-offset-2 focus:ring-offset-[#151515]">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            <span>Descargar</span>
                                        </a>
                                    @else
                                        <a href="{{ $item->url }}" 
                                           target="_blank" 
                                           rel="noreferrer" 
                                           class="flex-1 min-h-[44px] px-3.5 py-2.5 rounded-[6px] bg-[var(--lucille-accent)] text-white font-medium text-xs uppercase tracking-wider flex items-center justify-center gap-2 hover:bg-[#a8201a] transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--lucille-accent)] focus:ring-offset-2 focus:ring-offset-[#151515]">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            <span>Abrir</span>
                                        </a>
                                    @endif

                                    {{-- Acción Secundaria: Eliminar --}}
                                    <button type="button" 
                                            @click="confirmDelete = true" 
                                            class="flex-1 sm:flex-initial min-h-[44px] px-3.5 py-2.5 rounded-[6px] border border-white/15 bg-white/5 text-[#dcdcdc] hover:bg-red-950/40 hover:border-red-600/50 hover:text-red-300 font-medium text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-[#151515]">
                                        <svg class="w-4 h-4 shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        <span>Eliminar</span>
                                    </button>
                                </div>

                                {{-- Confirmación Explícita de Borrado --}}
                                <div x-show="confirmDelete" x-cloak class="p-3 bg-red-950/30 border border-red-700/50 rounded-[6px] space-y-2.5">
                                    <div class="text-xs text-red-200 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <span class="font-medium">¿Eliminar este archivo de forma permanente?</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <form action="{{ route('talents.media.destroy', ['id' => $item->id]) }}" method="POST" class="flex-1">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full min-h-[44px] px-3 py-2 rounded-[6px] bg-red-600 hover:bg-red-700 text-white font-semibold text-xs uppercase tracking-wider transition-colors flex items-center justify-center">
                                                Sí, eliminar
                                            </button>
                                        </form>
                                        <button type="button" @click="confirmDelete = false" class="flex-1 min-h-[44px] px-3 py-2 rounded-[6px] border border-white/20 bg-white/10 text-[#dcdcdc] hover:bg-white/20 font-medium text-xs uppercase tracking-wider transition-colors flex items-center justify-center">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center border border-white/5 rounded-[6px] bg-black/20 text-xs sm:text-sm text-[#7b7b7b]">
                            Todavía no has subido archivos. Utiliza la sección «Subir archivo» para añadir contenido a tu perfil.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <script>
        function talentMediaUploader(typeSpecs = {}, storageInfo = {}, userPlan = '') {
            return {
                typeSpecs: typeSpecs || {},
                storageInfo: storageInfo || { limit_mb: 0, used_mb: 0, remaining_mb: 0 },
                userPlan: userPlan || '',

                activeMobileTab: 'upload', // 'upload' | 'library'
                dropdownOpen: false,
                selectedType: '{{ old('type', 'photo') }}',
                selectedLabel: '{{ old('type') === 'mp3' ? 'MP3' : (old('type') === 'document' ? 'Documento' : (old('type') === 'video' ? 'Video' : 'Foto')) }}',
                title: '{{ addslashes(old('title', '')) }}',
                description: '{{ addslashes(old('description', '')) }}',
                isExclusive: {{ old('is_exclusive') ? 'true' : 'false' }},

                fileError: '',
                generalError: '',
                serverSuccess: '',

                isUploading: false,
                isProcessing: false,
                uploadProgress: 0,
                hasComputableProgress: false,

                init() {
                    if (this.typeSpecs[this.selectedType]) {
                        this.selectedLabel = this.typeSpecs[this.selectedType].name;
                    }
                },

                get currentSpec() {
                    return this.typeSpecs[this.selectedType] || null;
                },

                get isSubmittable() {
                    if (this.isUploading) return false;
                    const spec = this.currentSpec;
                    if (!spec) return false;
                    if (!spec.included) return false;
                    if (spec.quota_full) return false;
                    if (this.fileError) return false;
                    return true;
                },

                goToLibrary() {
                    this.activeMobileTab = 'library';
                    this.$nextTick(() => {
                        const biblio = document.getElementById('biblioteca');
                        if (biblio) {
                            biblio.scrollIntoView({ behavior: 'smooth' });
                        }
                    });
                },

                selectType(key) {
                    if (!this.typeSpecs[key]) return;
                    this.selectedType = key;
                    this.selectedLabel = this.typeSpecs[key].name;
                    this.dropdownOpen = false;
                    this.generalError = '';
                    this.validateCurrentFile();
                },

                detectFileKind(file) {
                    const ext = (file.name.split('.').pop() || '').toLowerCase();
                    const mime = (file.type || '').toLowerCase();

                    const audioExts = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'wma'];
                    const imageExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'avif'];
                    const docExts = ['pdf', 'doc', 'docx', 'odt', 'txt', 'rtf'];
                    const videoExts = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'wmv'];

                    if (mime.startsWith('audio/') || audioExts.includes(ext)) {
                        return { kind: 'audio', label: 'un audio' };
                    }
                    if (mime.startsWith('image/') || imageExts.includes(ext)) {
                        return { kind: 'image', label: 'una foto' };
                    }
                    if (mime === 'application/pdf' || docExts.includes(ext)) {
                        return { kind: 'doc', label: 'un documento' };
                    }
                    if (mime.startsWith('video/') || videoExts.includes(ext)) {
                        return { kind: 'video', label: 'un vídeo' };
                    }
                    return { kind: 'other', label: 'un archivo' };
                },

                validateCurrentFile() {
                    this.fileError = '';
                    const fileInput = this.$refs.fileInput;
                    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                        return true;
                    }

                    const file = fileInput.files[0];
                    const spec = this.currentSpec;

                    if (!spec) return true;

                    // 1. Verificación de tipo incluido en el plan
                    if (!spec.included) {
                        this.fileError = `Tu plan no incluye la subida de ${spec.plural}. Elige otro tipo de contenido o mejora tu plan.`;
                        return false;
                    }

                    // 2. Verificación de formato y coherencia de tipo
                    const ext = (file.name.split('.').pop() || '').toLowerCase();
                    const isExtAllowed = spec.exts.includes(ext);

                    if (!isExtAllowed) {
                        const detected = this.detectFileKind(file);
                        const targetLabel = spec.singular === 'foto' ? 'una foto' : (spec.singular === 'canción' ? 'un MP3' : 'un ' + spec.singular);
                        
                        if (detected.kind === 'audio') {
                            this.fileError = `Has seleccionado un audio. Para subir ${targetLabel}, elige ${spec.label}.`;
                        } else if (detected.kind === 'image') {
                            this.fileError = `Has seleccionado una imagen. Para subir ${targetLabel}, elige ${spec.label}.`;
                        } else if (detected.kind === 'doc') {
                            this.fileError = `Has seleccionado un documento. Para subir ${targetLabel}, elige ${spec.label}.`;
                        } else if (detected.kind === 'video') {
                            this.fileError = `Has seleccionado un vídeo. Para subir ${targetLabel}, elige ${spec.label}.`;
                        } else {
                            this.fileError = `Formato no admitido (.${ext || 'desconocido'}). Para subir ${targetLabel}, elige ${spec.label}.`;
                        }
                        return false;
                    }

                    // 3. Verificación de cupo agotado
                    if (spec.quota_full) {
                        this.fileError = `Has utilizado ${spec.used} de ${spec.limit} ${spec.plural}. Elimina una existente en la biblioteca antes de subir otra.`;
                        return false;
                    }

                    // 4. Verificación de tamaño máximo por archivo
                    const maxBytes = spec.max_kb * 1024;
                    if (file.size > maxBytes) {
                        const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                        this.fileError = `El archivo pesa ${sizeMb} MB. Tu plan permite hasta ${spec.max_mb} MB por ${spec.singular}.`;
                        return false;
                    }

                    // 5. Verificación de espacio total de almacenamiento restante
                    const remainingBytes = this.storageInfo.remaining_mb * 1024 * 1024;
                    if (this.storageInfo.limit_mb > 0 && file.size > remainingBytes) {
                        const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                        this.fileError = `El archivo pesa ${sizeMb} MB, pero solo te quedan ${this.storageInfo.remaining_mb} MB de almacenamiento total en tu plan.`;
                        return false;
                    }

                    return true;
                },

                onFileChanged() {
                    this.generalError = '';
                    this.serverSuccess = '';
                    this.validateCurrentFile();
                },

                handleSubmit() {
                    const spec = this.currentSpec;
                    if (!spec || !spec.included) {
                        this.fileError = `Tu plan no incluye la subida de ${spec ? spec.plural : 'este tipo de archivo'}.`;
                        return;
                    }
                    if (spec.quota_full) {
                        this.fileError = `Has utilizado ${spec.used} de ${spec.limit} ${spec.plural}.`;
                        return;
                    }

                    const fileInput = this.$refs.fileInput;
                    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                        this.fileError = 'Selecciona un archivo antes de subir.';
                        if (fileInput) fileInput.focus();
                        return;
                    }

                    if (!this.validateCurrentFile()) {
                        if (fileInput) fileInput.focus();
                        return;
                    }

                    this.fileError = '';
                    this.generalError = '';
                    this.serverSuccess = '';
                    this.isUploading = true;
                    this.isProcessing = false;
                    this.uploadProgress = 0;
                    this.hasComputableProgress = false;

                    const form = this.$refs.uploadForm;
                    const formData = new FormData(form);

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', form.action, true);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    xhr.upload.onprogress = (event) => {
                        if (event.lengthComputable && event.total > 0) {
                            this.hasComputableProgress = true;
                            this.uploadProgress = Math.min(100, Math.round((event.loaded / event.total) * 100));
                            if (this.uploadProgress >= 100) {
                                this.isProcessing = true;
                            }
                        } else {
                            this.hasComputableProgress = false;
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            let res = {};
                            try {
                                res = JSON.parse(xhr.responseText);
                            } catch (err) {}

                            this.isUploading = false;
                            this.isProcessing = false;
                            this.serverSuccess = res.message || 'Archivo subido correctamente.';

                            setTimeout(() => {
                                window.location.reload();
                            }, 1200);
                        } else {
                            this.isUploading = false;
                            this.isProcessing = false;
                            let errorData = {};
                            try {
                                errorData = JSON.parse(xhr.responseText);
                            } catch (err) {}

                            if (errorData.errors && errorData.errors.file) {
                                this.fileError = Array.isArray(errorData.errors.file) ? errorData.errors.file[0] : errorData.errors.file;
                            } else if (errorData.errors && errorData.errors.type) {
                                this.generalError = Array.isArray(errorData.errors.type) ? errorData.errors.type[0] : errorData.errors.type;
                            } else if (errorData.message) {
                                this.generalError = errorData.message;
                            } else {
                                this.generalError = 'Ocurrió un error al subir el archivo. Inténtalo de nuevo.';
                            }
                        }
                    };

                    xhr.onerror = () => {
                        this.isUploading = false;
                        this.isProcessing = false;
                        this.generalError = 'Error de conexión con el servidor. Por favor, inténtalo más tarde.';
                    };

                    xhr.send(formData);
                }
            };
        }
    </script>
</x-layouts.talent>
