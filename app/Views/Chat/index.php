<section class="chat-page" data-chat data-csrf="<?= e($_SESSION['csrf_token']) ?>" data-authenticated="<?= !empty($_SESSION['user_id']) ? '1' : '0' ?>">
    <div class="chat-heading">
        <div>
            <span class="eyebrow">COMUNIDAD FOXCRAFT</span>
            <h1>Chat <span>global</span></h1>
            <p>Conversá con la comunidad de FoxCraft.</p>
        </div>

        <div class="chat-connection">
            <span class="chat-status-dot" data-chat-status-dot></span>
            <span data-chat-status>Conectando...</span>
        </div>
    </div>

    <div class="chat-panel">
        <div class="chat-toolbar">
            <div>
                <strong>Canal global</strong>
                <span>Miembros y visitantes</span>
            </div>
            <span class="chat-online-label">CHAT PÚBLICO</span>
        </div>

        <div
            class="chat-messages"
            data-chat-messages
            role="log"
            aria-live="polite"
            aria-relevant="additions"
            aria-label="Mensajes del chat"
        >
            <div class="chat-placeholder" data-chat-placeholder>
                Cargando mensajes...
            </div>
        </div>

        <div class="chat-error" data-chat-error role="status" hidden></div>
        <div class="chat-guest-info" data-chat-guest-info hidden></div>

        <form class="chat-composer" data-chat-form>
            <label class="visually-hidden" for="chat-message">
                Escribí un mensaje
            </label>

            <textarea
                id="chat-message"
                name="message"
                maxlength="500"
                rows="1"
                placeholder="Escribí un mensaje..."
                required
                data-chat-input
            ></textarea>

            <div class="chat-composer-footer">
                <span data-chat-counter>0 / 500</span>

                <button
                    class="button button-primary"
                    type="submit"
                    data-chat-send
                    disabled
                >
                    Enviar
                </button>
            </div>
        </form>
    </div>

    <p class="chat-guideline">
        Mantené una conversación respetuosa. Los mensajes publicados son visibles
        para todos los usuarios conectados.
    </p>
</section>
