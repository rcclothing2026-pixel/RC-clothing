import '@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css';
import '@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
Alpine.plugin(collapse);
window.Alpine = Alpine;

// Product image lightbox factory — used by resources/views/product/show.blade.php
// via x-data="productLightbox([...])". Tracks active index, swipe gestures,
// and locks page scroll while open.
window.productLightbox = function (images) {
    return {
        images: images || [],
        index: 0,
        visible: false,
        _tx: 0,
        open(i) {
            if (!this.images.length) return;
            this.index = Math.max(0, Math.min(this.images.length - 1, i || 0));
            this.visible = true;
            document.documentElement.style.overflow = 'hidden';
        },
        close() {
            this.visible = false;
            document.documentElement.style.overflow = '';
        },
        // RTL convention: visual «next» button advances toward index+1.
        next() { if (this.images.length > 1) this.index = (this.index + 1) % this.images.length; },
        prev() { if (this.images.length > 1) this.index = (this.index - 1 + this.images.length) % this.images.length; },
        touchStart(e) { this._tx = e.changedTouches[0].clientX; },
        touchEnd(e) {
            const dx = e.changedTouches[0].clientX - this._tx;
            if (Math.abs(dx) < 40) return;
            // In RTL, a finger-flick to the right means "older / previous".
            (dx > 0 ? this.prev : this.next).call(this);
        },
    };
};

// Mini-cart drawer factory + Persian-digit helper. Defined HERE (not in the
// partial's @push('head')) so it's guaranteed present before Alpine.start():
// a partial @include'd in the layout body pushes to 'head' AFTER the head
// @stack has already rendered, so window.miniCart came out undefined and every
// `state.*` binding in the drawer threw "state is not defined".
// Numeral passthrough — Racket Club is an English (LTR) storefront, so digits
// stay Latin. Kept as a named helper so the mini-cart x-text bindings work
// unchanged.
window.__chiiacoFa = function (s) {
    return String(s ?? '');
};
window.miniCart = function () {
    return {
        visible: false,
        state: { count: 0, subtotal: '', cart_url: '', checkout_url: '', items: [], just_added: null },
        toPersianDigits: window.__chiiacoFa,
        open(payload) {
            if (payload) this.state = Object.assign({}, this.state, payload);
            this.visible = true;
            document.documentElement.style.overflow = 'hidden';
        },
        close() {
            this.visible = false;
            document.documentElement.style.overflow = '';
        },
    };
};

// Intercept every cart-add form: POST via fetch and open the mini-cart drawer
// on success; fall back to a real submit on network failure (the no-JS path).
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const action = form.getAttribute('action') || '';
    if (!/\/cart\/add(\?|$)/.test(action)) return;
    if (form.dataset.bypassMiniCart === '1') return;

    e.preventDefault();
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    const prevDisabled = btn ? btn.disabled : false;
    if (btn) btn.disabled = true;

    const fd = new FormData(form);
    fetch(action, {
        method: 'POST',
        body: fd,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
    .then(r => r.json().then(d => ({ ok: r.ok, body: d })))
    .then(({ ok, body }) => {
        if (!ok || !body.ok) {
            if (btn) btn.disabled = prevDisabled;
            alert(body && body.message ? body.message : 'Could not add to bag.');
            return;
        }
        document.querySelectorAll('[data-cart-count]').forEach(el => {
            el.textContent = window.__chiiacoFa(body.cart.count);
            el.classList.toggle('hidden', !body.cart.count);
        });
        window.dispatchEvent(new CustomEvent('open-mini-cart', { detail: body.cart }));
        if (btn) btn.disabled = prevDisabled;
    })
    .catch(() => {
        form.dataset.bypassMiniCart = '1';
        form.submit();
    });
});

Alpine.start();

