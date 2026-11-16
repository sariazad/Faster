// قدّها؟ — the whole game as one pure reducer.
// reduce(state, action) never touches the DOM, the clock or the network: anything time-based
// arrives inside the action (`now`), anything random (the golden cell, questions) arrives
// from the caller. The same reducer drives one-device games and the host of multi-device games.

export const KINDS = {
    full: { levels: [200, 400, 600], catsPerTeam: 2 },  // 4 categories × 3 levels × 2 teams = 24
    quick: { levels: [200, 400], catsPerTeam: 2 },      // 16
    duel: { levels: [200, 400, 600], catsPerTeam: 1 },  // 12
};
export const SECONDS = { 200: 30, 400: 45, 600: 60 };
export const FINAL_SECONDS = 60;
export const MIN_BID = 3;
export const MAX_BID = 40;
export const BLUFFS = 2;            // «ورّني!» per team per game
export const BLUFF_BONUS = 50;      // %
export const BOLD_STEP = 10;        // % per answer above BOLD_FROM
export const BOLD_FROM = 5;
export const BOLD_MAX = 50;         // %
export const TIME_BONUS = 50;       // finished with more than half the time left
export const STREAK = 3;            // successes in a row → «مولّع 🔥»
export const STREAK_BONUS = 100;
export const FREEZE_SECONDS = 10;
export const MINUS = 2;
export const HELPS = ['double', 'minus2', 'steal', 'freeze'];

export class RuleError extends Error {
    constructor(code) {
        super(code);
        this.code = code;
    }
}

const fail = (code) => { throw new RuleError(code); };
const copy = (o) => JSON.parse(JSON.stringify(o));
export const other = (t) => 1 - t;
const isTeam = (t) => t === 0 || t === 1;

/* ───────────────────────── creation ───────────────────────── */

/**
 * @param {{teams: {name: string, color?: string, emblem?: string}[], kind?: string, mode?: string,
 *          pool: {id: any, name: string, icon?: string}[], round?: number}} o
 */
export function newGame({ teams, kind = 'full', mode = 'single', pool = [], round = 1 }) {
    if (!KINDS[kind]) fail('bad_kind');
    if (!Array.isArray(teams) || teams.length !== 2 || teams.some((t) => !t || !String(t.name || '').trim())) fail('bad_teams');
    const need = KINDS[kind].catsPerTeam * 2;
    if (pool.length < need) fail('pool_too_small');

    const order = [];
    for (let i = 0; i < need; i++) order.push(i % 2);

    return {
        v: 1,
        round,
        seq: 0,
        phase: 'draft',
        kind,
        mode,
        teams: teams.map((t, i) => ({ name: String(t.name).trim(), color: t.color || (i ? 'teal' : 'coral'), emblem: t.emblem || (i ? '🐯' : '🦅') })),
        pool: pool.map((c) => ({ id: c.id, name: c.name, icon: c.icon || '' })),
        draft: { order, idx: 0, picks: [[], []] },
        cats: [],
        cells: [],
        scores: [0, 0],
        turn: 0,
        helps: [Object.fromEntries(HELPS.map((h) => [h, true])), Object.fromEntries(HELPS.map((h) => [h, true]))],
        bluffs: [BLUFFS, BLUFFS],
        streak: [0, 0],
        hot: [false, false],
        pendingDouble: false,
        current: null,
        final: null,
        history: [],
        adjustments: [],
        stats: { highest: null, bluffs: [], fastest: null },
        undo: null,
        seen: [],
    };
}

/** Number of cells on the board for a kind (shown in the settings screen). */
export const boardSize = (kind) => KINDS[kind].levels.length * KINDS[kind].catsPerTeam * 2 * 2;

/* ───────────────────────── helpers ───────────────────────── */

export const cellId = (catIdx, team, level) => `${catIdx}-${team}-${level}`;

export function remaining(timer, now) {
    if (!timer || timer.endsAt === null || timer.endsAt === undefined) return timer ? timer.duration + (timer.extra || 0) : 0;
    const end = timer.stoppedAt ?? now;
    return Math.max(0, (timer.endsAt - end) / 1000);
}

