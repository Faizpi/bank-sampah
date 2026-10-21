import { chromium, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { verifyIsolatedServer } from './support/isolated-environment.mjs';

const { baseUrl } = await verifyIsolatedServer(process.env);
const output = path.resolve(process.env.E2E_ARTIFACT_DIR ?? 'artifacts/cutover-browser-2026-10-05');
const fixture = JSON.parse(fs.readFileSync(process.env.E2E_FIXTURE_FILE ?? 'artifacts/cutover-browser-fixture.json', 'utf8').replace(/^\uFEFF/, ''));
fs.mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const errors = [];
const results = [];
const pages = {};
async function capture(page, name) {
  await page.waitForLoadState('networkidle');
  await page.screenshot({path:path.join(output,name+'.png'),fullPage:true});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth > innerWidth+1),name+' horizontal overflow').toBe(false);
}
async function run(name, callback) {
  try { await callback(); results.push({name,status:'passed'}); console.log('PASS '+name); }
  catch(error) {results.push({name,status:'failed',message:error.message});console.log('FAIL '+name+': '+error.message);}
}
try {
  for (const role of ['warga','petugas','bendahara','admin']) await run('login and mobile/desktop '+role, async()=> {
    const context=await browser.newContext({viewport:{width:390,height:844}});
    const page=await context.newPage(); pages[role]=page; page.setDefaultTimeout(15000);
    page.on('pageerror',e=>errors.push(role+': '+e.message));
    page.on('response',r=>{if(r.status()>=500)errors.push(role+': HTTP '+r.status()+' '+r.url());});
    await page.goto(baseUrl+(role==='admin'?'/backoffice/login':'/login'));
    await page.locator('input[name=username], input[id="form.username"]').fill(role);
    await page.locator('input[type=password]').fill('AuditOnly#2026');
    await page.getByRole('button',{name:/^Masuk(?: ke Akun)?$/i}).click();
    await page.waitForURL(u=>!u.pathname.endsWith('/login'));
    await capture(page,role+'-mobile');
    await page.setViewportSize({width:1280,height:900}); await capture(page,role+'-desktop');
    await page.setViewportSize({width:390,height:844});
  });
  await run('pickup address is prefilled and photos optional',async()=>{
    const page=pages.warga;await page.goto(baseUrl+'/warga/penjemputan/ajukan');
    await expect(page.locator('[name=address]')).toHaveValue(fixture.address);
    await capture(page,'citizen-pickup');
  });
  await run('Admin has no routine approval or physical buttons',async()=>{
    const page=pages.admin;
    for(const route of ['withdrawals/models/withdrawal-requests','groceries/models/grocery-redemptions','pickups/models/pickup-requests']) {
      await page.goto(baseUrl+'/backoffice/'+route);
      await expect(page.getByRole('button',{name:/Setujui|Tetapkan petugas|Terima dan jadwalkan|Ambil pembayaran|Paket sudah diserahkan/})).toHaveCount(0);
      await capture(page,'admin-'+route.split('/')[0]);
    }
  });
  await run('officer records a deposit without photo',async()=>{
    const page=pages.petugas;await page.goto(baseUrl+'/petugas/setoran/'+fixture.customerId+'?draftId='+fixture.draftId);
    await page.getByRole('button',{name:'Data sudah benar - catat setoran'}).click();
    await expect(page.getByText('Setoran berhasil dicatat',{exact:true}).first()).toBeVisible();
    await capture(page,'officer-deposit-complete');
  });
  await run('Treasurer claims and pays cash without photo',async()=>{
    const page=pages.bendahara;await page.goto(baseUrl+'/bendahara/pencairan');
    await page.locator('button').filter({hasText:fixture.withdrawalNumber}).click();
    await page.getByRole('button',{name:'Ambil pembayaran',exact:true}).click();
    await page.getByRole('button',{name:'Uang tunai sudah diserahkan — catat pembayaran',exact:true}).click();
    await capture(page,'cash-confirmation');
    await page.getByRole('dialog', { name: 'Catat pembayaran tunai?' }).getByRole('button', { name: 'Periksa lagi', exact: true }).click();
    await expect(page.getByText('Penyerahan tercatat',{exact:true})).toHaveCount(0);
    await page.getByRole('button',{name:'Uang tunai sudah diserahkan — catat pembayaran',exact:true}).click();
    await page.getByRole('dialog', { name: 'Catat pembayaran tunai?' }).getByRole('button', { name: 'Ya, catat pembayaran', exact: true }).click();
    await expect(page.getByText('Penyerahan tercatat',{exact:true})).toBeVisible();
    await capture(page,'cash-completed');
  });
  await run('officer hands groceries directly without preparation or photo',async()=>{
    const page=pages.petugas;await page.goto(baseUrl+'/petugas/sembako');
    await page.locator('button').filter({hasText:fixture.groceryNumber}).click();
    await page.getByRole('button',{name:'Ambil sembako',exact:true}).click();
    await page.getByRole('button',{name:'Paket sudah diserahkan',exact:true}).click();
    await capture(page,'grocery-confirmation');
    await page.getByRole('dialog', { name: 'Catat penyerahan sembako?' }).getByRole('button', { name: 'Ya, catat penyerahan', exact: true }).click();
    await expect(page.getByText('Penyerahan tercatat',{exact:true})).toBeVisible();
    await capture(page,'groceries-completed');
  });
  await run('citizen sees successful receipts without photos',async()=>{
    const page=pages.warga;
    await page.goto(baseUrl+'/warga/pencairan/'+fixture.withdrawalId+'/bukti');await expect(page.getByRole('heading',{name:'Pencairan berhasil',exact:true}).first()).toBeVisible();await capture(page,'cash-receipt');
    await page.goto(baseUrl+'/warga/sembako/'+fixture.groceryId+'/bukti');await capture(page,'grocery-receipt');
  });
  await run('officer completed grocery bookmark opens read-only own history', async()=>{
    const page=pages.petugas;
    await page.goto(baseUrl+'/petugas/sembako?taskId='+fixture.groceryId);
    await expect(page).toHaveURL(new RegExp('/petugas/riwayat\\?type=grocery&recordId='+fixture.groceryId));
    await expect(page.getByRole('heading', {name:fixture.groceryNumber,exact:true})).toBeVisible();
    await expect(page.getByRole('button', {name:'Paket sudah diserahkan',exact:true})).toHaveCount(0);
    await expect(page.locator('nav[aria-label="Navigasi petugas"] [data-nav-item]')).toHaveCount(5);
    for (const width of [360,390,768,1280]) {
      await page.setViewportSize({width,height:900});
      await capture(page,'officer-grocery-history-'+width);
    }
    await page.setViewportSize({width:390,height:844});
  });
  await run('officer claims and finishes pickup with one final click',async()=>{
    const page=pages.petugas;await page.goto(baseUrl+'/dashboard/petugas');
    await page.getByRole('button',{name:'Ambil tugas',exact:true}).click();
    await page.getByRole('button',{name:'Menuju Lokasi',exact:true}).click();
    await page.getByRole('button',{name:'Tandai Dijemput',exact:true}).click();
    expect(fixture.wasteTypes).toHaveLength(4);
    for (const [index,waste] of fixture.wasteTypes.entries()) {
      if (index>0) await page.getByRole('button',{name:'Tambah Detail Timbang',exact:true}).click();
      await page.locator(`[name="actualItems.${index}.waste_type_id"]`).selectOption(String(waste.id));
      await page.locator(`[name="actualItems.${index}.condition_id"]`).selectOption(String(waste.conditionId));
      await page.locator(`[name="actualItems.${index}.weight_kg"]`).fill('1.000');
      await page.locator(`[name="actualItems.${index}.weight_kg"]`).blur();
    }
    await expect(page.getByText('Rp 5.000', {exact:true})).toBeVisible();
    for (const width of [360,390,768,1280]) {
      await page.setViewportSize({width,height:900});
      await capture(page,'four-waste-weighing-'+width);
    }
    await page.setViewportSize({width:390,height:844});
    await page.getByRole('button',{name:'Catat setoran dan selesaikan pickup',exact:true}).click();
    await expect(page.getByText('Penjemputan selesai dan setoran aktual telah dibuat.')).toBeVisible();
    await page.reload();
    await expect(page.getByText('Penjemputan selesai!', {exact:true})).toBeVisible();
    await expect(page.getByText('Lihat riwayat setoran', {exact:true})).toBeVisible();
    await capture(page,'pickup-completed');
  });
  await run('citizen cancels an unclaimed withdrawal and releases hold',async()=>{
    const page=pages.warga;await page.goto(baseUrl+'/warga/pencairan/ajukan');
    await page.locator('[name=amount]').fill('10000');await page.locator('[name=pickupLocation]').fill('Loket pembatalan uji');
    await page.locator('[wire\\:click="submit"]').click();await page.waitForURL(/\/warga\/pencairan\/\d+$/);
    await page.getByRole('button',{name:'Batalkan pengajuan',exact:true}).click();
    await expect(page.getByText('Pengajuan dibatalkan dan saldo tertahan dilepas.')).toBeVisible();await capture(page,'citizen-cancelled');
  });
} finally {
  fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({results,errors},null,2));
  await browser.close();
}
if(errors.length||results.some(r=>r.status!=='passed')) process.exitCode=1;
console.log(JSON.stringify({scenarios:results.length,passed:results.filter(r=>r.status==='passed').length,errors},null,2));
