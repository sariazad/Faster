// ألف ياء — the main board: two teams, hex letters, connect your two sides to win the round.
import { $, $$, S, el, store, toast, openModal, closeModal, isModalOpen, sfx, confetti, config } from '../core.js';
import { buildBoard, findPath, randomLetters } from '../hex.js';
import { getQuestion } from '../api.js';
import { loadSettings, colorPair } from '../settings.js';
import { listen } from '../sync.js';

const KEY_MAP = { 'ا': 'أ', 'إ': 'أ', 'آ': 'أ', 'ه': 'هـ', 'ة': 'هـ', 'ى': 'ي', 'ؤ': 'و', 'ئ': 'ي' };

let game = null;
let board = null;           // { svg, cells }
let busy = false;           // between "round won" and the next board
let stopSync = null;
let questionToken = 0;

const N = () => game.size;
const save = () => store.set('game', game);
const roundName = (i) => S('play.round_names')[i] || String(i + 1);

function newGame(mode, mystery, sync) {
    const s = loadSettings();
    game = {
        size: s.size,
        rounds: s.rounds,
        autoWin: s.autoWin,
        colors: colorPair(s.colors),
        names: [s.team1, s.team2],
        contestName: s.contestName,
        mode, mystery, sync,
        round: 0,
        wins: [0, 0],
        correct: [0, 0],
        finished: false,
        ...freshBoard(s.size),
    };
    save();
}

function freshBoard(size) {
    return { letters: randomLetters(size * size), owners: Array(size * size).fill(null), selected: null, question: null, revealed: false };
}

// ───────── Rendering ─────────

function renderBoard() {
    const [a, b] = game.colors;
    board = buildBoard(N(), N(), { top: a, bottom: a, left: b, right: b }, { onClick: clickCell });
    $('#boardArea').replaceChildren(board.svg);
    board.cells.forEach((_, i) => paintCell(i));
}

function paintCell(i) {
    const cell = board.cells[i];
    const owner = game.owners[i];
    const selected = game.selected === i;
    const fill = owner !== null ? game.colors[owner] : selected ? 'var(--selected)' : '#fff';
    cell.poly.setAttribute('fill', fill);
    cell.text.textContent = owner !== null ? '' : (game.mystery && !selected ? '؟' : game.letters[i]);
    cell.g.setAttribute('aria-label', owner !== null ? `${game.names[owner]}` : `حرف ${game.mystery && !selected ? 'مخفي' : game.letters[i]}`);
    cell.g.classList.remove('win');
}

function renderSide() {
    const names = S('play.round_names');
    $('#roundTitle').innerHTML = '';
    $('#roundTitle').append(el('span', { class: 'a', text: S('play.round') }), ' ', el('span', { class: 'b', text: names[game.round] || game.round + 1 }));
    $('#contestName').textContent = game.contestName || '';
    renderTeams();
    renderQuestion();
}

function renderTeams() {
    const wrap = $('#teamBars');
    wrap.replaceChildren();
    [0, 1].forEach((t) => {
        wrap.append(el('div', { class: 'team-bar', style: 'margin-bottom:10px' }, [
            el('div', { class: 'hex-count', style: `background:${game.colors[t]}`, 'aria-label': `${S('play.wins')}: ${game.wins[t]}`, text: String(game.wins[t]) }),
            el('div', { class: 'name', style: `color:${game.colors[t]}`, text: game.names[t] }),
            game.selected !== null && !busy
                ? el('button', {
                    class: 'award-btn', style: `background:${game.colors[t]}`,
                    'aria-label': `${S('play.award')} ${game.names[t]} (${t + 1})`, title: `${t + 1}`,
                    onclick: () => award(t),
                }, el('i', { class: 'fa-solid fa-check' }))
                : null,
        ]));
    });
}

