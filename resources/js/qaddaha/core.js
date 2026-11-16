// قدّها؟ — shared helpers: DOM, storage, device id, toast, sounds (Web Audio, no files), vibration.
export const config = window.QD || {};

export const $ = (s, r = document) => r.querySelector(s);
export const $$ = (s, r = document) => [...r.querySelectorAll(s)];

export function el(tag, attrs = {}, kids = []) {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (v === null || v === undefined || v === false) continue;
        if (k === 'class') n.className = v;
        else if (k === 'text') n.textContent = v;
        else if (k === 'html') n.innerHTML = v;
        else if (k === 'style' && typeof v === 'object') Object.assign(n.style, v);
        else if (k.startsWith('on') && typeof v === 'function') n.addEventListener(k.slice(2), v);
        else n.setAttribute(k, v === true ? '' : v);
    }
    for (const c of [].concat(kids)) {
        if (c === null || c === undefined || c === false) continue;
        n.append(c.nodeType ? c : document.createTextNode(String(c)));
    }
    return n;
}

export const store = {
    get(k, d = null) { try { const r = localStorage.getItem('qaddaha.' + k); return r === null ? d : JSON.parse(r); } catch { return d; } },
    set(k, v) { try { localStorage.setItem('qaddaha.' + k, JSON.stringify(v)); } catch { /* private mode / full */ } },
    del(k) { try { localStorage.removeItem('qaddaha.' + k); } catch { /* ignore */ } },
};

