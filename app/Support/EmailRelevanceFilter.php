<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ThemeSetting;
use Illuminate\Support\Str;

class EmailRelevanceFilter
{
    /**
     * Palabras clave por defecto que denotan relevancia musical o informativa.
     */
    public const DEFAULT_KEYWORDS = [
        'single',
        'álbum',
        'album',
        'nuevo disco',
        'nuevo tema',
        'ep',
        'out now',
        'release',
        'estrena',
        'premiere',
        'gira',
        'tour',
        'concierto',
        'festival',
        'videoclip',
        'music video',
        'anuncia',
        'fallece',
        'muere',
        'muerto',
        'adiós',
        'se separa',
        'regresa',
    ];

    /**
     * Dominios de sellos discográficos, agencias y plataformas de promoción musical reconocidas.
     */
    public const DEFAULT_PROMO_DOMAINS = [
        'metalblade.com',
        'nuclearblast.com',
        'nuclearblast.de',
        'centurymedia.com',
        'napalmrecords.com',
        'insideoutmusic.com',
        'afm-records.de',
        'earache.com',
        'season-of-mist.com',
        'mascotlabelgroup.com',
        'frontiers.it',
        'relapse.com',
        'rocksolidadvertising.com',
        'metaldevastationpr.com',
        'grandsounds.net',
        'ashermedia.com',
        'hauruck.org',
        'haulix.com',
        'brevo.com',
        'mailchimp.com',
        'mailchimpapp.com',
    ];

    /**
     * Patrones negativos que descartan el correo antes de gastar en IA.
     */
    public const JUNK_PATTERNS = [
        'factura',
        'invoice',
        'recibo de pago',
        'aviso de cobro',
        'comprobante',
        'payment receipt',
        'billing notification',
        'orden de compra',
        'cuenta suspendida',
        'suscripción confirmada',
        'subscription confirmed',
        'cancel your subscription',
        'unsubscribe here',
        'encuesta',
        'survey',
        'danos tu opinión',
        'feedback request',
        'security alert',
        'alerta de seguridad',
        'código de verificación',
        'verification code',
        'password reset',
        'restablecer contraseña',
        'confirm your email',
        'informe de rendimiento',
        'reporte de rendimiento',
        'resumen del mes',
        'resumen mensual',
        'business profile',
        'metricool',
        'newsletter',
        'boletín mensual',
        'analytics report',
        'monthly digest',
        'monthly report',
    ];

    /**
     * Evalúa si un correo entrante merece ser analizado por un modelo de IA.
     *
     * @param string $senderEmail
     * @param string $subject
     * @param string $body
     * @param ThemeSetting|null $settings
     * @return array{pass: bool, reason: string}
     */
    public function shouldProcess(string $senderEmail, string $subject, string $body, ?ThemeSetting $settings = null): array
    {
        $settings = $settings ?: ThemeSetting::current();

        // 0. Si el pre-filtro está desactivado en configuración, todo pasa
        if ($settings && $settings->ai_prefilter_enabled === false) {
            return ['pass' => true, 'reason' => 'prefilter_disabled'];
        }

        $senderLower = mb_strtolower(trim($senderEmail));
        $subjectLower = mb_strtolower(trim($subject));
        $bodyPlain = mb_strtolower(strip_tags($body));

        // 1. Comprobar Lista Blanca del panel
        if ($this->isWhitelisted($senderLower, $settings)) {
            return ['pass' => true, 'reason' => 'whitelisted_sender'];
        }

        // 2. Comprobar señales negativas (facturas, recibos, newsletters irrelevantes, etc.)
        if ($this->matchesJunkPatterns($subjectLower, $bodyPlain)) {
            return ['pass' => false, 'reason' => 'junk_or_service_notice'];
        }

        // 3. Comprobar dominios de sellos y promoción
        if ($this->isPromoDomain($senderLower, $settings)) {
            return ['pass' => true, 'reason' => 'promo_domain'];
        }

        // 4. Comprobar palabras clave de lanzamientos, noticias y música
        $keywords = $this->resolveKeywords($settings);
        foreach ($keywords as $kw) {
            $kw = mb_strtolower(trim($kw));
            if ($kw === '') {
                continue;
            }

            // Búsqueda en el asunto (mayor peso) o en el cuerpo
            if (str_contains($subjectLower, $kw) || str_contains($bodyPlain, $kw)) {
                return ['pass' => true, 'reason' => "keyword_matched: {$kw}"];
            }
        }

        // 5. Sin señales de relevancia musical
        return ['pass' => false, 'reason' => 'no_musical_relevance'];
    }

    /**
     * Verifica si el remitente coincide con la lista blanca.
     */
    protected function isWhitelisted(string $senderEmail, ?ThemeSetting $settings): bool
    {
        if (! $settings || ! $settings->email_whitelist_senders) {
            return false;
        }

        $entries = array_filter(array_map('trim', explode(',', mb_strtolower($settings->email_whitelist_senders))));

        foreach ($entries as $entry) {
            if ($entry === '') {
                continue;
            }
            if ($senderEmail === $entry || str_contains($senderEmail, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si el remitente pertenece a sellos o plataformas de promoción.
     */
    protected function isPromoDomain(string $senderEmail, ?ThemeSetting $settings): bool
    {
        $domains = self::DEFAULT_PROMO_DOMAINS;

        if ($settings && ! empty($settings->ai_prefilter_promo_domains)) {
            $extra = array_filter(array_map('trim', explode(',', mb_strtolower($settings->ai_prefilter_promo_domains))));
            $domains = array_unique(array_merge($domains, $extra));
        }

        foreach ($domains as $domain) {
            $domain = trim($domain);
            if ($domain !== '' && str_contains($senderEmail, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detecta si el asunto o cuerpo tienen patrones de spam/facturación/administrativo.
     */
    protected function matchesJunkPatterns(string $subject, string $body): bool
    {
        foreach (self::JUNK_PATTERNS as $pattern) {
            // Si el patrón está en el asunto, es descarte directo
            if (str_contains($subject, $pattern)) {
                return true;
            }
        }

        // Descartar si el asunto tiene palabras de facturación habituales
        if (preg_match('/\b(factura|invoice|receipt|recibo|comprobante|billing)\b/i', $subject)) {
            return true;
        }

        return false;
    }

    /**
     * Obtiene la lista de palabras clave configuradas o por defecto.
     *
     * @return array<int, string>
     */
    protected function resolveKeywords(?ThemeSetting $settings): array
    {
        if ($settings && ! empty($settings->ai_prefilter_keywords)) {
            $list = array_filter(array_map('trim', explode(',', $settings->ai_prefilter_keywords)));
            if (! empty($list)) {
                return $list;
            }
        }

        return self::DEFAULT_KEYWORDS;
    }
}
