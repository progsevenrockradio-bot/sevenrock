<x-layouts.admin :title="'Editar plantilla de correo - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php
        $normalizedKey = str_replace(['\\', '.blade.php'], ['/', ''], $relativePath);
        $templateLabels = [
            'airplay_notice' => 'Aviso de programación en antena (Airplay)',
            'contact' => 'Formulario público de contacto web',
            'contracts/sign-request' => 'Solicitud de firma de contrato',
            'contracts/signed' => 'Confirmación de contrato firmado',
            'direct' => 'Correo directo del administrador',
            'marketing/direct' => 'Campaña de difusión · Contacto directo',
            'marketing/event' => 'Campaña de difusión · Lanzamiento o evento',
            'marketing/newsletter' => 'Campaña de difusión · Boletín de noticias',
            'marketing/offer' => 'Campaña de difusión · Oferta especial',
            'marketing/promo_service' => 'Campaña de difusión · Promoción de servicio',
            'media-kit' => 'Envío de dossier comercial (Media Kit)',
            'moderation/decision' => 'Resolución de moderación de contenido',
            'moderation/notification' => 'Aviso de moderación (HTML)',
            'moderation/notification-text' => 'Aviso de moderación (texto plano)',
            'new-comment' => 'Notificación de nuevo comentario en web',
            'new-release-published' => 'Notificación de nuevo lanzamiento publicado',
            'outreach' => 'Invitación de convocatoria a bandas (Outreach)',
            'podcast-archive-uploaded' => 'Confirmación de episodio alojado en Archive.org',
            'podcast-editorial-review' => 'Revisión editorial de episodio de podcast',
            'podcast-radioboss-uploaded' => 'Confirmación de entrega de audio a RadioBOSS',
            'podcast-uploaded' => 'Notificación general de subida de podcast',
            'post-published' => 'Notificación de noticia o artículo publicado',
            'producer-invitation' => 'Invitación y código de acceso a productor',
            'program-schedule' => 'Envío de parrilla y horarios de programas',
            'programs/uploaded' => 'Confirmación de programa subido por conductor',
            'submissions/published' => 'Maqueta aprobada y publicada',
            'submissions/received' => 'Acuse de recibo de maqueta enviada',
            'submissions/status' => 'Actualización de estado de maqueta',
            'talents/approved' => 'Cuenta de banda aprobada en el Muro del Rock',
            'talents/content-approved' => 'Material de banda aprobado en el Muro',
            'talents/expired' => 'Aviso de suscripción de banda vencida',
            'talents/expiry_warning' => 'Aviso previo de vencimiento de suscripción',
            'talents/hidden_notice' => 'Notificación de ficha ocultada en el Muro',
            'talents/interaction' => 'Aviso de interacción o contacto en el Muro',
            'talents/material_deleted' => 'Aviso de archivo eliminado en el Muro',
            'talents/pending_approval' => 'Solicitud de alta de banda pendiente de revisión',
            'talents/promoter_code' => 'Entrega de código de promotor para bandas',
            'talents/prospect-info' => 'Información de planes para bandas interesadas',
            'talents/referral_reward' => 'Recompensa por referido en el Muro del Rock',
            'talents/renewal' => 'Confirmación de renovación de suscripción',
            'talents/welcome' => 'Bienvenida de banda registrada en el Muro',
            'test' => 'Correo de diagnóstico SMTP del sistema',
            'test-layout' => 'Plantilla base de diseño de prueba',
        ];
        $readableTitle = $templateLabels[$normalizedKey] ?? ('Plantilla: ' . $normalizedKey);
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Plantillas de correo · Edición de código Blade</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">{{ $readableTitle }}</h1>
                <p class="mt-1 font-mono text-xs text-gray-400">Identificador técnico: <span class="text-gray-200">{{ str_replace('\\', '/', $relativePath) }}</span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.email-templates.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    Volver a plantillas
                </a>
                <button form="template-edit-form" type="submit" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a]">
                    Guardar cambios
                </button>
            </div>
        </header>

        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-100">
            <strong class="font-semibold text-amber-300">Precaución técnica:</strong>
            estás editando una vista Blade del sistema. Conserva intactas las directivas (<code class="text-[11px]">@if</code>, <code class="text-[11px]">@foreach</code>) y las variables PHP (<code class="text-[11px]">&#123;&#123; $variable &#125;&#125;</code>) que recibe el correo para evitar errores durante el despacho.
        </div>

        <form id="template-edit-form" action="{{ route('admin.email-templates.update', $encodedPath) }}" method="POST" class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="content" class="mb-1.5 block text-xs font-medium text-gray-300">Código fuente de la plantilla (Blade / HTML)</label>
                <textarea
                    id="content"
                    name="content"
                    rows="26"
                    class="w-full rounded-lg border border-white/10 bg-[#0b0b0d] p-4 font-mono text-xs leading-relaxed text-gray-100 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    spellcheck="false"
                    required
                >{{ old('content', $content) }}</textarea>
                @error('content')
                    <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-white/5 pt-3">
                <a href="{{ route('admin.email-templates.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white">
                    Cancelar
                </a>
                <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a]">
                    Guardar plantilla
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
