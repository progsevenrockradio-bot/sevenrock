<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sin conexión — Seven Rock Radio</title>
    <meta name="theme-color" content="#DC2626">
    <style>
        :root {
            --accent: #DC2626;
            --bg: #121212;
            --surface: #1e1e1e;
            --text: #e8e8e8;
            --muted: #7b7b7b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            overflow: hidden;
        }

        /* ── Partícula de fondo animada ── */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at center, rgba(220,38,38,0.04) 0%, transparent 60%);
            animation: pulse-bg 4s ease-in-out infinite alternate;
            pointer-events: none;
        }

        @keyframes pulse-bg {
            from { transform: scale(0.95); }
            to   { transform: scale(1.05); }
        }

        /* ── Ícono Wifi OFF ── */
        .wifi-icon {
            width: 90px;
            height: 90px;
            margin: 0 auto 32px;
            position: relative;
        }

        .wifi-icon svg {
            width: 100%;
            height: 100%;
        }

        /* Línea tachada animada */
        .wifi-slash {
            stroke-dasharray: 140;
            stroke-dashoffset: 140;
            animation: draw-slash 0.8s ease-out 0.3s forwards;
        }

        @keyframes draw-slash {
            to { stroke-dashoffset: 0; }
        }

        /* ── Ondas del wifi parpadeando ── */
        .wifi-arc {
            animation: blink-arc 2s ease-in-out infinite;
            transform-origin: 50% 80%;
        }
        .wifi-arc:nth-child(1) { animation-delay: 0s; }
        .wifi-arc:nth-child(2) { animation-delay: 0.3s; }
        .wifi-arc:nth-child(3) { animation-delay: 0.6s; }

        @keyframes blink-arc {
            0%, 100% { opacity: 0.15; }
            50%       { opacity: 0.45; }
        }

        /* ── Contenido ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(220,38,38,0.12);
            border: 1px solid rgba(220,38,38,0.3);
            color: #f87171;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 5px 12px;
            border-radius: 50px;
            margin-bottom: 20px;
        }

        .badge-dot {
            width: 6px;
            height: 6px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse-dot 1.5s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.7); }
        }

        h1 {
            font-family: 'Segoe UI', system-ui, sans-serif;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #fff;
            margin-bottom: 12px;
            line-height: 1.1;
        }

        p {
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
            max-width: 280px;
            margin: 0 auto 32px;
        }

        /* ── Botones ── */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 14px 28px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            text-decoration: none;
            width: 100%;
            max-width: 260px;
            justify-content: center;
            margin-bottom: 12px;
        }
        .btn-primary:active { transform: scale(0.97); background: #b91c1c; }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--muted);
            border: 1px solid #2a2a2a;
            border-radius: 50px;
            padding: 12px 24px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s, border-color 0.2s;
            background: transparent;
            width: 100%;
            max-width: 260px;
            justify-content: center;
        }
        .btn-secondary:hover { color: var(--text); border-color: #444; }

        /* ── Info emisora ── */
        .station-info {
            margin-top: 48px;
            padding-top: 24px;
            border-top: 1px solid #1e1e1e;
            width: 100%;
            max-width: 300px;
        }

        .station-info img {
            height: 24px;
            width: auto;
            margin: 0 auto 8px;
            display: block;
            opacity: 0.6;
        }

        .station-info p {
            font-size: 12px;
            color: #3a3a3a;
            margin-bottom: 0;
        }

        /* ── Mensaje de caché ── */
        .cache-hint {
            font-size: 12px;
            color: #2a2a2a;
            margin-top: 8px;
        }
    </style>
</head>
<body>

    {{-- Ícono WiFi desconectado --}}
    <div class="wifi-icon">
        <svg viewBox="0 0 100 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            {{-- Arcos del WiFi (tenues, parpadeantes) --}}
            <path class="wifi-arc" d="M10 30 Q50 0 90 30" stroke="#DC2626" stroke-width="6" stroke-linecap="round" fill="none"/>
            <path class="wifi-arc" d="M22 44 Q50 24 78 44" stroke="#DC2626" stroke-width="6" stroke-linecap="round" fill="none"/>
            <path class="wifi-arc" d="M34 58 Q50 46 66 58" stroke="#DC2626" stroke-width="6" stroke-linecap="round" fill="none"/>
            {{-- Punto central --}}
            <circle cx="50" cy="70" r="5" fill="#DC2626" opacity="0.4"/>
            {{-- Línea diagonal de "sin señal" --}}
            <line class="wifi-slash" x1="15" y1="10" x2="85" y2="75" stroke="#ef4444" stroke-width="5" stroke-linecap="round"/>
        </svg>
    </div>

    {{-- Badge de estado --}}
    <div class="badge">
        <span class="badge-dot"></span>
        Sin conexión
    </div>

    <h1>Estás offline</h1>

    <p>
        Parece que no tienes conexión a internet.
        Verifica tu señal y vuelve a intentarlo para escuchar Seven Rock Radio.
    </p>

    {{-- Botones --}}
    <button class="btn-primary" onclick="window.location.reload()">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Reintentar conexión
    </button>

    <a href="/app" class="btn-secondary">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        Ir al inicio
    </a>

    {{-- Info de la emisora --}}
    <div class="station-info">
        <img src="/assets/lucille/logo.png" alt="Seven Rock Radio">
        <p>Seven Rock Radio · sevenrockradio.com</p>
        <p class="cache-hint">El contenido en caché podría estar disponible.</p>
    </div>

    <script>
        // Auto-reintento cuando el navegador recupera conexión
        window.addEventListener('online', () => {
            window.location.href = '/app';
        });
    </script>
</body>
</html>
