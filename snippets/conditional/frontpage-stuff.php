<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* ============================================================================
 * FRONTPAGE STUFF — front page only. Scope: front-end.
 *   1  Cover       [er_frontpage_cover]: turning logo on --color-6, CSS only
 *   2  Styles      brand header, values row, stat tiles
 *   3  Portals     hover of the cards (.er-portal)
 *   4  Surprise    /surprise/ redirects to a random published entry
 * Feature code that merely also runs on the front page stays with its feature.
 * ========================================================================== */

/* Cover logo: the ericroth.org lettering as a ring (SVG, gradient included,
   centred on the knot) and the knot (WebP). */
defined( 'ER_FP_RING' ) || define( 'ER_FP_RING',
	'https://ericroth.org/wp-content/uploads/2026/11/er-cover-ring.svg' );
defined( 'ER_FP_KNOT' ) || define( 'ER_FP_KNOT',
	'https://ericroth.org/wp-content/uploads/2026/11/er-cover-knot.webp' );

/* Cover words: the outlined text running along the left edge. */
defined( 'ER_FP_WORDS' ) || define( 'ER_FP_WORDS', 'ericroth.org | ' );

add_action( 'init', static function () {
	add_shortcode( 'er_frontpage_cover', 'er_fp_cover_shortcode' );
}, 20 );

/* ============================================================================
 * 1  COVER — [er_frontpage_cover]
 *    Place first, as a direct child of a Group (Shortcode or Custom HTML block),
 *    and give that Group the class er-cover-host (Block settings → Advanced →
 *    Additional CSS class(es)); it makes the Group the full-height cover. If the
 *    Group is ever rebuilt, add the class again.
 *    First paint: The plain --color-6 background, nothing else. After load +
 *    idle: The two logo images load, fade in and turn, and the words
 *    (ER_FP_WORDS, outlined) fade in at the left edge and run
 *    slowly downwards, reading bottom to top (CSS animations, transform and
 *    opacity only; the script only builds the words and sets their speed).
 *    Phones and portrait screens: No words. Bots and headless browsers:
 *    Background only. Reduced motion: Logo and words fade in and stand still.
 *    Off screen or hidden tab: Paused.
 *    -------
 *    SETTINGS (position, size, strength, speed of ring, knot and words): The
 *    block at the top of er_fp_cover_css() below, one commented line each.
 *    Where the words switch off: the @container line marked "words off".
 *    Size follows the cover box, not the window, so it fits the content height;
 *    "tall" values apply when the cover is taller than wide (phones, portrait).
 *    Card hover: Module 3. Number colours and stat tiles: Module 2.
 *    Changing the logo: Upload the files, set ER_FP_RING / ER_FP_KNOT. The ring
 *    image must be square with the ring centred, or it wobbles while turning.
 * ========================================================================== */

function er_fp_cover_shortcode() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	add_action( 'wp_footer', 'er_fp_cover_script', 20 );

	return '<style id="er-cover-css">' . er_fp_cover_css() . '</style>'
		. '<div class="er-cover" aria-hidden="true" data-ring="' . esc_url( ER_FP_RING )
		. '" data-knot="' . esc_url( ER_FP_KNOT )
		. '" data-words="' . esc_attr( ER_FP_WORDS ) . '"></div>';
}

function er_fp_cover_script() {
	wp_print_inline_script_tag( er_fp_cover_js(), array( 'id' => 'er-cover-js' ) );
}

