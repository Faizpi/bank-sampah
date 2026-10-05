import '@fontsource-variable/plus-jakarta-sans';

// Livewire 4 provides the application's Alpine.js instance.
// Do not import or start Alpine.js from this bundle.

const OFFLINE_ACTION_MESSAGE = 'Koneksi diperlukan untuk mengirim perubahan ini.';
const PUBLIC_CACHE_PREFIX = 'bank-sampah-public-';
const PUBLIC_NAV_GROUP_SELECTOR = '[data-public-nav-group]';
const PUBLIC_NAV_TRIGGER_SELECTOR = '[data-public-nav-trigger]';
const PUBLIC_NAV_MENU_SELECTOR = '[data-public-nav-menu]';
const PUBLIC_NAV_CHEVRON_SELECTOR = '[data-public-nav-chevron]';
const PUBLIC_NAV_CLOSE_DELAY = 160;
const publicNavOpenState = new WeakMap();
const publicNavCloseTimers = new WeakMap();
let publicNavPointerOrigin = null;

function publicNavGroupFromTarget(target) {
    if (!(target instanceof Element)) {
        return null;
    }

    const group = target.closest(PUBLIC_NAV_GROUP_SELECTOR);

    return group instanceof HTMLElement ? group : null;
}

function publicNavElements(group) {
    const trigger = group.querySelector(PUBLIC_NAV_TRIGGER_SELECTOR);
    const menu = group.querySelector(PUBLIC_NAV_MENU_SELECTOR);
    const chevron = group.querySelector(PUBLIC_NAV_CHEVRON_SELECTOR);

    if (!(trigger instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) {
        return null;
    }

    return { trigger, menu, chevron };
}

function clearPublicNavCloseTimer(group) {
    const timer = publicNavCloseTimers.get(group);

    if (timer === undefined) {
        return;
    }

    window.clearTimeout(timer);
    publicNavCloseTimers.delete(group);
}

function schedulePublicNavGroupClose(group) {
    clearPublicNavCloseTimer(group);

    const timer = window.setTimeout(() => {
        publicNavCloseTimers.delete(group);

        if (document.activeElement instanceof Node && group.contains(document.activeElement)) {
            return;
        }

        setPublicNavGroupOpen(group, false);
    }, PUBLIC_NAV_CLOSE_DELAY);

    publicNavCloseTimers.set(group, timer);
}

function setPublicNavGroupOpen(group, open) {
    clearPublicNavCloseTimer(group);
    const elements = publicNavElements(group);

    if (elements === null) {
        return false;
    }

    publicNavOpenState.set(group, open);
    elements.trigger.setAttribute('aria-expanded', String(open));
    elements.menu.hidden = !open;

    if (elements.chevron instanceof Element) {
        elements.chevron.classList.toggle('rotate-90', open);
    }

    return true;
}

function isPublicNavGroupOpen(group) {
    const state = publicNavOpenState.get(group);

    if (typeof state === 'boolean') {
        return state;
    }

    const elements = publicNavElements(group);

    return elements !== null
        && elements.trigger.getAttribute('aria-expanded') === 'true'
        && !elements.menu.hidden;
}

function closePublicNavGroups(except = null) {
    document.querySelectorAll(PUBLIC_NAV_GROUP_SELECTOR).forEach((candidate) => {
        if (candidate instanceof HTMLElement && candidate !== except) {
            setPublicNavGroupOpen(candidate, false);
        }
    });
}

function openPublicNavGroup(group) {
    closePublicNavGroups(group);
    setPublicNavGroupOpen(group, true);
}

function togglePublicNavGroup(group) {
    if (isPublicNavGroupOpen(group)) {
        setPublicNavGroupOpen(group, false);
        return;
    }

    openPublicNavGroup(group);
}

document.addEventListener('click', (event) => {
    const group = publicNavGroupFromTarget(event.target);
    const trigger = event.target instanceof Element
        ? event.target.closest(PUBLIC_NAV_TRIGGER_SELECTOR)
        : null;
    if (!(trigger instanceof HTMLButtonElement) || !(group instanceof HTMLElement)) {
        publicNavPointerOrigin = null;

        if (group === null) {
            closePublicNavGroups();
        }

        return;
    }

    publicNavPointerOrigin = null;
    event.stopPropagation();
    togglePublicNavGroup(group);
}, true);

document.addEventListener('pointerdown', (event) => {
    const trigger = event.target instanceof Element
        ? event.target.closest(PUBLIC_NAV_TRIGGER_SELECTOR)
        : null;

    publicNavPointerOrigin = trigger instanceof HTMLButtonElement ? trigger : null;
});

document.addEventListener('pointerup', (event) => {
    const trigger = event.target instanceof Element
        ? event.target.closest(PUBLIC_NAV_TRIGGER_SELECTOR)
        : null;

    if (!(trigger instanceof HTMLButtonElement)) {
        publicNavPointerOrigin = null;
    }
});

document.addEventListener('pointercancel', () => {
    publicNavPointerOrigin = null;
});

document.addEventListener('pointerover', (event) => {
    const group = publicNavGroupFromTarget(event.target);

    if (group === null) {
        return;
    }

    clearPublicNavCloseTimer(group);

    if (event.relatedTarget instanceof Node && group.contains(event.relatedTarget)) {
        return;
    }

    openPublicNavGroup(group);
});

document.addEventListener('pointerout', (event) => {
    const group = publicNavGroupFromTarget(event.target);

    if (group === null || event.relatedTarget instanceof Node && group.contains(event.relatedTarget)) {
        return;
    }

    if (!(document.activeElement instanceof Node) || !group.contains(document.activeElement)) {
        schedulePublicNavGroupClose(group);
    }
});

document.addEventListener('focusin', (event) => {
    const group = publicNavGroupFromTarget(event.target);

    if (group === null) {
        return;
    }

    clearPublicNavCloseTimer(group);

    const trigger = event.target instanceof Element
        ? event.target.closest(PUBLIC_NAV_TRIGGER_SELECTOR)
        : null;

    if (trigger instanceof HTMLButtonElement && trigger === publicNavPointerOrigin) {
        return;
    }

    openPublicNavGroup(group);
});

document.addEventListener('focusout', (event) => {
    const group = publicNavGroupFromTarget(event.target);

    if (group === null || event.relatedTarget instanceof Node && group.contains(event.relatedTarget)) {
        return;
    }

    setPublicNavGroupOpen(group, false);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    let openGroup = null;

    document.querySelectorAll(PUBLIC_NAV_GROUP_SELECTOR).forEach((candidate) => {
        if (openGroup === null && candidate instanceof HTMLElement && isPublicNavGroupOpen(candidate)) {
            openGroup = candidate;
        }
    });

    if (openGroup === null) {
        return;
    }

    const elements = publicNavElements(openGroup);

    if (elements === null) {
        return;
    }

    event.preventDefault();
    elements.trigger.focus();
    closePublicNavGroups();
});

window.addEventListener('load', () => {
    const logoutConfirmed = document.body.dataset.pwaLogoutConfirmed === 'true';

    if ('serviceWorker' in navigator) {
        const registration = navigator.serviceWorker.register('/sw.js');

        if (logoutConfirmed && 'caches' in window) {
            void registration.then(
                () => clearPublicCachesAfterLogout(),
                () => clearPublicCachesAfterLogout(),
            );
        }

        return;
    }

    if (logoutConfirmed && 'caches' in window) {
        void clearPublicCachesAfterLogout();
    }
});

async function clearPublicCachesAfterLogout() {
    const cacheNames = await caches.keys();

    await Promise.all(
        cacheNames
            .filter((cacheName) => cacheName.startsWith(PUBLIC_CACHE_PREFIX))
            .map((cacheName) => caches.delete(cacheName)),
    );
}

const CUSTOMER_CARD_CANVAS_WIDTH = 1600;
const CUSTOMER_CARD_CANVAS_HEIGHT = 1009;

function customerCardFromTarget(target) {
    if (!(target instanceof Element)) {
        return null;
    }

    const page = target.closest('[data-customer-card-page]');
    const card = page?.querySelector('[data-customer-card-printable]');

    return card instanceof HTMLElement ? card : null;
}

function customerCardStatus(card, message) {
    const page = card.closest('[data-customer-card-page]');
    const status = page?.querySelector('[data-customer-card-status]');

    if (status instanceof HTMLElement) {
        status.textContent = message;
    }
}

function customerCardColor(name, fallback) {
    const color = window.getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return color === '' ? fallback : color;
}

function customerCardRoundedRectangle(context, x, y, width, height, radius, fillStyle, strokeStyle = null) {
    context.beginPath();
    context.roundRect(x, y, width, height, radius);
    context.fillStyle = fillStyle;
    context.fill();

    if (strokeStyle !== null) {
        context.strokeStyle = strokeStyle;
        context.lineWidth = 2;
        context.stroke();
    }
}

function customerCardTextLines(context, text, maxWidth) {
    const words = text.trim().split(/\s+/).filter(Boolean);
    const lines = [];
    let line = '';

    for (const word of words) {
        const candidate = line === '' ? word : `${line} ${word}`;

        if (line !== '' && context.measureText(candidate).width > maxWidth) {
            lines.push(line);
            line = word;
        } else {
            line = candidate;
        }
    }

    if (line !== '') {
        lines.push(line);
    }

    return lines.length > 0 ? lines : [''];
}

function customerCardDrawText(context, text, x, y, maxWidth, lineHeight, maxLines = 2) {
    const lines = customerCardTextLines(context, text, maxWidth).slice(0, maxLines);

    lines.forEach((line, index) => context.fillText(line, x, y + (index * lineHeight), maxWidth));

    return y + (lines.length * lineHeight);
}

function customerCardLoadImage(source) {
    return new Promise((resolve, reject) => {
        const image = new Image();

        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Kode QR tidak dapat dipersiapkan untuk PNG.'));
        image.src = source;
    });
}

async function customerCardCanvas(card) {
    const canvas = document.createElement('canvas');
    canvas.width = CUSTOMER_CARD_CANVAS_WIDTH;
    canvas.height = CUSTOMER_CARD_CANVAS_HEIGHT;
    const context = canvas.getContext('2d');

    if (context === null) {
        throw new Error('Browser tidak mendukung pembuatan kartu PNG.');
    }

    const forest = customerCardColor('--color-forest-600', '#1e6a56');
    const forestDark = customerCardColor('--color-forest-700', '#185746');
    const deepGreen = customerCardColor('--color-deep-green', '#123d32');
    const surface = customerCardColor('--color-surface', '#ffffff');
    const warmCanvas = customerCardColor('--color-warm-canvas', '#f6f5ef');
    const textSecondary = customerCardColor('--color-text-secondary', '#55635d');
    const border = customerCardColor('--color-border', '#d9e1dc');
    const name = card.querySelector('[data-customer-card-name]')?.textContent?.trim() ?? 'Nasabah Bank Sampah';
    const number = card.querySelector('[data-customer-card-number]')?.textContent?.trim() ?? '';
    const area = card.querySelector('[data-customer-card-area]')?.textContent?.trim() ?? 'Desa Sindangheula';
    const qrSource = card.querySelector('[data-customer-card-qr]')?.getAttribute('src') ?? '';

    context.save();
    context.beginPath();
    context.roundRect(0, 0, canvas.width, canvas.height, 34);
    context.clip();

    context.fillStyle = surface;
    context.fillRect(0, 0, canvas.width, canvas.height);

    context.save();
    context.globalAlpha = 0.08;
    context.strokeStyle = forest;
    context.lineWidth = 1;
    for (let x = 0; x <= canvas.width; x += 48) {
        context.beginPath();
        context.moveTo(x, 0);
        context.lineTo(x, canvas.height);
        context.stroke();
    }
    for (let y = 0; y <= canvas.height; y += 48) {
        context.beginPath();
        context.moveTo(0, y);
        context.lineTo(canvas.width, y);
        context.stroke();
    }
    context.restore();

    context.fillStyle = forest;
    context.fillRect(0, 0, canvas.width, 164);
    context.strokeStyle = forestDark;
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(0, 164);
    context.lineTo(canvas.width, 164);
    context.stroke();

    context.fillStyle = surface;
    context.font = "700 24px 'Plus Jakarta Sans', sans-serif";
    context.fillText('KARTU NASABAH DIGITAL', 72, 82);
    context.fillStyle = surface;
    context.font = "700 34px 'Plus Jakarta Sans', sans-serif";
    context.fillText('Bank Sampah Sindangheula', 72, 130);

    customerCardRoundedRectangle(context, 1224, 60, 242, 56, 28, surface, border);
    context.fillStyle = forest;
    context.beginPath();
    context.arc(1259, 88, 8, 0, Math.PI * 2);
    context.fill();
    context.font = "700 22px 'Plus Jakarta Sans', sans-serif";
    context.fillText('AKTIF', 1281, 96);

    context.fillStyle = textSecondary;
    context.font = "600 22px 'Plus Jakarta Sans', sans-serif";
    context.fillText('NAMA NASABAH', 72, 292);
    context.fillStyle = deepGreen;
    context.font = "700 62px 'Plus Jakarta Sans', sans-serif";
    const nextNameY = customerCardDrawText(context, name, 72, 366, 800, 74);

    const detailsY = Math.max(550, nextNameY + 78);
    context.strokeStyle = border;
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(72, detailsY - 42);
    context.lineTo(872, detailsY - 42);
    context.stroke();

    context.fillStyle = textSecondary;
    context.font = "600 22px 'Plus Jakarta Sans', sans-serif";
    context.fillText('NOMOR NASABAH', 72, detailsY);
    context.fillText('WILAYAH LAYANAN', 72, detailsY + 128);
    context.fillStyle = deepGreen;
    context.font = "700 34px 'Plus Jakarta Sans', sans-serif";
    context.fillText(number, 72, detailsY + 48);
    customerCardDrawText(context, area, 72, detailsY + 176, 800, 40, 2);

    customerCardRoundedRectangle(context, 1044, 222, 416, 416, 24, surface, border);
    if (qrSource !== '') {
        const qr = await customerCardLoadImage(qrSource);
        context.drawImage(qr, 1068, 246, 368, 368);
    } else {
        context.fillStyle = warmCanvas;
        context.fillRect(1068, 246, 368, 368);
        context.fillStyle = textSecondary;
        context.font = "600 26px 'Plus Jakarta Sans', sans-serif";
        context.textAlign = 'center';
        context.fillText('QR belum aktif', 1252, 442);
        context.textAlign = 'left';
    }

    context.fillStyle = textSecondary;
    context.font = "600 20px 'Plus Jakarta Sans', sans-serif";
    context.textAlign = 'center';
    context.fillText('PINDAI UNTUK VERIFIKASI', 1252, 688);
    context.textAlign = 'left';

    context.fillStyle = warmCanvas;
    context.fillRect(0, 868, canvas.width, 141);
    context.fillStyle = textSecondary;
    context.font = "600 21px 'Plus Jakarta Sans', sans-serif";
    context.fillText('QR tanpa data saldo · Gunakan di layanan resmi Bank Sampah Sindangheula', 72, 948);

    context.restore();

    return canvas;
}

async function renderCustomerCardPreview(card) {
    const preview = card.querySelector('[data-customer-card-preview-image]');
    const fallback = card.querySelector('[data-customer-card-preview-fallback]');

    if (!(preview instanceof HTMLImageElement) || !(fallback instanceof HTMLElement)) {
        return;
    }

    try {
        const canvas = await customerCardCanvas(card);
        preview.src = canvas.toDataURL('image/png');
        preview.hidden = false;
        fallback.hidden = true;
        card.dataset.customerCardPreviewState = 'ready';
    } catch (error) {
        card.dataset.customerCardPreviewState = 'fallback';
    }
}

function renderCustomerCardPreviews() {
    document.querySelectorAll('[data-customer-card-printable]').forEach((card) => {
        if (card instanceof HTMLElement && card.dataset.customerCardPreviewState !== 'ready') {
            void renderCustomerCardPreview(card);
        }
    });
}

function scheduleCustomerCardPreviews() {
    if (document.fonts?.ready) {
        void document.fonts.ready.then(renderCustomerCardPreviews);
        return;
    }

    renderCustomerCardPreviews();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleCustomerCardPreviews, { once: true });
} else {
    scheduleCustomerCardPreviews();
}

