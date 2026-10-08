import { chromium, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { verifyIsolatedServer } from './support/isolated-environment.mjs';

const { baseUrl } = await verifyIsolatedServer(process.env);
const fixture = JSON.parse(fs.readFileSync(process.env.E2E_UI_FIXTURE_FILE, 'utf8').replace(/^\uFEFF/, ''));
const output = path.resolve(process.env.E2E_ARTIFACT_DIR);
fs.mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const results = [];
const errors = [];
const pages = {};
async function run(name, action) {
  try { await action(); results.push({ name, status: 'passed' }); console.log('PASS ' + name); }
  catch (error) { results.push({ name, status: 'failed', message: error.message }); console.log('FAIL ' + name + ': ' + error.message); }
}
async function capture(page, name) {
  await page.waitForLoadState('networkidle');
  expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), name + ' overflow').toBe(false);
  await page.screenshot({ path: path.join(output, name + '.png'), fullPage: true });
}
try {
  for (const role of ['warga', 'petugas', 'bendahara', 'admin']) {
    await run('responsive role screens ' + role, async () => {
      const page = await (await browser.newContext()).newPage(); pages[role] = page;
      page.on('pageerror', error => errors.push(role + ': ' + error.message));
      page.on('response', response => { if (response.status() >= 500) errors.push(role + ': HTTP ' + response.status() + ' ' + response.url()); });
      await page.goto(baseUrl + (role === 'admin' ? '/backoffice/login' : '/login'));
      await page.locator('input[name=username], input[id="form.username"]').fill(role);
      await page.locator('input[type=password]').fill('AuditOnly#2026');
      await page.getByRole('button', { name: /^Masuk(?: ke Akun)?$/i }).click();
      await page.waitForURL(url => !url.pathname.endsWith('/login'));
      for (const width of [360, 390, 768, 1280]) { await page.setViewportSize({ width, height: 900 }); await capture(page, role + '-' + width); }
      await page.setViewportSize({ width: 390, height: 844 });
    });
  }
  await run('all services and one-screen pickup', async () => {
    const page = pages.warga;
    await page.getByRole('link', { name: 'Layanan', exact: true }).click();
    for (const label of ['Minta penjemputan', 'Cairkan saldo tunai', 'Tukar saldo dengan sembako', 'Hitung perkiraan nilai']) await expect(page.getByRole('link', { name: new RegExp(label) })).toBeVisible();
    await capture(page, 'citizen-services');
    await page.getByRole('link', { name: /^Minta penjemputan/ }).click();
    await expect(page.getByRole('button', { name: 'Kirim pengajuan', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /^Lanjut ke/ })).toHaveCount(0);
    await capture(page, 'citizen-pickup-one-form');
  });
  await run('officer mobile context and identity confirmation', async () => {
    const page = pages.petugas;
    await page.goto(baseUrl + '/petugas/pindai');
    await page.getByLabel('Metode setoran', { exact: true }).selectOption(String(fixture.activeMobileId));
    await page.getByLabel('Nomor atau nama warga', { exact: true }).fill('Siti');
    await page.getByRole('button', { name: 'Cari Nasabah', exact: true }).click();
    await page.getByRole('button', { name: 'Nama sudah sesuai — catat setoran', exact: true }).click();
    await page.waitForURL(/\/petugas\/setoran\/\d+/);
    await expect(page.getByText('Titik aktif audit UI', { exact: false })).toBeVisible();
    await capture(page, 'officer-mobile-deposit-context');
  });
  await run('admin mobile edit keeps saved fields', async () => {
    const page = pages.admin;
    await page.goto(baseUrl + '/backoffice/mobile-services/models/mobile-services');
    await page.getByRole('row').filter({ hasText: 'Titik draft audit UI' }).getByRole('button', { name: 'Ubah', exact: true }).click();
    await expect(page.getByLabel('Titik layanan', { exact: false })).toHaveValue('Titik draft audit UI');
    await expect(page.getByLabel('Catatan', { exact: true })).toHaveValue('Catatan jadwal tersimpan');
    await capture(page, 'admin-mobile-edit');
    await page.getByRole('button', { name: 'Batal', exact: true }).click();
  });
  await run('admin target edit keeps its scope', async () => {
    const page = pages.admin;
    await page.goto(baseUrl + '/backoffice/programs/models/collection-targets');
    await page.getByRole('row').filter({ hasText: 'Target cakupan audit UI' }).getByRole('button', { name: 'Ubah', exact: true }).click();
    await expect(page.getByRole('group', { name: 'Wilayah dan jenis yang dihitung', exact: true }).getByRole('combobox')).toHaveCount(3);
    await expect(page.getByText(fixture.rtLabel, { exact: false }).first()).toBeVisible();
    await capture(page, 'admin-target-scope');
    await page.getByRole('button', { name: 'Batal', exact: true }).click();
  });
  await run('single Admin reconciliation and correct shared navigation', async () => {
    const page = pages.admin;
    await page.goto(baseUrl + '/backoffice/reconciliation');
    if (!Number.isSafeInteger(fixture.expectedCashTotal) || fixture.expectedCashTotal < 0) throw new Error('Fixture must declare the paid cash total for the business date.');
    await page.locator('[wire\\:model="cashTotal"]').fill(new Intl.NumberFormat('id-ID').format(fixture.expectedCashTotal));
    await page.getByRole('button', { name: 'Tampilkan pembanding', exact: true }).click();
    await page.getByRole('button', { name: 'Catat hasil pemeriksaan', exact: true }).click();
    await expect(page.getByText('Hasil pemeriksaan tercatat.', { exact: false })).toBeVisible();
    await capture(page, 'admin-one-person-reconciliation');
    await page.goto(baseUrl + '/profil/kata-sandi');
    await expect(page.getByRole('link', { name: 'Panel Admin', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Tugas', exact: true })).toHaveCount(0);
    await capture(page, 'admin-profile-navigation');
  });
} finally {
  fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ results, errors }, null, 2));
  await browser.close();
}
if (errors.length || results.some(result => result.status !== 'passed')) process.exitCode = 1;
console.log(JSON.stringify({ scenarios: results.length, passed: results.filter(result => result.status === 'passed').length, errors }, null, 2));
