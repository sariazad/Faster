// ألف ياء — login IDs (10 digits) + 4-digit PIN. Demo IDs for now; replace these calls to plug in a real backend.
import { post, get } from './api.js';
import { config } from './core.js';

export const currentCode = () => config.account || null;
export const login = (code, pin) => post('/login', { code, pin });
export const logout = () => post('/logout');
export const demoCode = () => post('/demo-code');
export const me = () => get('/me');
