import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import fs from 'node:fs';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { chromium, expect } from '@playwright/test';
import { verifyIsolatedServer } from './support/isolated-environment.mjs';

const root = path.resolve(import.meta.dirname, '../..');
const database = 'bank_sampah_test_ui_20261021';
assert.ok(process.env.E2E_PHP_BINARY, 'E2E_PHP_BINARY is required.');
const runtime = path.join(os.tmpdir(), database);
const output = path.join(root, 'artifacts/hosting-ui-2026-10-21');
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
function php(...args) {
    const result = spawnSync(process.env.E2E_PHP_BINARY, args, { cwd: root, env, encoding: 'utf8', windowsHide: true });
    assert.equal(result.status, 0, `Isolated PHP command failed: ${args[0]}\n${result.stdout}\n${result.stderr}`);
}
php('tests/browser/seed-access-regression.php');
php('artisan', 'route:cache');
const server = spawn(process.env.E2E_PHP_BINARY, ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/browser/router.php'], { cwd: root, env, stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true });
server.stdout.on('data', () => {});
server.stderr.on('data', () => {});
let browser;
const checks = [];
try {
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt += 1) {
        if (server.exitCode !== null) throw new Error('Isolated server exited.');
        try { await verifyIsolatedServer(env); ready = true; break; } catch { /* Startup only. */ }
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
    assert.ok(ready, 'Could not verify the owned loopback test server.');
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(baseUrl);
    await page.locator('.public-hero-photo img').evaluateAll((images) => images.forEach((image) => { image.loading = 'eager'; }));
    await page.waitForFunction(() => {
        const photos = [...document.querySelectorAll('.public-hero-photo img')];
        return photos.length === 7 && photos.every((photo) => photo.complete && photo.naturalWidth > 0);
    });
    await page.screenshot({ path: path.join(output, 'hero-desktop.png') });
    checks.push('seven-hero-jpegs-loaded');
    for (const width of [360, 390, 768, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(`${baseUrl}/login`);
        const admin = page.getByRole('link', { name: 'Akses Panel Admin', exact: true });
        const submit = page.getByRole('button', { name: 'Masuk ke Akun', exact: true });
        await expect(admin).toHaveCSS('background-color', 'rgb(255, 255, 255)');
        await expect(admin).toHaveCSS('color', 'rgb(24, 87, 70)');
        await expect(admin).toHaveCSS('border-top-color', 'rgb(30, 106, 86)');
        await expect(admin).toHaveCSS('border-top-width', '2px');
        const adminBox = await admin.boundingBox();
        const submitBox = await submit.boundingBox();
        assert.ok(adminBox.height >= 48 && Math.abs(adminBox.width - submitBox.width) <= 1);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false);
        await admin.focus();
        await expect(admin).toBeFocused();
        await page.screenshot({ path: path.join(output, `login-${width}.png`), fullPage: true });
        checks.push(`outlined-admin-button-${width}`);
    }
    await page.getByRole('link', { name: 'Akses Panel Admin', exact: true }).click();
    await page.locator('input[id="form.username"]').fill('admin');
    await page.locator('input[type="password"]').fill('BrowserOnly#2026');
    await page.getByRole('button', { name: /^Masuk(?: ke Akun)?$/i }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    for (const legacy of ['roles', 'permissions']) {
        const response = await page.request.get(`${baseUrl}/backoffice/identity/models/${legacy}`, { maxRedirects: 0 });
        assert.equal(response.status(), 302);
        assert.equal(new URL(response.headers().location).pathname, '/backoffice/identity/models/users');
        await page.goto(`${baseUrl}/backoffice/identity/models/${legacy}`);
        await expect(page).toHaveURL(`${baseUrl}/backoffice/identity/models/users`);
        await expect(page.locator('.fi-sidebar a[href$="/roles"], .fi-sidebar a[href$="/permissions"]')).toHaveCount(0);
        checks.push(`cached-route-${legacy}-redirects-to-users`);
    }
    await page.screenshot({ path: path.join(output, 'admin-users.png') });
    const hubs = [
        { path: '/backoffice/waste-catalog', label: 'Bagian katalog sampah', count: 5 },
        { path: '/backoffice/regions', label: 'Bagian wilayah', count: 4 },
        { path: '/backoffice/directory', label: 'Bagian direktori', count: 3 },
    ];
    for (const width of [360, 390, 768, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        for (const hub of hubs) {
            await page.goto(`${baseUrl}${hub.path}`);
            const navigation = page.getByRole('navigation', { name: hub.label, exact: true });
            const links = navigation.locator('a');
            await expect(links).toHaveCount(hub.count);
            const bounds = await navigation.boundingBox();
            for (const link of await links.all()) {
                const box = await link.boundingBox();
                assert.ok(box.height >= 56, 'Section button must be at least 56 px high.');
                assert.ok(box.x >= bounds.x - 1 && box.x + box.width <= bounds.x + bounds.width + 1, 'Section button outside navigation.');
                await expect(link).toHaveCSS('font-size', '16px');
                await expect(link).toHaveCSS('border-top-width', '2px');
            }
            await links.first().focus();
            await expect(links.first()).toBeFocused();
            await page.keyboard.press('Tab');
            await expect(links.nth(1)).toBeFocused();
            assert.equal(await navigation.evaluate((element) => element.scrollWidth > element.clientWidth + 1), false);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false);
            if (width === 390 || width === 1280) {
                await page.screenshot({ path: path.join(output, `${hub.path.split('/').at(-1)}-${width}.png`), fullPage: true });
            }
        }
        checks.push(`large-section-buttons-${width}`);
    }
    await page.goto(`${baseUrl}/backoffice/waste-catalog`);
    for (const label of ['Kategori', 'Jenis', 'Kondisi', 'Harga', 'Satuan']) {
        const button = page.getByRole('navigation', { name: 'Bagian katalog sampah' }).getByRole('link', { name: label, exact: true });
        const target = await button.getAttribute('href');
        const response = await page.request.get(target);
        assert.equal(response.status(), 200, `Catalog destination failed: ${label}`);
    }
    checks.push('all-catalog-button-destinations-200');
    assert.deepEqual(errors, []);
    checks.push('no-js-errors');
    console.log(JSON.stringify({ result: 'passed', checks, screenshotDirectory: output }));
} finally {
    if (browser) await browser.close();
    server.kill();
}
