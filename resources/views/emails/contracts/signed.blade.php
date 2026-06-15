<x-mail::message>
<div style="font-size: 10px; color: #88888f; text-transform: uppercase; letter-spacing: 0.25em; font-weight: 600; color: #2ecc71;">Contrato Formalizado</div>

# Contrato Firmado con Éxito

Hola, **{{ $contract->signer_name }}**:

Te confirmamos que el contrato titulado **«{{ $contract->title }}»** ha sido firmado de forma electrónica y formalizado exitosamente.

<x-mail::panel>
**Datos de Auditoría de la Firma**

**Firmante:** {{ $contract->signer_name }}  
**Correo:** {{ $contract->signer_email }}  
**Fecha/Hora (UTC):** `{{ $contract->signed_at ? $contract->signed_at->toDateTimeString() : '' }}`  
**Dirección IP:** `{{ $contract->signer_ip }}`  
</x-mail::panel>

Tu copia digital en PDF del contrato final firmado ha sido generada y se adjunta a este correo.
Te recomendamos guardar este documento para tus registros.

Este es un mensaje automático generado por el sistema de firma de Seven Rock Radio.
</x-mail::message>
