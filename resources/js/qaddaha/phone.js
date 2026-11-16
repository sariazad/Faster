// قدّها؟ — a team leader's phone: a remote with big thumb buttons. It never changes the game
// itself; it sends intents and shows whatever the host publishes.
import * as api from './api.js';
import { $, config, el, errorText, randomId, store, toast, vibrate } from './core.js';
import { HELP_LABELS, fmt, nextBid, secondsLeft, teamVars } from './shared.js';
import { MAX_BID, MIN_BID, KINDS, cellId } from './engine.js';

const app = $('#app');
const code = config.code;
const params = new URLSearchParams(location.search);
const wanted = Math.min(1, Math.max(0, (parseInt(params.get('team') || '1', 10) || 1) - 1));

let team = null;        // our seat (0/1) once joined
let pub = null;         // public state from the host
let version = -1;
let offset = 0;         // host clock − our clock (for the countdown)
let lastKey = '';       // what we rendered last (avoid flicker)
let sent = new Set();   // intents waiting for the host
let myBet = store.get(`bet.${code}`, null);
let joinError = null;

const T = () => (pub && pub.teams ? pub.teams[team] : null);

async function join() {
    try {
        const r = await api.joinRoom(code, wanted);
        team = r.team;
        joinError = null;
    } catch (e) {
        joinError = e.code;
    }
    render(true);
}

async function poll() {
    try {
        const r = await api.roomState(code, version);
        if (r.team !== null && r.team !== undefined) team = r.team;
        if (!r.same) {
            const before = pub;
            pub = r.public;
            version = r.version;
            if (pub?.sentAt) offset = pub.sentAt - Date.now();
            sent = new Set();
            notify(before, pub);
            render();
        }
        $('#net')?.setAttribute('hidden', '');
    } catch (e) {
        if (e.status === 404) { joinError = 'no_room'; render(true); return; }
        $('#net')?.removeAttribute('hidden');
    }
}

/** Buzz when it becomes our move or the auction lands on us. */
function notify(a, b) {
    if (!b || team === null) return;
    const mine = (s) => {
        if (!s || !s.phase) return false;
        if (s.phase === 'draft') return s.draft.order[s.draft.idx] === team;
        if (s.phase === 'board') return s.turn === team;
        if (['auction', 'final_auction'].includes(s.phase)) return s.current.toAct === team;
        return false;
    };
    if (mine(b) && !mine(a)) vibrate([60, 40, 60]);
    const sold = (s) => s && ['challenge', 'final_challenge'].includes(s.phase) && s.current.challenger === team;
    if (sold(b) && !sold(a)) vibrate([200, 80, 200]);
}

async function send(type, extra = {}) {
    const intent = { id: 'i' + randomId(8), type, ...extra };
    sent.add(type);
    render(true);
    try {
        await api.sendIntent(code, intent);
        if (navigator.vibrate) vibrate(20);
        // if the host refuses it (not our turn any more…) nothing changes: free the button again
        setTimeout(() => { if (sent.delete(type)) render(true); }, 3000);
    } catch (e) {
        sent.delete(type);
        toast(errorText(e.code), true);
        render(true);
    }
}

/* ───────── rendering ───────── */

function render(force = false) {
    const key = JSON.stringify([version, team, joinError, [...sent], myBet]);
    if (!force && key === lastKey) return;
    lastKey = key;
    app.replaceChildren(screen());
    document.body.style.cssText = T() ? teamVars(T()) : '';
}

function shell(kids, { title = null } = {}) {
    const t = T();
    return el('div', { class: 'phone-screen' }, [
        el('header', { class: 'ph-top' }, [
            el('span', { class: 'logo small', text: 'قدّها؟' }),
            t ? el('span', { class: 'ph-team', text: `${t.emblem} ${t.name}` }) : null,
            pub?.scores ? el('span', { class: 'ph-score' }, [fmt(pub.scores[team]), ' : ', fmt(pub.scores[1 - team])]) : null,
        ]),
        title ? el('h1', { class: 'ph-title', text: title }) : null,
        ...kids,
    ]);
}

function wait(text, sub = null) {
    return shell([el('div', { class: 'ph-wait' }, [el('div', { class: 'ph-dots', text: '⋯' }), el('p', { text }), sub ? el('small', { text: sub }) : null])]);
}

const big = (label, onclick, { cls = '', disabled = false, id = null } = {}) =>
    el('button', { class: 'ph-btn ' + cls, onclick, disabled: disabled || undefined, id }, label);

