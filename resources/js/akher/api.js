// آخر القائمة — every server call goes through here (swap this file to change the backend).
import { config, deviceId } from './core.js';

async function call(method, path, body) {
    const res = await fetch(config.base + '/api' + path, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body ? JSON.stringify(body) : undefined,
        cache: 'no-store',
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const e = new Error(data.error || data.message || 'error');
        e.status = res.status;
        e.code = data.message;
        throw e;
    }
    return data;
}

const dev = () => ({ device: deviceId() });

export const createRoom = (name) => call('POST', '/rooms', { name, ...dev() });
export const joinRoom = (code, name) => call('POST', `/rooms/${code}/join`, { name, ...dev() });
export const getState = (code, since) => call('GET', `/rooms/${code}?device=${deviceId()}${since ? '&since=' + since : ''}`);
export const addPlayer = (code, name, team) => call('POST', `/rooms/${code}/players`, { name, team, ...dev() });
export const switchTeam = (code, id) => call('POST', `/rooms/${code}/players/${id}/switch`, dev());
export const removePlayer = (code, id) => call('POST', `/rooms/${code}/players/${id}/remove`, dev());
export const saveSettings = (code, settings) => call('POST', `/rooms/${code}/settings`, { ...settings, ...dev() });
export const guess = (code, turnIdx, text) => call('POST', `/rooms/${code}/guess`, { turn_idx: turnIdx, text, ...dev() });
export const expireTurn = (code, turnIdx) => call('POST', `/rooms/${code}/expire`, { turn_idx: turnIdx });
export const action = (code, name) => call('POST', `/rooms/${code}/${name}`, dev()); // start | skip | end | reset | leave