/* ---------------------------------------------------------------------------
   Marquee scrollers — used by category_tiles and collection_scroller when
   the editor picks the «اسلایدر بی‌نهایت» layout. Auto-advances at the
   chosen speed, but the user can drag, swipe, wheel, or use prev/next
   buttons to scroll manually; auto-scroll pauses during interaction and
   resumes after a short cooldown. Items are duplicated 2× in the markup
   so the wrap is seamless — we jump back by half the scrollWidth when
   we've passed the first set.
--------------------------------------------------------------------------- */
function initMarquees() {
    const isRtl = document.documentElement.dir === 'rtl';
    const direction = isRtl ? -1 : 1; // visual «next» = scrollLeft++ in LTR, -- in RTL

    document.querySelectorAll('[data-chiiaco-marquee]').forEach((el) => {
        if (el.dataset.chiiacoMarqueeInit === '1') return;
        el.dataset.chiiacoMarqueeInit = '1';

        const inner = el.firstElementChild;
        if (!inner) return;

        // Speed comes from data-speed-px in pixels per second.
        const speed = parseFloat(el.dataset.speedPx || '60');
        // Manual-scroll mode (carousels): no rAF auto-advance, no wrap.
        // Buttons + drag still wired. Set data-autoplay="0" to enable.
        const autoplay = el.dataset.autoplay !== '0';
        let pausedUntil = 0;
        let last = performance.now();
        let raf;

        // Position scrollLeft into the «first set» so we have room to wrap
        // both directions from the user's manual scroll.
        const wrapWindow = () => {
            const half = inner.scrollWidth / 2;
            if (!half) return;
            if (isRtl) {
                // RTL: scrollLeft is 0 or negative depending on browser.
                if (el.scrollLeft <= -half) el.scrollLeft += half;
                else if (el.scrollLeft >= 0) el.scrollLeft -= half;
            } else {
                if (el.scrollLeft >= half) el.scrollLeft -= half;
                else if (el.scrollLeft <= 0) el.scrollLeft += half;
            }
        };

        const tick = (now) => {
            const dt = now - last;
            last = now;
            if (now > pausedUntil) {
                el.scrollLeft += (speed * dt / 1000) * direction;
                wrapWindow();
            }
            raf = requestAnimationFrame(tick);
        };

        const pause = (ms) => { pausedUntil = performance.now() + ms; };

        // Pointer / touch / wheel interactions pause the auto-scroll for a
        // brief cooldown. Hover (desktop) holds longer — until they move off.
        el.addEventListener('mouseenter', () => pause(120_000));
        el.addEventListener('mouseleave', () => { pausedUntil = 0; });
        el.addEventListener('touchstart', () => pause(3_000), { passive: true });
        el.addEventListener('wheel', () => pause(3_000), { passive: true });
        el.addEventListener('pointerdown', () => pause(3_000));

        // Prev / Next button hooks (the Blade renders them as siblings of
        // the scroller, with data-marquee-prev / -next pointing at it via id).
        const id = el.id;
        if (id) {
            document.querySelectorAll(`[data-marquee-prev="${id}"]`).forEach((btn) => {
                btn.addEventListener('click', () => {
                    pause(4_000);
                    el.scrollBy({ left: -el.clientWidth * 0.7 * direction, behavior: 'smooth' });
                });
            });
            document.querySelectorAll(`[data-marquee-next="${id}"]`).forEach((btn) => {
                btn.addEventListener('click', () => {
                    pause(4_000);
                    el.scrollBy({ left: el.clientWidth * 0.7 * direction, behavior: 'smooth' });
                });
            });
        }

        // Drag-to-scroll on desktop (touch already handles this natively).
        let dragStartX = 0, dragStartScroll = 0, dragging = false;
        el.addEventListener('mousedown', (e) => {
            dragging = true; dragStartX = e.clientX; dragStartScroll = el.scrollLeft;
            el.style.cursor = 'grabbing';
        });
        window.addEventListener('mouseup', () => { dragging = false; el.style.cursor = ''; });
        window.addEventListener('mousemove', (e) => {
            if (!dragging) return;
            el.scrollLeft = dragStartScroll - (e.clientX - dragStartX);
            pause(3_000);
        });

        if (autoplay) raf = requestAnimationFrame(tick);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMarquees);
} else {
    initMarquees();
}

/* ---------------------------------------------------------------------------
   Transparent header overlay — when the first content block on the page is
   an editorial_hero (data-editorial-hero), the header floats over it with
   white text. Scrolling past the hero brings the header back to its solid
   default. Pages without a hero-as-first-block render the header normally.
--------------------------------------------------------------------------- */
function initTransparentHeader() {
    const header = document.querySelector('[data-header]');
    if (!header) return;
    // Find the first editorial hero in the document and only activate when
    // it sits at (or near) the very top of the page — i.e. is the first
    // visible block. A hero deeper in the page should leave the header alone.
    const hero = document.querySelector('[data-editorial-hero]');
    if (!hero) return;
    const heroTop = hero.getBoundingClientRect().top + window.scrollY;
    if (heroTop > header.offsetHeight + 8) return;

    const update = () => {
        const heroBottom = hero.getBoundingClientRect().bottom;
        // Header switches to solid when its bottom edge has crossed the
        // hero's bottom edge — i.e. it's about to overlap the next section.
        if (heroBottom > header.offsetHeight) {
            header.classList.add('header-over-hero');
        } else {
            header.classList.remove('header-over-hero');
        }
    };
    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTransparentHeader);
} else {
    initTransparentHeader();
}

