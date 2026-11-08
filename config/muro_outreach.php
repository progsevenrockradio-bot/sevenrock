<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Seguimiento comercial Muro del Rock (Fase 2 - Maquetas)
    |--------------------------------------------------------------------------
    |
    | Por seguridad, la campaña permanece DESACTIVADA por defecto y el enlace
    | comercial (cta_url) queda vacío hasta que se unifiquen las cuotas del
    | registro/página de planes. Mientras enabled=false o cta_url esté vacío
    | (o apunte al formulario de registro con cuotas contradictorias), el
    | sistema NO enviará ningún correo de invitación.
    |
    */
    'enabled' => (bool) env('MURO_FOLLOWUP_ENABLED', false),

    'campaign_key' => env('MURO_FOLLOWUP_CAMPAIGN_KEY', 'muro_rock_basic_v1'),

    'delay_days' => (int) env('MURO_FOLLOWUP_DELAY_DAYS', 3),

    // URL aprobada para el botón "Conocer el plan Básico".
    // Apunta por defecto a la página informativa pública unificada (/muro-del-rock/plan-basico).
    // Nunca debe apuntar al formulario de registro directo (/talents/register).
    'cta_url' => env('MURO_FOLLOWUP_CTA_URL', '/muro-del-rock/plan-basico'),

    // Precio informativo confirmado para el plan Básico.
    'basic_plan_price_label' => '3,50 €/mes',

    // Firma oficial acordada.
    'signature' => 'Equipo de Seven Rock Radio',
];

