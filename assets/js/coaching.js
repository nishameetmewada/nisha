(() => {
    'use strict';
    const players = [...document.querySelectorAll('[data-coaching-video-id]')];
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    for (const player of players) {
        const videoId = Number(player.dataset.coachingVideoId);
        const status = document.querySelector(`[data-coaching-progress="${videoId}"]`);
        let watchToken = '', busy = false, starting = null;
        const show = message => { if (status) status.textContent = message; };
        async function request(payload) {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 15000);
            try {
                const result = await fetch('api/video-progress.php', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': token }, body: JSON.stringify(payload), signal: controller.signal });
                let data;
                try { data = await result.json(); } catch (_) { throw new Error('Watch progress could not be confirmed. You can keep learning; play again to reconnect tracking.'); }
                if (!result.ok || !data.success) throw new Error(typeof data.error === 'string' ? data.error : 'Watch progress is temporarily unavailable.');
                return data;
            } finally { clearTimeout(timer); }
        }
        async function start() {
            if (starting) return starting;
            starting = (async () => {
                try {
                    const data = await request({ action: 'start', video_id: videoId, position: Number.isFinite(player.currentTime) ? player.currentTime : 0 });
                    watchToken = data.token;
                    show(data.message);
                } catch (_) { watchToken = ''; show('Watch progress is temporarily unavailable. You can keep learning; play again to reconnect.'); }
                finally { starting = null; }
            })();
            return starting;
        }
        async function heartbeat(wasPlaying = false) {
            if (busy || !watchToken || !Number.isFinite(player.currentTime) || player.seeking) return;
            const playing = wasPlaying || (!player.paused && !player.ended);
            if (!playing || document.visibilityState !== 'visible') return;
            busy = true;
            try {
                const data = await request({ action: 'heartbeat', video_id: videoId, token: watchToken, position: player.currentTime, playing, visible: true });
                show(data.message);
            } catch (_) { watchToken = ''; show('Watch progress could not be confirmed. Play again to reconnect; unverified time does not add reward views.'); }
            finally { busy = false; }
        }
        player.addEventListener('play', () => {
            for (const other of players) if (other !== player && !other.paused) other.pause();
            // Starting a lesson claims the student's single active watch lease.
            start();
        });
        player.addEventListener('pause', () => heartbeat(true));
        player.addEventListener('ended', () => heartbeat(true));
        player.addEventListener('error', () => show('This video could not be loaded. The teacher or site owner may need to update its hosting link.'));
        document.addEventListener('visibilitychange', () => { if (document.hidden && !player.paused) player.pause(); });
        setInterval(() => { if (!player.paused && !player.ended && !player.seeking && !document.hidden) heartbeat(); }, 10000);
    }
})();
