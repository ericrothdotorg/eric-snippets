<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// =========================================
// LANGUAGE SWITCHER (the browser's own translation)
// Desktop Chrome 138+ / Edge 148+: the page is translated on the device by the
// browser's built-in Translator API. The browser fetches the language pack once
// itself (Google / Microsoft), on the visitor's click; no page text leaves the device.
// Everywhere else (phones, Safari, Firefox): a short how-to for the browser's own
// "Translate" feature. No external script, no cookie.
// =========================================

add_action('wp_footer', function () {

	if (is_front_page()) return;

	// Single source of truth for the icon
	$er_lang_icon = '/wp-content/uploads/2026/05/globe-solid-white.svg';

	// Offered languages; only those the visitor's browser supports are listed.
	// Keep in sync with LANG_MAP in the "Toggle - Mute | Unmute" snippet.
	$er_langs = 'ar,da,de,es,fi,fr,hi,id,it,ja,ko,no,ru,sv,th,tl,tr,vi,zh-CN';

	?>

<div id="er-lang-panel" role="dialog" aria-label="Language selector" aria-hidden="true">
	<div id="er-lang-loading"><span id="er-lang-loading-text">Loading languages…</span></div>
	<select id="er-lang-select" aria-label="Select language" translate="no" hidden></select>
	<div id="er-lang-hint" hidden></div>
</div>

<button type="button" id="er-lang-toggle" title="Language Switcher" translate="no"
        aria-label="Open language switcher" aria-expanded="false" aria-controls="er-lang-panel">
	<img src="<?php echo esc_url($er_lang_icon); ?>" data-no-lazy="1" width="24" height="24" alt="" aria-hidden="true">
</button>

<div id="er-lang-announcer" role="status" aria-live="polite" aria-atomic="true"></div>

<style>
	#er-lang-toggle {
		position: fixed;
		bottom: 25px;
		right: 75px;
		z-index: 999;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 40px;
		height: 40px;
		padding: 6px;
		border: none;
		border-radius: 50%;
		background: var(--color-3);
		box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
		cursor: pointer;
		appearance: none;
		transition: transform 0.2s ease;
	}
	#er-lang-toggle:hover {transform: scale(1.1);}
	#er-lang-toggle:focus-visible {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}

	#er-lang-panel {
		display: none;
		position: fixed;
		bottom: 80px;
		right: 75px;
		z-index: 1000;
		padding: 10px;
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
	}
	#er-lang-panel.visible {display: block;}

	#er-lang-announcer {position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;}

	/* Language list (own translation) */
	#er-lang-select {
		display: block;
		width: 200px;
		padding: 8px;
		border: 1px solid var(--color-5);
		border-radius: 4px;
		background: var(--color-8);
		color: var(--color-3);
		font-family: Arial, sans-serif;
		font-size: var(--er-fs-xs);
	}

	/* How-to (browser's own translation) */
	#er-lang-hint {
		width: 250px;
		font-family: Arial, sans-serif;
		font-size: var(--er-fs-xs);
		line-height: 1.4;
		text-align: left;
		color: var(--color-3);
	}
	#er-lang-hint strong {display: block; margin-bottom: 6px; color: var(--color-4);}
	#er-lang-hint p {margin: 0 0 6px; text-align: left; hyphens: manual;}
	#er-lang-hint p:last-child {margin-bottom: 0;}
	#er-lang-select[hidden], #er-lang-hint[hidden] {display: none;}

	/* Spinner line: loading the list, preparing a language (incl. the browser's one-time download) */
	#er-lang-loading {
		display: none;
		align-items: center;
		gap: 8px;
		box-sizing: border-box;
		width: 200px;
		padding: 8px;
		border: 1px solid var(--color-5);
		border-radius: 4px;
		font-family: Arial, sans-serif;
		font-size: var(--er-fs-xs);
		line-height: 1.2;
		color: var(--color-9);
	}
	#er-lang-panel.is-loading #er-lang-loading {display: flex;}
	#er-lang-panel.is-loading #er-lang-loading + #er-lang-select {margin-top: 8px;}
	#er-lang-loading::before {
		content: '';
		flex: none;
		width: 14px;
		height: 14px;
		border: 2px solid var(--color-5);
		border-top-color: var(--color-1);
		border-radius: 50%;
		animation: er-lang-spin 0.8s linear infinite;
	}
	@keyframes er-lang-spin {to {transform: rotate(360deg);}}
	@media (prefers-reduced-motion: reduce) {
		#er-lang-loading::before {animation: none; border-top-color: var(--color-9);}
	}
