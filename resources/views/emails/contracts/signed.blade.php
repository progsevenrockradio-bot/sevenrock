<x-mail::message>
<div style="background:#0e0e10;border:1px solid #2c2c2c;padding:24px 20px;color:#e7e7e7;font-family:sans-serif;">
    <div style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#8d8d8d;">Seven Rock Radio</div>
    <h1 style="margin:12px 0 0;font-size:22px;line-height:1.2;color:#f3f3f3;">Contrato Formalizado y Firmado</h1>
    
    <div style="margin-top:18px;font-size:14px;line-height:1.8;color:#d0d0d0;">
        <p>Hola, <strong>{{ $contract->signer_name }}</strong>:</p>
        <p>Te confirmamos que el contrato titulado <strong>«{{ $contract->title }}»</strong> ha sido firmado electrónicamente con éxito.</p>
        <p>Adjunto a este correo electrónico encontrarás el documento oficial en formato PDF que contiene las cláusulas y los datos técnicos de auditoría del acuerdo (fecha, hora e IP de origen).</p>
        
        <p style="margin-top:20px;">Detalles de la firma:</p>
        <ul style="padding-left:20px; color:#d0d0d0; margin-top:5px;">
            <li><strong>Firmante:</strong> {{ $contract->signer_name }} ({{ $contract->signer_email }})</li>
            <li><strong>Fecha y Hora (UTC):</strong> {{ $contract->signed_at ? $contract->signed_at->toDateTimeString() : '' }}</li>
            <li><strong>Dirección IP:</strong> {{ $contract->signing_ip }}</li>
        </ul>
    </div>
    
    <div style="margin-top:22px;border-top:1px solid #2c2c2c;padding-top:14px;font-size:11px;line-height:1.7;color:#9c9c9c;">
        <div>Este es un correo automático. Por favor guarda el archivo adjunto para tus registros personales.</div>
        <div>© {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados.</div>
    </div>
</div>
</x-mail::message>
