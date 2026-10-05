import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { chromium } from '@playwright/test';

const projectDir = process.cwd();
const laravelEnv = loadDotEnv(path.join(projectDir, '.env'));
const baseUrl = configuredValue('E2E_BASE_URL', 'APP_URL', 'http://bank-sampah.test').replace(/\/$/, '');
const pwaBaseUrl = (process.env.E2E_PWA_BASE_URL ?? baseUrl.replace(/^http:/, 'https:')).replace(/\/$/, '');
const database = configuredValue('E2E_DB_DATABASE', 'DB_DATABASE', 'bank_sampah');
const php = process.env.PHP_BIN ?? 'C:\\Users\\Faiz\\AppData\\Local\\Microsoft\\WinGet\\Packages\\PHP.PHP.NTS.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\\php.exe';
const fixture = process.env.E2E_FIXTURE ?? `${projectDir}\\final-qc-home-360-footer.png`;
const devPassword = 'Dev#Sindangheula2026';
const payerLabel = process.env.E2E_PAYER_LABEL ?? 'Dewi Lestari';
const customerId = Number(process.env.E2E_CUSTOMER_ID ?? '1');
const customerNumber = process.env.E2E_CUSTOMER_NUMBER ?? 'CST-00000001';
const dbHost = configuredValue('E2E_DB_HOST', 'DB_HOST', '127.0.0.1');
const dbPort = configuredValue('E2E_DB_PORT', 'DB_PORT', '3306');
const dbUsername = configuredValue('E2E_DB_USERNAME', 'DB_USERNAME', 'root');
const dbPassword = configuredValue('E2E_DB_PASSWORD', 'DB_PASSWORD', '');
const appKey = process.env.E2E_APP_KEY ?? process.env.APP_KEY ?? laravelEnv.APP_KEY;
const artifactDirectory = process.env.E2E_ARTIFACT_DIR
    ?? path.join(projectDir, 'artifacts', 'uat', new Date().toISOString().replaceAll(':', '-').replaceAll('.', '-'));

if (!fs.existsSync(fixture)) {
    throw new Error(`Fixture tidak ditemukan: ${fixture}`);
}

fs.mkdirSync(artifactDirectory, { recursive: true });

const results = [];
const browserErrors = [];
let offlineSimulation = false;

