import { $, S, el } from '../core.js';
import { leaderboard } from '../api.js';

export default function () {
    const body = $('#lbBody');
    const medals = ['🥇', '🥈', '🥉'];

    const load = async (code = '') => {
        try {
            const data = await leaderboard(code);
            body.replaceChildren();
            if (!data.top.length) {
                body.append(el('tr', {}, el('td', { colspan: 6, text: S('leaderboard.empty') })));
            }
            for (const row of data.top) {
                body.append(el('tr', {}, [
                    el('td', {}, row.rank <= 3 ? el('span', { class: 'medal', text: medals[row.rank - 1] }) : String(row.rank)),
                    el('td', { text: row.player, style: 'direction:ltr' }),
                    el('td', { text: row.won ? S('leaderboard.won') : S('leaderboard.lost') }),
                    el('td', { text: String(row.correct) }),
                    el('td', { text: row.time_left + 's', style: 'direction:ltr' }),
                    el('td', { text: row.date || '' }),
                ]));
            }
            if (code) {
                $('#rankResult').textContent = data.mine
                    ? S('leaderboard.your_rank', { rank: data.mine.rank, total: data.mine.total })
                    : S('leaderboard.no_rank');
            }
        } catch {
            body.replaceChildren(el('tr', {}, el('td', { colspan: 6, text: S('app.error') })));
        }
    };

    const input = $('#rankCode');
    input.addEventListener('input', () => { input.value = input.value.replace(/\D/g, '').slice(0, 10); });
    $('#rankForm').addEventListener('submit', (e) => {
        e.preventDefault();
        if (input.value.length === 10) load(input.value);
    });

    load(input.value.length === 10 ? input.value : '');
}
