/* Frontmall main: sticky header, mobile nav, back-to-top */
(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var header = document.querySelector('.fm-header[data-sticky]');
		var placeholder = null;
		if (header) {
			var offset = header.offsetTop;
			var h = header.offsetHeight;
			window.addEventListener('scroll', function () {
				if (window.scrollY > offset + h) {
					if (!header.classList.contains('is-stuck')) {
						placeholder = document.createElement('div');
						placeholder.style.height = h + 'px';
						header.parentNode.insertBefore(placeholder, header.nextSibling);
						header.classList.add('is-stuck');
					}
				} else if (header.classList.contains('is-stuck')) {
					header.classList.remove('is-stuck');
					if (placeholder) { placeholder.remove(); placeholder = null; }
				}
			}, { passive: true });
		}

		var burger = document.querySelector('.fm-burger');
		var mnav = document.getElementById('fm-mobile-nav');
		if (burger && mnav) {
			burger.addEventListener('click', function () {
				var open = mnav.classList.toggle('is-open');
				burger.setAttribute('aria-expanded', open ? 'true' : 'false');
				document.body.style.overflow = open ? 'hidden' : '';
			});
			document.addEventListener('click', function (e) {
				if (mnav.classList.contains('is-open') && !mnav.contains(e.target) && !burger.contains(e.target)) {
					mnav.classList.remove('is-open');
					burger.setAttribute('aria-expanded', 'false');
					document.body.style.overflow = '';
				}
			});
		}

		var toTop = document.querySelector('.fm-to-top');
		if (toTop) {
			window.addEventListener('scroll', function () {
				toTop.hidden = window.scrollY < 400;
			}, { passive: true });
			toTop.addEventListener('click', function () {
				window.scrollTo({ top: 0, behavior: 'smooth' });
			});
		}
	});
})();


/* Frontmall hero slider (3 rotating slides) */
(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var slider = document.querySelector('[data-fm-slider]');
		if (!slider) { return; }
		var track = slider.querySelector('.fm-slider__track');
		var slides = slider.querySelectorAll('.fm-slide');
		var dots = slider.querySelectorAll('.fm-slider__dot');
		var prev = slider.querySelector('.fm-slider__arrow--prev');
		var next = slider.querySelector('.fm-slider__arrow--next');
		var n = slides.length, i = 0, timer = null;
		if (!track || n < 2) { return; }
		function go(k) {
			i = (k + n) % n;
			track.style.transform = 'translateX(' + (-i * 100) + '%)';
			for (var d = 0; d < dots.length; d++) { dots[d].classList.toggle('is-active', d === i); }
		}
		function start() { stop(); timer = setInterval(function () { go(i + 1); }, 6000); }
		function stop() { if (timer) { clearInterval(timer); timer = null; } }
		if (next) { next.addEventListener('click', function () { go(i + 1); start(); }); }
		if (prev) { prev.addEventListener('click', function () { go(i - 1); start(); }); }
		for (var d = 0; d < dots.length; d++) {
			(function (idx) { dots[idx].addEventListener('click', function () { go(idx); start(); }); })(d);
		}
		slider.addEventListener('mouseenter', stop);
		slider.addEventListener('mouseleave', start);
		start();
	});
})();


/* Frontmall horizontal scrollers (featured categories etc.) */
(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('button[data-scroll]').forEach(function (btn) {
			var section = btn.closest('.fm-section') || document;
			var scroller = section.querySelector('[data-fm-scroller]');
			if (!scroller) { return; }
			btn.addEventListener('click', function () {
				var dir = btn.getAttribute('data-scroll') === 'prev' ? -1 : 1;
				scroller.scrollBy({ left: dir * Math.round(scroller.clientWidth * 0.85), behavior: 'smooth' });
			});
		});
	});
})();