function renderQuestion() {
    const hex = $('#qHex');
    const text = $('#qText');
    const contrib = $('#qContrib');
    const answer = $('#qAnswer');
    const actions = $('#qActions');
    const i = game.selected;

    contrib.hidden = true;
    answer.hidden = true;
    actions.hidden = true;

    if (i === null) {
        hex.textContent = '!';
        hex.classList.add('empty');
        text.className = 'q-text placeholder';
        text.textContent = S('play.pick_cell');
        return;
    }

    hex.textContent = game.letters[i];
    hex.classList.remove('empty');

    if (game.mode === 'portal') {
        text.className = 'q-text placeholder';
        text.textContent = S('play.portal_mode');
        return;
    }

    const q = game.question;
    if (!q) {
        text.className = 'q-text placeholder';
        text.textContent = S('app.loading');
        return;
    }
    if (q.missing) {
        text.className = 'q-text placeholder';
        text.textContent = S('play.no_questions');
        actions.hidden = false;
        $('#answerBtn').hidden = true;
        return;
    }

    text.className = 'q-text';
    text.textContent = q.question;
    if (q.contributor) {
        contrib.hidden = false;
        contrib.textContent = `✒️ ${S('play.contributor')} ${q.contributor}`;
    }
    actions.hidden = false;
    $('#answerBtn').hidden = false;
    $('#answerBtn span').textContent = game.revealed ? S('play.hide_answer') : S('play.show_answer');
    if (game.revealed) {
        answer.hidden = false;
        answer.replaceChildren(el('i', { class: 'fa-solid fa-check' }), el('span', { text: q.answer }));
    }
}

async function loadQuestion(reset = false) {
    if (game.selected === null || game.mode === 'portal') return;
    const token = ++questionToken;
    game.question = null;
    game.revealed = false;
    renderQuestion();
    try {
        const q = await getQuestion(game.letters[game.selected], reset);
        if (token !== questionToken) return;
        game.question = q;
    } catch (e) {
        if (token !== questionToken) return;
        game.question = { missing: true };
        if (e.status !== 404) toast(S('app.error'), true);
    }
    save();
    renderQuestion();
}

// ───────── Game actions ─────────

function select(i) {
    const prev = game.selected;
    game.selected = i;
    game.question = null;
    game.revealed = false;
    if (prev !== null) paintCell(prev);
    paintCell(i);
    sfx.select();
    save();
    renderTeams();
    renderQuestion();
    loadQuestion();
}

function clickCell(r, c) {
    if (busy || game.finished) return;
    const i = r * N() + c;
    const owner = game.owners[i];

    if (owner !== null) {
        // Fix a mistake: team 1 → team 2 → empty.
        game.correct[owner] = Math.max(0, game.correct[owner] - 1);
        game.owners[i] = owner === 0 ? 1 : null;
        if (game.owners[i] !== null) game.correct[game.owners[i]]++;
        paintCell(i);
        save();
        checkWin();
        return;
    }

    if (game.selected === i) return;
    select(i);
}

function award(team) {
    if (busy || game.selected === null) return;
    const i = game.selected;
    game.owners[i] = team;
    game.correct[team]++;
    game.selected = null;
    game.question = null;
    game.revealed = false;
    questionToken++;
    paintCell(i);
    sfx.correct();
    save();
    renderTeams();
    renderQuestion();
    checkWin();
}

function checkWin() {
    if (!game.autoWin || busy) return;
    const n = N();
    for (const team of [0, 1]) {
        const path = findPath(n, n, (r, c) => game.owners[r * n + c] === team, team === 0 ? 'rows' : 'cols');
        if (path) {
            roundWon(team, path);
            return;
        }
    }
}

function roundWon(team, path = []) {
    busy = true;
    game.selected = null;
    path.forEach(([r, c]) => board.cells[r * N() + c].g.classList.add('win'));
    sfx.win();
    toast(S('play.round_won', { team: game.names[team] }));
    renderTeams();
    renderQuestion();

    setTimeout(() => {
        game.wins[team]++;
        if (game.wins[team] >= game.rounds) {
            game.finished = true;
            save();
            busy = false;
            renderTeams();
            showResults();
            return;
        }
        game.round++;
        Object.assign(game, freshBoard(N()));
        save();
        transition(() => {
            busy = false;
            renderBoard();
            renderSide();
        });
    }, 2000);
}

function transition(done) {
    $('#sunName').textContent = roundName(game.round);
    $('#sunburst').classList.add('open');
    sfx.whoosh();
    setTimeout(() => {
        done();
        $('#sunburst').classList.remove('open');
    }, 2300);
}

