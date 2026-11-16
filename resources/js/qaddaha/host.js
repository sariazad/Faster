// قدّها؟ — the host screen (TV / laptop). Runs the reducer, judges, and in multi-device games
// is the single authority: it applies the phones' intents and publishes the state.
import * as E from './engine.js';
import * as api from './api.js';
import QRCode from 'qrcode';
import { $, $$, config, el, errorText, play, randomId, setSound, soundOn, store, toast } from './core.js';
import { COLORS, EMBLEMS, HELP_LABELS, KIND_LABELS, fmt, helpsRow, levelName, lineText, nextBid, scorePill, secondsLeft, teamVars } from './shared.js';

const app = $('#app');
const KEY = 'session';
const POLL_MS = 800;

/** UI session: everything needed to resume after a reload. */
let S = store.get(KEY) || fresh();
let pool = null;          // categories from the server
let busy = false;         // a network step (questions, room) is running
let betStep = 0;          // single-device secret bets: which team is entering
let polling = null;
let pushing = false;
let pushAgain = false;
let lastTickSecond = null;

function fresh() {
    return {
        screen: 'setup',
        setup: {
            teams: [{ name: '', color: 'coral', emblem: '🦅' }, { name: '', color: 'teal', emblem: '🐯' }],
            kind: 'full',
            mode: 'single',
        },
        game: null,
        room: null,
        sid: null,
        startedAt: null,
    };
}

const save = () => store.set(KEY, S);
const G = () => S.game;
const multi = () => !!S.room;

/* ════════════════════════ actions ════════════════════════ */

function dispatch(action) {
    const before = G();
    const r = E.tryReduce(before, { now: Date.now(), ...action });
    if (r.error) {
        if (r.error !== 'duplicate') toast(errorText(r.error), true);
        return false;
    }
    S.game = r.state;
    effects(before, S.game, action);
    save();
    push();
    render();
    return true;
}

/** Sounds, stats and bookkeeping when the state moves. */
function effects(a, b, action) {
    if (!a) return;
    const t = action.type;
    if (t === 'RAISE') play('bid');
    if (t === 'COUNT') play(action.delta > 0 ? 'pop' : 'undo');
    if (t === 'FREEZE') play('freeze');
    if (t === 'STEAL' || t === 'MINUS2' || t === 'TOGGLE_DOUBLE') play('click');
    if ((a.phase === 'auction' && b.phase === 'challenge') || (a.phase === 'final_auction' && b.phase === 'final_challenge')) play('sold');
    if (b.phase === 'result' || b.phase === 'final_result') {
        if (a.phase !== b.phase) {
            const ok = b.current.result.success;
            play(ok ? 'success' : 'fail');
            if (b.current.result.lines.some((l) => l.key === 'streak')) setTimeout(() => play('streak'), 600);
        }
    }
    if (b.phase === 'final_reveal' && a.phase !== b.phase) play('reveal');
    if (t === 'START') {
        S.sid = randomId(10);
        S.startedAt = Date.now();
        beacon('start');
        const used = store.get('usedQ', []);
        const ids = b.cells.map((c) => c.q.id).concat(b.final?.q?.id).filter(Boolean);
        store.set('usedQ', [...used, ...ids].slice(-1500));
    }
    if (b.phase === 'finale' && a.phase !== 'finale') beacon('end');
    if (t === 'REMATCH') { S.sid = null; S.startedAt = null; }
}

function beacon(event) {
    const g = G();
    if (!g || !S.sid) return;
    const w = E.winner(g);
    api.logPlay({
        sid: S.sid,
        event,
        kind: g.kind,
        mode: multi() ? 'multi' : 'single',
        elapsed: Math.round((Date.now() - (S.startedAt || Date.now())) / 1000),
        names: g.teams.map((t) => t.name),
        scores: g.scores,
        played: g.history.length,
        winner: event === 'end' ? (w === null ? 'تعادل' : g.teams[w].name) : null,
    });
}

/* ════════════════════════ multi-device sync ════════════════════════ */

function publicPayload() {
    if (!G()) return { phase: 'lobby', teams: S.setup.teams, kind: S.setup.kind, sentAt: Date.now() };
    return { ...E.publicView(G()), sentAt: Date.now() };
}

async function push() {
    if (!multi()) return;
    if (pushing) { pushAgain = true; return; }
    pushing = true;
    try {
        do {
            pushAgain = false;
            await api.saveState(S.room.code, S.room.token, G() || { phase: 'lobby' }, publicPayload(), S.room.after || 0, G()?.phase === 'finale');
        } while (pushAgain);
    } catch (e) {
        if (e.status === 403 || e.status === 404) {
            toast('انتهت الغرفة. كمّلوا على جهاز واحد.', true);
            S.room = null;
            save();
            render();
        }
    } finally {
        pushing = false;
    }
}

async function poll() {
    if (!multi()) return;
    try {
        const r = await api.hostPoll(S.room.code, S.room.token, S.room.after || 0);
        const seatsChanged = JSON.stringify(r.seats) !== JSON.stringify(S.room.seats);
        S.room.seats = r.seats;
        let applied = false;
        for (const it of r.intents) {
            S.room.after = Math.max(S.room.after || 0, it.id);
            if (!G()) continue;
            const before = G();
            const res = E.applyIntent(before, it.intent, it.team, Date.now());
            if (!res.error) {
                S.game = res.state;
                effects(before, S.game, { ...it.intent, type: intentAction(it.intent.type), delta: 1 });
                applied = true;
            }
        }
        if (r.intents.length || seatsChanged) {
            save();
            if (r.intents.length) push();
            if (applied || seatsChanged) render();
        }
    } catch (e) {
        if (e.status === 403 || e.status === 404) {
            S.room = null;
            save();
            render();
        }
    }
}

