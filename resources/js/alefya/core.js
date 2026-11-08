// ألف ياء — shared helpers: strings, storage, modals, toast, sound, confetti.

const AY = window.AY || { strings: {} };

/** Interface text from strings.ar.json: S('play.show_answer'), S('play.round_won', { team: 'x' }). */
export function S(key, replace = {}) {
    let value = key.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : undefined), AY.strings);
    if (value === undefined) return key;
    if (typeof value === 'string') {
        for (const [k, v] of Object.entries(replace)) value = value.replaceAll(':' + k, v);
    }
    return value;
}

export const config = AY;
export const url = (path = '') => AY.base + path;

/** localStorage that never throws (private windows, blocked storage). */
export const store = {
    get(key, fallback = null) {
        try {
            const raw = localStorage.getItem('ay.' + key);
            return raw === null ? fallback : JSON.parse(raw);
        } catch {
            return fallback;
        }
    },
    set(key, value) {
        try { localStorage.setItem('ay.' + key, JSON.stringify(value)); } catch { /* ignore */ }
    },
    remove(key) {
        try { localStorage.removeItem('ay.' + key); } catch { /* ignore */ }
    },
};

export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

export function el(tag, attrs = {}, children = []) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (v === null || v === undefined || v === false) continue;
        if (k === 'class') node.className = v;
        else if (k === 'text') node.textContent = v;
        else if (k === 'html') node.innerHTML = v;
        else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
        else node.setAttribute(k, v === true ? '' : v);
    }
    for (const child of [].concat(children)) {
        if (child !== null && child !== undefined) node.append(child.nodeType ? child : document.createTextNode(child));
    }
    return node;
}

export const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// ───────── Toast ─────────
let toastTimer;
export function toast(message, error = false) {
    const node = $('#toast');
    if (!node) return;
    node.textContent = message;
    node.classList.toggle('error', error);
    node.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => node.classList.remove('show'), 2600);
}

// ───────── Modals ─────────
let lastFocus = null;

export function openModal(id) {
    const modal = typeof id === 'string' ? document.getElementById(id) : id;
    if (!modal) return;
    lastFocus = document.activeElement;
    modal.classList.add('open');
    const focusable = modal.querySelector('button, a, input, textarea');
    focusable?.focus({ preventScroll: true });
}

export function closeModal(id) {
    const modal = typeof id === 'string' ? document.getElementById(id) : id;
    if (!modal) return;
    modal.classList.remove('open');
    lastFocus?.focus?.({ preventScroll: true });
}

export function isModalOpen() {
    return !!document.querySelector('.modal.open');
}

function bindModals() {
    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-open]');
        if (opener) { e.preventDefault(); openModal(opener.dataset.open); return; }

        const closer = e.target.closest('[data-close]');
        if (closer) { closeModal(closer.closest('.modal')); return; }

        if (e.target.classList?.contains('modal') && !e.target.dataset.locked) closeModal(e.target);

        const copy = e.target.closest('[data-copy]');
        if (copy) copyText(copy.dataset.copy);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const open = document.querySelector('.modal.open:not([data-locked])');
            if (open) closeModal(open);
        }
    });
}

export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const t = el('textarea', { style: 'position:fixed;opacity:0' }, text);
        document.body.append(t);
        t.select();
        document.execCommand('copy');
        t.remove();
    }
    toast(S('app.copied'));
}

// ───────── Sound (Web Audio, no files) ─────────
let ctx = null;
const audio = () => {
    if (!ctx) {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        ctx = new AC();
    }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
};

function tone(freq, start, duration, { type = 'sine', gain = 0.12, slide = null } = {}) {
    const a = audio();
    if (!a) return;
    const osc = a.createOscillator();
    const g = a.createGain();
    const t = a.currentTime + start;
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t);
    if (slide) osc.frequency.exponentialRampToValueAtTime(slide, t + duration);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(gain, t + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, t + duration);
    osc.connect(g).connect(a.destination);
    osc.start(t);
    osc.stop(t + duration + 0.05);
}