window.addEventListener('livewire:navigated', scheduleCustomerCardPreviews);
document.addEventListener('livewire:navigated', scheduleCustomerCardPreviews);
window.addEventListener('load', () => window.setTimeout(scheduleCustomerCardPreviews, 250));

function customerCardDownloadFilename(card) {
    const number = card.querySelector('[data-customer-card-number]')?.textContent?.trim() ?? 'nasabah';
    const safeNumber = number.replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'nasabah';

    return `kartu-nasabah-${safeNumber}.png`;
}

async function downloadCustomerCardPng(button, card) {
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    customerCardStatus(card, 'Menyiapkan kartu PNG.');

    try {
        const canvas = await customerCardCanvas(card);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));

        if (blob === null) {
            throw new Error('Kartu PNG tidak dapat dibuat.');
        }

        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = customerCardDownloadFilename(card);
        document.body.append(anchor);
        anchor.click();
        anchor.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        customerCardStatus(card, 'Kartu PNG berhasil diunduh dan siap dicetak.');
    } catch (error) {
        customerCardStatus(card, error instanceof Error ? error.message : 'Kartu PNG tidak dapat dibuat. Coba lagi.');
    } finally {
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
}

function handlePublicNavigationClick(event) {
    const target = event.target instanceof Element ? event.target.closest('[data-public-navigation-trigger]') : null;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    window.dispatchEvent(new CustomEvent('open-bottom-sheet', {
        detail: {
            id: target.getAttribute('aria-controls'),
            invoker: target,
        },
    }));
}

