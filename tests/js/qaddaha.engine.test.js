import { describe, expect, it } from 'vitest';
import {
    applyIntent, boardSize, boldPercent, newGame, publicView, reduce, RuleError, tryReduce, winner,
    BLUFFS, FREEZE_SECONDS, STREAK_BONUS, TIME_BONUS,
} from '../../resources/js/qaddaha/engine.js';

const pool = Array.from({ length: 6 }, (_, i) => ({ id: i + 1, name: 'فئة ' + (i + 1) }));
const q = (cat, level, team) => ({ id: `${cat}-${level}-${team}`, text: `سؤال ${cat} ${level}`, answers: ['أ', 'ب', 'ج', 'د', 'ه', 'و', 'ز', 'ح'], category: 'فئة ' + cat });

function questionsFor(state) {
    const out = {};
    for (const id of state.draft.picks.flat()) {
        out[id] = {};
        for (const level of [200, 400, 600]) out[id][level] = [q(id, level, 0), q(id, level, 1)];
    }
    return out;
}

const final = { id: 'final', text: 'السؤال الأخير', answers: ['1', '2', '3'], category: 'نهائي' };

/** A game on the board: team 0 picked categories 1 & 3, team 1 picked 2 & 4. */
function started(kind = 'full', golden = null) {
    let s = newGame({ teams: [{ name: 'الصقور' }, { name: 'النمور' }], kind, pool });
    const picks = kind === 'duel' ? [1, 2] : [1, 2, 3, 4];
    picks.forEach((cat, i) => { s = reduce(s, { type: 'DRAFT_PICK', team: i % 2, cat }); });
    return reduce(s, { type: 'START', questions: questionsFor(s), final, golden });
}

const T0 = 1_000_000;
const run = (s, ...actions) => actions.reduce((st, a) => reduce(st, a), s);

/** Team 0 picks its 200 cell; team 0 bids `bid`, team 1 withdraws → team 0 challenges. */
function toChallenge(s, { cell = '0-0-200', bid = 4, by = 0 } = {}) {
    const picker = Number(cell.split('-')[1]);
    s = run(s, { type: 'PICK_CELL', team: picker, cell });
    if (by === picker) {
        s = run(s, { type: 'RAISE', team: picker, value: bid }, { type: 'WITHDRAW', team: 1 - picker });
    } else {
        s = run(s, { type: 'RAISE', team: picker, value: 3 }, { type: 'RAISE', team: by, value: bid }, { type: 'WITHDRAW', team: picker });
    }
    return s;
}

const succeed = (s, n, at = T0 + 5000) => {
    s = reduce(s, { type: 'START_TIMER', now: T0 });
    for (let i = 0; i < n; i++) s = reduce(s, { type: 'COUNT', delta: 1, now: at });
    return s;
};

describe('setup and draft', () => {
    it('sizes the three kinds', () => {
        expect(boardSize('full')).toBe(24);
        expect(boardSize('quick')).toBe(16);
        expect(boardSize('duel')).toBe(12);
        expect(started('full').cells).toHaveLength(24);
        expect(started('quick').cells).toHaveLength(16);
        expect(started('duel').cells).toHaveLength(12);
    });

    it('alternates the draft and refuses out of turn or repeated picks', () => {
        let s = newGame({ teams: [{ name: 'أ' }, { name: 'ب' }], pool });
        expect(tryReduce(s, { type: 'DRAFT_PICK', team: 1, cat: 1 }).error).toBe('not_your_turn');
        s = reduce(s, { type: 'DRAFT_PICK', team: 0, cat: 1 });
        expect(tryReduce(s, { type: 'DRAFT_PICK', team: 1, cat: 1 }).error).toBe('already_picked');
        s = run(s, { type: 'DRAFT_PICK', team: 1, cat: 2 }, { type: 'DRAFT_PICK', team: 0, cat: 3 }, { type: 'DRAFT_PICK', team: 1, cat: 4 });
        expect(s.phase).toBe('review');
        s = reduce(s, { type: 'DRAFT_RESET' });
        expect(s.phase).toBe('draft');
        expect(s.draft.picks).toEqual([[], []]);
    });

    it('needs a question for every cell', () => {
        let s = newGame({ teams: [{ name: 'أ' }, { name: 'ب' }], kind: 'duel', pool });
        s = run(s, { type: 'DRAFT_PICK', team: 0, cat: 1 }, { type: 'DRAFT_PICK', team: 1, cat: 2 });
        expect(tryReduce(s, { type: 'START', questions: {}, final }).error).toBe('missing_question');
    });

    it('teams pick only from their own column, in turn', () => {
        const s = started();
        expect(tryReduce(s, { type: 'PICK_CELL', team: 1, cell: '0-1-200' }).error).toBe('not_your_turn');
        expect(tryReduce(s, { type: 'PICK_CELL', team: 0, cell: '0-1-200' }).error).toBe('not_your_column');
        expect(reduce(s, { type: 'PICK_CELL', team: 0, cell: '0-0-200' }).phase).toBe('auction');
    });
});

