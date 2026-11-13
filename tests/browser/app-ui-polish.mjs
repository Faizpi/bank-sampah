import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import fs from 'node:fs';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { chromium, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { verifyIsolatedServer } from './support/isolated-environment.mjs';

const root = path.resolve(import.meta.dirname, '../..');
const database = 'bank_sampah_test_ui_20261021'; // Same guarded, disposable fixture as hosting-ui-regression.
const phase = process.env.E2E_UI_BASELINE === '1' ? 'before' : 'after';
const output = path.join(root, 'artifacts/app-ui-2026-10-23', phase);
const runtime = path.join(os.tmpdir(), database);
assert.ok(process.env.E2E_PHP_BINARY, 'E2E_PHP_BINARY is required.');
for (const directory of ['bootstrap', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'app/private', 'app/media']) {
    fs.mkdirSync(path.join(runtime, directory), { recursive: true });
}
fs.mkdirSync(output, { recursive: true });
const socket = net.createServer();
await new Promise((resolve) => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise((resolve) => socket.close(resolve));
const baseUrl = `http://127.0.0.1:${port}`;
const env = {
    ...process.env,
    APP_ENV: 'testing', APP_DEBUG: 'false', APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    APP_URL: baseUrl, APP_DEMO_MODE: 'false', APP_MAINTENANCE_DRIVER: 'file',
    LARAVEL_STORAGE_PATH: runtime, SESSION_DRIVER: 'file', SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'file',
    DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: '3306', DB_DATABASE: database,
    DB_USERNAME: 'root', DB_PASSWORD: '', DB_URL: '', QUEUE_CONNECTION: 'sync',
    E2E_DB_DATABASE: database, E2E_BASE_URL: baseUrl, E2E_ENVIRONMENT_TOKEN: randomBytes(32).toString('hex'),
};
for (const cache of ['CONFIG', 'ROUTES', 'SERVICES', 'PACKAGES', 'EVENTS']) {
    env[`APP_${cache}_CACHE`] = path.relative(root, path.join(runtime, 'bootstrap', `${cache.toLowerCase()}.php`)).replaceAll('\\', '/');
}
const seed = spawnSync(process.env.E2E_PHP_BINARY, [phase === 'before' ? 'tests/browser/seed-access-regression.php' : 'tests/browser/seed-workflow.php'], { cwd: root, env, encoding: 'utf8', windowsHide: true });
assert.equal(seed.status, 0, `Isolated fixture failed: ${seed.stdout}\n${seed.stderr}`);
const fixture = phase === 'after' ? JSON.parse(seed.stdout) : null;
const server = spawn(process.env.E2E_PHP_BINARY, ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/browser/router.php'], { cwd: root, env, stdio: 'ignore', windowsHide: true });
const surfaces = {
    warga: ['/dashboard/warga', '/warga/layanan', '/warga/penjemputan/ajukan', '/warga/pencairan/ajukan', '/warga/sembako/ajukan', '/warga/estimasi', '/profil/kata-sandi'],
    petugas: ['/dashboard/petugas', '/petugas/pindai', '/petugas/sembako', '/petugas/layanan-keliling', '/petugas/riwayat'],
    bendahara: ['/dashboard/bendahara', '/bendahara/pencairan', '/bendahara/laporan'],
};
if (fixture) {
    surfaces.warga.push(`/warga/pencairan/${fixture.withdrawalId}`, `/warga/sembako/${fixture.groceryId}`);
    surfaces.petugas.push(`/petugas/setoran/${fixture.customerId}?draftNumber=${fixture.draftId}`);
}
let browser;
let activePage;
const checks = [];
try {
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt += 1) {
        if (server.exitCode !== null) throw new Error('Owned test server exited.');
        try { await verifyIsolatedServer(env); ready = true; break; } catch { /* Startup only, never changes the target. */ }
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
    assert.ok(ready, 'Could not verify the isolated server.');
    browser = await chromium.launch({ headless: true });
    const errors = [];
    for (const [role, routes] of Object.entries(surfaces)) {
        const context = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'id-ID', timezoneId: 'Asia/Jakarta', ...(phase === 'after' ? { recordVideo: { dir: path.join(output, 'interaction-qa'), size: { width: 390, height: 844 } } } : {}) });
        const page = await context.newPage();
        activePage = page;
        page.on('pageerror', (error) => errors.push(`${role}: ${error.message}`));
        await page.goto(`${baseUrl}/login`);
        await page.locator('input[name=username]').fill(role);
        await page.locator('input[type=password]').fill(phase === 'before' ? 'BrowserOnly#2026' : 'AuditOnly#2026');
        await page.getByRole('button', { name: 'Masuk ke Akun', exact: true }).click();
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));
        for (const width of phase === 'before' ? [390] : [360, 390, 768, 1280]) {
            await page.setViewportSize({ width, height: 844 });
            for (const route of routes) {
                const response = await page.goto(`${baseUrl}${route}`);
                assert.equal(response.status(), 200, `${role} ${route}`);
                await page.waitForLoadState('networkidle');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, `Overflow: ${route}/${width}`);
                if (phase === 'after') await expect(page.locator('h1')).toHaveCount(1);
                if (width === 390 || width === 1280) {
                    const key = new URL(route, baseUrl).pathname.split('/').filter(Boolean).join('-');
                    await page.screenshot({ path: path.join(output, `${role}-${key}-${width}.png`), fullPage: true });
                }
            }
            checks.push(`${role}-${width}-pages-and-overflow`);
        }
        if (role === 'warga' && phase === 'after') {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.goto(`${baseUrl}/warga/layanan`);
            const cards = page.locator('[data-action-card]');
            await expect(cards).toHaveCount(6);
            const card = cards.first();
            await card.focus();
            await expect(card).toBeFocused();
            await card.hover();
            await page.waitForTimeout(250); // Observe the documented 180 ms interaction endpoint.
            assert.notEqual(await card.evaluate((el) => getComputedStyle(el).transform), 'none');
            await page.screenshot({ path: path.join(output, 'service-hover.png') });
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await expect(card).toHaveCSS('transform', 'none');
            await page.emulateMedia({ reducedMotion: 'no-preference' });
            await page.goto(`${baseUrl}/warga/penjemputan/ajukan`);
            await expect(page.locator('textarea[name=address]')).toHaveAttribute('placeholder', /Contoh:/);
            await page.locator('textarea[name=address]').fill('');
            await page.getByRole('button', { name: 'Kirim pengajuan', exact: true }).click();
            await expect(page.locator('#pickup-error-summary')).toBeVisible();
            await page.screenshot({ path: path.join(output, 'pickup-validation.png'), fullPage: true });
            checks.push('service-icons-hover-keyboard-reduced-motion-and-validation');
        }
        if (role !== 'warga' && phase === 'after') {
            await page.setViewportSize({ width: 390, height: 844 });
            const cash = role === 'bendahara';
            await page.goto(`${baseUrl}${cash ? '/bendahara/pencairan' : '/petugas/sembako'}`);
            await page.locator('button').filter({ hasText: cash ? fixture.withdrawalNumber : fixture.groceryNumber }).click();
            await page.getByRole('button', { name: cash ? 'Ambil pembayaran' : 'Ambil sembako', exact: true }).click();
            const complete = page.getByRole('button', { name: cash ? 'Uang tunai sudah diserahkan — catat pembayaran' : 'Paket sudah diserahkan', exact: true });
            await complete.click();
            const dialog = page.getByRole('dialog', { name: cash ? 'Catat pembayaran tunai?' : 'Catat penyerahan sembako?' });
            await expect(dialog).toBeVisible();
            await page.screenshot({ path: path.join(output, `${role}-confirmation.png`) });
            await dialog.getByRole('button', { name: 'Periksa lagi', exact: true }).click();
            await expect(dialog).toBeHidden();
            await expect(complete).toBeFocused();
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.screenshot({ path: path.join(output, `${role}-selected-queue.png`), fullPage: true });
            checks.push(`${role}-claim-confirmation-cancel-and-focus-return`);
        }
        if (phase === 'after') {
            if (role === 'warga') await page.goto(`${baseUrl}/warga/layanan`);
            const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
            assert.deepEqual(audit.violations.map(({ id, nodes }) => ({ id, targets: nodes.map(({ target }) => target) })), [], `${role} accessibility violations`);
            checks.push(`${role}-axe-wcag-aa`);
        }
        await context.close();
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ result: 'passed', phase, checks, screenshotDirectory: output }));
} catch (error) {
    if (activePage && !activePage.isClosed()) {
        await activePage.screenshot({ path: path.join(output, 'failure.png'), fullPage: true }).catch(() => {});
        console.error(`Failed on ${new URL(activePage.url()).pathname}; screenshot: ${path.join(output, 'failure.png')}`);
    }
    throw error;
} finally {
    if (browser) await browser.close();
    server.kill();
}
