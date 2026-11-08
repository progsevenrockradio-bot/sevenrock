// tests/js/player-timer-frontend-verification.mjs
// Suite de verificación automatizada del JavaScript real de player.js en Node v22.

import assert from 'node:assert/strict';
import { registerRadioPlayer } from '../../resources/js/player.js';

// Setup global browser mock for Node environment
globalThis.localStorage = {
    _data: {},
    getItem(k) { return this._data[k] ?? null; },
    setItem(k, v) { this._data[k] = String(v); },
    removeItem(k) { delete this._data[k]; },
    clear() { this._data = {}; }
};

globalThis.window = {
    innerWidth: 1024,
    addEventListener() {},
    removeEventListener() {},
    dispatchEvent() {},
    open() {},
};

globalThis.document = {
    getElementById(id) {
        return null;
    },
    querySelectorAll() {
        return [];
    },
    createElement() {
        return {
            setAttribute() {},
            value: '',
            innerHTML: '',
        };
    }
};

let playerFactory = null;
const mockAlpine = {
    data(name, factory) {
        if (name === 'radioPlayer') {
            playerFactory = factory;
        }
    }
};

registerRadioPlayer(mockAlpine);
assert.ok(playerFactory, 'registerRadioPlayer debe registrar el componente radioPlayer');

console.log('🧪 Iniciando verificación de JavaScript real (player.js)...\n');

// -------------------------------------------------------------------------
// PRUEBA 1: Dos canciones de duración similar con widget retrasado
// -------------------------------------------------------------------------
{
    console.log('Test 1: Dos canciones de duración similar (252s vs 255s) con widget retrasado...');
    const player = playerFactory({ mode: 'dock' });
    player.track.is_live = true;

    // Canción A (Iron Maiden - The Trooper: 252s)
    player.applyStatus({
        track: {
            title: 'The Trooper',
            artist: 'Iron Maiden',
            signature: 'sig-trooper',
            duration_seconds: 252,
            elapsed_seconds: 245,
            is_live: true,
            es_bloque_programa: false,
        }
    }, true, true);

    assert.equal(player.fixedDuration, 252);
    assert.equal(player.currentTime, 245);
    assert.equal(player.hasReliableTime(), true);

    // Entra Canción B (Dio - Rainbow in the Dark: 255s, diferencia de 3s)
    // Pero el widget en DOM todavía tiene 'Iron Maiden - The Trooper' (desfasado)
    const delayedWidgetTrack = {
        title: 'The Trooper',
        artist: 'Iron Maiden',
        duration: 252,
        elapsed: 250,
    };

    // La nueva canción B entra desde la API
    player.applyStatus({
        track: {
            title: 'Rainbow in the Dark',
            artist: 'Dio',
            signature: 'sig-rainbow',
            duration_seconds: 255,
            elapsed_seconds: 5,
            is_live: true,
            es_bloque_programa: false,
        }
    }, true, true);

    assert.equal(player.track.title, 'Rainbow in the Dark');
    assert.equal(player.fixedDuration, 255);
    assert.equal(player.currentTime, 5);

    // Comprobamos isWidgetMatchingCurrentTrack con el widget desfasado
    const matchesOld = player.isWidgetMatchingCurrentTrack(delayedWidgetTrack);
    assert.equal(matchesOld, false, 'El widget desfasado de Canción A NO debe coincidir con Canción B');

    // Forzamos un tick de progreso: no debe adoptar los 250s del widget desfasado
    player.playing = true;
    player.syncCounter = 4; // próximo tick dispara sync
    player.resolveWidgetTrack = () => delayedWidgetTrack;
    player.tickProgress();

    assert.equal(player.fixedDuration, 255, 'La duración debe mantenerse en 255s y NO adoptar 252s del widget');
    assert.equal(player.currentTime, 6, 'El contador debe avanzar 1s (5->6) y NO saltar a 250s');
    console.log('   ✓ Rechazo estricto de widget desfasado por identidad confirmado.');
}

// -------------------------------------------------------------------------
// PRUEBA 2: Pérdida de datos sin cambio de pista
// -------------------------------------------------------------------------
{
    console.log('Test 2: Pérdida de datos sin cambio de pista (The Trooper pierde duration_seconds)...');
    const player = playerFactory({ mode: 'dock' });
    player.track.is_live = true;

    // Canción con datos iniciales válidos
    player.applyStatus({
        track: {
            title: 'The Trooper',
            artist: 'Iron Maiden',
            signature: 'sig-trooper',
            duration_seconds: 252,
            elapsed_seconds: 100,
            is_live: true,
            es_bloque_programa: false,
        }
    }, true, true);

    assert.equal(player.hasReliableTime(), true);

    // Simular que expira la muestra previa en el tiempo
    player.timeSample.sampledAt = Date.now() - 20000; // 20s atrás

    // La API actualiza la misma pista pero ahora duration_seconds es 0 o ausente
    player.applyStatus({
        track: {
            title: 'The Trooper',
            artist: 'Iron Maiden',
            signature: 'sig-trooper',
            duration_seconds: 0,
            elapsed_seconds: 0,
            is_live: true,
            es_bloque_programa: false,
        }
    }, true, true);

    assert.equal(player.fixedDuration, 0, 'fixedDuration debe limpiarse a 0');
    assert.equal(player.currentTime, 0, 'currentTime debe limpiarse a 0');
    assert.equal(player.hasReliableTime(), false, 'hasReliableTime() debe ser false ante pérdida de datos');
    console.log('   ✓ Pérdida de datos sin cambio de pista limpia contadores y conmuta a «EN DIRECTO».');
}