describe('auction', () => {
    it('opens at 3 or more, then each bid must be higher', () => {
        let s = reduce(started(), { type: 'PICK_CELL', team: 0, cell: '0-0-200' });
        expect(tryReduce(s, { type: 'RAISE', team: 0, value: 2 }).error).toBe('bid_too_low');
        expect(tryReduce(s, { type: 'WITHDRAW', team: 0 }).error).toBe('nothing_to_withdraw');
        s = reduce(s, { type: 'RAISE', team: 0, value: 3 });
        expect(tryReduce(s, { type: 'RAISE', team: 0, value: 4 }).error).toBe('not_your_turn');
        expect(tryReduce(s, { type: 'RAISE', team: 1, value: 3 }).error).toBe('bid_too_low');
        s = reduce(s, { type: 'RAISE', team: 1, value: 7 }); // a jump
        s = reduce(s, { type: 'WITHDRAW', team: 0 });
        expect(s.phase).toBe('challenge');
        expect(s.current.challenger).toBe(1);
        expect(s.current.required).toBe(7);
        expect(s.stats.highest).toMatchObject({ team: 1, value: 7 });
    });
});

describe('scoring', () => {
    it('success gives the question points to the challenger', () => {
        let s = toChallenge(started(), { bid: 4 });
        s = succeed(s, 4, T0 + 20_000); // 10 s left of 30 → no time bonus
        expect(s.phase).toBe('result');
        expect(s.scores).toEqual([200, 0]);
        expect(s.cells.find((c) => c.id === '0-0-200').used).toBe(true);
    });

    it('failure gives the full points to the other team', () => {
        let s = toChallenge(started(), { bid: 4 });
        s = run(s, { type: 'START_TIMER', now: T0 }, { type: 'COUNT', delta: 1, now: T0 + 1000 });
        expect(tryReduce(s, { type: 'TIME_UP', now: T0 + 10_000 }).error).toBe('too_early');
        s = reduce(s, { type: 'TIME_UP', now: T0 + 30_000 });
        expect(s.current.result.success).toBe(false);
        expect(s.scores).toEqual([0, 200]);
    });

    it('rewards bold bids: +10% per answer above 5, at most 50%', () => {
        expect(boldPercent(5)).toBe(0);
        expect(boldPercent(7)).toBe(20);
        expect(boldPercent(20)).toBe(50);
        let s = toChallenge(started(), { bid: 8 });
        s = succeed(s, 8, T0 + 20_000);
        expect(s.scores[0]).toBe(260); // 200 + 30%
    });

    it('adds the time bonus when more than half the time is left', () => {
        let s = toChallenge(started(), { bid: 4 });
        s = succeed(s, 4, T0 + 5000);
        expect(s.scores[0]).toBe(200 + TIME_BONUS);
        expect(s.stats.fastest).toMatchObject({ team: 0, seconds: 5 });
    });

    it('the golden cell doubles the points for both sides', () => {
        let s = toChallenge(started('full', '0-0-200'), { bid: 4 });
        expect(s.current.base).toBe(400);
        s = reduce(s, { type: 'JUDGE', success: false, now: T0 });
        expect(s.scores).toEqual([0, 400]);
    });

    it('three successes in a row light the streak', () => {
        let s = started();
        s = succeed(toChallenge(s, { cell: '0-0-200' }), 4, T0 + 20_000);
        s = reduce(s, { type: 'NEXT' });
        s = succeed(toChallenge(s, { cell: '0-1-200', by: 0 }), 4, T0 + 20_000); // team 1 picks, team 0 wins the auction
        s = reduce(s, { type: 'NEXT' });
        expect(s.hot[0]).toBe(false);
        s = succeed(toChallenge(s, { cell: '0-0-400' }), 4, T0 + 30_000);
        expect(s.hot[0]).toBe(true);
        expect(s.current.result.lines.some((l) => l.key === 'streak' && l.points === STREAK_BONUS)).toBe(true);
        expect(s.scores[0]).toBe(200 + 200 + 400 + STREAK_BONUS);
        s = reduce(s, { type: 'NEXT' });
        s = toChallenge(s, { cell: '1-1-200', by: 0 });
        s = reduce(s, { type: 'JUDGE', success: false, now: T0 });
        expect(s.hot[0]).toBe(false);
        expect(s.streak[0]).toBe(0);
    });
});

