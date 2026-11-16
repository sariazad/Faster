// قدّها؟ — entry: the host screen or a leader's phone.
import { config } from './core.js';

const pages = {
    host: () => import('./host.js'),
    join: () => import('./phone.js'),
};

pages[config.page]?.().then((m) => m.default?.());
