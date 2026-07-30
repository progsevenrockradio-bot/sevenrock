<x-layouts.admin title="Programas con código">
    <div x-data="programSelection()">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Programas</h1>
            <p class="mt-2 max-w-3xl text-sm text-[#9a9a9a]">Gestiona códigos únicos y envíos de invitación a productores.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">CRUD programas</a>
            <a href="{{ route('admin.programs.invitations') }}" class="lucille-button-solid">Invitaciones</a>
        </div>
    </div>

    <!-- Toolbar Unificada -->
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        
        <!-- Izquierda: Master Checkbox y Acciones Masivas -->
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-3 border-r border-[#2b2b2b] pr-4">
                <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="lucille-checkbox h-5 w-5">
                <span class="text-xs uppercase tracking-[.1em] text-[#9a9a9a] select-none">
                    <span x-text="selectedPrograms.length"></span> seleccionado(s)
                </span>
            </div>
            
            <div class="flex items-center gap-2">
                <button @click="generatePdf()" :disabled="selectedPrograms.length === 0" :class="selectedPrograms.length > 0 ? 'lucille-button text-xs py-1.5' : 'lucille-button text-xs py-1.5 opacity-50 cursor-not-allowed'">Generar PDF</button>
                <button @click="showEmailModal = true" :disabled="selectedPrograms.length === 0" :class="selectedPrograms.length > 0 ? 'lucille-button-solid text-xs py-1.5' : 'lucille-button-solid text-xs py-1.5 opacity-50 cursor-not-allowed'">Enviar por correo</button>
            </div>
        </div>

        <!-- Derecha: Búsqueda y Filtros -->
        <form method="GET" class="flex flex-1 lg:max-w-md items-center gap-2">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-4 h-4 text-[#9a9a9a]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                    </svg>
                </div>
                <input name="search" value="{{ $search }}" class="lucille-product-field w-full pl-10 py-1.5 text-sm" placeholder="Buscar programa, conductor...">
            </div>
            <button type="submit" class="lucille-button-solid text-xs py-1.5">Buscar</button>
            @if($search)
                <a href="{{ route('admin.programs.index') }}" class="lucille-button text-xs py-1.5">Limpiar</a>
            @endif
        </form>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1040px] text-left text-sm">
                <thead class="text-xs uppercase tracking-[.18em] text-[#9a9a9a]">
                    <tr>
                        <th class="py-3 pr-4 pl-4 w-12">
                            <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="lucille-checkbox">
                        </th>
                        <th class="py-3 pr-4">Programa</th>
                        <th class="py-3 pr-4">Código</th>
                        <th class="py-3 pr-4">Productor</th>
                        <th class="py-3 pr-4">Email</th>
                        <th class="py-3 pr-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($programs as $program)
                        <tr class="border-t border-[#242424] align-top">
                            <td class="py-4 pr-4 pl-4">
                                <input type="checkbox" :value="{{ $program->id }}" x-model="selectedPrograms" class="lucille-checkbox">
                            </td>
                            <td class="py-4 pr-4 text-[#dcdcdc]">{{ $program->name }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $program->program_code ?: 'Sin código' }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $program->conductor }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $program->email_notificacion ?: 'Sin correo' }}</td>
                            <td class="py-4 pr-4">
                                <div class="flex flex-wrap gap-2">
                                    <form action="{{ route('admin.programs.generate-code', $program) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="lucille-button">Asignar/Regenerar código</button>
                                    </form>
                                    <form action="{{ route('admin.programs.send-invitation', $program) }}" method="POST" class="flex items-center gap-2">
                                        @csrf
                                        <select name="template_id" class="lucille-product-field w-[220px]">
                                            @foreach ($templates as $template)
                                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="lucille-button-solid">Enviar invitación</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-[#9a9a9a]">No hay programas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $programs->links() }}</div>
    </section>

    <!-- Modal Enviar Correo -->
    <div x-show="showEmailModal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-black/80 p-4 sm:p-0 backdrop-blur-sm">
        <div @click.away="!isSending && (showEmailModal = false)" class="relative w-full max-w-lg border border-[#2b2b2b] bg-[rgba(16,16,18,0.95)] shadow-[0_28px_72px_rgba(0,0,0,.58)]">
            <div class="border-b border-[#2b2b2b] p-6">
                <h3 class="font-display text-xl uppercase tracking-[.1em] text-[#dcdcdc]">Enviar Horarios</h3>
                <button type="button" @click="showEmailModal = false" :disabled="isSending" class="absolute right-6 top-6 text-[#9a9a9a] hover:text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            
            <div class="p-6">
                <form @submit.prevent="sendEmail" class="space-y-5">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Destinatario(s) *</label>
                        <input type="text" x-model="emailForm.email" required placeholder="ej: productor@mail.com, locutor@mail.com" class="lucille-product-field w-full" :disabled="isSending">
                        <template x-if="errors.email">
                            <p class="mt-1 text-xs text-[#c32720]" x-text="errors.email[0]"></p>
                        </template>
                    </div>
                    
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Asunto *</label>
                        <input type="text" x-model="emailForm.subject" required class="lucille-product-field w-full" :disabled="isSending">
                        <template x-if="errors.subject">
                            <p class="mt-1 text-xs text-[#c32720]" x-text="errors.subject[0]"></p>
                        </template>
                    </div>
                    
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Mensaje (Opcional)</label>
                        <textarea x-model="emailForm.message" rows="4" class="lucille-product-field w-full" :disabled="isSending"></textarea>
                        <template x-if="errors.message">
                            <p class="mt-1 text-xs text-[#c32720]" x-text="errors.message[0]"></p>
                        </template>
                    </div>
                    
                    <div class="mt-8 flex justify-end gap-3 border-t border-[#2b2b2b] pt-6">
                        <button type="button" @click="showEmailModal = false" :disabled="isSending" class="lucille-button">Cancelar</button>
                        <button type="submit" :disabled="isSending" class="lucille-button-solid flex items-center gap-2">
                            <span x-show="!isSending">Enviar Correo</span>
                            <span x-show="isSending">Enviando...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Notificación Corporativa -->
    <div x-show="showNotificationModal" style="display: none;" class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overflow-x-hidden bg-black/80 p-4 sm:p-0 backdrop-blur-sm">
        <div @click.away="showNotificationModal = false" class="relative w-full max-w-md border border-[#2b2b2b] bg-[rgba(16,16,18,0.96)] shadow-[0_30px_90px_rgba(0,0,0,.72)]">
            <div class="h-1 w-full" :class="notificationType === 'success' ? 'bg-[#3B82F6]' : 'bg-[#c32720]'"></div>
            <div class="p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div x-show="notificationType === 'success'" class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center border border-[#1e3a8a] bg-[rgba(59,130,246,.12)] text-[#93c5fd] text-[10px] font-bold uppercase tracking-[.22em]">✓</div>
                    <div x-show="notificationType === 'error'" class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center border border-[#5c2a2a] bg-[rgba(195,39,32,.12)] text-[#ffd0d0] text-[10px] font-bold uppercase tracking-[.22em]">!</div>
                    
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-[9px] uppercase tracking-[.28em] text-[#8a8a8a]" x-text="notificationType === 'success' ? 'Confirmación' : 'Atención'"></p>
                        <h2 class="mt-1 font-display text-[1.45rem] uppercase tracking-[.06em] text-[#f2f2f2]" x-text="notificationTitle"></h2>
                        <p class="mt-2 text-[13px] leading-6 text-[#c3c3c3]" x-text="notificationMessage"></p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" @click="showNotificationModal = false" class="lucille-button-solid">Aceptar</button>
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
                notificationType: 'success', // 'success' or 'error'
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
                        newWindow.document.write('<!DOCTYPE html><html><head><title>Generando PDF...</title><style>body{background:#151515;color:#dcdcdc;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;}</style></head><body><h2>Generando documento, por favor espere...</h2></body></html>');
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
