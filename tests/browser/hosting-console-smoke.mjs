import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import fs from 'node:fs';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { chromium, expect } from '@playwright/test';

// This runner owns its loopback server and isolated runtime; never target a hosted console.
const database = process.env.E2E_DB_DATABASE ?? '';
assert.match(database, /^bank_sampah_test(?:_[a-z0-9]+)*$/);
assert.ok(process.env.E2E_PHP_BINARY, 'Set E2E_PHP_BINARY to a local PHP executable.');
const root = path.resolve(import.meta.dirname, '../..');
const runtime = fs.mkdtempSync(path.join(os.tmpdir(), 'bank-sampah-console-'));
for (const directory of ['app/private', 'app/media', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'bootstrap']) {
    fs.mkdirSync(path.join(runtime, directory), { recursive: true });
}
const token = randomBytes(32).toString('hex');
const env = {
    ...process.env,
    APP_ENV: 'production', APP_DEBUG: 'false', APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    APP_MAINTENANCE_DRIVER: 'file', LARAVEL_STORAGE_PATH: runtime,
    DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: '3306', DB_DATABASE: database,
    DB_USERNAME: 'root', DB_PASSWORD: '', DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'array',
    QUEUE_CONNECTION: 'sync', DEPLOYMENT_LAYOUT: 'standard',
    DEPLOY_CONSOLE_TOKEN: token, DEPLOY_CONSOLE_ALLOWED_IPS: '127.0.0.1',
    HOSTING_DEMO_INSTALL_ENABLED: 'true', HOSTING_DEMO_DATABASE: database,
};
for (const cache of ['CONFIG', 'ROUTES', 'SERVICES', 'PACKAGES', 'EVENTS']) {
    // Laravel treats Windows drive prefixes as relative unless configured otherwise.
    env[`APP_${cache}_CACHE`] = path.relative(root, path.join(runtime, 'bootstrap', `${cache.toLowerCase()}.php`)).replaceAll('\\', '/');
}
const socket = net.createServer();
await new Promise((resolve) => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise((resolve) => socket.close(resolve));
const baseUrl = `http://127.0.0.1:${port}`;
const server = spawn(process.env.E2E_PHP_BINARY, ['-S', `127.0.0.1:${port}`, '-t', 'public'], { cwd: root, env, stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true });
let serverErrors = '';
server.stderr.on('data', (chunk) => { serverErrors = (serverErrors + chunk.toString()).slice(-4000); });
server.stdout.on('data', () => {});
let browser;
try {
    let ready = false;
    for (let attempt = 0; attempt < 40; attempt += 1) {
        if (server.exitCode !== null) throw new Error('Isolated server exited before startup.');
        try { ready = (await fetch(`${baseUrl}/deploy.php`)).ok; } catch { /* Startup only. */ }
        if (ready) break;
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
    assert.ok(ready, 'Isolated console did not start.');
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`${baseUrl}/deploy.php`);
    await expect(page.locator('.action-card:visible')).toHaveCount(4);
    await expect(page.locator('details')).not.toHaveAttribute('open', '');
    for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, 'Horizontal overflow.');
    }
    await page.locator('input[value="hosting-demo-reset"]').check();
    await expect(page.locator('#target_database')).toBeVisible();
    await expect(page.locator('[name="backup_confirmed"]')).toHaveAttribute('required', '');
    await page.locator('input[value="deployment-readiness"]').check();
    await expect(page.locator('#target_database')).toBeHidden();
    const submit = async (expectedStatus) => {
        const response = page.waitForResponse((item) => item.request().method() === 'POST' && item.url().endsWith('/deploy.php'));
        await page.getByRole('button', { name: 'Jalankan aksi' }).click();
        assert.equal((await response).status(), expectedStatus);
    };
    await page.locator('#token').fill('invalid-token');
    await submit(403);
    await expect(page.locator('.notice')).toContainText('Token deploy tidak valid');
    await page.locator('#token').fill(token);
    await page.locator('[name="csrf_token"]').evaluate((input) => { input.value = 'invalid-csrf'; });
    await submit(419);
    await page.goto(`${baseUrl}/deploy.php`);
    await page.locator('#token').fill(token);
    await submit(200);
    await expect(page.locator('.result')).toHaveClass(/success/);
    await expect(page.locator('.result')).not.toContainText('[FAIL]');
    await page.locator('#token').fill(token);
    await page.locator('details summary').click();
    await page.locator('input[value="hosting-demo-preview"]').check();
    await page.locator('#target_database').fill(database);
    await submit(200);
    if (await page.locator('.result.error').count()) {
        throw new Error(await page.locator('.result pre').innerText());
    }
    await expect(page.locator('.result')).toHaveClass(/success/);
    await expect(page.locator('.result')).toContainText('Pratinjau saja');
    assert.equal(errors.length, 0, errors.join('\n'));
    assert.equal(fs.existsSync(path.join(runtime, 'framework', 'down')), false, 'Preview enabled maintenance.');
    console.log(JSON.stringify({ result: 'passed', checks: ['four-primary-actions', 'advanced-collapsed', 'desktop-mobile-no-overflow', 'conditional-reset-inputs', 'invalid-token-403', 'invalid-csrf-419', 'runtime-readiness', 'authenticated-dry-run', 'preview-no-maintenance', 'no-js-errors'] }));
} catch (error) {
    // Do not print HTML/session tokens, database rows or configuration.
    throw new Error(`${error.message}\nIsolated PHP fatal: ${serverErrors.includes('Fatal error') ? 'yes' : 'no'}`);
} finally {
    if (browser) await browser.close();
    server.kill();
}