/* ---------------------------------------------------------------------------
   Jalali (Persian) date picker — attaches to any input with `data-jdp`.
   The whole site uses the Persian calendar; inputs submit a Jalali string the
   server converts to Gregorian for storage.
--------------------------------------------------------------------------- */
function initJalaliDatepicker() {
    if (!window.jalaliDatepicker) return;
    // Date-only inputs (the default for forms across the admin).
    if (document.querySelector('[data-jdp]:not([data-jdp-time="true"])')) {
        window.jalaliDatepicker.startWatch({
            time: false,
            persianDigit: true,
            autoShow: false,
            hideAfterChange: true,
            showTodayBtn: true,
            showEmptyBtn: true,
            selector: '[data-jdp]:not([data-jdp-time="true"])',
        });
    }
    // Date+time inputs — opt-in via data-jdp-time="true" (countdown fields).
    if (document.querySelector('[data-jdp][data-jdp-time="true"]')) {
        window.jalaliDatepicker.startWatch({
            time: true,
            persianDigit: true,
            autoShow: false,
            hideAfterChange: true,
            showTodayBtn: true,
            showEmptyBtn: true,
            selector: '[data-jdp][data-jdp-time="true"]',
        });
    }
}

/* ---------------------------------------------------------------------------
   Scroll-reveal: fade/slide elements in as they enter the viewport.
--------------------------------------------------------------------------- */
function initReveal() {
    const els = document.querySelectorAll('.reveal');
    if (!els.length) return;

    if (!('IntersectionObserver' in window)) {
        els.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );

    els.forEach((el) => io.observe(el));
}

