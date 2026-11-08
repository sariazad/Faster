// ألف ياء — optional link between the questions portal and the big screen.
//  • Same browser (two tabs): BroadcastChannel, instant.
//  • Two devices: both signed in with the same login ID; the portal posts the letter to the
//    server and the board polls it every ~1.5s. Turn it on from «ربط اللوحة بالبوابة».
import { get, post } from './api.js';

const channel = 'BroadcastChannel' in window ? new BroadcastChannel('alefya') : null;

/** Called by the portal whenever the host picks a letter. */
export function publishLetter(letter, signedIn) {
    channel?.postMessage({ letter, at: Date.now() });
    if (signedIn) post('/sync', { letter }).catch(() => {});
}

/** Called by the board; onLetter(letter) fires for each new pick. Returns a stop function. */
export function listen(onLetter, { poll = true, interval = 1500 } = {}) {
    let last = Date.now();
    const handler = (e) => {
        if (e.data?.at > last) { last = e.data.at; onLetter(e.data.letter); }
    };
    channel?.addEventListener('message', handler);

    let timer = null;
    let stopped = false;
    if (poll) {
        const tick = async () => {
            try {
                const data = await get('/sync');
                if (data.letter && data.at > last) { last = data.at; onLetter(data.letter); }
            } catch (e) {
                if (e.status === 401) { stopped = true; return; }
            }
            if (!stopped) timer = setTimeout(tick, interval);
        };
        timer = setTimeout(tick, interval);
    }

    return () => { stopped = true; clearTimeout(timer); channel?.removeEventListener('message', handler); };
}
