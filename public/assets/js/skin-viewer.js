'use strict';

(() => {
    const modal = document.querySelector('#skin-viewer-modal');
    const canvas = document.querySelector('#skin-viewer-canvas');
    const title = document.querySelector('#skin-viewer-title');
    const closeButton = document.querySelector('[data-skin-viewer-close]');
    const downloadButton = document.querySelector('#skin-viewer-download');
    const pageButton = document.querySelector('#skin-viewer-page');

    if (
        !modal ||
        !canvas ||
        !title ||
        !closeButton ||
        !downloadButton ||
        !pageButton
    ) {
        return;
    }

    let viewer = null;

    function closeViewer() {
        if (modal.open) {
            modal.close();
        }

        if (viewer) {
            viewer.dispose();
            viewer = null;
        }

        canvas.replaceChildren();
        document.body.style.overflow = '';
    }

    function openViewer(button) {
        const skinName = button.dataset.skinName || 'Skin de Minecraft';
        const textureUrl = button.dataset.skinTexture || '';
        const downloadUrl = button.dataset.skinDownload || '';
        const pageUrl = button.dataset.skinPage || '';

        if (!textureUrl) {
            return;
        }

        title.textContent = skinName;

        downloadButton.href = downloadUrl;
        downloadButton.hidden = !downloadUrl;

        pageButton.href = pageUrl;
        pageButton.hidden = !pageUrl;

        modal.showModal();
        document.body.style.overflow = 'hidden';

        if (typeof skinview3d === 'undefined') {
            closeViewer();
            console.error('No se pudo cargar la biblioteca skinview3d.');
            return;
        }

        try {
            viewer = new skinview3d.SkinViewer({
                canvas,
                width: canvas.clientWidth,
                height: canvas.clientHeight,
                skin: textureUrl
            });

            viewer.controls.enableRotate = true;
            viewer.controls.enableZoom = true;
            viewer.controls.enablePan = false;
            viewer.zoom = 0.9;

            viewer.animation = new skinview3d.IdleAnimation();
        } catch (error) {
            console.error('Error al iniciar el visor 3D:', error);
            closeViewer();
        }
    }

    document.querySelectorAll('[data-skin-viewer-open]').forEach((button) => {
        button.addEventListener('click', () => {
            openViewer(button);
        });
    });

    document.querySelectorAll('[data-skin-card]').forEach((card) => {
        card.addEventListener('click', (event) => {
            if (event.target.closest('a, button')) {
                return;
            }

            openViewer(card);
        });

        card.addEventListener('keydown', (event) => {
            if (event.target !== card || (event.key !== 'Enter' && event.key !== ' ')) {
                return;
            }

            event.preventDefault();
            openViewer(card);
        });
    });

    closeButton.addEventListener('click', closeViewer);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeViewer();
        }
    });

    modal.addEventListener('close', () => {
        if (viewer) {
            viewer.dispose();
            viewer = null;
        }

        document.body.style.overflow = '';
    });

    window.addEventListener('resize', () => {
        if (!viewer || !modal.open) {
            return;
        }

        viewer.setSize(
            canvas.clientWidth,
            canvas.clientHeight
        );
    });
})();