function screen() {
    if (joinError) {
        return shell([el('div', { class: 'ph-wait' }, [
            el('p', { text: errorText(joinError) }),
            joinError === 'seat_taken' ? el('small', { text: 'إذا هاد جوالك الجديد، خلّي المضيف يفصل الجوال القديم من غرفة الانتظار وجرّب كمان مرة.' }) : null,
            big('جرّب كمان مرة', join),
        ])]);
    }
    if (team === null || !pub) return wait('نتصل بالغرفة…');
    const s = pub;
    const opp = 1 - team;

    switch (s.phase) {
        case 'lobby':
            return wait('متصل ✓', 'بانتظار المضيف يبلّش اللعبة.');

        case 'draft': {
            const turn = s.draft.order[s.draft.idx];
            if (turn !== team) return wait(`دور ${s.teams[opp].name} يختار فئة`);
            const taken = new Set(s.draft.picks.flat().map(String));
            return shell([
                el('div', { class: 'ph-list' }, s.pool.filter((c) => !taken.has(String(c.id))).map((c) =>
                    big([el('span', { text: c.icon || '' }), ' ', c.name], () => send('draft_pick', { cat: c.id }), { cls: 'cat', disabled: sent.has('draft_pick'), id: 'ph-cat-' + c.id }))),
            ], { title: 'اختاروا فئة' });
        }

        case 'review':
            return wait('الفئات جاهزة', 'المضيف بيبلّش اللعب.');

        case 'board': {
            if (s.turn !== team) return wait(`دور ${s.teams[opp].name} يختار خانة`);
            const levels = KINDS[s.kind].levels;
            return shell([
                s.helps[team].double ? big(s.pendingDouble ? 'المضاعفة ×2 مفعّلة ✓' : 'فعّل المضاعفة ×2', () => send('toggle_double'), { cls: 'gold' + (s.pendingDouble ? ' on' : ''), disabled: sent.has('toggle_double'), id: 'phDouble' }) : null,
                el('div', { class: 'ph-board', style: `--cats:${s.cats.length}` }, s.cats.map((cat, ci) => el('div', { class: 'ph-col' }, [
                    el('small', { text: `${cat.icon || ''} ${cat.name}` }),
                    ...levels.map((l) => {
                        const c = s.cells.find((x) => x.id === cellId(ci, team, l));
                        return big(String(l), () => send('pick_cell', { cell: c.id }), { cls: 'cell', disabled: c.used || sent.has('pick_cell'), id: 'ph-' + c.id });
                    }),
                ]))),
            ], { title: 'اختاروا خانة' });
        }

        case 'auction':
        case 'final_auction': {
            const cur = s.current;
            const head = [
                el('p', { class: 'ph-q', text: cur.q.text }),
                el('div', { class: 'ph-bid' }, [el('small', { text: cur.bid ? `آخر مزايدة: ${s.teams[cur.bid.team].name}` : `افتحوا من ${MIN_BID}` }), el('b', { text: cur.bid ? cur.bid.value : '؟' })]),
            ];
            if (cur.toAct !== team) return shell([...head, el('p', { class: 'ph-wait-line', text: `دور ${s.teams[opp].name}…` })]);
            const nb = nextBid(cur);
            const canWithdraw = cur.bid && cur.bid.team !== team;
            const disabled = ['raise', 'withdraw', 'bluff'].some((t) => sent.has(t));
            return shell([
                ...head,
                big(cur.bid ? `+1  ←  ${nb}` : String(nb), () => send('raise', { value: nb }), { cls: 'main', disabled: disabled || nb > MAX_BID, id: 'phRaise' }),
                el('div', { class: 'ph-jumps' }, [2, 3, 5].map((d) => {
                    const v = (cur.bid ? cur.bid.value : MIN_BID - 1) + d;
                    return big(String(v), () => send('raise', { value: v }), { disabled: disabled || v > MAX_BID, id: 'phJump' + d });
                })),
                el('div', { class: 'ph-pair' }, [
                    big('انسحاب', () => send('withdraw'), { cls: 'ghost', disabled: disabled || !canWithdraw, id: 'phWithdraw' }),
                    cur.final ? null : big(`ورّني! (${s.bluffs[team]})`, () => send('bluff'), { cls: 'gold', disabled: disabled || !canWithdraw || !s.bluffs[team], id: 'phBluff' }),
                ]),
            ], { title: 'دوركم تزايدوا' });
        }

        case 'challenge':
        case 'final_challenge': {
            const cur = s.current;
            const tm = cur.timer;
            const started = tm.endsAt !== null;
            const mine = cur.challenger === team;
            const left = Math.ceil(secondsLeft(tm, Date.now() + offset));
            const help = (key, type) => big([el('b', { text: HELP_LABELS[key].icon }), ' ', HELP_LABELS[key].name], () => send(type), {
                cls: 'help', id: 'ph-' + key,
                disabled: sent.has(type) || !s.helps[team][key] || (key !== 'freeze' && started) || (key === 'steal' && (cur.bluff !== null || cur.stolen)) || (key === 'minus2' && cur.minus2) || (key === 'freeze' && cur.freeze),
            });
            return shell([
                el('p', { class: 'ph-q', text: cur.q.text }),
                el('div', { class: 'ph-progress' }, [
                    el('b', { id: 'phClock', 'data-ends': tm.endsAt ?? '', 'data-stopped': tm.stoppedAt ?? '', text: started ? String(left) : '⏸' }),
                    el('span', { text: `${cur.count} / ${cur.required}` }),
                ]),
                cur.final ? null : (mine
                    ? el('div', { class: 'ph-pair' }, [help('minus2', 'minus2'), help('freeze', 'freeze')])
                    : el('div', { class: 'ph-pair' }, [help('steal', 'steal')])),
            ], { title: mine ? `دوركم! اذكروا ${cur.required}` : `${s.teams[cur.challenger].name} بيتحدّى` });
        }

        case 'result':
        case 'final_result': {
            const r = s.current.result;
            const win = r.success === (s.current.challenger === team);
            return shell([el('div', { class: 'ph-wait' }, [
                el('div', { class: 'ph-big-icon', text: win ? '🎉' : '😬' }),
                el('p', { text: r.success ? `${s.teams[s.current.challenger].name} قدّها!` : `${s.teams[s.current.challenger].name} ما قدرها` }),
                el('small', { text: `نقاطكم: ${fmt(s.scores[team])}` }),
            ])]);
        }

        case 'final_bet': {
            if (s.final.locked[team]) return wait('رهانكم ثابت ✓', myBet !== null ? `راهنتوا بـ ${fmt(myBet)} — خليه سر 🤫` : 'بانتظار الفريق التاني.');
            const max = Math.max(0, s.scores[team]);
            const inp = el('input', { type: 'number', inputmode: 'numeric', min: 0, max, class: 'ph-input', id: 'phBet', placeholder: '0' });
            return shell([
                el('p', { class: 'ph-note', text: `رهان سري من 0 لحد ${fmt(max)}. الفايز بالسؤال الأخير بياخد رهانه، والتاني بيخسر رهانه.` }),
                inp,
                el('div', { class: 'ph-jumps' }, [0.25, 0.5, 1].map((p) => big(p === 1 ? 'الكل' : `${p * 100}%`, () => { inp.value = Math.floor(max * p); }, { cls: 'ghost' }))),
                big('ثبّت الرهان 🔒', () => {
                    const v = parseInt(inp.value || '0', 10);
                    if (!Number.isFinite(v) || v < 0 || v > max) return toast(errorText('bad_bet'), true);
                    myBet = v;
                    store.set(`bet.${code}`, v);
                    send('final_bet', { value: v });
                }, { cls: 'main', disabled: sent.has('final_bet'), id: 'phLockBet' }),
            ], { title: 'رهانكم السري' });
        }

        case 'final_reveal':
            return wait('انكشفت الرهانات!', `${s.teams[team].name}: ${fmt(s.final.bets[team])} · ${s.teams[opp].name}: ${fmt(s.final.bets[opp])}`);

        case 'finale': {
            const w = s.scores[0] === s.scores[1] ? null : (s.scores[0] > s.scores[1] ? 0 : 1);
            store.del(`bet.${code}`);
            return shell([el('div', { class: 'ph-wait' }, [
                el('div', { class: 'ph-big-icon', text: w === null ? '🤝' : (w === team ? '🏆' : '👏') }),
                el('p', { text: w === null ? 'تعادل!' : (w === team ? 'فزتوا!' : `فاز ${s.teams[w].name}`) }),
                el('small', { text: `${fmt(s.scores[team])} : ${fmt(s.scores[opp])}` }),
            ])]);
        }

        default:
            return wait('انتظروا…');
    }
}

/* the countdown ticks locally between polls */
setInterval(() => {
    const n = $('#phClock');
    if (!n || !n.dataset.ends) return;
    const ends = Number(n.dataset.ends);
    const stopped = n.dataset.stopped ? Number(n.dataset.stopped) : null;
    const left = Math.max(0, ((stopped ?? (Date.now() + offset)) < ends ? ends - (stopped ?? (Date.now() + offset)) : 0) / 1000);
    n.textContent = String(Math.ceil(left));
    n.classList.toggle('tense', left <= 10);
}, 250);

export default async function boot() {
    render(true);
    await join();
    await poll();
    setInterval(poll, 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
}