const intentAction = (t) => ({ raise: 'RAISE', freeze: 'FREEZE', steal: 'STEAL', minus2: 'MINUS2', toggle_double: 'TOGGLE_DOUBLE' }[t] || t);

function startPolling() {
    if (polling) return;
    polling = setInterval(poll, POLL_MS);
}

function stopPolling() {
    clearInterval(polling);
    polling = null;
}

/* ════════════════════════ shell ════════════════════════ */

let lastView = '';

function render() {
    const keepScroll = window.scrollY;
    app.replaceChildren();
    const view = S.screen === 'game' && G() ? G().phase : S.screen;
    document.body.dataset.phase = view;
    document.body.classList.toggle('still', view === lastView);
    lastView = view;
    app.append(header());
    const screen = S.screen === 'game' && G() ? gameScreen() : ({ setup: setupScreen, settings: settingsScreen, lobby: lobbyScreen }[S.screen] || setupScreen)();
    app.append(el('main', { class: 'stage' }, screen));
    if (multi()) startPolling(); else stopPolling();
    tickTimer(true);
    if (document.body.classList.contains('still')) window.scrollTo(0, keepScroll);
}

function header() {
    const g = S.screen === 'game' ? G() : null;
    return el('header', { class: 'top' }, [
        g ? scorePill(g, 0, { active: turnTeam(g) === 0 }) : el('span'),
        el('div', { class: 'brand' }, [
            el('span', { class: 'logo', text: 'قدّها؟' }),
            multi() ? el('span', { class: 'room-tag', text: S.room.code, title: 'كود الغرفة' }) : null,
        ]),
        g ? scorePill(g, 1, { active: turnTeam(g) === 1 }) : el('span'),
        el('div', { class: 'tools' }, [
            el('button', { class: 'tool', title: soundOn() ? 'كتم الصوت' : 'تشغيل الصوت', 'aria-label': 'الصوت', text: soundOn() ? '🔊' : '🔇', onclick: () => { setSound(!soundOn()); render(); } }),
            el('button', { class: 'tool', title: 'القوانين', 'aria-label': 'القوانين', text: '؟', onclick: rules }),
            S.screen !== 'setup' ? el('button', { class: 'tool', title: 'لعبة جديدة', 'aria-label': 'لعبة جديدة', text: '⟲', onclick: newGameConfirm }) : null,
        ]),
    ]);
}

function turnTeam(g) {
    if (['board'].includes(g.phase)) return g.turn;
    if (g.phase === 'draft') return g.draft.order[g.draft.idx];
    if (['auction', 'final_auction'].includes(g.phase)) return g.current.toAct;
    if (['challenge', 'final_challenge'].includes(g.phase)) return g.current.challenger;
    return null;
}

function newGameConfirm() {
    modal('لعبة جديدة؟', [el('p', { text: 'الجولة الحالية رح تنمسح.' })], [
        { label: 'إيه، لعبة جديدة', cls: 'danger', fn: () => { stopPolling(); const setup = S.setup; S = fresh(); S.setup = { ...setup, teams: setup.teams }; save(); render(); } },
        { label: 'رجوع', cls: 'ghost' },
    ]);
}

function modal(title, body, buttons) {
    const close = () => wrap.remove();
    const wrap = el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', onclick: (e) => { if (e.target === wrap) close(); } }, [
        el('div', { class: 'modal-card' }, [
            el('h2', { text: title }),
            ...body,
            el('div', { class: 'row end' }, buttons.map((b) => el('button', { class: 'btn ' + (b.cls || ''), text: b.label, onclick: () => { const keep = b.fn?.(); if (keep !== true) close(); } }))),
        ]),
    ]);
    document.body.append(wrap);
    wrap.querySelector('button')?.focus();
    return wrap;
}

function rules() {
    modal('كيف نلعب «قدّها؟»', [
        el('ol', { class: 'rules' }, [
            'كل فريق بيختار فئاته بالتناوب، وبعدين بيبلّش اللعب على اللوحة.',
            'صاحب الدور بيختار خانة من عموده (200 / 400 / 600)، والدور بيتبدّل بعد كل سؤال.',
            'المزاد: كم إجابة بتقدروا تذكروا؟ زايد بـ «+1» أو اقفز لرقم أعلى، واللي بينسحب أول بيخسر المزاد.',
            'اللي رسى عليه المزاد لازم يذكر العدد قبل ما يخلص الوقت (30 / 45 / 60 ثانية). نجح: النقاط إله. فشل: النقاط كاملة للخصم.',
            '«ورّني!» بدل الانسحاب: إذا فشل المتحدي بياخد الكاشف النقاط +50%، وإذا نجح بياخد المتحدي النقاط +50%. مرتين لكل فريق.',
            'الجرأة: كل إجابة فوق 5 بالمزايدة بتزيد 10% على نقاط السؤال (لحد 50%). وإذا خلصتوا وباقي أكتر من نص الوقت: +50.',
            'مساعدات (مرة لكل فريق): مضاعفة ×2، خصم 2 من المطلوب، سرقة المزاد (+1 على المطلوب)، وتجميد الوقت 10 ثواني.',
            'خانة ذهبية مخبّاة باللوحة: نقاطها دبل للطرفين. و3 نجاحات ورا بعض = مولّع 🔥 و+100.',
            'الجولة الأخيرة: كل فريق بيراهن سراً بجزء من نقاطه، وبعدين مزاد عادي. الفايز بالتحدي بياخد رهانه، والتاني بيخسر رهانه.',
        ].map((t) => el('li', { text: t }))),
    ], [{ label: 'تمام', cls: 'gold' }]);
}

/* ════════════════════════ setup screens ════════════════════════ */