/* Frontmall native wishlist (localStorage, no plugin required) */
(function () {
	'use strict';
	var KEY = 'fm_wishlist';

	function read() {
		try { return JSON.parse(localStorage.getItem(KEY) || '[]').filter(function (n) { return n; }); }
		catch (e) { return []; }
	}
	function write(list) {
		try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {}
	}
	function has(id) { return read().indexOf(String(id)) !== -1; }
	function toggle(id) {
		id = String(id);
		var list = read(), i = list.indexOf(id);
		if (i === -1) { list.push(id); } else { list.splice(i, 1); }
		write(list);
		updateCount();
		return i === -1;
	}
	function updateCount() {
		var n = read().length;
		document.querySelectorAll('.fm-wish-count').forEach(function (el) {
			el.setAttribute('data-count', String(n));
			el.textContent = n > 0 ? String(n) : '';
			el.hidden = n === 0;
		});
	}
	function paintButtons(scope) {
		(scope || document).querySelectorAll('[data-wishlist]').forEach(function (btn) {
			var on = has(btn.getAttribute('data-wishlist'));
			btn.classList.toggle('is-active', on);
			btn.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
	}

	function bind(scope) {
		(scope || document).querySelectorAll('[data-wishlist]').forEach(function (btn) {
			if (btn.dataset.fmBound) { return; }
			btn.dataset.fmBound = '1';
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var id = btn.getAttribute('data-wishlist');
				var added = toggle(id);
				btn.classList.toggle('is-active', added);
				btn.setAttribute('aria-pressed', added ? 'true' : 'false');
				var page = document.querySelector('[data-fm-wishlist]');
				if (page && !added) {
					var card = btn.closest('.fm-card');
					if (card) { card.remove(); }
					if (!page.querySelector('.fm-card')) { renderWishlist(); }
				}
			});
		});
	}

	function renderWishlist() {
		var wrap = document.querySelector('[data-fm-wishlist]');
		if (!wrap) { return; }
		var loading = wrap.querySelector('[data-loading]');
		var empty = wrap.querySelector('[data-empty]');
		var items = wrap.querySelector('[data-items]');
		var list = read();
		if (loading) { loading.hidden = true; }
		if (!list.length) {
			if (empty) { empty.hidden = false; }
			if (items) { items.hidden = true; items.innerHTML = ''; }
			return;
		}
		if (typeof FRONTMALL === 'undefined' || !FRONTMALL.ajaxUrl) { return; }
		var url = FRONTMALL.ajaxUrl + '?action=frontmall_wishlist_items&nonce=' +
			encodeURIComponent(FRONTMALL.wishlistNonce || '') + '&ids=' + encodeURIComponent(list.join(','));
		fetch(url, { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success || !res.data.count) {
					if (empty) { empty.hidden = false; }
					return;
				}
				if (items) { items.innerHTML = res.data.html; items.hidden = false; }
				if (empty) { empty.hidden = true; }
				bind(items);
				paintButtons(items);
			})
			.catch(function () { if (empty) { empty.hidden = false; } });
	}

	document.addEventListener('DOMContentLoaded', function () {
		updateCount();
		paintButtons(document);
		bind(document);
		renderWishlist();
	});
})();


/* Frontmall shop filters: mobile drawer + auto-submit on toggle change */
(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var panel = document.getElementById('fm-shop-filters');
		var openBtn = document.querySelector('.fm-filters-toggle');
		var closeBtn = document.querySelector('.fm-filters-close');
		function open() { if (panel) { panel.classList.add('is-open'); document.body.classList.add('fm-filters-open'); if (openBtn) { openBtn.setAttribute('aria-expanded', 'true'); } } }
		function close() { if (panel) { panel.classList.remove('is-open'); document.body.classList.remove('fm-filters-open'); if (openBtn) { openBtn.setAttribute('aria-expanded', 'false'); } } }
		if (openBtn) { openBtn.addEventListener('click', open); }
		if (closeBtn) { closeBtn.addEventListener('click', close); }
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });

		var form = document.querySelector('form[data-fm-filters]');
		if (form) {
			form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
				cb.addEventListener('change', function () { form.submit(); });
			});
		}
	});
})();


