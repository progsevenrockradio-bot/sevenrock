<x-mail::message>
# ¡La estética del Rock ha llegado a tu bandeja de entrada! 🎸

Hola, esto es una prueba para comprobar cómo se ven los nuevos correos electrónicos de **Seven Rock Radio**.

Como puedes ver, hemos abandonado los aburridos correos en fondo blanco para darle un toque mucho más profesional y acorde a tu sitio web.

<x-mail::panel>
**Detalles del rediseño:**
- Fondo oscuro (#101012 y #161618)
- Acentos en el rojo característico de Lucille
- Nuevo pie de página dinámico tipo Cloudflare
</x-mail::panel>

<x-mail::button :url="url('/')" color="primary">
Visitar la Web
</x-mail::button>

Si puedes leer esto perfectamente y los enlaces del pie de página funcionan, ¡el despliegue ha sido un éxito rotundo!

Sigue rockeando,<br>
{{ config('app.name') }}
</x-mail::message>
