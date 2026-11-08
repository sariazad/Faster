import { $, $$, S, toast } from '../core.js';
import { login, demoCode } from '../auth.js';

export default function () {
    const code = $('#code');
    const pins = $$('[data-pin]');
    const form = $('#loginForm');

    const showPin = () => {
        const ready = code.value.length === 10;
        $('#pinWrap').hidden = !ready;
        if (ready && !pins.some((p) => p.value)) pins[0].focus();
    };

    code.addEventListener('input', () => {
        code.value = code.value.replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/\D/g, '').slice(0, 10);
        showPin();
    });

    pins.forEach((input, i) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/\D/g, '').slice(-1);
            if (input.value && pins[i + 1]) pins[i + 1].focus();
            if (pins.every((p) => p.value)) form.requestSubmit();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !input.value && pins[i - 1]) { pins[i - 1].focus(); pins[i - 1].value = ''; e.preventDefault(); }
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const pin = pins.map((p) => p.value).join('');
        if (code.value.length !== 10) { code.focus(); return; }
        if (pin.length !== 4) { showPin(); pins.find((p) => !p.value)?.focus(); return; }

        $('#loginBtn').disabled = true;
        try {
            const res = await login(code.value, pin);
            toast(res.first ? S('login.welcome_first') : S('login.welcome'));
            setTimeout(() => { location.href = form.dataset.next; }, 500);
        } catch (err) {
            toast(err.message === 'unknown' ? S('login.unknown') : S('login.wrong'), true);
            pins.forEach((p) => { p.value = ''; });
            pins[0].focus();
            $('#loginBtn').disabled = false;
        }
    });

    $('#demoBtn').addEventListener('click', async () => {
        try {
            const { code: c } = await demoCode();
            code.value = c;
            showPin();
            toast(S('login.demo_done', { code: c }));
        } catch {
            toast(S('app.error'), true);
        }
    });

    code.focus();
}
