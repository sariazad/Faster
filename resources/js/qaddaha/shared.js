// قدّها؟ — bits shared by the host screen and the phones: team colours, labels, small widgets.
import { el } from './core.js';
import { MIN_BID } from './engine.js';

/** Team colours (one per team, fixed on every screen). Gold is kept for points. */
export const COLORS = {
    coral: { main: '#FF5A5F', ink: '#2B0607', name: 'مرجاني' },
    teal: { main: '#22D3C5', ink: '#032B28', name: 'فيروزي' },
    violet: { main: '#A78BFA', ink: '#1E1240', name: 'بنفسجي' },
    lime: { main: '#A3E635', ink: '#1A2A03', name: 'ليموني' },
    orange: { main: '#FB923C', ink: '#2E1503', name: 'برتقالي' },
    sky: { main: '#38BDF8', ink: '#03243A', name: 'سماوي' },
    pink: { main: '#F472B6', ink: '#3A0822', name: 'وردي' },
};

export const EMBLEMS = ['🦅', '🐯', '🦁', '🐺', '🦈', '🐉', '🐎', '🦂', '🐪', '⚡', '🔥', '🚀'];

export const KIND_LABELS = {
    full: { name: 'كاملة', note: '٤ فئات × ٣ مستويات · ٢٤ سؤال' },
    quick: { name: 'سريعة', note: '٤ فئات × مستويين · ١٦ سؤال' },
    duel: { name: 'مواجهة ١ ضد ١', note: 'فئة لكل لاعب · ١٢ سؤال' },
};

export const HELP_LABELS = {
    double: { icon: '×2', name: 'مضاعفة' },
    minus2: { icon: '−2', name: 'خصم ٢' },
    steal: { icon: '🫳', name: 'سرقة' },
    freeze: { icon: '❄️', name: 'تجميد' },
};

export const color = (team) => COLORS[team?.color] || COLORS.coral;

/** Inline CSS vars for a team (used everywhere a team colour shows). */
export const teamVars = (team) => `--tc:${color(team).main};--ti:${color(team).ink}`;

export const fmt = (n) => (n < 0 ? '−' : '') + Math.abs(n).toLocaleString('en-US');

export const nextBid = (cur) => (cur?.bid ? cur.bid.value + 1 : MIN_BID);

export function lineText(line, teams) {
    const t = teams[line.team]?.name || '';
    switch (line.key) {
        case 'base': return `${t}: نقاط السؤال ${fmt(line.points)}`;
        case 'bold': return `${t}: مكافأة الجرأة +${line.pct}%`;
        case 'bluff': return `${t}: مكافأة «ورّني!» +${line.pct}%`;
        case 'double': return `${t}: المضاعفة ×2`;
        case 'time': return `${t}: مكافأة الوقت +${line.points}`;
        case 'streak': return `${t}: مولّع 🔥 +${line.points}`;
        case 'final_bet': return `${t}: الرهان ${line.points >= 0 ? '+' : ''}${fmt(line.points)}`;
        default: return '';
    }
}

/** Score pill for a team. */
export function scorePill(state, i, { active = false } = {}) {
    const t = state.teams[i];
    return el('div', { class: 'pill' + (active ? ' active' : ''), style: teamVars(t) }, [
        el('span', { class: 'pill-em', text: t.emblem }),
        el('span', { class: 'pill-name', text: t.name }),
        el('b', { class: 'pill-score', text: fmt(state.scores[i]) }),
        state.hot?.[i] ? el('span', { class: 'pill-hot', title: 'مولّع', text: '🔥' }) : null,
    ]);
}

/** Small row of the helps a team still has. */
export function helpsRow(state, i) {
    return el('div', { class: 'helps-mini' }, [
        ...Object.entries(HELP_LABELS).map(([k, h]) => el('span', { class: 'hm' + (state.helps[i][k] ? '' : ' used'), title: h.name, text: h.icon })),
        el('span', { class: 'hm bluff' + (state.bluffs[i] ? '' : ' used'), title: 'ورّني!', text: `ورّني ×${state.bluffs[i]}` }),
    ]);
}

export const levelName = (l) => ({ 200: 'سهل', 400: 'متوسط', 600: 'صعب' }[l] || '');

/** Remaining seconds shown on screens (host clock, phones correct with an offset). */
export function secondsLeft(timer, now) {
    if (!timer) return 0;
    if (timer.endsAt === null || timer.endsAt === undefined) return timer.duration + (timer.extra || 0);
    const end = timer.stoppedAt ?? now;
    return Math.max(0, (timer.endsAt - end) / 1000);
}
