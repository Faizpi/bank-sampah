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
const database = 'bank_sampah_test_ui_payouts_20261110';
const output = path.join(root, 'artifacts/payouts-2026-11-10');
const runtime = path.join(os.tmpdir(), database);
const php = process.env.E2E_PHP_BINARY;
assert.ok(php, 'Set E2E_PHP_BINARY.');
fs.mkdirSync(output, { recursive: true });
const socket = net.createServer();
await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const env = {
    ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    APP_URL: `http://127.0.0.1:${port}`, APP_MAINTENANCE_DRIVER: 'file', LARAVEL_STORAGE_PATH: runtime,
    SESSION_DRIVER: 'file', SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'file', QUEUE_CONNECTION: 'sync',
    DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: process.env.E2E_DB_PORT ?? '3306',
    DB_DATABASE: database, DB_USERNAME: 'root', DB_PASSWORD: '', DB_URL: '', TELEGRAM_ENABLED: 'false',
    PAYOUT_SANDBOX_ENABLED: 'true', PAYOUT_SANDBOX_DATABASE: database,
    XENDIT_PAYOUT_SECRET_KEY: 'xnd_development_FAKE_KEY_FOR_TESTS_ONLY', XENDIT_PAYOUT_WEBHOOK_TOKEN: 'w'.repeat(40),
    E2E_DB_DATABASE: database, E2E_BASE_URL: `http://127.0.0.1:${port}`, E2E_ENVIRONMENT_TOKEN: randomBytes(32).toString('hex'),
};
for (const name of ['BCA', 'MANDIRI', 'BRI', 'BNI', 'DANA', 'GOPAY']) env[`XENDIT_PAYOUT_ROUTING_${name}`] = `TEST_${name}`;
const fixture = (mode) => {
    const result = spawnSync(php, ['tests/browser/payout-fixture.php', mode], { cwd: root, env, encoding: 'utf8', windowsHide: true });
    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
};
fixture('seed');
const server = spawn(php, ['-d', 'opcache.enable_cli=0', '-d', 'opcache.enable=0', '-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/browser/router.php'], { cwd: root, env, stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true });
let serverLog = '';
server.stdout.on('data', chunk => { serverLog += chunk; });
server.stderr.on('data', chunk => { serverLog += chunk; });
let browser;
const checks = [];
const errors = [];
try {
    let ready = false;
    for (let i = 0; i < 60; i++) {
        try { await verifyIsolatedServer(env); ready = true; break; } catch { await new Promise(resolve => setTimeout(resolve, 250)); }
    }
    assert.ok(ready, `Isolated server unavailable: ${serverLog}`);
    browser = await chromium.launch({ headless: true });
    async function login(role) {
        const context = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'id-ID', timezoneId: 'Asia/Jakarta' });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        page.on('response', r => { if (r.status() >= 500) errors.push(`HTTP ${r.status()} ${r.url()}`); });
        await page.goto(`${env.APP_URL}${role === 'admin' ? '/backoffice/login' : '/login'}`);
        await page.locator('input[name=username], input[id="form.username"]').fill(role);
        await page.locator('input[type=password]').fill('BrowserOnly#2026');
        await page.getByRole('button', { name: /^Masuk(?: ke Akun)?$/ }).click();
        await page.waitForURL(url => !url.pathname.endsWith('/login'));
        return page;
    }
    async function capture(page, name) {
        await page.waitForLoadState('networkidle');
        await page.locator('#nprogress').waitFor({ state: 'detached' });
        for (const width of [360, 390, 768, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            await page.evaluate(() => window.scrollTo(0, 0));
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, `Overflow ${name}/${width}`);
            await page.screenshot({ path: path.join(output, `${name}-${width}.png`), fullPage: true });
        }
        const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
        fs.writeFileSync(path.join(output, `${name}-axe.json`), JSON.stringify(axe.violations, null, 2));
        assert.deepEqual(axe.violations.filter(v => ['serious', 'critical'].includes(v.impact)).map(v => v.id), [], name);
        await page.setViewportSize({ width: 390, height: 844 });
        checks.push(name);
    }
    const citizen = await login('warga');
    await citizen.goto(`${env.APP_URL}/warga/pencairan-digital/ajukan`);
    await expect(citizen.getByRole('radio')).toHaveCount(6);
    await expect(citizen.locator('nav[aria-label="Navigasi warga"] [aria-current="page"]')).toContainText('Layanan');
    await capture(citizen, 'form');
    await citizen.getByRole('button', { name: 'Periksa pengajuan', exact: true }).click();
    await expect(citizen.getByRole('alert')).toBeVisible();
    await capture(citizen, 'validation');
    await citizen.getByRole('radio', { name: 'DANA', exact: true }).check();
    await citizen.locator('[name=recipientName]').fill('Siti Aminah');
    await citizen.locator('[name=accountNumber]').fill('081234567890');
    await citizen.locator('[name=amount]').fill('20000');
    await citizen.getByRole('button', { name: 'Periksa pengajuan', exact: true }).click();
    await expect(citizen.getByRole('button', { name: 'Konfirmasi pencairan simulasi', exact: true })).toBeVisible();
    await capture(citizen, 'review');
    await citizen.getByRole('button', { name: 'Periksa lagi', exact: true }).click();
    await expect(citizen.locator('[name=accountNumber]')).toHaveValue('081234567890');
    await citizen.getByRole('button', { name: 'Periksa pengajuan', exact: true }).click();
    await citizen.getByRole('button', { name: 'Konfirmasi pencairan simulasi', exact: true }).click();
    await citizen.waitForURL(/\/warga\/pencairan-digital\/\d+$/);
    await capture(citizen, 'queued');
    fixture('work');
    await citizen.reload();
    await expect(citizen.getByRole('heading', { name: 'Simulasi berhasil', exact: true })).toBeVisible();
    await capture(citizen, 'settled');
    await citizen.getByRole('link', { name: 'Riwayat pencairan', exact: true }).click();
    await expect(citizen.getByRole('heading', { name: 'Riwayat Pencairan', exact: true })).toBeVisible();
    await expect(citizen.getByRole('button', { name: 'Digital (simulasi)', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(citizen.getByText('Simulasi berhasil', { exact: true })).toBeVisible();
    await capture(citizen, 'history');
    const treasurer = await login('bendahara');
    await treasurer.goto(`${env.APP_URL}/pengurus/pencairan-digital`);
    await expect(treasurer.getByText('Simulasi berhasil (simulasi)', { exact: true })).toBeVisible();
    await capture(treasurer, 'monitor');
    const admin = await login('admin');
    await admin.goto(`${env.APP_URL}/backoffice/sandbox-payouts`);
    await expect(admin.getByText('Simulasi berhasil', { exact: true })).toBeVisible();
    await capture(admin, 'admin');
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ checks, errors }, null, 2));
} finally {
    fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ checks, errors }, null, 2));
    await browser?.close();
    server.kill();
}
