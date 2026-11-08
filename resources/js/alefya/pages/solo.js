// ألف ياء — single challenge: join right and left on a 3×5 board before the clock runs out.
import { $, S, el, openModal, closeModal, sfx, confetti, config, toast } from '../core.js';
import { buildBoard, findPath } from '../hex.js';
import { soloQuestions, saveScore } from '../api.js';

const GREEN = '#22d3ee';   // right answers + the two sides to join (team cyan)
const ORANGE = '#fb7185';  // wrong answers + the closed sides (team rose)
const RING = 2 * Math.PI * 52;

let state = null;

const rows = () => config.solo.rows;
const cols = () => config.solo.cols;

function preview() {
    const { svg, cells } = buildBoard(rows(), cols(), { top: ORANGE, bottom: ORANGE, left: GREEN, right: GREEN }, { R: 30 });
    cells.forEach((c) => { c.text.textContent = ''; c.g.classList.add('locked'); });
    [[1, 0], [1, 1], [0, 2], [1, 3], [1, 4]].forEach(([r, c]) => cells[r * cols() + c].poly.setAttribute('fill', GREEN));
    svg.setAttribute('width', '200');
    $('#soloPreview').replaceWith(svg);
}

async function start() {
    $('#soloStart').disabled = true;
    let data;
    try {
        data = await soloQuestions();
    } catch {
        toast(S('app.error'), true);
        $('#soloStart').disabled = false;
        return;
    }
    $('#soloStart').disabled = false;

    state = {
        questions: data.questions,
        status: Array(rows() * cols()).fill(null),
        remaining: data.seconds,
        total: data.seconds,
        bonus: data.bonus,
        correct: 0,
        wrong: 0,
        open: null,
        over: false,
        timer: null,
        last: 0,
    };

    $('#soloIntro').hidden = true;
    $('#soloGame').hidden = false;
    closeModal('soloEnd');
    document.body.classList.remove('danger-bg');
    $('#goodCount').textContent = '0';
    $('#badCount').textContent = '0';

    state.board = buildBoard(rows(), cols(), { top: ORANGE, bottom: ORANGE, left: GREEN, right: GREEN }, { onClick: clickCell });
    state.board.cells.forEach((c, i) => {
        const q = state.questions[i];
        c.text.textContent = q ? q.letter : '';
        if (!q) { c.g.classList.add('locked'); state.status[i] = 'empty'; }
    });
    $('#soloBoard').replaceChildren(state.board.svg);
    drawTimer();

    const go = el('div', { class: 'go-word', text: S('solo.go') });
    document.body.append(go);
    sfx.whoosh();
    setTimeout(() => {
        go.remove();
        state.last = performance.now();
        state.timer = setInterval(tick, 100);
    }, 1100);
}

function tick() {
    const now = performance.now();
    const before = Math.ceil(state.remaining);
    state.remaining = Math.max(0, state.remaining - (now - state.last) / 1000);
    state.last = now;
    if (Math.ceil(state.remaining) !== before && state.remaining <= 5 && state.remaining > 0) sfx.tick();
    drawTimer();
    if (state.remaining <= 0) finish(false, 'time');
}

function drawTimer() {
    const left = Math.ceil(state.remaining);
    $('#timerNum').textContent = left;
    const ratio = Math.min(1, state.remaining / state.total);
    $('#timerRing').setAttribute('stroke-dashoffset', String(RING * (1 - ratio)));
    const danger = state.remaining <= 7;
    $('#timer').classList.toggle('danger', danger);
    $('#timerRing').setAttribute('stroke', danger ? '#fb7185' : '#ffaa33');
    document.body.classList.toggle('danger-bg', danger && !state.over);
}

function clickCell(r, c) {
    if (!state || state.over) return;
    const i = r * cols() + c;
    if (state.status[i] !== null) return;
    const q = state.questions[i];
    state.open = i;
    sfx.select();
    state.board.cells[i].poly.setAttribute('fill', '#ffaa33');
    $('#soloQHex').textContent = q.letter;
    $('#soloQText').textContent = q.question;
    $('#soloOptions').replaceChildren(...q.options.map((opt) => el('button', {
        class: 'btn btn-white',
        text: opt,
        onclick: (e) => answer(i, opt, e.currentTarget),
    })));
    const modal = $('#soloQ');
    modal.dataset.locked = '1';
    openModal(modal);
}

function answer(i, choice, button) {
    if (state.open !== i || state.over) return;
    const q = state.questions[i];
    const right = choice === q.answer;
    state.open = null;
    [...$('#soloOptions').children].forEach((b) => {
        b.disabled = true;
        if (b.textContent === q.answer) b.classList.add('right');
    });
    if (!right) button.classList.add('wrong');

    state.status[i] = right ? 'right' : 'wrong';
    state.board.cells[i].poly.setAttribute('fill', right ? GREEN : ORANGE);
    state.board.cells[i].g.classList.add('locked');

    if (right) {
        state.correct++;
        state.remaining += state.bonus;
        sfx.correct();
        const bonus = el('div', { class: 'bonus', text: `+${state.bonus}` });
        $('#timer').append(bonus);
        setTimeout(() => bonus.remove(), 1000);
    } else {
        state.wrong++;
        sfx.wrong();
    }
    $('#goodCount').textContent = state.correct;
    $('#badCount').textContent = state.wrong;
    drawTimer();

    setTimeout(() => {
        closeModal('soloQ');
        const n = { r: rows(), c: cols() };
        const won = findPath(n.r, n.c, (r, c) => state.status[r * n.c + c] === 'right', 'cols');
        if (won) {
            won.forEach(([r, c]) => state.board.cells[r * n.c + c].g.classList.add('win'));
            finish(true);
            return;
        }
        const possible = findPath(n.r, n.c, (r, c) => {
            const s = state.status[r * n.c + c];
            return s === null || s === 'right';
        }, 'cols');
        if (!possible) finish(false, 'blocked');
    }, 650);
}

async function finish(won, reason = '') {
    if (state.over) return;
    state.over = true;
    clearInterval(state.timer);
    closeModal('soloQ');
    document.body.classList.remove('danger-bg');
    const timeLeft = Math.ceil(state.remaining);

    if (won) { sfx.win(); confetti(); } else { sfx.wrong(); }

    $('#soloEndTitle').textContent = won ? S('solo.won') : reason === 'time' ? S('solo.lost_time') : S('solo.lost_blocked');
    $('#soloEndStats').textContent = `${S('solo.correct')}: ${state.correct} • ${S('solo.wrong')}: ${state.wrong} • ${S('solo.time_left')}: ${timeLeft} ${S('solo.seconds')}`;
    $('#soloSaved').textContent = '';

    setTimeout(() => {
        const modal = $('#soloEnd');
        modal.dataset.locked = '1';
        openModal(modal);
    }, won ? 900 : 300);

    try {
        const res = await saveScore({ won, correct: state.correct, wrong: state.wrong, time_left: won ? timeLeft : 0 });
        $('#soloSaved').textContent = res.guest ? S('solo.saved_guest') : S('solo.saved');
    } catch { /* offline: the round still counts on screen */ }
}

export default function () {
    preview();
    $('#soloStart').addEventListener('click', start);
    $('#soloRetry').addEventListener('click', start);
}
