<x-mail::message>
<div style="font-size: 10px; color: #88888f; text-transform: uppercase; letter-spacing: 0.25em; font-weight: 600;">Portal de Firma Digital</div>

# Firma de Contrato Requerida

Hola, **{{ $contract->signer_name }}**:

Se ha preparado un documento legal titulado **«{{ $contract->title }}»** para formalizar tu acuerdo de colaboración con Seven Rock Radio.

<x-mail::panel>
**Resumen del Acuerdo**

**Documento:** {{ $contract->title }}  
**Destinatario:** {{ $contract->signer_name }} ({{ $contract->signer_email }})  
**Modalidad:** E-Signature Clickwrap  
</x-mail::panel>

Para continuar con el proceso de integración, es indispensable que revises el documento completo y dejes tu firma digital de conformidad. El proceso tomará menos de 2 minutos.

<x-mail::button :url="$contract->signed_url" color="primary">
Revisar y Firmar Documento
</x-mail::button>

*Este enlace es único, privado y está asociado a tu dirección de correo electrónico.*

Este es un mensaje automático generado por el sistema de firma de Seven Rock Radio.
</x-mail::message>
