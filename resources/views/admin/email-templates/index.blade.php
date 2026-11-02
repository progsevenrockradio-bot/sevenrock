<x-layouts.admin :title="'Plantillas de correo - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php
        $templateCatalog = [
            'airplay_notice' => ['label' => 'Aviso de programación en antena (Airplay)', 'group' => 'Programación y radio'],
            'contact' => ['label' => 'Formulario público de contacto web', 'group' => 'General y sistema'],
            'contracts/sign-request' => ['label' => 'Solicitud de firma de contrato', 'group' => 'Contratos'],
            'contracts/signed' => ['label' => 'Confirmación de contrato firmado', 'group' => 'Contratos'],
            'direct' => ['label' => 'Correo directo del administrador', 'group' => 'Comunicación'],
            'marketing/direct' => ['label' => 'Campaña de difusión · Contacto directo', 'group' => 'Campañas de difusión'],
            'marketing/event' => ['label' => 'Campaña de difusión · Lanzamiento o evento', 'group' => 'Campañas de difusión'],
            'marketing/newsletter' => ['label' => 'Campaña de difusión · Boletín de noticias', 'group' => 'Campañas de difusión'],
            'marketing/offer' => ['label' => 'Campaña de difusión · Oferta especial', 'group' => 'Campañas de difusión'],
            'marketing/promo_service' => ['label' => 'Campaña de difusión · Promoción de servicio', 'group' => 'Campañas de difusión'],
            'media-kit' => ['label' => 'Envío de dossier comercial (Media Kit)', 'group' => 'Comunicación'],
            'moderation/decision' => ['label' => 'Resolución de moderación de contenido', 'group' => 'Moderación'],
            'moderation/notification' => ['label' => 'Aviso de moderación (HTML)', 'group' => 'Moderación'],
            'moderation/notification-text' => ['label' => 'Aviso de moderación (texto plano)', 'group' => 'Moderación'],
            'new-comment' => ['label' => 'Notificación de nuevo comentario en web', 'group' => 'Contenidos'],
            'new-release-published' => ['label' => 'Notificación de nuevo lanzamiento publicado', 'group' => 'Contenidos'],
            'outreach' => ['label' => 'Invitación de convocatoria a bandas (Outreach)', 'group' => 'Contactos e invitaciones'],
            'podcast-archive-uploaded' => ['label' => 'Confirmación de episodio alojado en Archive.org', 'group' => 'Emisiones y podcasts'],
            'podcast-editorial-review' => ['label' => 'Revisión editorial de episodio de podcast', 'group' => 'Emisiones y podcasts'],
            'podcast-radioboss-uploaded' => ['label' => 'Confirmación de entrega de audio a RadioBOSS', 'group' => 'Emisiones y podcasts'],
            'podcast-uploaded' => ['label' => 'Notificación general de subida de podcast', 'group' => 'Emisiones y podcasts'],
            'post-published' => ['label' => 'Notificación de noticia o artículo publicado', 'group' => 'Contenidos'],
            'producer-invitation' => ['label' => 'Invitación y código de acceso a productor', 'group' => 'Programación y radio'],
            'program-schedule' => ['label' => 'Envío de parrilla y horarios de programas', 'group' => 'Programación y radio'],
            'programs/uploaded' => ['label' => 'Confirmación de programa subido por conductor', 'group' => 'Programación y radio'],
            'submissions/published' => ['label' => 'Maqueta aprobada y publicada', 'group' => 'Buzón de maquetas'],
            'submissions/received' => ['label' => 'Acuse de recibo de maqueta enviada', 'group' => 'Buzón de maquetas'],
            'submissions/status' => ['label' => 'Actualización de estado de maqueta', 'group' => 'Buzón de maquetas'],
            'talents/approved' => ['label' => 'Cuenta de banda aprobada en el Muro del Rock', 'group' => 'Bandas del Muro'],
            'talents/content-approved' => ['label' => 'Material de banda aprobado en el Muro', 'group' => 'Bandas del Muro'],
            'talents/expired' => ['label' => 'Aviso de suscripción de banda vencida', 'group' => 'Bandas del Muro'],
            'talents/expiry_warning' => ['label' => 'Aviso previo de vencimiento de suscripción', 'group' => 'Bandas del Muro'],
            'talents/hidden_notice' => ['label' => 'Notificación de ficha ocultada en el Muro', 'group' => 'Bandas del Muro'],
            'talents/interaction' => ['label' => 'Aviso de interacción o contacto en el Muro', 'group' => 'Bandas del Muro'],
            'talents/material_deleted' => ['label' => 'Aviso de archivo eliminado en el Muro', 'group' => 'Bandas del Muro'],
            'talents/pending_approval' => ['label' => 'Solicitud de alta de banda pendiente de revisión', 'group' => 'Bandas del Muro'],
            'talents/promoter_code' => ['label' => 'Entrega de código de promotor para bandas', 'group' => 'Bandas del Muro'],
            'talents/prospect-info' => ['label' => 'Información de planes para bandas interesadas', 'group' => 'Bandas del Muro'],
            'talents/referral_reward' => ['label' => 'Recompensa por referido en el Muro del Rock', 'group' => 'Bandas del Muro'],
            'talents/renewal' => ['label' => 'Confirmación de renovación de suscripción', 'group' => 'Bandas del Muro'],
            'talents/welcome' => ['label' => 'Bienvenida de banda registrada en el Muro', 'group' => 'Bandas del Muro'],
            'test' => ['label' => 'Correo de diagnóstico SMTP del sistema', 'group' => 'General y sistema'],
            'test-layout' => ['label' => 'Plantilla base de diseño de prueba', 'group' => 'General y sistema'],
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-5 font-sans" x-data="{ searchQuery: '', groupFilter: '' }">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Comunicación · Correo transaccional y automático</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Plantillas de correo</h1>
                <p class="mt-1 max-w-3xl text-xs text-gray-400">
                    Catálogo de vistas Blade utilizadas por los envíos automáticos de la plataforma, identificadas con su nombre comprensible y su ruta técnica original.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.email-logs.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    <span>Historial de correos</span>
                </a>
                <a href="{{ route('admin.outreach.templates.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    <span>Plantillas de invitación (Outreach)</span>
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Sección visualmente separada: Diagnóstico de Servidor SMTP --}}
        <section aria-labelledby="smtp-test-heading" class="rounded-xl border border-white/10 bg-[#101012] p-4">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <div class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-amber-300">
                        <span>Herramienta de diagnóstico técnico (independiente de la edición de plantillas)</span>
                    </div>
                    <h2 id="smtp-test-heading" class="mt-0.5 font-display text-sm uppercase tracking-wider text-white">Prueba de conexión del servidor SMTP</h2>
                    <p class="mt-0.5 text-xs text-gray-400">
                        Envía un mensaje simple de diagnóstico (<code class="text-gray-300">TestEmail</code>) para comprobar que las credenciales de salida SMTP responden sin modificar ninguna plantilla.
                    </p>
                </div>
                <form action="{{ route('admin.email-templates.test') }}" method="POST" class="flex flex-wrap items-end gap-2.5">
                    @csrf
                    <div class="min-w-[250px] flex-1 sm:flex-initial">
                        <label for="smtp-test-email" class="mb-1 block text-xs font-medium text-gray-300">Correo destino de prueba</label>
                        <input
                            id="smtp-test-email"
                            type="email"
                            name="test_email"
                            required
                            placeholder="tu@correo.com"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        >
                    </div>
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/15 bg-white/[0.06] px-3.5 text-xs font-semibold text-white hover:bg-white/[0.12] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Enviar prueba SMTP
                    </button>
                </form>
            </div>
        </section>

        {{-- Buscador y filtro rápido del catálogo de plantillas --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_240px_auto] items-end">
                <div>
                    <label for="tpl-search" class="mb-1 block text-xs font-medium text-gray-300">Buscar por nombre comprensible o identificador técnico</label>
                    <input
                        id="tpl-search"
                        type="text"
                        x-model="searchQuery"
                        placeholder="Ej: suscripción, podcast, talents/approved, media-kit..."
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                </div>
                <div>
                    <label for="tpl-group" class="mb-1 block text-xs font-medium text-gray-300">Módulo o categoría</label>
                    <select
                        id="tpl-group"
                        x-model="groupFilter"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="">Todas las categorías ({{ count($templates) }})</option>
                        <option value="Bandas del Muro">Bandas del Muro</option>
                        <option value="Emisiones y podcasts">Emisiones y podcasts</option>
                        <option value="Programación y radio">Programación y radio</option>
                        <option value="Buzón de maquetas">Buzón de maquetas</option>
                        <option value="Campañas de difusión">Campañas de difusión</option>
                        <option value="Contactos e invitaciones">Contactos e invitaciones</option>
                        <option value="Contenidos">Contenidos</option>
                        <option value="Moderación">Moderación</option>
                        <option value="Contratos">Contratos</option>
                        <option value="Comunicación">Comunicación</option>
                        <option value="General y sistema">General y sistema</option>
                    </select>
                </div>
                <div>
                    <button
                        type="button"
                        x-show="searchQuery !== '' || groupFilter !== ''"
                        @click="searchQuery = ''; groupFilter = ''"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white"
                    >
                        Restablecer filtros
                    </button>
                </div>
            </div>
        </section>

        {{-- Tabla de plantillas en escritorio --}}
        <section class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-[#151518] text-[11px] font-semibold text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Función y nombre de la plantilla</th>
                            <th class="px-4 py-3">Identificador técnico y archivo</th>
                            <th class="px-4 py-3">Categoría</th>
                            <th class="px-4 py-3">Tamaño</th>
                            <th class="px-4 py-3">Última modificación</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($templates as $template)
                            @php
                                $normalizedKey = str_replace('\\', '/', $template['name']);
                                $meta = $templateCatalog[$normalizedKey] ?? [
                                    'label' => 'Plantilla de correo: ' . str_replace(['/', '_', '-'], [' · ', ' ', ' '], $normalizedKey),
                                    'group' => 'General y sistema',
                                ];
                                $searchIndex = mb_strtolower($meta['label'] . ' ' . $normalizedKey . ' ' . $template['path'] . ' ' . $meta['group']);
                            @endphp
                            <tr
                                x-show="(searchQuery === '' || {{ Js::from($searchIndex) }}.includes(searchQuery.toLowerCase())) && (groupFilter === '' || groupFilter === {{ Js::from($meta['group']) }})"
                                class="hover:bg-white/[0.02] align-middle"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-white">{{ $meta['label'] }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-mono text-[11px] text-gray-200">{{ $normalizedKey }}</div>
                                    <div class="font-mono text-[10px] text-gray-500">resources/views/emails/{{ str_replace('\\', '/', $template['path']) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md border border-white/10 bg-white/[0.03] px-2 py-0.5 text-[11px] text-gray-300">
                                        {{ $meta['group'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-400">
                                    {{ round($template['size'] / 1024, 2) }} KB
                                </td>
                                <td class="px-4 py-3 text-gray-400">
                                    {{ $template['last_modified']->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.email-templates.edit', $template['encoded_path']) }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                        Editar plantilla
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Tarjetas en móvil (390 px) --}}
        <section class="space-y-3 md:hidden">
            @foreach ($templates as $template)
                @php
                    $normalizedKey = str_replace('\\', '/', $template['name']);
                    $meta = $templateCatalog[$normalizedKey] ?? [
                        'label' => 'Plantilla: ' . $normalizedKey,
                        'group' => 'General y sistema',
                    ];
                    $searchIndex = mb_strtolower($meta['label'] . ' ' . $normalizedKey . ' ' . $template['path'] . ' ' . $meta['group']);
                @endphp
                <article
                    x-show="(searchQuery === '' || {{ Js::from($searchIndex) }}.includes(searchQuery.toLowerCase())) && (groupFilter === '' || groupFilter === {{ Js::from($meta['group']) }})"
                    class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-2.5"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-xs font-semibold text-white">{{ $meta['label'] }}</div>
                            <div class="mt-0.5 font-mono text-[11px] text-gray-400">{{ $normalizedKey }}</div>
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] text-gray-300">
                            {{ round($template['size'] / 1024, 1) }} KB
                        </span>
                    </div>
                    <div class="flex items-center justify-between border-t border-white/5 pt-2 text-[11px] text-gray-400">
                        <span>{{ $meta['group'] }}</span>
                        <a href="{{ route('admin.email-templates.edit', $template['encoded_path']) }}" class="inline-flex h-7 items-center rounded-lg bg-[#c32720] px-2.5 text-xs font-semibold text-white">
                            Editar
                        </a>
                    </div>
                </article>
            @endforeach
        </section>
    </div>
</x-layouts.admin>
