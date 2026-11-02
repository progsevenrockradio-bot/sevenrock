<!DOCTYPE html>
<html lang="es" data-theme="pinion">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $action }} - Seven Rock Radio</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #0e0e11;
            color: #dcdcdc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 16px;
        }
        .panel {
            background-color: #101012;
            padding: 28px 24px;
            border-radius: 12px;
            max-width: 480px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 28px 72px rgba(0, 0, 0, .58);
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 14px;
        }
        .status-ok {
            background-color: rgba(16, 185, 129, 0.12);
            color: #6ee7b7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-err {
            background-color: rgba(195, 39, 32, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(195, 39, 32, 0.35);
        }
        h1 {
            color: #ffffff;
            margin: 0 0 8px;
            font-size: 1.35rem;
            letter-spacing: 0.02em;
        }
        p {
            font-size: 0.875rem;
            line-height: 1.5;
            margin: 0 0 18px;
            color: #9ca3af;
        }
        .meta {
            background-color: #151518;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 20px;
            font-size: 0.8125rem;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }
        .meta-row:last-child { border-bottom: 0; }
        .meta-label { color: #9ca3af; }
        .meta-value { color: #ffffff; font-weight: 600; }
        .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 38px;
            background-color: #c32720;
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.8125rem;
            font-weight: 600;
        }
        .action-link:hover { background-color: #a8201a; }
    </style>
</head>
<body>
    @php
        $planLabels = ['free' => 'Gratuito (Free)', 'basic' => 'Básico', 'pro' => 'Pro', 'premium' => 'Premium'];
        $statusLabels = ['active' => 'Activa', 'pending' => 'Pendiente', 'expired' => 'Vencida', 'inactive' => 'Inactiva', 'cancelled' => 'Cancelada'];
    @endphp
    <div class="panel">
        @if(str_contains(strtolower($action), 'aprobada'))
            <span class="status-pill status-ok">✓ Banda aprobada</span>
        @else
            <span class="status-pill status-err">✕ Solicitud rechazada</span>
        @endif

        <h1>{{ $action }}</h1>
        <p>{{ $message }}</p>

        <div class="meta">
            <div class="meta-row">
                <span class="meta-label">Banda</span>
                <span class="meta-value">{{ $talent->band_name }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Correo electrónico</span>
                <span class="meta-value">{{ $talent->email }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Plan</span>
                <span class="meta-value">{{ $planLabels[$talent->plan] ?? ucfirst((string) $talent->plan) }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Estado de suscripción</span>
                <span class="meta-value">{{ $statusLabels[$talent->subscription_status] ?? ucfirst((string) $talent->subscription_status) }}</span>
            </div>
        </div>

        <a href="{{ route('admin.talents.index') }}" class="action-link">Ir a Bandas del Muro</a>
    </div>
</body>
</html>