function setupScreen() {
    const st = S.setup;
    const teamCard = (i) => {
        const t = st.teams[i];
        return el('section', { class: 'card team-setup', style: teamVars(t) }, [
            el('h2', { text: i ? 'الفريق التاني' : 'الفريق الأول' }),
            el('label', { class: 'field' }, [
                el('span', { text: 'اسم الفريق' }),
                el('input', { id: `team${i}`, type: 'text', maxlength: 20, value: t.name, placeholder: i ? 'النمور' : 'الصقور', autocomplete: 'off',
                    oninput: (e) => { t.name = e.target.value; save(); } }),
            ]),
            el('div', { class: 'field' }, [
                el('span', { text: 'اللون' }),
                el('div', { class: 'swatches' }, Object.entries(COLORS).map(([k, c]) => el('button', {
                    class: 'swatch' + (t.color === k ? ' on' : ''),
                    style: `--sw:${c.main}`,
                    title: c.name,
                    'aria-label': c.name,
                    disabled: st.teams[1 - i].color === k,
                    onclick: () => { t.color = k; save(); render(); },
                }))),
            ]),
            el('div', { class: 'field' }, [
                el('span', { text: 'الشعار' }),
                el('div', { class: 'emblems' }, EMBLEMS.map((em) => el('button', {
                    class: 'emblem' + (t.emblem === em ? ' on' : ''),
                    text: em,
                    disabled: st.teams[1 - i].emblem === em,
                    onclick: () => { t.emblem = em; save(); render(); },
                }))),
            ]),
        ]);
    };

    return el('div', { class: 'screen setup' }, [
        el('div', { class: 'hero' }, [
            el('h1', { class: 'display', text: 'قدّها؟' }),
            el('p', { class: 'lead', text: 'كم إجابة بتقدروا تذكروا؟ زايدوا… بس انتبهوا: اللي بيزايد لازم يورّي.' }),
        ]),
        el('div', { class: 'grid2' }, [teamCard(0), teamCard(1)]),
        el('div', { class: 'row center' }, [
            el('button', { class: 'btn gold big', id: 'toSettings', text: 'التالي ←', onclick: () => {
                st.teams.forEach((t, i) => { t.name = (t.name || '').trim() || (i ? 'النمور' : 'الصقور'); });
                if (st.teams[0].name === st.teams[1].name) return toast('اختاروا اسمين مختلفين.', true);
                S.screen = 'settings';
                save();
                render();
            } }),
        ]),
    ]);
}

function settingsScreen() {
    const st = S.setup;
    const option = (group, key, title, note) => el('button', {
        class: 'option' + (st[group] === key ? ' on' : ''),
        'data-option': `${group}-${key}`,
        onclick: () => { st[group] = key; save(); render(); },
    }, [el('b', { text: title }), el('small', { text: note })]);

    return el('div', { class: 'screen settings' }, [
        el('h1', { class: 'title', text: 'الإعدادات' }),
        el('section', { class: 'card' }, [
            el('h2', { text: 'نوع اللعبة' }),
            el('div', { class: 'options' }, Object.entries(KIND_LABELS).map(([k, v]) => option('kind', k, v.name, v.note))),
        ]),
        el('section', { class: 'card' }, [
            el('h2', { text: 'طريقة اللعب' }),
            el('div', { class: 'options' }, [
                option('mode', 'single', 'جهاز واحد', 'كل التحكم من هالشاشة. بيشتغل بدون نت بعد ما تبلّش.'),
                option('mode', 'multi', 'أجهزة متعددة', 'الشاشة للعرض، وقائد كل فريق بيتحكم من جواله.'),
            ]),
        ]),
        el('div', { class: 'row center' }, [
            el('button', { class: 'btn ghost', text: '→ رجوع', onclick: () => { S.screen = 'setup'; save(); render(); } }),
            el('button', { class: 'btn gold big', id: 'toGame', text: busy ? '…' : (st.mode === 'multi' ? 'افتح الغرفة ←' : 'ابدأ ←'), disabled: busy, onclick: afterSettings }),
        ]),
    ]);
}

async function loadPool() {
    if (pool) return pool;
    const r = await api.categories();
    pool = r.categories;
    return pool;
}

async function afterSettings() {
    busy = true;
    render();
    try {
        await loadPool();
        if (S.setup.mode === 'multi') {
            const r = await api.createRoom();
            S.room = { code: r.code, token: r.token, after: 0, seats: [false, false] };
            S.screen = 'lobby';
            save();
            push();
        } else {
            startGame();
        }
    } catch (e) {
        toast(errorText(e.code), true);
    } finally {
        busy = false;
        render();
    }
}

function startGame() {
    const r = (() => {
        try {
            return E.newGame({ teams: S.setup.teams, kind: S.setup.kind, mode: S.setup.mode, pool });
        } catch (e) {
            toast(errorText(e.code), true);
            return null;
        }
    })();
    if (!r) return;
    S.game = r;
    S.screen = 'game';
    save();
    push();
    render();
}