/* Frontmall single-product sticky add-to-cart bar */
(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var bar = document.querySelector('[data-fm-sticky-atc]');
		if (!bar) { return; }
		var form = document.querySelector('.product form.cart') || document.querySelector('form.cart') || document.querySelector('.single_add_to_cart_button');
		if (!form) { return; }

		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					var below = en.boundingClientRect.top > 0;
					bar.hidden = en.isIntersecting || below;
				});
			}, { threshold: 0 });
			io.observe(form);
		}

		var scrollLink = bar.querySelector('[data-fm-sticky-scroll]');
		if (scrollLink) {
			scrollLink.addEventListener('click', function (e) {
				e.preventDefault();
				form.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});
		}
	});
})();


/* Frontmall Quick View modal */
(function () {
	'use strict';
	if (typeof FRONTMALL === 'undefined') { return; }
	var modal = null, lastFocus = null;

	function build() {
		if (modal) { return modal; }
		modal = document.createElement('div');
		modal.className = 'fm-qv-modal';
		modal.hidden = true;
		modal.innerHTML =
			'<div class="fm-qv-modal__overlay" data-qv-close></div>' +
			'<div class="fm-qv-modal__panel" role="dialog" aria-modal="true" aria-label="Quick view">' +
				'<button class="fm-qv-modal__close" type="button" data-qv-close aria-label="Close">&times;</button>' +
				'<div class="fm-qv-modal__content" data-qv-content></div>' +
			'</div>';
		document.body.appendChild(modal);
		modal.addEventListener('click', function (e) { if (e.target.closest('[data-qv-close]')) { close(); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { close(); } });
		document.body.addEventListener('fm:added', function () { if (modal && !modal.hidden) { close(); } });
		return modal;
	}

	function open(html) {
		build();
		modal.querySelector('[data-qv-content]').innerHTML = html;
		modal.hidden = false;
		requestAnimationFrame(function () { modal.classList.add('is-open'); });
		document.body.style.overflow = 'hidden';
		var closeBtn = modal.querySelector('.fm-qv-modal__close');
		if (closeBtn) { closeBtn.focus(); }
	}

	function close() {
		if (!modal) { return; }
		modal.classList.remove('is-open');
		document.body.style.overflow = '';
		setTimeout(function () { if (modal && !modal.classList.contains('is-open')) { modal.hidden = true; modal.querySelector('[data-qv-content]').innerHTML = ''; } }, 280);
		if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-quickview]');
		if (!btn) { return; }
		e.preventDefault();
		lastFocus = btn;
		var id = btn.getAttribute('data-quickview');
		if (!id) { return; }
		build();
		open('<div class="fm-qv-loading">' + (FRONTMALL.i18n && FRONTMALL.i18n.searching ? FRONTMALL.i18n.searching : 'Loading...') + '</div>');
		var url = FRONTMALL.ajaxUrl + '?action=frontmall_quickview&nonce=' + encodeURIComponent(FRONTMALL.quickviewNonce || '') + '&product_id=' + encodeURIComponent(id);
		fetch(url, { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success && res.data && res.data.html) { open(res.data.html); }
				else { close(); window.location.href = btn.getAttribute('href') || '#'; }
			})
			.catch(function () { close(); window.location.href = btn.getAttribute('href') || '#'; });
	});
})();


/* Frontmall newsletter subscribe (AJAX, spam-hardened server-side) */
(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var form = document.querySelector('form[data-fm-newsletter]');
		if (!form) { return; }
		var msg = form.querySelector('[data-nl-msg]');
		var btn = form.querySelector('button[type="submit"]');
		function say(text, ok) {
			if (!msg) { return; }
			msg.hidden = false;
			msg.textContent = text;
			msg.className = 'fm-newsletter__msg ' + (ok ? 'is-ok' : 'is-err');
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (btn) { btn.disabled = true; }
			fetch(form.action, { method: 'POST', credentials: 'same-origin', body: new FormData(form) })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (btn) { btn.disabled = false; }
					var ok = !!(res && res.success);
					say((res && res.data && res.data.message) ? res.data.message : (ok ? 'Thank you!' : 'Something went wrong.'), ok);
					if (ok) { form.reset(); }
				})
				.catch(function () {
					if (btn) { btn.disabled = false; }
					say('Something went wrong. Please try again.', false);
				});
		});
	});
})();
