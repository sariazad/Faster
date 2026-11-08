import { $, $$, S, toast } from '../core.js';
import { contribute } from '../api.js';

const MAX = 5;

/** Same rule as the server: first letter of the answer, ignoring «ال» and tashkeel. */
export function letterOf(answer) {
    let word = (answer || '').replace(/[ً-ٰٟـ«»"'()]/g, '').trim().split(/\s+/)[0] || '';
    if (word.length > 3 && word.startsWith('ال')) word = word.slice(2);
    const first = word.charAt(0);
    const map = { 'ا': 'أ', 'إ': 'أ', 'آ': 'أ', 'ٱ': 'أ', 'ه': 'هـ', 'ة': 'هـ', 'ى': 'ي', 'ؤ': 'و', 'ئ': 'ي' };
    const l = map[first] || first;
    return /^[ء-ي]$/.test(l) || l === 'هـ' ? l : null;
}

export default function () {
    const blocks = $('#blocks');
    const tpl = $('#blockTpl');

    const renumber = () => {
        $$('.q-block', blocks).forEach((b, i) => {
            $('.num', b).textContent = `${S('contribute.block')} ${i + 1}`;
            $('.remove', b).hidden = i === 0;
        });
        const full = $$('.q-block', blocks).length >= MAX;
        $('#addBlock').hidden = full;
        $('#maxNote').hidden = !full;
    };

    const add = () => {
        const node = tpl.content.firstElementChild.cloneNode(true);
        const answer = $('.a', node);
        answer.addEventListener('input', () => {
            const l = letterOf(answer.value);
            $('.letter-tag', node).textContent = l ? `${S('contribute.letter')} ${l}` : '';
        });
        $('.remove', node).addEventListener('click', () => { node.remove(); renumber(); });
        blocks.append(node);
        renumber();
        $('.q', node).focus();
    };

    $('#addBlock').addEventListener('click', add);
    add();

    $('#contribForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const items = $$('.q-block', blocks).map((b) => ({ question: $('.q', b).value.trim(), answer: $('.a', b).value.trim() }));
        if (items.some((it) => it.question.length < 4 || !letterOf(it.answer))) {
            toast(S('contribute.invalid'), true);
            return;
        }
        $('#cSend').disabled = true;
        try {
            await contribute($('#cName').value.trim(), items);
            toast(S('contribute.sent'));
            blocks.replaceChildren();
            add();
        } catch {
            toast(S('contribute.invalid'), true);
        } finally {
            $('#cSend').disabled = false;
        }
    });
}
