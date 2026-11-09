// آخر القائمة — the room: lobby, game and end screens.
// The device that opened the room is the stage (big screen); every other device is a player's phone.
// State comes from the server (polled every second); the server owns the clock and the rules.
import { $, S, el, store, toast, sfx, confetti, config, deviceId } from '../core.js';
import * as api from '../api.js';

const app = $('#app');
const code = app.dataset.code;
const POLL_MS = 1000;
const teamName = (t) => S(`teams.${t}`);
const SOLO_COLORS = ['#e5484d', '#2b7fd8', '#16a34a', '#d97706', '#9333ea', '#db2777', '#0891b2', '#65a30d', '#ea580c', '#4f46e5', '#0d9488', '#be185d'];
const solo = () => state?.mode === 'solo';

/** Colour of whoever owns something: the team (teams mode) or the player (solo). */
function ownerOf(playerId, team) {
    if (!solo()) return { cls: team || 'A', style: null };
    const idx = Math.max(0, state.players.findIndex((p) => p.id === playerId));
    return { cls: 'P', style: `--c:${SOLO_COLORS[idx % SOLO_COLORS.length]}` };
}

let state = null;
let offset = 0;                 // server clock − local clock
let lastTurnKey = null;         // to notice a new turn (sound / vibration)
let expiredFor = null;          // turn already reported as expired
let lastGuessId = 0;            // to pop newly revealed cells
let boardOpen = false;
let revealDone = false;
let sending = false;
let ticked = -1;

// ───────── Polling ─────────

async function poll() {
    try {
        const s = await api.getState(code, state?.version);
        $('#net').hidden = true;
        offset = s.server_now - Date.now();
        if (!s.unchanged) {
            const prev = state;
            state = s;
            if (!s.is_host && !s.my_players.length) {
                location.href = `${config.base}?room=${code}`;
                return;
            }
            render(prev);
        }
    } catch (e) {
        if (!e.status) console.error(e);
        if (e.status === 404) {
            store.del('room');
            app.replaceChildren(el('div', { class: 'card center', style: 'margin-top:60px' }, [
                el('h2', { text: 'الغرفة مش موجودة أو انتهت' }),
                el('a', { class: 'btn', href: config.base, text: S('app.back_home') }),
            ]));
            return;
        }
        $('#net').hidden = false;
    }
    setTimeout(poll, POLL_MS);
}

const now = () => Date.now() + offset;
const playerById = (id) => state.players.find((p) => p.id === id);
const mine = (id) => state.my_players.includes(id);

// ───────── Render dispatcher ─────────

function render(prev) {
    document.body.dataset.status = state.status;
    if (state.status === 'lobby') { revealDone = false; renderLobby(); }
    else if (state.status === 'playing') renderGame(prev);
    else renderEnd(prev);
}

function header(extra = []) {
    return el('div', { class: 'row between', style: 'margin-bottom:14px' }, [
        el('a', { href: config.base, class: 'logo sm', 'aria-label': S('app.name') }, [el('span', { class: 'w1', text: S('app.w1') }), el('span', { class: 'w2', text: S('app.w2') }), el('sup', { text: '100' })]),
        el('div', { class: 'row', style: 'gap:8px' }, extra),
    ]);
}

async function act(fn, ...args) {
    try { await fn(code, ...args); state.version = 0; } catch (e) { toast(e.message || S('app.error'), true); }
}

// ───────── Lobby ─────────

