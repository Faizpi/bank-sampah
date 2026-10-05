import { chromium, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { verifyIsolatedServer } from './support/isolated-environment.mjs';

const { baseUrl } = await verifyIsolatedServer(process.env);
const output = path.resolve(process.env.E2E_ARTIFACT_DIR ?? 'artifacts/workflow-audit-2026-10-05');
fs.mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const errors = [];
const results = [];
const pages = {};
const fixture = path.resolve('public/icons/icon-192x192.png');

async function login(role) {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    pages[role] = page;
    page.setDefaultTimeout(10000);
    page.setDefaultNavigationTimeout(20000);
    page.on('pageerror', error => errors.push(`${role}: ${error.message}`));
    page.on('response', response => { if (response.status() >= 500) errors.push(`${role}: HTTP ${response.status()} ${response.url()}`); });
    await page.goto(`${baseUrl}${['admin', 'superadmin'].includes(role) ? '/backoffice/login' : '/login'}`);
    await page.locator('input[name=username], input[id="form.username"]').fill(`audit.${role}`);
    await page.locator('input[type=password]').fill('AuditOnly#2026');
    await page.getByRole('button', { name: /^Masuk(?: ke Akun)?$/i }).click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    await page.waitForLoadState('networkidle');
    pages[role] = page;
    return page;
}

async function capture(page, name) {
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: path.join(output, `${name}.png`), fullPage: true });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
    expect(overflow, `${name}: horizontal overflow`).toBe(false);
}

async function run(name, callback) {
    try {
        await callback();
        results.push({ name, status: 'passed' });
        console.log(`PASS ${name}`);
    } catch (error) {
        results.push({ name, status: 'failed', message: error.message });
        console.log(`FAIL ${name}: ${error.message}`);
    }
}

async function upload(page, id) {
    await page.locator(`#${id}`).setInputFiles(fixture);
    await expect(page.locator(`#${id}-status`)).toContainText(/siap|berhasil|terpilih|tersimpan/i, { timeout: 30000 });
}

try {
    for (const role of ['warga', 'petugas', 'bendahara', 'admin', 'superadmin']) {
        await run(`login and responsive dashboard: ${role}`, async () => {
            const page = await login(role);
            await capture(page, `${role}-mobile`);
            await page.setViewportSize({ width: 1280, height: 900 });
            await capture(page, `${role}-desktop`);
            await page.setViewportSize({ width: 390, height: 844 });
        });
    }

    await run('citizen pickup address and single area are prefilled', async () => {
        const page = pages.warga;
        await page.goto(`${baseUrl}/warga/penjemputan/ajukan`);
        await expect(page.locator('[name=address]')).toHaveValue('Alamat sintetis untuk uji antarmuka');
        await expect(page.getByText('Area pelayanan: Area uji')).toBeVisible();
        await capture(page, 'citizen-prefilled-pickup');
    });

    await run('admin approves and assigns cash payment in one dialog', async () => {
        const page = pages.admin;
        await page.goto(`${baseUrl}/backoffice/withdrawals/models/withdrawal-requests`);
        await page.getByRole('button', { name: 'Setujui dan tetapkan bendahara', exact: true }).click();
        const dialog = page.getByRole('alertdialog').or(page.getByRole('dialog'));
        await dialog.locator('select').first().selectOption({ label: 'Akun Uji Bendahara' });
        await capture(page, 'admin-combined-withdrawal');
        await dialog.getByRole('button', { name: 'Setujui dan tetapkan bendahara', exact: true }).click();
        await expect(dialog).toBeHidden();
        await expect(page.locator('tbody').getByText('Siap diambil', { exact: true }).first()).toBeVisible();
    });

    await run('admin accepts and schedules pickup in one dialog', async () => {
        const page = pages.admin;
        await page.goto(`${baseUrl}/backoffice/pickups/models/pickup-requests`);
        await page.getByRole('button', { name: 'Terima dan jadwalkan', exact: true }).click();
        const dialog = page.getByRole('alertdialog').or(page.getByRole('dialog'));
        await dialog.getByLabel('Petugas penjemputan', { exact: false }).selectOption({ label: 'Akun Uji Petugas' });
        await capture(page, 'admin-combined-pickup');
        await dialog.getByRole('button', { name: /Kirim|Simpan|Terima dan jadwalkan/ }).last().click();
        await expect(dialog).toBeHidden();
        await expect(page.locator('tbody').getByText('Dijadwalkan', { exact: true }).first()).toBeVisible();
    });

    await run('officer finalizes a deposit with one confirmation', async () => {
        const page = pages.petugas;
        await page.goto(`${baseUrl}/petugas/setoran/1?draftId=1`);
        await upload(page, 'deposit-evidence');
        await capture(page, 'officer-deposit-single-confirmation');
        await page.getByRole('button', { name: 'Data sudah benar - catat setoran', exact: true }).click();
        await expect(page.getByText('Setoran berhasil dicatat', { exact: true })).toBeVisible();
    });

    await run('treasurer records cash payment with one confirmation', async () => {
        const page = pages.bendahara;
        await page.goto(`${baseUrl}/bendahara/pencairan`);
        await page.getByRole('button', { name: 'Proses Pembayaran', exact: true }).click();
        await page.locator('select[name=recipientVerification]').selectOption('nomor_nasabah');
        await page.locator('input[name=recipientReference]').fill('CST-00000001');
        await upload(page, 'payment-proof');
        await capture(page, 'treasurer-single-confirmation');
        await page.getByRole('button', { name: 'Uang tunai sudah diserahkan - catat pembayaran', exact: true }).click();
        await expect(page.getByText(/Pembayaran (berhasil|tercatat)/).first()).toBeVisible();
    });

    await run('grocery preparation needs only the ready action', async () => {
        const page = pages.admin;
        await page.goto(`${baseUrl}/backoffice/groceries/models/grocery-redemptions`);
        await page.getByRole('button', { name: 'Setujui', exact: true }).click();
        const dialog = page.getByRole('alertdialog').or(page.getByRole('dialog'));
        await dialog.locator('textarea').first().fill('Paket tersedia untuk pengujian sintetis.');
        await dialog.getByRole('button', { name: 'Setujui penukaran', exact: true }).click();
        await expect(dialog).toBeHidden();
        await pages.petugas.goto(`${baseUrl}/petugas/sembako`);
        await expect(pages.petugas.getByRole('button', { name: 'Mulai siapkan', exact: true })).toHaveCount(0);
        await pages.petugas.getByRole('button', { name: 'Paket sudah siap - beri tahu warga', exact: true }).click();
        await expect(pages.petugas.getByRole('button', { name: 'Proses serah-terima', exact: true })).toBeVisible();
        await capture(pages.petugas, 'officer-grocery-ready');
    });

    await run('superadmin user context combines account and staff assignments', async () => {
        const page = pages.superadmin;
        await page.goto(`${baseUrl}/backoffice/identity/models/users`);
        const row = page.locator('tr').filter({ hasText: 'Akun Uji Petugas' });
        await row.getByRole('button', { name: 'Kelola akun dan tugas', exact: true }).click();
        await expect(page.getByRole('dialog').getByText('Area dan masa tugas', { exact: true })).toBeVisible();
        await capture(page, 'superadmin-unified-user');
    });
} finally {
    fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ results, errors }, null, 2));
    await browser.close();
}
if (errors.length || results.some(result => result.status === 'failed')) process.exitCode = 1;
