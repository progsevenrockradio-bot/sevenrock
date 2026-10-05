<x-mail::message>
# Hola, {{ $promoter->owner_name }}

Aquí tienes tu código de promotor oficial para **Seven Rock Radio**. 

Puedes compartir este código con bandas para que se registren de forma totalmente gratuita y accedan a todos los beneficios del portal.

### Tu código:
<div style="background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 2px; margin-bottom: 20px; border-radius: 8px;">
    {{ $promoter->code }}
</div>

@if ($promoter->max_bands)
*Nota: Este código es válido para un máximo de {{ $promoter->max_bands }} bandas.*
@else
*Nota: Este código no tiene límite de usos.*
@endif

Para usarlo, las bandas solo tienen que introducirlo en el formulario de registro:

<x-mail::button :url="route('talents.register')">
Ir al Portal de Bandas
</x-mail::button>

Gracias por ser parte de nuestra comunidad.

Saludos,<br>
El equipo de {{ config('app.name') }}
</x-mail::message>
