/* Frontmall AJAX add-to-cart + slide-in mini-cart drawer */
(function () {
	'use strict';
	if (typeof FRONTMALL === 'undefined') { return; }
	var i18n = FRONTMALL.i18n || {};
	var drawer = null;

	function buildDrawer() {
		if (drawer) { return drawer; }
		drawer = document.createElement('div');
		drawer.className = 'fm-cart-drawer';
		drawer.id = 'fm-cart-drawer';
		drawer.hidden = true;
		drawer.innerHTML =
			'<div class="fm-cart-drawer__overlay" data-fm-cart-close></div>' +
			'<aside class="fm-cart-drawer__panel" role="dialog" aria-modal="true" aria-label="' + (i18n.inCart || 'Added to cart') + '">' +
				'<div class="fm-cart-drawer__head">' +
					'<span class="fm-cart-drawer__title">' + (i18n.inCart || 'Added to your cart') + '</span>' +
					'<button class="fm-cart-drawer__close" type="button" data-fm-cart-close aria-label="Close">&times;</button>' +
				'</div>' +
				'<div class="fm-cart-drawer__item">' +
					'<div class="fm-cart-drawer__img"></div>' +
					'<div class="fm-cart-drawer__meta"><span class="fm-cart-drawer__name"></span><span class="fm-cart-drawer__qty"></span></div>' +
				'</div>' +
				'<div class="fm-cart-drawer__subtotal"><span>' + (i18n.subtotal || 'Subtotal') + '</span><span class="fm-cart-drawer__subtotal-val"></span></div>' +
				'<div class="fm-cart-drawer__actions">' +
					'<a class="fm-btn fm-btn--outline fm-cart-drawer__view" href="#">' + (i18n.viewCart || 'View cart') + '</a>' +
					'<a class="fm-btn fm-cart-drawer__checkout" href="#">' + (i18n.checkout || 'Checkout') + '</a>' +
				'</div>' +
				'<button class="fm-cart-drawer__continue" type="button" data-fm-cart-close>' + (i18n.continueShopping || 'Continue shopping') + '</button>' +
			'</aside>';
		document.body.appendChild(drawer);
		drawer.addEventListener('click', function (e) {
			if (e.target.closest('[data-fm-cart-close]')) { closeDrawer(); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { closeDrawer(); }
		});
		return drawer;
	}

	function openDrawer(d) {
		buildDrawer();
		drawer.querySelector('.fm-cart-drawer__img').innerHTML = d.productImage || '';
		drawer.querySelector('.fm-cart-drawer__name').textContent = d.productName || '';
		drawer.querySelector('.fm-cart-drawer__qty').textContent = (i18n.qty || 'Qty') + ': ' + (d.quantity || 1);
		drawer.querySelector('.fm-cart-drawer__subtotal-val').innerHTML = d.subtotal || '';
		drawer.querySelector('.fm-cart-drawer__view').href = d.cartUrl || FRONTMALL.cartUrl || '#';
		drawer.querySelector('.fm-cart-drawer__checkout').href = d.checkoutUrl || FRONTMALL.checkoutUrl || '#';
		drawer.hidden = false;
		requestAnimationFrame(function () { drawer.classList.add('is-open'); });
		document.body.style.overflow = 'hidden';
	}

	function closeDrawer() {
		if (!drawer) { return; }
		drawer.classList.remove('is-open');
		document.body.style.overflow = '';
		setTimeout(function () { if (drawer && !drawer.classList.contains('is-open')) { drawer.hidden = true; } }, 320);
	}

	function applyFragments(fragments) {
		if (!fragments) { return; }
		Object.keys(fragments).forEach(function (sel) {
			document.querySelectorAll(sel).forEach(function (node) {
				var tmp = document.createElement('div');
				tmp.innerHTML = fragments[sel];
				if (tmp.firstElementChild) { node.replaceWith(tmp.firstElementChild); }
			});
		});
	}

	function addToCart(pid, qty, feedbackEl) {
		var original = feedbackEl ? feedbackEl.textContent : '';
		if (feedbackEl) {
			if (feedbackEl.classList.contains('is-loading')) { return; }
			feedbackEl.classList.add('is-loading');
			feedbackEl.textContent = '...';
		}
		var body = new URLSearchParams();
		body.append('action', 'frontmall_add_to_cart');
		body.append('nonce', FRONTMALL.cartNonce);
		body.append('product_id', pid);
		body.append('quantity', qty || '1');
		fetch(FRONTMALL.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (feedbackEl) { feedbackEl.classList.remove('is-loading'); }
				if (res && res.success && res.data) {
					applyFragments(res.data.fragments);
					if (feedbackEl) {
						feedbackEl.classList.add('is-done');
						feedbackEl.textContent = i18n.added || 'Added';
						setTimeout(function () { feedbackEl.classList.remove('is-done'); feedbackEl.textContent = original; }, 1800);
					}
					document.body.dispatchEvent(new CustomEvent('fm:added', { detail: { productId: pid } }));
					openDrawer(res.data);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : (i18n.error || 'Error');
					if (feedbackEl) { feedbackEl.textContent = msg; setTimeout(function () { feedbackEl.textContent = original; }, 2200); }
				}
			})
			.catch(function () {
				if (feedbackEl) { feedbackEl.classList.remove('is-loading'); feedbackEl.textContent = i18n.error || 'Error'; setTimeout(function () { feedbackEl.textContent = original; }, 2200); }
			});
	}

	/* Product-card AJAX buttons */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.fm-ajax-add');
		if (!btn) { return; }
		e.preventDefault();
		var pid = btn.getAttribute('data-product-id');
		if (!pid) { return; }
		addToCart(pid, '1', btn);
	});

	/* Single product page: route simple products through AJAX + drawer */
	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || !form.classList || !form.classList.contains('cart')) { return; }
		if (form.classList.contains('variations_form') || form.classList.contains('grouped_form')) { return; }
		if (form.querySelector('[name="variation_id"]')) { return; }
		var addBtn = form.querySelector('[name="add-to-cart"]');
		var pid = addBtn ? (addBtn.value || addBtn.getAttribute('value')) : '';
		if (!pid || !/^[0-9]+$/.test(pid)) { return; }
		e.preventDefault();
		var qtyEl = form.querySelector('input[name="quantity"]');
		var qty = qtyEl ? qtyEl.value : '1';
		addToCart(pid, qty, form.querySelector('.single_add_to_cart_button'));
	});
})();
