// قدّها؟ — plays real games in real browser windows: one device, then host + two phones.
import { expect, test } from '@playwright/test';
import fs from 'node:fs';

const SHOTS = 'test-results/shots';
fs.mkdirSync(SHOTS, { recursive: true });

/** Console errors (ignoring blocked web fonts / CDN in sandboxes). */
function watch(page, errors, name) {
    page.on('console', (m) => {
        if (m.type() !== 'error') return;
        const t = m.text();
        if (/fonts\.g|cdnjs|ERR_TUNNEL|ERR_NAME|ERR_CONNECTION|net::ERR_|Failed to load resource/.test(t) && !/qaddaha\/api/.test(t)) return;
        errors.push(`${name}: ${t}`);
    });
    page.on('pageerror', (e) => errors.push(`${name}: ${e.message}`));
}

const game = (page) => page.evaluate(() => window.__qaddaha.state.game);
const phase = async (page) => (await game(page))?.phase;
const shot = async (page, name) => { await page.waitForTimeout(500); await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: false }); };

async function setup(page, kind, mode) {
    await page.goto('/qaddaha');
    await page.evaluate(() => localStorage.clear());
    await page.goto('/qaddaha');
    await page.fill('#team0', 'الصقور');
    await page.fill('#team1', 'النمور');
    await shot(page, '01-setup');
    await page.click('#toSettings');
    await page.click(`[data-option="kind-${kind}"]`);
    await page.click(`[data-option="mode-${mode}"]`);
    await shot(page, '02-settings');
    await page.click('#toGame');
}

async function waitPhase(page, ...phases) {
    await expect.poll(() => phase(page), { timeout: 15_000 }).toMatch(new RegExp(`^(${phases.join('|')})$`));
}

test('one device: a whole game with every twist, surviving a reload', async ({ page }) => {
    const errors = [];
    watch(page, errors, 'host');
    await setup(page, 'duel', 'single');

    // draft: one category each
    await waitPhase(page, 'draft');
    await page.locator('[data-cat]:not([disabled])').first().click();
    await shot(page, '03-draft');
    await page.locator('[data-cat]:not([disabled])').first().click();
    await waitPhase(page, 'review');
    await page.click('#startBoard');
    await waitPhase(page, 'board');
    await shot(page, '04-board');

    const bid = (v) => page.click(`[data-bid="${v}"]`);
    const firstCell = async () => {
        const g = await game(page);
        return g.cells.find((c) => !c.used && c.team === g.turn).id;
    };
    const required = async () => (await game(page)).current.required;

    let k = 0;
    let reloaded = false;
    let undone = false;
    while ((await phase(page)) === 'board') {
        const g = await game(page);
        const t = g.turn;
        if (k === 0) await page.click('#doubleBtn');
        await page.click(`[data-cell="${await firstCell()}"]`);
        await waitPhase(page, 'auction');
        if (k === 0) await shot(page, '05-auction');

        if (k === 1) {
            await bid(3);                                   // team 1 opens
            await bid(5);                                   // team 0 jumps
            await page.click('[data-act="bluff"]');         // team 1: «ورّني!»
            await waitPhase(page, 'challenge');
            await page.click('#judgeNo');                   // the bidder fails → caller +50%
        } else if (k === 2) {
            await bid(3);
            await page.click('[data-act="withdraw"]');
            await waitPhase(page, 'challenge');
            await page.click(`[data-help="steal"]`);        // the other team steals at +1
            expect((await game(page)).current.challenger).toBe(1 - t);
            await shot(page, '07-steal');
            await page.click('#judgeYes');
        } else if (k === 3) {
            await bid(7);
            await page.click('[data-act="withdraw"]');
            await waitPhase(page, 'challenge');
            const before = await required();
            await page.click('[data-help="minus2"]');
            expect(await required()).toBe(before - 2);
            await page.click('#startTimer');
            await page.click('[data-help="freeze"]');
            await page.waitForTimeout(300);
            await shot(page, '08-freeze');
            for (let i = 0; i < before - 2; i++) await page.keyboard.press('Space');
        } else {
            await bid(3);
            await page.click('[data-act="withdraw"]');
            await waitPhase(page, 'challenge');
            await page.click('#startTimer');
            if (t === 0) {
                if (k === 0) await shot(page, '06-challenge');
                for (let i = 0; i < 3; i++) await page.keyboard.press('Space'); // fast → time bonus
            } else {
                await page.keyboard.press('Space');
                await page.keyboard.press('z');               // judge takes a tap back
                expect((await game(page)).current.count).toBe(0);
                await page.click('#judgeNo');
            }
        }

        await waitPhase(page, 'result');
        if (k === 0) await shot(page, '09-result');
        if (k === 5 && !undone) {
            undone = true;
            const scores = (await game(page)).scores;
            await page.click('#undoBtn');
            await waitPhase(page, 'board');
            const back = await game(page);
            expect(back.history.length).toBe(5);
            expect(back.scores).not.toEqual(scores.length ? null : scores);
            continue; // replay the same question slot
        }
        await page.click('#nextBtn');
        if (k === 6 && !reloaded) {
            reloaded = true;
            const before = await game(page);
            await page.reload();
            await expect.poll(async () => (await game(page))?.seq).toBe(before.seq);
            expect(await phase(page)).toBe(before.phase);
        }
        k++;
    }

    const end = await game(page);
    expect(end.phase).toBe('final_bet');
    expect(end.cells.every((c) => c.used)).toBe(true);
    expect(end.cells.some((c) => c.golden)).toBe(true);
    expect(end.history.some((h) => h.bluff !== null)).toBe(true);
    expect(end.history.some((h) => h.stolen)).toBe(true);
    expect(end.stats.fastest).not.toBeNull();
    expect(end.hot[0] || end.history.filter((h) => h.challenger === 0 && h.success).length >= 3).toBe(true);
    expect(end.helps[0].double).toBe(false);

    // final: secret bets, one team after the other
    await shot(page, '10-bets');
    for (let i = 0; i < 2; i++) {
        const g = await game(page);
        if (g.final.locked[0] && g.final.locked[1]) break;
        await page.fill('#betInput', '100');
        await page.click('#lockBet');
    }
    await page.click('#revealBets');
    await waitPhase(page, 'final_reveal');
    await page.waitForTimeout(1500);
    await shot(page, '11-reveal');
    await page.click('#finalAuction');
    await waitPhase(page, 'final_auction');
    await page.click('[data-bid="3"]');
    await page.click('[data-act="withdraw"]');
    await waitPhase(page, 'final_challenge');
    await page.click('#startTimer');
    await page.click('#judgeYes');
    await waitPhase(page, 'final_result');
    await page.click('#nextBtn');
    await waitPhase(page, 'finale');
    await expect(page.locator('#winner')).toBeVisible();
    await expect(page.locator('.fact')).toHaveCount(4);
    await shot(page, '12-finale');

    await page.click('#rematch');
    await waitPhase(page, 'draft');
    expect((await game(page)).round).toBe(2);

    expect(errors).toEqual([]);
});

