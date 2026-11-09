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


 // Recortador de avatar
const avatarInput = document.querySelector('#avatar');
const avatarPreview = document.querySelector('#avatar-preview');
const avatarPlaceholder = document.querySelector('#avatar-placeholder');

const cropper = document.querySelector('#avatar-cropper');
const cropperImage = document.querySelector('#avatar-cropper-image');
const cropperStage = document.querySelector('#avatar-cropper-stage');
const cropperWorkspace = document.querySelector('#avatar-cropper-workspace');
const cropperLoading = document.querySelector('#avatar-cropper-loading');
const cropperClose = document.querySelector('#avatar-cropper-close');
const cropperCancel = document.querySelector('#avatar-cropper-cancel');
const cropperConfirm = document.querySelector('#avatar-cropper-confirm');
const cropperZoom = document.querySelector('#avatar-zoom');

if (
    avatarInput &&
    avatarPreview &&
    avatarPlaceholder &&
    cropper &&
    cropperImage &&
    cropperStage &&
    cropperWorkspace &&
    cropperLoading &&
    cropperConfirm &&
    cropperZoom
) {
    let sourceUrl = null;
    let previewUrl = null;
    let scale = 1;
    let offsetX = 0;
    let offsetY = 0;
    let baseScale = 1;
    let dragging = false;
    let lastPointerX = 0;
    let lastPointerY = 0;

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    function updateCropImage() {
        const stageWidth = cropperStage.clientWidth;
        const stageHeight = cropperStage.clientHeight;

        const width = cropperImage.naturalWidth * baseScale * scale;
        const height = cropperImage.naturalHeight * baseScale * scale;

        const maxOffsetX = Math.max(0, (width - stageWidth) / 2);
        const maxOffsetY = Math.max(0, (height - stageHeight) / 2);

        offsetX = Math.max(-maxOffsetX, Math.min(maxOffsetX, offsetX));
        offsetY = Math.max(-maxOffsetY, Math.min(maxOffsetY, offsetY));

        cropperImage.style.width = `${width}px`;
        cropperImage.style.height = `${height}px`;
        cropperImage.style.left = `${(stageWidth - width) / 2 + offsetX}px`;
        cropperImage.style.top = `${(stageHeight - height) / 2 + offsetY}px`;
    }

function resetCropper(clearInput = true) {
    cropper.hidden = true;
    cropperImage.removeAttribute('src');
    cropperWorkspace.hidden = true;
    cropperLoading.hidden = true;
    cropperConfirm.disabled = false;

    if (sourceUrl) {
        URL.revokeObjectURL(sourceUrl);
        sourceUrl = null;
    }

    if (clearInput) {
        avatarInput.value = '';
    }
}

function openCropper(file) {
    cropper.hidden = false;
    cropperLoading.hidden = false;
    cropperWorkspace.hidden = true;
    cropperConfirm.disabled = true;

    if (sourceUrl) {
        URL.revokeObjectURL(sourceUrl);
    }

    sourceUrl = URL.createObjectURL(file);

    cropperImage.onload = () => {
        // Mostrar el espacio antes de medir sus dimensiones.
        cropperLoading.hidden = true;
        cropperWorkspace.hidden = false;

        requestAnimationFrame(() => {
            const stageWidth = cropperStage.getBoundingClientRect().width;
            const imageWidth = cropperImage.naturalWidth;
            const imageHeight = cropperImage.naturalHeight;

            if (!stageWidth || !imageWidth || !imageHeight) {
                resetCropper();
                alert('No se pudo calcular el recorte.');
                return;
            }

            baseScale = Math.max(
                stageWidth / imageWidth,
                stageWidth / imageHeight
            );

            scale = 1;
            offsetX = 0;
            offsetY = 0;
            cropperZoom.value = '1';

            updateCropImage();

            cropperConfirm.disabled = false;
        });
    };

    cropperImage.onerror = () => {
        resetCropper();
        alert('No se pudo cargar la imagen.');
    };

    cropperImage.src = sourceUrl;
}

    avatarInput.addEventListener('change', () => {
        const file = avatarInput.files?.[0];

        if (!file) return;

        if (!allowedTypes.includes(file.type) || file.size > 2 * 1024 * 1024) {
            avatarInput.value = '';
            alert('Seleccioná una imagen JPG, PNG o WebP de hasta 2 MB.');
            return;
        }

        openCropper(file);
    });

    cropperZoom.addEventListener('input', () => {
        scale = Number(cropperZoom.value);
        updateCropImage();
    });

    cropperStage.addEventListener('pointerdown', (event) => {
        if (cropperWorkspace.hidden) return;

        dragging = true;
        lastPointerX = event.clientX;
        lastPointerY = event.clientY;
        cropperStage.setPointerCapture(event.pointerId);
    });

    cropperStage.addEventListener('pointermove', (event) => {
        if (!dragging) return;

        offsetX += event.clientX - lastPointerX;
        offsetY += event.clientY - lastPointerY;

        lastPointerX = event.clientX;
        lastPointerY = event.clientY;

        updateCropImage();
    });

    cropperStage.addEventListener('pointerup', () => {
        dragging = false;
    });

    cropperStage.addEventListener('pointercancel', () => {
        dragging = false;
    });

    cropperConfirm.addEventListener('click', () => {
        if (!cropperImage.naturalWidth || cropperWorkspace.hidden) return;

        cropperConfirm.disabled = true;
        cropperConfirm.textContent = 'Procesando...';

        const stageWidth = cropperStage.clientWidth;
        const renderedWidth = cropperImage.clientWidth;
        const renderedHeight = cropperImage.clientHeight;
        const imageLeft = Number.parseFloat(cropperImage.style.left);
        const imageTop = Number.parseFloat(cropperImage.style.top);

        const sourceX = Math.max(0, (0 - imageLeft) / renderedWidth * cropperImage.naturalWidth);
        const sourceY = Math.max(0, (0 - imageTop) / renderedHeight * cropperImage.naturalHeight);
        const sourceWidth = Math.min(
            stageWidth / renderedWidth * cropperImage.naturalWidth,
            cropperImage.naturalWidth - sourceX
        );
        const sourceHeight = Math.min(
            stageWidth / renderedHeight * cropperImage.naturalHeight,
            cropperImage.naturalHeight - sourceY
        );

        const canvas = document.createElement('canvas');
        canvas.width = 512;
        canvas.height = 512;

        const context = canvas.getContext('2d');

        if (!context) {
            cropperConfirm.disabled = false;
            cropperConfirm.textContent = 'Confirmar recorte';
            alert('No se pudo procesar la imagen.');
            return;
        }

        context.drawImage(
            cropperImage,
            sourceX,
            sourceY,
            sourceWidth,
            sourceHeight,
            0,
            0,
            512,
            512
        );

        canvas.toBlob((blob) => {
            if (!blob) {
                cropperConfirm.disabled = false;
                cropperConfirm.textContent = 'Confirmar recorte';
                alert('No se pudo generar la imagen recortada.');
                return;
            }

            const croppedFile = new File(
                [blob],
                'avatar.jpg',
                { type: 'image/jpeg' }
            );

            const transfer = new DataTransfer();
            transfer.items.add(croppedFile);
            avatarInput.files = transfer.files;

            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }

            previewUrl = URL.createObjectURL(blob);
            avatarPreview.src = previewUrl;
            avatarPreview.hidden = false;
            avatarPlaceholder.hidden = true;

            cropperConfirm.textContent = 'Confirmar recorte';
            resetCropper(false);
        }, 'image/jpeg', 0.90);
    });

    cropperClose?.addEventListener('click', resetCropper);
    cropperCancel?.addEventListener('click', resetCropper);

    cropper.addEventListener('click', (event) => {
        if (event.target === cropper) {
            resetCropper();
        }
    });
}

