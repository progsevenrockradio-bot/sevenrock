export function registerAlbumPreview(Alpine) {
    Alpine.data('albumPreview', () => ({
        currentTrack: null,
        playing: false,
        loading: false,
        progress: 0,
        elapsed: 0,
        duration: 0,
        previewDuration: 30,
        audio: null,

        init() {
            // Will be set up when user clicks play
        },

        play(trackIndex, previewUrl) {
            // Stop current playback if any
            this.stop();

            if (!previewUrl) return;

            this.currentTrack = trackIndex;
            this.loading = true;

            // Create audio element
            const audio = new Audio(previewUrl);
            audio.preload = 'auto';

            audio.addEventListener('canplaythrough', () => {
                this.loading = false;
                audio.play().then(() => {
                    this.playing = true;
                }).catch(() => {
                    this.playing = false;
                    this.loading = false;
                });
            }, { once: true });

            audio.addEventListener('timeupdate', () => {
                this.elapsed = audio.currentTime;
                this.duration = Math.min(audio.duration || this.previewDuration, this.previewDuration);
                this.progress = audio.duration > 0 ? (audio.currentTime / this.previewDuration) * 100 : 0;

                // Stop at 30 seconds
                if (audio.currentTime >= this.previewDuration) {
                    this.stop();
                }
            });

            audio.addEventListener('ended', () => {
                this.stop();
            });

            audio.addEventListener('error', () => {
                this.loading = false;
                this.playing = false;
            });

            this.audio = audio;
        },

        toggle(trackIndex, previewUrl) {
            if (this.currentTrack === trackIndex && this.playing) {
                this.pause();
            } else {
                this.play(trackIndex, previewUrl);
            }
        },

        pause() {
            if (this.audio) {
                this.audio.pause();
            }
            this.playing = false;
        },

        stop() {
            if (this.audio) {
                this.audio.pause();
                this.audio.currentTime = 0;
                this.audio = null;
            }
            this.playing = false;
            this.currentTrack = null;
            this.progress = 0;
            this.elapsed = 0;
            this.duration = 0;
            this.loading = false;
        },

        seek(event) {
            if (!this.audio || this.currentTrack === null) return;
            const rect = event.currentTarget.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const ratio = Math.max(0, Math.min(1, x / rect.width));
            this.audio.currentTime = ratio * this.previewDuration;
        },

        formatTime(seconds) {
            const m = Math.floor(seconds / 60);
            const s = Math.floor(seconds % 60);
            return `${m}:${s.toString().padStart(2, '0')}`;
        },

        get isPlaying() {
            return this.playing;
        },

        get currentIndex() {
            return this.currentTrack;
        }
    }));
}
