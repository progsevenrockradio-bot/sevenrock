<!DOCTYPE html>
<html lang="es" data-theme="pinion">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $action }} - Seven Rock Radio</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #151515;
            color: #dcdcdc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 16px;
        }
        .card {
            background-color: #101012;
            padding: 24px 20px;
            border-radius: 12px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 28px 72px rgba(0,0,0,.58);
            border: 1px solid #1a1a1a;
        }
        @media (min-width: 640px) {
            .card {
                padding: 40px 30px;
            }
        }
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .badge-success {
            background-color: rgba(40, 167, 69, 0.15);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        .badge-danger {
            background-color: rgba(195, 39, 32, 0.15);
            color: #c32720;
            border: 1px solid rgba(195, 39, 32, 0.3);
        }
        h1 {
            color: #dcdcdc;
            margin: 0 0 15px;
            font-size: 1.8rem;
            letter-spacing: -0.02em;
        }
        .band-name {
            color: #c32720;
            font-weight: 700;
        }
        p {
            font-size: 1.05rem;
            line-height: 1.6;
            margin-bottom: 20px;
            color: #a0a0a0;
        }
        .status-box {
            background-color: rgba(255, 255, 255, 0.02);
            border: 1px solid #222;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 25px;
            text-align: left;
            font-size: 0.95rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .status-box div {
            margin-bottom: 6px;
        }
        .status-box div:last-child {
            margin-bottom: 0;
        }
        .btn {
            display: block;
            background-color: #081a24;
            color: #dcdcdc;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 6px;
            font-weight: bold;
            border: 1px solid #1a1a1a;
            transition: all 0.2s ease;
        }
        @media (min-width: 640px) {
            .btn {
                display: inline-block;
            }
        }
        .btn:hover {
            background-color: #c32720;
            color: #fff;
            border-color: #c32720;
        }
    </style>
</head>
<body>
    <div class="card">
        @if(str_contains(strtolower($action), 'aprobada'))
            <span class="badge badge-success">✓ Aprobado</span>
        @else
            <span class="badge badge-danger">✕ Rechazado</span>
        @endif

        <h1>{{ $action }}</h1>

        <p>{{ $message }}</p>

        <div class="status-box">
            <div><strong>Banda:</strong> <span class="band-name">{{ $talent->band_name }}</span></div>
            <div><strong>Email:</strong> {{ $talent->email }}</div>
            <div><strong>Plan:</strong> {{ ucfirst($talent->plan) }}</div>
            <div><strong>Estado actual:</strong> {{ ucfirst($talent->subscription_status) }}</div>
        </div>

        <a href="{{ route('admin.talents.index') }}" class="btn">Ir al panel de talentos</a>
    </div>
</body>
</html>