/* ---------------------------------------------------------------------------
   Product page: gallery thumbnails + size/color selection.
--------------------------------------------------------------------------- */
function initProduct() {
    const root = document.querySelector('[data-product]');
    if (!root) return;

    // Gallery thumbnails — tracks the active index for the lightbox.
    const main = root.querySelector('[data-main-image]');
    let activeIndex = 0;
    root.querySelectorAll('[data-thumb]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (main) main.src = btn.dataset.thumb;
            activeIndex = parseInt(btn.dataset.thumbIndex || '0', 10);
            root.querySelectorAll('[data-thumb]').forEach((b) =>
                b.classList.remove('ring-2', 'ring-brand-800')
            );
            btn.classList.add('ring-2', 'ring-brand-800');
        });
    });

    // Click the main image (or its wrapper) → open the lightbox at the
    // currently-selected gallery index.
    const trigger = root.querySelector('[data-lightbox-trigger]');
    if (trigger) {
        trigger.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('open-lightbox', { detail: { index: activeIndex } }));
        });
    }

    // Variant catalogue for resolving color+size -> variant id.
    let variants = [];
    try {
        variants = JSON.parse(root.dataset.variants || '[]');
    } catch (e) {
        variants = [];
    }

    // Single-select groups (color, size)
    const selection = { color: null, size: null };
    const note = root.querySelector('[data-cart-note]');
    const variantInput = root.querySelector('[data-variant-id]');
    const addBtn = root.querySelector('[data-add-to-cart]');

    const needsSize = !!root.querySelector('[data-sizes]');
    const needsColor = !!root.querySelector('[data-colors]');

    function resolveVariant() {
        return variants.find(
            (v) =>
                (!needsColor || v.color === selection.color) &&
                (!needsSize || v.size === selection.size)
        );
    }

    const bisForm = root.querySelector('#back-in-stock-form');
    const bisInput = root.querySelector('#notify-variant-id');

    function markUnavailable() {
        // Visually flag out-of-stock options up-front (before any click), so the
        // shopper sees what's unavailable. Kept clickable so the back-in-stock
        // form still works for the chosen combination.
        const sizeWrap = root.querySelector('[data-sizes]');
        if (sizeWrap) {
            sizeWrap.querySelectorAll('[data-size]').forEach((btn) => {
                const sz = btn.dataset.size;
                // Available if some in-stock variant offers this size — for the
                // chosen colour, or across all colours when none is chosen yet.
                const available = variants.some((v) =>
                    v.size === sz &&
                    (selection.color ? v.color === selection.color : true) &&
                    v.stock > 0
                );
                btn.classList.toggle('opacity-40', !available);
                btn.classList.toggle('line-through', !available);
            });
        }
        const colorWrap = root.querySelector('[data-colors]');
        if (colorWrap) {
            colorWrap.querySelectorAll('[data-color]').forEach((btn) => {
                const col = btn.dataset.color;
                const available = variants.some((v) => v.color === col && v.stock > 0);
                btn.classList.toggle('opacity-40', !available);
                btn.classList.toggle('line-through', !available);
            });
        }
    }

    function refresh() {
        const ready =
            (!needsColor || selection.color) && (!needsSize || selection.size);
        markUnavailable();
        if (!ready) {
            if (variantInput) variantInput.value = '';
            if (bisForm) bisForm.classList.add('hidden');
            return;
        }
        const variant = resolveVariant();
        if (!variant || variant.stock <= 0) {
            if (variantInput) variantInput.value = '';
            if (addBtn) addBtn.disabled = true;
            if (note) {
                note.textContent = 'This colour and size combination is unavailable.';
                note.classList.remove('hidden');
            }
            // Show back-in-stock form for the specific out-of-stock variant
            if (bisForm && bisInput && variant) {
                bisInput.value = variant.id;
                bisForm.classList.remove('hidden');
            }
            return;
        }
        if (variantInput) variantInput.value = variant.id;
        if (addBtn) addBtn.disabled = false;
        if (note) note.classList.add('hidden');
        if (bisForm) bisForm.classList.add('hidden');

        // Stock urgency
        const ind = root.querySelector('#stock-indicator');
        const msg = root.querySelector('#stock-msg span');
        if (ind && msg) {
            if (variant.stock > 0 && variant.stock <= 5) {
                msg.textContent = 'Only ' + variant.stock + ' left in stock';
                ind.classList.remove('hidden');
            } else {
                ind.classList.add('hidden');
            }
        }
    }

    [
        { wrap: '[data-colors]', attr: 'color' },
        { wrap: '[data-sizes]', attr: 'size' },
    ].forEach(({ wrap, attr }) => {
        const container = root.querySelector(wrap);
        if (!container) return;
        container.querySelectorAll(`[data-${attr}]`).forEach((btn) => {
            btn.addEventListener('click', () => {
                container
                    .querySelectorAll(`[data-${attr}]`)
                    .forEach((b) => b.removeAttribute('data-active'));
                btn.setAttribute('data-active', 'true');
                selection[attr] = btn.dataset[attr];
                refresh();
            });
        });
    });

    // Block submit until a valid in-stock variant is chosen.
    root.querySelector('[data-cart-form]')?.addEventListener('submit', (e) => {
        if (!variantInput || !variantInput.value) {
            e.preventDefault();
            if (note) {
                note.textContent = 'Please select a colour and size.';
                note.classList.remove('hidden');
            }
        }
    });

    // Mobile sticky add-to-cart bar
    const stickyBar = document.getElementById('sticky-atc');
    const stickyBtn = document.getElementById('sticky-atc-btn');
    const stickyLabel = document.getElementById('sticky-variant-label');
    if (stickyBar && stickyBtn) {
        const cartForm = root.querySelector('[data-cart-form]');
        // Show sticky bar on mobile (slide up) if product has variants or is in stock
        if (needsColor || needsSize) {
            requestAnimationFrame(() => stickyBar.classList.remove('translate-y-full'));
        }
        // Sync sticky button state with main add-to-cart
        const origRefresh = refresh;
        refresh = function () {
            origRefresh.apply(this, arguments);
            const ready =
                (!needsColor || selection.color) && (!needsSize || selection.size);
            if (ready) {
                const variant = resolveVariant();
                if (variant && variant.stock > 0) {
                    stickyBtn.disabled = false;
                    if (stickyLabel) stickyLabel.textContent =
                        [selection.color, selection.size].filter(Boolean).join(' — ');
                } else {
                    stickyBtn.disabled = true;
                    if (stickyLabel) stickyLabel.textContent = 'Sold Out';
                }
            } else {
                stickyBtn.disabled = true;
                if (stickyLabel) stickyLabel.textContent = 'Please select a colour and size';
            }
        };
        // Sticky ATC triggers the real cart form
        const qtyInput = cartForm?.querySelector('#qty-input');
        stickyBtn.addEventListener('click', () => {
            if (!variantInput || !variantInput.value) {
                if (note) {
                    note.textContent = 'Please select a colour and size.';
                    note.classList.remove('hidden');
                }
                return;
            }
            const stickyDisplay = document.getElementById('sticky-qty-display');
            if (stickyDisplay && qtyInput) {
                const persianToNum = (s) => parseInt(s.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)), 10);
                qtyInput.value = persianToNum(stickyDisplay.textContent);
            }
            cartForm?.requestSubmit();
        });
    }

    // Auto-select any option group that has a single choice (one-size or
    // single-colour products), so the shopper doesn't have to click the only
    // option before adding to cart.
    [['[data-colors]', 'color'], ['[data-sizes]', 'size']].forEach(([wrap, attr]) => {
        const container = root.querySelector(wrap);
        if (!container) return;
        const btns = container.querySelectorAll(`[data-${attr}]`);
        if (btns.length === 1) {
            btns[0].setAttribute('data-active', 'true');
            selection[attr] = btns[0].dataset[attr];
        }
    });

    // Initial paint: flag out-of-stock options and resolve any auto-selection.
    refresh();
}

