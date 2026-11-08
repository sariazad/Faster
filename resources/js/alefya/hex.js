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

/**
 * Builds the board SVG. frame: { top, bottom, left, right } colours for the side triangles.
 * Returns { svg, cells: [{ g, poly, text, r, c }] }.
 */
export function buildBoard(rows, cols, frame, { R = 46, onClick = null } = {}) {
    const NS = 'http://www.w3.org/2000/svg';
    const w = Math.sqrt(3) * R;
    const pad = R * 0.9;
    const W = 2 * pad + w * cols + w / 2;
    const H = 2 * pad + 2 * R + (rows - 1) * 1.5 * R;
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${W.toFixed(1)} ${H.toFixed(1)}`);
    svg.setAttribute('class', 'hex-board');
    svg.setAttribute('role', 'grid');

    const mk = (tag, attrs) => {
        const n = document.createElementNS(NS, tag);
        for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, v);
        return n;
    };

    // Background: four triangles meeting in the middle tell each team which sides to join.
    const mx = W / 2, my = H / 2;
    const bg = mk('g', {});
    bg.append(
        mk('rect', { x: 0, y: 0, width: W, height: H, rx: 26, fill: '#2A0E45' }),
        mk('polygon', { points: `0,0 ${W},0 ${mx},${my}`, fill: frame.top }),
        mk('polygon', { points: `0,${H} ${W},${H} ${mx},${my}`, fill: frame.bottom }),
        mk('polygon', { points: `0,0 0,${H} ${mx},${my}`, fill: frame.left }),
        mk('polygon', { points: `${W},0 ${W},${H} ${mx},${my}`, fill: frame.right }),
    );
    const clip = mk('clipPath', { id: 'boardClip' });
    clip.append(mk('rect', { x: 0, y: 0, width: W, height: H, rx: 26 }));
    svg.append(clip);
    bg.setAttribute('clip-path', 'url(#boardClip)');
    svg.append(bg);

    const cells = [];
    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            const cx = pad + w / 2 + c * w + (r % 2 ? w / 2 : 0);
            const cy = pad + R + r * 1.5 * R;
            const g = mk('g', { class: 'cell', role: 'gridcell', tabindex: '0', 'data-r': r, 'data-c': c });
            const poly = mk('polygon', { points: hexPoints(cx, cy, R - 3), fill: '#fff', stroke: '#6A3093', 'stroke-width': 6, 'stroke-linejoin': 'round' });
            const text = mk('text', { x: cx, y: cy + R * 0.06, 'text-anchor': 'middle', 'dominant-baseline': 'central', 'font-size': R * 0.78, fill: '#6A3093' });
            g.append(poly, text);
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
