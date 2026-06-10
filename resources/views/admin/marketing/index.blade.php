<x-layouts.admin :title="'Email Marketing & Contactos - Seven Rock Radio'">
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {!! nl2br(e(session('status'))) !!}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 border border-[#7a2b2b] bg-[rgba(195,39,32,.15)] px-4 py-3 text-sm text-[#ff9e9e]">
            {!! nl2br(e(session('error'))) !!}
        </div>
    @endif

    <div 
        class="space-y-6" 
        x-data="{ 
            activeTab: '{{ request('tab', 'contacts') }}',
            showAddContactModal: false,
            showScrapeModal: false,
            showAddAccountModal: false,
            showCronModal: false,
            editAccountData: null
        }"
    >
        <!-- Cabecera del Módulo -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Email Marketing & Contactos</h1>
                    <p class="mt-3 text-sm leading-7 text-[#7b7b7b]">
                        Administra tu base de datos de remitentes, importa contactos desde tus bandejas de Gmail enriquecidos por Inteligencia Artificial y envía campañas de correo electrónico con plantillas personalizadas.
                    </p>
                </div>
                <div>
                    <a href="{{ route('admin.dashboard') }}" class="lucille-button">Volver al Dashboard</a>
                </div>
            </div>
        </section>

        <!-- Barra de Navegación de Pestañas -->
        <section class="border border-[#2b2b2b] bg-[rgba(10,10,11,.96)] px-4 py-4 shadow-[0_18px_40px_rgba(0,0,0,.35)]">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="lucille-button" :class="activeTab === 'contacts' ? 'lucille-button-solid' : ''" @click="activeTab = 'contacts'">
                    👥 Contactos ({{ $contacts->total() }})
                </button>
                <button type="button" class="lucille-button" :class="activeTab === 'campaigns' ? 'lucille-button-solid' : ''" @click="activeTab = 'campaigns'">
                    ✉️ Enviar Campaña
                </button>
                <button type="button" class="lucille-button" :class="activeTab === 'accounts' ? 'lucille-button-solid' : ''" @click="activeTab = 'accounts'">
                    ⚙️ Cuentas de Correo ({{ $accounts->count() }})
                </button>
                <button type="button" class="lucille-button" :class="activeTab === 'history' ? 'lucille-button-solid' : ''" @click="activeTab = 'history'">
                    📊 Historial de Envíos
                </button>
                <button type="button" class="lucille-button" :class="activeTab === 'guide' ? 'lucille-button-solid' : ''" @click="activeTab = 'guide'">
                    📖 Manual de Uso
                </button>
            </div>
        </section>

        <!-- ==================== PESTAÑA: CONTACTOS ==================== -->
        <section x-cloak x-show="activeTab === 'contacts'" class="space-y-6">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                
                <!-- Diagnóstico de Tareas de Cola -->
                @if($pendingJobsCount > 0)
                    <div class="mb-6 border border-[#c9912c] bg-[rgba(201,145,44,.05)] p-5 rounded shadow-lg space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <h4 class="font-bold text-[#ffd580] text-sm uppercase tracking-wider">Tareas de Marketing Pendientes en Cola ({{ $pendingJobsCount }})</h4>
                                <p class="text-xs text-[#dcdcdc] mt-1">
                                    Tienes procesos de importación o envíos de campañas esperando en segundo plano. Esto sucede porque el procesador automático (Cron Job) no está activo en Hostinger para la cola de <code>marketing</code>.
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-[rgba(201,145,44,.15)]">
                            <form action="{{ route('admin.marketing.run-worker') }}" method="POST" class="inline-block">
                                @csrf
                                <button type="submit" class="lucille-button-solid bg-[#c9912c] border-[#c9912c] hover:bg-[#b07d20] hover:border-[#b07d20] text-[#101012] font-bold text-xs uppercase tracking-wider py-1.5 px-3">
                                    ⚡ Procesar Cola Manualmente
                                </button>
                            </form>
                            <span class="text-xs text-[#7b7b7b]">o</span>
                            <button type="button" class="text-xs text-[#ffd580] underline hover:text-white" @click="showCronModal = true">
                                Configurar Cron Job automático en Hostinger
                            </button>
                        </div>
                    </div>
                @else
                    <!-- Enlace discreto a la guía si no hay tareas pendientes -->
                    <div class="flex justify-end mb-4">
                        <button type="button" class="text-xs text-[#7b7b7b] hover:text-[#dcdcdc] flex items-center gap-1" @click="showCronModal = true">
                            🔧 Configuración de Cron Job y Cola
                        </button>
                    </div>
                @endif

                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6 border-b border-[#2b2b2b] pb-4">
                    <!-- Buscador -->
                    <form action="{{ route('admin.marketing.index') }}" method="GET" class="flex items-center gap-2 w-full md:max-w-md">
                        <input type="hidden" name="tab" value="contacts">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por email, nombre, banda..." class="lucille-product-field w-full">
                        <button type="submit" class="lucille-button-solid">Buscar</button>
                        @if(request('search'))
                            <a href="{{ route('admin.marketing.index', ['tab' => 'contacts']) }}" class="lucille-button">Limpiar</a>
                        @endif
                    </form>

                    <!-- Acciones -->
                    <div class="flex flex-wrap gap-2">
                        @if($accounts->count() > 0)
                            <button type="button" class="lucille-button-solid bg-[#1e4d2b] border-[#1e4d2b]" @click="showScrapeModal = true">
                                🔄 Sincronizar desde Gmail
                            </button>
                        @else
                            <button type="button" class="lucille-button opacity-50 cursor-not-allowed" title="Agrega una cuenta primero">
                                🔄 Sincronizar desde Gmail
                            </button>
                        @endif
                        <button type="button" class="lucille-button-solid" @click="showAddContactModal = true">
                            ➕ Agregar Contacto
                        </button>
                    </div>
                </div>

                <!-- Tabla de Contactos -->
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm text-[#dcdcdc]">
                        <thead>
                            <tr class="border-b border-[#2b2b2b] bg-[rgba(0,0,0,.2)] text-xs uppercase tracking-wider text-[#7b7b7b]">
                                <th class="p-4">Contacto</th>
                                <th class="p-4">Empresa / Banda</th>
                                <th class="p-4">Cargo / Rol</th>
                                <th class="p-4">Origen</th>
                                <th class="p-4">Fecha Importado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#2b2b2b]">
                            @forelse($contacts as $contact)
                                <tr class="hover:bg-[rgba(255,255,255,.02)]">
                                    <td class="p-4">
                                        <div class="font-bold text-[#ffffff]">{{ $contact->name ?: 'Sin Nombre' }}</div>
                                        <div class="text-xs text-[#7b7b7b]">{{ $contact->email }}</div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-1 text-xs border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] text-[#e0e0e0]">
                                            {{ $contact->company_or_band ?: 'Independiente' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-[#e0e0e0]">
                                        {{ $contact->role ?: 'Contacto' }}
                                    </td>
                                    <td class="p-4">
                                        <div class="text-xs text-[#dcdcdc] font-mono">
                                            {{ $contact->source_type }}
                                        </div>
                                        @if($contact->sourceAccount)
                                            <div class="text-[10px] text-[#7b7b7b]">{{ $contact->sourceAccount->email }}</div>
                                        @endif
                                    </td>
                                    <td class="p-4 text-xs text-[#7b7b7b]">
                                        {{ $contact->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="p-4 text-right">
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
                                            <button type="submit" class="text-xs text-[#ff9e9e] hover:underline">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-[#7b7b7b]">
                                        No se encontraron contactos en tu base de datos.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="mt-6">
                    {{ $contacts->links() }}
                </div>
            </div>
        </section>

        <!-- ==================== PESTAÑA: CREAR CAMPAÑA ==================== -->
        <section x-cloak x-show="activeTab === 'campaigns'">
            @if($accounts->count() === 0)
                <div class="border border-[#7a2b2b] bg-[rgba(195,39,32,.15)] p-8 text-center text-[#ff9e9e]">
                    <h3 class="font-display text-xl uppercase tracking-wider mb-2">No tienes cuentas de correo configuradas</h3>
                    <p class="text-sm">Debes agregar al menos una cuenta de correo en la pestaña de "Cuentas de Correo" antes de poder enviar campañas.</p>
                </div>
            @else
                <form 
                    action="{{ route('admin.marketing.campaigns.store') }}" 
                    method="POST" 
                    class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 space-y-6"
                    data-confirm="¿Confirmas el envío de esta campaña a todos tus contactos? El proceso se ejecutará en segundo plano."
                    data-confirm-title="Confirmar envío de campaña"
                    data-confirm-action="Enviar"
                    data-confirm-tone="soft"
                >
                    @csrf
                    <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-4">Redactar Nueva Campaña</h2>
                    
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Asunto -->
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Asunto del Correo</label>
                            <input type="text" name="subject" value="{{ old('subject') }}" required class="lucille-product-field w-full" placeholder="ej. ¡Gran lanzamiento rockero esta semana en Seven Rock Radio!">
                            @error('subject')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                        </div>

                        <!-- Remitente -->
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cuenta Remitente</label>
                            <select name="sender_account_id" class="lucille-product-field lucille-select-field w-full">
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->sender_name }} ({{ $acc->email }})</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-[#7b7b7b]">Los correos se despacharán usando la configuración SMTP de esta cuenta.</p>
                        </div>

                        <!-- Plantilla -->
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Plantilla de Diseño</label>
                            <select name="template" class="lucille-product-field lucille-select-field w-full">
                                @foreach($templates as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-[#7b7b7b]">Elige la estructura visual de la campaña.</p>
                        </div>

                        <!-- Contenido -->
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Mensaje / Cuerpo del Correo</label>
                            <textarea name="body_content" rows="10" required class="lucille-product-field w-full" placeholder="Escribe el mensaje aquí. Puedes usar saltos de línea normales para estructurar los párrafos."></textarea>
                            @error('body_content')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                        </div>

                        <!-- Botón CTA (Llamado a la acción) -->
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Texto del Botón (Opcional)</label>
                            <input type="text" name="button_text" value="{{ old('button_text') }}" class="lucille-product-field w-full" placeholder="ej. Visitar Sitio Web">
                        </div>

                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Enlace / URL del Botón (Opcional)</label>
                            <input type="url" name="button_url" value="{{ old('button_url') }}" class="lucille-product-field w-full" placeholder="ej. https://sevenrockradio.com">
                        </div>
                    </div>

                    <div class="border-t border-[#2b2b2b] pt-6 flex justify-end">
                        <button type="submit" class="lucille-button-solid bg-[#c32720] border-[#c32720]">
                            🚀 Despachar Campaña en Lotes
                        </button>
                    </div>
                </form>
            @endif
        </section>

        <!-- ==================== PESTAÑA: CUENTAS DE CORREO ==================== -->
        <section x-cloak x-show="activeTab === 'accounts'" class="space-y-6">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <div class="flex items-center justify-between mb-6 border-b border-[#2b2b2b] pb-4">
                    <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Cuentas Configureras</h2>
                    <button type="button" class="lucille-button-solid" @click="showAddAccountModal = true">
                        ➕ Agregar Cuenta de Correo
                    </button>
                </div>

                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @forelse($accounts as $acc)
                        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.2)] p-6 rounded-lg space-y-4 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-display text-lg text-[#ffffff] uppercase tracking-wider">{{ $acc->sender_name }}</span>
                                    <span class="px-2 py-0.5 text-[10px] uppercase font-bold {{ $acc->is_active ? 'bg-[rgba(16,64,30,.2)] border border-[#1e4d2b] text-[#b8e6c3]' : 'bg-[rgba(195,39,32,.15)] border border-[#7a2b2b] text-[#ff9e9e]' }}">
                                        {{ $acc->is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </div>
                                <div class="text-sm text-[#7b7b7b] font-mono break-all">{{ $acc->email }}</div>

                                <div class="mt-4 space-y-2 border-t border-[#2b2b2b] pt-3 text-xs text-[#e0e0e0]">
                                    <div><strong>Host IMAP:</strong> {{ $acc->imap_host }}:{{ $acc->imap_port }} ({{ $acc->imap_encryption }})</div>
                                    <div><strong>Host SMTP:</strong> {{ $acc->smtp_host }}:{{ $acc->smtp_port }} ({{ $acc->smtp_encryption }})</div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 border-t border-[#2b2b2b] pt-4 mt-auto">
                                <a href="{{ route('admin.marketing.accounts.test', $acc->id) }}" class="lucille-button text-xs bg-[#1a2e3a] hover:bg-[#203a4b] w-full text-center">
                                    🔌 Probar Conexión
                                </a>
                                <button type="button" class="lucille-button text-xs flex-1" @click="editAccountData = {{ Js::from($acc) }}">
                                    Editar
                                </button>
                                <form 
                                    action="{{ route('admin.marketing.accounts.delete', $acc->id) }}" 
                                    method="POST" 
                                    class="inline flex-1"
                                    data-confirm="¿Eliminar esta cuenta de correo?"
                                    data-confirm-title="Eliminar cuenta de correo"
                                    data-confirm-action="Eliminar"
                                    data-confirm-tone="danger"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button text-xs text-[#ff9e9e] border-[#7a2b2b] w-full">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full border border-[#2b2b2b] p-8 text-center text-[#7b7b7b]">
                            No tienes ninguna cuenta de correo agregada. ¡Configura una para poder sincronizar contactos y enviar boletines!
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- ==================== PESTAÑA: HISTORIAL DE CAMPAÑAS ==================== -->
        <section x-cloak x-show="activeTab === 'history'">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-4 mb-6">Campañas Enviadas</h2>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm text-[#dcdcdc]">
                        <thead>
                            <tr class="border-b border-[#2b2b2b] bg-[rgba(0,0,0,.2)] text-xs uppercase tracking-wider text-[#7b7b7b]">
                                <th class="p-4">Campaña / Asunto</th>
                                <th class="p-4">Remitente</th>
                                <th class="p-4">Plantilla</th>
                                <th class="p-4">Estado</th>
                                <th class="p-4">Destinatarios</th>
                                <th class="p-4">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#2b2b2b]">
                            @forelse($campaigns as $camp)
                                <tr class="hover:bg-[rgba(255,255,255,.02)]">
                                    <td class="p-4">
                                        <div class="font-bold text-[#ffffff]">{{ $camp->subject }}</div>
                                    </td>
                                    <td class="p-4 text-xs font-mono text-[#e0e0e0]">
                                        {{ $camp->senderAccount->email ?? 'Remitente no disponible' }}
                                    </td>
                                    <td class="p-4 text-xs">
                                        {{ $templates[$camp->template] ?? $camp->template }}
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 text-xs font-bold uppercase rounded
                                            @if($camp->status === 'sent') bg-[rgba(16,64,30,.2)] border border-[#1e4d2b] text-[#b8e6c3]
                                            @elseif($camp->status === 'sending') bg-[rgba(30,58,138,.3)] border border-[#1d4ed8] text-[#93c5fd]
                                            @elseif($camp->status === 'failed') bg-[rgba(153,27,27,.2)] border border-[#991b1b] text-[#fca5a5]
                                            @else bg-[rgba(255,255,255,.05)] border border-[#2b2b2b] text-[#dcdcdc] @endif">
                                            {{ $camp->status }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-xs text-[#e0e0e0]">
                                        <strong>{{ $camp->sent_contacts }}</strong> / {{ $camp->total_contacts }} enviados
                                    </td>
                                    <td class="p-4 text-xs text-[#7b7b7b]">
                                        {{ $camp->created_at->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-[#7b7b7b]">
                                        No has enviado ninguna campaña promocional aún.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ==================== PESTAÑA: MANUAL DE USO ==================== -->
        <section x-cloak x-show="activeTab === 'guide'" class="space-y-6">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 space-y-8">
                <div>
                    <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-4 mb-4">📖 Manual de Uso del Módulo de Marketing</h2>
                    <p class="text-sm text-[#7b7b7b]">
                        Aprende a gestionar tus cuentas de correo, recolectar contactos mediante Inteligencia Artificial y enviar boletines informativos o campañas promocionales profesionales.
                    </p>
                </div>

                <!-- Sección 1: Cuentas de Correo -->
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-6 rounded space-y-4">
                    <h3 class="font-display text-lg uppercase tracking-wider text-[#ffd580] flex items-center gap-2">
                        ✉️ 1. Cuentas de Correo (Remitentes)
                    </h3>
                    <div class="text-xs text-[#dcdcdc] space-y-3 leading-relaxed">
                        <p>
                            Para poder recolectar contactos y enviar correos, primero debes configurar al menos una cuenta en la pestaña <strong>⚙️ Cuentas de Correo</strong>:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 mt-1">
                            <li>
                                <strong>Configuración de Entrada (IMAP)</strong>: Se utiliza para leer de forma segura tu bandeja de correo e importar nuevos contactos.
                            </li>
                            <li>
                                <strong>Configuración de Salida (SMTP)</strong>: Se utiliza para realizar los envíos físicos de tus boletines o campañas promocionales.
                            </li>
                            <li>
                                <strong class="text-[#ff9e9e]">Contraseña de Aplicación de Google (App Password)</strong>: Si usas Gmail, no debes poner tu contraseña normal de inicio de sesión. Tienes que generar una contraseña de aplicación de 16 caracteres en la configuración de seguridad de tu cuenta de Google (verificación en dos pasos) y colocarla en los campos de contraseña IMAP y SMTP.
                            </li>
                            <li>
                                <strong>Prueba de Conexión</strong>: Una vez registrada la cuenta, haz clic en el botón <strong class="text-white">🔌 Probar Conexión</strong>. El sistema verificará que la cuenta esté lista para recibir y enviar mensajes sin fallas.
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Sección 2: Base de Datos de Contactos e IA -->
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-6 rounded space-y-4">
                    <h3 class="font-display text-lg uppercase tracking-wider text-[#ffd580] flex items-center gap-2">
                        👥 2. Gestión de Contactos e Inteligencia Artificial
                    </h3>
                    <div class="text-xs text-[#dcdcdc] space-y-3 leading-relaxed">
                        <p>
                            Puedes alimentar tu lista de contactos de dos maneras:
                        </p>
                        <ol class="list-decimal pl-5 space-y-2">
                            <li>
                                <strong>Carga Manual</strong>: Usando el botón <strong class="text-white">➕ Agregar Contacto</strong> para registrar de forma inmediata a un destinatario con su correo, nombre y banda.
                            </li>
                            <li>
                                <strong>Sincronización Inteligente con IA (Recomendado)</strong>:
                                <ul class="list-disc pl-5 mt-1 space-y-1 text-[#7b7b7b]">
                                    <li>Haz clic en el botón <strong class="text-white">🔄 Sincronizar desde Gmail</strong>.</li>
                                    <li>Elige cuál de tus cuentas configuradas deseas analizar y la carpeta a leer (ej. <code>INBOX</code> para la bandeja de entrada o <code>[Gmail]/Papelera</code> para escanear correos borrados).</li>
                                    <li>El sistema analizará los correos de remitentes que no tengas guardados, extraerá el contenido y usará la <strong>IA de Google Gemini</strong> para identificar automáticamente el Nombre del remitente, el Nombre de la Banda de Rock o Empresa, y su Rol/Cargo (ej. Vocalista, Manager, Prensa).</li>
                                </ul>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Sección 3: Creación y Envío de Campañas -->
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-6 rounded space-y-4">
                    <h3 class="font-display text-lg uppercase tracking-wider text-[#ffd580] flex items-center gap-2">
                        ✉️ 3. Redactar y Enviar Campañas
                    </h3>
                    <div class="text-xs text-[#dcdcdc] space-y-3 leading-relaxed">
                        <p>
                            En la pestaña <strong>✉️ Enviar Campaña</strong> puedes redactar tus comunicados:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 mt-1">
                            <li>
                                <strong>Remitente Flexible</strong>: Si tienes varias cuentas configuradas (ej. prensa@, info@), puedes seleccionar cuál de ellas se usará como remitente para esa campaña específica.
                            </li>
                            <li>
                                <strong>Plantillas de Diseño Lucille</strong>: Selecciona el estilo visual que mejor se adapte a tu mensaje:
                                <ul class="list-disc pl-5 mt-1 space-y-1 text-[#7b7b7b]">
                                    <li><strong class="text-[#e0e0e0]">Servicio (Dark Rock)</strong>: Diseño premium de fondo oscuro con detalles en verde bosque, ideal para destacar tu marca o servicios de radio.</li>
                                    <li><strong class="text-[#e0e0e0]">Boletín de Noticias (Newsletter)</strong>: Formato editorial limpio de estilo prensa para resúmenes de noticias y actualizaciones.</li>
                                    <li><strong class="text-[#e0e0e0]">Oferta Especial</strong>: Caja destacada y visual para anunciar promociones, descuentos o patrocinios.</li>
                                    <li><strong class="text-[#e0e0e0]">Contacto Directo</strong>: Simula un correo en texto plano escrito a mano para lograr máxima cercanía y confianza con el receptor.</li>
                                    <li><strong class="text-[#e0e0e0]">Lanzamiento o Evento</strong>: Plantilla muy vistosa y rockera, ideal para estrenos de discos o conciertos.</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Llamado a la Acción (Botón CTA)</strong>: Puedes activar un botón en la parte inferior del correo ingresando el texto personalizado (ej. "Escuchar en Vivo") y la dirección web de destino.
                            </li>
                            <li>
                                <strong>Envío Asíncrono Seguro</strong>: Las campañas se envían en segundo plano. El sistema procesa los envíos incorporando pausas automáticas de 3 a 5 segundos entre cada destinatario. Esto previene que los servidores de correo cataloguen tu dominio o cuenta de Gmail como emisores de spam.
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Sección 4: Historial de Envíos -->
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-6 rounded space-y-4">
                    <h3 class="font-display text-lg uppercase tracking-wider text-[#ffd580] flex items-center gap-2">
                        📊 4. Seguimiento e Historial
                    </h3>
                    <div class="text-xs text-[#dcdcdc] leading-relaxed">
                        <p>
                            En la pestaña <strong>📊 Historial de Envíos</strong> puedes dar seguimiento en tiempo real al estado de tus campañas: sabrás con precisión si están en proceso de despacho, finalizadas o si hubo algún error de conexión durante el envío masivo, detallando el número total de correos que se enviaron con éxito.
                        </p>
                    </div>
                </div>

                <!-- Sección 5: Logs de Diagnóstico -->
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.2)] p-6 rounded space-y-4">
                    <h3 class="font-display text-lg uppercase tracking-wider text-[#dcdcdc] flex items-center gap-2">
                        📋 5. Registro de Actividad Reciente (Logs)
                    </h3>
                    <div class="text-xs text-[#dcdcdc] space-y-3 leading-relaxed">
                        <p>
                            A continuación se muestran las últimas 30 líneas del registro de actividad (logs) del sistema. Úsalas para verificar si la sincronización de contactos o envíos tuvieron éxito o si se produjo algún error con Gmail o la API de Gemini:
                        </p>
                        <div class="p-4 bg-black border border-[#2b2b2b] rounded font-mono text-[10px] text-[#b8e6c3] overflow-x-auto max-h-72 overflow-y-auto space-y-1 select-all">
                            @forelse($logs as $logLine)
                                <div class="whitespace-pre-wrap leading-normal py-0.5 border-b border-[rgba(255,255,255,.03)] hover:bg-[rgba(255,255,255,.02)]">{{ $logLine }}</div>
                            @empty
                                <div class="text-[#7b7b7b] italic">No hay registros de actividad recientes en laravel.log.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== MODALES ==================== -->

        <!-- MODAL: AGREGAR CONTACTO -->
        <div x-cloak x-show="showAddContactModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div class="border border-[#2b2b2b] bg-[#101012] p-8 max-w-md w-full rounded-lg shadow-2xl space-y-6" @click.away="showAddContactModal = false">
                <h3 class="font-display text-xl uppercase tracking-wider text-[#dcdcdc]">Agregar Contacto Manual</h3>
                
                <form action="{{ route('admin.marketing.contacts.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-wider text-[#7b7b7b]">Correo Electrónico *</label>
                        <input type="email" name="email" required class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-wider text-[#7b7b7b]">Nombre del Contacto</label>
                        <input type="text" name="name" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-wider text-[#7b7b7b]">Empresa o Banda</label>
                        <input type="text" name="company_or_band" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-wider text-[#7b7b7b]">Cargo o Rol</label>
                        <input type="text" name="role" class="lucille-product-field w-full">
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-[#2b2b2b]">
                        <button type="button" class="lucille-button" @click="showAddContactModal = false">Cancelar</button>
                        <button type="submit" class="lucille-button-solid">Guardar Contacto</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: SINCRONIZAR DESDE GMAIL -->
        <div x-cloak x-show="showScrapeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div class="border border-[#2b2b2b] bg-[#101012] p-8 max-w-md w-full rounded-lg shadow-2xl space-y-6" @click.away="showScrapeModal = false">
                <h3 class="font-display text-xl uppercase tracking-wider text-[#dcdcdc]">Sincronizar Contactos con IA</h3>
                <p class="text-xs text-[#7b7b7b]">Esta acción leerá la bandeja IMAP seleccionada, descubrirá remitentes que no tengas en tu base de datos y usará la API de Gemini para clasificar y extraer sus cargos y bandas de rock.</p>

                <form action="{{ route('admin.marketing.contacts.scrape') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-wider text-[#7b7b7b]">Elegir Cuenta de Correo</label>
                        <select name="account_id" class="lucille-product-field lucille-select-field w-full">
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->sender_name }} ({{ $acc->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-wider text-[#7b7b7b]">Carpeta a Escanear</label>
                        <input type="text" name="folder" value="INBOX" required class="lucille-product-field w-full" placeholder="INBOX, [Gmail]/Papelera, Trash...">
                        <p class="mt-1 text-[10px] text-[#7b7b7b]">Escribe "INBOX" para bandeja de entrada, o "[Gmail]/Papelera" para la papelera de Gmail.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-wider text-[#7b7b7b]">Límite de Correos a Analizar</label>
                        <select name="limit" class="lucille-product-field lucille-select-field w-full">
                            <option value="50">50 correos más recientes</option>
                            <option value="100" selected>100 correos más recientes (Recomendado)</option>
                            <option value="200">200 correos más recientes</option>
                            <option value="500">500 correos más recientes (Lento)</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-[#2b2b2b]">
                        <button type="button" class="lucille-button" @click="showScrapeModal = false">Cancelar</button>
                        <button type="submit" class="lucille-button-solid bg-[#1e4d2b] border-[#1e4d2b]">Iniciar Importación IA</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: GUÍA DE CONFIGURACIÓN CRON JOB -->
        <div x-cloak x-show="showCronModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div class="border border-[#2b2b2b] bg-[#101012] p-8 max-w-2xl w-full rounded-lg shadow-2xl space-y-6 overflow-y-auto max-h-[90vh]" @click.away="showCronModal = false">
                <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-3">
                    <h3 class="font-display text-xl uppercase tracking-wider text-[#dcdcdc] flex items-center gap-2">
                        🔧 Guía: Automatización de Cola en Hostinger
                    </h3>
                    <button type="button" class="text-xs text-[#7b7b7b] hover:text-white" @click="showCronModal = false">✕ Cerrar</button>
                </div>

                <div class="space-y-4 text-xs text-[#dcdcdc] leading-relaxed">
                    <p>
                        Para que las tareas en segundo plano (como el scraping de correos con IA y el envío de campañas de email) funcionen automáticamente en piloto automático, debes configurar una <strong>Tarea Programada (Cron Job)</strong> en el panel de Hostinger.
                    </p>

                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.3)] p-4 space-y-2 rounded">
                        <h4 class="font-bold text-[#c32720] uppercase tracking-wider">Configurar el Cron Job para la cola de Marketing</h4>
                        <p>
                            En tu panel de Hostinger (sección de Tareas Programadas / Cron Jobs), crea una nueva tarea programada con los siguientes valores:
                        </p>
                        <ul class="list-disc pl-4 space-y-1 mt-1 text-[#7b7b7b]">
                            <li><strong>Tipo:</strong> Comando personalizado / Custom</li>
                            <li><strong>Intervalo / Frecuencia:</strong> Cada 1 minuto (o cada 5 minutos) <code>* * * * *</code></li>
                            <li><strong>Comando:</strong> Copia y pega la siguiente línea exacta:</li>
                        </ul>
                        <div class="mt-3 p-3 bg-black border border-[#2b2b2b] rounded font-mono text-[10px] text-[#ffd580] select-all break-all leading-normal">
                            /opt/alt/php84/usr/bin/php /home/u531780502/domains/sevenrockradio.com/public_html/artisan queue:work --queue=marketing --stop-when-empty --tries=3 --timeout=600 &gt;&gt; /dev/null 2&gt;&amp;1
                        </div>
                        <p class="text-[10px] text-[#7b7b7b] mt-1">
                            *Nota: Usamos <code>--stop-when-empty</code> y un timeout alto de 10 minutos (<code>--timeout=600</code>) porque el scraping con Gemini tarda unos segundos por correo. De esta forma, el worker se apagará limpiamente al terminar y no consumirá recursos extra.
                        </p>
                    </div>

                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.3)] p-4 space-y-2 rounded">
                        <h4 class="font-bold text-[#ffd580] uppercase tracking-wider">¿Por qué es necesario esto?</h4>
                        <p>
                            Si no configuras esta tarea programada, las importaciones e emails de marketing se quedarán en la base de datos de manera indefinida. Para procesarlas, tendrás que usar el botón de <strong>⚡ Procesar Cola Manualmente</strong> que se muestra cuando hay tareas pendientes.
                        </p>
                    </div>

                    @if($failedJobsCount > 0)
                        <div class="border border-[#7a2b2b] bg-[rgba(195,39,32,.08)] p-4 space-y-2 rounded">
                            <h4 class="font-bold text-[#ff9e9e] uppercase tracking-wider">⚠️ Tareas Fallidas Detectadas en el Sistema ({{ $failedJobsCount }})</h4>
                            <p>
                                Hay tareas que han fallado en su ejecución (probablemente por timeouts previos o credenciales SMTP/IMAP inválidas). 
                                Puedes limpiarlas o reintentarlas ejecutando comandos artisan desde la terminal si tienes acceso SSH.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-4 border-t border-[#2b2b2b]">
                    <button type="button" class="lucille-button" @click="showCronModal = false">Entendido, cerrar</button>
                </div>
            </div>
        </div>

        <!-- MODAL: AGREGAR CUENTA DE CORREO -->
        <div x-cloak x-show="showAddAccountModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div class="border border-[#2b2b2b] bg-[#101012] p-8 max-w-xl w-full rounded-lg shadow-2xl space-y-6 overflow-y-auto max-h-[90vh]" @click.away="showAddAccountModal = false">
                <h3 class="font-display text-xl uppercase tracking-wider text-[#dcdcdc] border-b border-[#2b2b2b] pb-3">Agregar Nueva Cuenta de Correo</h3>

                <form action="{{ route('admin.marketing.accounts.store') }}" method="POST" class="grid gap-4 md:grid-cols-2">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Correo Electrónico</label>
                        <input type="email" name="email" required class="lucille-product-field w-full" placeholder="ej. press.sevenrockradio@gmail.com">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Nombre del Remitente</label>
                        <input type="text" name="sender_name" required class="lucille-product-field w-full" placeholder="ej. Seven Rock Press">
                    </div>

                    <!-- Configuración IMAP -->
                    <div class="md:col-span-2 mt-2 border-b border-[#2b2b2b] pb-1">
                        <h4 class="text-xs uppercase font-bold text-[#c32720]">Configuración Entrada (IMAP)</h4>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Servidor IMAP</label>
                        <input type="text" name="imap_host" value="imap.gmail.com" required class="lucille-product-field w-full">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Puerto</label>
                            <input type="number" name="imap_port" value="993" required class="lucille-product-field w-full">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Cifrado</label>
                            <select name="imap_encryption" class="lucille-product-field lucille-select-field w-full">
                                <option value="ssl" selected>SSL</option>
                                <option value="tls">TLS</option>
                                <option value="none">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Contraseña de Aplicación IMAP</label>
                        <input type="password" name="imap_password" required autocomplete="new-password" class="lucille-product-field w-full" placeholder="Contraseña de aplicación de 16 caracteres de Google">
                    </div>

                    <!-- Configuración SMTP -->
                    <div class="md:col-span-2 mt-2 border-b border-[#2b2b2b] pb-1">
                        <h4 class="text-xs uppercase font-bold text-[#c32720]">Configuración Salida (SMTP)</h4>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Servidor SMTP</label>
                        <input type="text" name="smtp_host" value="smtp.gmail.com" required class="lucille-product-field w-full">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Puerto</label>
                            <input type="number" name="smtp_port" value="465" required class="lucille-product-field w-full">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Cifrado</label>
                            <select name="smtp_encryption" class="lucille-product-field lucille-select-field w-full">
                                <option value="ssl" selected>SSL</option>
                                <option value="tls">TLS</option>
                                <option value="none">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Contraseña de Aplicación SMTP</label>
                        <input type="password" name="smtp_password" required autocomplete="new-password" class="lucille-product-field w-full" placeholder="Normalmente la misma contraseña de aplicación">
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-3 pt-4 border-t border-[#2b2b2b] mt-4">
                        <button type="button" class="lucille-button" @click="showAddAccountModal = false">Cancelar</button>
                        <button type="submit" class="lucille-button-solid">Guardar Cuenta</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: EDITAR CUENTA DE CORREO -->
        <div x-cloak x-show="editAccountData !== null" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div class="border border-[#2b2b2b] bg-[#101012] p-8 max-w-xl w-full rounded-lg shadow-2xl space-y-6 overflow-y-auto max-h-[90vh]" @click.away="editAccountData = null">
                <h3 class="font-display text-xl uppercase tracking-wider text-[#dcdcdc] border-b border-[#2b2b2b] pb-3">Editar Cuenta de Correo</h3>

                <form :action="'{{ route('admin.marketing.accounts.update', ['id' => 'TEMP_ID']) }}'.replace('TEMP_ID', editAccountData ? editAccountData.id : '')" method="POST" class="grid gap-4 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Correo Electrónico (No modificable)</label>
                        <input type="email" disabled :value="editAccountData ? editAccountData.email : ''" class="lucille-product-field w-full opacity-60">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Nombre del Remitente</label>
                        <input type="text" name="sender_name" required :value="editAccountData ? editAccountData.sender_name : ''" class="lucille-product-field w-full">
                    </div>

                    <!-- Configuración IMAP -->
                    <div class="md:col-span-2 mt-2 border-b border-[#2b2b2b] pb-1">
                        <h4 class="text-xs uppercase font-bold text-[#c32720]">Configuración Entrada (IMAP)</h4>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Servidor IMAP</label>
                        <input type="text" name="imap_host" required :value="editAccountData ? editAccountData.imap_host : ''" class="lucille-product-field w-full">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Puerto</label>
                            <input type="number" name="imap_port" required :value="editAccountData ? editAccountData.imap_port : ''" class="lucille-product-field w-full">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Cifrado</label>
                            <select name="imap_encryption" class="lucille-product-field lucille-select-field w-full">
                                <option value="ssl" :selected="editAccountData && editAccountData.imap_encryption == 'ssl'">SSL</option>
                                <option value="tls" :selected="editAccountData && editAccountData.imap_encryption == 'tls'">TLS</option>
                                <option value="none" :selected="editAccountData && editAccountData.imap_encryption == 'none'">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Nueva Contraseña de Aplicación IMAP (Dejar vacío para no cambiar)</label>
                        <input type="password" name="imap_password" autocomplete="new-password" class="lucille-product-field w-full" placeholder="Omitir si no deseas cambiarla">
                    </div>

                    <!-- Configuración SMTP -->
                    <div class="md:col-span-2 mt-2 border-b border-[#2b2b2b] pb-1">
                        <h4 class="text-xs uppercase font-bold text-[#c32720]">Configuración Salida (SMTP)</h4>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Servidor SMTP</label>
                        <input type="text" name="smtp_host" required :value="editAccountData ? editAccountData.smtp_host : ''" class="lucille-product-field w-full">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Puerto</label>
                            <input type="number" name="smtp_port" required :value="editAccountData ? editAccountData.smtp_port : ''" class="lucille-product-field w-full">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Cifrado</label>
                            <select name="smtp_encryption" class="lucille-product-field lucille-select-field w-full">
                                <option value="ssl" :selected="editAccountData && editAccountData.smtp_encryption == 'ssl'">SSL</option>
                                <option value="tls" :selected="editAccountData && editAccountData.smtp_encryption == 'tls'">TLS</option>
                                <option value="none" :selected="editAccountData && editAccountData.smtp_encryption == 'none'">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-[#7b7b7b]">Nueva Contraseña de Aplicación SMTP (Dejar vacío para no cambiar)</label>
                        <input type="password" name="smtp_password" autocomplete="new-password" class="lucille-product-field w-full" placeholder="Omitir si no deseas cambiarla">
                    </div>

                    <div class="md:col-span-2 mt-2 border-t border-[#2b2b2b] pt-3 flex items-center gap-2">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" :checked="editAccountData && editAccountData.is_active" class="rounded border-[#2b2b2b] bg-[#101012] text-[#c32720]">
                        <label for="edit_is_active" class="text-xs uppercase tracking-wider text-[#e0e0e0]">Cuenta activa para envíos y sincronización</label>
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-3 pt-4 border-t border-[#2b2b2b] mt-4">
                        <button type="button" class="lucille-button" @click="editAccountData = null">Cancelar</button>
                        <button type="submit" class="lucille-button-solid">Actualizar Cuenta</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-layouts.admin>