function renderLobby() {
    const s = state;
    const teams = { A: s.players.filter((p) => p.team === 'A'), B: s.players.filter((p) => p.team === 'B') };
    const joinUrl = new URL(`${config.base}?room=${s.code}`, location.href).href; // config.base is already absolute
    const active = s.players.filter((p) => !p.spectator);
    const nTurns = solo() ? s.settings.turns_per_player : s.settings.turns_per_player * Math.max(teams.A.length, teams.B.length, 1);

    const playerCard = (p) => el('div', { class: 'pcard' }, [
        el('span', { class: 'nm', text: p.name }),
        p.host ? el('span', { class: 'tag', text: S('lobby.host') }) : null,
        p.mine ? el('span', { class: 'tag me', text: S('lobby.this_device') }) : null,
        p.offline ? el('span', { class: 'tag', text: S('lobby.no_phone') }) : null,
        p.spectator ? el('span', { class: 'tag', text: S('lobby.spectator') }) : null,
        !solo() && (p.mine || (s.is_host && p.offline)) ? el('button', { class: 'chip-btn', text: S('lobby.switch'), 'aria-label': `${S('lobby.switch')}: ${p.name}`, onclick: () => act(api.switchTeam, p.id) }) : null,
        (p.mine && !p.host) || (s.is_host && !p.host) ? el('button', { class: 'chip-btn', text: '✕', 'aria-label': `${S('lobby.remove')}: ${p.name}`, onclick: () => act(api.removePlayer, p.id) }) : null,
    ]);

    const cols = solo()
        ? el('section', { class: 'card', 'aria-label': S('lobby.players') }, [
            el('h3', { text: `${S('lobby.players')} (${s.players.length})` }),
            el('div', { class: 'solo-players' }, s.players.map((p) => { const c = playerCard(p); const sd = ownerOf(p.id); c.prepend(el('span', { class: `dot ${sd.cls}`, style: sd.style })); return c; })),
        ])
        : el('div', { class: 'teams' }, ['A', 'B'].map((t) => el('section', { class: `team-col ${t}`, 'aria-label': teamName(t) }, [
            el('h3', { text: `${teamName(t)} (${teams[t].length})` }),
            ...(teams[t].length ? teams[t].map(playerCard) : [el('p', { class: 'muted small', text: S('lobby.empty') })]),
        ])));

    const top = el('div', { class: 'card center' }, [
        el('div', { class: 'muted', text: S('lobby.code') }),
        el('div', { class: 'room-code', text: s.code, 'aria-label': `${S('lobby.code')} ${s.code.split('').join(' ')}` }),
        s.is_host ? el('div', { class: 'qr', id: 'qr', 'aria-hidden': 'true' }) : null,
        s.is_host ? el('p', { class: 'muted small', text: S('lobby.scan') }) : null,
    ]);

    const children = [header([el('button', { class: 'btn ghost sm', text: S('lobby.leave'), onclick: leave })]), el('div', { class: 'stack' }, [top, cols])];

    if (s.is_host) {
        const nameIn = el('input', { class: 'field grow', maxlength: 20, placeholder: S('lobby.add_name'), 'aria-label': S('lobby.add_name') });
        let addTeam = 'A';
        const teamOpts = el('div', { class: 'opts' }, ['A', 'B'].map((t) => el('button', { type: 'button', class: 'opt' + (t === 'A' ? ' on' : ''), text: teamName(t), onclick: (e) => { addTeam = t; [...teamOpts.children].forEach((b) => b.classList.toggle('on', b === e.currentTarget)); } })));
        const addForm = el('form', { class: 'card stack', onsubmit: async (e) => { e.preventDefault(); if (!nameIn.value.trim()) return; await act(api.addPlayer, nameIn.value.trim(), solo() ? null : addTeam); nameIn.value = ''; } }, [
            el('h3', { text: S('lobby.add_title') }),
            el('div', { class: 'row' }, [nameIn, el('button', { class: 'btn', text: S('lobby.add') })]),
            solo() ? null : teamOpts,
        ]);

        const current = (key) => (key === 'mode' ? s.mode : s.settings[key]);
    const opt = (key, values, label) => el('div', {}, [
            el('div', { class: 'lbl', text: label }),
            el('div', { class: 'opts', role: 'radiogroup', 'aria-label': label }, values.map(([v, t]) => el('button', {
                class: 'opt' + (current(key) === v ? ' on' : ''), role: 'radio', 'aria-checked': String(current(key) === v), text: t,
                onclick: () => act(api.saveSettings, { [key]: v }),
            }))),
        ]);

        const a = teams.A.filter((p) => !p.spectator).length;
        const b = teams.B.filter((p) => !p.spectator).length;
        const problem = solo()
            ? (active.length < 2 ? S('lobby.need_two') : null)
            : (!a || !b ? S('lobby.empty_team') : (a !== b ? S('lobby.uneven', { a, b }) : null));
        const canStart = !problem && s.list;
        children.push(el('div', { class: 'card stack', style: 'margin-top:14px' }, [
            el('h2', { text: S('lobby.settings') }),
            opt('mode', [['teams', S('lobby.mode_teams')], ['solo', S('lobby.mode_solo')]], S('lobby.mode')),
            el('div', {}, [
                el('div', { class: 'lbl', text: S('lobby.list') }),
                el('div', { class: 'list-cards' }, (s.lists || []).map((l) => el('button', {
                    class: 'list-card' + (s.list?.id === l.id ? ' on' : ''), 'aria-pressed': String(s.list?.id === l.id),
                    onclick: () => act(api.saveSettings, { list_id: l.id }),
                }, [l.title, el('small', { text: `${l.count} ${S('lobby.items')}` })]))),
            ]),
            opt('turns_per_player', [1, 2, 3, 4].map((n) => [n, String(n)]), S('lobby.turns')),
            opt('turn_seconds', [20, 30, 45, 60].map((n) => [n, `${n} ${S('lobby.sec')}`]), S('lobby.seconds')),
            opt('wrong_penalty', [[false, S('lobby.no_penalty')], [true, S('lobby.minus10')]], S('lobby.penalty')),
            el('p', { class: 'muted', text: S(solo() ? 'lobby.calc_solo' : 'lobby.calc', { n: nTurns }) }),
            el('button', { class: 'btn lantern block', disabled: !canStart, text: S('lobby.start'), onclick: () => act(api.action, 'start') }),
            problem ? el('p', { class: 'muted small center', role: 'status', text: problem }) : null,
        ]), addForm);
    } else {
        const meSpectating = s.players.some((p) => p.mine && p.spectator);
        children.push(el('p', { class: 'center muted', style: 'font-size:18px;margin-top:18px', role: 'status', text: meSpectating ? S('play.spectating') : S('lobby.waiting') }));
    }

    app.replaceChildren(...children);

    if (s.is_host && window.QRCode && $('#qr')) {
        // eslint-disable-next-line no-new
        new window.QRCode($('#qr'), { text: joinUrl, width: 150, height: 150, correctLevel: window.QRCode.CorrectLevel.M });
    } else if (s.is_host) {
        setTimeout(() => { if (window.QRCode && $('#qr') && !$('#qr').children.length) new window.QRCode($('#qr'), { text: joinUrl, width: 150, height: 150 }); }, 600);
    }
}