export const isFrozen = (timer, now) => !!(timer && timer.frozenUntil && now < timer.frozenUntil);

export function boardDone(state) {
    return state.cells.length > 0 && state.cells.every((c) => c.used);
}

/** Percent bonus for bold winning bids. */
export const boldPercent = (required) => Math.min(BOLD_MAX, BOLD_STEP * Math.max(0, required - BOLD_FROM));

/** The most a team may bet in the final round. */
export const maxBet = (state, team) => Math.max(0, state.scores[team]);

/* ───────────────────────── reducer ───────────────────────── */

/** Applies one action; throws RuleError when it isn't allowed right now. */
export function reduce(prev, action) {
    if (!action || typeof action.type !== 'string') fail('bad_action');
    const handler = HANDLERS[action.type];
    if (!handler) fail('unknown_action');

    const s = copy(prev);
    const out = handler(s, action, prev) || s;
    out.seq = (prev.seq || 0) + 1;
    if (action.intentId) {
        out.seen = [...(out.seen || []), action.intentId].slice(-64);
    }
    return out;
}

/** Same as reduce() but returns {state, error} instead of throwing. */
export function tryReduce(state, action) {
    try {
        return { state: reduce(state, action), error: null };
    } catch (e) {
        if (e instanceof RuleError) return { state, error: e.code };
        throw e;
    }
}

const need = (cond, code) => { if (!cond) fail(code); };
const needPhase = (s, ...phases) => need(phases.includes(s.phase), 'wrong_phase');
const needTeam = (t) => need(isTeam(t), 'bad_team');

