// ألف ياء — 🔔 notifications (unread = newer than the last one opened on this device).
import { S, store, el, openModal, $ } from './core.js';
import { notifications } from './api.js';

export function setupBell() {
    const btn = $('#bellBtn');
    const badge = $('#bellCount');
    const list = $('#notifList');
    if (!btn) return;

    let items = [];
    const lastRead = () => store.get('notifRead', 0);

    const render = () => {
        list.replaceChildren();
        if (!items.length) {
            list.append(el('p', { class: 'muted', text: S('notifications.empty') }));
            return;
        }
        const read = lastRead();
        for (const n of items) {
            list.append(el('div', { class: 'notif' + (n.id > read ? ' unread' : '') }, [
                el('b', { text: n.title }),
                n.body ? el('div', { text: n.body }) : null,
                el('small', { text: n.date || '' }),
            ]));
        }
    };

    notifications().then((data) => {
        items = data.notifications || [];
        const unread = items.filter((n) => n.id > lastRead()).length;
        badge.textContent = unread;
        badge.hidden = unread === 0;
    }).catch(() => {});

    btn.addEventListener('click', () => {
        render();
        openModal('notifModal');
        if (items.length) store.set('notifRead', Math.max(...items.map((n) => n.id)));
        badge.hidden = true;
    });
}