describe('ورّني! (calling the bluff)', () => {
    const called = () => {
        let s = reduce(started(), { type: 'PICK_CELL', team: 0, cell: '0-0-200' });
        s = run(s, { type: 'RAISE', team: 0, value: 5 }, { type: 'CALL_BLUFF', team: 1 });
        return s;
    };

    it('the bidder must deliver; failing pays the caller +50%', () => {
        let s = called();
        expect(s.current.challenger).toBe(0);
        expect(s.current.bluff).toBe(1);
        expect(s.bluffs[1]).toBe(BLUFFS - 1);
        s = reduce(s, { type: 'JUDGE', success: false, now: T0 });
        expect(s.scores).toEqual([0, 300]);
        expect(s.stats.bluffs[0]).toMatchObject({ caller: 1, bidder: 0, value: 5, success: false });
    });

    it('delivering pays the bidder +50%', () => {
        let s = succeed(called(), 5, T0 + 20_000);
        expect(s.scores).toEqual([300, 0]);
    });

    it('only twice per team, and no stealing after a call', () => {
        let s = called();
        expect(tryReduce(s, { type: 'STEAL', team: 1 }).error).toBe('no_steal_after_bluff');
        s = reduce(s, { type: 'JUDGE', success: true, now: T0 });
        s = reduce(s, { type: 'NEXT' }); // team 1's turn
        s = run(s, { type: 'PICK_CELL', team: 1, cell: '0-1-200' }, { type: 'RAISE', team: 1, value: 3 }, { type: 'RAISE', team: 0, value: 4 }, { type: 'CALL_BLUFF', team: 1 });
        s = reduce(s, { type: 'JUDGE', success: true, now: T0 });
        s = reduce(s, { type: 'NEXT' });
        s = run(s, { type: 'PICK_CELL', team: 0, cell: '0-0-400' }, { type: 'RAISE', team: 0, value: 3 });
        expect(s.bluffs[1]).toBe(0);
        expect(tryReduce(s, { type: 'CALL_BLUFF', team: 1 }).error).toBe('no_bluffs_left');
    });
});