function er_fp_cover_css() {
	return <<<'CSS'

/* ===== COVER SETTINGS: change the values here, nothing else needs touching ===== */
div.er-cover {
--er-logo-x: 75%;							/* where ring and knot sit across the width (50% = centre) */
--er-logo-x-tall: 85%;						/* the same on phones / portrait screens */
--er-ring-size: max(150cqh,118cqw);			/* ring diameter: % of cover height (cqh) or width (cqw), the larger wins */
--er-ring-size-tall: max(200cqw, 95cqh);	/* the same on phones / portrait screens */
--er-ring-opacity: .25;						/* lettering strength: 0 = invisible, 1 = full */
--er-ring-turn: 75s;						/* one full turn of the lettering (higher = slower) */
--er-knot-size: max(60cqh,42cqw);			/* knot width: % of cover height or width, the larger wins */
--er-knot-size-tall: 110cqw;				/* the same on phones / portrait screens */
--er-knot-opacity: .25;						/* knot strength */
--er-knot-turn: 25s;						/* one full 3D turn of the knot */
--er-words-size: clamp(60px, 12cqh, 150px);	/* letter size of the words at the left edge */
--er-words-colour: var(--color-7);			/* outline colour of the words */
--er-words-stroke: 1px;						/* outline thickness */
--er-words-opacity: .25;					/* words strength: 0 = invisible, 1 = full */
--er-words-speed: 75;						/* speed in pixels per second (a plain number, no unit) */
--er-words-x: 0.5em;						/* nudge from the left edge (negative = further out) */
--er-words-gap: 0.0em;						/* space between one repeat of the words and the next */
--er-fade-in: 1.75s;						/* fade-in of logo and words after the page has loaded */
}
/* ===== end of settings ===== */

.wp-block-group.er-cover-host {position: relative; display: flex; flex-direction: column;
justify-content: safe center; min-height: 100svh; width: auto; max-width: none; margin: 0; overflow: clip;
background-color: var(--color-6);}
:where(.wp-block-group.er-cover-host) {--er-link: var(--color-11); --er-link-hover: var(--color-12);}
:empty:has(+ .wp-block-group.er-cover-host) {display: none;}	/* hide the empty block WordPress sometimes leaves just before the cover */
.wp-block-group.er-cover-host>:not(.er-cover) {position: relative; z-index: 1; width: 100%;}
.wp-block-group.er-cover-host>.er-cover+* {margin-top: 0;}
div.er-cover {position: absolute; inset: 0; z-index: 0; max-width: none;
margin: 0; overflow: hidden; pointer-events: none; container-type: size; perspective: 150cqmax;}
.er-cover img {position: absolute; left: var(--er-logo-x); top: 50%; max-width: none; height: auto; translate: -50% -50%;
opacity: 0; transition: opacity var(--er-fade-in) ease; will-change: transform;}
.er-cover .er-cover-ring {width: var(--er-ring-size); animation: er-cover-ring var(--er-ring-turn) linear infinite;}
.er-cover .er-cover-knot {width: var(--er-knot-size); animation: er-cover-knot var(--er-knot-turn) linear infinite;}
/* Words: one line set sideways at the left edge, reading bottom to top. Built and timed by the script. */
.er-cover .er-cover-words {position: absolute; top: 0; left: var(--er-words-x); writing-mode: vertical-rl; rotate: 180deg;
font-size: var(--er-words-size); font-weight: var(--er-fw-normal, 400); line-height: 1; text-box: trim-both cap alphabetic;
white-space: nowrap; color: transparent; -webkit-text-stroke: var(--er-words-stroke) var(--er-words-colour);
opacity: 0; transition: opacity var(--er-fade-in) ease; animation: er-cover-words linear infinite; will-change: transform;}
.er-cover .er-cover-words > span {padding-inline-end: var(--er-words-gap);}
@container (aspect-ratio < 1) {
.er-cover img {left: var(--er-logo-x-tall);}
.er-cover .er-cover-ring {width: var(--er-ring-size-tall);}
.er-cover .er-cover-knot {width: var(--er-knot-size-tall);}}
@container (aspect-ratio < 1) or (width < 768px) {.er-cover .er-cover-words {display: none;}}	/* words off: phones, portrait, narrow */
.er-cover.is-on .er-cover-ring {opacity: var(--er-ring-opacity);}
.er-cover.is-on .er-cover-knot {opacity: var(--er-knot-opacity);}
.er-cover.is-on .er-cover-words {opacity: var(--er-words-opacity);}
.er-cover.is-paused img, .er-cover.is-paused .er-cover-words {animation-play-state: paused;}
@keyframes er-cover-ring {to {rotate: -360deg;}}
@keyframes er-cover-knot {from {transform: rotate3d(1, 1, 1, 0deg);} to {transform: rotate3d(1, 1, 1, 360deg);}}	/* tumbles like the logo at the top */
@keyframes er-cover-words {from {translate: 0 -50%;} to {translate: 0 0;}}
@media (prefers-reduced-motion: reduce) {.er-cover img, .er-cover .er-cover-words {animation: none;}}
CSS;
}