window.addEventListener('click', handlePublicNavigationClick);

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const cardDownload = target?.closest('[data-customer-card-download]');

    if (!(cardDownload instanceof HTMLButtonElement)) {
        return;
    }

    const card = customerCardFromTarget(cardDownload);

    if (card !== null) {
        event.preventDefault();
        void downloadCustomerCardPng(cardDownload, card);
    }
});

// Bound on window (not document) to respect the PWA contract that reserves
// document-level click listeners for the two navigation handles only.
window.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const printButton = target?.closest('[data-print-button]');

    if (printButton === null) {
        return;
    }

    event.preventDefault();
    window.print();
});

document.addEventListener('public:offline-action-blocked', (event) => {
    if (!(event instanceof CustomEvent)) {
        return;
    }

    const liveRegions = document.querySelectorAll('[data-public-offline-status]');

    liveRegions.forEach((liveRegion) => {
        liveRegion.textContent = event.detail.message;
    });

    event.preventDefault();
});

document.addEventListener('livewire:init', () => {
    Livewire.interceptRequest(({ request }) => {
        if (navigator.onLine) {
            return;
        }

        request.cancel();
        dispatchOfflineActionBlocked('livewire');
    });
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || navigator.onLine) {
        return;
    }

    const isNativeStateChanging = form.method.toLowerCase() !== 'get'
        && !Array.from(form.attributes).some((attribute) => attribute.name.startsWith('wire:submit'));

    if (!isNativeStateChanging) {
        return;
    }

    blockNativeOfflineAction(event, form);
}, true);

