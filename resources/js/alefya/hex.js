// ألف ياء — hex grid maths. "odd-r" layout: odd rows (2nd, 4th…) are shifted half a cell.
import { hexPoints } from './core.js';

export const LETTERS = ['أ', 'ب', 'ت', 'ث', 'ج', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ك', 'ل', 'م', 'ن', 'هـ', 'و', 'ي'];

export function neighbours(r, c, rows, cols) {
    const s = r % 2 === 1 ? 1 : 0;
    return [[r, c - 1], [r, c + 1], [r - 1, c - 1 + s], [r - 1, c + s], [r + 1, c - 1 + s], [r + 1, c + s]]
        .filter(([y, x]) => y >= 0 && y < rows && x >= 0 && x < cols);
}

/**
 * Shortest chain of cells where ok(r, c) is true, from one side to the other, or null.
 * axis 'rows' → top row to bottom row; 'cols' → first column to last column.
 * @returns {Array<[number, number]>|null}
 */
export function findPath(rows, cols, ok, axis) {
    const start = [];
    if (axis === 'rows') for (let c = 0; c < cols; c++) start.push([0, c]);
    else for (let r = 0; r < rows; r++) start.push([r, 0]);

    const parent = new Map();
    const queue = [];
    for (const [r, c] of start) {
        if (ok(r, c)) { parent.set(r + ',' + c, null); queue.push([r, c]); }
    }

    while (queue.length) {
        const [r, c] = queue.shift();
        if ((axis === 'rows' && r === rows - 1) || (axis === 'cols' && c === cols - 1)) {
            const path = [];
            let key = r + ',' + c;
            while (key) { path.unshift(key.split(',').map(Number)); key = parent.get(key); }
            return path;
        }
        for (const [nr, nc] of neighbours(r, c, rows, cols)) {
            const k = nr + ',' + nc;
            if (!parent.has(k) && ok(nr, nc)) { parent.set(k, r + ',' + c); queue.push([nr, nc]); }
        }
    }
    return null;
}

export function shuffle(list) {
    const a = [...list];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

/** N×M letters: no repeats until all 28 are used. */
export function randomLetters(count) {
    let out = [];
    while (out.length < count) out = out.concat(shuffle(LETTERS));
    return out.slice(0, count);
}

/** Darker version of a #rrggbb colour (for the racing-flag squares). */
export function shade(hex, amount = 0.38) {
    const n = parseInt(hex.replace('#', ''), 16);
    const f = (v) => Math.round(v * (1 - amount)).toString(16).padStart(2, '0');
    return '#' + f(n >> 16) + f((n >> 8) & 255) + f(n & 255);
}

let boardSeq = 0;

/**
 * Builds the board SVG in the مين الأسبق style: white card, tinted triangles showing each
 * team's sides, and racing-flag finish lines. frame: { top, bottom, left, right } colours.
 * Returns { svg, cells: [{ g, poly, text, r, c }] }.
 */
export function buildBoard(rows, cols, frame, { R = 46, onClick = null } = {}) {
    const NS = 'http://www.w3.org/2000/svg';
    const id = 'b' + (++boardSeq);
    const w = Math.sqrt(3) * R;
    const pad = R * 0.95;
    const W = 2 * pad + w * cols + w / 2;
    const H = 2 * pad + 2 * R + (rows - 1) * 1.5 * R;
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${W.toFixed(1)} ${H.toFixed(1)}`);
    svg.setAttribute('class', 'hex-board');
    svg.setAttribute('role', 'grid');

    const mk = (tag, attrs = {}) => {
        const n = document.createElementNS(NS, tag);
        for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, v);
        return n;
    };

    const defs = mk('defs');
    const pattern = (name, color) => {
        const p = mk('pattern', { id: `${id}-${name}`, width: 14, height: 14, patternUnits: 'userSpaceOnUse' });
        p.append(
            mk('rect', { width: 14, height: 14, fill: color }),
            mk('rect', { width: 7, height: 7, fill: shade(color) }),
            mk('rect', { x: 7, y: 7, width: 7, height: 7, fill: shade(color) }),
        );
        return p;
    };
    defs.append(pattern('v', frame.top), pattern('h', frame.left));
    const clip = mk('clipPath', { id: `${id}-clip` });
    clip.append(mk('rect', { x: 0, y: 0, width: W, height: H, rx: 24 }));
    defs.append(clip);
    svg.append(defs);

    // Tinted triangles meeting in the middle tell each team which sides to join.
    const mx = W / 2, my = H / 2;
    const bg = mk('g', { 'clip-path': `url(#${id}-clip)` });
    bg.append(
        mk('rect', { x: 0, y: 0, width: W, height: H, fill: '#ffffff' }),
        mk('polygon', { points: `0,0 ${W},0 ${mx},${my}`, fill: frame.top, opacity: 0.2 }),
        mk('polygon', { points: `0,${H} ${W},${H} ${mx},${my}`, fill: frame.bottom, opacity: 0.2 }),
        mk('polygon', { points: `0,0 0,${H} ${mx},${my}`, fill: frame.left, opacity: 0.2 }),
        mk('polygon', { points: `${W},0 ${W},${H} ${mx},${my}`, fill: frame.right, opacity: 0.2 }),
    );
    // Finish lines
    const s = R * 0.26, inset = R * 0.12, span = pad * 1.2;
    bg.append(
        mk('rect', { x: span, y: inset, width: W - 2 * span, height: s, rx: s / 2, fill: `url(#${id}-v)` }),
        mk('rect', { x: span, y: H - inset - s, width: W - 2 * span, height: s, rx: s / 2, fill: `url(#${id}-v)` }),
        mk('rect', { x: inset, y: span, width: s, height: H - 2 * span, rx: s / 2, fill: `url(#${id}-h)` }),
        mk('rect', { x: W - inset - s, y: span, width: s, height: H - 2 * span, rx: s / 2, fill: `url(#${id}-h)` }),
    );
    svg.append(bg);

    const cells = [];
    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            const cx = pad + w / 2 + c * w + (r % 2 ? w / 2 : 0);
            const cy = pad + R + r * 1.5 * R;
            const g = mk('g', { class: 'cell', role: 'gridcell', tabindex: '0', 'data-r': r, 'data-c': c });
            const poly = mk('polygon', {
                class: 'face', points: hexPoints(cx, cy, R - 3), fill: '#fff3dc',
                stroke: '#ffffff', 'stroke-width': 5, 'stroke-linejoin': 'round',
            });
            const rim = mk('polygon', {
                points: hexPoints(cx, cy, R - 3), fill: 'none', stroke: '#120d2b', 'stroke-opacity': 0.12, 'stroke-width': 1.5,
            });
            const text = mk('text', { x: cx, y: cy + R * 0.07, 'text-anchor': 'middle', 'dominant-baseline': 'central', 'font-size': R * 0.8, fill: '#120d2b' });
            g.append(poly, rim, text);
            if (onClick) {
                g.addEventListener('click', () => onClick(r, c));
                g.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClick(r, c); } });
            }
            svg.append(g);
            cells.push({ g, poly, text, r, c });
        }
    }

    return { svg, cells };
}