</style>

<script>
(function () {
	'use strict';

	var LANGS    = '<?php echo esc_js($er_langs); ?>'.split(',');
	var KEY      = 'erLang';               // chosen language, remembered across pages (this browser only)
	var API_CODE = { 'zh-CN': 'zh' };      // site code -> Translator API code, where they differ

	// Not translated: scripts, code, form fields, our own UI, anything marked translate="no"
	var SKIP  = 'script,style,noscript,template,code,pre,kbd,samp,textarea,select,option,svg,math,iframe,canvas,' +
	            '[translate="no"],.notranslate,[contenteditable=""],[contenteditable="true"],' +
	            '#er-lang-panel,#er-lang-toggle,#er-lang-announcer,#wpadminbar';
	var ATTRS = ['title', 'alt', 'placeholder', 'aria-label'];

	var hasEngine = typeof self.Translator !== 'undefined';
	var ORIG_LANG = document.documentElement.getAttribute('lang') || 'en';
	var SOURCE    = ORIG_LANG.split('-')[0];          // the page's own language: English, German on German pages
	if (LANGS.indexOf(SOURCE) >= 0) LANGS[LANGS.indexOf(SOURCE)] = 'en'; // on a German page: English instead of German

	var panel, toggle, announcer, loadingText, select, hint;
	var listPromise = null;

	// --- Helpers -------------------------------------------------

	var store = {
		get: function () { try { return localStorage.getItem(KEY); } catch (e) { return null; } },
		set: function (v) { try { localStorage.setItem(KEY, v); } catch (e) {} },
		del: function () { try { localStorage.removeItem(KEY); } catch (e) {} }
	};

	function announce(msg) {
		announcer.textContent = msg;
		setTimeout(function () { announcer.textContent = ''; }, 1000);
	}

	function apiCode(code) { return API_CODE[code] || code; }

	// Some browsers expose the API but never answer (no engine installed, blocked by
	// policy): after 3 s the language counts as unavailable and the how-to takes over.
	function availability(code) {
		return Promise.race([
			Translator.availability({ sourceLanguage: SOURCE, targetLanguage: apiCode(code) }),
			new Promise(function (resolve) { setTimeout(function () { resolve('unavailable'); }, 3000); })
		]).catch(function () { return 'unavailable'; });
	}

	// Each language in its own name and script, e.g. "Deutsch", "日本語"
	function langName(code) {
		var c = apiCode(code);
		try {
			var n = new Intl.DisplayNames([c], { type: 'language' }).of(c);
			return n.charAt(0).toLocaleUpperCase(c) + n.slice(1);
		} catch (e) { return code; }
	}

	function emit() {
		document.dispatchEvent(new CustomEvent('er:langchange', { detail: { lang: engine.lang } }));
	}

	// Leftovers of the former Google widget (cookie on host / dot-domain, TTS hand-over key)
	(function cleanupGoogle() {
		if (document.cookie.indexOf('googtrans=') < 0) return;
		var host = location.hostname;
		['', '; domain=' + host, '; domain=.' + host].forEach(function (d) {
			document.cookie = 'googtrans=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT' + d;
		});
		try { localStorage.removeItem('preferredLang'); } catch (e) {}
	})();

	// --- Translation engine (desktop Chrome / Edge) ----------------
	// Translates text nodes and a few attributes in place, visible part first,
	// and keeps every original so "English (original)" (or "Deutsch (original)") restores the page without a reload.

	var engine = {
		lang: null,          // language shown now (site code), null = the original
		pending: null,       // language being prepared, so read-aloud already knows it
		translator: null,
		items: [],           // { node | el+attr, orig, applied, done, waiters }
		byNode: new WeakMap(),
		byAttr: new WeakMap(),
		queue: [],
		cache: new Map(),    // original string -> translation, per language
		running: false,
		run: 0,              // a new run (switch / restore) stops the old one
		observer: null
	};

	function hasLetters(s) { return /\p{L}/u.test(s); }

	function skipped(el) { return !el || !!(el.closest && el.closest(SKIP)); }

	function addText(n, out) {
		if (engine.byNode.has(n)) return;
		var v = n.nodeValue;
		if (!v || !hasLetters(v) || skipped(n.parentElement)) return;
		var item = { node: n, orig: v, applied: null, done: false, waiters: [] };
		engine.byNode.set(n, item);
		engine.items.push(item);
		out.push(item);
	}

	function addAttrs(el, out) {
		var seen = engine.byAttr.get(el);
		ATTRS.forEach(function (a) {
			var v = el.getAttribute(a);
			if (!v || !hasLetters(v) || (seen && seen[a])) return;
			if (!seen) { seen = {}; engine.byAttr.set(el, seen); }
			var item = { el: el, attr: a, orig: v, applied: null, done: false, waiters: [] };
			seen[a] = item;
			engine.items.push(item);
			out.push(item);
		});
	}

	function collect(root, out) {
		if (root.nodeType === 3) { addText(root, out); return out; }
		if (root.nodeType !== 1 || skipped(root)) return out;
		addAttrs(root, out);
		var w = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
			acceptNode: function (n) {
				return (n.nodeType === 1 && n.matches(SKIP)) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
			}
		});
		var n;
		while ((n = w.nextNode())) {
			if (n.nodeType === 3) addText(n, out);
			else addAttrs(n, out);
		}
		return out;
	}

	function onScreen(item) {
		var el = item.node ? item.node.parentElement : item.el;
		if (!el) return false;
		var r = el.getBoundingClientRect();
		return r.bottom > 0 && r.top < window.innerHeight && (r.width || r.height);
	}

	function settle(item) {
		item.done = true;
		item.waiters.forEach(function (fn) { fn(); });
		item.waiters = [];
	}

	function current(item) { return item.node ? item.node.nodeValue : item.el.getAttribute(item.attr); }

	function apply(item, text) {
		if (item.node) item.node.nodeValue = text;
		else item.el.setAttribute(item.attr, text);
		item.applied = text;
	}

	function pump(run) {
		if (engine.running) return;
		engine.running = true;
		(async function () {
			while (engine.queue.length && run === engine.run) {
				var item = engine.queue.shift();
				if (item.done) continue;
				// Changed by the page in the meantime (counter, slider): leave it alone
				if (current(item) !== item.orig) { settle(item); continue; }

				var core = item.orig.trim();
				var out = engine.cache.get(core);
				if (out === undefined) {
					try { out = await engine.translator.translate(core); } catch (e) { out = core; }
					if (run !== engine.run) break;
					engine.cache.set(core, out);
				}
				if (current(item) === item.orig) {
					apply(item, item.orig.match(/^\s*/)[0] + out + item.orig.match(/\s*$/)[0]);
				}
				settle(item);
			}
			engine.running = false;
			if (run !== engine.run && engine.queue.length) pump(engine.run); // a newer run is waiting
		})();
	}

	// Content added later (slider clones, "load more", read-aloud cues): translate it too
	function observe() {
		engine.observer = new MutationObserver(function (records) {
			var add = [];
			records.forEach(function (r) {
				r.addedNodes.forEach(function (n) { collect(n, add); });
			});
			if (!add.length) return;
			engine.queue = engine.queue.concat(add);
			pump(engine.run);
		});
		engine.observer.observe(document.body, { childList: true, subtree: true });
	}

	// Back to the original, without a reload
	function restore() {
		engine.run++;
		if (engine.observer) { engine.observer.disconnect(); engine.observer = null; }
		engine.items.forEach(function (item) {
			if (item.applied !== null && current(item) === item.applied) apply(item, item.orig);
			settle(item);
		});
		engine.items = [];
		engine.queue = [];
		engine.byNode = new WeakMap();
		engine.byAttr = new WeakMap();
		engine.cache = new Map();
		if (engine.translator && engine.translator.destroy) { try { engine.translator.destroy(); } catch (e) {} }
		engine.translator = null;
		engine.lang = null;
		engine.pending = null;
		document.documentElement.setAttribute('lang', ORIG_LANG);
		emit();
	}

	// Must be called straight from the visitor's click (or for a pack that is
	// already installed): the browser only fetches a missing pack on a user gesture.
	function translateTo(code, onProgress) {
		if (engine.lang) restore();
		var run = ++engine.run;
		engine.pending = code;

		return Translator.create({
			sourceLanguage: SOURCE,
			targetLanguage: apiCode(code),
			monitor: function (m) {
				m.addEventListener('downloadprogress', function (e) { if (onProgress) onProgress(e.loaded); });
			}
		}).then(function (t) {
			if (run !== engine.run) { if (t.destroy) t.destroy(); return; }
			engine.translator = t;
			engine.lang = code;
			engine.pending = null;
			document.documentElement.setAttribute('lang', apiCode(code));

			var items = collect(document.body, []);
			var first = items.filter(onScreen);
			engine.queue = first.concat(items.filter(function (i) { return first.indexOf(i) < 0; }));
			observe();
			emit();
			pump(run);
		}, function (err) {
			if (run === engine.run) { engine.pending = null; emit(); }
			throw err;
		});
	}

	// For read-aloud: resolves once everything inside el is translated (or after the timeout)
	function ready(el, timeout) {
		if (!engine.lang && !engine.pending) return Promise.resolve();
		return new Promise(function (resolve) {
			var finish = function () { clearTimeout(timer); resolve(); };
			var timer = setTimeout(resolve, timeout || 5000);
			if (!engine.lang) { // language pack still loading: wait for it, then for el
				document.addEventListener('er:langchange', function () { ready(el, timeout).then(finish); }, { once: true });
				return;
			}
			var mine = collect(el, []); // anything in el not picked up yet
			var open = [];
			var w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
			var n;
			while ((n = w.nextNode())) {
				var it = engine.byNode.get(n);
				if (it && !it.done) open.push(it);
			}
			mine.forEach(function (it) { if (open.indexOf(it) < 0) open.push(it); });
			if (!open.length) { finish(); return; }
			// Read-aloud is waiting for these: move them to the front of the queue
			engine.queue = open.concat(engine.queue.filter(function (i) { return open.indexOf(i) < 0; }));
			var left = open.length;
			open.forEach(function (it) { it.waiters.push(function () { if (--left === 0) finish(); }); });
			pump(engine.run);
		});
	}

	window.erLang = {
		current: function () { return engine.lang || engine.pending || null; },
		ready: ready
	};

	// --- How-to for the browser's own translation ------------------

	function hintHtml() {
		var ua = navigator.userAgent;
		var ios = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
		var android = /Android/.test(ua);
		var how;
		if (ios) {
			how = '<b>Safari:</b> tap <i>aA</i> in the address bar, then <i>Translate</i>.';
		} else if (/Firefox\//.test(ua)) {
			how = android
				? '<b>Firefox:</b> menu <i>⋮</i>, then <i>Translate page</i>.'
				: '<b>Firefox:</b> click the translation icon in the address bar.';
		} else if (/SamsungBrowser/.test(ua)) {
			how = '<b>Samsung Internet:</b> menu, then <i>Translate</i>.';
		} else if (android) {
			how = '<b>Chrome / Edge:</b> menu <i>⋮</i>, then <i>Translate…</i>';
		} else if (/Safari\//.test(ua) && !/Chrome|Chromium|Edg\//.test(ua)) {
			how = '<b>Safari:</b> click the translate icon in the address bar.';
		} else {
			how = '<b>Chrome / Edge:</b> right-click anywhere on the page, then <i>Translate to …</i>';
		}
		return '<strong>Read this page in your language</strong>' +
		       '<p>Your browser can translate it for you:</p><p>' + how + '</p>';
	}

	function showHint(note) {
		hasEngine = false;
		panel.classList.remove('is-loading');
		select.hidden = true;
		hint.innerHTML = (note ? '<p>' + note + '</p>' : '') + hintHtml();
		hint.hidden = false;
	}

	// --- Language list ---------------------------------------------

	function setLoading(text) {
		if (text) {
			loadingText.textContent = text;
			panel.classList.add('is-loading');
		} else {
			panel.classList.remove('is-loading');
		}
	}

	function buildList() {
		if (listPromise) return listPromise;
		setLoading('Loading languages…');
		listPromise = Promise.all(LANGS.map(function (code) {
			return availability(code).then(function (a) { return a === 'unavailable' ? null : code; });
		})).then(function (codes) {
			codes = codes.filter(Boolean);
			if (!codes.length) { showHint(); return; }
			select.add(new Option(langName(SOURCE) + ' (original)', ''));
			codes.forEach(function (code) { select.add(new Option(langName(code), code)); });
			syncSelect();
			setLoading(null);
			select.hidden = false;
		});
		return listPromise;
	}

	function syncSelect() {
		if (select.options.length) select.value = engine.lang || engine.pending || '';
	}

	function onSelect() {
		var code = select.value;
		if (!code) {
			if (engine.lang || engine.pending) {
				restore();
				store.del();
				announce('Page shown in its original language');
			}
			closePanel();
			return;
		}
		var name = langName(code);
		setLoading('Preparing ' + name + '…');
		announce('Preparing ' + name);
		// Called right here, inside the visitor's selection: a missing language pack
		// is fetched by the browser now, with its progress shown in the spinner line
		translateTo(code, function (loaded) {
			setLoading('Preparing ' + name + '… ' + Math.round(loaded * 100) + '%');
		}).then(function () {
			if (engine.lang !== code) return;
			store.set(code);
			setLoading(null);
			announce('Page translated to ' + name);
			closePanel();
		}, function () {
			store.del();
			syncSelect();
			showHint('Your browser could not prepare ' + name + ' right now.');
		});
	}

	// --- Panel ---------------------------------------------------

	function openPanel() {
		panel.classList.add('visible');
		panel.setAttribute('aria-hidden', 'false');
		toggle.setAttribute('aria-expanded', 'true');
		toggle.setAttribute('aria-label', 'Close language switcher');
		announce('Language selector opened');

		if (!hasEngine) {
			if (hint.hidden) showHint();
			return;
		}
		buildList().then(function () {
			syncSelect();
			if (!select.hidden) select.focus();
		});
	}

	function closePanel() {
		panel.classList.remove('visible');
		panel.setAttribute('aria-hidden', 'true');
		toggle.setAttribute('aria-expanded', 'false');
		toggle.setAttribute('aria-label', 'Open language switcher');
		toggle.focus();
	}

	function togglePanel() {
		if (panel.classList.contains('visible')) { closePanel(); announce('Language selector closed'); }
		else { openPanel(); }
	}

	// --- Init ----------------------------------------------------

	function init() {
		panel       = document.getElementById('er-lang-panel');
		toggle      = document.getElementById('er-lang-toggle');
		announcer   = document.getElementById('er-lang-announcer');
		loadingText = document.getElementById('er-lang-loading-text');
		select      = document.getElementById('er-lang-select');
		hint        = document.getElementById('er-lang-hint');

		toggle.addEventListener('click', togglePanel);
		select.addEventListener('change', onSelect);

		document.addEventListener('click', function (e) {
			if (!panel.classList.contains('visible')) return;
			if (panel.contains(e.target) || toggle.contains(e.target)) return;
			closePanel();
		});

		document.addEventListener('keydown', function (e) {
			if (!panel.classList.contains('visible')) return;
			if (e.key === 'Escape') { closePanel(); return; }
			if (e.key === 'Tab') {
				var focusable = panel.querySelectorAll('select:not([hidden]), button, a[href], [tabindex]:not([tabindex="-1"])');
				if (!focusable.length) return;
				var first = focusable[0];
				var last  = focusable[focusable.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); toggle.focus(); }
				else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); toggle.focus(); }
			}
		});

		// Next page / next visit: same language again, but only if its pack is already
		// installed (no download without a click). Otherwise forget the choice.
		var saved = store.get();
		if (saved === SOURCE) saved = null; // the page is already in that language; the choice stays for other pages
		if (saved && LANGS.indexOf(saved) < 0) { store.del(); saved = null; }
		if (saved && !hasEngine) store.del();
		if (saved && hasEngine) {
			engine.pending = saved;
			availability(saved).then(function (a) {
				if (a === 'available') return translateTo(saved);
				throw new Error(a);
			}).catch(function () {
				if (engine.lang) return;
				engine.pending = null;
				store.del();
				emit();
			});
		}
	}

	init(); // the panel and the toggle are printed right above this script
})();
</script>

	<?php
});