async function leave() {
    try { await api.action(code, 'leave'); } catch { /* ignore */ }
    store.del('room');
    location.href = config.base;
}

// ───────── Game ─────────

function lastResultLine() {
    const g = state.guesses[state.guesses.length - 1];
    if (!g) return { cls: '', text: S('play.waiting_first') };
    const vars = { name: g.player, text: g.text, item: g.name || '', rank: g.rank, points: g.points, penalty: Math.abs(g.points) };
    const key = g.result === 'none' && g.points < 0 ? 'r_none_penalty' : `r_${g.result}`;
    return { cls: g.result, text: S(`play.${key}`, vars) };
}

function board(mini = false) {
    const cells = new Map(state.revealed.map((r) => [r.rank, r]));
    const fresh = new Set(state.guesses.filter((g) => g.id > lastGuessId && g.result === 'match').map((g) => g.rank));
    const total = state.list?.count || 100;
    const grid = el('div', { class: 'board' + (mini ? ' mini' : ''), role: 'grid', 'aria-label': state.list?.title });
    for (let i = 1; i <= total; i++) {
        const r = cells.get(i);
        const sd = r ? ownerOf(r.player_id, r.team) : null;
        grid.append(el('div', {
            class: 'cell' + (r ? ` ${sd.cls}` : '') + (fresh.has(i) ? ' pop' : ''),
            style: sd?.style,
            role: 'gridcell',
            'aria-label': r ? `${i}: ${r.name}` : String(i),
            title: r ? `${r.name} — ${r.player}` : null,
        }, [el('span', { class: 'n', text: String(i) }), r ? r.name : '']));
    }
    return grid;
}

