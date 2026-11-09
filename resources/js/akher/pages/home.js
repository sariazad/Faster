import { $, S, store, toast, config } from '../core.js';
import { createRoom, joinRoom, getState } from '../api.js';

const roomUrl = (code) => `${config.base}/room/${code}`;

export default function () {
    const name = $('#name');
    const code = $('#code');
    name.value = store.get('name', '');

    const params = new URLSearchParams(location.search);
    if (/^\d{4}$/.test(params.get('room') || '')) code.value = params.get('room');

    code.addEventListener('input', () => { code.value = code.value.replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/\D/g, '').slice(0, 4); });

    const myName = () => {
        const v = name.value.trim().slice(0, 20);
        if (!v) { toast(S('home.need_name'), true); name.focus(); return null; }
        store.set('name', v);
        return v;
    };

    $('#createBtn').addEventListener('click', async (e) => {
        const n = myName();
        if (!n) return;
        e.currentTarget.disabled = true;
        try {
            const { code: c } = await createRoom(n);
            store.set('room', c);
            location.href = roomUrl(c);
        } catch (err) {
            toast(err.message || S('app.error'), true);
            e.currentTarget.disabled = false;
        }
    });

    $('#joinForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const n = myName();
        if (!n) return;
        if (!/^\d{4}$/.test(code.value)) { toast(S('home.need_code'), true); code.focus(); return; }
        try {
            await joinRoom(code.value, n);
            store.set('room', code.value);
            location.href = roomUrl(code.value);
        } catch (err) {
            toast(err.message || S('app.error'), true);
        }
    });

    // Reconnect: offer the last room if it's still alive.
    const last = store.get('room');
    if (last) {
        getState(last).then((s) => {
            if (s.my_players?.length) {
                $('#resumeBox').hidden = false;
                $('#resumeBtn').textContent = `${S('home.resume')} ${last}`;
                $('#resumeBtn').href = roomUrl(last);
            }
        }).catch(() => store.del('room'));
    }

    (code.value ? (name.value ? code : name) : name).focus();
}