test('multi-device: host + two phones play a quick game', async ({ browser }) => {
    const errors = [];
    const hostCtx = await browser.newContext({ viewport: { width: 1600, height: 900 } });
    const host = await hostCtx.newPage();
    watch(host, errors, 'host');
    await setup(host, 'quick', 'multi');
    const code = (await host.locator('#roomCode').textContent()).trim();
    expect(code).toMatch(/^[A-HJ-NP-Z2-9]{6}$/);

    const phoneCtx = [await browser.newContext({ viewport: { width: 390, height: 780 }, hasTouch: true }), await browser.newContext({ viewport: { width: 390, height: 780 }, hasTouch: true })];
    const phones = [];
    for (const i of [0, 1]) {
        const p = await phoneCtx[i].newPage();
        watch(p, errors, 'phone' + (i + 1));
        await p.goto(`/qaddaha/join/${code}?team=${i + 1}`);
        phones.push(p);
    }
    await expect(host.locator('[data-seat="0"]')).toHaveText(/متصل/);
    await expect(host.locator('[data-seat="1"]')).toHaveText(/متصل/);
    await shot(host, '20-lobby');

    // a third device cannot take a seat that is already held
    const intruder = await (await browser.newContext()).newPage();
    await intruder.goto(`/qaddaha/join/${code}?team=1`);
    await expect(intruder.locator('.ph-wait p')).toHaveText(/جوال تاني/);
    await intruder.close();

    await host.click('#lobbyStart');
    await waitPhase(host, 'draft');

    // draft from the phones
    for (let i = 0; i < 4; i++) {
        const g = await game(host);
        const t = g.draft.order[g.draft.idx];
        const btn = phones[t].locator('.ph-btn.cat:not([disabled])').first();
        await expect(btn).toBeVisible();
        if (i === 0) await shot(phones[t], '21-phone-draft');
        await btn.click();
        await expect.poll(async () => (await game(host)).draft.idx, { timeout: 12_000 }).toBe(i + 1);
    }
    await waitPhase(host, 'review');
    await host.click('#startBoard');
    await waitPhase(host, 'board');

    let k = 0;
    let rejoined = false;
    let hostReloaded = false;
    while ((await phase(host)) === 'board') {
        const g = await game(host);
        const t = g.turn;
        const o = 1 - t;
        const cell = g.cells.find((c) => !c.used && c.team === t).id;
        await phones[t].locator(`#ph-${cell}`).click();
        await waitPhase(host, 'auction');
        await expect(phones[t].locator('#phRaise')).toBeEnabled();
        if (k === 0) await shot(phones[t], '22-phone-auction');
        await phones[t].click('#phRaise');                                   // opens at 3
        await expect.poll(async () => (await game(host)).current.toAct).toBe(o);

        if (k === 1) {
            await phones[o].locator('#phJump2').click();                    // jump to 5
            await expect.poll(async () => (await game(host)).current.toAct).toBe(t);
            await phones[t].locator('#phBluff').click();                     // «ورّني!» from the phone
            await waitPhase(host, 'challenge');
            expect((await game(host)).current.bluff).toBe(t);
            await host.click('#judgeNo');
        } else if (k === 2) {
            await phones[o].locator('#phWithdraw').click();
            await waitPhase(host, 'challenge');
            await expect(phones[o].locator('#ph-steal')).toBeEnabled();
            await phones[o].locator('#ph-steal').click();                    // steal from the phone
            await expect.poll(async () => (await game(host)).current.challenger).toBe(o);
            await shot(host, '23-host-stolen');
            await host.click('#judgeYes');
        } else {
            await phones[o].locator('#phWithdraw').click();
            await waitPhase(host, 'challenge');
            await host.click('#startTimer');
            await host.click(t === 0 ? '#judgeYes' : '#judgeNo');
        }
        await waitPhase(host, 'result');
        await host.click('#nextBtn');

        if (k === 3 && !rejoined) {
            // a leader closes the phone and opens the link again: same seat, same game
            rejoined = true;
            await phones[0].close();
            phones[0] = await phoneCtx[0].newPage();
            watch(phones[0], errors, 'phone1b');
            await phones[0].goto(`/qaddaha/join/${code}?team=1`);
            await expect(phones[0].locator('.ph-team')).toHaveText(/الصقور/);
        }
        if (k === 5 && !hostReloaded) {
            hostReloaded = true;
            const before = await game(host);
            await host.reload();
            await expect.poll(async () => (await game(host))?.seq).toBe(before.seq);
        }
        k++;
    }

    // secret bets from the phones
    await waitPhase(host, 'final_bet');
    const g = await game(host);
    const bets = [Math.min(150, g.scores[0]), Math.min(70, g.scores[1])];
    for (const i of [0, 1]) {
        if (g.final.locked[i]) continue;
        await phones[i].fill('#phBet', String(bets[i]));
        await phones[i].click('#phLockBet');
    }
    await expect.poll(async () => (await game(host)).final.locked.every(Boolean)).toBe(true);
    await shot(host, '24-host-bets-locked');
    // nobody can see a bet before the reveal: not the big screen, not the other phone
    const pub = await phones[1].evaluate(async (c) => (await (await fetch(`/qaddaha/api/rooms/${c}?since=-1&device=x`)).json()).public, code);
    expect(pub.final.bets).toEqual([null, null]);
    if (bets[0] > 0) await expect(host.locator('main')).not.toContainText(String(bets[0]));

    await host.click('#revealBets');
    await waitPhase(host, 'final_reveal');
    await page_wait(host);
    await shot(host, '25-host-reveal');
    await host.click('#finalAuction');
    await waitPhase(host, 'final_auction');
    const fa = await game(host);
    await phones[fa.current.toAct].click('#phRaise');
    await expect.poll(async () => (await game(host)).current.toAct).toBe(1 - fa.current.toAct);
    await phones[1 - fa.current.toAct].click('#phWithdraw');
    await waitPhase(host, 'final_challenge');
    await host.click('#startTimer');
    await host.click('#judgeYes');
    await waitPhase(host, 'final_result');
    await host.click('#nextBtn');
    await waitPhase(host, 'finale');
    await expect(phones[0].locator('.ph-wait p')).toHaveText(/فزتوا|فاز|تعادل/);
    await shot(host, '26-host-finale');
    await shot(phones[0], '27-phone-finale');

    expect(errors).toEqual([]);
});

const page_wait = (p) => p.waitForTimeout(1500);
