// قدّها؟ — every server call goes through here.
import { config, deviceId } from './core.js';

async function call(method, path, body) {
    let res;
    try {
        res = await fetch(config.base + '/api' + path, {
            method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined,
            cache: 'no-store',
            credentials: 'same-origin',
        });
    } catch {
        const e = new Error('network');
        e.code = 'network';
        throw e;
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const e = new Error(data.message || 'error');
        e.status = res.status;
        e.code = data.message || 'error';
        throw e;
    }
    return data;
}

export const categories = () => call('GET', '/categories');
export const questions = (cats, levels, exclude) => call('POST', '/questions', { cats, levels, exclude });
export const logPlay = (data) => call('POST', '/play', data).catch(() => {});

// host
export const createRoom = () => call('POST', '/rooms', {});
export const hostPoll = (code, token, after, full = false) =>
    call('GET', `/rooms/${code}/host?token=${encodeURIComponent(token)}&after=${after}${full ? '&full=1' : ''}`);
export const saveState = (code, token, state, pub, ack, finished) =>
    call('POST', `/rooms/${code}/state`, { token, state, public: pub, ack, finished });
export const freeSeat = (code, token, team) => call('POST', `/rooms/${code}/seats/${team}/free`, { token });

// phones
export const joinRoom = (code, team) => call('POST', `/rooms/${code}/join`, { device: deviceId(), team });
export const roomState = (code, since) => call('GET', `/rooms/${code}?device=${deviceId()}&since=${since}`);
export const sendIntent = (code, intent) => call('POST', `/rooms/${code}/intent`, { device: deviceId(), intent });