function blockNativeOfflineAction(event, source) {
    event.preventDefault();
    event.stopImmediatePropagation();
    dispatchOfflineActionBlocked(source);
}

function dispatchOfflineActionBlocked(source) {
    const offlineEvent = new CustomEvent('public:offline-action-blocked', {
        bubbles: true,
        cancelable: true,
        detail: {
            source,
            message: OFFLINE_ACTION_MESSAGE,
        },
    });
    const wasHandled = !document.dispatchEvent(offlineEvent);

    if (!wasHandled) {
        window.alert(OFFLINE_ACTION_MESSAGE);
    }
}

const PHOTO_PICKER_DEFAULT_MAX_COUNT = 2;
const PHOTO_PICKER_MAX_BYTES = 1024 * 1024;
const MEDIA_PICKER_MAX_BYTES = 5 * 1024 * 1024;
const PHOTO_PICKER_MAX_DIMENSION = 2000;
const PHOTO_PICKER_QUALITIES = [0.86, 0.78, 0.7, 0.62, 0.54, 0.46];
const PHOTO_PICKER_CONFIRM_ATTEMPTS = 3;
const PHOTO_PICKER_CONFIRM_DELAY_MS = 80;
const PHOTO_PICKER_EXTENSIONS = new Map([
    ['jpg', 'image/jpeg'],
    ['jpeg', 'image/jpeg'],
    ['png', 'image/png'],
    ['webp', 'image/webp'],
]);
const PHOTO_PICKER_CAMERA_EXTENSIONS = new Set([...PHOTO_PICKER_EXTENSIONS.keys(), 'heic', 'heif']);

