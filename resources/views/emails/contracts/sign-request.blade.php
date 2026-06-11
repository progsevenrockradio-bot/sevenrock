<x-mail::message>
<div style="background:#0e0e10;border:1px solid #2c2c2c;padding:24px 20px;color:#e7e7e7;font-family:sans-serif;">
    <div style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#8d8d8d;">Seven Rock Radio</div>
    <h1 style="margin:12px 0 0;font-size:22px;line-height:1.2;color:#f3f3f3;">Firma de Contrato Digital</h1>
    
    <div style="margin-top:18px;font-size:14px;line-height:1.8;color:#d0d0d0;">
        <p>Hola, <strong>{{ $contract->signer_name }}</strong>:</p>
        <p>Se ha generado el contrato titulado <strong>«{{ $contract->title }}»</strong> para formalizar tu acuerdo con Seven Rock Radio.</p>
        <p>Por favor, accede a través del siguiente enlace para revisar las cláusulas y realizar la firma electrónica mediante un solo clic:</p>
        
        <div style="margin-top: 24px; text-align: center;">
            <a href="{{ $signingUrl }}" style="background:#c32720;color:#ffffff;text-decoration:none;padding:12px 30px;font-weight:bold;text-transform:uppercase;letter-spacing:.08em;font-size:13px;border-radius:4px;display:inline-block;box-shadow:0 4px 12px rgba(195,39,32,0.3);">
                Revisar y Firmar Contrato
            </a>
        </div>
        
        <p style="margin-top:24px; font-size:12px; color:#8d8d8d;">
            Si el botón no funciona, copia y pega la siguiente URL en tu navegador:<br>
            <a href="{{ $signingUrl }}" style="color:#d3a15a;word-break:break-all;">{{ $signingUrl }}</a>
        </p>
    </div>
    
    <div style="margin-top:22px;border-top:1px solid #2c2c2c;padding-top:14px;font-size:11px;line-height:1.7;color:#9c9c9c;">
        <div>Este es un correo automático, por favor no respondas directamente.</div>
        <div>© {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados.</div>
    </div>
</div>
</x-mail::message>
