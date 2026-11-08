// ألف ياء — entry point: shared setup, then the module for the current page.
import { initCommon, config } from './core.js';

const pages = {
    home: () => import('./pages/home.js'),
    customize: () => import('./pages/customize.js'),
    play: () => import('./pages/play.js'),
    solo: () => import('./pages/solo.js'),
    leaderboard: () => import('./pages/leaderboard.js'),
    how: () => import('./pages/how.js'),
    login: () => import('./pages/login.js'),
    questions: () => import('./pages/portal.js'),
    contribute: () => import('./pages/contribute.js'),
};

initCommon();
pages[config.page]?.().then((m) => m.default?.());