export const sfx = {
    select: () => tone(660, 0, 0.12, { type: 'triangle', gain: 0.1 }),
    correct: () => { tone(523, 0, 0.14, { type: 'triangle' }); tone(784, 0.12, 0.25, { type: 'triangle' }); },
    wrong: () => tone(220, 0, 0.35, { type: 'sawtooth', gain: 0.06, slide: 110 }),
    tick: () => tone(1200, 0, 0.05, { type: 'square', gain: 0.03 }),
    win: () => [523, 659, 784, 1047, 784, 1047].forEach((f, i) => tone(f, i * 0.13, 0.3, { type: 'triangle', gain: 0.12 })),
    whoosh: () => tone(300, 0, 0.5, { type: 'sine', gain: 0.08, slide: 900 }),
};

// Background music: a soft pentatonic loop scheduled on the audio clock.
const music = { timer: null, step: 0, next: 0 };
const MELODY = [392, 440, 523, 587, 659, 587, 523, 440, 392, 330, 392, 440, 523, 440, 392, 330];
const BASS = [196, 196, 220, 220, 262, 262, 220, 220];

function scheduleMusic() {
    const a = audio();
    if (!a) return;
    while (music.next < a.currentTime + 0.4) {
        const at = music.next - a.currentTime;
        tone(MELODY[music.step % MELODY.length], Math.max(at, 0), 0.32, { type: 'sine', gain: 0.035 });
        if (music.step % 2 === 0) tone(BASS[(music.step / 2) % BASS.length], Math.max(at, 0), 0.6, { type: 'triangle', gain: 0.03 });
        music.next += 0.3;
        music.step++;
    }
}

export function setMusic(on) {
    store.set('music', on);
    clearInterval(music.timer);
    music.timer = null;
    if (on) {
        const a = audio();
        if (!a) return;
        music.next = a.currentTime + 0.05;
        music.timer = setInterval(scheduleMusic, 120);
        scheduleMusic();
    }
    $$('#musicBtn').forEach((b) => { b.classList.toggle('off', !on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
}

function bindMusic() {
    const on = store.get('music', false);
    $$('#musicBtn').forEach((b) => {
        b.classList.toggle('off', !on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.addEventListener('click', () => setMusic(!store.get('music', false)));
    });
    // Browsers only start audio after a user gesture: resume music on the first tap.
    if (on) document.addEventListener('pointerdown', () => store.get('music', false) && !music.timer && setMusic(true), { once: true });
}

// ───────── Confetti ─────────
export function confetti(duration = 3500) {
    const canvas = el('canvas', { class: 'confetti' });
    document.body.append(canvas);
    const c = canvas.getContext('2d');
    const resize = () => { canvas.width = innerWidth; canvas.height = innerHeight; };
    resize();
    const colors = ['#FFDD00', '#26C6DA', '#FF5252', '#3BB61B', '#FF7A2F', '#ffffff'];
    const parts = Array.from({ length: 180 }, () => ({
        x: Math.random() * canvas.width, y: -20 - Math.random() * canvas.height * 0.5,
        w: 6 + Math.random() * 8, h: 8 + Math.random() * 10, vy: 2 + Math.random() * 4, vx: -2 + Math.random() * 4,
        r: Math.random() * Math.PI, vr: -0.2 + Math.random() * 0.4, color: colors[Math.floor(Math.random() * colors.length)],
    }));
    const end = performance.now() + duration;
    (function frame(now) {
        c.clearRect(0, 0, canvas.width, canvas.height);
        for (const p of parts) {
            p.x += p.vx; p.y += p.vy; p.r += p.vr;
            if (p.y > canvas.height + 20 && now < end) { p.y = -20; p.x = Math.random() * canvas.width; }
            c.save(); c.translate(p.x, p.y); c.rotate(p.r); c.fillStyle = p.color; c.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); c.restore();
        }
        if (now < end + 2500) requestAnimationFrame(frame); else canvas.remove();
    })(performance.now());
}

export function hexPoints(cx, cy, r) {
    const pts = [];
    for (let i = 0; i < 6; i++) {
        const a = (Math.PI / 180) * (60 * i - 90);
        pts.push(`${(cx + r * Math.cos(a)).toFixed(1)},${(cy + r * Math.sin(a)).toFixed(1)}`);
    }
    return pts.join(' ');
}

export function initCommon() {
    bindModals();
    bindMusic();
}