describe('helps', () => {
    it('double ×2 on success, and on failure the other team gets double', () => {
        let s = reduce(started(), { type: 'TOGGLE_DOUBLE', team: 0 });
        s = toChallenge(s, { bid: 4 });
        expect(s.current.double).toBe(0);
        expect(s.helps[0].double).toBe(false);
        const won = succeed(s, 4, T0 + 20_000);
        expect(won.scores).toEqual([400, 0]);
        const lost = reduce(s, { type: 'JUDGE', success: false, now: T0 });
        expect(lost.scores).toEqual([0, 400]);
    });

    it('double comes back if the other team takes the challenge', () => {
        let s = reduce(started(), { type: 'TOGGLE_DOUBLE', team: 0 });
        s = toChallenge(s, { bid: 5, by: 1 });
        expect(s.current.challenger).toBe(1);
        expect(s.current.double).toBe(null);
        expect(s.helps[0].double).toBe(true);
    });

    it('minus 2 lowers the number before the clock starts, never below 1', () => {
        let s = toChallenge(started(), { bid: 6 });
        expect(tryReduce(s, { type: 'MINUS2', team: 1 }).error).toBe('not_challenger');
        s = reduce(s, { type: 'MINUS2', team: 0 });
        expect(s.current.required).toBe(4);
        expect(tryReduce(s, { type: 'MINUS2', team: 0 }).error).toBe('help_used');
        const late = reduce(toChallenge(started(), { bid: 6 }), { type: 'START_TIMER', now: T0 });
        expect(tryReduce(late, { type: 'MINUS2', team: 0 }).error).toBe('timer_running');
        let small = toChallenge(started(), { bid: 3 });
        small = reduce(small, { type: 'MINUS2', team: 0 });
        expect(small.current.required).toBe(1);
    });

    it('steal takes the challenge at +1 over the current number (after minus 2)', () => {
        let s = toChallenge(started(), { bid: 6 });
        s = reduce(s, { type: 'MINUS2', team: 0 });   // 6 → 4
        expect(tryReduce(s, { type: 'STEAL', team: 0 }).error).toBe('cannot_steal_own');
        s = reduce(s, { type: 'STEAL', team: 1 });    // 4 + 1
        expect(s.current.challenger).toBe(1);
        expect(s.current.required).toBe(5);
        expect(tryReduce(s, { type: 'STEAL', team: 0 }).error).toBe('already_stolen');
    });

    it('steal returns the victim\'s double', () => {
        let s = reduce(started(), { type: 'TOGGLE_DOUBLE', team: 0 });
        s = toChallenge(s, { bid: 4 });
        s = reduce(s, { type: 'STEAL', team: 1 });
        expect(s.current.double).toBe(null);
        expect(s.helps[0].double).toBe(true);
        s = reduce(s, { type: 'JUDGE', success: false, now: T0 });
        expect(s.scores).toEqual([200, 0]); // no double: plain points to team 0
    });

    it('freeze stops the clock for 10 seconds', () => {
        let s = toChallenge(started(), { bid: 4 });
        s = run(s, { type: 'START_TIMER', now: T0 }, { type: 'FREEZE', team: 0, now: T0 + 5000 });
        expect(s.current.timer.endsAt).toBe(T0 + (30 + FREEZE_SECONDS) * 1000);
        expect(s.current.timer.frozenUntil).toBe(T0 + 15_000);
        expect(tryReduce(s, { type: 'TIME_UP', now: T0 + 30_000 }).error).toBe('too_early');
        let before = reduce(toChallenge(started(), { bid: 4 }), { type: 'FREEZE', team: 0 });
        before = reduce(before, { type: 'START_TIMER', now: T0 });
        expect(before.current.timer.endsAt).toBe(T0 + 40_000);
        expect(before.current.timer.frozenUntil).toBe(T0 + 10_000);
    });
});

