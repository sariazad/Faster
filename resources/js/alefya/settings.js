// ألف ياء — contest settings saved in localStorage («تخصيص المسابقة»).
import { S, store } from './core.js';

export const defaults = () => ({
    contestName: '',
    team1: S('customize.team1'),
    team2: S('customize.team2'),
    rounds: 1,
    size: 5,
    autoWin: true,
    colors: 0,
});

export function loadSettings() {
    return { ...defaults(), ...(store.get('settings', {}) || {}) };
}

export function saveSettings(settings) {
    store.set('settings', settings);
}

export function colorPair(index) {
    const pairs = S('colors');
    return pairs[index] || pairs[0];
}