function photoPickerFromTarget(target) {
    if (!(target instanceof Element)) {
        return null;
    }

    const picker = target.closest('[data-photo-picker]');

    return picker instanceof HTMLElement ? picker : null;
}

function photoPickerInput(picker) {
    const input = picker.querySelector('[data-photo-picker-input]');

    return input instanceof HTMLInputElement ? input : null;
}

function photoPickerMaxCount(picker) {
    const configured = Number.parseInt(picker.dataset.photoPickerMax ?? '', 10);

    return Number.isInteger(configured) && configured > 0 ? configured : PHOTO_PICKER_DEFAULT_MAX_COUNT;
}

function photoPickerAllowsPdf(picker) {
    return picker.dataset.photoPickerAllowPdf === 'true';
}

function photoPickerNoun(picker) {
    return photoPickerAllowsPdf(picker) ? 'file' : 'foto';
}

function photoPickerStatusMessage(picker, count, maxCount) {
    if (count === 0) {
        return picker.dataset.photoPickerEmptyStatus
            ?? (photoPickerAllowsPdf(picker) ? 'Belum ada file dipilih.' : 'Belum ada foto dipilih.');
    }

    return `${count} dari ${maxCount} ${photoPickerNoun(picker)} siap dipakai.`;
}

function normalizedPhotoPickerFile(file, picker) {
    const fallbackName = photoPickerAllowsPdf(picker) ? 'Bukti unggahan' : 'Foto sampah';

    return {
        name: typeof file?.name === 'string' ? file.name : fallbackName,
        size: Number.isFinite(Number(file?.size)) ? Number(file.size) : 0,
        mimeType: typeof file?.mimeType === 'string'
            ? file.mimeType
            : (file instanceof File ? file.type : ''),
        previewUrl: typeof file?.previewUrl === 'string' ? file.previewUrl : '',
    };
}

function photoPickerFiles(input, picker) {
    if (Array.isArray(input._photoPickerFiles)) {
        return input._photoPickerFiles;
    }

    let initialFiles = [];
    try {
        const encoded = picker.dataset.photoPickerInitialFiles;
        initialFiles = typeof encoded === 'string' && encoded !== '' ? JSON.parse(encoded) : [];
    } catch {
        initialFiles = [];
    }

    input._photoPickerFiles = Array.isArray(initialFiles)
        ? initialFiles
            .filter((file) => file !== null && typeof file === 'object')
            .map((file) => normalizedPhotoPickerFile(file, picker))
        : [];

    return input._photoPickerFiles;
}

function photoPickerWire(picker) {
    const componentRoot = picker.closest('[wire\\:id]');
    const componentId = componentRoot?.getAttribute('wire:id');

    if (typeof componentId !== 'string' || componentId === '' || typeof window.Livewire?.find !== 'function') {
        return null;
    }

    return window.Livewire.find(componentId);
}

function photoPickerStatus(picker) {
    const status = picker.querySelector('[data-photo-picker-status]');

    return status instanceof HTMLElement ? status : null;
}

function photoPickerPreview(picker) {
    const preview = picker.querySelector('[data-photo-picker-preview]');

    return preview instanceof HTMLElement ? preview : null;
}

