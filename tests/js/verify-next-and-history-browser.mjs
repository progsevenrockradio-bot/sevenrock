import { spawn } from 'child_process';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const port = 9222;
const testUrl = 'http://sevenrockradio.test/dev/player-scenario';

async function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

class CDPClient {
    constructor(wsUrl) {
        this.ws = new WebSocket(wsUrl);
        this.id = 1;
        this.callbacks = new Map();
        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            if (data.method === 'Runtime.consoleAPICalled') {
                console.log('  [CONSOLE]', data.params.type, ...data.params.args.map(a => a.value ?? a.description ?? ''));
            }
            if (data.method === 'Runtime.exceptionThrown') {
                console.log('  [EXCEPTION]', data.params.exceptionDetails?.text, data.params.exceptionDetails?.exception?.description);
            }
            if (data.id && this.callbacks.has(data.id)) {
                const cb = this.callbacks.get(data.id);
                this.callbacks.delete(data.id);
                if (data.error) cb.reject(new Error(data.error.message));
                else cb.resolve(data.result);
            }
        };
    }

    async ready() {
        if (this.ws.readyState === WebSocket.OPEN) return;
        return new Promise((resolve, reject) => {
            this.ws.onopen = () => resolve();
            this.ws.onerror = (e) => reject(e);
        });
    }

    async send(method, params = {}) {
        await this.ready();
        const id = this.id++;
        return new Promise((resolve, reject) => {
            this.callbacks.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    async evaluate(expression) {
        const res = await this.send('Runtime.evaluate', {
            expression,
            returnByValue: true,
            awaitPromise: true,
        });
        if (res.exceptionDetails) {
            throw new Error(res.exceptionDetails.exception?.description || 'Eval error');
        }
        return res.result?.value;
    }

    close() {
        try { this.ws.close(); } catch (_) {}
    }
}

async function run() {
    console.log('🚀 Iniciando Chrome Headless para verificar «Próxima Canción e Historial»...');
    const chrome = spawn(chromePath, [
        `--remote-debugging-port=${port}`,
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--autoplay-policy=no-user-gesture-required',
        '--window-size=1280,800',
        'about:blank',
    ]);

    let cdp = null;

    try {
        await sleep(1500);
        const targetsRes = await fetch(`http://127.0.0.1:${port}/json`);
        const targets = await targetsRes.json();
        const pageTarget = targets.find((t) => t.type === 'page');

        if (!pageTarget || !pageTarget.webSocketDebuggerUrl) {
            throw new Error('No se encontró página en Chrome');
        }

        cdp = new CDPClient(pageTarget.webSocketDebuggerUrl);
        await cdp.ready();
        await cdp.send('Page.enable');
        await cdp.send('Runtime.enable');

        console.log(`🌐 Navegando a ${testUrl}...`);
        await cdp.send('Page.navigate', { url: testUrl });
        await sleep(2000);

        // Esperar a que window.MusicPlayer y window.Alpine estén disponibles
        for (let i = 0; i < 30; i++) {
            const ready = await cdp.evaluate(`Boolean(window.MusicPlayer && window.Alpine)`);
            if (ready) break;
            await sleep(200);
        }

        // Iniciar audio para validar que las acciones informativas no interrumpen el stream
        console.log('▶️ Iniciando reproducción de audio...');
        await cdp.evaluate(`(() => {
            if (window.MusicPlayer) {
                window.MusicPlayer.togglePlay();
            }
        })()`);
        await sleep(1500);
        const audioPlaying = await cdp.evaluate(`Boolean(window.MusicPlayer && window.MusicPlayer.playing)`);
        console.log(`   Audio sonando: ${audioPlaying ? '✅ SÍ' : '❌ NO'}`);

        // 1. Verificación: Próxima canción disponible
        console.log('\n[1] Verificando Próxima Canción Disponible...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('song');
                await tester.toggleNextTrack(true);
            }
        })()`);
        await sleep(1200);

        const nextTrackCheck = await cdp.evaluate(`(() => {
            return {
                hasValidNextTrack: window.MusicPlayer.hasValidNextTrack,
                title: window.MusicPlayer.nextTrack?.title,
                artist: window.MusicPlayer.nextTrack?.artist,
                cover: Boolean(window.MusicPlayer.nextTrack?.cover),
            };
        })()`);
        console.log(`   Próxima canción detectada: "${nextTrackCheck.title}" por ${nextTrackCheck.artist} | Válida: ${nextTrackCheck.hasValidNextTrack ? '✅ SÍ' : '❌ NO'}`);
        if (!nextTrackCheck.hasValidNextTrack || nextTrackCheck.title !== 'Painkiller') {
            throw new Error('Fallo al validar próxima canción disponible');
        }

        // 2. Verificación: Próxima canción ausente
        console.log('\n[2] Verificando Próxima Canción Ausente...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.toggleNextTrack(false);
            }
        })()`);
        await sleep(1000);

        const nextTrackAbsent = await cdp.evaluate(`(() => {
            return {
                hasValidNextTrack: window.MusicPlayer.hasValidNextTrack,
                nextTrack: window.MusicPlayer.nextTrack,
            };
        })()`);
        console.log(`   Próxima canción ocultada limpiamente: ${!nextTrackAbsent.hasValidNextTrack ? '✅ SÍ' : '❌ NO'}`);
        if (nextTrackAbsent.hasValidNextTrack) {
            throw new Error('La próxima canción ausente debería ocultar el bloque');
        }

        // 3. Verificación: Cambio de pista (próxima pasa a sonar y se retira de «A continuación»)
        console.log('\n[3] Verificando Cambio de Pista (Pista prevista entra al aire)...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('next_track_started');
            }
        })()`);
        await sleep(1200);

        const trackChangeCheck = await cdp.evaluate(`(() => {
            return {
                currentTitle: window.MusicPlayer.track.title,
                currentArtist: window.MusicPlayer.track.artist,
                nextTitle: window.MusicPlayer.nextTrack?.title,
                nextArtist: window.MusicPlayer.nextTrack?.artist,
                historyTop: window.MusicPlayer.history[0]?.title,
            };
        })()`);
        console.log(`   Pista sonando al aire: "${trackChangeCheck.currentTitle}" (${trackChangeCheck.currentArtist})`);
        console.log(`   Nueva pista próxima: "${trackChangeCheck.nextTitle}" (${trackChangeCheck.nextArtist})`);
        console.log(`   Pista anterior en historial: "${trackChangeCheck.historyTop}"`);
        if (trackChangeCheck.currentTitle !== 'Painkiller' || trackChangeCheck.nextTitle === 'Painkiller') {
            throw new Error('La pista entrante no se retiró correctamente de "A continuación"');
        }

        // 4. Verificación: Repetición legítima en historial
        console.log('\n[4] Verificando Repetición Legítima en Historial...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('song');
            }
        })()`);
        await sleep(1200);

        const legitimateRepetitionCheck = await cdp.evaluate(`(() => {
            const history = window.MusicPlayer.history || [];
            const trooperEntries = history.filter(h => h.title === 'The Trooper');
            return {
                totalTrooper: trooperEntries.length,
                timestamps: trooperEntries.map(h => h.played_at),
            };
        })()`);
        console.log(`   Veces que aparece "The Trooper" en momentos distintos: ${legitimateRepetitionCheck.totalTrooper}`);
        console.log(`   Timestamps: ${JSON.stringify(legitimateRepetitionCheck.timestamps)}`);
        if (legitimateRepetitionCheck.totalTrooper < 2) {
            throw new Error('El historial eliminó erróneamente una repetición legítima espaciada en el tiempo');
        }

        // 5. Verificación: Sondeo duplicado (Deduplicación)
        console.log('\n[5] Verificando Deduplicación ante Sondeo Duplicado...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('duplicate_polling');
            }
        })()`);
        await sleep(1200);

        const duplicateCheck = await cdp.evaluate(`(() => {
            const history = window.MusicPlayer.history || [];
            const trooperEntries = history.filter(h => h.title === 'The Trooper');
            return {
                totalCount: history.length,
                trooperCount: trooperEntries.length,
                firstTrooperTime: trooperEntries[0]?.played_at,
                allTitles: history.map(h => h.title),
            };
        })()`);
        console.log(`   Historial actual: ${JSON.stringify(duplicateCheck.allTitles)}`);
        console.log(`   Entradas de The Trooper tras deduplicar sondeo idéntico de 10s: ${duplicateCheck.trooperCount} (Esperado: 1)`);
        if (duplicateCheck.trooperCount !== 1) {
            throw new Error('No se deduplicó el registro idéntico consecutivo del sondeo');
        }

        // 6. Verificación: Historial vacío
        console.log('\n[6] Verificando Historial Vacío...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('empty_history');
            }
        })()`);
        await sleep(1200);

        const emptyCheck = await cdp.evaluate(`(() => {
            return {
                historyLength: window.MusicPlayer.history.length,
                visibleLength: window.MusicPlayer.visibleHistory.length,
            };
        })()`);
        console.log(`   Historial vacío controlado: ${emptyCheck.historyLength === 0 ? '✅ SÍ' : '❌ NO'}`);
        if (emptyCheck.historyLength !== 0) {
            throw new Error('El escenario de historial vacío no debería tener elementos');
        }

        // 7. Verificación: Caída de la fuente (Historial Stale)
        console.log('\n[7] Verificando Caída de la Fuente (Historial Stale conservado)...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('source_failure');
            }
        })()`);
        await sleep(1200);

        const staleCheck = await cdp.evaluate(`(() => {
            return {
                historyStale: window.MusicPlayer.historyStale,
                historyCount: window.MusicPlayer.history.length,
            };
        })()`);
        console.log(`   Historial conservado ante fallo: ${staleCheck.historyCount > 0 ? '✅ SÍ (' + staleCheck.historyCount + ' temas)' : '❌ NO'}`);
        console.log(`   Marca de desactualizado (historyStale): ${staleCheck.historyStale ? '✅ SÍ' : '❌ NO'}`);
        if (!staleCheck.historyStale || staleCheck.historyCount === 0) {
            throw new Error('Ante caída de fuente, debe conservarse el último historial y marcarse como no actualizado');
        }

        // 8. Verificación: Distinción de programas vs canciones y Carátula ausente
        console.log('\n[8] Verificando Distinción de Programas y Carátula Ausente...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('song');
            }
        })()`);
        await sleep(1200);

        const programAndCoverCheck = await cdp.evaluate(`(() => {
            const history = window.MusicPlayer.history || [];
            const programItem = history.find(h => h.is_program === true);
            const noCoverItem = history.find(h => !h.cover || h.cover === '');
            return {
                foundProgram: Boolean(programItem),
                programTitle: programItem?.title,
                foundNoCover: Boolean(noCoverItem),
                noCoverTitle: noCoverItem?.title,
            };
        })()`);
        console.log(`   Programa distinguido en historial: "${programAndCoverCheck.programTitle}" (is_program=true): ${programAndCoverCheck.foundProgram ? '✅ SÍ' : '❌ NO'}`);
        console.log(`   Canción sin carátula en historial: "${programAndCoverCheck.noCoverTitle}" (cover vacío manejado): ${programAndCoverCheck.foundNoCover ? '✅ SÍ' : '❌ NO'}`);
        if (!programAndCoverCheck.foundProgram || !programAndCoverCheck.foundNoCover) {
            throw new Error('Fallo al comprobar distinción de programas o pistas con carátula ausente');
        }

        // 9. Verificación: Comportamiento Plegable (VisibleHistory y Toggle)
        console.log('\n[9] Verificando Comportamiento Plegable del Historial...');
        const initialFoldedCount = await cdp.evaluate(`window.MusicPlayer.visibleHistory.length`);
        console.log(`   Pistas visibles en modo plegado: ${initialFoldedCount} (debe ser 3)`);
        if (initialFoldedCount !== 3) {
            throw new Error(`Se esperaban 3 pistas visibles en modo plegado, pero hay ${initialFoldedCount}`);
        }

        await cdp.evaluate(`window.MusicPlayer.toggleHistoryExpanded()`);
        await sleep(300);
        const expandedCount = await cdp.evaluate(`window.MusicPlayer.visibleHistory.length`);
        console.log(`   Pistas visibles tras desplegar: ${expandedCount} (esperado > 3)`);
        if (expandedCount <= 3) {
            throw new Error('El historial no se expandió al llamar a toggleHistoryExpanded');
        }

        // 10. Verificación: No interrupción del audio durante navegación informativa
        console.log('\n[10] Verificando que la navegación informativa no interrumpe el audio...');
        await cdp.evaluate(`(() => {
            window.MusicPlayer.openBandWindow();
            window.MusicPlayer.setBandWindowTab('history');
            window.MusicPlayer.setTab('history');
        })()`);
        await sleep(800);
        const audioStillPlaying = await cdp.evaluate(`Boolean(window.MusicPlayer && window.MusicPlayer.playing)`);
        console.log(`   Audio continúa sonando tras abrir modal y conmutar pestañas: ${audioStillPlaying ? '✅ SÍ' : '❌ NO'}`);
        if (!audioStillPlaying) {
            throw new Error('La interacción con las pestañas o modal interrumpió la reproducción de audio');
        }

        // 11. Verificación Regla 2: Identidad Completa (Mismo título, distinto artista)
        console.log('\n[11] Verificando Regla 2: Identidad Completa (Changes Sabbath vs Changes Bowie)...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('same_title_diff_artist');
            }
        })()`);
        await sleep(1200);

        const identityCheck = await cdp.evaluate(`(() => {
            return {
                currentTitle: window.MusicPlayer.track?.title,
                currentArtist: window.MusicPlayer.track?.artist,
                nextTitle: window.MusicPlayer.nextTrack?.title,
                nextArtist: window.MusicPlayer.nextTrack?.artist,
                hasValidNextTrack: window.MusicPlayer.hasValidNextTrack,
            };
        })()`);
        console.log(`   Sonando: "${identityCheck.currentTitle}" (${identityCheck.currentArtist}) | Próxima: "${identityCheck.nextTitle}" (${identityCheck.nextArtist})`);
        console.log(`   hasValidNextTrack activo ante mismo título y distinto artista: ${identityCheck.hasValidNextTrack ? '✅ SÍ' : '❌ NO'}`);
        if (!identityCheck.hasValidNextTrack || identityCheck.nextArtist !== 'David Bowie') {
            throw new Error('Regla 2 fallida: el próximo tema no debió ocultarse por compartir título con otro artista');
        }

        // 12. Verificación Regla 4: Repetición auténtica < 5 minutos conservada
        console.log('\n[12] Verificando Regla 4: Repetición auténtica < 5 min (Blitzkrieg Bop)...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('short_song_fast_repetition');
            }
        })()`);
        await sleep(1200);

        const repetitionCheck = await cdp.evaluate(`(() => {
            const history = window.MusicPlayer.history || [];
            const bopEntries = history.filter(h => h.title === 'Blitzkrieg Bop');
            return {
                totalBop: bopEntries.length,
                timestamps: bopEntries.map(h => h.played_at),
                durations: bopEntries.map(h => h.duration_seconds),
            };
        })()`);
        console.log(`   Veces de Blitzkrieg Bop conservadas (< 5 min pero separación > 72s): ${repetitionCheck.totalBop}`);
        if (repetitionCheck.totalBop !== 2) {
            throw new Error('Regla 4 fallida: se descartó erróneamente la repetición genuina ocurrida a los 85 segundos');
        }

        // 13. Verificación Regla 3: No descarte falso positivo de canciones reales con "jingle" o "cuna"
        console.log('\n[13] Verificando Regla 3: Canciones legítimas con "Jingle" o "Cuna" no descartadas...');
        await cdp.evaluate(`(async () => {
            const el = document.querySelector('[x-data*="playerScenarioTester"]');
            const tester = window.Alpine ? window.Alpine.$data(el) : null;
            if (tester) {
                await tester.setScenario('jingle_bell_rock_legitimate');
            }
        })()`);
        await sleep(1200);

        const jingleCheck = await cdp.evaluate(`(() => {
            return {
                currentTitle: window.MusicPlayer.track?.title,
                currentArtist: window.MusicPlayer.track?.artist,
                nextTitle: window.MusicPlayer.nextTrack?.title,
                nextArtist: window.MusicPlayer.nextTrack?.artist,
                hasValidNextTrack: window.MusicPlayer.hasValidNextTrack,
            };
        })()`);
        console.log(`   Canción sonando: "${jingleCheck.currentTitle}" por ${jingleCheck.currentArtist}`);
        console.log(`   Próxima canción: "${jingleCheck.nextTitle}" por ${jingleCheck.nextArtist} | Válida: ${jingleCheck.hasValidNextTrack ? '✅ SÍ' : '❌ NO'}`);
        if (jingleCheck.currentTitle !== 'Jingle Bell Rock' || !jingleCheck.hasValidNextTrack || jingleCheck.nextTitle !== 'Cuna de Caín') {
            throw new Error('Regla 3 fallida: canciones reales con "jingle" o "cuna" fueron filtradas erróneamente');
        }

        console.log('\n🎉 ¡TODAS LAS COMPROBACIONES DE «PRÓXIMA CANCIÓN E HISTORIAL» EN NAVEGADOR PASARON CON ÉXITO!');
    } catch (err) {
        console.error('❌ Error en comprobación de navegador:', err);
        process.exitCode = 1;
    } finally {
        if (cdp) cdp.close();
        chrome.kill();
    }
}

run();
