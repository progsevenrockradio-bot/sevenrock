<x-mail::message>
# Nuevo mensaje de contacto

Has recibido un nuevo mensaje a través del formulario de **{{  }}** de Seven Rock Radio.

---

**Nombre:** {{  }}
**Email:** {{  }}
**Teléfono:** {{  ?: 'No indicado' }}

**Mensaje:**

{{  }}

---

<x-mail::button :url=mailto:{{ $senderEmail }}>
Responder a {{  }}
</x-mail::button>

Saludos,<br>
Seven Rock Radio
</x-mail::message>
