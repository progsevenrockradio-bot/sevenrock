<x-layouts.admin :title="'Master Programs - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php $admin = $themeAppearance['admin_texts']; @endphp

    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Master programs</h1>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.master-programs.report') }}" class="lucille-button">Reporte de Horarios</a>
                <a href="{{ route('admin.programs.index') }}" class="lucille-button">Panel códigos</a>
                <a href="{{ route('admin.master-programs.create') }}" class="lucille-button-solid">Nuevo programa</a>
            </div>
        </div>

        <form method="GET" class="mt-6 flex flex-wrap gap-3">
            <input name="search" value="{{ $search }}" class="lucille-product-field min-w-[260px] flex-1" placeholder="Buscar por nombre, código, productor o email">
            <button type="submit" class="lucille-button-solid">Filtrar</button>
            <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Limpiar</a>
        </form>

        <div class="mt-8" x-data='{"activeDay": "{{ $activeDay }}"}'>
            <div class="flex flex-wrap gap-3 border border-[#242424] bg-[#131313] p-3">
                @foreach ($dayTabs as $dayKey => $dayLabel)
                    @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                    <button
                        type="button"
                        @click="activeDay = '{{ $dayKey }}'"
                        data-day-tab="{{ $dayKey }}"
                        class="inline-flex min-w-[8rem] items-center justify-between gap-3 border px-4 py-3 text-sm uppercase tracking-[.18em] transition-colors"
                        :class="activeDay === '{{ $dayKey }}'
                            ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.04)] text-[#f2f2f2]'
                            : 'border-[#2b2b2b] text-[#7b7b7b] hover:border-[#505050] hover:text-[#dcdcdc]'"
                        aria-label="Ver programas de {{ $dayLabel }}"
                    >
                        <span>{{ $dayLabel }}</span>
                        <span class="text-[11px] tracking-[.2em] text-[#9d9d9d]">{{ $dayPrograms->count() }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($dayTabs as $dayKey => $dayLabel)
                @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                <section
                    x-cloak
                    x-show="activeDay === '{{ $dayKey }}'"
                    x-transition.opacity.duration.150ms
                    data-day-panel="{{ $dayKey }}"
                    class="pt-6"
                >
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $dayLabel }}</h2>
                            <p class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $dayPrograms->count() }} programa{{ $dayPrograms->count() === 1 ? '' : 's' }}</p>
                        </div>
                        <div class="border border-[#2b2b2b] px-3 py-2 text-[11px] uppercase tracking-[.2em] text-[#9d9d9d]">
                            Ordenado por hora de transmisión
                        </div>
                    </div>

                    @if ($dayPrograms->isEmpty())
                        <div class="border border-[#242424] bg-[#111] px-6 py-10 text-center text-[#7b7b7b]">
                            No hay programas asignados para este día.
                        </div>
                    @else
                        <div class="overflow-x-auto border border-[#242424]">
                            <table class="min-w-full divide-y divide-[#242424] text-left text-sm">
                                <thead class="bg-[#131313] text-[#7b7b7b]">
                                    <tr>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Imagen</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Programa</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Código</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Horario</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Estado</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#242424]">
                                    @foreach ($dayPrograms as $masterProgram)
                                        <tr class="align-top">
                                            <td class="px-4 py-4">
                                                @if ($masterProgram->cover_url)
                                                    <img
                                                        src="{{ $masterProgram->cover_url }}"
                                                        loading="lazy"
                                                        alt="{{ $masterProgram->name }}"
                                                        class="h-20 w-20 border border-[#2b2b2b] object-cover"
                                                    >
                                                @else
                                                    <div class="flex h-20 w-20 items-center justify-center border border-[#2b2b2b] bg-[#111] text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                                                        Sin imagen
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="font-display text-base uppercase tracking-[.08em] text-[#dcdcdc]">{{ $masterProgram->name }}</div>
                                                <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $masterProgram->conductor }}</div>
                                            </td>
                                            <td class="px-4 py-4 text-[#9d9d9d]">
                                                <div class="font-mono text-sm text-[#dcdcdc]">{{ $masterProgram->program_code ?: 'Sin código' }}</div>
                                                <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $masterProgram->code_prefix ?: 'Base auto' }}</div>
                                            </td>
                                            <td class="px-4 py-4 text-[#9d9d9d]">
                                                <div>{{ $masterProgram->dia_transmision }}</div>
                                                <div>{{ $masterProgram->hora_transmision ?: 'Sin hora' }}</div>
                                                <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $masterProgram->timezone }}</div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="inline-flex items-center border border-[#2b2b2b] px-3 py-1 text-[11px] uppercase tracking-[.18em] {{ $masterProgram->activo ? 'text-[#dcdcdc]' : 'text-[#7b7b7b]' }}">
                                                    {{ $masterProgram->activo ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="flex flex-nowrap gap-1.5">
                                                    <a
                                                        href="{{ route('admin.master-programs.edit', $masterProgram) }}"
                                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center border border-[#2b2b2b] text-[#dcdcdc] transition-colors hover:border-[var(--color-lucille-accent)] hover:bg-[var(--color-lucille-accent)] hover:text-white"
                                                        title="Editar"
                                                        aria-label="Editar programa"
                                                    >
                                                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <path d="M12 20h9" />
                                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                        </svg>
                                                    </a>
                                                    <button
                                                        type="button"
                                                        @click="$dispatch('open-invitation', { id: {{ $masterProgram->id }}, name: '{{ addslashes($masterProgram->name) }}', email: '{{ addslashes((string)($masterProgram->email_notificacion ?? '')) }}' })"
                                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center border border-[#2b2b2b] text-[#dcdcdc] transition-colors hover:border-[#a855f7] hover:bg-[#a855f7]/20 hover:text-[#a855f7]"
                                                        title="Solicitar Info"
                                                    >
                                                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                                        </svg>
                                                    </button>
                                                    <form
                                                        action="{{ route('admin.master-programs.destroy', $masterProgram) }}"
                                                        method="POST"
                                                        data-confirm="¿Eliminar este programa maestro?"
                                                        data-confirm-title="Eliminar programa maestro"
                                                        data-confirm-action="Eliminar"
                                                        data-confirm-tone="danger"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center border border-[#2b2b2b] text-[#dcdcdc] transition-colors hover:border-red-500 hover:bg-red-500 hover:text-white"
                                                            title="Eliminar"
                                                            aria-label="Eliminar programa"
                                                        >
                                                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                <path d="M3 6h18" />
                                                                <path d="M8 6V4h8v2" />
                                                                <path d="M19 6l-1 14H6L5 6" />
                                                                <path d="M10 11v6" />
                                                                <path d="M14 11v6" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </section>

    <!-- Modal Invitación -->
    <div x-data="{
        show: false,
        loading: false,
        sendingEmail: false,
        openInvitation: false,
        programId: null,
        programName: '',
        programEmail: '',
        invitationId: null,
        expiresIn: 3,
        url: '',
        alertOpen: false,
        alertMessage: '',
        alertType: 'error',
        fields: {
            nombre: true,
            conductor: true,
            genero: true,
            descripcion: true,
            red_social1_url: false,
            red_social2_url: false,
            dia_transmision: true,
            hora_transmision: true,
            caratula_url: false
        },
        init() {
            window.addEventListener('open-invitation', (e) => {
                this.programId = e.detail.id;
                this.programName = e.detail.name;
                this.programEmail = e.detail.email || '';
                this.invitationId = null;
                this.url = '';
                this.openInvitation = true;
            });
        },
        async generate() {
            this.loading = true;
            this.url = '';
            let requestedFields = [];
            for (const [key, val] of Object.entries(this.fields)) {
                if (val) requestedFields.push(key);
            }
            try {
                const res = await fetch('/admin/master-programs/' + this.programId + '/invitations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        expires_in_days: this.expiresIn,
                        requested_fields: requestedFields
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.url = data.url;
                    this.invitationId = data.invitation_id;
                } else {
                    this.showError('Error: ' + data.message);
                }
            } catch (err) {
                this.showError('Ocurrió un error al generar la invitación.');
            }
            this.loading = false;
        },
        copyUrl() {
            const input = document.getElementById('invitation-url');
            input.select();
            document.execCommand('copy');
            this.showError('¡Enlace copiado al portapapeles!', 'success');
        },
        async sendEmail() {
            if (!this.invitationId || !this.programEmail) return;
            this.sendingEmail = true;
            try {
                const res = await fetch('/admin/master-programs/' + this.programId + '/invitations/' + this.invitationId + '/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ email: this.programEmail })
                });
                const data = await res.json();
                if (data.success) {
                    this.showError('Correo enviado exitosamente.', 'success');
                } else {
                    this.showError('Error: ' + (data.message || 'No se encontró la ruta o recurso. Asegúrate de vaciar la caché de rutas.'));
                }
            } catch (err) {
                this.showError('Ocurrió un error al enviar el correo. Si el error persiste, intenta vaciar la caché de rutas en tu servidor.');
            }
            this.sendingEmail = false;
        },
        showError(message, type = 'error') {
            this.alertMessage = message;
            this.alertType = type;
            this.alertOpen = true;
        }
    }">
        <div x-show="openInvitation" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4 py-6 sm:px-0">
            <div @click.away="openInvitation = false" class="w-full max-w-lg border border-[#2b2b2b] bg-[rgba(16,16,18,1)] p-6 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-display text-xl uppercase tracking-[.1em] text-[#dcdcdc]">Generar Invitación Temporal</h3>
                    <button @click="openInvitation = false" class="text-[#7b7b7b] hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="mb-4 text-sm text-[#7b7b7b]">Crea un enlace único para que el productor actualice la información de: <strong class="text-white" x-text="programName"></strong>.</p>
                
                <div class="mb-4">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Vigencia (Días)</label>
                    <input type="number" x-model.number="expiresIn" min="1" max="30" class="lucille-product-field w-full">
                </div>
                
                <div class="mb-6">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Campos a solicitar</label>
                    <div class="grid grid-cols-2 gap-3 text-sm text-[#dcdcdc]">
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="fields.nombre" class="rounded border-[#2b2b2b] bg-[#1a1a1e] text-[#a855f7]"> Nombre</label>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="fields.conductor" class="rounded border-[#2b2b2b] bg-[#1a1a1e] text-[#a855f7]"> Conductor</label>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="fields.genero" class="rounded border-[#2b2b2b] bg-[#1a1a1e] text-[#a855f7]"> Género</label>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="fields.descripcion" class="rounded border-[#2b2b2b] bg-[#1a1a1e] text-[#a855f7]"> Descripción</label>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="fields.caratula_url" class="rounded border-[#2b2b2b] bg-[#1a1a1e] text-[#a855f7]"> Carátula URL</label>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="fields.red_social1_url" class="rounded border-[#2b2b2b] bg-[#1a1a1e] text-[#a855f7]"> Red Social 1</label>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="fields.red_social2_url" class="rounded border-[#2b2b2b] bg-[#1a1a1e] text-[#a855f7]"> Red Social 2</label>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="fields.dia_transmision" class="rounded border-[#2b2b2b] bg-[#1a1a1e] text-[#a855f7]"> Día Transmisión</label>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="fields.hora_transmision" class="rounded border-[#2b2b2b] bg-[#1a1a1e] text-[#a855f7]"> Hora Transmisión</label>
                    </div>
                </div>

                <div x-show="!url" class="flex justify-end">
                    <button type="button" @click="generate()" class="lucille-button-solid bg-[#a855f7] border-[#a855f7] text-white hover:bg-[#9333ea]" :disabled="loading">
                        <span x-show="!loading">Generar Enlace</span>
                        <span x-show="loading">Generando...</span>
                    </button>
                </div>

                <template x-if="url">
                    <div class="mt-6 border-t border-[#2b2b2b] pt-6">
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Enlace Generado</label>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly :value="url" class="lucille-form-field flex-1 cursor-text bg-[#0e0e10]" id="invitation-url">
                            <button type="button" @click="copyUrl" class="lucille-button-solid bg-[#a855f7] hover:bg-[#9333ea] border-none text-white whitespace-nowrap">
                                Copiar
                            </button>
                        </div>
                        <template x-if="programEmail">
                            <button type="button" :disabled="sendingEmail" @click="sendEmail" class="lucille-button-solid mt-4 w-full bg-green-600 hover:bg-green-500 border-none text-white flex justify-center items-center gap-2">
                                <span x-show="!sendingEmail">Enviar al correo (</span><span x-show="!sendingEmail" x-text="programEmail"></span><span x-show="!sendingEmail">)</span>
                                <span x-show="sendingEmail">Enviando...</span>
                            </button>
                        </template>
                        <template x-if="!programEmail">
                            <p class="mt-4 text-xs text-red-400">Este programa no tiene un correo configurado. Cópialo manualmente.</p>
                        </template>
                    </div>
                </template>
                <p class="mt-4 text-xs text-[#7b7b7b]">Este enlace expirará en <span x-text="expiresIn"></span> días y solo puede usarse una vez.</p>
            </div>
        </div>

        <!-- Custom Alert Modal -->
        <div x-show="alertOpen" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 px-4 py-6 sm:px-0">
            <div @click.away="alertOpen = false" class="w-full max-w-sm border bg-[rgba(16,16,18,1)] p-6 shadow-2xl"
                 :class="alertType === 'error' ? 'border-[#c32720]' : 'border-[var(--color-lucille-accent)]'">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-display text-lg uppercase tracking-[.1em]" :class="alertType === 'error' ? 'text-[#c32720]' : 'text-[var(--color-lucille-accent)]'" x-text="alertType === 'error' ? 'Error' : 'Notificación'"></h3>
                    <button @click="alertOpen = false" class="text-[#7b7b7b] hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="mb-6 text-sm text-[#dcdcdc]" x-text="alertMessage"></p>
                <div class="flex justify-end">
                    <button type="button" @click="alertOpen = false" class="lucille-button-solid" :class="alertType === 'error' ? 'bg-[#c32720] hover:bg-[#a1201a] border-[#c32720]' : 'bg-[var(--color-lucille-accent)] hover:bg-opacity-80'">
                        Aceptar
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
