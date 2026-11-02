<x-layouts.admin title="Invitaciones a programas">
    <div x-data="programSelection()" class="space-y-6">
        {{-- Cabecera --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl font-bold uppercase tracking-wider text-white">Invitaciones a programas</h1>
                <p class="mt-1 text-sm text-zinc-400">Gestión de códigos de acceso de productores, exportación de horarios y envío de invitaciones por correo.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.master-programs.index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                    <span>Programas de radio</span>
                </a>
                <a href="{{ route('admin.programs.invitations') }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#d62d26]">
                    <span>Vista rápida por tarjetas</span>
                </a>
            </div>
        </div>

        {{-- Barra de herramientas y filtros --}}
        <div class="flex flex-col gap-3 rounded-xl border border-white/10 bg-[#101012] p-3.5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex items-center gap-2.5 border-r border-white/10 pr-3.5 text-xs font-medium text-zinc-300 cursor-pointer select-none">
                    <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                    <span><strong class="font-semibold text-white" x-text="selectedPrograms.length"></strong> seleccionado(s)</span>
                </label>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="generatePdf()"
                        :disabled="selectedPrograms.length === 0"
                        :class="selectedPrograms.length > 0
                            ? 'border-white/10 bg-white/5 text-zinc-200 hover:bg-white/10 hover:text-white'
                            : 'border-white/5 bg-white/[0.02] text-zinc-500 cursor-not-allowed'"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-xs font-medium transition"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span>Generar PDF</span>
                    </button>
                    <button
                        type="button"
                        @click="showEmailModal = true"
                        :disabled="selectedPrograms.length === 0"
                        :class="selectedPrograms.length > 0
                            ? 'bg-[#c32720] text-white hover:bg-[#d62d26]'
                            : 'bg-white/5 text-zinc-500 cursor-not-allowed'"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3.5 text-xs font-semibold transition"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <span>Enviar horarios por correo</span>
                    </button>
                </div>
            </div>

            <form method="GET" class="flex flex-1 items-center gap-2 lg:max-w-md">
                <div class="relative flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input
                        name="search"
                        value="{{ $search }}"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#161619] pl-9 pr-3 text-sm text-zinc-100 placeholder-zinc-500 focus:border-[#c32720] focus:outline-none focus:ring-2 focus:ring-[#c32720]/30"
                        placeholder="Buscar programa, código o conductor..."
                    >
                </div>
                <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-white/10 px-3.5 text-xs font-medium text-white transition hover:bg-white/15">
                    Buscar
                </button>
                @if ($search)
                    <a href="{{ route('admin.programs.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 px-3 text-xs font-medium text-zinc-400 transition hover:bg-white/5 hover:text-zinc-200">
                        Limpiar
                    </a>
                @endif
            </form>
        </div>

        {{-- Vista móvil (tarjetas compactas) --}}
        <section class="space-y-3 md:hidden">
            @forelse ($programs as $program)
                <div class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <label class="flex items-start gap-2.5 min-w-0 cursor-pointer">
                            <input type="checkbox" :value="{{ $program->id }}" x-model="selectedPrograms" class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                            <div class="min-w-0">
                                <div class="font-semibold text-zinc-100 text-sm truncate">{{ $program->name }}</div>
                                <div class="text-xs text-zinc-400 truncate">{{ $program->conductor ?: 'Sin conductor' }}</div>
                            </div>
                        </label>
                        <span class="shrink-0 rounded-md border border-white/10 bg-white/5 px-2 py-0.5 font-mono text-xs text-zinc-200">
                            {{ $program->program_code ?: 'Sin código' }}
                        </span>
                    </div>

                    <div class="text-xs text-zinc-400 border-t border-white/5 pt-2">
                        Correo: <span class="text-zinc-300">{{ $program->email_notificacion ?: 'Sin correo configurado' }}</span>
                    </div>

                    <div class="space-y-2 border-t border-white/5 pt-2.5">
                        <form action="{{ route('admin.programs.generate-code', $program) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-zinc-200">
                                {{ $program->program_code ? 'Regenerar código' : 'Asignar código' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.programs.send-invitation', $program) }}" method="POST" class="flex items-center gap-2">
                            @csrf
                            <select name="template_id" class="h-8 flex-1 rounded-lg border border-white/10 bg-[#161619] px-2.5 text-xs text-zinc-200">
                                @forelse ($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @empty
                                    <option value="">Plantilla predeterminada</option>
                                @endforelse
                            </select>
                            <button type="submit" class="inline-flex h-8 shrink-0 items-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white">
                                Enviar
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-8 text-center text-sm text-zinc-400">
                    No se encontraron programas con los criterios indicados.
                </div>
            @endforelse
        </section>

        {{-- Tabla de programas e invitaciones (Escritorio) --}}
        <section class="hidden md:block overflow-hidden rounded-xl border border-white/10 bg-[#101012]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                    <thead class="bg-white/[0.02] text-xs font-semibold text-zinc-400">
                        <tr>
                            <th class="w-10 px-3.5 py-3">
                                <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                            </th>
                            <th class="px-3.5 py-3">Programa</th>
                            <th class="px-3.5 py-3">Código</th>
                            <th class="px-3.5 py-3">Productor y correo</th>
                            <th class="px-3.5 py-3 text-right">Código e invitación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($programs as $program)
                            <tr class="align-middle transition hover:bg-white/[0.02]">
                                <td class="px-3.5 py-3">
                                    <input type="checkbox" :value="{{ $program->id }}" x-model="selectedPrograms" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                                </td>
                                <td class="px-3.5 py-3 font-semibold text-zinc-100">
                                    {{ $program->name }}
                                </td>
                                <td class="px-3.5 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-md border border-white/10 bg-white/5 px-2 py-0.5 font-mono text-xs text-zinc-200">
                                        {{ $program->program_code ?: 'Sin código' }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-3">
                                    <div class="text-xs font-medium text-zinc-200">{{ $program->conductor ?: '—' }}</div>
                                    <div class="mt-0.5 text-[11px] text-zinc-400">{{ $program->email_notificacion ?: 'Sin correo configurado' }}</div>
                                </td>
                                <td class="px-3.5 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex flex-nowrap items-center justify-end gap-1.5">
                                        <form action="{{ route('admin.programs.generate-code', $program) }}" method="POST" class="shrink-0">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 shrink-0 items-center rounded-lg border border-white/10 bg-white/5 px-2.5 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                                                {{ $program->program_code ? 'Regenerar código' : 'Asignar código' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.programs.send-invitation', $program) }}" method="POST" class="inline-flex flex-nowrap items-center gap-1.5 shrink-0">
                                            @csrf
                                            <select name="template_id" class="h-8 w-[140px] shrink-0 rounded-lg border border-white/10 bg-[#161619] px-2 text-xs text-zinc-200 focus:border-[#c32720] focus:outline-none">
                                                @forelse ($templates as $template)
                                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                                @empty
                                                    <option value="">Plantilla base</option>
                                                @endforelse
                                            </select>
                                            <button type="submit" class="inline-flex h-8 shrink-0 items-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white transition hover:bg-[#d62d26]">
                                                Enviar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-zinc-400">
                                    No se encontraron programas con los criterios indicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($programs->hasPages())
                <div class="border-t border-white/10 px-4 py-3">
                    {{ $programs->links() }}
                </div>
            @endif
        </section>

        {{-- Modal Enviar Horarios por Correo --}}
        <div x-show="showEmailModal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
            <div @click.away="!isSending && (showEmailModal = false)" class="relative w-full max-w-lg rounded-xl border border-white/10 bg-[#141417] p-6 shadow-2xl">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-display text-lg font-bold uppercase tracking-wider text-white">Enviar horarios seleccionados</h3>
                    <button type="button" @click="showEmailModal = false" :disabled="isSending" class="rounded-lg p-1 text-zinc-400 transition hover:bg-white/5 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="sendEmail" class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-300">Destinatario(s) *</label>
                        <input type="text" x-model="emailForm.email" required placeholder="productor@correo.com, locutor@correo.com" class="h-10 w-full rounded-lg border border-white/10 bg-[#18181c] px-3 text-sm text-zinc-100 placeholder-zinc-500 focus:border-[#c32720] focus:outline-none" :disabled="isSending">
                        <template x-if="errors.email">
                            <p class="mt-1 text-xs text-red-400" x-text="errors.email[0]"></p>
                        </template>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-300">Asunto *</label>
                        <input type="text" x-model="emailForm.subject" required class="h-10 w-full rounded-lg border border-white/10 bg-[#18181c] px-3 text-sm text-zinc-100 focus:border-[#c32720] focus:outline-none" :disabled="isSending">
                        <template x-if="errors.subject">
                            <p class="mt-1 text-xs text-red-400" x-text="errors.subject[0]"></p>
                        </template>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-300">Mensaje adicional (opcional)</label>
                        <textarea x-model="emailForm.message" rows="4" class="w-full rounded-lg border border-white/10 bg-[#18181c] p-3 text-sm text-zinc-100 focus:border-[#c32720] focus:outline-none" :disabled="isSending"></textarea>
                        <template x-if="errors.message">
                            <p class="mt-1 text-xs text-red-400" x-text="errors.message[0]"></p>
                        </template>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-white/10 pt-4">
                        <button type="button" @click="showEmailModal = false" :disabled="isSending" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/5 px-4 text-xs font-medium text-zinc-300 hover:bg-white/10">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="isSending" class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#d62d26]">
                            <span x-show="!isSending">Enviar correo</span>
                            <span x-show="isSending">Enviando...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal de Notificación --}}
        <div x-show="showNotificationModal" style="display: none;" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
            <div @click.away="showNotificationModal = false" class="relative w-full max-w-md overflow-hidden rounded-xl border border-white/10 bg-[#141417] shadow-2xl">
                <div class="h-1 w-full" :class="notificationType === 'success' ? 'bg-emerald-500' : 'bg-[#c32720]'"></div>
                <div class="p-6">
                    <div class="flex items-start gap-3.5">
                        <div x-show="notificationType === 'success'" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 font-bold">✓</div>
                        <div x-show="notificationType === 'error'" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-red-500/30 bg-red-500/10 text-red-400 font-bold">!</div>

                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400" x-text="notificationType === 'success' ? 'Confirmación' : 'Atención'"></p>
                            <h2 class="mt-0.5 font-display text-lg font-bold uppercase tracking-wider text-white" x-text="notificationTitle"></h2>
                            <p class="mt-2 text-sm text-zinc-300" x-text="notificationMessage"></p>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="button" @click="showNotificationModal = false" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#d62d26]">
                            Aceptar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function programSelection() {
            return {
                selectedPrograms: [],
                selectAll: false,
                isSending: false,
                showEmailModal: false,
                showNotificationModal: false,
                notificationTitle: '',
                notificationMessage: '',
                notificationType: 'success',
                emailForm: { email: '', subject: 'Horarios de Programas - Seven Rock Radio', message: '' },
                errors: {},
                allProgramIds: @json($programs->pluck('id')),

                showNotify(title, message, type = 'success') {
                    this.notificationTitle = title;
                    this.notificationMessage = message;
                    this.notificationType = type;
                    this.showNotificationModal = true;
                },

                toggleAll() {
                    if (this.selectAll) {
                        this.selectedPrograms = [...this.allProgramIds];
                    } else {
                        this.selectedPrograms = [];
                    }
                },

                async generatePdf() {
                    if (this.selectedPrograms.length === 0) return;
                    const query = this.selectedPrograms.map(id => `ids[]=${id}`).join('&');
                    const url = `{{ route('admin.programs.export-pdf') }}?${query}`;

                    const newWindow = window.open('', '_blank');
                    if (newWindow) {
                        newWindow.document.write('<!DOCTYPE html><html><head><title>Generando PDF...</title><style>body{background:#101012;color:#dcdcdc;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;}</style></head><body><h2>Generando documento, por favor espere...</h2></body></html>');
                    }

                    try {
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) {
                            if (newWindow) newWindow.close();
                            const data = await response.json().catch(() => ({}));
                            this.showNotify('Error', 'No se pudo generar el PDF. ' + (data.message || 'Inténtelo de nuevo.'), 'error');
                            return;
                        }

                        if (newWindow) {
                            newWindow.location.href = url;
                        } else {
                            window.location.href = url;
                        }
                    } catch (error) {
                        if (newWindow) newWindow.close();
                        this.showNotify('Error', 'Error de red al generar el PDF.', 'error');
                    }
                },

                async sendEmail() {
                    if (this.selectedPrograms.length === 0) return;
                    this.isSending = true;
                    this.errors = {};

                    try {
                        const response = await fetch(`{{ route('admin.programs.send-email') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                email: this.emailForm.email,
                                subject: this.emailForm.subject,
                                message: this.emailForm.message,
                                ids: this.selectedPrograms
                            })
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            if (response.status === 422) {
                                this.errors = data.errors || {};
                            } else {
                                this.showEmailModal = false;
                                this.showNotify('Error', 'Error al enviar el correo: ' + (data.message || 'Desconocido'), 'error');
                            }
                        } else {
                            this.showEmailModal = false;
                            this.showNotify('Operación exitosa', data.message || 'El correo ha sido enviado correctamente.', 'success');
                            this.emailForm.email = '';
                            this.emailForm.message = '';
                        }
                    } catch (error) {
                        this.showEmailModal = false;
                        this.showNotify('Error', 'Error de red al enviar el correo.', 'error');
                    } finally {
                        this.isSending = false;
                    }
                }
            }
        }
    </script>
</x-layouts.admin>
