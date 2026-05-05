(function () {
    const CARD_DATA    = window.paymentsData;
    const UNDO_TIMEOUT = 5000; // ms

    let currentIndex = 0;
    let autorizadoCount = 0;
    let rechazadoCount  = 0;

    // Estado de drag
    let isDragging   = false;
    let isAnimating  = false;
    let startX       = 0;
    let currentX     = 0;

    // Estado undo
    let lastAction   = null; // { paymentIndex, cardData, status }
    let undoTimer    = null;
    let undoTimerAnim = null;

    // DOM
    const cardStack      = document.getElementById('cardStack');
    const progressFill   = document.getElementById('progressFill');
    const progressText   = document.getElementById('progressText');
    const swipeButtons   = document.getElementById('swipeButtons');
    const swipeComplete  = document.getElementById('swipeComplete');
    const btnAutorizar   = document.getElementById('btnAutorizar');
    const btnRechazar    = document.getElementById('btnRechazar');
    const btnUndo        = document.getElementById('btnUndo');
    const undoTimerBar   = document.getElementById('undoTimerBar');
    const undoTimerFill  = document.getElementById('undoTimerFill');
    const countAutorizado = document.getElementById('countAutorizado');
    const countRechazado  = document.getElementById('countRechazado');

    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    /* ── Render ── */

    function urgencyLabel(urgency) {
        if (urgency === 'vencido') return '<span class="urgency-badge badge-vencido"><i class="ri-alarm-warning-line"></i> Vencido</span>';
        if (urgency === 'proximo') return '<span class="urgency-badge badge-proximo"><i class="ri-time-line"></i> Próximo a vencer</span>';
        return '<span class="urgency-badge badge-ok"><i class="ri-checkbox-circle-line"></i> Al día</span>';
    }

    function createCard(data) {
        const card = document.createElement('div');
        card.className = `swipe-card urgency-${data.urgency}`;
        card.dataset.paymentId = data.id;
        card.dataset.swipeUrl  = data.swipe_url;

        card.innerHTML = `
            <span class="swipe-indicator indicator-autorizar"><i class="ri-check-double-line me-1"></i>Autorizar</span>
            <span class="swipe-indicator indicator-rechazar"><i class="ri-close-circle-line me-1"></i>Rechazar</span>
            <div class="card-loading-overlay" id="loading-${data.id}">
                <div class="spinner-border text-secondary" role="status" style="width:2rem;height:2rem;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
            <div class="card-inner">
                <div class="card-amount-section">
                    <div class="card-amount-label">Monto a autorizar</div>
                    <div class="card-amount-wrapper">
                        <div class="card-amount">
                            $${data.amount}<span class="card-amount-currency">${data.currency}</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-supplier-block">
                        <div class="card-supplier-label"><i class="ri-building-line"></i> Proveedor</div>
                        <div class="card-supplier">${data.supplier}</div>
                    </div>
                    <div class="card-meta">
                        <div class="card-meta-item">
                            <i class="ri-file-list-3-line"></i>
                            <span>Orden de Compra <strong>#${data.po_id}</strong>${data.po_project ? ' — ' + data.po_project : ''}</span>
                        </div>
                        <div class="card-meta-item">
                            <i class="ri-price-tag-3-line"></i>
                            <span>${data.milestone_type} · ${data.folio}</span>
                        </div>
                        ${data.due_date ? `
                        <div class="card-meta-item">
                            <i class="ri-calendar-event-line"></i>
                            <span>Vence: ${data.due_date} ${urgencyLabel(data.urgency)}</span>
                        </div>` : ''}
                    </div>
                </div>
            </div>
        `;
        return card;
    }

    function renderCards() {
        cardStack.innerHTML = '';
        const slice = CARD_DATA.slice(currentIndex, currentIndex + 3);
        const cards = [];
        for (let i = slice.length - 1; i >= 0; i--) {
            const card = createCard(slice[i]);
            cardStack.appendChild(card);
            cards.push(card);
        }
        cards.forEach(setupMarquee);
        updateProgress();
    }

    function setupMarquee(card) {
        requestAnimationFrame(() => {
            const wrapper  = card.querySelector('.card-amount-wrapper');
            const amountEl = card.querySelector('.card-amount');
            if (!wrapper || !amountEl) return;
            const overflow = amountEl.scrollWidth - wrapper.clientWidth;
            if (overflow > 0) {
                const half = Math.ceil(overflow / 2) + 6;
                amountEl.style.setProperty('--marquee-start', `${half}px`);
                amountEl.style.setProperty('--marquee-end',   `-${half}px`);
                amountEl.classList.add('marquee-active');
            }
        });
    }

    function updateProgress() {
        const pct = (currentIndex / CARD_DATA.length) * 100;
        progressFill.style.width = `${pct}%`;
        progressText.textContent = `${currentIndex} / ${CARD_DATA.length}`;
    }

    function getTopCard() {
        return cardStack.querySelector('.swipe-card:last-child:not(.swiping)');
    }

    function isInsideCard(x, y) {
        const card = getTopCard();
        if (!card) return false;
        const r = card.getBoundingClientRect();
        return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
    }

    /* ── Drag ── */

    document.addEventListener('mousedown', e => {
        if (currentIndex >= CARD_DATA.length || isAnimating) return;
        if (!isInsideCard(e.clientX, e.clientY)) return;
        isDragging = true;
        startX = e.clientX;
        currentX = 0;
        getTopCard()?.classList.add('dragging');
    });

    document.addEventListener('mousemove', e => {
        if (!isDragging || isAnimating) return;
        currentX = e.clientX - startX;
        const card = getTopCard();
        if (!card) return;
        card.style.transform = `translateX(${currentX}px) rotate(${currentX * 0.08}deg)`;
        updateIndicators(card, currentX);
    });

    document.addEventListener('mouseup', () => {
        if (!isDragging) return;
        isDragging = false;
        getTopCard()?.classList.remove('dragging');
        if (!isAnimating) handleSwipeEnd();
    });

    document.addEventListener('touchstart', e => {
        if (currentIndex >= CARD_DATA.length || isAnimating) return;
        const t = e.touches[0];
        if (!isInsideCard(t.clientX, t.clientY)) return;
        e.preventDefault();
        isDragging = true;
        startX = t.clientX;
        currentX = 0;
        getTopCard()?.classList.add('dragging');
    }, { passive: false });

    document.addEventListener('touchmove', e => {
        if (!isDragging || isAnimating) return;
        e.preventDefault();
        const t = e.touches[0];
        currentX = t.clientX - startX;
        const card = getTopCard();
        if (!card) return;
        card.style.transform = `translateX(${currentX}px) rotate(${currentX * 0.08}deg)`;
        updateIndicators(card, currentX);
    }, { passive: false });

    document.addEventListener('touchend', () => {
        if (!isDragging) return;
        isDragging = false;
        getTopCard()?.classList.remove('dragging');
        if (!isAnimating) handleSwipeEnd();
    });

    function updateIndicators(card, dx) {
        const yes = card.querySelector('.indicator-autorizar');
        const no  = card.querySelector('.indicator-rechazar');
        const threshold = 50;
        if (dx > threshold) {
            yes.style.opacity = Math.min((dx - threshold) / 80, 1);
            no.style.opacity  = 0;
        } else if (dx < -threshold) {
            no.style.opacity  = Math.min((-dx - threshold) / 80, 1);
            yes.style.opacity = 0;
        } else {
            yes.style.opacity = 0;
            no.style.opacity  = 0;
        }
    }

    function handleSwipeEnd() {
        const card = getTopCard();
        if (!card) return;
        const threshold = 90;
        if (currentX > threshold) {
            swipeCard('autorizado');
        } else if (currentX < -threshold) {
            swipeCard('rechazado');
        } else {
            // Reset
            card.style.transition = 'transform 0.25s ease';
            card.style.transform  = '';
            card.querySelector('.indicator-autorizar').style.opacity = 0;
            card.querySelector('.indicator-rechazar').style.opacity  = 0;
            setTimeout(() => { if (card) card.style.transition = ''; }, 250);
        }
    }

    /* ── Swipe ── */

    function swipeCard(status) {
        const card = getTopCard();
        if (!card || isAnimating) return;

        hideUndo();

        isAnimating = true;
        card.classList.add('swiping');

        const flyX    = status === 'autorizado' ? 600 : -600;
        const rotation = status === 'autorizado' ? 30 : -30;

        card.style.transition = 'transform 0.3s ease-out, opacity 0.3s ease-out';
        card.style.transform  = `translateX(${flyX}px) rotate(${rotation}deg)`;
        card.style.opacity    = '0';

        // Guardar estado para undo
        const paymentIndex = currentIndex;
        const cardData     = CARD_DATA[currentIndex];

        isDragging = false;
        currentX   = 0;
        currentIndex++;

        if (status === 'autorizado') autorizadoCount++;
        else rechazadoCount++;

        // AJAX
        updatePaymentStatus(cardData.swipe_url, status, cardData.id);

        setTimeout(() => {
            isAnimating = false;
            if (currentIndex >= CARD_DATA.length) {
                showComplete();
            } else {
                renderCards();
            }

            // Mostrar undo después de renderizar
            lastAction = { paymentIndex, cardData, status };
            showUndo();
        }, 320);
    }

    async function updatePaymentStatus(url, status, paymentId) {
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ status }),
            });
            if (!res.ok) throw new Error('Error en servidor');
        } catch (err) {
            console.error('Error al actualizar pago:', err);
        }
    }

    /* ── Undo ── */

    function showUndo() {
        btnUndo.classList.add('visible');
        undoTimerBar.classList.add('visible');
        undoTimerFill.style.transition = 'none';
        undoTimerFill.style.width = '100%';

        // Forzar reflow para que la transición arranque desde 100%
        undoTimerFill.getBoundingClientRect();
        undoTimerFill.style.transition = `width ${UNDO_TIMEOUT}ms linear`;
        undoTimerFill.style.width = '0%';

        clearTimeout(undoTimer);
        undoTimer = setTimeout(hideUndo, UNDO_TIMEOUT);
    }

    function hideUndo() {
        btnUndo.classList.remove('visible');
        undoTimerBar.classList.remove('visible');
        clearTimeout(undoTimer);
        undoTimer = null;
        lastAction = null;
    }

    btnUndo.addEventListener('click', async () => {
        if (!lastAction || isAnimating) return;

        const action = lastAction;
        hideUndo();

        // Revertir contadores
        if (action.status === 'autorizado') autorizadoCount--;
        else rechazadoCount--;

        currentIndex = action.paymentIndex;

        // Revertir en BD sin notificación
        await updatePaymentStatus(action.cardData.swipe_url, 'por_autorizar', action.cardData.id);

        // Si estábamos en pantalla de completado, volver
        swipeComplete.classList.remove('show');
        cardStack.style.display   = '';
        swipeButtons.style.display = '';
        document.querySelector('.undo-container').style.display = '';
        undoTimerBar.style.display = '';
        document.querySelector('.swipe-progress').style.display = '';

        renderCards();
    });

    /* ── Botones ── */

    btnAutorizar.addEventListener('click', () => {
        if (!getTopCard() || isAnimating) return;
        currentX = 0;
        swipeCard('autorizado');
    });

    btnRechazar.addEventListener('click', () => {
        if (!getTopCard() || isAnimating) return;
        currentX = 0;
        swipeCard('rechazado');
    });

    /* ── Completado ── */

    function showComplete() {
        cardStack.style.display    = 'none';
        swipeButtons.style.display = 'none';
        document.querySelector('.undo-container').style.display = 'none';
        undoTimerBar.style.display = 'none';
        document.querySelector('.swipe-progress').style.display = 'none';
        countAutorizado.textContent = autorizadoCount;
        countRechazado.textContent  = rechazadoCount;
        swipeComplete.classList.add('show');
    }

    /* ── Init ── */
    renderCards();
})();