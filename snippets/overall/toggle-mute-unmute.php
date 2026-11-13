<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ===========================================================================
// TOGGLE - MUTE | UNMUTE (Text-to-Speech)
// Markup for the toggle itself lives in the theme: parts/footer.html
// ===========================================================================

// =================================
// STYLE IN HEAD
// =================================

add_action('wp_head', function () {
	if (is_front_page()) return;
	?>
	<style>
	/* TTS Toggle Switch */
	#tts-toggle-btn {gap: 10px;} /* flex + centring from .flex-container (style.css §4) */
	#tts-toggle-btn img {opacity: 0.5;}
	#tts-toggle-btn input[type='checkbox'] {position: absolute; opacity: 0; width: 0; height: 0;}
	#tts-toggle-btn input[type='checkbox']:focus-visible + .toggle-visual {outline: var(--a11y-focus-width) solid var(--color-8); outline-offset: var(--a11y-focus-offset);}
	#tts-toggle-btn .toggle-visual {
		background: var(--color-3);
		border: 1px solid var(--color-4);
		border-radius: 50px;
		cursor: pointer;
		position: relative;
		transition: all ease-in-out 0.3s;
		width: 50px;
		height: 25px;
	}
	#tts-toggle-btn .toggle-visual::after {
		background: var(--color-4);
		border-radius: 50%;
		content: '';
		position: absolute;
		left: 1px;
		top: 1px;
		transition: all ease-in-out 0.3s;
		width: 21px;
		height: 21px;
	}
	#tts-toggle-btn input[type='checkbox']:checked + .toggle-visual {background: var(--color-10); border-color: var(--color-3);}
	#tts-toggle-btn input[type='checkbox']:checked + .toggle-visual::after {background: var(--color-3); transform: translateX(25px);}

	/* Screen reader only */
	#tts-status, .tts-toggle-btn-accessibility-label, .tts-cue {
		position: absolute;
		left: -9999px;
		width: 1px;
		height: 1px;
		overflow: hidden;
	}
	/* A cue in front of a container's first block must not cost that block its
	   "first child, no top margin" status (up to three cues: slideshow, related, video) */
	.tts-cue:first-child + *, .tts-cue:first-child + .tts-cue + *, .tts-cue:first-child + .tts-cue + .tts-cue + * {margin-block-start: 0;}

	/* TTS Controls (Play, Pause, Stop) */
	#tts-controls {
		position: fixed;
		bottom: 27.5px;
		left: 20px;
		z-index: 9999;
		display: flex;
		align-items: center;
		gap: 10px;
		opacity: 0;
		pointer-events: none;
		transition: opacity 0.3s;
	}
	#tts-controls.show {opacity: 1; pointer-events: auto;}
	#tts-controls button {
		padding: 5px 10px;
		border: none;
		border-radius: 5px;
		background: var(--color-3);
		color: var(--color-8);
		font-size: var(--er-fs-sm);
		line-height: 1;
		cursor: pointer;
		transition: background 0.2s;
		-webkit-appearance: none;
		appearance: none;
	}
	#tts-controls button:hover {background: var(--color-4);}
	#tts-controls button:focus-visible {outline: var(--a11y-focus-width) solid var(--color-8); outline-offset: var(--a11y-focus-offset);}
	</style>
	<?php
});

// =================================
// MARKUP & SCRIPT IN FOOTER
// =================================

