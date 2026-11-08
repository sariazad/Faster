// ألف ياء — بوابة الأسئلة: the host's phone. Pick the letter chosen on the big screen and read the question.
import { $, $$, S, el, store, toast, openModal, closeModal, config } from '../core.js';
import { getQuestion, rateQuestion, sendNote } from '../api.js';
import { logout } from '../auth.js';
import { publishLetter } from '../sync.js';
import { setupBell } from '../notif.js';

let letter = null;
let mystery = false;
let current = null;
let token = 0;

function setLetter(l) {
    $$('#pLetters button').forEach((b) => b.classList.toggle('on', b.dataset.letter === l));
}

async function load(reset = false) {
    const box = $('#pq');
    const my = ++token;
    box.replaceChildren(el('div', { class: 'spinner', 'aria-label': S('app.loading') }));
    $('#pControls').hidden = true;
    try {
        const q = await getQuestion(mystery ? null : letter, reset);
        if (my !== token) return;
        current = q;
        if (q.reset) toast(S('portal.reset'));
        show();
    } catch (e) {
        if (my !== token) return;
        current = null;
        box.replaceChildren(el('div', { class: 'muted', text: e.status === 404 ? S('play.no_questions') : S('app.error') }));
    }
}

function show() {
    const q = current;
    const box = $('#pq');
    box.replaceChildren(...[
        mystery ? el('span', { class: 'tag', text: `${S('portal.mystery_badge')}: ${q.letter}` }) : null,
        el('div', { class: 'q-text', text: q.question }),
        q.contributor ? el('span', { class: 'tag', text: `✒️ ${S('play.contributor')} ${q.contributor}` }) : null,
    ].filter(Boolean));
    $('#pControls').hidden = false;
    $('#pAnswer').disabled = false;
    $('#pRate').hidden = true;
    const noted = store.get('noted', []);
    $('#pNote').disabled = noted.includes(q.id);
}

export default function () {
    setupBell();

    $$('#pLetters button').forEach((b) => b.addEventListener('click', () => {
        mystery = false;
        letter = b.dataset.letter;
        setLetter(letter);
        publishLetter(letter, !!config.account);
        load();
    }));

    $('#pMystery').addEventListener('click', () => {
        mystery = true;
        letter = null;
        setLetter(null);
        load();
    });

    $('#pNew').addEventListener('click', () => load());

    $('#pAnswer').addEventListener('click', () => {
        if (!current) return;
        $('#pq').append(el('div', { class: 'portal-answer', text: current.answer }));
        $('#pAnswer').disabled = true;
        const rated = store.get('rated', []);
        $('#pRate').hidden = rated.includes(current.id);
    });

    $('#pRate').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-rate]');
        if (!btn || !current) return;
        const rated = store.get('rated', []);
        rated.push(current.id);
        store.set('rated', rated.slice(-2000));
        $('#pRate').hidden = true;
        toast(S('portal.thanks'));
        rateQuestion(current.id, btn.dataset.rate).catch(() => {});
    });

    $('#pNote').addEventListener('click', () => {
        $('#noteBody').value = '';
        openModal('noteModal');
    });

    $('#noteSend').addEventListener('click', async () => {
        const body = $('#noteBody').value.trim();
        if (!body) { toast(S('portal.note_empty'), true); return; }
        const btn = $('#noteSend');
        btn.disabled = true;
        btn.querySelector('span').textContent = S('portal.note_sending');
        btn.querySelector('i').className = 'fa-solid fa-spinner fa-spin';
        try {
            await sendNote(current.id, body);
            const noted = store.get('noted', []);
            noted.push(current.id);
            store.set('noted', noted.slice(-2000));
            $('#pNote').disabled = true;
            closeModal('noteModal');
            toast(S('portal.note_sent'));
        } catch {
            toast(S('app.error'), true);
        } finally {
            btn.disabled = false;
            btn.querySelector('span').textContent = S('portal.note_send');
            btn.querySelector('i').className = 'fa-solid fa-paper-plane';
        }
    });

    $('#logoutBtn').addEventListener('click', async () => {
        try { await logout(); } finally { location.href = config.base; }
    });
}