function guessForm(player) {
    const input = el('input', {
        class: 'field grow', maxlength: 80, autocomplete: 'off', enterkeyhint: 'send', dir: 'auto',
        placeholder: state.list?.hint || S('play.placeholder'), 'aria-label': state.list?.hint || S('play.placeholder'),
    });
    const form = el('form', {
        class: 'guess-form',
        onsubmit: async (e) => {
            e.preventDefault();
            const text = input.value.trim();
            if (!text || sending) return;
            sending = true;
            try {
                const r = await api.guess(code, state.turn_idx, text);
                input.value = '';
                (r.result === 'match' ? sfx.right : sfx.wrong)();
                state.version = 0;
            } catch (err) {
                toast(err.message || S('app.error'), true);
            } finally {
                sending = false;
            }
        },
    }, [input, el('button', { class: 'btn', text: S('play.guess'), 'aria-label': `${S('play.guess')} — ${player.name}` })]);
    setTimeout(() => input.focus({ preventScroll: true }), 30);
    return form;
}

function renderGame(prev) {
    const s = state;
    const current = playerById(s.current_player);
    const turnKey = `${s.round}:${s.turn_idx}`;
    const myTurn = current && mine(current.id);
    const amPlaying = s.players.some((p) => p.mine && !p.spectator);

    if (turnKey !== lastTurnKey) {
        if (myTurn && !s.is_host) { sfx.yourTurn(); navigator.vibrate?.(120); }
        lastTurnKey = turnKey;
        ticked = -1;
    }
    const newest = s.guesses[s.guesses.length - 1];
    if (prev && newest && newest.id > (prev.guesses.at(-1)?.id || 0) && !myTurn) {
        if (newest.result === 'match') sfx.right(); else if (['none', 'timeout'].includes(newest.result)) sfx.wrong();
    }

    const last = lastResultLine();
    const leader = s.scores.A === s.scores.B ? null : (s.scores.A > s.scores.B ? 'A' : 'B');
    const ranking = solo() ? s.players.filter((p) => s.scores[p.id] !== undefined).sort((x, y) => s.scores[y.id] - s.scores[x.id]) : [];
    const scorebar = solo() ? el('div', { class: 'solo-scores' }, [
        el('div', { class: 'score-mid' }, [el('div', { class: 'num', text: S('play.turn', { i: Math.min(s.turn_idx + 1, s.turns_total), n: s.turns_total }) }), el('div', { class: 'muted small num', text: `#${s.code}` })]),
        ...ranking.map((p, i) => { const sd = ownerOf(p.id); return el('div', { class: 'pscore' + (p.id === s.current_player ? ' now' : '') + (i === 0 && s.scores[p.id] > 0 ? ' lead' : ''), style: sd.style }, [el('span', { class: `dot ${sd.cls}`, style: sd.style }), el('span', { class: 'nm', text: p.name }), el('b', { class: 'num', text: String(s.scores[p.id]) })]); }),
    ]) : el('div', { class: 'scorebar' }, [
        el('div', { class: `score A${leader === 'A' ? ' lead' : ''}` }, [el('span', { class: 't', text: teamName('A') }), el('span', { class: 'big-num', text: String(s.scores.A) })]),
        el('div', { class: 'score-mid' }, [el('div', { class: 'num', text: S('play.turn', { i: Math.min(s.turn_idx + 1, s.turns_total), n: s.turns_total }) }), el('div', { class: 'muted small num', text: `#${s.code}` })]),
        el('div', { class: `score B${leader === 'B' ? ' lead' : ''}` }, [el('span', { class: 't', text: teamName('B') }), el('span', { class: 'big-num', text: String(s.scores.B) })]),
    ]);

    const timebar = el('div', { class: 'timebar', 'aria-hidden': 'true' }, el('i', { id: 'timefill' }));
    const lastLine = el('div', { class: `last ${last.cls}`, role: 'status', 'aria-live': 'polite', text: last.text });

    const cs = ownerOf(current?.id, current?.team);
    const sub = solo() ? (s.list?.title || '') : teamName(current?.team || 'A');
    if (s.is_host) {
        // ── Stage (big screen) ──
        const turnCard = el('section', { class: `turn ${cs.cls}`, style: cs.style, id: 'turnCard' }, [
            el('h2', { text: S('play.turn_of', { name: current?.name || '—' }) }),
            el('div', { class: 'sub', text: sub }),
            timebar,
            current && mine(current.id) ? guessForm(current) : null,
        ]);
        const revealed = [...s.revealed].sort((a, b) => b.rank - a.rank);
        const side = el('aside', { class: 'stack' }, [
            el('section', { class: 'card' }, [
                el('h3', { text: `${S('play.revealed')} (${revealed.length})` }),
                el('div', { class: 'revealed' }, revealed.map((r) => el('div', { class: 'rev' }, [
                    el('span', { class: `num ${ownerOf(r.player_id, r.team).cls}`, style: ownerOf(r.player_id, r.team).style, text: String(r.rank) }),
                    el('b', { text: r.name }),
                    el('small', { text: [r.extra && `${r.extra} ${s.list?.extra_label || ''}`, r.player].filter(Boolean).join(' • ') }),
                ]))),
            ]),
            el('section', { class: 'card' }, [
                el('h3', { text: S('play.wrong_pile') }),
                el('div', { class: 'wrongs' }, s.guesses.filter((g) => g.result === 'none').map((g) => el('span', { text: g.text }))),
            ]),
            el('div', { class: 'row' }, [
                el('button', { class: 'btn ghost grow', text: S('play.skip'), onclick: () => act(api.action, 'skip') }),
                el('button', { class: 'btn danger grow', text: S('play.end'), onclick: () => { if (confirm(S('play.end_confirm'))) act(api.action, 'end'); } }),
            ]),
        ]);
        app.replaceChildren(
            header([el('span', { class: 'muted small', text: s.list?.title })]),
            scorebar, turnCard, lastLine,
            el('div', { class: 'stage' }, [
                el('div', {}, [board(), el('div', { class: 'board-legend' }, [el('span', { text: S('play.first') }), el('span', { text: S('play.last') })])]),
                side,
            ]),
        );
    } else {
        // ── Phone ──
        const kids = [header(), scorebar];
        if (!amPlaying) kids.push(el('p', { class: 'center muted', text: S('play.spectating') }));
        if (myTurn) {
            kids.push(el('section', { class: `phone-turn ${cs.cls}`, style: cs.style, id: 'turnCard' }, [
                el('h2', { text: S('play.your_turn', { name: current.name }) }),
                el('div', { class: 'sub', text: s.list?.title }),
                guessForm(current),
                timebar,
            ]), lastLine);
        } else {
            kids.push(el('section', { class: `turn ${cs.cls}`, style: cs.style, id: 'turnCard' }, [
                el('h2', { text: S('play.turn_of', { name: current?.name || '—' }) }),
                el('div', { class: 'sub', text: sub }),
                timebar,
            ]), lastLine);
        }
        const toggle = el('button', { class: 'btn ghost block', style: 'margin-top:12px', text: boardOpen ? S('play.hide_board') : S('play.show_board'), 'aria-expanded': String(boardOpen), onclick: () => { boardOpen = !boardOpen; renderGame(); } });
        kids.push(toggle);
        if (boardOpen) kids.push(el('div', { style: 'margin-top:10px' }, board(true)));
        app.replaceChildren(...kids);
    }

    lastGuessId = s.guesses.at(-1)?.id || lastGuessId;
}