add_action('wp_footer', function () {
	if (is_front_page()) return;
	?>

<div id="tts-controls" role="group" aria-label="Text-to-speech controls">
	<button type="button" id="tts-play"  aria-label="Play text-to-speech"  title="Play">&#9654;</button>
	<button type="button" id="tts-pause" aria-label="Pause text-to-speech" title="Pause">&#9208;</button>
	<button type="button" id="tts-stop"  aria-label="Stop text-to-speech"  title="Stop">&#9209;</button>
</div>

<script>
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fn); }
		else { fn(); }
	}

	ready(function () {

		var els = {
			toggleBtn: document.getElementById('tts-toggle-btn'),
			toggle:    document.getElementById('tts-toggle-switch'),
			visual:    document.querySelector('#tts-toggle-btn .toggle-visual'),
			status:    document.getElementById('tts-status'),
			controls:  document.getElementById('tts-controls'),
			play:      document.getElementById('tts-play'),
			pause:     document.getElementById('tts-pause'),
			stop:      document.getElementById('tts-stop')
		};

		// No speech synthesis: hide the UI entirely, do nothing else.
		if (!('speechSynthesis' in window)) {
			if (els.toggleBtn) els.toggleBtn.style.display = 'none';
			if (els.controls)  els.controls.style.display  = 'none';
			return;
		}
		if (!els.toggle) return;

		var synth = window.speechSynthesis;

		// Keep in sync with $er_langs in the "Language Switcher" snippet.
		var LANG_MAP = {
			'ar': 'ar-SA', 'da': 'da-DK', 'de': 'de-DE', 'es': 'es-ES',
			'fi': 'fi-FI', 'fr': 'fr-FR', 'hi': 'hi-IN', 'id': 'id-ID',
			'it': 'it-IT', 'ja': 'ja-JP', 'ko': 'ko-KR', 'no': 'no-NO',
			'ru': 'ru-RU', 'sv': 'sv-SE', 'th': 'th-TH', 'tl': 'tl-PH',
			'tr': 'tr-TR', 'vi': 'vi-VN', 'zh-CN': 'zh-CN', 'en': 'en-US'
		};

		var MAX_CHUNK = 180; // Chromium drops utterances after ~15s, so speak in small pieces

		var state = {
			session: 0,        // invalidates callbacks of a cancelled run
			queue: [],
			index: 0,
			paused: false,
			debounce: null,
			watchdog: null,
			autoTimer: null,
			blocks: null,      // translated page: live blocks still to be read
			waiting: false     // translated page: waiting for a block to be translated
		};

		// --- Safe localStorage ---------------------------------------

		var store = {
			get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
			set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
			remove: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
		};

		function status(msg) { if (els.status) els.status.textContent = msg; }

		// --- Voices ---------------------------------------------------

		function withVoices(cb) {
			if (synth.getVoices().length) { cb(); return; }
			var done = false;
			var go = function () {
				if (done) return;
				done = true;
				synth.removeEventListener('voiceschanged', go);
				cb();
			};
			synth.addEventListener('voiceschanged', go);
			setTimeout(go, 1000); // proceed anyway; the browser still has a default voice
		}

		function normLang(l) { return (l || '').replace('_', '-').toLowerCase(); }

		// Exact match first, then base language. null = let the browser decide via utterance.lang
		function pickVoice(target) {
			var voices = synth.getVoices() || [];
			var t = normLang(target);
			var base = t.split('-')[0];

			// Modern neural / network voices sound far better than the
			// legacy system voices (Windows SAPI in particular).
			var GOOD = /(google|natural|neural|enhanced|premium|siri|online|eloquence)/i;
			var POOR = /(david|zira|mark|hazel|sabina|helena|compact|espeak|festival|pico)/i;

			var candidates = voices.filter(function (v) { return normLang(v.lang) === t; });
			if (!candidates.length) {
				candidates = voices.filter(function (v) { return normLang(v.lang).split('-')[0] === base; });
			}
			if (!candidates.length) return null; // browser picks its own via utterance.lang

			function score(v) {
				var s = 0;
				if (GOOD.test(v.name)) s += 4;
				if (POOR.test(v.name)) s -= 4;
				if (v.localService === false) s += 2; // server-side voices are usually the good ones
				if (v.default) s += 1;
				return s;
			}

			candidates.sort(function (a, b) { return score(b) - score(a); });
			return candidates[0];
		}

		// Translated by the Language Switcher snippet (window.erLang), or by the
		// browser's own "Translate" (Chrome / Edge mark <html> with translated-ltr / -rtl)
		function ownLang() { return (window.erLang && window.erLang.current()) || null; }
		function browserTranslated() { return /translated-/.test(document.documentElement.className); }
		function isTranslated() { return !!ownLang() || browserTranslated(); }

		// Not translated: the page's own <html lang> (German on German pages). Browser
		// translation: best effort from <html lang>, which not every browser updates
		function targetLang() {
			var raw = ownLang() || document.documentElement.lang || 'en';
			return LANG_MAP[raw] || LANG_MAP[raw.split('-')[0]] || raw;
		}

		// --- Spoken cues for non-text content -------------------------

		// Rule 1: if the target sits inside a column, announce once in front of
		// the whole columns block instead of once per column.
		function cueAnchor(el) {
			if (!el.closest) return el;
			var col = el.closest('.wp-block-column');
			if (col) {
				var row = col.closest('.wp-block-columns');
				if (row && row.parentNode) return row;
			}
			return el;
		}

		function hasCueBefore(node, key) {
			var prev = node.previousElementSibling;
			while (prev && prev.classList.contains('tts-cue')) {
				if (prev.getAttribute('data-cue-key') === key) return true;
				prev = prev.previousElementSibling;
			}
			return false;
		}

		function addCue(selector, key, text) {
			document.querySelectorAll(selector).forEach(function (el) {
				var anchor = cueAnchor(el);
				if (!anchor.parentNode) return;
				if (hasCueBefore(anchor, key)) return;
				var cue = document.createElement('div');
				cue.className = 'tts-cue';
				cue.setAttribute('data-cue-key', key);
				cue.setAttribute('aria-hidden', 'true'); // for the TTS voice only, screen readers skip it
				cue.textContent = text;
				anchor.parentNode.insertBefore(cue, anchor);
			});
		}

		// Every slideshow (all profile classes start with "slideshow-", see eric-slider-init):
		// announced, never read. Related posts get their own wording, because they are a
		// slideshow above four entries and a plain grid below - excluded here so they never
		// collect both announcements at once.
		var SLIDESHOW = '[class*="slideshow-"]';
		var SLIDESHOW_SEL = SLIDESHOW + ':not(.er-related-posts)';

		// In the page from load on, so a translation picks the cues up together with the
		// page (the browser's own translation may skip nodes added after it has translated).
		// Idempotent, so re-running on enable catches content added since load.
		// Delete a line here to drop that announcement entirely.
		function insertCues() {
			addCue(SLIDESHOW_SEL, 'slideshow', 'A slideshow follows here.');
			addCue('.er-related-posts', 'related', 'Related posts follow here.');
			addCue('.wp-block-embed-youtube', 'video', 'A video follows here.');
		}

		// --- Text extraction ------------------------------------------

		// Text-carrying elements. The last few are div/span based containers used
		// by Display Posts and the theme templates — without them, card titles
		// are silently skipped.
		var BLOCK_SEL = 'h1,h2,h3,h4,h5,h6,p,li,blockquote,figcaption,dt,dd,td,th,summary,' +
			'.tts-cue,.listing-item,.title,.excerpt,.page-description';

		function pruneClone(clone) {
			// Structural noise, navigation, and meta data — nothing to listen to.
			// 'tts-skip' is the manual opt-out: put that class on any block,
			// slideshow or not, and it is skipped entirely.
			clone.querySelectorAll(
				'nav,aside,footer,header,style,script,noscript,select,code,pre,img,svg,iframe,' +
				'.social-icons,.share-buttons,.tag-cloud,.tag-cloud-footer,' +
				'.like-dislike-container,.er-social-icon-row,.er-author-links,' +
				'.entry-meta,.er-reading-time,.post-stats,.er-breadcrumbs,.er-postnav,' +
				'.er-related-posts,.tts-skip,' +
				// Slideshows: the cue announces them, their slides say nothing. The controls are
				// listed too, because center-mode sliders keep them outside the slideshow element.
				SLIDESHOW + ',.eric-slider-controls'
			).forEach(function (el) { el.remove(); });

			// Collapsed accordions: read the heading, skip the hidden content
			clone.querySelectorAll('details:not([open])').forEach(function (d) {
				Array.prototype.slice.call(d.children).forEach(function (child) {
					if (child.tagName !== 'SUMMARY') child.remove();
				});
			});
			return clone;
		}

		function collectText(clone) {
			// Read block by block so sentences do not run into each other
			var parts = [];
			var blocks = [];     // data-tts-b of each part, to find the live block again

			clone.querySelectorAll(BLOCK_SEL).forEach(function (el) {
				if (el.querySelector(BLOCK_SEL)) return; // container, its children are handled

				var t = (el.textContent || '').replace(/\s+/g, ' ').trim();
				if (!t) return;

				parts.push(/[.!?\u2026:;]$/.test(t) ? t : t + '.');
				blocks.push(el.getAttribute('data-tts-b'));
			});

			var text = parts.join(' ');
			if (text.length < 10) {
				text = (clone.textContent || '').replace(/\s+/g, ' ').trim();
			}
			return { text: text, blocks: blocks };
		}

		function getReadableText() {
			var main = document.querySelector('main') || document.body;
			if (!main) return { text: '', blocks: [] };

			// Tag every block, so a translated page can be read from the live page
			var live = main.querySelectorAll(BLOCK_SEL);
			Array.prototype.forEach.call(live, function (el, i) { el.setAttribute('data-tts-b', String(i)); });

			var page = collectText(pruneClone(main.cloneNode(true)));
			page.blocks = page.blocks.map(function (i) { return live[i]; });
			return page;
		}

		// Text of one accordion, without its heading (that was read already)
		function getDetailsText(details) {
			var clone = details.cloneNode(true);
			var summary = clone.querySelector('summary');
			if (summary) summary.remove();
			return collectText(pruneClone(clone)).text;
		}

		// Splits the text into pieces of at most max characters, at sentence ends where possible
		function chunkText(text, max) {
			var sentences = text.match(/[^.!?\u2026]+[.!?\u2026]*\s*/g) || [text];
			var chunks = [];
			var cur = '';

			sentences.forEach(function (s) {
				if (cur && (cur + s).length > max) {
					chunks.push(cur.trim());
					cur = s;
				} else {
					cur += s;
				}
				while (cur.length > max) {
					var cut = cur.lastIndexOf(' ', max);
					if (cut < max * 0.5) cut = max;
					chunks.push(cur.slice(0, cut).trim());
					cur = cur.slice(cut);
				}
			});

			if (cur.trim()) chunks.push(cur.trim());
			return chunks.filter(function (c) { return c.length > 0; });
		}

		// --- Playback --------------------------------------------------

		function startWatchdog() {
			clearInterval(state.watchdog);
			// Chromium occasionally stalls mid-queue; resume() is a no-op when healthy.
			state.watchdog = setInterval(function () {
				if (!synth.speaking) { clearInterval(state.watchdog); return; }
				if (!state.paused && synth.paused) synth.resume();
			}, 8000);
		}

		function speakNext(session, voice, lang) {
			if (session !== state.session) return;

			if (state.index >= state.queue.length) {
				if (state.blocks && state.blocks.length) { nextBlock(session, voice, lang); return; }
				status('Text-to-speech finished.');
				store.remove('ttsPlaying');
				clearInterval(state.watchdog);
				return;
			}

			var text = state.queue[state.index++];
			var u = new SpeechSynthesisUtterance(text);
			u.lang = lang;
			if (voice) u.voice = voice;

			u.onstart = function () {
				if (session !== state.session) return;
				state.paused = false;
				if (state.index === 1) status('Text-to-speech started.');
			};
			u.onend = function () { speakNext(session, voice, lang); };
			u.onerror = function (e) {
				if (session !== state.session) return;
				var err = e && e.error;
				if (err === 'interrupted' || err === 'canceled') return;
				status('Error occurred during speech.');
				store.remove('ttsPlaying');
			};

			synth.speak(u);
		}

		// Translated page: read block by block from the live page. Each block is scrolled
		// into view when reading reaches it and read once it is translated (or after 5 s,
		// whatever is there). The Language Switcher reports that per block; the browser's
		// own translation only translates what is on screen or close to it.
		function nextBlock(session, voice, lang) {
			var el = state.blocks.shift();
			var r = el.getBoundingClientRect();
			var scrolled = r.top < 0 || r.bottom > window.innerHeight;
			if (scrolled) {
				var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
				// scrollTo, not scrollIntoView: that would also shift the slider tracks
				window.scrollTo({ top: r.top + window.scrollY - window.innerHeight / 3, behavior: reduce ? 'auto' : 'smooth' });
			}

			state.waiting = true;
			function read() {
				if (session !== state.session) return;
				state.waiting = false;
				var t = (pruneClone(el.cloneNode(true)).textContent || '').replace(/\s+/g, ' ').trim();
				if (t) {
					if (!/[.!?\u2026:;]$/.test(t)) t += '.';
					state.queue = state.queue.concat(chunkText(t, MAX_CHUNK));
				}
				startWatchdog(); // it stops itself whenever nothing is speaking, e.g. while waiting
				speakNext(session, voice, lang);
			}

			if (ownLang()) { window.erLang.ready(el, 5000).then(read); return; }

			// Browser's own translation (Chrome / Edge wrap what they translated in <font>):
			// done when this block or the next one has it, or when the batch for the
			// scrolled-to area has landed (a block of names or numbers may get no <font>).
			var fonts = document.getElementsByTagName('font');
			var before = fonts.length, count = before;
			var t0 = Date.now(), changed = t0;
			(function wait() {
				if (session !== state.session) return;
				var now = Date.now();
				if (fonts.length !== count) { count = fonts.length; changed = now; }
				var ready = !scrolled || el.querySelector('font') || (state.blocks[0] && state.blocks[0].querySelector('font')) ||
					(count > before && now - changed > 300);
				if (!ready && now - t0 < 5000) { setTimeout(wait, 100); return; }
				read();
			})();
		}

		function start(isAutoResume) {
			var page = getReadableText();
			if (page.text.length < 10) { status('No readable content found.'); return; }

			var lang = targetLang();

			withVoices(function () {
				var voice = pickVoice(lang);

				state.session++;
				var translated = isTranslated();
				state.queue = translated ? [] : chunkText(page.text, MAX_CHUNK);
				state.blocks = translated ? page.blocks : null;
				state.index = 0;
				state.paused = false;

				synth.cancel();
				speakNext(state.session, voice, lang);
				store.set('ttsPlaying', 'true');
				startWatchdog();

				// Browsers block speech without a user gesture (Safari/iOS in particular).
				// If nothing started, tell the user instead of pretending it plays.
				if (isAutoResume) {
					clearTimeout(state.autoTimer);
					state.autoTimer = setTimeout(function () {
						if (!synth.speaking && !synth.pending && !state.waiting) {
							store.remove('ttsPlaying');
							status('Press play to continue reading.');
						}
					}, 1500);
				}
			});
		}

		function pause() {
			if (synth.speaking && !synth.paused) {
				synth.pause();
				state.paused = true;
				store.remove('ttsPlaying'); // do not auto-resume on the next page
				status('Text-to-speech paused.');
			}
		}

		function resume() {
			if (synth.paused) {
				synth.resume();
				state.paused = false;
				store.set('ttsPlaying', 'true');
				startWatchdog();
				status('Text-to-speech resumed.');
			}
		}

		// Full reset — everything that could keep stale state must be cleared here.
		function stop() {
			state.session++;
			state.queue = [];
			state.index = 0;
			state.blocks = null;
			state.waiting = false;
			state.paused = false;
			clearInterval(state.watchdog);
			clearTimeout(state.autoTimer);
			synth.cancel();
			store.remove('ttsPlaying');
		}

		// --- Enable / disable ------------------------------------------

		function setEnabled(on) {
			els.toggle.checked = on;
			els.toggle.setAttribute('aria-checked', on ? 'true' : 'false');
			if (els.controls) els.controls.classList.toggle('show', on);

			if (on) {
				store.set('ttsEnabled', 'true');
				insertCues();
				status('Text-to-speech enabled.');
			} else {
				store.remove('ttsEnabled');
				stop();
				status('Text-to-speech disabled.');
			}
		}

		// --- Wiring -----------------------------------------------------

		els.toggle.setAttribute('role', 'switch');
		els.toggle.setAttribute('aria-checked', els.toggle.checked ? 'true' : 'false');
		if (els.visual) els.visual.setAttribute('aria-hidden', 'true');

		// The visual span is decorative; clicking it drives the real checkbox
		if (els.visual) {
			els.visual.addEventListener('click', function () {
				els.toggle.checked = !els.toggle.checked;
				els.toggle.dispatchEvent(new Event('change'));
			});
		}

		els.toggle.addEventListener('change', function () {
			var on = els.toggle.checked;
			clearTimeout(state.debounce);
			state.debounce = setTimeout(function () {
				setEnabled(on);
				if (!on) return;
				var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
				window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
				setTimeout(function () { start(false); }, 400);
			}, 150);
		});

		if (els.play) {
			els.play.addEventListener('click', function () {
				if (synth.paused) { resume(); }
				else if (!synth.speaking) {
					start(false);
				}
			});
		}
		if (els.pause) els.pause.addEventListener('click', pause);
		if (els.stop) {
			els.stop.addEventListener('click', function () {
				setEnabled(false); // calls stop() internally
			});
		}

		// An accordion opened mid-read: slot its content in right after the
		// chunk that is currently being spoken, then carry on as before.
		// 'toggle' does not bubble, so this listens in the capture phase.
		document.addEventListener('toggle', function (e) {
			var d = e.target;
			if (!d || d.tagName !== 'DETAILS' || !d.open) return;
			if (!synth.speaking && !synth.paused) return; // nothing running, nothing to do

			var text = getDetailsText(d);
			if (!text) return;

			var chunks = chunkText(text, MAX_CHUNK);
			if (!chunks.length) return;

			Array.prototype.splice.apply(state.queue, [state.index, 0].concat(chunks));
			status('Reading the section you just opened.');
		}, true);

		// pagehide instead of beforeunload: does not break the bfcache
		window.addEventListener('pagehide', function () {
			try { synth.cancel(); } catch (e) {}
		});

		// --- Restore previous state --------------------------------------

		insertCues();

		if (store.get('ttsEnabled')) {
			els.toggle.checked = true;
			els.toggle.setAttribute('aria-checked', 'true');
			if (els.controls) els.controls.classList.add('show');

			if (store.get('ttsPlaying')) {
				setTimeout(function () { start(true); }, 300);
			}
		}
	});
})();
</script>

	<?php
});
