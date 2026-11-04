'use strict';

// Menú principal móvil
const menuButton = document.querySelector('.menu-toggle');
const mainNav = document.querySelector('#main-nav');
const siteHeader = document.querySelector('.site-header');
const menuBackdrop = document.querySelector('[data-menu-close]');

if (menuButton && mainNav && siteHeader) {
    let savedScrollY = 0;
    let touchStartX = 0;
    let touchStartY = 0;

    const closeMenu = () => {
        if (menuButton.getAttribute('aria-expanded') !== 'true') return;

        mainNav.classList.remove('is-open');
        siteHeader.classList.remove('menu-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', 'Abrir menú');

        document.body.classList.remove('menu-locked');
        document.body.style.top = '';

        window.scrollTo(0, savedScrollY);
    };

    const openMenu = () => {
        savedScrollY = window.scrollY;

        document.body.style.top = `-${savedScrollY}px`;
        document.body.classList.add('menu-locked');

        mainNav.classList.add('is-open');
        siteHeader.classList.add('menu-open');
        menuButton.setAttribute('aria-expanded', 'true');
        menuButton.setAttribute('aria-label', 'Cerrar menú');
    };

    menuButton.addEventListener('click', () => {
        const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
        isOpen ? closeMenu() : openMenu();
    });

    menuBackdrop?.addEventListener('click', closeMenu);

    mainNav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    // Cerrar al intentar desplazarse fuera del menú.
    document.addEventListener('wheel', (event) => {
        if (
            menuButton.getAttribute('aria-expanded') === 'true' &&
            !mainNav.contains(event.target)
        ) {
            closeMenu();
        }
    }, { passive: true });

    document.addEventListener('touchstart', (event) => {
        if (menuButton.getAttribute('aria-expanded') !== 'true') return;

        touchStartX = event.touches[0].clientX;
        touchStartY = event.touches[0].clientY;
    }, { passive: true });

    document.addEventListener('touchmove', (event) => {
        if (menuButton.getAttribute('aria-expanded') !== 'true') return;

        const touch = event.touches[0];
        const deltaX = Math.abs(touch.clientX - touchStartX);
        const deltaY = Math.abs(touch.clientY - touchStartY);

        if (
            !mainNav.contains(event.target) &&
            (deltaX > 10 || deltaY > 10)
        ) {
            closeMenu();
        }
    }, { passive: true });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }

        if (
            menuButton.getAttribute('aria-expanded') === 'true' &&
            ['ArrowDown', 'ArrowUp', 'PageDown', 'PageUp', ' '].includes(event.key) &&
            !mainNav.contains(document.activeElement)
        ) {
            closeMenu();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 760) {
            closeMenu();
        }
    });
}

// Botón de descarga
const downloadButton = document.querySelector('[data-download-button]');

if (downloadButton) {
    const countdownDuration = Number(
        downloadButton.dataset.downloadCountdown
    ) || 5;

    let countdownStarted = false;
    let countdownFinished = false;

    downloadButton.addEventListener('click', (event) => {
        if (countdownFinished) {
            return;
        }

        event.preventDefault();

        if (countdownStarted) {
            return;
        }

        countdownStarted = true;
        downloadButton.setAttribute('aria-disabled', 'true');

        let remaining = countdownDuration;

        downloadButton.textContent = `Procesando... ${remaining}`;

        const countdown = () => {
            remaining--;

            if (remaining > 0) {
                downloadButton.textContent = `Procesando... ${remaining}`;
                window.setTimeout(countdown, 1000);
                return;
            }

            countdownFinished = true;
            downloadButton.removeAttribute('aria-disabled');
            downloadButton.textContent = 'Descargar ahora';
        };

        window.setTimeout(countdown, 1000);
    });
}

// Consentimiento de cookies
const cookieConsent = document.querySelector('#cookie-consent');
const acceptCookiesButton = document.querySelector('[data-cookie-accept]');
const rejectCookiesButton = document.querySelector('[data-cookie-reject]');
const cookieSettingsButtons = document.querySelectorAll('[data-cookie-settings]');

const COOKIE_CONSENT_KEY = 'foxcraft_cookie_consent';

function getCookieConsent() {
    try {
        return localStorage.getItem(COOKIE_CONSENT_KEY);
    } catch {
        return null;
    }
}

function saveCookieConsent(value) {
    try {
        localStorage.setItem(COOKIE_CONSENT_KEY, value);
    } catch {
        // El consentimiento se mantiene durante esta página.
    }
}

function loadMonetag() {
    const monetagElement = document.querySelector('[data-monetag-zone]');

    if (!monetagElement) {
        return;
    }

    if (document.querySelector('[data-foxcraft-monetag]')) {
        return;
    }

    const script = document.createElement('script');

    script.dataset.zone = monetagElement.dataset.monetagZone;
    script.dataset.foxcraftMonetag = 'true';
    script.src = 'https://al5sm.com/tag.min.js';

    document.body.appendChild(script);
}

function applyCookieConsent(value) {
    if (cookieConsent) {
        cookieConsent.hidden = true;
    }

    if (value === 'accepted') {
        loadMonetag();
        return;
    }

    if (
        value === 'rejected' &&
        document.querySelector('[data-foxcraft-monetag]')
    ) {
        window.location.reload();
    }
}

if (cookieConsent) {
    const savedConsent = getCookieConsent();

    if (savedConsent === 'accepted' || savedConsent === 'rejected') {
        applyCookieConsent(savedConsent);
    } else {
        cookieConsent.hidden = false;
    }
}

if (acceptCookiesButton) {
    acceptCookiesButton.addEventListener('click', () => {
        saveCookieConsent('accepted');
        applyCookieConsent('accepted');
    });
}

if (rejectCookiesButton) {
    rejectCookiesButton.addEventListener('click', () => {
        saveCookieConsent('rejected');
        applyCookieConsent('rejected');
    });
}

if (cookieConsent) {
    cookieSettingsButtons.forEach((button) => {
        button.addEventListener('click', () => {
            cookieConsent.hidden = false;
        });
    });
}

// Vista previa del avatar
const avatarInput = document.querySelector('#avatar');
const avatarPreview = document.querySelector('#avatar-preview');
const avatarPlaceholder = document.querySelector('#avatar-placeholder');

if (avatarInput && avatarPreview && avatarPlaceholder) {
    let currentPreviewUrl = null;

    avatarInput.addEventListener('change', () => {
        const file = avatarInput.files?.[0];

        if (!file) {
            return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!allowedTypes.includes(file.type) || file.size > 2 * 1024 * 1024) {
            avatarInput.value = '';
            return;
        }

        if (currentPreviewUrl) {
            URL.revokeObjectURL(currentPreviewUrl);
        }

        currentPreviewUrl = URL.createObjectURL(file);
        avatarPreview.src = currentPreviewUrl;
        avatarPreview.hidden = false;
        avatarPlaceholder.hidden = true;
    });
}