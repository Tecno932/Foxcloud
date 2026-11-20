'use strict';

(() => {
    const root = document.querySelector('[data-chat]');

    if (!root) return;

    const csrf = root.dataset.csrf;
    const isLoggedIn = root.dataset.authenticated === '1';

    const messagesElement = root.querySelector('[data-chat-messages]');
    const placeholder = root.querySelector('[data-chat-placeholder]');
    const statusElement = root.querySelector('[data-chat-status]');
    const statusDot = root.querySelector('[data-chat-status-dot]');
    const errorElement = root.querySelector('[data-chat-error]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const counter = root.querySelector('[data-chat-counter]');
    const sendButton = root.querySelector('[data-chat-send]');
    const guestInfo = root.querySelector('[data-chat-guest-info]');

    let socket = null;
    let authenticated = false;
    let reconnectTimer = null;
    let reconnectDelay = 1000;
    let manuallyClosed = false;
    let guestRemaining = 20;
    let guestCooldownUntil = 0;
    let guestCooldownTimer = null;

    const MAX_LENGTH = 500;

    function setStatus(status, connected = false) {
        statusElement.textContent = status;
        statusDot.classList.toggle('is-connected', connected);
    }

    function showError(message) {
        errorElement.textContent = message;
        errorElement.hidden = false;
    }

    function clearError() {
        errorElement.textContent = '';
        errorElement.hidden = true;
    }

    function updateGuestStatus(remaining, waitSeconds = 0) {
        if (isLoggedIn || !guestInfo) return;

        guestRemaining = Math.max(0, Number(remaining) || 0);
        guestCooldownUntil = Date.now() + Math.max(0, Number(waitSeconds) || 0) * 1000;

        if (guestCooldownTimer) {
            clearTimeout(guestCooldownTimer);
            guestCooldownTimer = null;
        }

        guestInfo.hidden = false;

        const refresh = () => {
            const seconds = Math.max(
                0,
                Math.ceil((guestCooldownUntil - Date.now()) / 1000)
            );

            guestInfo.textContent = seconds > 0
                ? `Visitante: ${guestRemaining} mensajes disponibles hoy. Podés enviar otro en ${seconds} s.`
                : `Visitante: ${guestRemaining} mensajes disponibles hoy.`;

            updateComposer();

            if (seconds > 0) {
                guestCooldownTimer = setTimeout(refresh, 1000);
            }
        };

        refresh();
    }

    function updateComposer() {
        const length = Array.from(input.value).length;
        const coolingDown = !isLoggedIn && Date.now() < guestCooldownUntil;

        counter.textContent = `${length} / ${MAX_LENGTH}`;

        sendButton.disabled =
            !authenticated ||
            !input.value.trim() ||
            length > MAX_LENGTH ||
            coolingDown ||
            (!isLoggedIn && guestRemaining <= 0);
    }

    function formatDate(value) {
        const date = new Date(value);

        if (Number.isNaN(date.getTime())) return '';

        return date.toLocaleTimeString('es-AR', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function appendMessage(message) {
        if (!message || typeof message.message !== 'string') return;

        placeholder.hidden = true;

        const article = document.createElement('article');
        article.className = 'chat-message';

        const avatar = document.createElement('div');
        avatar.className = 'chat-message-avatar';

        const avatarPath = message.avatarPath;

        if (
            typeof avatarPath === 'string' &&
            (
                (avatarPath.startsWith('/') && !avatarPath.startsWith('//')) ||
                avatarPath.startsWith('https://')
            )
        ) {
            const image = document.createElement('img');
            image.src = avatarPath;
            image.alt = '';
            image.loading = 'lazy';
            avatar.appendChild(image);
        } else {
            avatar.textContent = (message.displayName || message.username || '?')
                .trim()
                .charAt(0)
                .toUpperCase();
        }

        const content = document.createElement('div');
        content.className = 'chat-message-content';

        const header = document.createElement('div');
        header.className = 'chat-message-header';

        const name = document.createElement('strong');
        name.textContent = message.displayName || message.username || 'Usuario';

        const time = document.createElement('time');
        time.textContent = formatDate(message.createdAt);

        header.append(name, time);

        const text = document.createElement('p');
        text.textContent = message.message;

        content.append(header, text);
        article.append(avatar, content);
        messagesElement.appendChild(article);

        messagesElement.scrollTop = messagesElement.scrollHeight;
    }

    async function getToken() {
        const response = await fetch('/?page=chat-token', {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-CSRF-Token': csrf,
                'Accept': 'application/json',
            },
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || typeof data.token !== 'string') {
            throw new Error(data.error || 'No se pudo autenticar el chat.');
        }

        return data.token;
    }

    async function connect() {
        if (manuallyClosed) return;

        clearError();
        authenticated = false;
        updateComposer();
        setStatus('Conectando...');

        try {
            const token = isLoggedIn ? await getToken() : null;

            if (manuallyClosed) return;

            const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';

            socket = new WebSocket(`${protocol}//${location.host}/ws/`);

            socket.addEventListener('open', () => {
                const handshake = { type: 'auth' };

                if (token) handshake.token = token;

                socket.send(JSON.stringify(handshake));
            });

            socket.addEventListener('message', (event) => {
                let data;

                try {
                    data = JSON.parse(event.data);
                } catch {
                    return;
                }

                if (data.type === 'auth_ok') {
                    authenticated = true;
                    reconnectDelay = 1000;
                    setStatus('Conectado', true);
                    clearError();

                    if (!isLoggedIn && data.guest) {
                        updateGuestStatus(data.remaining, data.waitSeconds);
                    }

                    updateComposer();
                    return;
                }

                if (data.type === 'guest_status') {
                    updateGuestStatus(data.remaining, data.waitSeconds);
                    return;
                }

                if (data.type === 'history' && Array.isArray(data.messages)) {
                    messagesElement.replaceChildren();
                    placeholder.hidden = true;

                    if (data.messages.length === 0) {
                        placeholder.textContent = 'Todavía no hay mensajes. Iniciá la conversación.';
                        placeholder.hidden = false;
                    } else {
                        data.messages.forEach(appendMessage);
                    }

                    return;
                }

                if (data.type === 'message') {
                    appendMessage(data.message);
                    return;
                }

                if (data.type === 'error') {
                    showError(data.message || 'Se produjo un error.');

                    if (!isLoggedIn && Number.isFinite(data.remaining)) {
                        updateGuestStatus(data.remaining, data.waitSeconds);
                    }
                }
            });

            socket.addEventListener('close', () => {
                authenticated = false;
                updateComposer();

                if (!manuallyClosed) {
                    setStatus('Desconectado. Reconectando...');
                    scheduleReconnect();
                }
            });

            socket.addEventListener('error', () => {
                setStatus('Error de conexión');
            });
        } catch (error) {
            setStatus('No se pudo conectar');
            showError(error.message);
            scheduleReconnect();
        }
    }

    function scheduleReconnect() {
        if (reconnectTimer || manuallyClosed) return;

        reconnectTimer = setTimeout(() => {
            reconnectTimer = null;
            connect();
        }, reconnectDelay);

        reconnectDelay = Math.min(reconnectDelay * 2, 15000);
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const message = input.value.trim();

        if (
            !authenticated ||
            !socket ||
            socket.readyState !== WebSocket.OPEN ||
            !message ||
            Array.from(message).length > MAX_LENGTH
        ) {
            return;
        }

        if (!isLoggedIn && Date.now() < guestCooldownUntil) return;
        if (!isLoggedIn && guestRemaining <= 0) return;

        socket.send(JSON.stringify({
            type: 'message',
            message,
        }));

        input.value = '';
        updateComposer();
        input.focus();
    });

    input.addEventListener('input', updateComposer);

    window.addEventListener('beforeunload', () => {
        manuallyClosed = true;

        if (reconnectTimer) clearTimeout(reconnectTimer);
        if (guestCooldownTimer) clearTimeout(guestCooldownTimer);

        if (socket) socket.close();
    });

    updateComposer();
    connect();
})();