/* ---------------------------------------------------------------------------
   Motion banner: auto-rotating blob hero (page-builder block). Cross-fades
   slides, supports dots, swipe gestures, and pauses on hover.
--------------------------------------------------------------------------- */
function initMotionBanners() {
    document.querySelectorAll('.motion-banner').forEach((banner) => {
        const slides = [...banner.querySelectorAll('[data-slide]')];
        if (slides.length < 2) return;

        const dots = [...banner.querySelectorAll('[data-dot]')];
        const interval = Math.max(2000, parseInt(banner.dataset.interval, 10) || 5000);
        let idx = 0;
        let timer = null;

        function show(n) {
            slides.forEach((s, i) => {
                s.style.opacity = i === n ? '1' : '0';
                s.style.pointerEvents = i === n ? '' : 'none';
            });
            dots.forEach((d, i) => {
                d.classList.toggle('bg-brand-900', i === n);
                d.classList.toggle('bg-brand-300', i !== n);
            });
            idx = n;
        }
        const next = () => show((idx + 1) % slides.length);
        const prev = () => show((idx - 1 + slides.length) % slides.length);
        const start = () => { timer = setInterval(next, interval); };
        const stop = () => { clearInterval(timer); timer = null; };

        banner.addEventListener('mouseenter', stop);
        banner.addEventListener('mouseleave', start);
        dots.forEach((d, i) => d.addEventListener('click', () => { stop(); show(i); start(); }));

        // Touch swipe support for mobile
        let touchX = 0;
        banner.addEventListener('touchstart', (e) => { touchX = e.touches[0].clientX; }, { passive: true });
        banner.addEventListener('touchend', (e) => {
            const dx = e.changedTouches[0].clientX - touchX;
            if (Math.abs(dx) < 40) return;
            stop();
            // RTL: swipe left = next slide, swipe right = prev slide
            if (dx < 0) next(); else prev();
            start();
        }, { passive: true });

        show(0);
        start();
    });
}

