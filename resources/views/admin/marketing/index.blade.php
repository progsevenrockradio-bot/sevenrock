<x-layouts.admin :title="'Campañas de difusión - Seven Rock Radio'">
    @php
        $campaignStatusLabels = [
            'sent' => 'Despachada (SMTP)',
            'sending' => 'En proceso de envío',
            'failed' => 'Con fallos',
            'pending' => 'En cola',
        ];
        $sourceTypeLabels = [
            'manual' => 'Carga manual',
            'imap_ai' => 'Sincronización Gmail (IA)',
            'imap' => 'Sincronización IMAP',
        ];
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-5 font-sans"
        x-data="{
            activeTab: '{{ request('tab', 'contacts') }}',
            showAddContactModal: false,
            showScrapeModal: false,
            showAddAccountModal: false,
            showCronModal: false,
            editAccountData: null
        }"
    >
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Comunicación · Boletines y difusión general</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Campañas de difusión</h1>
                <p class="mt-1 max-w-3xl text-xs text-gray-400">
                    Gestión de cuentas remitentes IMAP/SMTP, directorio general de contactos comerciales y envío de boletines en lotes (independiente de <a href="{{ route('admin.outreach.index') }}" class="text-gray-300 underline hover:text-white">Contactos e invitaciones</a>).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="showCronModal = true" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Estado de cola ({{ $pendingJobsCount }} pend.)</span>
                </button>
                <button type="button" @click="activeTab = 'campaigns'" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Redactar campaña</span>
                </button>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {!! nl2br(e(session('status'))) !!}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                {!! nl2br(e(session('error'))) !!}
            </div>
        @endif

        {{-- Navegación ligera por pestañas --}}
        <nav aria-label="Secciones de campañas de difusión" class="flex flex-wrap items-center gap-1.5 rounded-xl border border-white/10 bg-[#101012] p-1.5">
            <button
                type="button"
                @click="activeTab = 'contacts'"
                :class="activeTab === 'contacts' ? 'bg-[#c32720] text-white font-semibold' : 'text-gray-300 hover:bg-white/[0.05] hover:text-white'"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs transition"
            >
                <span>Contactos ({{ $contacts->total() }})</span>
            </button>
            <button
                type="button"
                @click="activeTab = 'campaigns'"
                :class="activeTab === 'campaigns' ? 'bg-[#c32720] text-white font-semibold' : 'text-gray-300 hover:bg-white/[0.05] hover:text-white'"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs transition"
            >
                <span>Redactar y enviar campaña</span>
            </button>
            <button
                type="button"
                @click="activeTab = 'accounts'"
                :class="activeTab === 'accounts' ? 'bg-[#c32720] text-white font-semibold' : 'text-gray-300 hover:bg-white/[0.05] hover:text-white'"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs transition"
            >
                <span>Cuentas remitentes ({{ $accounts->count() }})</span>
            </button>
            <button
                type="button"
                @click="activeTab = 'history'"
                :class="activeTab === 'history' ? 'bg-[#c32720] text-white font-semibold' : 'text-gray-300 hover:bg-white/[0.05] hover:text-white'"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs transition"
            >
                <span>Historial de campañas ({{ $campaigns->count() }})</span>
            </button>
            <button
                type="button"
                @click="activeTab = 'guide'"
                :class="activeTab === 'guide' ? 'bg-[#c32720] text-white font-semibold' : 'text-gray-300 hover:bg-white/[0.05] hover:text-white'"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs transition"
            >
                <span>Guía y diagnóstico</span>
            </button>
        </nav>

        {{-- ==================== PESTAÑA: CONTACTOS ==================== --}}
        <section x-cloak x-show="activeTab === 'contacts'" class="space-y-4">
            @if($pendingJobsCount > 0)
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-xs text-amber-100">
                        <strong class="font-semibold text-amber-300">Tareas pendientes en la cola de difusión ({{ $pendingJobsCount }}):</strong>
                        existen procesos de sincronización o envío en espera de ejecución por el trabajador de segundo plano (<code class="text-[11px]">marketing</code>).
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <form action="{{ route('admin.marketing.run-worker') }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex h-8 items-center rounded-lg bg-amber-500 px-3 text-xs font-semibold text-black hover:bg-amber-400">
                                Procesar cola manualmente
                            </button>
                        </form>
                        <button type="button" @click="showCronModal = true" class="inline-flex h-8 items-center rounded-lg border border-amber-400/30 bg-black/20 px-2.5 text-xs text-amber-200 hover:text-white">
                            Ver configuración Cron
                        </button>
                    </div>
                </div>
            @endif

            {{-- Barra de búsqueda y acciones con alcance claro --}}
            <div class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <form action="{{ route('admin.marketing.index') }}" method="GET" class="flex flex-1 flex-wrap items-end gap-2.5">
                        <input type="hidden" name="tab" value="contacts">
                        <div class="flex-1 min-w-[240px]">
                            <label for="marketing-search" class="mb-1 block text-xs font-medium text-gray-300">Buscar en contactos de difusión</label>
                            <input
                                id="marketing-search"
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Correo, nombre, banda, empresa o cargo..."
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                            >
                        </div>
                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                            Filtrar
                        </button>
                        @if(request('search'))
                            <a href="{{ route('admin.marketing.index', ['tab' => 'contacts']) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white">
                                Restablecer filtros
                            </a>
                        @endif
                    </form>

                    <div class="flex flex-wrap items-center gap-2">
                        @if($accounts->count() > 0)
                            <button type="button" @click="showScrapeModal = true" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 text-xs font-medium text-emerald-200 hover:bg-emerald-500/20">
                                <span>Sincronizar desde Gmail (IMAP)</span>
                            </button>
                        @else
                            <button type="button" disabled title="Configura primero una cuenta remitente en la pestaña Cuentas remitentes" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.02] px-3 text-xs font-medium text-gray-500 cursor-not-allowed">
                                <span>Sincronizar desde Gmail (requiere cuenta)</span>
                            </button>
                        @endif
                        <button type="button" @click="showAddContactModal = true" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                            <span>+ Agregar contacto manual</span>
                        </button>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 border-t border-white/5 pt-2.5">
                    <strong class="text-gray-300">Alcance:</strong> «Agregar contacto manual» incorpora un destinatario individual a este directorio; «Sincronizar desde Gmail» analiza en segundo plano entre 50 y 500 correos de la carpeta IMAP elegida para extraer nuevos remitentes.
                </p>
            </div>

            {{-- Tabla de contactos en escritorio --}}
            <div class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-white/10 bg-[#151518] text-[11px] font-semibold text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Contacto y correo</th>
                                <th class="px-4 py-3">Empresa / Banda</th>
                                <th class="px-4 py-3">Cargo / Rol</th>
                                <th class="px-4 py-3">Origen de importación</th>
                                <th class="px-4 py-3">Fecha de alta</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($contacts as $contact)
                                <tr class="hover:bg-white/[0.02] align-middle">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-white">{{ $contact->name ?: 'Sin nombre registrado' }}</div>
                                        <div class="mt-0.5 text-[11px] text-gray-400">{{ $contact->email }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded border border-white/10 bg-white/[0.03] px-2 py-0.5 text-[11px] text-gray-200">
                                            {{ $contact->company_or_band ?: 'Independiente' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-300">{{ $contact->role ?: 'Contacto' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-[11px] text-gray-200">{{ $sourceTypeLabels[$contact->source_type] ?? $contact->source_type }}</div>
                                        @if($contact->sourceAccount)
                                            <div class="text-[10px] text-gray-400 font-mono">{{ $contact->sourceAccount->email }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-[11px] text-gray-400">{{ $contact->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <form
                                            action="{{ route('admin.marketing.contacts.delete', $contact->id) }}"
                                            method="POST"
                                            class="inline"
                                            data-confirm="¿Eliminar este contacto de la lista?"
                                            data-confirm-title="Eliminar contacto"
                                            data-confirm-action="Eliminar"
                                            data-confirm-tone="danger"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 text-xs font-medium text-red-300 hover:bg-red-500/20">
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-400">
                                        No se encontraron contactos en la base de datos de difusión.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tarjetas de contactos en móvil (390 px) --}}
            <div class="space-y-3 md:hidden">
                @forelse($contacts as $contact)
                    <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-white truncate">{{ $contact->name ?: 'Sin nombre' }}</div>
                                <div class="text-xs text-gray-400 truncate">{{ $contact->email }}</div>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded border border-white/10 bg-white/[0.03] px-2 py-0.5 text-[10px] text-gray-300">
                                {{ $contact->company_or_band ?: 'Independiente' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-gray-400 border-t border-white/5 pt-2">
                            <span>{{ $contact->role ?: 'Contacto' }} · {{ $contact->created_at->format('d/m/Y') }}</span>
                            <form
                                action="{{ route('admin.marketing.contacts.delete', $contact->id) }}"
                                method="POST"
                                data-confirm="¿Eliminar este contacto de la lista?"
                                data-confirm-title="Eliminar contacto"
                                data-confirm-action="Eliminar"
                                data-confirm-tone="danger"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-300 hover:underline">Eliminar</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-white/10 bg-[#101012] p-6 text-center text-xs text-gray-400">
                        No se encontraron contactos.
                    </div>
                @endforelse
            </div>

            <div>{{ $contacts->links() }}</div>
        </section>

        {{-- ==================== PESTAÑA: REDACTAR Y ENVIAR CAMPAÑA ==================== --}}
        <section x-cloak x-show="activeTab === 'campaigns'">
            @if($accounts->count() === 0)
                <div class="rounded-xl border border-red-500/30 bg-red-500/10 p-6 text-center space-y-2">
                    <h2 class="font-display text-base uppercase tracking-wider text-red-200">No hay cuentas remitentes configuradas</h2>
                    <p class="text-xs text-red-200/80">Agrega al menos una cuenta IMAP/SMTP en la pestaña «Cuentas remitentes» antes de redactar y despachar una campaña.</p>
                    <div class="pt-2">
                        <button type="button" @click="activeTab = 'accounts'; showAddAccountModal = true" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white">
                            Configurar cuenta remitente
                        </button>
                    </div>
                </div>
            @else
                <form
                    action="{{ route('admin.marketing.campaigns.store') }}"
                    method="POST"
                    class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5"
                    data-confirm="¿Confirmas el envío de esta campaña a todos tus contactos? El proceso se ejecutará en segundo plano."
                    data-confirm-title="Confirmar envío de campaña"
                    data-confirm-action="Enviar"
                    data-confirm-tone="soft"
                >
                    @csrf
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-white/5 pb-3">
                        <div>
                            <h2 class="font-display text-base uppercase tracking-wider text-white">Composición de campaña de difusión</h2>
                            <p class="text-xs text-gray-400">El mensaje se encolará en segundo plano para todos los contactos activos del directorio de difusión.</p>
                        </div>
                        <span class="inline-flex items-center rounded-lg border border-white/10 bg-[#151518] px-3 py-1 text-xs text-gray-300">
                            Alcance actual: <strong class="ml-1 text-white">{{ $contacts->total() }} contactos</strong>
                        </span>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="camp-subject" class="mb-1.5 block text-xs font-medium text-gray-300">Asunto del correo *</label>
                            <input id="camp-subject" type="text" name="subject" value="{{ old('subject') }}" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]" placeholder="Ej: Novedades y lanzamientos de esta semana en Seven Rock Radio">
                            @error('subject')<p class="mt-1 text-xs text-red-300">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="camp-sender" class="mb-1.5 block text-xs font-medium text-gray-300">Cuenta remitente (SMTP) *</label>
                            <select id="camp-sender" name="sender_account_id" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->sender_name }} ({{ $acc->email }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-gray-400">Se utilizará el servidor SMTP configurado en esta cuenta.</p>
                        </div>

                        <div>
                            <label for="camp-template" class="mb-1.5 block text-xs font-medium text-gray-300">Diseño visual de plantilla *</label>
                            <select id="camp-template" name="template" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                                @foreach($templates as $key => $name)
                                    <option value="{{ $key }}" @selected(old('template') === $key)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-gray-400">Define la maquetación HTML que envolverá el mensaje.</p>
                        </div>

                        <div class="md:col-span-2">
                            <label for="camp-body" class="mb-1.5 block text-xs font-medium text-gray-300">Mensaje principal *</label>
                            <textarea id="camp-body" name="body_content" rows="9" required class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs leading-relaxed text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]" placeholder="Escribe aquí el contenido del comunicado...">{{ old('body_content') }}</textarea>
                            @error('body_content')<p class="mt-1 text-xs text-red-300">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="camp-btn-text" class="mb-1.5 block text-xs font-medium text-gray-300">Texto del botón de acción (opcional)</label>
                            <input id="camp-btn-text" type="text" name="button_text" value="{{ old('button_text') }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]" placeholder="Ej: Escuchar en directo">
                        </div>

                        <div>
                            <label for="camp-btn-url" class="mb-1.5 block text-xs font-medium text-gray-300">URL del botón de acción (opcional)</label>
                            <input id="camp-btn-url" type="url" name="button_url" value="{{ old('button_url') }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]" placeholder="https://sevenrockradio.com">
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-white/5 pt-4">
                        <p class="text-[11px] text-gray-400">
                            Al confirmar, se encolará el envío escalonado en lotes (con pausas de 3 a 5 s por destinatario) hacia los contactos activos.
                        </p>
                        <button type="submit" class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                            Despachar campaña en lotes
                        </button>
                    </div>
                </form>
            @endif
        </section>

        {{-- ==================== PESTAÑA: CUENTAS DE CORREO ==================== --}}
        <section x-cloak x-show="activeTab === 'accounts'" class="space-y-4">
            <div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/5 pb-3">
                    <div>
                        <h2 class="font-display text-base uppercase tracking-wider text-white">Cuentas remitentes y de sincronización</h2>
                        <p class="text-xs text-gray-400">Credenciales IMAP (lectura de contactos) y SMTP (despacho de campañas).</p>
                    </div>
                    <button type="button" @click="showAddAccountModal = true" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                        + Agregar cuenta de correo
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @forelse($accounts as $acc)
                        <div class="rounded-xl border border-white/10 bg-[#151518] p-4 flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="text-sm font-semibold text-white">{{ $acc->sender_name }}</div>
                                        <div class="mt-0.5 font-mono text-xs text-gray-400 break-all">{{ $acc->email }}</div>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-semibold {{ $acc->is_active ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-red-500/30 bg-red-500/10 text-red-300' }}">
                                        {{ $acc->is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </div>

                                <div class="mt-3 space-y-1 border-t border-white/5 pt-2.5 text-[11px] text-gray-300">
                                    <div><span class="text-gray-400">Entrada IMAP:</span> {{ $acc->imap_host }}:{{ $acc->imap_port }} ({{ strtoupper($acc->imap_encryption) }})</div>
                                    <div><span class="text-gray-400">Salida SMTP:</span> {{ $acc->smtp_host }}:{{ $acc->smtp_port }} ({{ strtoupper($acc->smtp_encryption) }})</div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 border-t border-white/5 pt-3">
                                <a href="{{ route('admin.marketing.accounts.test', $acc->id) }}" class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                    Probar conexión IMAP/SMTP
                                </a>
                                <button type="button" @click="editAccountData = {{ Js::from($acc) }}" class="inline-flex h-8 flex-1 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                    Editar
                                </button>
                                <form
                                    action="{{ route('admin.marketing.accounts.delete', $acc->id) }}"
                                    method="POST"
                                    class="flex-1"
                                    data-confirm="¿Eliminar esta cuenta de correo?"
                                    data-confirm-title="Eliminar cuenta de correo"
                                    data-confirm-action="Eliminar"
                                    data-confirm-tone="danger"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-red-500/30 bg-red-500/10 px-3 text-xs font-medium text-red-300 hover:bg-red-500/20">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full rounded-xl border border-white/5 bg-[#151518]/50 p-8 text-center text-xs text-gray-400">
                            No hay cuentas de correo registradas. Agrega una cuenta para habilitar la sincronización y el despacho de campañas.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- ==================== PESTAÑA: HISTORIAL DE CAMPAÑAS ==================== --}}
        <section x-cloak x-show="activeTab === 'history'" class="space-y-4">
            <div class="rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
                <div class="border-b border-white/5 px-4 py-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-display text-sm uppercase tracking-wider text-white">Historial de campañas de difusión</h2>
                        <p class="text-[11px] text-gray-400">El contador de destinatarios refleja los despachos realizados al servidor SMTP de salida.</p>
                    </div>
                </div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-white/10 bg-[#151518] text-[11px] font-semibold text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Asunto de la campaña</th>
                                <th class="px-4 py-3">Cuenta remitente</th>
                                <th class="px-4 py-3">Diseño aplicado</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Despachos SMTP</th>
                                <th class="px-4 py-3">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($campaigns as $camp)
                                <tr class="hover:bg-white/[0.02] align-middle">
                                    <td class="px-4 py-3 font-semibold text-white">{{ $camp->subject }}</td>
                                    <td class="px-4 py-3 font-mono text-[11px] text-gray-300">{{ $camp->senderAccount->email ?? 'Cuenta eliminada' }}</td>
                                    <td class="px-4 py-3 text-gray-300">{{ $templates[$camp->template] ?? $camp->template }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium
                                            @if($camp->status === 'sent') border-emerald-500/30 bg-emerald-500/10 text-emerald-300
                                            @elseif($camp->status === 'sending') border-sky-500/30 bg-sky-500/10 text-sky-300
                                            @elseif($camp->status === 'failed') border-red-500/30 bg-red-500/10 text-red-300
                                            @else border-white/10 bg-white/[0.04] text-gray-300 @endif">
                                            {{ $campaignStatusLabels[$camp->status] ?? $camp->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-200">
                                        <strong>{{ $camp->sent_contacts }}</strong> / {{ $camp->total_contacts }} despachados
                                    </td>
                                    <td class="px-4 py-3 text-[11px] text-gray-400">{{ $camp->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-400">
                                        Todavía no se han enviado campañas de difusión.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Móvil (390 px) --}}
                <div class="divide-y divide-white/5 md:hidden">
                    @forelse($campaigns as $camp)
                        <div class="p-4 space-y-2 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-semibold text-white">{{ $camp->subject }}</div>
                                <span class="shrink-0 inline-flex items-center rounded border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] text-gray-200">
                                    {{ $campaignStatusLabels[$camp->status] ?? $camp->status }}
                                </span>
                            </div>
                            <div class="text-[11px] text-gray-400">{{ $camp->senderAccount->email ?? 'Remitente no disponible' }}</div>
                            <div class="flex items-center justify-between text-[11px] text-gray-300">
                                <span>{{ $camp->sent_contacts }} / {{ $camp->total_contacts }} despachados</span>
                                <span class="text-gray-400">{{ $camp->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-gray-400">No hay campañas registradas.</div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- ==================== PESTAÑA: GUÍA Y DIAGNÓSTICO ==================== --}}
        <section x-cloak x-show="activeTab === 'guide'" class="space-y-4">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-3 text-xs text-gray-300 leading-relaxed">
                    <h2 class="font-display text-sm uppercase tracking-wider text-white border-b border-white/5 pb-2.5">Funcionamiento del módulo de difusión</h2>
                    <ul class="list-disc pl-4 space-y-2">
                        <li><strong class="text-white">Cuentas remitentes:</strong> Si utilizas Gmail, configura una contraseña de aplicación de 16 caracteres tanto en IMAP como en SMTP y verifica la conexión con «Probar conexión IMAP/SMTP».</li>
                        <li><strong class="text-white">Sincronización IMAP con IA:</strong> Analiza los correos más recientes de la carpeta indicada y extrae nombre, banda/empresa y rol de los remitentes que aún no estén en el directorio.</li>
                        <li><strong class="text-white">Envío escalonado en segundo plano:</strong> Las campañas se despachan mediante la cola <code class="text-gray-200">marketing</code> con pausas automáticas entre destinatarios para cuidar la reputación del remitente.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-3">
                    <div class="flex items-center justify-between border-b border-white/5 pb-2.5">
                        <h2 class="font-display text-sm uppercase tracking-wider text-white">Últimas líneas de registro (laravel.log)</h2>
                        <span class="text-[11px] text-gray-400">{{ count($logs) }} líneas</span>
                    </div>
                    <div class="max-h-64 overflow-y-auto rounded-lg border border-white/5 bg-black p-3 font-mono text-[10px] text-emerald-300 space-y-1">
                        @forelse($logs as $logLine)
                            <div class="whitespace-pre-wrap border-b border-white/[0.03] py-0.5">{{ $logLine }}</div>
                        @empty
                            <div class="text-gray-500">No hay registros recientes en laravel.log.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- ==================== MODALES ==================== --}}

        {{-- MODAL: AGREGAR CONTACTO MANUAL --}}
        <div x-cloak x-show="showAddContactModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-xl border border-white/10 bg-[#101012] p-5 shadow-2xl space-y-4" @click.away="showAddContactModal = false">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-display text-base uppercase tracking-wider text-white">Agregar contacto manual</h3>
                    <button type="button" class="text-xs text-gray-400 hover:text-white" @click="showAddContactModal = false">Cerrar</button>
                </div>
                <p class="text-xs text-gray-400">El contacto quedará activo en el directorio general de Campañas de difusión.</p>

                <form action="{{ route('admin.marketing.contacts.store') }}" method="POST" class="space-y-3.5">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Correo electrónico *</label>
                        <input type="email" name="email" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Nombre del contacto</label>
                        <input type="text" name="name" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Empresa o banda</label>
                        <input type="text" name="company_or_band" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Cargo o rol</label>
                        <input type="text" name="role" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
                    </div>
                    <div class="flex justify-end gap-2 border-t border-white/10 pt-3">
                        <button type="button" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300" @click="showAddContactModal = false">Cancelar</button>
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a]">Guardar contacto</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL: SINCRONIZAR DESDE GMAIL --}}
        <div x-cloak x-show="showScrapeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-xl border border-white/10 bg-[#101012] p-5 shadow-2xl space-y-4" @click.away="showScrapeModal = false">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-display text-base uppercase tracking-wider text-white">Sincronizar contactos desde IMAP con IA</h3>
                    <button type="button" class="text-xs text-gray-400 hover:text-white" @click="showScrapeModal = false">Cerrar</button>
                </div>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Esta tarea se ejecuta en segundo plano sobre la carpeta IMAP seleccionada, omite remitentes ya registrados y clasifica nombre, banda y cargo con Gemini IA.
                </p>

                <form action="{{ route('admin.marketing.contacts.scrape') }}" method="POST" class="space-y-3.5">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Cuenta de correo a escanear</label>
                        <select name="account_id" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->sender_name }} ({{ $acc->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Carpeta IMAP</label>
                        <input type="text" name="folder" value="INBOX" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white" placeholder="INBOX">
                        <p class="mt-1 text-[11px] text-gray-400">Usa <code class="text-gray-300">INBOX</code> para la bandeja de entrada.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Alcance máximo de correos a revisar</label>
                        <select name="limit" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                            <option value="50">50 correos más recientes</option>
                            <option value="100" selected>100 correos más recientes (recomendado)</option>
                            <option value="200">200 correos más recientes</option>
                            <option value="500">500 correos más recientes</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-white/10 pt-3">
                        <button type="button" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300" @click="showScrapeModal = false">Cancelar</button>
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a]">Encolar sincronización</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL: ESTADO Y CRON JOB DE COLA --}}
        <div x-cloak x-show="showCronModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
            <div class="w-full max-w-xl rounded-xl border border-white/10 bg-[#101012] p-5 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto" @click.away="showCronModal = false">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-display text-base uppercase tracking-wider text-white">Configuración de cola de difusión (Hostinger)</h3>
                    <button type="button" class="text-xs text-gray-400 hover:text-white" @click="showCronModal = false">Cerrar</button>
                </div>

                <div class="space-y-3 text-xs text-gray-300 leading-relaxed">
                    <p>El envío de campañas y la sincronización IMAP se procesan mediante la cola <code class="text-white">marketing</code>:</p>
                    <div class="rounded-lg border border-white/10 bg-black p-3 font-mono text-[11px] text-amber-300 break-all select-all">
                        /opt/alt/php84/usr/bin/php /home/u531780502/domains/sevenrockradio.com/sevenrockradio/artisan queue:work --queue=marketing --stop-when-empty --tries=3 --timeout=600
                    </div>
                    @if($failedJobsCount > 0)
                        <div class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-red-200">
                            <strong>Tareas fallidas registradas en el sistema:</strong> {{ $failedJobsCount }}.
                        </div>
                    @endif
                </div>

                <div class="flex justify-end border-t border-white/10 pt-3">
                    <button type="button" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-200" @click="showCronModal = false">Cerrar</button>
                </div>
            </div>
        </div>

        {{-- MODAL: AGREGAR CUENTA DE CORREO --}}
        <div x-cloak x-show="showAddAccountModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
            <div class="w-full max-w-xl rounded-xl border border-white/10 bg-[#101012] p-5 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto" @click.away="showAddAccountModal = false">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-display text-base uppercase tracking-wider text-white">Agregar cuenta de correo</h3>
                    <button type="button" class="text-xs text-gray-400 hover:text-white" @click="showAddAccountModal = false">Cerrar</button>
                </div>

                <form action="{{ route('admin.marketing.accounts.store') }}" method="POST" class="grid gap-3.5 md:grid-cols-2">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-300">Correo electrónico *</label>
                        <input type="email" name="email" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white" placeholder="prensa@sevenrockradio.com">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-300">Nombre visible del remitente *</label>
                        <input type="text" name="sender_name" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white" placeholder="Seven Rock Radio">
                    </div>

                    <div class="md:col-span-2 border-b border-white/5 pb-1 pt-1">
                        <h4 class="text-xs font-semibold text-[#c32720]">Servidor de entrada (IMAP)</h4>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Host IMAP *</label>
                        <input type="text" name="imap_host" value="imap.gmail.com" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-300">Puerto *</label>
                            <input type="number" name="imap_port" value="993" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-300">Cifrado</label>
                            <select name="imap_encryption" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                                <option value="ssl" selected>SSL</option>
                                <option value="tls">TLS</option>
                                <option value="none">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-300">Contraseña o clave de aplicación IMAP *</label>
                        <input type="password" name="imap_password" required autocomplete="new-password" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                    </div>

                    <div class="md:col-span-2 border-b border-white/5 pb-1 pt-1">
                        <h4 class="text-xs font-semibold text-[#c32720]">Servidor de salida (SMTP)</h4>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Host SMTP *</label>
                        <input type="text" name="smtp_host" value="smtp.gmail.com" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-300">Puerto *</label>
                            <input type="number" name="smtp_port" value="465" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-300">Cifrado</label>
                            <select name="smtp_encryption" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                                <option value="ssl" selected>SSL</option>
                                <option value="tls">TLS</option>
                                <option value="none">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-300">Contraseña o clave de aplicación SMTP *</label>
                        <input type="password" name="smtp_password" required autocomplete="new-password" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-2 border-t border-white/10 pt-3 mt-1">
                        <button type="button" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300" @click="showAddAccountModal = false">Cancelar</button>
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a]">Guardar cuenta</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL: EDITAR CUENTA DE CORREO --}}
        <div x-cloak x-show="editAccountData !== null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
            <div class="w-full max-w-xl rounded-xl border border-white/10 bg-[#101012] p-5 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto" @click.away="editAccountData = null">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-display text-base uppercase tracking-wider text-white">Editar cuenta de correo</h3>
                    <button type="button" class="text-xs text-gray-400 hover:text-white" @click="editAccountData = null">Cerrar</button>
                </div>

                <form :action="'{{ route('admin.marketing.accounts.update', ['id' => 'TEMP_ID']) }}'.replace('TEMP_ID', editAccountData ? editAccountData.id : '')" method="POST" class="grid gap-3.5 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-400">Correo electrónico (no modificable)</label>
                        <input type="email" disabled :value="editAccountData ? editAccountData.email : ''" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-400 opacity-70">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-300">Nombre visible del remitente *</label>
                        <input type="text" name="sender_name" required :value="editAccountData ? editAccountData.sender_name : ''" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                    </div>

                    <div class="md:col-span-2 border-b border-white/5 pb-1 pt-1">
                        <h4 class="text-xs font-semibold text-[#c32720]">Servidor de entrada (IMAP)</h4>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Host IMAP *</label>
                        <input type="text" name="imap_host" required :value="editAccountData ? editAccountData.imap_host : ''" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-300">Puerto *</label>
                            <input type="number" name="imap_port" required :value="editAccountData ? editAccountData.imap_port : ''" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-300">Cifrado</label>
                            <select name="imap_encryption" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                                <option value="ssl" :selected="editAccountData && editAccountData.imap_encryption == 'ssl'">SSL</option>
                                <option value="tls" :selected="editAccountData && editAccountData.imap_encryption == 'tls'">TLS</option>
                                <option value="none" :selected="editAccountData && editAccountData.imap_encryption == 'none'">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-300">Nueva contraseña IMAP (dejar vacío para conservar)</label>
                        <input type="password" name="imap_password" autocomplete="new-password" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                    </div>

                    <div class="md:col-span-2 border-b border-white/5 pb-1 pt-1">
                        <h4 class="text-xs font-semibold text-[#c32720]">Servidor de salida (SMTP)</h4>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-300">Host SMTP *</label>
                        <input type="text" name="smtp_host" required :value="editAccountData ? editAccountData.smtp_host : ''" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-300">Puerto *</label>
                            <input type="number" name="smtp_port" required :value="editAccountData ? editAccountData.smtp_port : ''" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-300">Cifrado</label>
                            <select name="smtp_encryption" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                                <option value="ssl" :selected="editAccountData && editAccountData.smtp_encryption == 'ssl'">SSL</option>
                                <option value="tls" :selected="editAccountData && editAccountData.smtp_encryption == 'tls'">TLS</option>
                                <option value="none" :selected="editAccountData && editAccountData.smtp_encryption == 'none'">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-300">Nueva contraseña SMTP (dejar vacío para conservar)</label>
                        <input type="password" name="smtp_password" autocomplete="new-password" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white">
                    </div>

                    <div class="md:col-span-2 pt-1">
                        <label class="inline-flex items-center gap-2 text-xs text-gray-200 cursor-pointer">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1" :checked="editAccountData && editAccountData.is_active" class="h-4 w-4 rounded border-white/20 bg-[#101012] text-[#c32720]">
                            <span>Cuenta activa para envíos y sincronización</span>
                        </label>
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-2 border-t border-white/10 pt-3 mt-1">
                        <button type="button" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300" @click="editAccountData = null">Cancelar</button>
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a]">Actualizar cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