// -------------------------------------------------------------------------
// PRUEBA 3: Caducidad de datos al finalizar la pista
// -------------------------------------------------------------------------
{
    console.log('Test 3: Caducidad de datos al alcanzar el final...');
    const player = playerFactory({ mode: 'dock' });
    player.track.is_live = true;
    player.playing = true;

    player.applyStatus({
        track: {
            title: 'Short Track',
            artist: 'Artist',
            signature: 'sig-short',
            duration_seconds: 60,
            elapsed_seconds: 59,
            is_live: true,
            es_bloque_programa: false,
        }
    }, true, true);

    assert.equal(player.hasReliableTime(), true);

    // Simular avance de 7 segundos en ticks consecutivos
    for (let i = 0; i < 7; i++) {
        player.tickProgress();
    }

    assert.equal(player.fixedDuration, 0, 'Al sobrepasar la duración + margen, fixedDuration debe limpiarse a 0');
    assert.equal(player.hasReliableTime(), false, 'hasReliableTime() debe pasar a false al expirar la pista');
    console.log('   ✓ Caducidad de datos efectiva: contador no se queda congelado indefinidamente.');
}

// -------------------------------------------------------------------------
// PRUEBA 4: 0 es un valor inicial válido para elapsed_seconds
// -------------------------------------------------------------------------
{
    console.log('Test 4: Conservación de 0 como valor válido de inicio (no confundir con ausente)...');
    const player = playerFactory({ mode: 'dock' });
    player.track.is_live = true;

    player.applyStatus({
        track: {
            title: 'New Song',
            artist: 'Band',
            signature: 'sig-new',
            duration_seconds: 180,
            elapsed_seconds: 0, // Inicia en 0
            is_live: true,
            es_bloque_programa: false,
        }
    }, true, true);

    assert.equal(player.fixedDuration, 180);
    assert.equal(player.currentTime, 0);
    assert.equal(player.hasReliableTime(), true, '0 es un tiempo transcurrido válido al iniciar canción');
    console.log('   ✓ Cero validado como inicio genuino de canción.');
}

// -------------------------------------------------------------------------
// PRUEBA 5: Bloque de programa no lee widgets de canciones
// -------------------------------------------------------------------------
{
    console.log('Test 5: Bloque de programa prohíbe lectura de widget de canciones...');
    const player = playerFactory({ mode: 'dock' });
    player.track.is_live = true;
    player.playing = true;

    player.applyStatus({
        track: {
            title: 'Especial NWOBHM',
            artist: 'Carlos Metalhead',
            signature: 'sig-prog',
            duration_seconds: 3600,
            elapsed_seconds: 300,
            is_live: true,
            es_bloque_programa: true, // Bloque de programa
        }
    }, true, true);

    // Widget que contiene una canción cualquiera
    const widgetTrack = {
        title: 'The Trooper',
        artist: 'Iron Maiden',
        duration: 252,
        elapsed: 100,
    };

    assert.equal(player.isWidgetMatchingCurrentTrack(widgetTrack), false);

    player.resolveWidgetTrack = () => widgetTrack;
    player.syncCounter = 4;
    player.tickProgress();

    assert.equal(player.fixedDuration, 3600, 'La duración del programa (3600) se mantiene intacta');
    assert.equal(player.currentTime, 301, 'El contador avanza limpiamente sin ser corrompido por el widget');
    console.log('   ✓ Protección de bloque de programa frente a widgets confirmada.');
}

// -------------------------------------------------------------------------
// PRUEBA 6: Podcast de Archive.org bajo demanda
// -------------------------------------------------------------------------
{
    console.log('Test 6: Podcast bajo demanda de Archive.org con control interactivo...');
    const player = playerFactory({ mode: 'dock' });
    player.$refs = {
        audio: {
            duration: 3240,
            currentTime: 0,
            pause() {},
            play() { return Promise.resolve(); },
            removeAttribute() {},
            load() {},
            src: '',
            currentSrc: ''
        }
    };

    // Simular carga de podcast
    player.handleMultimediaTrack({
        title: '192.- TARDES LITERARIAS. 15-06-2026',
        subtitle: 'Tardes Literarias',
        src: 'https://archive.org/download/tardes_192/192.mp3',
        type: 'podcast',
    });

    assert.equal(player.track.is_live, false);
    assert.equal(player.track.audio_url, 'https://archive.org/download/tardes_192/192.mp3');

    // Simular evento canplay / syncFromAudio con duración 3240s
    player.progress.duration = 3240;
    player.fixedDuration = 3240;
    player.currentTime = 300;
    player.syncProgress();

    assert.equal(player.hasReliableTime(), true);
    assert.equal(player.progress.elapsed, 300);
    assert.equal(player.progress.duration, 3240);
    assert.equal(player.formatTime(player.progress.duration), '54:00');
    assert.equal(player.formatTime(player.progress.elapsed), '05:00');

    // Simular seek interactivo al 50%
    const mockSeekEvent = {
        clientX: 500,
        currentTarget: {
            getBoundingClientRect() { return { left: 0, width: 1000 }; }
        }
    };
    player.seek(mockSeekEvent);
    assert.equal(player.currentTime, 1620, 'Seek al 50% en podcast debe posicionarse en 1620s');
    assert.equal(player.progress.ratio, 50);

    // Comprobar que en directo seek() no hace nada
    player.track.is_live = true;
    player.seek(mockSeekEvent);
    assert.equal(player.currentTime, 1620, 'En directo seek() no debe modificar la posición');
    console.log('   ✓ Podcast interactivo y bloqueo de seek en directo comprobados.');
}

console.log('\n✨ Todas las pruebas del motor JavaScript pasaron satisfactoriamente (6/6).');