function showResults() {
    const wrap = $('#resultTeams');
    wrap.replaceChildren();
    const winner = game.wins[0] === game.wins[1] ? null : game.wins[0] > game.wins[1] ? 0 : 1;
    [0, 1].forEach((t) => {
        wrap.append(el('div', { class: 'team' }, [
            t === winner ? el('div', { class: 'trophy' }, [el('i', { class: 'fa-solid fa-trophy' }), el('span', { text: S('result.champion') })]) : null,
            el('div', { class: 'big-hex', style: `background:${game.colors[t]}`, text: String(game.wins[t]) }),
            el('div', { class: 'name-bar' }, [
                el('b', { style: `color:${game.colors[t]}`, text: game.names[t] }),
                el('span', { text: `${S('result.correct')} ${game.correct[t]}` }),
            ]),
        ]));
    });
    $('#results').classList.add('open');
    confetti();
}

function restartRound() {
    Object.assign(game, freshBoard(N()));
    busy = false;
    save();
    renderBoard();
    renderSide();
    closeModal('menuModal');
}

function startNewGame() {
    store.remove('game');
    location.reload();
}

// ───────── Keyboard ─────────

function onKey(e) {
    if (isModalOpen() || busy || game.finished || e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.target.matches('input, textarea')) return;

    if (e.key === '1' || e.key === '١') { award(0); e.preventDefault(); return; }
    if (e.key === '2' || e.key === '٢') { award(1); e.preventDefault(); return; }
    if (e.key === 'Escape' && game.selected !== null) {
        const prev = game.selected;
        game.selected = null;
        questionToken++;
        paintCell(prev);
        renderTeams();
        renderQuestion();
        save();
        return;
    }

    const letter = KEY_MAP[e.key] || e.key;
    if (letter.length > 2 || !game.letters.includes(letter)) return;
    pickLetter(letter);
}

/** Selects a free cell with this letter; pressing again moves to its next copy. */
function pickLetter(letter) {
    const free = game.letters.map((l, i) => (l === letter && game.owners[i] === null ? i : -1)).filter((i) => i >= 0);
    if (!free.length) return;
    const pos = free.indexOf(game.selected);
    select(free[(pos + 1) % free.length]);
}

// ───────── Start ─────────

function begin() {
    renderBoard();
    renderSide();

    const pill = $('#syncPill');
    if (game.sync) {
        pill.hidden = false;
        pill.classList.toggle('on', !!config.account);
        pill.textContent = config.account ? `🔗 ${S('play.sync_on')}` : S('play.sync_off');
        stopSync = listen((letter) => { if (!busy && !game.finished && !isModalOpen()) pickLetter(letter); }, { poll: !!config.account });
    }

    if (game.finished) showResults();
}

export default function () {
    $('#menuBtn').addEventListener('click', () => {
        const manual = !game.autoWin && !game.finished;
        $('#manualAward').hidden = !manual;
        if (manual) {
            $('#manualAwardBtns').replaceChildren(...[0, 1].map((t) => el('button', {
                class: 'btn btn-sm', style: `background:${game.colors[t]};color:#fff;width:100%`,
                text: game.names[t],
                onclick: () => { closeModal('menuModal'); roundWon(t); },
            })));
        }
        openModal('menuModal');
    });
    $('#restartBtn').addEventListener('click', restartRound);
    $('#newGameBtn').addEventListener('click', startNewGame);
    $('#resNew').addEventListener('click', startNewGame);
    $('#answerBtn').addEventListener('click', () => { game.revealed = !game.revealed; save(); renderQuestion(); });
    $('#newQBtn').addEventListener('click', () => loadQuestion());
    document.addEventListener('keydown', onKey);
    window.addEventListener('beforeunload', () => stopSync?.());

    const saved = store.get('game');
    if (saved && !saved.finished && Array.isArray(saved.letters) && saved.letters.length === saved.size * saved.size) {
        game = saved;
        game.question = null;
        game.revealed = false;
        begin();
        if (game.selected !== null) loadQuestion();
        return;
    }

    // New game: ask how questions are shown first.
    const modal = $('#modeModal');
    modal.dataset.locked = '1';
    const toggles = { mystery: false, sync: false };
    $$('[data-toggle]', modal).forEach((group) => {
        $$('.choice', group).forEach((btn) => btn.addEventListener('click', () => {
            $$('.choice', group).forEach((b) => b.classList.toggle('on', b === btn));
            toggles[group.dataset.toggle] = btn.dataset.value === '1';
        }));
    });
    $$('[data-mode]', modal).forEach((btn) => btn.addEventListener('click', () => {
        if (btn.dataset.mode === 'portal') window.open(config.base + '/questions', '_blank', 'noopener');
        newGame(btn.dataset.mode, toggles.mystery, toggles.sync);
        closeModal(modal);
        begin();
    }));
    openModal(modal);
}