function formatPhotoSize(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function setPhotoPickerStatus(picker, message, state = 'idle', progress = null) {
    const status = photoPickerStatus(picker);
    const feedback = picker.querySelector('[data-photo-picker-feedback]');
    const progressTrack = picker.querySelector('[data-photo-picker-progress]');
    const progressBar = picker.querySelector('[data-photo-picker-progress-bar]');

    picker.dataset.photoPickerState = state;

    if (status !== null) {
        status.textContent = message;
    }

    if (feedback instanceof HTMLElement) {
        feedback.classList.toggle('text-text-secondary', state === 'idle');
        feedback.classList.toggle('text-sky-blue', state === 'busy');
        feedback.classList.toggle('text-forest-700', state === 'ready');
        feedback.classList.toggle('text-terracotta', state === 'error');
    }

    picker.querySelectorAll('[data-photo-picker-icon]').forEach((icon) => {
        if (icon instanceof SVGElement) {
            icon.classList.toggle('hidden', icon.dataset.photoPickerIcon !== state);
        }
    });

    const numericProgress = Number(progress);
    const hasProgress = state === 'busy' && Number.isFinite(numericProgress);
    if (progressTrack instanceof HTMLElement) {
        progressTrack.classList.toggle('hidden', !hasProgress);
    }
    if (progressBar instanceof HTMLElement) {
        const percentage = hasProgress ? Math.min(100, Math.max(0, numericProgress)) : 0;
        progressBar.style.width = `${percentage}%`;
    }
}

function appendFilePlaceholder(summary, file) {
    const placeholder = document.createElement('div');
    placeholder.className = 'flex size-12 shrink-0 items-center justify-center rounded-sm border border-border bg-surface text-sky-blue';
    placeholder.setAttribute('aria-hidden', 'true');
    placeholder.innerHTML = '<svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>';
    summary.append(placeholder);
}

function renderPhotoPickerPreview(picker, files) {
    const preview = photoPickerPreview(picker);

    if (preview === null) {
        return;
    }

    preview.replaceChildren();

    files.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'flex min-w-0 items-center justify-between gap-3 rounded-md border border-border bg-warm-canvas p-2 text-body-sm';

        const summary = document.createElement('div');
        summary.className = 'flex min-w-0 items-center gap-3';

        const mimeType = file instanceof File ? file.type : file.mimeType;
        const imageSource = file instanceof File ? URL.createObjectURL(file) : file.previewUrl;
        if (typeof mimeType === 'string' && mimeType.startsWith('image/') && typeof imageSource === 'string' && imageSource !== '') {
            const image = document.createElement('img');
            image.className = 'size-12 shrink-0 rounded-sm border border-border bg-surface object-cover';
            image.src = imageSource;
            image.alt = `Pratinjau ${file.name}`;
            if (file instanceof File) {
                image.addEventListener('load', () => URL.revokeObjectURL(imageSource), { once: true });
                image.addEventListener('error', () => URL.revokeObjectURL(imageSource), { once: true });
            }
            summary.append(image);
        } else {
            appendFilePlaceholder(summary, file);
        }

        const details = document.createElement('div');
        details.className = 'min-w-0';

        const name = document.createElement('p');
        name.className = 'truncate font-semibold text-deep-green';
        name.textContent = file.name;

        const size = document.createElement('p');
        size.className = 'text-caption text-text-secondary';
        size.textContent = `${formatPhotoSize(file.size)} · siap dipakai`;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.photoPickerRemove = String(index);
        remove.className = 'min-h-10 shrink-0 rounded-md px-3 text-label font-semibold text-terracotta hover:bg-danger-bg focus:outline-none focus:ring-2 focus:ring-focus disabled:cursor-wait disabled:bg-disabled-bg';
        remove.textContent = 'Hapus';
        remove.setAttribute('aria-label', `Hapus ${file.name}`);

        details.append(name, size);
        summary.append(details);
        item.append(summary, remove);
        preview.append(item);
    });
}

function setPhotoPickerBusy(picker, isBusy) {
    picker.toggleAttribute('aria-busy', isBusy);
    picker.dataset.photoPickerBusy = isBusy ? 'true' : 'false';

    picker.querySelectorAll('[data-photo-picker-trigger], [data-photo-picker-input], [data-photo-picker-remove]').forEach((element) => {
        if (element instanceof HTMLButtonElement || element instanceof HTMLInputElement) {
            element.disabled = isBusy;
        }

        if (element instanceof HTMLLabelElement) {
            element.setAttribute('aria-disabled', String(isBusy));
            element.classList.toggle('pointer-events-none', isBusy);
            element.classList.toggle('opacity-60', isBusy);
        }
    });

    const componentRoot = picker.closest('[wire\\:id]');
    componentRoot?.querySelectorAll('[data-photo-picker-action]').forEach((element) => {
        if (!(element instanceof HTMLButtonElement)) {
            return;
        }

        if (isBusy && !element.disabled) {
            element.disabled = true;
            element.dataset.photoPickerDisabled = 'true';
        } else if (!isBusy && element.dataset.photoPickerDisabled === 'true') {
            element.disabled = false;
            delete element.dataset.photoPickerDisabled;
        }
    });
}

function uploadPhotoPickerFiles(picker, property, files, multiple) {
    const wire = photoPickerWire(picker);
    const uploadMethod = multiple ? wire?.$uploadMultiple : wire?.$upload;
    if (wire === null || typeof uploadMethod !== 'function') {
        return Promise.reject(new Error('Uploader belum siap. Muat ulang halaman lalu coba lagi.'));
    }

    return new Promise((resolve, reject) => {
        const noun = photoPickerNoun(picker);
        const onError = () => reject(new Error(`${noun[0].toUpperCase()}${noun.slice(1)} gagal diunggah. Periksa koneksi lalu coba lagi.`));
        const onFinished = () => window.setTimeout(resolve, 0);
        const onProgress = (event) => {
            const progress = Number(event?.detail?.progress ?? event?.progress ?? 0);
            setPhotoPickerStatus(picker, `Mengunggah ${noun}… ${Math.round(progress)}%`, 'busy', progress);
        };

        if (multiple) {
            wire.$uploadMultiple(property, files, onFinished, onError, onProgress);
        } else {
            wire.$upload(property, files[0], onFinished, onError, onProgress);
        }
    });
}

