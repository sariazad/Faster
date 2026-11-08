// ألف ياء — thin layer over the Laravel JSON API (swap these functions to change the backend).
import { url, store } from './core.js';

const headers = () => ({
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
    'X-Requested-With': 'XMLHttpRequest',
});

async function request(method, path, body) {
    const res = await fetch(url('/api' + path), {
        method,
        headers: headers(),
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined,
        cache: 'no-store',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const error = new Error(data.message || 'request-failed');
        error.status = res.status;
        error.data = data;
        throw error;
    }
    return data;
}

export const get = (path) => request('GET', path);
export const post = (path, body = {}) => request('POST', path, body);

/**
 * A question for the letter that hasn't been shown on this device yet; starts over when the
 * letter runs out. letter = null → any letter (تحدي المجهول).
 */
export async function getQuestion(letter, reset = false) {
    const key = letter || '*';
    const seen = store.get('seen', {});
    if (reset) seen[key] = [];
    const exclude = (seen[key] || []).slice(-400).join(',');
    const params = new URLSearchParams({ exclude });
    if (letter) params.set('letter', letter);

    const data = await get('/question?' + params.toString());
    const list = data.reset ? [] : (seen[key] || []);
    list.push(data.question.id);
    seen[key] = list;
    store.set('seen', seen);

    return { ...data.question, reset: data.reset };
}

export const soloQuestions = () => get('/solo');
export const rateQuestion = (id, level) => post('/rate', { id, level });
export const sendNote = (id, body) => post('/note', { id, body });
export const contribute = (name, items) => post('/contribute', { name, items });
export const notifications = () => get('/notifications');
export const saveScore = (score) => post('/scores', score);
export const leaderboard = (code) => get('/leaderboard' + (code ? '?code=' + encodeURIComponent(code) : ''));
