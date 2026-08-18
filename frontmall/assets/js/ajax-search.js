/* Frontmall instant AJAX search: debounce + abort + in-memory cache + keyboard */
(function () {
	'use strict';
	if (typeof FRONTMALL === 'undefined') { return; }

	document.addEventListener('DOMContentLoaded', function () {
		var input = document.getElementById('fm-search-input');
		var box = document.getElementById('fm-search-results');
		if (!input || !box) { return; }

		var timer = null, controller = null, lastQ = '', cache = {};

		function hide() { box.hidden = true; input.setAttribute('aria-expanded', 'false'); }
		function show(html) { box.innerHTML = html; box.hidden = false; input.setAttribute('aria-expanded', 'true'); }
		function paint(res) {
			if (!res || !res.count) { show('<div class="fm-search__empty">' + FRONTMALL.i18n.noResults + '</div>'); return; }
			show(res.html);
		}

		function run(q) {
			if (Object.prototype.hasOwnProperty.call(cache, q)) { paint(cache[q]); return; }
			if (controller) { controller.abort(); }
			controller = new AbortController();
			show('<div class="fm-search__loading">' + FRONTMALL.i18n.searching + '</div>');
			var url = FRONTMALL.ajaxUrl + '?action=frontmall_search&nonce=' + encodeURIComponent(FRONTMALL.searchNonce) + '&q=' + encodeURIComponent(q);
			fetch(url, { signal: controller.signal, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) { hide(); return; }
					cache[q] = res.data;
					paint(res.data);
				})
				.catch(function (e) { if (e.name !== 'AbortError') { hide(); } });
		}

		input.addEventListener('input', function () {
			var q = input.value.trim();
			if (q === lastQ) { return; }
			lastQ = q;
			clearTimeout(timer);
			if (q.length < 2) { hide(); return; }
			timer = setTimeout(function () { run(q); }, 140);
		});

		input.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { hide(); }
			if (e.key === 'ArrowDown' && !box.hidden) {
				var first = box.querySelector('a');
				if (first) { e.preventDefault(); first.focus(); }
			}
		});

		document.addEventListener('click', function (e) {
			if (!box.contains(e.target) && e.target !== input) { hide(); }
		});
		input.addEventListener('focus', function () {
			if (input.value.trim().length >= 2 && box.innerHTML) { box.hidden = false; }
		});
	});
})();