// Mostrar u ocultar contraseñas
document.querySelectorAll('.password-toggle').forEach((button) => {
    const input = document.getElementById(button.dataset.target);

    if (!input) return;

    const eye = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
    `;

    const eyeOff = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m3 3 18 18"/>
            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
            <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-3 3.8"/>
            <path d="M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10.8 10.8 0 0 0 3.1-.5"/>
        </svg>
    `;

    button.innerHTML = eye;
    button.setAttribute('aria-label', 'Mostrar contraseña');
    button.setAttribute('aria-pressed', 'false');

    button.addEventListener('click', () => {
        const visible = input.type === 'password';

        input.type = visible ? 'text' : 'password';
        button.innerHTML = visible ? eyeOff : eye;

        button.setAttribute(
            'aria-label',
            visible ? 'Ocultar contraseña' : 'Mostrar contraseña'
        );

        button.setAttribute('aria-pressed', String(visible));
    });
});

// Listas de seguidores y seguidos
document.querySelectorAll('[data-social-dialog]').forEach((dialog) => {
    const title = dialog.querySelector('[data-social-dialog-title]');
    const closeButton = dialog.querySelector('[data-social-close]');
    const panels = dialog.querySelectorAll('[data-social-panel]');

    document.querySelectorAll('[data-social-list]').forEach((button) => {
        button.addEventListener('click', () => {
            const listType = button.dataset.socialList;

            const panel = dialog.querySelector(
                `[data-social-panel="${listType}"]`
            );

            if (!panel) return;

            panels.forEach((item) => {
                item.hidden = item !== panel;
            });

            title.textContent = listType === 'followers'
                ? 'Seguidores'
                : 'Seguidos';

            if (!dialog.open) {
                dialog.showModal();
            }
        });
    });

    closeButton?.addEventListener('click', () => {
        dialog.close();
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
});