const HANDLERS = {
    /* draft */
    DRAFT_PICK(s, { team, cat }) {
        needPhase(s, 'draft');
        needTeam(team);
        need(team === s.draft.order[s.draft.idx], 'not_your_turn');
        need(s.pool.some((c) => String(c.id) === String(cat)), 'bad_category');
        need(!s.draft.picks.flat().some((c) => String(c) === String(cat)), 'already_picked');
        const picked = s.pool.find((c) => String(c.id) === String(cat)).id;
        s.draft.picks[team].push(picked);
        s.draft.idx++;
        if (s.draft.idx >= s.draft.order.length) s.phase = 'review';
    },

    DRAFT_RESET(s) {
        needPhase(s, 'review', 'draft');
        s.draft = { order: s.draft.order, idx: 0, picks: [[], []] };
        s.phase = 'draft';
    },

    /**
     * questions: { [catId]: { [level]: [questionForTeam0Column, questionForTeam1Column] } }
     * final: the final-round question, golden: the cell id with doubled points (or null).
     */
    START(s, { questions, final, golden = null }) {
        needPhase(s, 'review');
        need(questions && typeof questions === 'object', 'missing_question');
        const { levels } = KINDS[s.kind];
        const [a, b] = s.draft.picks;
        const ordered = [];
        for (let i = 0; i < Math.max(a.length, b.length); i++) {
            if (a[i] !== undefined) ordered.push(a[i]);
            if (b[i] !== undefined) ordered.push(b[i]);
        }

        s.cats = ordered.map((id) => {
            const c = s.pool.find((p) => String(p.id) === String(id));
            return { id: c.id, name: c.name, icon: c.icon };
        });
        s.cells = [];
        s.cats.forEach((cat, ci) => {
            for (const team of [0, 1]) {
                for (const level of levels) {
                    const q = questions[cat.id]?.[level]?.[team];
                    need(q && q.text && Array.isArray(q.answers), 'missing_question');
                    const id = cellId(ci, team, level);
                    s.cells.push({ id, cat: ci, team, level, golden: id === golden, used: false, q: cleanQ(q, cat, level), result: null });
                }
            }
        });
        need(final && final.text && Array.isArray(final.answers), 'missing_question');
        s.final = { q: cleanQ(final, { name: final.category || '' }, 600), bets: [null, null], locked: [false, false], revealed: false };
        s.phase = 'board';
        s.turn = 0;
    },

    /* board */
    TOGGLE_DOUBLE(s, { team }) {
        needPhase(s, 'board');
        need(team === s.turn, 'not_your_turn');
        need(s.helps[team].double, 'help_used');
        s.pendingDouble = !s.pendingDouble;
    },

    PICK_CELL(s, { team, cell }, prev) {
        needPhase(s, 'board');
        need(team === s.turn, 'not_your_turn');
        const c = s.cells.find((x) => x.id === cell);
        need(c, 'bad_cell');
        need(c.team === team, 'not_your_column');
        need(!c.used, 'cell_used');

        s.undo = snapshot(prev);
        const double = s.pendingDouble ? team : null;
        if (double !== null) s.helps[team].double = false;
        s.pendingDouble = false;
        s.current = {
            cell: c.id,
            q: c.q,
            level: c.level,
            golden: c.golden,
            base: c.level * (c.golden ? 2 : 1),
            picker: team,
            double,
            bid: null,
            bids: [],
            toAct: team,
            bluff: null,
            challenger: null,
            required: null,
            minus2: false,
            stolen: false,
            freeze: false,
            timer: { duration: SECONDS[c.level], extra: 0, endsAt: null, frozenUntil: null, startedAt: null, stoppedAt: null },
            count: 0,
            result: null,
            final: false,
        };
        s.phase = 'auction';
    },

    /* auction (also the final auction) */
    RAISE(s, { team, value }) {
        needPhase(s, 'auction', 'final_auction');
        const cur = s.current;
        need(team === cur.toAct, 'not_your_turn');
        need(Number.isInteger(value), 'bad_bid');
        need(value <= MAX_BID, 'bid_too_high');
        need(cur.bid ? value > cur.bid.value : value >= MIN_BID, 'bid_too_low');
        cur.bid = { team, value };
        cur.bids.push({ team, value });
        cur.toAct = other(team);
        if (!s.stats.highest || value > s.stats.highest.value) {
            s.stats.highest = { team, value, cat: cur.q.category };
        }
    },

    WITHDRAW(s, { team }) {
        needPhase(s, 'auction', 'final_auction');
        const cur = s.current;
        need(team === cur.toAct, 'not_your_turn');
        need(cur.bid && cur.bid.team !== team, 'nothing_to_withdraw');
        endAuction(s);
    },

    CALL_BLUFF(s, { team }) {
        needPhase(s, 'auction');
        const cur = s.current;
        need(team === cur.toAct, 'not_your_turn');
        need(cur.bid && cur.bid.team !== team, 'nothing_to_call');
        need(s.bluffs[team] > 0, 'no_bluffs_left');
        s.bluffs[team]--;
        cur.bluff = team;
        endAuction(s);
    },

    /* challenge: helps before/while the clock runs */
    MINUS2(s, { team }) {
        needPhase(s, 'challenge');
        const cur = s.current;
        need(team === cur.challenger, 'not_challenger');
        need(cur.timer.endsAt === null, 'timer_running');
        need(s.helps[team].minus2, 'help_used');
        need(!cur.minus2, 'help_used');
        s.helps[team].minus2 = false;
        cur.minus2 = true;
        cur.required = Math.max(1, cur.required - MINUS);
    },

    STEAL(s, { team }) {
        needPhase(s, 'challenge');
        const cur = s.current;
        need(isTeam(team) && team !== cur.challenger, 'cannot_steal_own');
        need(cur.timer.endsAt === null, 'timer_running');
        need(cur.bluff === null, 'no_steal_after_bluff');
        need(!cur.stolen, 'already_stolen');
        need(s.helps[team].steal, 'help_used');
        s.helps[team].steal = false;
        const victim = cur.challenger;
        cur.stolen = true;
        cur.stolenFrom = victim;
        cur.challenger = team;
        cur.required += 1; // on top of the current number, after any «خصم 2»
        if (cur.double === victim) {
            s.helps[victim].double = true; // the double goes back to its owner
            cur.double = null;
        }
    },

    FREEZE(s, { team, now }) {
        needPhase(s, 'challenge');
        const cur = s.current;
        need(team === cur.challenger, 'not_challenger');
        need(s.helps[team].freeze, 'help_used');
        need(!cur.freeze, 'help_used');
        need(cur.result === null, 'already_judged');
        s.helps[team].freeze = false;
        cur.freeze = true;
        const t = cur.timer;
        if (t.endsAt === null) {
            t.extra = FREEZE_SECONDS; // the first ten seconds of the clock stand still
        } else {
            need(Number.isFinite(now), 'bad_time');
            const from = Math.max(now, t.frozenUntil || 0);
            t.frozenUntil = from + FREEZE_SECONDS * 1000;
            t.endsAt += FREEZE_SECONDS * 1000;
        }
    },

    START_TIMER(s, { now }) {
        needPhase(s, 'challenge', 'final_challenge');
        need(Number.isFinite(now), 'bad_time');
        const t = s.current.timer;
        need(t.endsAt === null, 'timer_running');
        t.startedAt = now;
        t.endsAt = now + (t.duration + t.extra) * 1000;
        if (t.extra) t.frozenUntil = now + t.extra * 1000;
    },

    /* judging (host only) */
    COUNT(s, { delta, now }) {
        needPhase(s, 'challenge', 'final_challenge');
        const cur = s.current;
        need(cur.timer.endsAt !== null, 'timer_not_started');
        need(delta === 1 || delta === -1, 'bad_delta');
        cur.count = Math.max(0, cur.count + delta);
        if (cur.count >= cur.required) return resolve(s, true, now);
    },

    TIME_UP(s, { now }) {
        needPhase(s, 'challenge', 'final_challenge');
        const t = s.current.timer;
        need(t.endsAt !== null, 'timer_not_started');
        need(Number.isFinite(now) && now >= t.endsAt - 300, 'too_early');
        return resolve(s, s.current.count >= s.current.required, now);
    },

    JUDGE(s, { success, now }) {
        needPhase(s, 'challenge', 'final_challenge');
        return resolve(s, !!success, now);
    },

    SET_AUCTION(s, { team, value }) {
        needPhase(s, 'challenge', 'final_challenge');
        needTeam(team);
        need(Number.isInteger(value) && value >= 1 && value <= MAX_BID, 'bad_bid');
        s.current.challenger = team;
        s.current.required = value;
        s.current.bid = { team, value };
    },

    ADJUST(s, { team, delta }) {
        needTeam(team);
        need(Number.isInteger(delta) && delta !== 0 && Math.abs(delta) <= 1000, 'bad_delta');
        need(!['draft', 'review'].includes(s.phase), 'wrong_phase');
        s.scores[team] += delta;
        s.adjustments.push({ team, delta, phase: s.phase });
    },

    NEXT(s) {
        needPhase(s, 'result', 'final_result');
        if (s.phase === 'final_result') {
            s.phase = 'finale';
            return;
        }
        const picker = s.current.picker;
        s.current = null;
        if (boardDone(s)) {
            s.phase = 'final_bet';
            s.final.bets = [null, null];
            s.final.locked = [false, false];
            for (const t of [0, 1]) {
                if (maxBet(s, t) === 0) { s.final.bets[t] = 0; s.final.locked[t] = true; }
            }
        } else {
            s.phase = 'board';
            s.turn = other(picker);
        }
    },

    UNDO(s) {
        need(s.undo, 'nothing_to_undo');
        need(['result', 'board', 'auction', 'challenge'].includes(s.phase), 'wrong_phase');
        const back = copy(s.undo);
        back.undo = null;
        back.seen = s.seen;
        return back;
    },

    /* final round */
    FINAL_BET(s, { team, value }) {
        needPhase(s, 'final_bet');
        needTeam(team);
        need(!s.final.locked[team], 'bet_locked');
        need(Number.isInteger(value) && value >= 0 && value <= maxBet(s, team), 'bad_bet');
        s.final.bets[team] = value;
        s.final.locked[team] = true;
    },

    FINAL_REVEAL(s) {
        needPhase(s, 'final_bet');
        need(s.final.locked[0] && s.final.locked[1], 'bets_missing');
        s.final.revealed = true;
        s.phase = 'final_reveal';
    },

    FINAL_AUCTION(s) {
        needPhase(s, 'final_reveal');
        const opener = s.scores[0] <= s.scores[1] ? 0 : 1; // the team behind opens
        s.undo = null;
        s.current = {
            cell: null,
            q: s.final.q,
            level: 600,
            golden: false,
            base: 0,
            picker: opener,
            double: null,
            bid: null,
            bids: [],
            toAct: opener,
            bluff: null,
            challenger: null,
            required: null,
            minus2: false,
            stolen: false,
            freeze: false,
            timer: { duration: FINAL_SECONDS, extra: 0, endsAt: null, frozenUntil: null, startedAt: null, stoppedAt: null },
            count: 0,
            result: null,
            final: true,
        };
        s.phase = 'final_auction';
    },

    REMATCH(s) {
        needPhase(s, 'finale');
        return newGame({ teams: s.teams, kind: s.kind, mode: s.mode, pool: s.pool, round: (s.round || 1) + 1 });
    },
};