describe('judging tools', () => {
    it('the judge can take back a tap', () => {
        let s = reduce(toChallenge(started(), { bid: 4 }), { type: 'START_TIMER', now: T0 });
        s = run(s, { type: 'COUNT', delta: 1, now: T0 }, { type: 'COUNT', delta: 1, now: T0 }, { type: 'COUNT', delta: -1, now: T0 });
        expect(s.current.count).toBe(1);
        s = reduce(s, { type: 'COUNT', delta: -1, now: T0 });
        s = reduce(s, { type: 'COUNT', delta: -1, now: T0 });
        expect(s.current.count).toBe(0);
    });

    it('undo restores the state from before the last question', () => {
        let s = reduce(started(), { type: 'TOGGLE_DOUBLE', team: 0 });
        const before = s;
        s = succeed(toChallenge(s, { bid: 4 }), 4, T0 + 20_000);
        s = reduce(s, { type: 'NEXT' });
        s = reduce(s, { type: 'UNDO' });
        expect(s.phase).toBe('board');
        expect(s.scores).toEqual([0, 0]);
        expect(s.turn).toBe(0);
        expect(s.pendingDouble).toBe(true);
        expect(s.cells).toEqual(before.cells);
        expect(tryReduce(s, { type: 'UNDO' }).error).toBe('nothing_to_undo');
    });

    it('manual corrections', () => {
        let s = toChallenge(started(), { bid: 4 });
        s = reduce(s, { type: 'SET_AUCTION', team: 1, value: 6 });
        expect(s.current).toMatchObject({ challenger: 1, required: 6 });
        s = reduce(s, { type: 'ADJUST', team: 0, delta: 100 });
        s = reduce(s, { type: 'ADJUST', team: 0, delta: -100 });
        s = reduce(s, { type: 'ADJUST', team: 1, delta: -100 });
        expect(s.scores).toEqual([0, -100]);
    });
});

describe('final round and the end', () => {
    /** Team 0 always delivers; team 1 only on its 600 cells (or never when `shutout`). */
    function playBoard(kind = 'duel', shutout = false) {
        let s = started(kind);
        let guard = 0;
        while (s.phase !== 'final_bet' && guard++ < 100) {
            const team = s.turn;
            const cell = s.cells.find((c) => !c.used && c.team === team);
            s = run(s, { type: 'PICK_CELL', team, cell: cell.id }, { type: 'RAISE', team, value: 3 }, { type: 'WITHDRAW', team: 1 - team });
            s = reduce(s, { type: 'JUDGE', success: team === 0 || (!shutout && cell.level === 600), now: T0 });
            s = reduce(s, { type: 'NEXT' });
        }
        return s;
    }

    it('reaches the final after the last cell', () => {
        const s = playBoard();
        expect(s.phase).toBe('final_bet');
        expect(s.cells.every((c) => c.used)).toBe(true);
        expect(s.scores[0]).toBeGreaterThan(0);
    });

    it('secret bets stay hidden until both lock, then the trailing team opens', () => {
        let s = playBoard();
        const [a, b] = s.scores;
        expect(tryReduce(s, { type: 'FINAL_BET', team: 0, value: a + 1 }).error).toBe('bad_bet');
        s = reduce(s, { type: 'FINAL_BET', team: 0, value: 500 });
        expect(tryReduce(s, { type: 'FINAL_BET', team: 0, value: 100 }).error).toBe('bet_locked');
        expect(publicView(s).final.bets).toEqual([null, null]);
        expect(tryReduce(s, { type: 'FINAL_REVEAL' }).error).toBe('bets_missing');
        expect(b).toBeGreaterThan(0);
        s = reduce(s, { type: 'FINAL_BET', team: 1, value: 100 });
        s = reduce(s, { type: 'FINAL_REVEAL' });
        expect(publicView(s).final.bets[0]).toBe(500);
        s = reduce(s, { type: 'FINAL_AUCTION' });
        const opener = a <= b ? 0 : 1;
        expect(s.current.toAct).toBe(opener);
        expect(tryReduce(s, { type: 'CALL_BLUFF', team: opener }).error).toBe('wrong_phase');
        s = run(s, { type: 'RAISE', team: opener, value: 3 }, { type: 'WITHDRAW', team: 1 - opener });
        expect(s.phase).toBe('final_challenge');
        const challenger = opener;
        const before = [...s.scores];
        s = reduce(s, { type: 'JUDGE', success: true, now: T0 });
        expect(s.scores[challenger]).toBe(before[challenger] + s.final.bets[challenger]);
        expect(s.scores[1 - challenger]).toBe(before[1 - challenger] - s.final.bets[1 - challenger]);
        s = reduce(s, { type: 'NEXT' });
        expect(s.phase).toBe('finale');
        expect(winner(s)).not.toBeUndefined();
    });

    it('a team with no points bets 0 automatically', () => {
        const s = playBoard('duel', true);
        expect(s.scores[1]).toBe(0);
        expect(s.final.locked[1]).toBe(true);
        expect(s.final.bets[1]).toBe(0);
    });

});

