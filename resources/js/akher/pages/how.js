import { $$ } from '../core.js';

export default function () {
    $$('[data-tab]').forEach((t) => t.addEventListener('click', () => {
        $$('[data-tab]').forEach((x) => { x.classList.toggle('on', x === t); x.setAttribute('aria-selected', String(x === t)); });
        $$('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== t.dataset.tab; });
    }));
}