async function confirmedPhotoPickerFiles(picker, fallbackFiles) {
    const confirmMethod = picker.dataset.photoPickerConfirmMethod;
    const wire = photoPickerWire(picker);

    if (typeof confirmMethod !== 'string' || confirmMethod === '' || wire === null) {
        return fallbackFiles.map((file) => normalizedPhotoPickerFile(file, picker));
    }

    for (let attempt = 1; attempt <= PHOTO_PICKER_CONFIRM_ATTEMPTS; attempt += 1) {
        try {
            const confirmedFiles = await wire.$call(confirmMethod);
            if (Array.isArray(confirmedFiles) && confirmedFiles.length > 0) {
                return confirmedFiles.map((file) => normalizedPhotoPickerFile(file, picker));
            }
        } catch {
            // Livewire may finish the upload callback just before its updated
            // component snapshot is available to the next method call.
        }

        if (attempt < PHOTO_PICKER_CONFIRM_ATTEMPTS) {
            await new Promise((resolve) => window.setTimeout(resolve, PHOTO_PICKER_CONFIRM_DELAY_MS));
        }
    }

    throw new Error(`${photoPickerNoun(picker)[0].toUpperCase()}${photoPickerNoun(picker).slice(1)} belum tersimpan di formulir. Muat ulang halaman lalu unggah kembali.`);
}

function loadPhotoImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Foto tidak dapat dibaca. Gunakan JPEG, PNG, atau WebP.'));
        };
        image.src = url;
    });
}

function canvasBlob(canvas, quality) {
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
}

async function compressPickupPhoto(file) {
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
    const browserRecognizesImage = file.type.startsWith('image/') || PHOTO_PICKER_CAMERA_EXTENSIONS.has(extension);
    if (!browserRecognizesImage) {
        throw new Error('Gunakan file foto yang dapat dibaca browser.');
    }

    const image = await loadPhotoImage(file);
    const scale = Math.min(1, PHOTO_PICKER_MAX_DIMENSION / Math.max(image.naturalWidth, image.naturalHeight));
    const baseWidth = Math.max(1, Math.round(image.naturalWidth * scale));
    const baseHeight = Math.max(1, Math.round(image.naturalHeight * scale));

    for (let dimensionScale = 1; dimensionScale >= 0.25; dimensionScale *= 0.85) {
        const width = Math.max(1, Math.round(baseWidth * dimensionScale));
        const height = Math.max(1, Math.round(baseHeight * dimensionScale));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');

        if (context === null) {
            throw new Error('Browser tidak mendukung kompresi foto.');
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        context.drawImage(image, 0, 0, width, height);

        for (const quality of PHOTO_PICKER_QUALITIES) {
            const blob = await canvasBlob(canvas, quality);

            if (blob !== null && blob.size <= PHOTO_PICKER_MAX_BYTES) {
                const baseName = file.name.replace(/\.[^.]+$/, '') || 'foto';

                return new File([blob], `${baseName}.jpg`, {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                });
            }
        }
    }

    throw new Error('Foto terlalu kompleks untuk dikompres sampai 1 MB. Coba foto lain.');
}

async function preparePhotoPickerFile(file, picker) {
    if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
        if (!photoPickerAllowsPdf(picker)) {
            throw new Error('Gunakan foto JPEG atau PNG.');
        }
        if (file.type !== 'application/pdf') {
            throw new Error('File PDF tidak dapat dibaca. Pilih PDF yang valid.');
        }
        if (file.size > MEDIA_PICKER_MAX_BYTES) {
            throw new Error('Ukuran PDF maksimal 5 MB.');
        }

        return file;
    }

    return compressPickupPhoto(file);
}

async function handlePhotoPickerChange(event, picker, input) {
    const property = input.dataset.photoPickerProperty;
    if (typeof property !== 'string' || property === '') {
        return;
    }

    if (picker.dataset.photoPickerBusy === 'true') {
        input.value = '';

        return;
    }

    const selectedFiles = Array.from(input.files ?? []);
    const existingFiles = photoPickerFiles(input, picker);

    if (selectedFiles.length === 0) {
        return;
    }

    const maxCount = photoPickerMaxCount(picker);
    const available = Math.max(0, maxCount - existingFiles.length);
    const filesToProcess = selectedFiles.slice(0, available);
    const preparedFiles = [];
    const noun = photoPickerNoun(picker);

    if (available === 0) {
        setPhotoPickerStatus(picker, `Maksimal ${maxCount} ${noun} sudah dipilih. Hapus yang lama sebelum menambahkan lagi.`, 'error');
        input.value = '';

        return;
    }

    setPhotoPickerBusy(picker, true);

    try {
        for (const [index, file] of filesToProcess.entries()) {
            const isPdf = file.type === 'application/pdf';
            setPhotoPickerStatus(
                picker,
                isPdf ? `Memeriksa file ${index + 1} dari ${filesToProcess.length}…` : `Mengompres foto ${index + 1} dari ${filesToProcess.length}…`,
                'busy',
            );
            preparedFiles.push(await preparePhotoPickerFile(file, picker));
        }

        setPhotoPickerStatus(picker, `Menyiapkan upload ${preparedFiles.length} ${noun}…`, 'busy', 0);
        await uploadPhotoPickerFiles(picker, property, preparedFiles, input.multiple);
        setPhotoPickerStatus(picker, `Memastikan ${noun} tersimpan di formulir…`, 'busy');

        const confirmedFiles = await confirmedPhotoPickerFiles(picker, [...existingFiles, ...preparedFiles]);
        input._photoPickerFiles = confirmedFiles;
        renderPhotoPickerPreview(picker, confirmedFiles);
        setPhotoPickerStatus(picker, photoPickerStatusMessage(picker, confirmedFiles.length, maxCount), 'ready');

        if (selectedFiles.length > filesToProcess.length) {
            setPhotoPickerStatus(picker, `Maksimal ${maxCount} ${noun}. Hanya ${filesToProcess.length} ${noun} pertama yang ditambahkan.`, 'error');
        }
    } catch (error) {
        setPhotoPickerStatus(picker, error instanceof Error ? error.message : `${noun[0].toUpperCase()}${noun.slice(1)} tidak dapat diproses.`, 'error');
    } finally {
        input.value = '';
        setPhotoPickerBusy(picker, false);
    }
}