function lobbyScreen() {
    const room = S.room;
    if (!room) { S.screen = 'settings'; return settingsScreen(); }
    const link = (i) => `${config.base}/join/${room.code}?team=${i + 1}`;
    const ready = room.seats[0] && room.seats[1];
    const card = (i) => {
        const t = S.setup.teams[i];
        const qr = el('div', { class: 'qr', 'data-qr': link(i) });
        return el('section', { class: 'card join-card', style: teamVars(t) }, [
            el('h2', {}, [t.emblem + ' ', t.name]),
            qr,
            el('a', { class: 'join-link', href: link(i), target: '_blank', rel: 'noopener', text: link(i).replace(/^https?:\/\//, '') }),
            el('div', { class: 'status ' + (room.seats[i] ? 'ok' : 'wait'), 'data-seat': i, text: room.seats[i] ? 'متصل ✓' : 'بانتظار القائد…' }),
            room.seats[i] ? el('button', { class: 'btn ghost small', text: 'افصل هالجوال', onclick: async () => {
                try { const r = await api.freeSeat(room.code, room.token, i); room.seats = r.seats; save(); render(); } catch (e) { toast(errorText(e.code), true); }
            } }) : null,
        ]);
    };
    setTimeout(drawQrs, 0);
    return el('div', { class: 'screen lobby' }, [
        el('h1', { class: 'title', text: 'غرفة الانتظار' }),
        el('p', { class: 'lead center' }, ['كود الغرفة: ', el('b', { class: 'code', id: 'roomCode', text: room.code }), ' — قائد كل فريق يمسح رمز فريقه.']),
        el('div', { class: 'grid2' }, [card(0), card(1)]),
        el('div', { class: 'row center' }, [
            el('button', { class: 'btn ghost', id: 'toSingle', text: 'التحويل لجهاز واحد', onclick: async () => { S.room = null; S.setup.mode = 'single'; stopPolling(); try { await loadPool(); startGame(); } catch (e) { toast(errorText(e.code), true); } } }),
            el('button', { class: 'btn gold big', id: 'lobbyStart', disabled: !ready, text: ready ? 'يلا نبدأ ←' : 'بانتظار الفريقين…', onclick: async () => { try { await loadPool(); startGame(); } catch (e) { toast(errorText(e.code), true); } } }),
        ]),
    ]);
}

function drawQrs() {
    $$('[data-qr]').forEach((n) => {
        if (n.dataset.drawn) return;
        n.dataset.drawn = '1';
        const canvas = document.createElement('canvas');
        QRCode.toCanvas(canvas, n.dataset.qr, { width: 220, margin: 1, color: { dark: '#0E1330', light: '#ffffff' }, errorCorrectionLevel: 'M' })
            .then(() => n.replaceChildren(canvas))
            .catch(() => { n.textContent = 'افتح الرابط تحت'; });
    });
}

/* ════════════════════════ game screens ════════════════════════ */

function gameScreen() {
    const g = G();
    const screens = {
        draft: draftScreen,
        review: reviewScreen,
        board: boardScreen,
        auction: auctionScreen,
        final_auction: auctionScreen,
        challenge: challengeScreen,
        final_challenge: challengeScreen,
        result: resultScreen,
        final_result: resultScreen,
        final_bet: betScreen,
        final_reveal: revealScreen,
        finale: finaleScreen,
    };
    return (screens[g.phase] || boardScreen)();
}

const teamName = (i) => G().teams[i].name;
const teamTag = (i, text) => el('span', { class: 'tteam', style: teamVars(G().teams[i]) }, text ?? `${G().teams[i].emblem} ${teamName(i)}`);

function phoneNote(i, what) {
    if (!multi()) return null;
    return el('p', { class: 'phone-note' }, ['📱 ', teamTag(i), ` ${what} من الجوال`]);
}

/** Host-only fallback controls for multi-device games (in case a phone drops). */
function fallback(kids) {
    if (!multi()) return el('div', { class: 'controls' }, kids);
    return el('details', { class: 'fallback' }, [el('summary', { text: 'تحكّم المضيف (احتياطي)' }), el('div', { class: 'controls' }, kids)]);
}

function draftScreen() {
    const g = G();
    const team = g.draft.order[g.draft.idx];
    const picked = new Map();
    g.draft.picks.forEach((ids, t) => ids.forEach((id) => picked.set(String(id), t)));
    const per = E.KINDS[g.kind].catsPerTeam;
    return el('div', { class: 'screen draft' }, [
        el('h1', { class: 'title' }, ['دور ', teamTag(team), ' يختار فئة']),
        el('p', { class: 'lead center', text: `كل فريق بيختار ${per === 1 ? 'فئة وحدة' : per + ' فئات'}. (${g.draft.idx + 1} من ${g.draft.order.length})` }),
        phoneNote(team, 'بيختار'),
        el('div', { class: 'cats' }, g.pool.map((c) => {
            const by = picked.get(String(c.id));
            return el('button', {
                class: 'cat' + (by !== undefined ? ' taken' : ''),
                style: by !== undefined ? teamVars(g.teams[by]) : '',
                disabled: by !== undefined,
                'data-cat': c.id,
                onclick: () => dispatch({ type: 'DRAFT_PICK', team, cat: c.id }),
            }, [el('span', { class: 'cat-icon', text: c.icon || '•' }), el('b', { text: c.name }), by !== undefined ? el('small', { text: g.teams[by].emblem + ' ' + g.teams[by].name }) : null]);
        })),
    ]);
}

function reviewScreen() {
    const g = G();
    const col = (t) => el('section', { class: 'card review-team', style: teamVars(g.teams[t]) }, [
        el('h2', { text: `${g.teams[t].emblem} ${g.teams[t].name}` }),
        el('ul', {}, g.draft.picks[t].map((id) => {
            const c = g.pool.find((p) => String(p.id) === String(id));
            return el('li', { text: `${c.icon || ''} ${c.name}` });
        })),
    ]);
    return el('div', { class: 'screen review' }, [
        el('h1', { class: 'title', text: 'جاهزين؟' }),
        el('div', { class: 'grid2' }, [col(0), col(1)]),
        el('p', { class: 'lead center', text: `${E.boardSize(g.kind)} سؤال على اللوحة + جولة أخيرة «كل شي أو لا شي».` }),
        el('div', { class: 'row center' }, [
            el('button', { class: 'btn ghost', text: 'أعيدوا الاختيار', onclick: () => dispatch({ type: 'DRAFT_RESET' }) }),
            el('button', { class: 'btn gold big', id: 'startBoard', disabled: busy, text: busy ? 'نجهّز الأسئلة…' : 'ابدأ اللعب ←', onclick: startBoard }),
        ]),
    ]);
}

async function startBoard() {
    const g = G();
    busy = true;
    render();
    try {
        const cats = g.draft.picks.flat();
        const r = await api.questions(cats, E.KINDS[g.kind].levels, store.get('usedQ', []));
        const ids = [];
        E.KINDS[g.kind].levels.forEach((l) => g.draft.picks.flat().forEach((_, ci) => [0, 1].forEach((t) => ids.push(E.cellId(ci, t, l)))));
        const golden = ids[Math.floor(Math.random() * ids.length)];
        busy = false;
        dispatch({ type: 'START', questions: r.questions, final: r.final, golden });
    } catch (e) {
        busy = false;
        toast(e.code === 'network' ? 'لازم نت لتحميل الأسئلة أول مرة.' : errorText(e.code === 'not_enough_questions' ? 'missing_question' : e.code), true);
        render();
    }
}

function boardScreen() {
    const g = G();
    const t = g.turn;
    const levels = E.KINDS[g.kind].levels;
    return el('div', { class: 'screen board-screen' }, [
        el('div', { class: 'turn-bar', style: teamVars(g.teams[t]) }, [
            el('span', {}, ['الدور على ', teamTag(t), ' — اختاروا خانة من عمودكم']),
            g.helps[t].double ? el('button', {
                class: 'btn double' + (g.pendingDouble ? ' on' : ''),
                id: 'doubleBtn',
                onclick: () => dispatch({ type: 'TOGGLE_DOUBLE', team: t }),
                text: g.pendingDouble ? 'المضاعفة ×2 مفعّلة ✓' : 'فعّل المضاعفة ×2',
            }) : null,
        ]),
        phoneNote(t, 'بيختار الخانة'),
        el('div', { class: 'board', style: `--cats:${g.cats.length}` }, g.cats.map((cat, ci) => el('div', { class: 'bcol' }, [
            el('div', { class: 'bhead' }, [el('span', { class: 'cat-icon', text: cat.icon || '' }), el('b', { text: cat.name })]),
            el('div', { class: 'bsplit' }, [0, 1].map((team) => el('div', { class: 'bside', style: teamVars(g.teams[team]) }, levels.map((level) => {
                const c = g.cells.find((x) => x.id === E.cellId(ci, team, level));
                const res = c.result;
                const mine = team === t && !c.used;
                return el('button', {
                    class: 'cell' + (c.used ? ' used' : '') + (mine ? ' mine' : '') + (c.used && c.golden ? ' golden' : ''),
                    disabled: !mine,
                    'data-cell': c.id,
                    title: c.used ? '' : levelName(level),
                    onclick: () => dispatch({ type: 'PICK_CELL', team: t, cell: c.id }),
                }, c.used
                    ? [el('span', { class: 'cell-mark', style: teamVars(g.teams[res.success ? res.challenger : 1 - res.challenger]), text: res.success ? '✓' : '✗' }), c.golden ? el('span', { class: 'star', text: '★' }) : null]
                    : [el('b', { text: level })]);
            })))),
        ]))),
        el('div', { class: 'legend' }, [0, 1].map((i) => el('div', { class: 'legend-team', style: teamVars(g.teams[i]) }, [teamTag(i), helpsRow(g, i)]))),
        g.undo ? el('div', { class: 'row center' }, [el('button', { class: 'btn ghost small', text: '↶ تراجع عن آخر نتيجة', onclick: () => dispatch({ type: 'UNDO' }) })]) : null,
    ]);
}

function questionCard(cur, { answers = false } = {}) {
    const g = G();
    return el('section', { class: 'qcard' + (cur.golden ? ' gold-q' : '') }, [
        el('div', { class: 'qmeta' }, [
            el('span', { text: cur.q.category }),
            cur.final ? el('span', { class: 'badge gold', text: 'الجولة الأخيرة' }) : el('span', { class: 'badge', text: `${cur.level} · ${levelName(cur.level)}` }),
            cur.golden ? el('span', { class: 'badge gold', text: '★ سؤال ذهبي — النقاط دبل' }) : null,
            cur.double !== null && cur.double !== undefined ? el('span', { class: 'badge', style: teamVars(g.teams[cur.double]), text: `×2 ${g.teams[cur.double].name}` }) : null,
        ]),
        el('h2', { class: 'qtext', text: cur.q.text }),
        answers ? answersList(cur.q.answers) : null,
    ]);
}

function answersList(list) {
    return el('div', { class: 'answers' }, [
        el('h3', { text: 'إجابات نموذجية' }),
        el('ul', {}, list.map((a) => el('li', { text: a }))),
        el('small', { class: 'muted', text: 'القائمة مش شاملة: أي إجابة صحيحة تانية بتنحسب.' }),
    ]);
}

function auctionScreen() {
    const g = G();
    const cur = g.current;
    const t = cur.toAct;
    const nb = nextBid(cur);
    const canWithdraw = cur.bid && cur.bid.team !== t;
    const canBluff = canWithdraw && !cur.final && g.bluffs[t] > 0;
    const jumps = [1, 2, 3, 5].map((d) => (cur.bid ? cur.bid.value + d : E.MIN_BID + d - 1)).filter((v) => v <= E.MAX_BID);

    return el('div', { class: 'screen auction', style: teamVars(g.teams[t]) }, [
        questionCard(cur),
        el('div', { class: 'bid-zone' }, [
            el('div', { class: 'bid-label', text: cur.bid ? 'أعلى مزايدة' : 'افتحوا المزاد' }),
            el('div', { class: 'bid-number', id: 'bidNumber', style: cur.bid ? teamVars(g.teams[cur.bid.team]) : '', text: cur.bid ? cur.bid.value : '؟' }),
            el('div', { class: 'bid-by' }, cur.bid ? ['آخر مزايدة: ', teamTag(cur.bid.team)] : [`أقل رقم ${E.MIN_BID}`]),
            el('div', { class: 'bid-history' }, cur.bids.slice(-8).map((b) => el('span', { class: 'bh', style: teamVars(g.teams[b.team]), text: b.value }))),
        ]),
        el('h2', { class: 'turn-line' }, ['الدور على ', teamTag(t)]),
        phoneNote(t, 'بيزايد'),
        fallback([
            ...jumps.map((v, i) => el('button', { class: 'btn team ' + (i ? '' : 'big'), 'data-bid': v, onclick: () => dispatch({ type: 'RAISE', team: t, value: v }), text: i === 0 ? (cur.bid ? `+1 ← ${v}` : `${v}`) : `${v}` })),
            el('button', { class: 'btn ghost', 'data-act': 'withdraw', disabled: !canWithdraw, onclick: () => dispatch({ type: 'WITHDRAW', team: t }), text: 'انسحاب' }),
            cur.final ? null : el('button', { class: 'btn gold', 'data-act': 'bluff', disabled: !canBluff, onclick: () => dispatch({ type: 'CALL_BLUFF', team: t }), text: `ورّني! (${g.bluffs[t]})` }),
        ]),
        cur.final ? el('p', { class: 'muted center', text: `الرهانات: ${teamName(0)} ${fmt(g.final.bets[0])} · ${teamName(1)} ${fmt(g.final.bets[1])}` }) : null,
        nb > E.MAX_BID ? el('p', { class: 'muted center', text: 'وصلتوا لأعلى رقم.' }) : null,
    ]);
}

function challengeScreen() {
    const g = G();
    const cur = g.current;
    const c = cur.challenger;
    const o = 1 - c;
    const tm = cur.timer;
    const started = tm.endsAt !== null;
    const slots = Array.from({ length: cur.required }, (_, i) => el('span', { class: 'slot' + (i < cur.count ? ' on' : ''), text: i + 1 }));
    const helpBtn = (team, key, type) => {
        const avail = g.helps[team][key];
        return el('button', {
            class: 'btn help', style: teamVars(g.teams[team]), 'data-help': key,
            disabled: !avail || (key !== 'freeze' && started) || (key === 'steal' && (cur.bluff !== null || cur.stolen)) || (key === 'minus2' && cur.minus2) || (key === 'freeze' && cur.freeze),
            onclick: () => dispatch({ type, team }),
        }, [el('b', { text: HELP_LABELS[key].icon }), ` ${HELP_LABELS[key].name}`]);
    };

    return el('div', { class: 'screen challenge', style: teamVars(g.teams[c]) }, [
        el('div', { class: 'challenger' }, [
            teamTag(c),
            el('span', { text: ' لازم يذكر ' }),
            el('b', { class: 'req', id: 'required', text: cur.required }),
            el('span', { text: ' إجابات' }),
            cur.bluff !== null ? el('span', { class: 'badge gold', text: `ورّني! من ${teamName(cur.bluff)}` }) : null,
            cur.stolen ? el('span', { class: 'badge', text: 'مزاد مسروق' }) : null,
            cur.minus2 ? el('span', { class: 'badge', text: '−2' }) : null,
        ]),
        questionCard(cur),
        el('div', { class: 'clock-row' }, [
            el('div', { class: 'clock', id: 'clock' }, [el('span', { id: 'clockNum', text: Math.ceil(secondsLeft(tm, Date.now())) })]),
            el('div', { class: 'slots', id: 'slots' }, slots),
        ]),
        cur.final ? null : el('div', { class: 'helps-row' }, [
            el('div', { class: 'help-group' }, [teamTag(c, 'مساعدات المتحدي'), helpBtn(c, 'minus2', 'MINUS2'), helpBtn(c, 'freeze', 'FREEZE')]),
            el('div', { class: 'help-group' }, [teamTag(o, 'مساعدة الخصم'), helpBtn(o, 'steal', 'STEAL')]),
        ]),
        el('div', { class: 'judge' }, [
            started
                ? el('button', { class: 'btn count', id: 'countBtn', onclick: () => dispatch({ type: 'COUNT', delta: 1 }) }, [el('b', { text: '✓' }), ' إجابة صحيحة', el('kbd', { text: 'مسافة' })])
                : el('button', { class: 'btn gold big', id: 'startTimer', onclick: () => dispatch({ type: 'START_TIMER' }) }, ['▶ ابدأ الوقت ', el('kbd', { text: 'Enter' })]),
            started ? el('button', { class: 'btn ghost', id: 'uncount', disabled: cur.count === 0, onclick: () => dispatch({ type: 'COUNT', delta: -1 }) }, ['↶ تراجع ', el('kbd', { text: 'Z' })]) : null,
            el('div', { class: 'row' }, [
                el('button', { class: 'btn ok small', id: 'judgeYes', onclick: () => dispatch({ type: 'JUDGE', success: true }), text: 'نجح' }),
                el('button', { class: 'btn danger small', id: 'judgeNo', onclick: () => dispatch({ type: 'JUDGE', success: false }), text: 'فشل' }),
                el('button', { class: 'btn ghost small', onclick: editAuction, text: 'تعديل المزاد' }),
            ]),
        ]),
    ]);
}

function editAuction() {
    const g = G();
    const cur = g.current;
    let team = cur.challenger;
    const num = el('input', { type: 'number', min: 1, max: E.MAX_BID, value: cur.required, class: 'num' });
    const pick = el('div', { class: 'row' }, [0, 1].map((i) => el('button', { class: 'btn small team-choice' + (i === team ? ' on' : ''), style: teamVars(g.teams[i]), text: g.teams[i].name, onclick: (e) => { team = i; $$('.team-choice', pick).forEach((b) => b.classList.remove('on')); e.target.classList.add('on'); } })));
    modal('تعديل المزاد', [el('p', { text: 'مين المتحدي وكم المطلوب؟' }), pick, num], [
        { label: 'احفظ', cls: 'gold', fn: () => { const v = parseInt(num.value, 10); dispatch({ type: 'SET_AUCTION', team, value: v }); } },
        { label: 'إلغاء', cls: 'ghost' },
    ]);
}

function resultScreen() {
    const g = G();
    const cur = g.current;
    const r = cur.result;
    const c = cur.challenger;
    const gain = (i) => r.awards[i] ? el('div', { class: 'award', style: teamVars(g.teams[i]) }, [teamTag(i), el('b', { text: (r.awards[i] > 0 ? '+' : '') + fmt(r.awards[i]) })]) : null;
    return el('div', { class: 'screen result ' + (r.success ? 'win' : 'lose'), style: teamVars(g.teams[c]) }, [
        el('div', { class: 'verdict' }, [
            el('div', { class: 'verdict-icon', text: r.success ? '✓' : '✗' }),
            el('h1', { class: 'display', id: 'verdict' }, r.success ? [teamTag(c), ' قدّها!'] : [teamTag(c), ' ما قدرها']),
            el('p', { class: 'lead', text: `${r.count} من ${cur.required}${r.used ? ` · ${r.used} ثانية` : ''}` }),
        ]),
        el('div', { class: 'awards' }, [gain(0), gain(1)]),
        r.lines.length ? el('ul', { class: 'lines' }, r.lines.map((l) => el('li', { text: lineText(l, g.teams) }))) : null,
        el('div', { class: 'row center' }, [
            el('button', { class: 'btn gold big', id: 'nextBtn', onclick: () => dispatch({ type: 'NEXT' }), text: g.phase === 'final_result' ? 'النتيجة النهائية ←' : (E.boardDone(g) ? 'للجولة الأخيرة ←' : 'التالي ←') }),
        ]),
        questionCard(cur, { answers: true }),
        el('div', { class: 'corrections' }, [
            el('span', { class: 'muted', text: 'تصحيح يدوي:' }),
            ...[0, 1].flatMap((i) => [
                el('button', { class: 'btn small team', style: teamVars(g.teams[i]), onclick: () => dispatch({ type: 'ADJUST', team: i, delta: 100 }), text: `${g.teams[i].name} +100` }),
                el('button', { class: 'btn small ghost', onclick: () => dispatch({ type: 'ADJUST', team: i, delta: -100 }), text: `${g.teams[i].name} −100` }),
            ]),
            g.undo ? el('button', { class: 'btn small ghost', id: 'undoBtn', onclick: () => dispatch({ type: 'UNDO' }), text: '↶ تراجع عن آخر نتيجة' }) : null,
        ]),
    ]);
}

function betScreen() {
    const g = G();
    const f = g.final;
    const status = (i) => el('div', { class: 'bet-status ' + (f.locked[i] ? 'ok' : 'wait'), style: teamVars(g.teams[i]), 'data-bet': i }, [
        teamTag(i), el('span', { text: f.locked[i] ? ' ثبّت رهانه ✓' : ' لسا…' }),
    ]);
    const intro = el('div', { class: 'final-intro' }, [
        el('h1', { class: 'display', text: 'كل شي أو لا شي' }),
        el('p', { class: 'lead', text: 'كل فريق بيراهن سراً بجزء من نقاطه (لحد كاملها). بعدها مزاد عادي على سؤال صعب: الفايز بياخد رهانه، والتاني بيخسر رهانه.' }),
        el('div', { class: 'row center' }, [status(0), status(1)]),
    ]);

    let entry = null;
    const pending = [0, 1].filter((i) => !f.locked[i]);
    if (pending.length) {
        const i = pending.includes(betStep) ? betStep : pending[0];
        const inp = el('input', { type: 'password', inputmode: 'numeric', autocomplete: 'off', class: 'num secret', id: 'betInput', placeholder: '٠', 'aria-label': 'الرهان' });
        const box = el('section', { class: 'card bet-entry', style: teamVars(g.teams[i]) }, [
            el('h2', {}, ['رهان ', teamTag(i)]),
            el('p', { class: 'muted', text: `خلّي ${teamName(1 - i)} يدير وجهه 🙈 — من 0 لحد ${fmt(E.maxBet(g, i))}` }),
            inp,
            el('div', { class: 'row' }, [0.25, 0.5, 1].map((p) => el('button', { class: 'btn small ghost', text: p === 1 ? 'كل النقاط' : `${p * 100}%`, onclick: () => { inp.value = Math.floor(E.maxBet(g, i) * p); } }))),
            el('button', { class: 'btn team big', id: 'lockBet', onclick: () => {
                const v = parseInt(inp.value || '0', 10);
                if (dispatch({ type: 'FINAL_BET', team: i, value: Number.isFinite(v) ? v : -1 })) betStep = 1 - i;
            }, text: 'ثبّت الرهان 🔒' }),
        ]);
        entry = multi() ? el('details', { class: 'fallback' }, [el('summary', { text: 'إدخال الرهان من هون (احتياطي)' }), box]) : box;
    }

    return el('div', { class: 'screen bet' }, [
        intro,
        multi() ? el('p', { class: 'phone-note', text: '📱 الرهانات بتنكتب من جوالات القادة، وما بتطلع هون قبل ما يثبّت الفريقين.' }) : null,
        entry,
        el('div', { class: 'row center' }, [
            el('button', { class: 'btn gold big', id: 'revealBets', disabled: !(f.locked[0] && f.locked[1]), onclick: () => dispatch({ type: 'FINAL_REVEAL' }), text: 'اكشفوا الرهانات ←' }),
        ]),
    ]);
}

function revealScreen() {
    const g = G();
    return el('div', { class: 'screen reveal' }, [
        el('h1', { class: 'title', text: 'الرهانات' }),
        el('div', { class: 'grid2' }, [0, 1].map((i) => el('div', { class: 'flip', style: teamVars(g.teams[i]) }, [
            el('div', { class: 'flip-inner' }, [
                el('div', { class: 'flip-back', text: '🔒' }),
                el('div', { class: 'flip-front' }, [teamTag(i), el('b', { class: 'bet-value', text: fmt(g.final.bets[i]) }), el('small', { text: `من ${fmt(g.scores[i])}` })]),
            ]),
        ]))),
        el('p', { class: 'lead center' }, ['الفريق المتأخر بيفتح المزاد: ', teamTag(g.scores[0] <= g.scores[1] ? 0 : 1)]),
        el('div', { class: 'row center' }, [el('button', { class: 'btn gold big', id: 'finalAuction', onclick: () => dispatch({ type: 'FINAL_AUCTION' }), text: 'السؤال الأخير ←' })]),
    ]);
}

function finaleScreen() {
    const g = G();
    const w = E.winner(g);
    const st = g.stats;
    const boldest = [...st.bluffs].sort((a, b) => b.value - a.value)[0];
    const fact = (title, body) => el('div', { class: 'fact' }, [el('small', { text: title }), el('b', {}, body)]);
    if (!document.body.dataset.cheered) { document.body.dataset.cheered = '1'; setTimeout(() => play('streak'), 200); }
    return el('div', { class: 'screen finale', style: w === null ? '' : teamVars(g.teams[w]) }, [
        el('div', { class: 'trophy', text: w === null ? '🤝' : '🏆' }),
        el('h1', { class: 'display', id: 'winner' }, w === null ? ['تعادل!'] : [teamTag(w), ' فاز!']),
        el('div', { class: 'final-scores' }, [0, 1].map((i) => el('div', { class: 'fs', style: teamVars(g.teams[i]) }, [el('span', { text: `${g.teams[i].emblem} ${g.teams[i].name}` }), el('b', { text: fmt(g.scores[i]) })]))),
        el('div', { class: 'facts' }, [
            fact('أعلى مزايدة', st.highest ? [teamTag(st.highest.team), ` ${st.highest.value} إجابات`, el('small', { text: st.highest.cat ? ` · ${st.highest.cat}` : '' })] : ['—']),
            fact('أجرأ بلف', boldest ? [teamTag(boldest.bidder), ` زايد ${boldest.value} وانكشف — `, boldest.success ? 'وورّاهم ✓' : 'وما قدرها ✗'] : ['ما حدا قال «ورّني!»']),
            fact('أسرع تحدّي', st.fastest ? [teamTag(st.fastest.team), ` ${st.fastest.required} إجابات بـ ${Math.max(1, st.fastest.seconds)} ${Math.max(1, st.fastest.seconds) === 1 ? 'ثانية' : 'ثواني'}`] : ['—']),
            fact('أسئلة انلعبت', [String(g.history.length)]),
        ]),
        el('div', { class: 'row center' }, [
            el('button', { class: 'btn gold big', id: 'rematch', onclick: () => { delete document.body.dataset.cheered; dispatch({ type: 'REMATCH' }); }, text: '⚔️ جولة ثأر' }),
            el('button', { class: 'btn ghost', onclick: () => { stopPolling(); const setup = S.setup; S = fresh(); S.setup = setup; delete document.body.dataset.cheered; save(); render(); }, text: 'لعبة جديدة' }),
        ]),
    ]);
}

/* ════════════════════════ clock ════════════════════════ */

function tickTimer(fromRender = false) {
    const g = G();
    const running = S.screen === 'game' && g && ['challenge', 'final_challenge'].includes(g.phase) && g.current.timer.endsAt !== null && !g.current.result;
    document.body.classList.toggle('tense', false);
    if (!running) return;
    const now = Date.now();
    const tm = g.current.timer;
    const left = secondsLeft(tm, now);
    const frozen = E.isFrozen(tm, now);
    const num = $('#clockNum');
    if (num) num.textContent = Math.ceil(left);
    const clock = $('#clock');
    if (clock) {
        clock.classList.toggle('frozen', frozen);
        clock.style.setProperty('--p', String(Math.max(0, Math.min(1, left / (tm.duration + (tm.extra || 0))))));
    }
    const tense = left <= 10 && !frozen;
    document.body.classList.toggle('tense', tense);
    // tick every second, twice a second in the last ten
    const step = tense ? Math.ceil(left * 2) : Math.ceil(left);
    if (!fromRender && !frozen && step !== lastTickSecond) {
        lastTickSecond = step;
        if (left > 0) play(tense ? 'tock' : 'tick');
    }
    if (left <= 0) dispatch({ type: 'TIME_UP' });
}

setInterval(() => tickTimer(false), 100);

/* ════════════════════════ keyboard ════════════════════════ */

document.addEventListener('keydown', (e) => {
    const g = G();
    if (!g || S.screen !== 'game' || e.target.closest('input, textarea, .modal')) return;
    if (!['challenge', 'final_challenge'].includes(g.phase)) return;
    const started = g.current.timer.endsAt !== null;
    if (e.code === 'Space' && started) { e.preventDefault(); dispatch({ type: 'COUNT', delta: 1 }); }
    if ((e.key === 'z' || e.key === 'Z' || e.key === 'ظ' || e.key === 'Backspace') && started) { e.preventDefault(); dispatch({ type: 'COUNT', delta: -1 }); }
    if (e.key === 'Enter' && !started) { e.preventDefault(); dispatch({ type: 'START_TIMER' }); }
});

/* ════════════════════════ boot ════════════════════════ */

export default async function boot() {
    // a host that reloads mid-game resumes from its own copy, or from the room on the server
    if (S.room && !S.game && S.screen === 'game') {
        try {
            const r = await api.hostPoll(S.room.code, S.room.token, 0, true);
            if (r.state && r.state.phase) S.game = r.state;
        } catch { /* keep local */ }
    }
    if (S.screen === 'game' && !S.game) S.screen = 'setup';
    if (S.screen === 'settings' || S.screen === 'lobby') loadPool().catch(() => {});
    render();
    if (multi()) push();
}

window.__qaddaha = { get state() { return S; } }; // handy for debugging and the browser tests