/* ---------------------------------------------------------------------------
   Pop-ups: admin-built modals shown by trigger (load/delay/exit/scroll) and
   gated by frequency (always/session/daily/once) via web storage.
--------------------------------------------------------------------------- */
function initPopups() {
    const el = document.getElementById('popups-data');
    if (!el) return;

    let popups = [];
    try { popups = JSON.parse(el.textContent || '[]'); } catch (e) { return; }
    const isHome = el.dataset.isHome === '1';
    popups = popups.filter((p) => p.pages === 'all' || (p.pages === 'home' && isHome));

    const key = (id) => 'chiaco_popup_' + id;
    const eligible = (p) => {
        if (p.frequency === 'always') return true;
        if (p.frequency === 'session') return sessionStorage.getItem(key(p.id)) == null;
        const v = localStorage.getItem(key(p.id));
        if (p.frequency === 'daily') return !v || Date.now() - parseInt(v, 10) > 86400000;
        if (p.frequency === 'once') return v == null;
        return true;
    };

    const p = popups.find(eligible);
    if (!p) return;

    const markSeen = () => {
        if (p.frequency === 'session') sessionStorage.setItem(key(p.id), '1');
        else localStorage.setItem(key(p.id), String(Date.now()));
    };

    function show() {
        const overlay = document.createElement('div');
        overlay.className = 'popup-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:90;display:grid;place-items:center;background:rgba(40,40,40,.55);padding:16px;';
        const card = document.createElement('div');
        card.style.cssText = 'position:relative;max-width:480px;width:100%;background:#fff;border-radius:1rem;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;';

        const closeBtn = '<button data-popup-close aria-label="Close" style="position:absolute;top:8px;left:8px;z-index:2;border:0;background:#f1ecee;width:32px;height:32px;border-radius:9999px;cursor:pointer;font-size:18px;line-height:1;">×</button>';

        // A full HTML document (with its own <style>/<head>) can't render via
        // innerHTML — the parser drops <head> + the styles. Render it in an
        // <iframe srcdoc> so it displays exactly as designed, auto-sized.
        if (/<\s*(!doctype|html|head|body|style)\b/i.test(p.html)) {
            card.style.background = 'transparent';
            card.style.boxShadow = 'none';
            card.style.maxWidth = '540px';
            card.innerHTML = closeBtn;
            const frame = document.createElement('iframe');
            frame.setAttribute('scrolling', 'no');
            frame.style.cssText = 'width:100%;height:600px;border:0;display:block;background:transparent;';
            frame.srcdoc = p.html;
            frame.addEventListener('load', () => {
                try {
                    const doc = frame.contentDocument;
                    const h = Math.max(doc.documentElement.scrollHeight, doc.body.scrollHeight);
                    if (h) frame.style.height = (h + 2) + 'px';
                } catch (e) { /* keep fallback height */ }
            });
            card.appendChild(frame);
        } else {
            card.innerHTML = closeBtn + '<div style="padding:8px">' + p.html + '</div>';
        }
        overlay.appendChild(card);
        document.body.appendChild(overlay);
        markSeen();

        const close = () => { overlay.remove(); window.removeEventListener('message', onMsg); };
        // Let a button inside an iframe popup close the overlay via
        // parent.postMessage('chiaco-popup-close','*').
        function onMsg(e) { if (e && e.data === 'chiaco-popup-close') close(); }
        window.addEventListener('message', onMsg);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('[data-popup-close]')) close();
        });
        document.addEventListener('keydown', function esc(e) {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
        });
    }

    if (p.trigger === 'delay') {
        setTimeout(show, Math.max(0, p.delay || 3) * 1000);
    } else if (p.trigger === 'scroll') {
        const onScroll = () => {
            if ((window.scrollY + window.innerHeight) / document.body.scrollHeight > 0.5) {
                window.removeEventListener('scroll', onScroll);
                show();
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
    } else if (p.trigger === 'exit') {
        const onLeave = (e) => {
            if (e.clientY <= 0) { document.removeEventListener('mouseout', onLeave); show(); }
        };
        document.addEventListener('mouseout', onLeave);
    } else {
        show(); // load
    }
}

/* ---------------------------------------------------------------------------
   Product page: quantity stepper (+/- buttons → #qty-input hidden field).
   Also syncs mobile sticky stepper if present.
--------------------------------------------------------------------------- */
function initQuantityStepper() {
    const minus = document.getElementById('qty-minus');
    const plus = document.getElementById('qty-plus');
    const display = document.getElementById('qty-display');
    const input = document.getElementById('qty-input');
    if (!minus || !plus || !display || !input) return;

    const stickyMinus = document.getElementById('sticky-qty-minus');
    const stickyPlus = document.getElementById('sticky-qty-plus');
    const stickyDisplay = document.getElementById('sticky-qty-display');

    const fmtQty = (n) => String(n);

    let qty = 1;
    const update = () => {
        const txt = fmtQty(qty);
        display.textContent = txt;
        input.value = qty;
        minus.disabled = qty <= 1;
        if (stickyDisplay) stickyDisplay.textContent = txt;
        if (stickyMinus) stickyMinus.disabled = qty <= 1;
    };

    const adjust = (delta) => {
        const next = qty + delta;
        if (next >= 1 && next <= 20) { qty = next; update(); }
    };

    minus.addEventListener('click', () => adjust(-1));
    plus.addEventListener('click', () => adjust(1));
    if (stickyMinus) stickyMinus.addEventListener('click', () => adjust(-1));
    if (stickyPlus) stickyPlus.addEventListener('click', () => adjust(1));
    update();
}

/* ---------------------------------------------------------------------------
   Cart page: +/- stepper buttons submit the quantity update form.
   Optimistically updates the line total display before the page reloads.
--------------------------------------------------------------------------- */
function formatToman(toman) {
    return Math.round(toman).toLocaleString('en-US') + ' Toman';
}

function initCartSteppers() {
    document.querySelectorAll('[data-cart-stepper]').forEach((stepper) => {
        const form = stepper.closest('form');
        const input = stepper.querySelector('input[name="quantity"]');
        if (!form || !input) return;

        const unitPrice = parseInt(form.dataset.unitPrice || '0', 10);
        const lineTotalEl = form.parentElement?.querySelector('[data-line-total]');

        let debounceTimer = null;

        const submitDebounced = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => form.submit(), 300);
        };

        const updateDisplay = (v) => {
            input.value = v;
            if (lineTotalEl && unitPrice) {
                lineTotalEl.textContent = formatToman(v * unitPrice);
                lineTotalEl.classList.add('opacity-60');
            }
        };

        stepper.querySelector('[data-step="-1"]')?.addEventListener('click', () => {
            updateDisplay(Math.max(0, parseInt(input.value, 10) - 1));
            submitDebounced();
        });
        stepper.querySelector('[data-step="1"]')?.addEventListener('click', () => {
            updateDisplay(Math.min(20, parseInt(input.value, 10) + 1));
            submitDebounced();
        });
    });
}