function cleanQ(q, cat, level) {
    return {
        id: q.id ?? null,
        text: String(q.text),
        answers: q.answers.map(String),
        category: q.category || cat.name || '',
        level,
    };
}

function snapshot(state) {
    const s = copy(state);
    s.undo = null;
    return s;
}

function endAuction(s) {
    const cur = s.current;
    cur.challenger = cur.bid.team;
    cur.required = cur.bid.value;
    if (cur.double !== null && cur.double !== cur.challenger) {
        s.helps[cur.double].double = true; // the double only works for the team that takes the challenge
        cur.double = null;
    }
    if (cur.bluff !== null) {
        s.stats.bluffs.push({ caller: cur.bluff, bidder: cur.challenger, value: cur.required });
    }
    s.phase = cur.final ? 'final_challenge' : 'challenge';
}

/** Ends the challenge: points, streaks, history, stats. */
function resolve(s, success, now) {
    const cur = s.current;
    need(cur.result === null, 'already_judged');
    const t = cur.timer;
    const at = Number.isFinite(now) ? now : (t.startedAt ?? 0);
    if (t.endsAt !== null) t.stoppedAt = Math.min(at, t.endsAt);
    const left = remaining(t, at);
    const total = t.duration + (t.extra || 0);
    const used = Math.max(0, Math.round(total - left));
    const c = cur.challenger;
    const o = other(c);
    const awards = [0, 0];
    const lines = [];

    if (cur.final) {
        const bets = s.final.bets;
        if (success) {
            awards[c] = bets[c];
            awards[o] = -bets[o];
        } else {
            awards[c] = -bets[c];
            awards[o] = bets[o];
        }
        lines.push({ team: c, key: 'final_bet', points: awards[c] }, { team: o, key: 'final_bet', points: awards[o] });
    } else if (success) {
        let pct = boldPercent(cur.required);
        if (pct) lines.push({ team: c, key: 'bold', pct });
        if (cur.bluff === o) {
            pct += BLUFF_BONUS;
            lines.push({ team: c, key: 'bluff', pct: BLUFF_BONUS });
        }
        let pts = Math.round(cur.base * (1 + pct / 100));
        if (cur.double === c) {
            pts *= 2;
            lines.push({ team: c, key: 'double' });
        }
        lines.unshift({ team: c, key: 'base', points: cur.base });
        awards[c] = pts;
        if (t.endsAt !== null && left > t.duration / 2) {
            awards[c] += TIME_BONUS;
            lines.push({ team: c, key: 'time', points: TIME_BONUS });
        }
        s.streak[c]++;
        if (s.streak[c] % STREAK === 0) {
            awards[c] += STREAK_BONUS;
            s.hot[c] = true;
            lines.push({ team: c, key: 'streak', points: STREAK_BONUS });
        }
        if (t.endsAt !== null && (!s.stats.fastest || used < s.stats.fastest.seconds)) {
            s.stats.fastest = { team: c, seconds: used, required: cur.required, cat: cur.q.category };
        }
    } else {
        let pts = cur.base;
        lines.push({ team: o, key: 'base', points: cur.base });
        if (cur.bluff === o) {
            pts = Math.round(cur.base * (1 + BLUFF_BONUS / 100));
            lines.push({ team: o, key: 'bluff', pct: BLUFF_BONUS });
        }
        if (cur.double === c) {
            pts *= 2;
            lines.push({ team: o, key: 'double' });
        }
        awards[o] = pts;
        s.streak[c] = 0;
        s.hot[c] = false;
    }

    s.scores[0] += awards[0];
    s.scores[1] += awards[1];
    cur.result = { success, count: cur.count, left: Math.round(left), used, awards, lines };

    if (cur.bluff !== null) {
        const b = s.stats.bluffs[s.stats.bluffs.length - 1];
        if (b) b.success = success;
    }

    if (!cur.final) {
        const cell = s.cells.find((x) => x.id === cur.cell);
        cell.used = true;
        cell.result = { challenger: c, success, required: cur.required, awards };
    }
    s.history.push({
        cell: cur.cell,
        final: cur.final,
        cat: cur.q.category,
        level: cur.level,
        challenger: c,
        required: cur.required,
        count: cur.count,
        success,
        bluff: cur.bluff,
        stolen: cur.stolen,
        awards,
        used,
    });
    s.phase = cur.final ? 'final_result' : 'result';
    return s;
}

