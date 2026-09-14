/* Warleek — Vanilla-JS: Reveal, Count-up, Flare-Bar, Smoke-Layer, Hero-Video-Schonung. */
(function () {
	'use strict';
	var d = document, root = d.documentElement;
	root.classList.add('wl-js');
	var reduce = (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) || !!d.getElementById('warleek-snap');
	var saveData = navigator.connection && navigator.connection.saveData;

	function ready(fn) { if (d.readyState !== 'loading') fn(); else d.addEventListener('DOMContentLoaded', fn); }

	ready(function () {
		// Flare-Balken (einmal pro Seitenaufruf).
		if (!reduce && !d.querySelector('.wl-flare-bar')) {
			var bar = d.createElement('div'); bar.className = 'wl-flare-bar'; bar.setAttribute('aria-hidden', 'true');
			d.body.appendChild(bar);
			bar.addEventListener('animationend', function () { bar.remove(); });
		}

		// Smoke-Layer im Hero (rein dekorativ).
		var hero = d.querySelector('.wl-hero');
		if (hero && !reduce) {
			var sm = d.createElement('div'); sm.className = 'wl-smoke'; sm.setAttribute('aria-hidden', 'true');
			hero.insertBefore(sm, hero.firstChild);
		}

		// Hero-Video: bei reduced-motion / Datensparmodus entfernen (Poster/Bild bleibt), sonst abspielen.
		d.querySelectorAll('.wl-hero__video video').forEach(function (v) {
			if (reduce || saveData) { v.closest('.wl-hero__video').remove(); return; }
			v.muted = true; v.setAttribute('playsinline', ''); v.setAttribute('aria-hidden', 'true'); v.setAttribute('tabindex', '-1');
			var p = v.play(); if (p && p.catch) { p.catch(function () {}); }
			// Pause-Button (Barrierefreiheit: bewegte Inhalte müssen anhaltbar sein)
			var host = v.closest('.wl-hero') || v.parentNode, btn = d.createElement('button');
			var icoPause = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 2h4v12H3zM9 2h4v12H9z"/></svg>';
			var icoPlay = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 2l10 6-10 6z"/></svg>';
			btn.type = 'button'; btn.className = 'wl-video-toggle'; btn.setAttribute('aria-pressed', 'false');
			btn.innerHTML = icoPause + '<span>Video pausieren</span>';
			btn.addEventListener('click', function () {
				var paused = v.paused;
				if (paused) { v.play(); } else { v.pause(); }
				btn.innerHTML = (paused ? icoPause : icoPlay) + '<span>' + (paused ? 'Video pausieren' : 'Video abspielen') + '</span>';
				btn.setAttribute('aria-pressed', paused ? 'false' : 'true');
			});
			host.appendChild(btn);
		});

		// Reveal on scroll.
		var targets = d.querySelectorAll('.wl-card, .wl-tier, .wl-stat, .wl-member, .wl-section__head, .wl-patchlist li, .wl-columns .wp-block-column, .wl-archive-list .wp-block-post');
		if ('IntersectionObserver' in window && targets.length) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
			}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
			var vh = window.innerHeight || 800;
			targets.forEach(function (t, i) {
				// Elemente, die beim Laden schon sichtbar sind, nicht verstecken (kein „Aufploppen").
				if (t.getBoundingClientRect().top < vh * 0.9) { return; }
				t.classList.add('wl-reveal');
				t.style.transitionDelay = ((i % 6) * 60) + 'ms';
				io.observe(t);
			});
		}

		// Count-up für Zahlen in .wl-stat__num (nur wenn rein numerisch mit optionalem Suffix).
		if (!reduce && 'IntersectionObserver' in window) {
			var nums = d.querySelectorAll('.wl-stat__num');
			var cio = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) {
					if (!e.isIntersecting) return; cio.unobserve(e.target);
					var el = e.target, m = /^(\d[\d.]*)(\D*)$/.exec(el.textContent.trim());
					if (!m) return;
					var end = parseFloat(m[1].replace(/\./g, '')), suffix = m[2], start = null, dur = 1200;
					function step(ts) {
						if (!start) start = ts;
						var p = Math.min(1, (ts - start) / dur), ease = 1 - Math.pow(1 - p, 3);
						el.textContent = Math.round(end * ease).toLocaleString('de-DE') + suffix;
						if (p < 1) requestAnimationFrame(step);
					}
					requestAnimationFrame(step);
				});
			}, { threshold: 0.5 });
			nums.forEach(function (n) { cio.observe(n); });
		}
	});
})();