export function randomId(n = 12) {
    const bytes = (window.crypto && crypto.getRandomValues) ? crypto.getRandomValues(new Uint8Array(n)) : Uint8Array.from({ length: n }, () => Math.floor(Math.random() * 256));
    return [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** Random id that identifies this browser (no accounts). */
export function deviceId() {
    let id = store.get('device');
    if (!id || !/^[A-Za-z0-9_-]{8,64}$/.test(id)) {
        id = 'd-' + randomId(12);
        store.set('device', id);
    }
    return id;
}

let toastTimer;
export function toast(msg, error = false) {
    const n = $('#toast');
    if (!n) return;
    n.textContent = msg;
    n.classList.toggle('error', error);
    n.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => n.classList.remove('show'), 2600);
}

export const vibrate = (p) => { try { navigator.vibrate?.(p); } catch { /* not supported */ } };

/* ───────── sounds ───────── */
let ctx = null;
export const soundOn = () => store.get('sound', true);
export function setSound(on) { store.set('sound', !!on); }

function audio() {
    if (!ctx) {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        ctx = new AC();
    }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
}

function tone(freq, at, dur, { type = 'sine', gain = 0.14, to = null } = {}) {
    const a = audio();
    if (!a) return;
    const o = a.createOscillator();
    const g = a.createGain();
    const t = a.currentTime + at;
    o.type = type;
    o.frequency.setValueAtTime(freq, t);
    if (to) o.frequency.exponentialRampToValueAtTime(to, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(gain, t + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g).connect(a.destination);
    o.start(t);
    o.stop(t + dur + 0.02);
}

function noise(at, dur, gain = 0.2) {
    const a = audio();
    if (!a) return;
    const len = Math.floor(a.sampleRate * dur);
    const buf = a.createBuffer(1, len, a.sampleRate);
    const d = buf.getChannelData(0);
    for (let i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * (1 - i / len) ** 3;
    const src = a.createBufferSource();
    const g = a.createGain();
    const f = a.createBiquadFilter();
    f.type = 'lowpass';
    f.frequency.value = 900;
    g.gain.value = gain;
    src.buffer = buf;
    src.connect(f).connect(g).connect(a.destination);
    src.start(a.currentTime + at);
}

const SOUNDS = {
    click: () => tone(660, 0, 0.05, { type: 'triangle', gain: 0.06 }),
    bid: () => { tone(520, 0, 0.08, { type: 'square', gain: 0.05 }); tone(780, 0.07, 0.12, { type: 'square', gain: 0.05 }); },
    sold: () => { noise(0, 0.18, 0.35); tone(140, 0, 0.18, { type: 'sine', gain: 0.25 }); tone(1046, 0.16, 0.5, { gain: 0.08 }); tone(1318, 0.24, 0.6, { gain: 0.06 }); },
    tick: () => tone(1500, 0, 0.03, { type: 'square', gain: 0.03 }),
    tock: () => tone(1900, 0, 0.04, { type: 'square', gain: 0.05 }),
    pop: () => tone(880, 0, 0.09, { type: 'triangle', gain: 0.12, to: 1320 }),
    undo: () => tone(500, 0, 0.1, { type: 'triangle', gain: 0.08, to: 300 }),
    success: () => [523, 659, 784, 1046].forEach((f, i) => tone(f, i * 0.1, 0.35, { type: 'triangle', gain: 0.12 })),
    fail: () => { tone(330, 0, 0.3, { type: 'sawtooth', gain: 0.07, to: 220 }); tone(220, 0.28, 0.5, { type: 'sawtooth', gain: 0.07, to: 110 }); },
    reveal: () => { tone(200, 0, 0.6, { type: 'sine', gain: 0.08, to: 900 }); noise(0, 0.5, 0.05); },
    streak: () => [392, 523, 659, 784, 1046, 1318].forEach((f, i) => tone(f, i * 0.07, 0.3, { type: 'square', gain: 0.05 })),
    turn: () => { tone(740, 0, 0.12, { gain: 0.1 }); tone(988, 0.12, 0.18, { gain: 0.1 }); },
    freeze: () => { tone(1800, 0, 0.5, { type: 'sine', gain: 0.06, to: 600 }); tone(2400, 0.05, 0.4, { type: 'sine', gain: 0.04, to: 900 }); },
};

export function play(name) {
    if (!soundOn()) return;
    try { SOUNDS[name]?.(); } catch { /* audio not available */ }
}

/** Arabic error messages for rule and server codes. */
export const ERRORS = {
    not_your_turn: 'مش دوركم.',
    wrong_phase: 'مش وقته هلأ.',
    bid_too_low: 'لازم الرقم يكون أعلى.',
    bid_too_high: 'الرقم كبير زيادة.',
    bad_bid: 'رقم مش صحيح.',
    nothing_to_withdraw: 'لازم أحد يزايد أول.',
    nothing_to_call: 'لازم أحد يزايد أول.',
    no_bluffs_left: 'خلصت مرّات «ورّني!».',
    help_used: 'هالمساعدة استعملتوها.',
    timer_running: 'الوقت بلّش.',
    not_challenger: 'هالمساعدة لفريق المتحدي.',
    cannot_steal_own: 'ما بتقدر تسرق مزادك.',
    already_stolen: 'المزاد انسرق قبل.',
    no_steal_after_bluff: 'ما في سرقة بعد «ورّني!».',
    not_your_column: 'اختار من عمود فريقك.',
    cell_used: 'هالخانة انلعبت.',
    already_picked: 'هالفئة انختارت.',
    bad_bet: 'الرهان لازم يكون بين صفر ونقاط فريقك.',
    bet_locked: 'الرهان ثابت.',
    bets_missing: 'لسا في فريق ما ثبّت رهانه.',
    nothing_to_undo: 'ما في شي نرجع عنه.',
    missing_question: 'ما لقينا أسئلة كفاية لهالفئات.',
    pool_too_small: 'ما في فئات كفاية.',
    no_room: 'ما لقينا هالغرفة. تأكد من الكود.',
    seat_taken: 'في جوال تاني ماسك هالفريق.',
    not_seated: 'انضم لفريق أول.',
    host_only: 'هالشي بس لشاشة المضيف.',
    duplicate: '',
    network: 'في مشكلة بالاتصال.',
};

export const errorText = (code) => ERRORS[code] ?? 'ما زبطت.';
