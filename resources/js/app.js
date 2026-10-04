import '@fontsource-variable/plus-jakarta-sans';

// Livewire 4 provides the application's Alpine.js instance.
// Do not import or start Alpine.js from this bundle.

const OFFLINE_ACTION_MESSAGE = 'Koneksi diperlukan untuk melanjutkan tindakan ini.';
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

document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element ? event.target.closest('[data-public-navigation-trigger]') : null;

    if (!(trigger instanceof HTMLElement)) {
        return;
    }

    window.dispatchEvent(new CustomEvent('open-bottom-sheet', {
        detail: {
            id: trigger.getAttribute('aria-controls'),
            invoker: trigger,
        },
    }));
});

document.addEventListener('public:offline-action-blocked', (event) => {
    if (!(event instanceof CustomEvent)) {
        return;
    }

    const liveRegions = document.querySelectorAll('[data-public-offline-status]');

    if (liveRegions.length === 0) {
        return;
    }

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
