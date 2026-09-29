/* Warleek — Vanilla-JS: Reveal, Count-up, Flare-Bar, Smoke (Desktop), Hero-Video (Desktop, nur sichtbar). */
(function () {
	'use strict';
	var d = document, root = d.documentElement;
	root.classList.add('wl-js');
	var mq = function (q) { return !!(window.matchMedia && window.matchMedia(q).matches); };
	var snap = !!d.getElementById('warleek-snap');
	var reduce = mq('(prefers-reduced-motion: reduce)') || snap;
	var saveData = !!(navigator.connection && navigator.connection.saveData);
	var lite = reduce || saveData || mq('(max-width: 781px)') || (mq('(hover: none)') && mq('(pointer: coarse)')); // Touch/Mobile: kein Video, kein Smoke

	function ready(fn) { if (d.readyState !== 'loading') fn(); else d.addEventListener('DOMContentLoaded', fn); }

	ready(function () {
		if (!reduce && !d.querySelector('.wl-flare-bar')) {
			var bar = d.createElement('div'); bar.className = 'wl-flare-bar'; bar.setAttribute('aria-hidden', 'true');
			d.body.appendChild(bar);
			bar.addEventListener('animationend', function () { bar.remove(); });
		}

		var hero = d.querySelector('.wl-hero');
		if (hero && !lite) {
			var sm = d.createElement('div'); sm.className = 'wl-smoke'; sm.setAttribute('aria-hidden', 'true');
			hero.insertBefore(sm, hero.firstChild);
		}

		// Hero-Video: auf Touch/Mobile entfernen (Poster-Bild bleibt), auf Desktop nur abspielen, solange sichtbar.
		d.querySelectorAll('.wl-hero__video video').forEach(function (v) {
			if (lite) { v.closest('.wl-hero__video').remove(); return; }
			v.muted = true; v.loop = true; v.setAttribute('playsinline', ''); v.setAttribute('aria-hidden', 'true'); v.setAttribute('tabindex', '-1');
			var userPaused = false, armed = false;
			function play() {
				if (userPaused) return;
				if (!armed) { armed = true; if (v.dataset.poster) { v.poster = v.dataset.poster; } v.src = v.dataset.src; v.preload = 'auto'; }
				var p = v.play(); if (p && p.catch) { p.catch(function () {}); }
			}
			if ('IntersectionObserver' in window) {
				new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) play(); else v.pause(); }); }, { threshold: 0.1 }).observe(v);
			} else { play(); }
			var host = v.closest('.wl-hero') || v.parentNode, btn = d.createElement('button');
			var icoPause = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 2h4v12H3zM9 2h4v12H9z"/></svg>';
			var icoPlay = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 2l10 6-10 6z"/></svg>';
			btn.type = 'button'; btn.className = 'wl-video-toggle'; btn.setAttribute('aria-pressed', 'false');
			btn.innerHTML = icoPause + '<span>Video pausieren</span>';
			btn.addEventListener('click', function () {
				userPaused = !v.paused;
				if (userPaused) { v.pause(); } else { play(); }
				btn.innerHTML = (userPaused ? icoPlay : icoPause) + '<span>' + (userPaused ? 'Video abspielen' : 'Video pausieren') + '</span>';
				btn.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
			});
			host.appendChild(btn);
		});

		// Reveal on scroll – nur Elemente unterhalb des Folds, damit nichts „aufploppt".
		if (!reduce && 'IntersectionObserver' in window) {
			var targets = d.querySelectorAll('.wl-card, .wl-tier, .wl-stat, .wl-member, .wl-section__head, .wl-patchlist li, .wl-columns .wp-block-column, .wl-archive-list .wp-block-post');
			var vh = window.innerHeight || 800;
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
			}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
			targets.forEach(function (t, i) {
				if (t.getBoundingClientRect().top < vh * 0.9) { return; }
				t.classList.add('wl-reveal');
				t.style.transitionDelay = ((i % 6) * 50) + 'ms';
				io.observe(t);
			});
		}

		// Count-up für Zahlen in .wl-stat__num
		if (!reduce && 'IntersectionObserver' in window) {
			var cio = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) {
					if (!e.isIntersecting) return; cio.unobserve(e.target);
					var el = e.target, m = /^(\d[\d.]*)(\D*)$/.exec(el.textContent.trim());
					if (!m) return;
					var end = parseFloat(m[1].replace(/\./g, '')), suffix = m[2], start = null, dur = 1000;
					function step(ts) {
						if (!start) start = ts;
						var p = Math.min(1, (ts - start) / dur), ease = 1 - Math.pow(1 - p, 3);
						el.textContent = Math.round(end * ease).toLocaleString('de-DE') + suffix;
						if (p < 1) requestAnimationFrame(step);
					}
					requestAnimationFrame(step);
				});
			}, { threshold: 0.5 });
			d.querySelectorAll('.wl-stat__num').forEach(function (n) { cio.observe(n); });
		}

		// Gruß an alle, die hier reinschauen.
		try {
			var css = 'color:#9be15d;background:#0d110f;font:bold 14px/1.6 "Barlow Condensed",Impact,sans-serif;padding:8px 14px;letter-spacing:.08em';
			console.log('%c// WARLEEK // DACH // Du liest den Quellcode statt Nachschub zu fahren?', css);
			console.log('%cDie FOB braucht Build-Supplies, nicht deine Neugier. Aber okay: wer Quellcode liest, schreibt vielleicht auch Guides → /community/', 'color:#c7b58f;font:13px/1.5 Barlow,sans-serif');
			console.log('%c   ___\n  /   \\   Dogtag ✓\n  | 🥬 |   Lauch  ✓\n  \\___/   Guides ✓  (lies sie)', 'color:#8a938c;font:12px/1.3 monospace');
		} catch (e) {}
	});
})();