/* ───────────────────────── views ───────────────────────── */

/**
 * What the phones (and anyone without the host token) may see: no answers before the reveal,
 * no questions waiting on the board, no secret bets before both teams locked theirs.
 */
export function publicView(state) {
    const s = copy(state);
    delete s.undo;
    delete s.seen;
    s.cells = (s.cells || []).map((c) => ({ ...c, q: { category: c.q?.category || '' } }));
    if (s.current) {
        const show = ['result', 'final_result'].includes(s.phase);
        s.current.q = { text: s.current.q.text, category: s.current.q.category, level: s.current.q.level, answers: show ? s.current.q.answers : [] };
    }
    if (s.final) {
        const open = ['final_auction', 'final_challenge', 'final_result', 'finale'].includes(s.phase);
        s.final.q = open ? { text: s.final.q.text, category: s.final.q.category } : { category: s.final.q.category };
        if (!s.final.revealed) s.final.bets = [null, null];
    }
    return s;
}

/** Winner index, or null for a tie. */
export function winner(state) {
    if (state.scores[0] === state.scores[1]) return null;
    return state.scores[0] > state.scores[1] ? 0 : 1;
}

/* ───────────────────────── phone intents ───────────────────────── */

const INTENTS = {
    draft_pick: (i) => ({ type: 'DRAFT_PICK', cat: i.cat }),
    toggle_double: () => ({ type: 'TOGGLE_DOUBLE' }),
    pick_cell: (i) => ({ type: 'PICK_CELL', cell: i.cell }),
    raise: (i) => ({ type: 'RAISE', value: i.value }),
    withdraw: () => ({ type: 'WITHDRAW' }),
    bluff: () => ({ type: 'CALL_BLUFF' }),
    minus2: () => ({ type: 'MINUS2' }),
    steal: () => ({ type: 'STEAL' }),
    freeze: () => ({ type: 'FREEZE' }),
    final_bet: (i) => ({ type: 'FINAL_BET', value: i.value }),
};

export const INTENT_TYPES = Object.keys(INTENTS);

/**
 * Validates an intent sent by a team's phone and applies it.
 * seatTeam is the team the server says the sending device sits on.
 * Returns {state, error}; error is null when applied.
 */
export function applyIntent(state, intent, seatTeam, now) {
    if (!intent || typeof intent !== 'object') return { state, error: 'bad_intent' };
    if (!intent.id || typeof intent.id !== 'string') return { state, error: 'bad_intent' };
    if ((state.seen || []).includes(intent.id)) return { state, error: 'duplicate' };
    if (!isTeam(intent.team) || intent.team !== seatTeam) return { state, error: 'wrong_seat' };
    const make = INTENTS[intent.type];
    if (!make) return { state, error: 'unknown_intent' };
    const action = { ...make(intent), team: intent.team, now, intentId: intent.id };
    return tryReduce(state, action);
}
