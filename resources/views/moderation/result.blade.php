<!DOCTYPE html>
<html lang="es" data-theme="pinion">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderación de Contenido - 7RR</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: #151515;
            color: #dcdcdc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            background-color: #101012;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 28px 72px rgba(0,0,0,.58);
            border: 1px solid #1a1a1a;
        }
        h1 {
            color: #c32720;
            margin-bottom: 20px;
        }
        p {
            font-size: 1.1rem;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .btn {
            display: inline-block;
            background-color: #081a24;
            color: #dcdcdc;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 20px;
            border: 1px solid #1a1a1a;
        }
        .btn:hover {
            background-color: #101012;
            color: white;
        }
        .text-muted {
            color: #7b7b7b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Contenido {{ $action }}</h1>
        <p><strong>{{ $item->title }}</strong></p>
        
        @if(!$wasPending)
            <p class="text-muted" style="color: #ff9800; font-weight: bold;">
                Este contenido ya estaba {{ $item->status === 'approved' ? 'aprobado' : 'denegado' }} el {{ $item->decided_at?->format('d/m/Y H:i') }}.
            </p>
        @else
            <p>La acción se ha registrado correctamente con fecha {{ $item->decided_at?->format('d/m/Y H:i') }}.</p>
        @endif

        <a href="{{ route('admin.moderation.show', $item) }}" class="btn">Ver en el Panel</a>
    </div>
</body>
</html>