/* ---------------------------------------------------------------------------
   Mobile shop filter drawer toggle.
--------------------------------------------------------------------------- */
function initShopFilterDrawer() {
    const btn = document.getElementById('filter-toggle');
    const drawer = document.getElementById('filter-drawer');
    const backdrop = document.getElementById('filter-backdrop');
    if (!btn || !drawer) return;

    const open = () => {
        drawer.classList.remove('translate-y-full');
        backdrop?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    };
    const close = () => {
        drawer.classList.add('translate-y-full');
        backdrop?.classList.add('hidden');
        document.body.style.overflow = '';
    };

    btn.addEventListener('click', open);
    backdrop?.addEventListener('click', close);
    drawer.querySelector('[data-filter-close]')?.addEventListener('click', close);
}

/* ---------------------------------------------------------------------------
   Recently viewed products — stored in localStorage, rendered client-side.
--------------------------------------------------------------------------- */
function initRecentlyViewed() {
    const root = document.querySelector('[data-product]');
    const MAX = 8;
    const KEY = 'chiaco_recently_viewed';

    function load() {
        try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch { return []; }
    }
    function save(items) {
        try { localStorage.setItem(KEY, JSON.stringify(items)); } catch {}
    }

    // Track current product
    if (root && root.dataset.productId) {
        const item = {
            id: root.dataset.productId,
            name: root.dataset.productName || '',
            url: root.dataset.productUrl || window.location.pathname,
            image: root.dataset.productImage || '',
            price: root.dataset.productPrice || '',
        };
        let items = load().filter((i) => i.id !== item.id);
        items.unshift(item);
        save(items.slice(0, MAX));
    }

    // Render recently viewed section
    const section = document.getElementById('recently-viewed');
    const grid = document.getElementById('recently-viewed-grid');
    if (!section || !grid) return;

    const items = load().filter((i) => !root || i.id !== root.dataset.productId);
    if (items.length < 2) return;

    const persianPrice = (n) => {
        if (!n || isNaN(n)) return '';
        return Number(n).toLocaleString('en-US') + ' Toman';
    };

    grid.innerHTML = items.slice(0, 4).map((item) => `
        <a href="${item.url}" class="group block overflow-hidden rounded-2xl bg-white ring-1 ring-brand-100 transition hover:ring-brand-300">
            <div class="aspect-[3/4] overflow-hidden bg-brand-50">
                ${item.image
                    ? `<img src="${item.image}" alt="${item.name}" class="h-full w-full object-cover transition group-hover:scale-105">`
                    : `<div class="h-full w-full bg-brand-100"></div>`}
            </div>
            <div class="p-3">
                <p class="truncate text-xs font-semibold text-brand-800">${item.name}</p>
                ${item.price ? `<p class="mt-1 text-xs text-brand-500 fa-num">${persianPrice(item.price)}</p>` : ''}
            </div>
        </a>
    `).join('');

    section.style.display = '';
}