describe('phone intents', () => {
    const intent = (over) => ({ id: 'i-' + Math.random().toString(36).slice(2), team: 0, ...over });

    it('applies a valid intent from the right seat', () => {
        const s = started();
        const r = applyIntent(s, intent({ type: 'pick_cell', cell: '0-0-200' }), 0, T0);
        expect(r.error).toBe(null);
        expect(r.state.phase).toBe('auction');
    });

    it('refuses intents from the team whose turn it is not', () => {
        const s = started();
        expect(applyIntent(s, intent({ team: 1, type: 'pick_cell', cell: '0-1-200' }), 1, T0).error).toBe('not_your_turn');
    });

    it('refuses intents in the wrong phase', () => {
        const s = started();
        expect(applyIntent(s, intent({ type: 'raise', value: 5 }), 0, T0).error).toBe('wrong_phase');
        expect(applyIntent(s, intent({ type: 'final_bet', value: 0 }), 0, T0).error).toBe('wrong_phase');
    });

    it('refuses intents claiming another seat or unknown types (judging stays with the host)', () => {
        const s = started();
        expect(applyIntent(s, intent({ type: 'pick_cell', cell: '0-0-200' }), 1, T0).error).toBe('wrong_seat');
        expect(applyIntent(s, intent({ type: 'judge', success: true }), 0, T0).error).toBe('unknown_intent');
    });

    it('a repeated intent is applied only once', () => {
        let s = reduce(started(), { type: 'PICK_CELL', team: 0, cell: '0-0-200' });
        const i = intent({ type: 'raise', value: 4 });
        const first = applyIntent(s, i, 0, T0);
        expect(first.error).toBe(null);
        const again = applyIntent(first.state, i, 0, T0);
        expect(again.error).toBe('duplicate');
        expect(again.state).toBe(first.state);
    });
});

describe('public view', () => {
    it('hides answers and waiting questions', () => {
        let s = reduce(started(), { type: 'PICK_CELL', team: 0, cell: '0-0-200' });
        const v = publicView(s);
        expect(v.current.q.answers).toEqual([]);
        expect(v.cells[0].q.text).toBeUndefined();
        expect(v.undo).toBeUndefined();
        expect(v.final.q.text).toBeUndefined();
        s = run(s, { type: 'RAISE', team: 0, value: 3 }, { type: 'WITHDRAW', team: 1 }, { type: 'JUDGE', success: true, now: T0 });
        expect(publicView(s).current.q.answers.length).toBe(8);
    });
});

describe('errors', () => {
    it('throws RuleError for unknown actions', () => {
        expect(() => reduce(started(), { type: 'NOPE' })).toThrow(RuleError);
    });

    it('rematch resets everything but the teams', () => {
        let s = started('duel');
        s = { ...s, phase: 'finale', scores: [500, 100] };
        const r = reduce(s, { type: 'REMATCH' });
        expect(r.phase).toBe('draft');
        expect(r.scores).toEqual([0, 0]);
        expect(r.teams.map((t) => t.name)).toEqual(['الصقور', 'النمور']);
        expect(r.round).toBe(2);
    });
});
