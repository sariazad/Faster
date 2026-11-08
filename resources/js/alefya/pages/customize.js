import { $, $$, toast, S, config } from '../core.js';
import { loadSettings, saveSettings, colorPair } from '../settings.js';

export default function () {
    const s = loadSettings();

    $('#contestName').value = s.contestName;
    $('#team1').value = s.team1;
    $('#team2').value = s.team2;

    const paintTeams = () => {
        const [a, b] = colorPair(s.colors);
        $('[data-team-hex="0"]').style.background = a;
        $('[data-team-hex="1"]').style.background = b;
    };

    $$('.choices[data-setting]').forEach((group) => {
        const key = group.dataset.setting;
        const current = key === 'autoWin' ? (s.autoWin ? '1' : '0') : String(s[key]);
        $$('.choice', group).forEach((btn) => {
            const on = btn.dataset.value === current;
            btn.classList.toggle('on', on);
            btn.setAttribute('aria-checked', on ? 'true' : 'false');
            btn.setAttribute('role', 'radio');
            btn.addEventListener('click', () => {
                $$('.choice', group).forEach((b) => { b.classList.remove('on'); b.setAttribute('aria-checked', 'false'); });
                btn.classList.add('on');
                btn.setAttribute('aria-checked', 'true');
                s[key] = key === 'autoWin' ? btn.dataset.value === '1' : Number(btn.dataset.value);
                if (key === 'colors') paintTeams();
            });
        });
    });
    paintTeams();

    $('#saveBtn').addEventListener('click', () => {
        s.contestName = $('#contestName').value.trim();
        s.team1 = $('#team1').value.trim() || S('customize.team1');
        s.team2 = $('#team2').value.trim() || S('customize.team2');
        saveSettings(s);
        toast(S('customize.saved'));
        setTimeout(() => { location.href = config.base; }, 600);
    });
}
