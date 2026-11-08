import { $, toast, S } from '../core.js';
import { setupBell } from '../notif.js';
import { logout } from '../auth.js';

export default function () {
    setupBell();
    $('#logoutBtn')?.addEventListener('click', async () => {
        try { await logout(); location.reload(); } catch { toast(S('app.error'), true); }
    });
}
