<x-mail::message>
# Nuevo mensaje de contacto

Has recibido un nuevo mensaje a través del formulario de **{{ $source }}** de Seven Rock Radio.

---

**Nombre:** {{ $senderName }}
**Email:** {{ $senderEmail }}
**Teléfono:** {{ $senderPhone ?: "No indicado" }}

**Mensaje:**

{{ $messageBody }}

---

<x-mail::button :url="mailto:{{ $senderEmail }}">
Responder a {{ $senderName }}
</x-mail::button>

Saludos,<br>
Seven Rock Radio
</x-mail::message>