/* ---------------------------------------------------------------------------
   Shop search autocomplete — debounced fetch from /search/suggest.
--------------------------------------------------------------------------- */
function initSearchAutocomplete() {
    const wrap = document.querySelector('[data-search-autocomplete]');
    if (!wrap) return;

    const input = wrap.querySelector('input[name="q"]');
    const list = wrap.querySelector('#search-suggestions');
    if (!input || !list) return;

    let timer = null;

    const hide = () => { list.classList.add('hidden'); list.innerHTML = ''; };
    const show = (items) => {
        if (!items.length) { hide(); return; }
        list.innerHTML = items.map((item) =>
            `<li><a href="${item.url}" class="block px-4 py-2.5 text-sm text-brand-800 transition hover:bg-brand-50 hover:text-accent-600">${item.name}</a></li>`
        ).join('');
        list.classList.remove('hidden');
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { hide(); return; }
        timer = setTimeout(async () => {
            try {
                const res = await fetch(`/search/suggest?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                show(data);
            } catch { hide(); }
        }, 220);
    });

    input.addEventListener('blur', () => setTimeout(hide, 150));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });
}

document.addEventListener('DOMContentLoaded', () => {
    initReveal();
    initProduct();
    initQuantityStepper();
    initCartSteppers();
    initJalaliDatepicker();
    initMotionBanners();
    initPopups();
    initShopFilterDrawer();
    initRecentlyViewed();
    initSearchAutocomplete();
});
