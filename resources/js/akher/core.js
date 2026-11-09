// آخر القائمة — shared helpers: strings, storage, device id, toast, sound, confetti.
const AK = window.AK || { strings: {} };
export const config = AK;

export function S(key, vars = {}) {
    let v = key.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : undefined), AK.strings);
    if (v === undefined) return key;
    if (typeof v === 'string') for (const [k, x] of Object.entries(vars)) v = v.replaceAll(':' + k, x);
    return v;
}

export const store = {
    get(k, d = null) { try { const r = localStorage.getItem('akher.' + k); return r === null ? d : JSON.parse(r); } catch { return d; } },
    set(k, v) { try { localStorage.setItem('akher.' + k, JSON.stringify(v)); } catch { /* private mode */ } },
    del(k) { try { localStorage.removeItem('akher.' + k); } catch { /* ignore */ } },
};

/** Random id that identifies this browser (no accounts). */
export function deviceId() {
    let id = store.get('device');
    if (!id || !/^[A-Za-z0-9_-]{8,64}$/.test(id)) {
        const bytes = crypto.getRandomValues(new Uint8Array(12));
        id = 'd-' + [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
        store.set('device', id);
    }
    return id;
}

export const $ = (s, r = document) => r.querySelector(s);
export const $$ = (s, r = document) => [...r.querySelectorAll(s)];

export function el(tag, attrs = {}, kids = []) {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (v === null || v === undefined || v === false) continue;
        if (k === 'class') n.className = v;
        else if (k === 'text') n.textContent = v;
        else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
        else n.setAttribute(k, v === true ? '' : v);
    }
    for (const c of [].concat(kids)) if (c !== null && c !== undefined && c !== false) n.append(c.nodeType ? c : document.createTextNode(c));
    return n;
}

let tt;
export function toast(msg, error = false) {
    const n = $('#toast');
    if (!n) return;
    n.textContent = msg;
    n.classList.toggle('error', error);
    n.classList.add('show');
    clearTimeout(tt);
    tt = setTimeout(() => n.classList.remove('show'), 2600);
}

// ───────── Theme ─────────
export function initTheme() {
    $$('[data-theme-toggle]').forEach((b) => b.addEventListener('click', () => {
        const dark = getComputedStyle(document.documentElement).colorScheme === 'dark';
        const next = dark ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('akher.theme', next); } catch { /* ignore */ }
    }));
}

// ───────── Sound (Web Audio, no files) ─────────
let ctx = null;
const soundOn = () => store.get('sound', true);
function audio() {
    if (!ctx) { const AC = window.AudioContext || window.webkitAudioContext; if (!AC) return null; ctx = new AC(); }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
}
function tone(f, at, dur, { type = 'sine', gain = 0.12, to = null } = {}) {
    if (!soundOn()) return;
    const a = audio();
    if (!a) return;
    const o = a.createOscillator(), g = a.createGain(), t = a.currentTime + at;
    o.type = type; o.frequency.setValueAtTime(f, t);
    if (to) o.frequency.exponentialRampToValueAtTime(to, t + dur);
    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(gain, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g).connect(a.destination); o.start(t); o.stop(t + dur + 0.05);
}
export const sfx = {
    right: () => { tone(523, 0, 0.14, { type: 'triangle' }); tone(784, 0.12, 0.26, { type: 'triangle' }); },
    wrong: () => tone(220, 0, 0.35, { type: 'sawtooth', gain: 0.06, to: 110 }),
    yourTurn: () => { tone(660, 0, 0.12); tone(880, 0.13, 0.18); },
    tick: () => tone(1200, 0, 0.05, { type: 'square', gain: 0.03 }),
    win: () => [523, 659, 784, 1047, 784, 1047].forEach((f, i) => tone(f, i * 0.13, 0.3, { type: 'triangle' })),
};
export function initSound() {
    const paint = () => $$('[data-sound-toggle]').forEach((b) => { b.classList.toggle('off', !soundOn()); b.setAttribute('aria-pressed', String(soundOn())); });
    $$('[data-sound-toggle]').forEach((b) => b.addEventListener('click', () => { store.set('sound', !soundOn()); paint(); if (soundOn()) sfx.yourTurn(); }));
    paint();
}

// ───────── Confetti in the winner's colour ─────────
export function confetti(color) {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const c = el('canvas', { class: 'confetti' });
    document.body.append(c);
    const g = c.getContext('2d');
    c.width = innerWidth; c.height = innerHeight;
    const colors = [color, color, '#ffaa33', '#ffffff'];
    const ps = Array.from({ length: 160 }, () => ({ x: Math.random() * c.width, y: -Math.random() * c.height * 0.6, w: 6 + Math.random() * 7, h: 8 + Math.random() * 9, vy: 2 + Math.random() * 4, vx: -2 + Math.random() * 4, r: Math.random() * 6, vr: -0.2 + Math.random() * 0.4, k: colors[Math.floor(Math.random() * colors.length)] }));
    const end = performance.now() + 3500;
    (function f(now) {
        g.clearRect(0, 0, c.width, c.height);
        for (const p of ps) { p.x += p.vx; p.y += p.vy; p.r += p.vr; g.save(); g.translate(p.x, p.y); g.rotate(p.r); g.fillStyle = p.k; g.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); g.restore(); }
        if (now < end) requestAnimationFrame(f); else c.remove();
    })(performance.now());
}