function handlePhotoPickerRemove(event, picker, input) {
    const target = event.target instanceof Element ? event.target.closest('[data-photo-picker-remove]') : null;
    if (!(target instanceof HTMLButtonElement) || picker.dataset.photoPickerBusy === 'true') {
        return;
    }

    const index = Number.parseInt(target.dataset.photoPickerRemove ?? '', 10);
    const removeMethod = picker.dataset.photoPickerRemoveMethod;
    const wire = photoPickerWire(picker);
    if (!Number.isInteger(index) || index < 0 || typeof removeMethod !== 'string' || removeMethod === '' || wire === null) {
        setPhotoPickerStatus(picker, 'File tidak dapat dihapus. Muat ulang halaman lalu coba lagi.', 'error');

        return;
    }

    event.preventDefault();
    const files = photoPickerFiles(input, picker);
    const previousFiles = [...files];
    const noun = photoPickerNoun(picker);
    files.splice(index, 1);
    input._photoPickerFiles = files;
    renderPhotoPickerPreview(picker, files);
    setPhotoPickerBusy(picker, true);
    setPhotoPickerStatus(picker, `Menghapus ${noun}…`, 'busy');

    void wire.$call(removeMethod, index)
        .then(() => setPhotoPickerStatus(
            picker,
            photoPickerStatusMessage(picker, files.length, photoPickerMaxCount(picker)),
            files.length === 0 ? 'idle' : 'ready',
        ))
        .catch(() => {
            input._photoPickerFiles = previousFiles;
            renderPhotoPickerPreview(picker, previousFiles);
            setPhotoPickerStatus(picker, `${noun[0].toUpperCase()}${noun.slice(1)} tidak dapat dihapus. Coba lagi.`, 'error');
        })
        .finally(() => setPhotoPickerBusy(picker, false));
}

function hydratePhotoPicker(picker) {
    const input = photoPickerInput(picker);
    if (input === null) {
        return;
    }
    if (input.dataset.photoPickerInput === 'compressed-upload-v2') {
        return;
    }

    input.dataset.photoPickerInput = 'compressed-upload-v2';
    picker.querySelectorAll('[data-photo-picker-trigger]').forEach((trigger) => {
        if (!(trigger instanceof HTMLLabelElement) || trigger.dataset.photoPickerInitialized === 'true') {
            return;
        }

        trigger.dataset.photoPickerInitialized = 'true';
        const prepareInput = () => {
            const activeInput = photoPickerInput(picker);
            if (activeInput === null || picker.dataset.photoPickerBusy === 'true') {
                return null;
            }

            if (trigger.dataset.photoPickerTrigger === 'camera') {
                activeInput.setAttribute('capture', 'environment');
            } else {
                activeInput.removeAttribute('capture');
            }

            activeInput.value = '';

            return activeInput;
        };

        trigger.addEventListener('click', (event) => {
            if (prepareInput() === null) {
                event.preventDefault();
            }
            // Do not call input.click() here. The label's native activation is
            // required by mobile browsers that block programmatic file pickers.
        });
        trigger.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            prepareInput()?.click();
        });
    });
    input.addEventListener('change', (event) => void handlePhotoPickerChange(event, picker, input), true);
    if (picker.dataset.photoPickerInitialized !== 'true') {
        picker.dataset.photoPickerInitialized = 'true';
        picker.addEventListener('click', (event) => {
            const activeInput = photoPickerInput(picker);
            if (activeInput !== null) {
                handlePhotoPickerRemove(event, picker, activeInput);
            }
        });
    }

    const files = photoPickerFiles(input, picker);
    renderPhotoPickerPreview(picker, files);
    setPhotoPickerStatus(
        picker,
        photoPickerStatusMessage(picker, files.length, photoPickerMaxCount(picker)),
        files.length === 0 ? 'idle' : 'ready',
    );
}

function hydratePhotoPickers(root = document) {
    if (root instanceof HTMLElement && root.matches('[data-photo-picker]')) {
        hydratePhotoPicker(root);
    }

    if (root instanceof Document || root instanceof HTMLElement) {
        root.querySelectorAll('[data-photo-picker]').forEach((picker) => {
            if (picker instanceof HTMLElement) {
                hydratePhotoPicker(picker);
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => hydratePhotoPickers(), { once: true });
} else {
    hydratePhotoPickers();
}

document.addEventListener('livewire:initialized', () => hydratePhotoPickers());
document.addEventListener('livewire:navigated', () => hydratePhotoPickers());

const photoPickerObserver = new MutationObserver((records) => {
    records.forEach((record) => {
        record.addedNodes.forEach((node) => {
            if (node instanceof HTMLElement) {
                hydratePhotoPickers(node);
            }
        });
    });
});

photoPickerObserver.observe(document.body ?? document.documentElement, { childList: true, subtree: true });