// Smooth countdown from the server deadline; when it runs out, ask the server to close the turn.
function tick() {
    if (state?.status === 'playing' && state.deadline) {
        const total = state.settings.turn_seconds * 1000;
        const left = Math.max(0, state.deadline - now());
        const fill = $('#timefill');
        if (fill) fill.style.transform = `scaleX(${left / total})`;
        const secs = Math.ceil(left / 1000);
        $('#turnCard')?.classList.toggle('urgent', left > 0 && left <= 5000);
        if (left > 0 && left <= 5000 && secs !== ticked) { ticked = secs; sfx.tick(); }
        const key = `${state.round}:${state.turn_idx}`;
        if (left <= 0 && expiredFor !== key) {
            expiredFor = key;
            api.expireTurn(code, state.turn_idx).then(() => { state.version = 0; }).catch(() => { expiredFor = null; });
        }
    }
    requestAnimationFrame(tick);
}

// ───────── End ─────────

function renderEnd(prev) {
    const s = state;
    const firstTime = !prev || prev.status !== 'done';
    let banner;
    if (solo()) {
        const ranked = s.players.filter((p) => s.scores[p.id] !== undefined).sort((x, y) => s.scores[y.id] - s.scores[x.id]);
        const top = ranked.length ? s.scores[ranked[0].id] : 0;
        const winners = ranked.filter((p) => s.scores[p.id] === top);
        const sd = winners.length === 1 ? ownerOf(winners[0].id) : null;
        if (firstTime && sd) { sfx.win(); confetti(SOLO_COLORS[Math.max(0, s.players.findIndex((p) => p.id === winners[0].id)) % SOLO_COLORS.length]); }
        banner = el('div', { class: `banner ${sd ? sd.cls : 'tie'}`, style: sd?.style, role: 'status' },
            sd ? S('end.win_solo', { name: winners[0].name, n: top }) : S('end.tie_solo', { names: winners.map((p) => p.name).join(' و'), n: top }));
    } else {
        const { A, B } = s.scores;
        const winner = A === B ? null : (A > B ? 'A' : 'B');
        if (firstTime && winner) { sfx.win(); confetti(getComputedStyle(document.documentElement).getPropertyValue(`--${winner}`).trim()); }
        banner = el('div', { class: `banner ${winner || 'tie'}`, role: 'status' },
            winner ? S('end.win', { team: teamName(winner), a: Math.max(A, B), b: Math.min(A, B) }) : S('end.tie', { a: A, b: B }));
    }

    // Per-player stats
    const stats = s.players.filter((p) => !p.spectator).map((p) => {
        const gs = s.guesses.filter((g) => g.player_id === p.id);
        const right = gs.filter((g) => g.result === 'match');
        return {
            ...p,
            points: gs.reduce((t, g) => t + g.points, 0),
            right: right.length,
            wrong: gs.filter((g) => ['none', 'timeout'].includes(g.result)).length,
            far: right.length ? Math.max(...right.map((g) => g.rank)) : 0,
            avg: right.length ? right.reduce((t, g) => t + g.rank, 0) / right.length : null,
        };
    }).sort((a, b) => b.points - a.points);

    const table = el('table', { class: 'stats' }, [
        el('thead', {}, el('tr', {}, [S('end.player'), S('end.points'), S('end.right'), S('end.wrong'), S('end.farthest')].map((h) => el('th', { text: h })))),
        el('tbody', {}, stats.map((p) => el('tr', {}, [
            el('td', {}, [el('span', { class: `dot ${ownerOf(p.id, p.team).cls}`, style: ownerOf(p.id, p.team).style }), p.name]),
            el('td', { class: 'num', text: String(p.points) }),
            el('td', { class: 'num', text: String(p.right) }),
            el('td', { class: 'num', text: String(p.wrong) }),
            el('td', { class: 'num', text: p.far ? String(p.far) : '—' }),
        ]))),
    ]);

    const titles = [];
    const brave = [...stats].filter((p) => p.far).sort((a, b) => b.far - a.far)[0];
    if (brave) titles.push([S('end.t_brave'), brave.name, S('end.t_brave_d', { rank: brave.far })]);
    const careful = [...stats].filter((p) => p.avg !== null).sort((a, b) => a.avg - b.avg)[0];
    if (careful && careful !== brave) titles.push([S('end.t_careful'), careful.name, S('end.t_careful_d', { avg: careful.avg.toFixed(1) })]);
    const oops = [...stats].sort((a, b) => b.wrong - a.wrong)[0];
    if (oops && oops.wrong > 0) titles.push([S('end.t_wrong'), oops.name, S('end.t_wrong_d', { n: oops.wrong })]);

    // Full list reveal: 100 → 1, 20ms apart
    const taken = new Map(s.revealed.map((r) => [r.rank, r]));
    const full = s.full_list || [];
    const grid = el('div', { class: 'board', role: 'grid', 'aria-label': S('end.full_list') });
    const cells = full.map((it) => {
        const r = taken.get(it.rank);
        const sd = r ? ownerOf(r.player_id, r.team) : null;
        return el('div', { class: 'cell' + (r ? ` ${sd.cls}` : ' ghost'), style: sd?.style, role: 'gridcell', title: it.extra || null, 'aria-label': `${it.rank}: ${it.name}` }, [el('span', { class: 'n', text: String(it.rank) }), it.name]);
    });
    cells.forEach((c) => grid.append(c));
    if (!revealDone && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        cells.forEach((c) => { c.style.visibility = 'hidden'; });
        [...cells].reverse().forEach((c, i) => setTimeout(() => { c.style.visibility = ''; c.classList.add('show'); }, i * 20));
    }
    revealDone = true;

    const forgotten = full.filter((it) => !taken.has(it.rank));

    const hostBtns = s.is_host ? el('div', { class: 'row', style: 'flex-wrap:wrap' }, [
        el('button', { class: 'btn lantern grow', text: S('end.new_round'), onclick: () => act(api.action, 'reset') }),
        el('button', { class: 'btn ghost grow', text: S('end.other_list'), onclick: () => act(api.action, 'reset') }),
    ]) : null;

    app.replaceChildren(
        header([el('button', { class: 'btn ghost sm', text: S('end.leave'), onclick: leave })]),
        el('div', { class: 'stack' }, [
            banner,
            el('section', { class: 'card', style: 'overflow-x:auto' }, table),
            titles.length ? el('section', { class: 'card' }, [el('h3', { text: S('end.titles') }), el('div', { class: 'titles' }, titles.map(([t, n, d]) => el('div', { class: 'title-card' }, [el('b', { text: `🏅 ${t}` }), el('span', { text: `${n} — ${d}` })])))]) : null,
            el('section', { class: 'card' }, [el('h3', { text: `${S('end.full_list')}: ${s.list?.title || ''}` }), grid,
                el('div', { class: 'board-legend' }, [el('span', { text: S('play.first') }), el('span', { text: S('play.last') })])]),
            forgotten.length ? el('section', { class: 'card' }, [el('h3', { text: `${S('end.forgotten')} (${forgotten.length})` }), el('div', { class: 'forgotten' }, forgotten.map((it) => el('span', { text: `${it.rank}. ${it.name}` })))]) : null,
            hostBtns,
        ]),
    );
}

// ───────── Start ─────────

export default function () {
    deviceId();
    store.set('room', code);
    poll();
    requestAnimationFrame(tick);
    document.addEventListener('visibilitychange', () => { if (!document.hidden && state) state.version = 0; });
}
