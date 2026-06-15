<x-mail::message>
<div style="font-size: 10px; color: #88888f; text-transform: uppercase; letter-spacing: 0.25em; font-weight: 600;">Registro de Bandas & Artistas</div>

# ¡Queremos escuchar tu música!

Hola, **{{ $senderName }}**:

Nos alegra tu interés en formar parte de Seven Rock Radio con tu proyecto musical: **{{ $bandName }}**.

Para publicar tus canciones en el **Muro del Rock**, contar con tu perfil oficial y que toda nuestra comunidad escuche tu propuesta, puedes registrarte en el plan que mejor se adapte a tus necesidades:

<x-mail::panel>
<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #2d2d33; padding-bottom: 12px; margin-bottom: 12px;">
    <div>
        <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #88888f; display: block; margin-bottom: 2px;">Plan Básico Gratuito</span>
        <strong style="font-size: 20px;">FREE</strong>
    </div>
    <div style="text-align: right;">
        <a href="{{ route('talents.plans.checkout', ['plan' => 'free', 'band' => $bandName, 'email' => $senderEmail]) }}" style="background-color: #2b2b2b; color: #ffffff; text-decoration: none; padding: 6px 14px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block;">Seleccionar Gratis</a>
    </div>
</div>
<ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #a0aec0; line-height: 1.6;">
    <li>Perfil de banda básico</li>
    <li>1 canción en el Muro del Rock por mes</li>
    <li>Acceso a foro de la comunidad</li>
</ul>
</x-mail::panel>

<x-mail::panel>
<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c32720; padding-bottom: 12px; margin-bottom: 12px;">
    <div>
        <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #c32720; display: block; margin-bottom: 2px;">Membresía Premium</span>
        <strong style="font-size: 20px;">PRO <span style="font-size: 14px; font-weight: normal; color: #88888f;">/ $9.99 mes</span></strong>
    </div>
    <div style="text-align: right;">
        <a href="{{ route('talents.plans.checkout', ['plan' => 'pro', 'band' => $bandName, 'email' => $senderEmail]) }}" style="background-color: #c32720; color: #ffffff; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; box-shadow: 0 4px 10px rgba(195, 39, 32, 0.4);">Hazte PRO</a>
    </div>
</div>
<ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #a0aec0; line-height: 1.6;">
    <li>Perfil de artista avanzado con biografía completa</li>
    <li>Canciones ilimitadas en el Muro del Rock</li>
    <li>Insignia PRO en tu perfil</li>
    <li>Prioridad en rotación de radio y playlists</li>
    <li>Analíticas de reproducciones</li>
    <li>Soporte VIP y oportunidades de entrevistas</li>
</ul>
</x-mail::panel>

Si tienes alguna duda sobre el funcionamiento de los planes, responde a este correo y nuestro equipo de A&R te asesorará de inmediato.

¡Te esperamos en el lado más ruidoso de internet!

Saludos cordiales,<br>
**A&R - Reclutamiento de Bandas**<br>
Seven Rock Radio
</x-mail::message>
