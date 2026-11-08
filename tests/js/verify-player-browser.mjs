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
    console.log('🚀 Lanzando Chrome en modo headless...');
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

        // Obtener la lista de páginas de Chrome
        const targetsRes = await fetch(`http://127.0.0.1:${port}/json`);
        const targets = await targetsRes.json();
        const pageTarget = targets.find((t) => t.type === 'page');

        if (!pageTarget || !pageTarget.webSocketDebuggerUrl) {
            throw new Error('No se encontró página con WebSocket debugger en Chrome');
        }

        cdp = new CDPClient(pageTarget.webSocketDebuggerUrl);
        await cdp.ready();
        await cdp.send('Page.enable');
        await cdp.send('Runtime.enable');

        console.log(`🌐 Navegando a ${testUrl}...`);
        await cdp.send('Page.navigate', { url: testUrl });
        await sleep(2500);

        // 1. Verificar que el dock está visible al cargar
        const initialDockVisible = await cdp.evaluate(`Boolean(window.MusicPlayer && window.MusicPlayer.dockVisible)`);
        console.log(`  [1] Dock visible inicialmente en /dev/player-scenario: ${initialDockVisible ? '✅ SÍ' : '❌ NO'}`);
        if (!initialDockVisible) {
            throw new Error('El dock debería estar visible inicialmente en /dev/player-scenario');
        }

        // 2. Iniciar reproducción mediante clic en el botón de reproducción
        console.log('  [2] Simulando clic de usuario en el botón de reproducción...');
        await cdp.evaluate(`
            const playBtn = document.querySelector('[data-role="play-button"]') || document.querySelector('button[aria-label*="reproducir" i]') || document.querySelector('button[aria-label*="play" i]');
            if (playBtn) {
                playBtn.click();
            } else if (window.MusicPlayer) {
                window.MusicPlayer.togglePlay();
            }
        `);
        await sleep(1200);

        const isPlaying = await cdp.evaluate(`Boolean(window.MusicPlayer && window.MusicPlayer.playing)`);
        const dockStillVisible = await cdp.evaluate(`Boolean(window.MusicPlayer && window.MusicPlayer.dockVisible)`);
        console.log(`      Reproducción iniciada: ${isPlaying ? '✅ SÍ' : '⚠️ Pendiente'} | Dock visible: ${dockStillVisible ? '✅ SÍ' : '❌ NO'}`);

        // 3. Simular pausa y comprobar que el dock NO se cierra
        console.log('  [3] Pausando reproducción y comprobando persistencia del dock...');
        await cdp.evaluate(`window.MusicPlayer.togglePlay();`);
        await sleep(600); // Superar los 400ms del antiguo temporizador de cierre
        const isPaused = await cdp.evaluate(`!window.MusicPlayer.playing`);
        const dockOpenAfterPause = await cdp.evaluate(`Boolean(window.MusicPlayer && window.MusicPlayer.dockVisible)`);
        console.log(`      Pausado: ${isPaused ? '✅ SÍ' : '❌ NO'} | Dock permanece abierto tras pausa: ${dockOpenAfterPause ? '✅ SÍ (Sin auto-cierre)' : '❌ NO'}`);
        if (!dockOpenAfterPause) {
            throw new Error('El dock se cerró al pausar el reproductor');
        }

        // 4. Probar cambio a escenario local_sample (Muestra Local MP3 Offline)
        console.log('  [4] Cambiando a escenario "local_sample" (MP3 Offline)...');
        await cdp.evaluate(`(() => {
            const tester = Alpine.$data(document.querySelector('[x-data*="playerScenarioTester"]'));
            if (tester) tester.setScenario('local_sample');
        })()`);
        await sleep(1000);

        const localTrack = await cdp.evaluate(`({
            title: window.MusicPlayer.track.title,
            artist: window.MusicPlayer.track.artist,
            audio_url: window.MusicPlayer.track.audio_url,
            is_live: window.MusicPlayer.track.is_live,
            dockVisible: window.MusicPlayer.dockVisible,
        })`);
        console.log(`      Pista local: "${localTrack.title}" (${localTrack.artist}) | is_live: ${localTrack.is_live} | Dock: ${localTrack.dockVisible ? 'Abierto' : 'Cerrado'}`);
        if (localTrack.is_live || !localTrack.audio_url.includes('sample.mp3')) {
            throw new Error('El escenario local_sample no cargó sample.mp3 correctamente');
        }

        // 5. Iniciar reproducción del audio local y comprobar currentTime
        console.log('  [5] Reproduciendo muestra local...');
        await cdp.evaluate(`window.MusicPlayer.togglePlay();`);
        await sleep(800);
        const localPlaying = await cdp.evaluate(`Boolean(window.MusicPlayer.playing && !window.MusicPlayer.$refs.audio.paused)`);
        console.log(`      Reproduciendo audio local: ${localPlaying ? '✅ SÍ' : '❌ NO'}`);

        // 6. Probar cambio a escenario podcast (Archive.org)
        console.log('  [6] Cambiando a escenario "podcast" (Archive.org)...');
        await cdp.evaluate(`(() => {
            const tester = Alpine.$data(document.querySelector('[x-data*="playerScenarioTester"]'));
            if (tester) tester.setScenario('podcast');
        })()`);
        await sleep(1000);

        const podcastTrack = await cdp.evaluate(`({
            title: window.MusicPlayer.track.title,
            audio_url: window.MusicPlayer.track.audio_url,
            duration: window.MusicPlayer.fixedDuration,
            dockVisible: window.MusicPlayer.dockVisible,
        })`);
        console.log(`      Podcast: "${podcastTrack.title}" | Duración: ${podcastTrack.duration}s | Dock: ${podcastTrack.dockVisible ? 'Abierto' : 'Cerrado'}`);
        if (!podcastTrack.audio_url.includes('archive.org')) {
            throw new Error('El escenario podcast no tiene URL de Archive.org');
        }

        // 7. Probar escenario "missing_time" («EN DIRECTO»)
        console.log('  [7] Cambiando a escenario "missing_time" (Sin Tiempos)...');
        await cdp.evaluate(`(() => {
            const tester = Alpine.$data(document.querySelector('[x-data*="playerScenarioTester"]'));
            if (tester) tester.setScenario('missing_time');
        })()`);
        await sleep(1000);

        const missingTimeInfo = await cdp.evaluate(`({
            is_live: window.MusicPlayer.track.is_live,
            hasReliableTime: window.MusicPlayer.hasReliableTime(),
            fixedDuration: window.MusicPlayer.fixedDuration,
            dockVisible: window.MusicPlayer.dockVisible,
        })`);
        console.log(`      Sin tiempos: is_live=${missingTimeInfo.is_live} | hasReliableTime=${missingTimeInfo.hasReliableTime} | Duración=${missingTimeInfo.fixedDuration} | Dock: ${missingTimeInfo.dockVisible ? 'Abierto' : 'Cerrado'}`);
        if (missingTimeInfo.hasReliableTime || missingTimeInfo.fixedDuration !== 0) {
            throw new Error('El escenario missing_time no debería tener tiempo confiable ni duración');
        }

        // 8. Regreso a escenario "song" (The Trooper)
        console.log('  [8] Cambiando de vuelta a escenario "song" (The Trooper)...');
        await cdp.evaluate(`(() => {
            const tester = Alpine.$data(document.querySelector('[x-data*="playerScenarioTester"]'));
            if (tester) tester.setScenario('song');
        })()`);
        await sleep(1000);

        const finalSong = await cdp.evaluate(`({
            title: window.MusicPlayer.track.title,
            artist: window.MusicPlayer.track.artist,
            duration: window.MusicPlayer.fixedDuration,
            dockVisible: window.MusicPlayer.dockVisible,
        })`);
        console.log(`      Canción: "${finalSong.title}" (${finalSong.artist}) | Duración: ${finalSong.duration}s | Dock: ${finalSong.dockVisible ? 'Abierto' : 'Cerrado'}`);

        console.log('\n🎉 ¡TODAS LAS COMPROBACIONES EN NAVEGADOR PASARON CON ÉXITO!');
    } catch (err) {
        console.error('❌ Error en comprobación de navegador:', err);
        process.exitCode = 1;
    } finally {
        if (cdp) cdp.close();
        chrome.kill();
    }
}

run();