function loadDotEnv(filename) {
    if (!fs.existsSync(filename)) return {};

    return Object.fromEntries(
        fs.readFileSync(filename, 'utf8')
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter((line) => line !== '' && !line.startsWith('#'))
            .map((line) => {
                const separator = line.indexOf('=');
                if (separator < 0) return null;
                const key = line.slice(0, separator).trim();
                const rawValue = line.slice(separator + 1).trim();
                const value = rawValue.replace(/^(["'])(.*)\1$/, '$2');
                return key === '' ? null : [key, value];
            })
            .filter((entry) => entry !== null),
    );
}

function configuredValue(e2eName, laravelName, fallback) {
    return process.env[e2eName] ?? process.env[laravelName] ?? laravelEnv[laravelName] ?? fallback;
}

function testEnv() {
    const env = {
        ...process.env,
        APP_ENV: 'local',
        DB_CONNECTION: 'mysql',
        DB_HOST: dbHost,
        DB_PORT: dbPort,
        DB_DATABASE: database,
        DB_USERNAME: dbUsername,
        DB_PASSWORD: dbPassword,
        APP_URL: baseUrl,
    };

    if (appKey) {
        env.APP_KEY = appKey;
    }

    return env;
}

function tinker(expression) {
    const output = execFileSync(php, ['artisan', 'tinker', '--execute', expression], {
        cwd: projectDir,
        env: testEnv(),
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();

    return output.split(/\r?\n/).filter(Boolean).at(-1) ?? '';
}

function tinkerJson(expression) {
    return JSON.parse(tinker(expression));
}

// Pickup requests are scoped to the customer's RT.  The first active area in
// the public form is not necessarily the area assigned to the test customer,
// so resolve the linked area from the same database used by the application.
const customerServiceAreaId = Number(process.env.E2E_SERVICE_AREA_ID ?? tinker(`$u=\\App\\Models\\User::with('customerProfile')->findOrFail(${customerId}); echo \\App\\Domain\\CustomersRegions\\Models\\ServiceArea::query()->where('is_active',true)->whereHas('rts',fn($q)=>$q->whereKey($u->customerProfile->rt_id))->value('id');`));
if (!customerServiceAreaId) {
    throw new Error(`Area pelayanan aktif untuk customer ${customerId} tidak ditemukan.`);
}
const nextDate = process.env.E2E_NEXT_DATE ?? new Date(Date.now() + 86400000).toISOString().slice(0, 10);
// Local seed data contains historical capacities. Ensure the live UAT date has
// a normal operating quota without changing any existing capacity row.
tinker(`\\App\\Domain\\Pickups\\Models\\PickupCapacity::query()->firstOrCreate(['service_area_id'=>${customerServiceAreaId},'service_date'=>'${nextDate}'],['max_addresses'=>12,'max_weight_kg'=>'80.000','vehicle_label'=>'Kendaraan UAT','is_active'=>true]); echo 'ok';`);

async function waitForLivewire(page) {
    await page.waitForTimeout(1200);
}

async function settle(page) {
    await page.waitForTimeout(450);
}

async function searchTable(page, value) {
    const search = page.locator('input[type="search"]').last();
    await search.waitFor({ state: 'visible' });
    await search.fill(value);
    await page.waitForTimeout(1200);
}

async function chooseFirst(page, name) {
    const select = page.locator(`select[name="${name}"]`);
    await select.waitFor({ state: 'visible' });
    const value = await select.locator('option').evaluateAll((options) => {
        const option = options.find((candidate) => candidate.value !== '');
        return option?.value ?? '';
    });
    if (!value) throw new Error(`Tidak ada pilihan aktif untuk ${name}`);
    await select.selectOption(value);
    await settle(page);
}

async function loginCitizen(page, role) {
    const labels = { warga: 'Warga', petugas: 'Petugas', bendahara: 'Bendahara' };
    await dismissLivewireError(page);
    await logout(page);
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    if (new URL(page.url()).pathname !== '/login') throw new Error(`Sesi sebelumnya belum keluar sebelum login sebagai ${role}.`);
    await page.getByRole('button', { name: new RegExp(`^${labels[role]}`) }).click();
    await page.waitForFunction((password) => document.querySelector('input[name="password"]')?.value === password, devPassword);
    await Promise.all([
        page.waitForURL(/dashboard\/(warga|petugas|bendahara)/, { timeout: 15000 }),
        page.getByRole('button', { name: 'Masuk ke Akun' }).click(),
    ]);
}

async function loginBackoffice(page, email = 'admin@sindangheula.dev') {
    await page.goto(`${baseUrl}/backoffice/login`, { waitUntil: 'networkidle' });
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(devPassword);
    await Promise.all([
        page.waitForURL((url) => url.pathname.startsWith('/backoffice') && url.pathname !== '/backoffice/login', { timeout: 15000 }),
        page.getByRole('button', { name: /Sign in|Masuk|Login/i }).click(),
    ]);
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(1000);
}

async function logout(page) {
    await dismissLivewireError(page);
    let button = page.locator('form[action*="logout"] button').first();
    let visible = await button.isVisible().catch(() => false);

    // A page can be an error response without the navigation header. Visit the
    // public home first so an authenticated session can still be closed.
    if (!visible) {
        await page.goto(baseUrl, { waitUntil: 'domcontentloaded' }).catch(() => {});
        button = page.locator('form[action*="logout"] button').first();
        visible = await button.isVisible().catch(() => false);
    }

    if (visible) {
        await Promise.all([
            page.waitForURL(/\/login/, { timeout: 10000 }).catch(() => {}),
            button.click({ force: true }),
        ]);
    }
}

async function dismissLivewireError(page) {
    await page.locator('#livewire-error').evaluateAll((dialogs) => dialogs.forEach((dialog) => dialog.remove())).catch(() => {});
}

function observePage(page) {
    page.on('pageerror', (error) => browserErrors.push(`pageerror: ${error.message}`));
    page.on('requestfailed', (request) => {
        if (!offlineSimulation) {
            browserErrors.push(`requestfailed: ${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`);
        }
    });

    return page;
}

async function capture(page, name) {
    const filename = `${name.replace(/[^a-z0-9-_]+/gi, '-').replace(/^-|-$/g, '')}.png`;
    await page.screenshot({ path: path.join(artifactDirectory, filename), fullPage: true });
}

async function visitNavigationLinks(page, prefix, label) {
    const links = await page.locator('a[href]').evaluateAll((anchors, routePrefix) => [...new Set(
        anchors
            .map((anchor) => anchor.href)
            .filter((href) => {
                const url = new URL(href);
                return url.pathname.startsWith(routePrefix) && url.search === '' && url.hash === '';
            })
            .map((href) => new URL(href).pathname),
    )], prefix);

    if (links.length === 0) {
        throw new Error(`${label}: tidak ada tautan navigasi dengan awalan ${prefix}.`);
    }

    for (const href of links) {
        const response = await page.goto(`${baseUrl}${href}`, { waitUntil: 'domcontentloaded' });
        if (!response || response.status() >= 400) {
            throw new Error(`${label}: ${href} mengembalikan HTTP ${response?.status() ?? 'tanpa respons'}.`);
        }
        await page.locator('main, body').first().waitFor({ state: 'visible' });
        const errorDialog = page.locator('#livewire-error');
        if (await errorDialog.isVisible().catch(() => false)) {
            throw new Error(`${label}: ${href} menampilkan error Livewire: ${(await errorDialog.innerText()).trim() || 'tanpa detail'}.`);
        }
        const bodyText = await page.locator('body').innerText();
        if (/Internal Server Error|Return value must be of type|View \[[^\]]+\] not found/i.test(bodyText)) {
            throw new Error(`${label}: ${href} menampilkan error aplikasi.`);
        }
    }

    console.log(`SMOKE | ${label}: ${links.length} halaman navigasi.`);
}

async function run(name, callback) {
    try {
        await callback();
        results.push({ name, status: 'PASS' });
        console.log(`PASS | ${name}`);
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        results.push({ name, status: 'FAIL', message });
        console.log(`FAIL | ${name} | ${message}`);
    }
}

const browser = await chromium.launch({
    headless: true,
    // Laragon's development certificate is self-signed. Chromium otherwise
    // rejects the service-worker script even when the page allows HTTPS errors.
    args: pwaBaseUrl.startsWith('https://') ? ['--ignore-certificate-errors'] : [],
});

await run('Publik, manifest PWA, cache offline, dan banner koneksi', async () => {
    const pwaContext = await browser.newContext({
        viewport: { width: 1280, height: 900 },
        ignoreHTTPSErrors: pwaBaseUrl.startsWith('https://'),
    });
    const pwaPage = observePage(await pwaContext.newPage());

    try {
        const homeResponse = await pwaPage.goto(pwaBaseUrl, { waitUntil: 'networkidle' });
        if (!homeResponse?.ok()) throw new Error(`Halaman beranda mengembalikan HTTP ${homeResponse?.status() ?? 'tanpa respons'}.`);

        const manifestHref = await pwaPage.locator('link[rel="manifest"]').getAttribute('href');
        if (!manifestHref?.endsWith('/manifest.webmanifest')) throw new Error('Tag manifest PWA tidak ditemukan atau URL-nya salah.');

        const manifestResponse = await pwaPage.request.get(`${pwaBaseUrl}/manifest.webmanifest`);
        const manifest = await manifestResponse.json();
        if (!manifestResponse.ok() || manifest.display !== 'standalone' || manifest.icons?.length < 2) {
            throw new Error('Manifest PWA tidak valid atau ikon PWA belum lengkap.');
        }

        await pwaPage.waitForFunction(async () => {
            const registration = await navigator.serviceWorker.getRegistration();
            return registration?.active?.scriptURL.endsWith('/sw.js') === true;
        }, null, { timeout: 20000 });
        await pwaPage.reload({ waitUntil: 'networkidle' });
        const pwaState = await pwaPage.evaluate(async () => ({
            controlled: navigator.serviceWorker.controller !== null,
            caches: await caches.keys(),
        }));
        if (!pwaState.controlled || !pwaState.caches.some((cacheName) => cacheName.startsWith('bank-sampah-public-'))) {
            throw new Error('Service worker belum mengendalikan halaman publik atau cache publik belum dibuat.');
        }
        await capture(pwaPage, 'public-pwa-online');

        for (const route of ['/', '/katalog-sampah', '/harga-sampah', '/pengumuman', '/jadwal-keliling', '/target-dan-statistik', '/ketentuan-dan-privasi']) {
            const response = await pwaPage.goto(`${pwaBaseUrl}${route}`, { waitUntil: 'domcontentloaded' });
            if (!response?.ok()) throw new Error(`Halaman publik ${route} mengembalikan HTTP ${response?.status() ?? 'tanpa respons'}.`);
            await pwaPage.locator('main').waitFor({ state: 'visible' });
        }

        offlineSimulation = true;
        await pwaContext.setOffline(true);
        const offlineResponse = await pwaPage.goto(pwaBaseUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
        if (!offlineResponse?.ok()) throw new Error('Beranda publik tidak tersedia dari cache ketika offline.');
        await pwaPage.evaluate(() => window.dispatchEvent(new Event('offline')));
        await pwaPage.getByText('Mode offline aktif', { exact: true }).waitFor({ state: 'visible' });
        await capture(pwaPage, 'public-pwa-offline-banner');

        const cachedCatalogResponse = await pwaPage.goto(`${pwaBaseUrl}/katalog-sampah`, { waitUntil: 'domcontentloaded', timeout: 15000 });
        if (!cachedCatalogResponse?.ok()) throw new Error('Katalog sampah tidak tersedia dari cache ketika offline.');
    } finally {
        await pwaContext.setOffline(false).catch(() => {});
        offlineSimulation = false;
        await pwaContext.close();
    }
});

await run('Dashboard Warga, Petugas, Bendahara, dan Admin dapat diakses', async () => {
    const roleDashboards = [
        ['warga', '/dashboard/warga'],
        ['petugas', '/dashboard/petugas'],
        ['bendahara', '/dashboard/bendahara'],
    ];

    for (const [role, route] of roleDashboards) {
        const roleContext = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        const rolePage = observePage(await roleContext.newPage());
        await loginCitizen(rolePage, role);
        if (new URL(rolePage.url()).pathname !== route) throw new Error(`Dashboard ${role} tidak terbuka setelah login.`);
        await rolePage.getByRole('heading').first().waitFor({ state: 'visible' });
        await capture(rolePage, `dashboard-${role}`);
        await logout(rolePage);
        await roleContext.close();
    }

    const adminContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const adminPage = observePage(await adminContext.newPage());
    await loginBackoffice(adminPage, 'superadmin@sindangheula.dev');
    await adminPage.getByRole('heading').first().waitFor({ state: 'visible' });
    await capture(adminPage, 'dashboard-admin-backoffice');
    await visitNavigationLinks(adminPage, '/backoffice', 'Backoffice');
    await adminContext.close();
});

const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = observePage(await context.newPage());

let pickupNumber = null;
let withdrawalNumber = null;
let withdrawalId = null;
let groceryNumber = null;
let groceryId = null;
let depositData = null;

await run('Warga mengajukan pickup dengan foto', async () => {
    await loginCitizen(page, 'warga');
    await page.goto(`${baseUrl}/warga/penjemputan/ajukan`, { waitUntil: 'networkidle' });
    await page.locator('select[name="serviceAreaId"]').selectOption(String(customerServiceAreaId));
    await settle(page);
    await settle(page);
    await page.locator('input[name="selectedDate"]').fill(nextDate);
    await settle(page);
    await page.locator('textarea[name="address"]').fill('Jl. UAT E2E No. 1, Sindangheula');
    await settle(page);
    await page.getByRole('button', { name: 'Lanjut ke Jenis & foto' }).click();
    await settle(page);
    await chooseFirst(page, 'items.0.waste_type_id');
    await page.locator('input[name="items.0.estimated_weight_kg"]').fill('2.500');
    await settle(page);
    await page.locator('input[name="items.0.estimated_quantity"]').fill('4');
    await settle(page);
    const photoUpload = page.locator('#pickup-photos').evaluate((input) => new Promise((resolve, reject) => {
        input.addEventListener('livewire-upload-finish', () => resolve(true), { once: true });
        input.addEventListener('livewire-upload-error', () => reject(new Error('Upload foto pickup ditolak Livewire.')), { once: true });
    }));
    await page.locator('#pickup-photos').setInputFiles(fixture);
    await photoUpload;
    await page.getByText('1 dari 2 foto siap dikirim.', { exact: true }).waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
    await page.getByRole('button', { name: 'Lanjut ke Tinjau' }).click();
    await settle(page);
    if (!(await page.locator('body').innerText()).includes('1 dari 2 foto')) {
        throw new Error('Foto pickup belum tersimpan di langkah tinjau.');
    }
    await page.getByRole('button', { name: /Kirim pengajuan/i }).click();
    await page.waitForURL(/\/warga\/penjemputan\/\d+/, { timeout: 20000 });
    await waitForLivewire(page);
    const text = await page.locator('body').innerText();
    if (!text.toLowerCase().includes('menunggu pemeriksaan')) throw new Error('Detail pickup tidak menampilkan status awal.');
    pickupNumber = text.match(/PUP-[A-Z0-9-]+/)?.[0] ?? null;
    if (!pickupNumber) throw new Error('Nomor pickup tidak ditemukan.');
    await capture(page, 'warga-pickup-terkirim');
    await logout(page);
});

await run('Petugas memfinalisasi setoran dan saldo masuk', async () => {
    await loginCitizen(page, 'petugas');
    await page.goto(`${baseUrl}/petugas/setoran/${customerId}`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Tambah Item' }).click();
    await settle(page);
    await chooseFirst(page, 'items.0.waste_type_id');
    await chooseFirst(page, 'items.0.condition_id');
    await page.locator('input[name="items.0.weight_kg"]').fill('100.000');
    await settle(page);
    await page.locator('#deposit-evidence').setInputFiles(fixture);
    await settle(page);
    await page.getByRole('button', { name: 'Catat setoran' }).click();
    await waitForLivewire(page);
    if (!await page.getByText('Setoran berhasil dicatat.').isVisible()) {
        throw new Error('Pesan finalisasi setoran tidak muncul.');
    }
    depositData = tinkerJson(`$d=\\App\\Domain\\Deposits\\Models\\Deposit::where('customer_id',${customerId})->latest('id')->firstOrFail(); echo json_encode(['id'=>$d->id,'number'=>$d->deposit_number,'value'=>$d->total_value,'token'=>$d->verificationToken()]);`);
    if (depositData.value <= 0 || !depositData.token) throw new Error('Setoran final tidak menghasilkan nilai atau token QR.');
    await capture(page, 'petugas-setoran-difinalisasi');
    await logout(page);
});

await run('Warga membuka bukti setoran dan QR publik', async () => {
    const qrContext = await browser.newContext();
    const qrPage = await qrContext.newPage();
    await loginCitizen(qrPage, 'warga');
    await qrPage.goto(`${baseUrl}/warga/riwayat-setoran`, { waitUntil: 'networkidle' });
    await qrPage.goto(`${baseUrl}/warga/setoran/${depositData.id}`, { waitUntil: 'networkidle' });
    const qr = qrPage.locator('img[alt^="QR verifikasi"]');
    await qr.waitFor({ state: 'visible' });
    if (!(await qr.getAttribute('src'))?.startsWith('data:image/')) throw new Error('QR receipt bukan data image.');
    await qrPage.goto(`${baseUrl}/verifikasi/setoran/${depositData.token}`, { waitUntil: 'networkidle' });
    if ((await qrPage.locator('body').innerText()).includes('404')) throw new Error('QR verifikasi publik tidak ditemukan.');
    await capture(qrPage, 'publik-verifikasi-setoran');
    await qrContext.close();
});

await run('Warga menggunakan estimasi, kartu digital, dan pusat notifikasi', async () => {
    await loginCitizen(page, 'warga');
    await page.goto(`${baseUrl}/warga/estimasi`, { waitUntil: 'networkidle' });
    await chooseFirst(page, 'wasteTypeId');
    await chooseFirst(page, 'conditionId');
    await page.locator('input[name="weightKg"]').fill('1.250');
    await page.getByRole('button', { name: 'Hitung Estimasi' }).click();
    await page.getByText('Estimasi Informatif', { exact: true }).waitFor({ state: 'visible' });

    await page.goto(`${baseUrl}/warga/kartu-nasabah`, { waitUntil: 'networkidle' });
    await page.getByRole('heading', { name: 'Kartu Digital Nasabah' }).waitFor({ state: 'visible' });
    await capture(page, 'warga-kartu-digital');

    await page.goto(`${baseUrl}/notifikasi`, { waitUntil: 'networkidle' });
    await page.getByRole('heading', { name: 'Notifikasi', exact: true }).last().waitFor({ state: 'visible' });
    await logout(page);
});

await run('Admin menerima dan menjadwalkan pickup', async () => {
    if (!pickupNumber) throw new Error('Prasyarat pickup belum tersedia; tahap approval dilewati.');
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    await loginBackoffice(admin);
    await admin.goto(`${baseUrl}/backoffice/pickups/models/pickup-requests`, { waitUntil: 'networkidle' });
    await searchTable(admin, pickupNumber);
    const row = admin.locator('tr').filter({ hasText: pickupNumber }).first();
    await row.waitFor({ state: 'visible' });
    await row.getByRole('button', { name: 'Terima' }).click();
    await waitForLivewire(admin);
    const acceptedRow = admin.locator('tr').filter({ hasText: pickupNumber }).first();
    await acceptedRow.getByRole('button', { name: 'Jadwalkan' }).click();
    const dialog = admin.locator('[role="dialog"]');
    await dialog.locator('select').first().selectOption({ index: 1 });
    await dialog.locator('input[type="date"]').fill(nextDate);
    await dialog.getByRole('button', { name: /Kirim|Jadwalkan/ }).click();
    await waitForLivewire(admin);
    const state = tinker(`$p=\\App\\Domain\\Pickups\\Models\\PickupRequest::where('request_number','${pickupNumber}')->firstOrFail(); echo $p->status->value;`);
    if (state !== 'dijadwalkan') throw new Error(`Status pickup setelah approval: ${state}`);
    await adminContext.close();
});

await run('Petugas menyelesaikan pickup dan setoran aktual', async () => {
    if (!pickupNumber) throw new Error('Prasyarat pickup belum tersedia; tahap penyelesaian dilewati.');
    await loginCitizen(page, 'petugas');
    const pickup = tinkerJson(`$p=\\App\\Domain\\Pickups\\Models\\PickupRequest::where('request_number','${pickupNumber}')->firstOrFail(); echo json_encode(['id'=>$p->id,'staff'=>$p->assigned_staff_id]);`);
    await page.goto(`${baseUrl}/petugas/penjemputan/${pickup.id}`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Menuju Lokasi' }).click();
    await waitForLivewire(page);
    await page.getByRole('button', { name: 'Tandai Dijemput' }).click();
    await waitForLivewire(page);
    await chooseFirst(page, 'actualItems.0.waste_type_id');
    await chooseFirst(page, 'actualItems.0.condition_id');
    await page.locator('input[name="actualItems.0.weight_kg"]').fill('2.250');
    await settle(page);
    await page.getByRole('button', { name: 'Finalkan Setoran Aktual' }).click();
    await waitForLivewire(page);
    const state = tinker(`$p=\\App\\Domain\\Pickups\\Models\\PickupRequest::where('request_number','${pickupNumber}')->firstOrFail(); echo $p->status->value;`);
    if (state !== 'selesai') throw new Error(`Status pickup setelah penyelesaian: ${state}`);
    await capture(page, 'petugas-pickup-selesai');
    await logout(page);
});

await run('Petugas mencari nasabah dan membuka tugas layanan keliling', async () => {
    await loginCitizen(page, 'petugas');
    await page.goto(`${baseUrl}/petugas/pindai`, { waitUntil: 'networkidle' });
    await page.locator('input[name="search"]').fill(customerNumber);
    await page.getByRole('button', { name: 'Cari Nasabah' }).click();
    await page.getByText('Kandidat identitas', { exact: true }).waitFor({ state: 'visible' });

    await page.goto(`${baseUrl}/petugas/layanan-keliling`, { waitUntil: 'networkidle' });
    await page.getByRole('heading', { name: 'Jadwal Layanan Keliling', exact: true }).last().waitFor({ state: 'visible' });
    await capture(page, 'petugas-layanan-keliling');
    await logout(page);
});

await run('Warga mengajukan pencairan dan hold saldo', async () => {
    await loginCitizen(page, 'warga');
    await page.goto(`${baseUrl}/warga/pencairan/ajukan`, { waitUntil: 'networkidle' });
    const availableText = await page.locator('text=/Saldo tersedia/').locator('..').innerText();
    const available = Number((availableText.match(/[0-9.]+/)?.[0] ?? '0').replaceAll('.', ''));
    const amount = Math.min(10000, Math.floor(available / 2500) * 2500);
    if (amount < 10000) throw new Error(`Saldo tersedia untuk pencairan hanya Rp${available}.`);
    await page.locator('input[name="amount"]').fill(String(amount));
    await settle(page);
    await page.locator('input[name="pickupDate"]').fill(nextDate);
    await settle(page);
    await page.locator('textarea[name="pickupLocation"]').fill('Loket UAT Bank Sampah Sindangheula');
    await settle(page);
    await page.getByRole('button', { name: 'Ajukan Pencairan' }).click();
    await page.waitForURL(/\/warga\/pencairan\/\d+/, { timeout: 20000 });
    await waitForLivewire(page);
    const withdrawal = tinkerJson(`$w=\\App\\Domain\\Withdrawals\\Models\\WithdrawalRequest::where('customer_id',${customerId})->latest('id')->firstOrFail(); echo json_encode(['id'=>$w->id,'number'=>$w->request_number]);`);
    withdrawalId = withdrawal.id;
    withdrawalNumber = withdrawal.number;
    if (!withdrawalNumber) throw new Error('Nomor pencairan tidak ditemukan.');
    await logout(page);
});

await run('Warga mengajukan penukaran sembako', async () => {
    await loginCitizen(page, 'warga');
    await page.goto(`${baseUrl}/warga/sembako/ajukan`, { waitUntil: 'networkidle' });
    const packageOption = page.locator('input[name="packageId"]:not(:disabled)').first();
    await packageOption.waitFor({ state: 'visible' });
    await packageOption.check();
    await settle(page);
    await page.getByRole('button', { name: 'Ajukan Paket' }).click();
    await page.waitForURL(/\/warga\/sembako\/\d+/, { timeout: 20000 });
    await waitForLivewire(page);
    const grocery = tinkerJson(`$g=\\App\\Domain\\Groceries\\Models\\GroceryRedemption::where('customer_id',${customerId})->latest('id')->firstOrFail(); echo json_encode(['id'=>$g->id,'number'=>$g->request_number]);`);
    groceryId = grocery.id;
    groceryNumber = grocery.number;
    if (!groceryNumber) throw new Error('Nomor sembako tidak ditemukan.');
    await logout(page);
});

await run('Admin menyetujui koreksi setoran melalui browser', async () => {
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    await loginBackoffice(admin, 'superadmin@sindangheula.dev');
    await admin.goto(`${baseUrl}/backoffice/deposits/models/deposits`, { waitUntil: 'networkidle' });
    await searchTable(admin, depositData.number);
    const row = admin.locator('tr').filter({ hasText: depositData.number }).first();
    await row.waitFor({ state: 'visible' });
    await row.getByRole('button', { name: 'Koreksi' }).click();
    const dialog = admin.locator('.fi-modal').last();
    await dialog.locator('input[type="number"]').first().fill(String(Math.max(0, depositData.value - 1000)));
    await settle(admin);
    await dialog.locator('textarea').first().fill('Koreksi UAT: verifikasi ulang hasil timbang dan bukti.');
    await settle(admin);
    await dialog.locator('input[type="file"]').setInputFiles(fixture);
    await settle(admin);
    await dialog.getByRole('button', { name: 'Catat koreksi', exact: true }).click();
    await waitForLivewire(admin);
    const state = tinker(`$d=\\App\\Domain\\Deposits\\Models\\Deposit::findOrFail(${depositData.id}); echo $d->status;`);
    if (state !== 'dikoreksi') throw new Error(`Status setoran setelah koreksi: ${state}`);
    await adminContext.close();
});

await run('Admin menyetujui sembako dan petugas mencatat handover', async () => {
    if (!groceryNumber || !groceryId) throw new Error('Prasyarat sembako belum tersedia; tahap approval dan handover dilewati.');
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    await loginBackoffice(admin);
    await admin.goto(`${baseUrl}/backoffice/groceries/models/grocery-redemptions`, { waitUntil: 'networkidle' });
    await searchTable(admin, groceryNumber);
    const row = admin.locator('tr').filter({ hasText: groceryNumber }).first();
    await row.waitFor({ state: 'visible' });
    await row.getByRole('button', { name: 'Setujui' }).click();
    const dialog = admin.locator('[role="dialog"]');
    await dialog.locator('textarea').first().fill('Stok tersedia untuk UAT dan siap diproses.');
    await settle(admin);
    await dialog.getByRole('button', { name: /Kirim|Setujui/ }).click();
    await waitForLivewire(admin);
    await adminContext.close();

    await loginCitizen(page, 'petugas');
    await page.goto(`${baseUrl}/petugas/sembako`, { waitUntil: 'networkidle' });
    const prepareButtons = page.getByRole('button', { name: 'Mulai Siapkan' });
    const prepareIndex = await prepareButtons.evaluateAll((buttons, id) => buttons.findIndex((button) => button.getAttribute('wire:click') === `prepare(${id})`), groceryId);
    if (prepareIndex >= 0) {
        await prepareButtons.nth(prepareIndex).click();
        await waitForLivewire(page);
    }
    const readyButtons = page.getByRole('button', { name: 'Tandai Siap Diambil' });
    const readyIndex = await readyButtons.evaluateAll((buttons, id) => buttons.findIndex((button) => button.getAttribute('wire:click') === `ready(${id})`), groceryId);
    if (readyIndex >= 0) {
        await readyButtons.nth(readyIndex).click();
        await waitForLivewire(page);
    }
    const handoverButtons = page.getByRole('button', { name: /Proses (Handover|serah-terima)/i });
    const handoverIndex = await handoverButtons.evaluateAll((buttons, id) => buttons.findIndex((button) => button.getAttribute('wire:click') === `select(${id})`), groceryId);
    if (handoverIndex < 0) throw new Error('Tombol handover untuk redemption UAT tidak ditemukan.');
    await handoverButtons.nth(handoverIndex).click();
    await page.locator('select[name="recipientVerification"]').selectOption('nomor_nasabah');
    await settle(page);
    await page.locator('input[name="recipientReference"]').fill(customerNumber);
    await settle(page);
    await page.locator('#grocery-proof').setInputFiles(fixture);
    await settle(page);
    await page.getByRole('button', { name: /Konfirmasi (Handover|serah-terima)/i }).click();
    await waitForLivewire(page);
    const state = tinker(`$g=\\App\\Domain\\Groceries\\Models\\GroceryRedemption::where('request_number','${groceryNumber}')->firstOrFail(); echo $g->status->value;`);
    if (state !== 'selesai') throw new Error(`Status sembako setelah handover: ${state}`);
    await logout(page);
});

await run('Admin menyetujui dan bendahara membayar pencairan', async () => {
    if (!withdrawalNumber || !withdrawalId) throw new Error('Prasyarat pencairan belum tersedia; tahap pembayaran dilewati.');
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    await loginBackoffice(admin, 'superadmin@sindangheula.dev');
    await admin.goto(`${baseUrl}/backoffice/withdrawals/models/withdrawal-requests`, { waitUntil: 'networkidle' });
    await searchTable(admin, withdrawalNumber);
    const row = admin.locator('tr').filter({ hasText: withdrawalNumber }).first();
    await row.waitFor({ state: 'visible' });
    admin.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Setujui' }).click();
    await admin.locator('.fi-modal').last().getByRole('button', { name: /Setujui pencairan/i }).click();
    await waitForLivewire(admin);
    const approvedRow = admin.locator('tr').filter({ hasText: withdrawalNumber }).first();
    const assignPayerButton = approvedRow.getByRole('button', { name: /Tetapkan (payer|petugas pembayaran)/i });
    await assignPayerButton.waitFor({ state: 'visible', timeout: 10000 });
    await assignPayerButton.click();
    const dialog = admin.locator('.fi-modal').last();
    await dialog.locator('select').first().selectOption({ label: payerLabel });
    await settle(admin);
    await dialog.getByRole('button', { name: /Kirim|Tetapkan payer/ }).click();
    await waitForLivewire(admin);
    await adminContext.close();

    await loginCitizen(page, 'bendahara');
    await page.goto(`${baseUrl}/bendahara/pencairan`, { waitUntil: 'networkidle' });
    const paymentButtons = page.getByRole('button', { name: 'Proses Pembayaran' });
    const paymentIndex = await paymentButtons.evaluateAll((buttons, id) => buttons.findIndex((button) => (button.getAttribute('wire:click') ?? '').includes(`select(${id})`)), withdrawalId);
    if (paymentIndex < 0) {
        throw new Error(`Tombol pembayaran untuk withdrawal UAT tidak ditemukan.\n${(await page.locator('body').innerText()).slice(-2500)}`);
    }
    await paymentButtons.nth(paymentIndex).click();
    await page.locator('select[name="recipientVerification"]').selectOption('nomor_nasabah');
    await settle(page);
    await page.locator('input[name="recipientReference"]').fill(customerNumber);
    await settle(page);
    await page.locator('#payment-proof').setInputFiles(fixture);
    await settle(page);
    await page.getByRole('button', { name: 'Tinjau sebelum bayar' }).click();
    await waitForLivewire(page);
    await page.getByRole('button', { name: 'Bayar dan catat bukti' }).click();
    await waitForLivewire(page);
    const state = tinker(`$w=\\App\\Domain\\Withdrawals\\Models\\WithdrawalRequest::where('request_number','${withdrawalNumber}')->firstOrFail(); echo $w->status->value;`);
    if (state !== 'sudah_dibayar') throw new Error(`Status pencairan setelah pembayaran: ${state}`);
    await capture(page, 'bendahara-pencairan-dibayar');
    await logout(page);
});

await run('Bendahara memfilter laporan transaksi', async () => {
    await loginCitizen(page, 'bendahara');
    await page.goto(`${baseUrl}/bendahara/laporan`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Per bulan' }).click();
    await waitForLivewire(page);
    await chooseFirst(page, 'reportType');
    await page.getByRole('button', { name: 'Terapkan' }).click();
    await waitForLivewire(page);
    await page.getByRole('heading', { name: /Laporan / }).last().waitFor({ state: 'visible' });
    await capture(page, 'bendahara-laporan-terfilter');
    await logout(page);
});

await context.close();
await browser.close();

console.log(`\nBrowser errors: ${browserErrors.length}`);
for (const error of browserErrors) console.log(`  ${error}`);
console.log(JSON.stringify({ results, browserErrors, depositData, pickupNumber, withdrawalNumber, groceryNumber }, null, 2));

if (results.some((result) => result.status === 'FAIL') || browserErrors.length > 0) {
    process.exitCode = 1;
}