function er_fp_cover_js() {
	return <<<'JS'
(() => {
const root = document.querySelector('.er-cover');
if (!root || navigator.webdriver || /lighthouse|headlesschrome/i.test(navigator.userAgent)) return;

/* Both images are requested only here, after load: nothing of the cover competes with FCP / LCP. */
const img = (cls, src) => {
	const i = new Image();
	i.className = cls; i.alt = ''; i.src = src;
	return i.decode().then(() => i);
};

/* Words at the left edge: repeated until taller than the cover, then doubled, so moving
   down by one half loops seamlessly. Duration = length of one half / --er-words-speed. */
function words() {
	root.querySelector('.er-cover-words')?.remove();	/* remove old words first, so this can run again after a resize */
	const text = root.dataset.words;
	if (!text) return;
	const unit = () => {
		const s = document.createElement('span');
		s.textContent = text;
		return s;
	};
	const w = document.createElement('div');
	w.className = 'er-cover-words';
	root.prepend(w);
	w.append(unit());
	if (!w.offsetHeight) { w.remove(); return; }
	const need = root.offsetHeight * 1.3;	/* 30 % taller than the cover, so no gap shows in the loop */
	while (w.offsetHeight < need && w.children.length < 40) w.append(unit());	/* brake: never more than 40 repeats */
	const half = w.offsetHeight;
	[...w.children].forEach((s) => w.append(s.cloneNode(true)));
	const speed = parseFloat(getComputedStyle(root).getPropertyValue('--er-words-speed'));
	if (speed > 0) w.style.animationDuration = (half / speed) + 's';
}

function start() {
	Promise.all([img('er-cover-ring', root.dataset.ring), img('er-cover-knot', root.dataset.knot)]).then((imgs) => {
		words();
		root.append(...imgs);
		requestAnimationFrame(() => requestAnimationFrame(() => root.classList.add('is-on')));	/* wait two frames: images must be drawn once at opacity 0, or they pop in without fading */
		/* Rebuild the words when the cover changes size (window resized, tablet turned), 200 ms after the last change */
		let size = root.offsetWidth + 'x' + root.offsetHeight, timer;
		new ResizeObserver(() => {
			const now = root.offsetWidth + 'x' + root.offsetHeight;
			if (now === size) return;
			size = now;
			clearTimeout(timer);
			timer = setTimeout(words, 200);
		}).observe(root);
		/* Pause while off screen (hidden tabs: the browser pauses by itself) */
		new IntersectionObserver((e) => root.classList.toggle('is-paused', !e[0].isIntersecting)).observe(root);
	}).catch(() => {});	/* an image failed to load: the cover simply stays plain */
}

const idle = window.requestIdleCallback || ((f) => setTimeout(f, 300));
const go = () => idle(start, { timeout: 2500 });	/* 300 ms: wait in browsers without requestIdleCallback; 2500 ms: start at the latest after this */
if (document.readyState === 'complete') go(); else window.addEventListener('load', go, { once: true });
})();
JS;
}

/* ============================================================================
 * 2  STYLES — brand header, values row and stat tiles. rotate3d keyframe:
 *    Eric Companion CSS.
 * ========================================================================== */

add_action( 'wp_head', 'er_fp_styles', 10 );

function er_fp_styles() {
	if ( ! is_front_page() ) {
		return;
	}
	echo '<style id="er-frontpage-css">' . er_fp_styles_css() . '</style>' . "\n";
}

