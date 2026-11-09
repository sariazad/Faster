import { config, initTheme, initSound } from './core.js';

const pages = {
    home: () => import('./pages/home.js'),
    how: () => import('./pages/how.js'),
    room: () => import('./pages/room.js'),
};

initTheme();
initSound();
pages[config.page]?.().then((m) => m.default?.());
