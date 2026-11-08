import { $$ } from '../core.js';

export default function () {
    $$('[data-tab]').forEach((tab) => tab.addEventListener('click', () => {
        $$('[data-tab]').forEach((t) => { t.classList.toggle('on', t === tab); t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
        $$('[data-tab-panel]').forEach((p) => { p.hidden = p.dataset.tabPanel !== tab.dataset.tab; });
    }));
}