function er_fp_styles_css() {
	return <<<'CSS'
	/* Brand header */
	.er-brand-frontpage {display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 1.75rem;}
	.er-brand-frontpage-logo img {width: clamp(45px, 9vw, 85px); aspect-ratio: 1 / 1; height: auto; animation: rotate3d 5s linear infinite; position: relative; top: 0.05em;}	/* top: optical nudge, set by eye */
	.er-brand-frontpage-logo img:hover {animation-play-state: paused;}
	.er-brand-frontpage-title {font-size: clamp(2rem, 6vw, 3.5rem); font-weight: var(--er-fw-bold); color: var(--color-7); line-height: 1; letter-spacing: 0.1em; text-shadow: 0.10em 0.10em 0.15em rgba(0, 0, 0, 0.25);}
	.er-brand-frontpage-teaser {display: block; width: 100%; max-width: 850px; box-sizing: border-box; margin: 0 auto; border-radius: 25px;}

	/* Stat tiles over the cover (Views & Like | Dislike Voting): front-page colours.
	   Size, squares, centring and phone layout come from the Voting snippet. */
	.home p:has(> .er-stat-line) {gap: 35px;}
	@media (max-width: 600px) {.home p:has(> .er-stat-line) {gap: 10px;}}
	.home .er-stat-line {background: var(--color-10) !important; border: 1px solid var(--color-4) !important; color: var(--color-5) !important;}
	.home .er-stat-foot {border-top-color: var(--color-3) !important; color: var(--color-5) !important;}
	.home .er-stat-icon {filter: brightness(0) invert(1) brightness(0.95) !important;}	/* icons near-white whatever their colour: black, then inverted to white, then 5 % darker */

	/* Counted values row (Number Counter). Each number reserves the width of its
	   final value (hidden copy behind it), so the row does not shift while counting. */
	.er-fp-values .er-num {display: inline-grid; font-size: var(--er-fs-xxl); vertical-align: -0.1em; margin-inline-start: -0.1em;}	/* -0.1em: optical nudges, set by eye */
	.er-fp-values .er-num > * {grid-area: 1 / 1; text-align: center; text-box: trim-both cap alphabetic;}
	.er-fp-values .er-num-final {visibility: hidden;}
	.er-fp-values .counter-value {font-size: inherit; vertical-align: baseline; color: var(--color-11); transition: color 0.2s ease;}
	.er-fp-values a:is(:hover, :focus-visible) .counter-value {color: var(--color-12);}
	@media (max-width: 600px) {.er-fp-values .er-num {font-size: var(--er-fs-xl);}}

	/* Each value is a link: the number carries the dark link colours (--color-11, hover
	   --color-12, rule above); the word keeps the row's text colour (color:inherit on the
	   link in the editor). The padding keeps the row's spacing. text-box trims the fonts'
	   built-in space (number: above the digits, link: below the baseline). */
	.er-fp-values a {display: inline-block; padding: 0.65em 0.5em 0.70em 0.5em; line-height: 1.1; text-box: trim-end cap alphabetic;}	/* padding: optical nudge, set by eye */

	/* Space above and below the content once it is taller than the screen, so it clears the fixed header */
	@media (max-width: 1200px) {.wp-block-group.er-cover-host > .wp-block-group {padding-top: var(--wp--preset--spacing--x-large); padding-bottom: var(--wp--preset--spacing--x-large);}}
CSS;
}

/* ============================================================================
 * 3  PORTALS — the portal cards (.er-portal) in their grid (.er-portals).
 *    Columns: 4 side by side, 2 × 2 below 1200px, so a card never sits
 *    alone in a row (the editor's auto grid would go 4-3-2-1).
 *    Hover: scale 1.05, background --color-6 (the dark-mode element hover, as
 *    on the Display Posts listings and counter discs) and the border turns
 *    --color-12, 0.2 s. The card's background and border (--color-11, the link
 *    colour, so it reads as clickable) are set in the editor; !important beats
 *    its inline colours. Whole-card click comes from the heading
 *    link + .is-clickable-card (Eric Companion PHP 9f).
 * ========================================================================== */

add_action( 'wp_head', 'er_fp_portals_styles', 10 );

function er_fp_portals_styles() {
	if ( ! is_front_page() ) {
		return;
	}
	echo '<style id="er-portals-css">' . er_fp_portals_css() . '</style>' . "\n";
}

function er_fp_portals_css() {
	return <<<'CSS'
	.wp-block-group.er-portals {grid-template-columns: repeat(4, minmax(0, 1fr));}
	@media (max-width: 1200px) {.wp-block-group.er-portals {grid-template-columns: repeat(2, minmax(0, 1fr));}}
	@media (max-width: 600px) {.wp-block-group.er-portals {gap: 12px;}}
	.er-portal {transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;}
	.er-portal:hover, .er-portal:focus-within {transform: scale(1.05); background: var(--color-6) !important; border-color: var(--color-12) !important;}
CSS;
}

/* ============================================================================
 * 4  SURPRISE — /surprise/ sends the visitor to a random published entry
 *    (posts, pages, interests, quotes, traits; no password-protected ones).
 *    Excluded IDs = the exclude list → same as [display-posts] random slider.
 *    Never cached, so every call picks anew.
 * ========================================================================== */

add_action( 'template_redirect', 'er_fp_surprise', 1 );

function er_fp_surprise() {
	global $wp;
	if ( 'surprise' !== trim( (string) $wp->request, '/' ) ) {
		return;
	}
	$ids = get_posts( array(
		'post_type'      => array( 'post', 'page', 'my-interests', 'my-quotes', 'my-traits' ),
		'post_status'    => 'publish',
		'has_password'   => false,
		'post__not_in'   => array( 14581, 179, 149904, 149998, 150200, 150120, 150034, 150233, 150223, 113713, 123635, 150455, 150449 ),
		'posts_per_page' => 1,
		'orderby'        => 'rand',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	do_action( 'litespeed_control_set_nocache', 'er surprise redirect' );
	nocache_headers();
	wp_safe_redirect( $ids ? get_permalink( $ids[0] ) : home_url( '/' ), 302 );
	exit;
}
