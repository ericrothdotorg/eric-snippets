<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* ============================================================================
 * FRONTPAGE STUFF — front page only. Scope: front-end.
 *   1  Cover       [er_frontpage_cover]: turning logo on --color-6, CSS only
 *   2  Cover host  classes on the Group around the cover
 *   3  Styles      brand header, values row, stat tiles
 *   4  Portals     hover of the cards (.er-portal)
 *   5  Surprise    /surprise/ redirects to a random published entry
 * Feature code that merely also runs on the front page stays with its feature.
 * ========================================================================== */

/* Cover logo: the ericroth.org lettering as a ring (SVG, gradient included,
   centred on the knot) and the knot (WebP). */
defined( 'ER_FP_RING' ) || define( 'ER_FP_RING',
	'https://ericroth.org/wp-content/uploads/2026/11/er-cover-ring.svg' );
defined( 'ER_FP_KNOT' ) || define( 'ER_FP_KNOT',
	'https://ericroth.org/wp-content/uploads/2026/11/er-cover-knot.webp' );

add_action( 'init', static function () {
	add_shortcode( 'er_frontpage_cover', 'er_fp_cover_shortcode' );
}, 20 );

/* ============================================================================
 * 1  COVER — [er_frontpage_cover]
 *    Place first, as a direct child of a Group (Shortcode or Custom HTML block).
 *    First paint: The plain --color-6 background, nothing else. After load +
 *    idle: The two logo images load, fade in and turn and soft light in the
 *    logo colours fades in at the left edge (CSS animations, transform and
 *    opacity only; no animation script). Bots and headless browsers: Background
 *    only. Reduced motion: Logo and light fade in and stand still. Off screen
 *    or hidden tab: Paused.
 *    -------
 *    SETTINGS (position, size, strength, speed of ring, knot and light): The
 *    block at the top of er_fp_cover_css() below, one commented line each.
 *    Size follows the cover box, not the window, so it fits the content height;
 *    "tall" values apply when the cover is taller than wide (phones, portrait).
 *    Card hover: Module 4. Number colours and stat tiles: Module 3.
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
		. '" data-knot="' . esc_url( ER_FP_KNOT ) . '"></div>';
}

function er_fp_cover_script() {
	wp_print_inline_script_tag( er_fp_cover_js(), array( 'id' => 'er-cover-js' ) );
}

function er_fp_cover_css() {
	return <<<'CSS'

/* ===== COVER SETTINGS: change the values here, nothing else needs touching ===== */
div.er-cover {
--er-logo-x: 75%;						/* where ring and knot sit across the width (50% = centre) */
--er-logo-x-tall: 85%;					/* the same on phones / portrait screens */
--er-ring-size: max(150cqh,118cqw);		/* ring diameter: % of cover height (cqh) or width (cqw), the larger wins */
--er-ring-size-tall: max(200cqw,95cqh);	
--er-ring-opacity: .35;					/* lettering strength: 0 = invisible, 1 = full */
--er-ring-turn: 75s;					/* one full turn of the lettering (higher = slower) */
--er-knot-size: max(60cqh,42cqw);		
--er-knot-size-tall: 110cqw;				
--er-knot-opacity: .25;					/* knot strength */
--er-knot-turn: 25s;					/* one full 3D turn of the knot */
--er-light-width: 35%;					/* how far the light reaches in from the left */
--er-light-width-tall: 55%;				
--er-light-low: .50;					/* the light breathes between full and this strength */
--er-light-breath: 5s;					/* one breath, in or out */
--er-fade-in: 1.75s;					/* fade-in of logo and light after the page has loaded */
}
/* ===== end of settings ===== */

.wp-block-group.er-cover-host {position: relative; display: flex; flex-direction: column; justify-content: center;
justify-content: safe center; min-height: 100svh; width: auto; max-width: none; margin: 0; overflow: clip;
background-color: var(--color-6);}
:where(.wp-block-group.er-cover-host) {--er-link: var(--color-11); --er-link-hover: var(--color-12);}
.er-cover-anc.er-cover-anc {margin-top: 0;}
:empty:has(+ .wp-block-group.er-cover-host) {display: none;}
.wp-block-group.er-cover-host>:not(.er-cover) {position: relative; z-index: 1; width: 100%;}
.wp-block-group.er-cover-host>.er-cover+* {margin-top: 0;}
div.er-cover {position: absolute; inset: 0; z-index: 0; display: block; width: 100%; height: 100%; max-width: none;
margin: 0; overflow: hidden; pointer-events: none; container-type: size; perspective: 150vmax; perspective: 150cqmax;}
.er-cover img {position: absolute; left: var(--er-logo-x); top: 50%; max-width: none; height: auto; translate: -50% -50%;
opacity: 0; transition: opacity var(--er-fade-in) ease; will-change: transform;}
.er-cover .er-cover-ring {width: 150vh; width: var(--er-ring-size); animation: er-cover-ring var(--er-ring-turn) linear infinite;}
.er-cover .er-cover-knot {width: 60vh; width: var(--er-knot-size); animation: er-cover-knot var(--er-knot-turn) linear infinite;}
/* Light: eight soft ovals in the logo colours, top to bottom, faded out to the right */
div.er-cover::before {content: ""; position: absolute; top: -10%; bottom: -10%; left: -12%; width: var(--er-light-width); opacity: 0;
-webkit-mask: linear-gradient(90deg, #000 15%, rgba(0, 0, 0, .55) 45%, transparent); mask: linear-gradient(90deg, #000 15%, rgba(0, 0, 0, .55) 45%, transparent); background:
radial-gradient(100% 34% at 0% 6%, rgba(203, 49, 49, .3), transparent 75%),
radial-gradient(100% 34% at 0% 18.5%, rgba(254, 152, 48, .28), transparent 75%),
radial-gradient(100% 34% at 0% 31%, rgba(252, 201, 0, .24), transparent 75%),
radial-gradient(100% 34% at 0% 43.5%, rgba(152, 203, 48, .26), transparent 75%),
radial-gradient(100% 34% at 0% 56%, rgba(0, 152, 49, .28), transparent 75%),
radial-gradient(100% 34% at 0% 68.5%, rgba(0, 151, 151, .3), transparent 75%),
radial-gradient(100% 34% at 0% 81%, rgba(0, 100, 203, .34), transparent 75%),
radial-gradient(100% 34% at 0% 93.5%, rgba(152, 49, 152, .32), transparent 75%);}
@container (aspect-ratio < 1) {
.er-cover img {left: var(--er-logo-x-tall);}
.er-cover .er-cover-ring {width: var(--er-ring-size-tall);}
.er-cover .er-cover-knot {width: var(--er-knot-size-tall);}
div.er-cover::before {width: var(--er-light-width-tall); left: -20%;}}
.er-cover.is-on .er-cover-ring {opacity: var(--er-ring-opacity);}
.er-cover.is-on .er-cover-knot {opacity: var(--er-knot-opacity);}
.er-cover.is-on::before {opacity: 1; animation: er-cover-in var(--er-fade-in) ease, er-cover-glow var(--er-light-breath) ease-in-out var(--er-fade-in) infinite alternate;}
.er-cover.is-paused img, .er-cover.is-paused::before {animation-play-state: paused;}
@keyframes er-cover-ring {to {rotate: -360deg;}}
@keyframes er-cover-knot {from {transform: rotate3d(1, 1, 1, 0deg);} to {transform: rotate3d(1, 1, 1, 360deg);}}	/* tumbles like the logo at the top */
@keyframes er-cover-in {from {opacity: 0;}}
@keyframes er-cover-glow {to {opacity: var(--er-light-low);}}
@media (prefers-reduced-motion: reduce) {.er-cover img {animation: none;} .er-cover.is-on::before {animation: er-cover-in var(--er-fade-in) ease;}}
CSS;
}

function er_fp_cover_js() {
	$js = <<<'JS'
(() => {
const root = document.querySelector('.er-cover');
if (!root || !root.dataset.ring || navigator.webdriver || /lighthouse|headlesschrome/i.test(navigator.userAgent)) return;

/* Both images are requested only here, after load: nothing of the cover competes with FCP / LCP. */
const img = (cls, src) => {
	const i = new Image();
	i.className = cls; i.alt = ''; i.decoding = 'async'; i.src = src;
	return i.decode().then(() => i);
};

function start() {
	Promise.all([img('er-cover-ring', root.dataset.ring), img('er-cover-knot', root.dataset.knot)]).then((imgs) => {
		root.append(...imgs);
		requestAnimationFrame(() => requestAnimationFrame(() => root.classList.add('is-on')));
		/* Pause while off screen or in a hidden tab */
		let seen = true;
		const toggle = () => root.classList.toggle('is-paused', !seen || document.hidden);
		if ('IntersectionObserver' in window) new IntersectionObserver((e) => { seen = e[0].isIntersecting; toggle(); }).observe(root);
		document.addEventListener('visibilitychange', toggle);
	}).catch(() => {});
}

const idle = window.requestIdleCallback || ((f) => setTimeout(f, 300));
const go = () => idle(start, { timeout: 2500 });
if (document.readyState === 'complete') go(); else window.addEventListener('load', go, { once: true });
})();
JS;
	/* Strips indentation and blank lines: use only block comments in the JS. */
	return preg_replace( '/^\s+|\n\s*\n/m', '', $js );
}

/* ============================================================================
 * 2  COVER HOST — er-cover-host on the Group holding the shortcode, er-cover-anc
 *    on its parent and grandparent. Set server-side: :has() would restyle the
 *    cover on every counter tick.
 * ========================================================================== */

add_filter( 'render_block', 'er_fp_cover_mark_block', 10, 3 );

function er_fp_cover_mark_block( $html, $block, $instance = null ) {
	if ( ! is_front_page() ) {
		return $html;
	}
	$depth = er_fp_cover_depth( $block, $instance, 2 );
	if ( $depth < 0 ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	if ( $p->next_tag() ) {
		$p->add_class( 0 === $depth ? 'er-cover-host' : 'er-cover-anc' );
	}
	return $p->get_updated_html();
}

/* 0 = host Group, 1 = parent, 2 = grandparent, -1 = none. */
function er_fp_cover_depth( $block, $instance, $max ) {
	if ( er_fp_cover_is_host( $block ) ) {
		return 0;
	}
	if ( $max <= 0 ) {
		return -1;
	}
	foreach ( er_fp_cover_children( $block, $instance ) as $child ) {
		$d = er_fp_cover_depth( $child, null, $max - 1 );
		if ( $d >= 0 ) {
			return $d + 1;
		}
	}
	return -1;
}

function er_fp_cover_is_host( $block ) {
	if ( 'core/group' !== ( $block['blockName'] ?? '' ) ) {
		return false;
	}
	foreach ( $block['innerBlocks'] ?? array() as $inner ) {
		if ( in_array( $inner['blockName'] ?? '', array( 'core/shortcode', 'core/html' ), true )
			&& false !== strpos( (string) ( $inner['innerHTML'] ?? '' ), '[er_frontpage_cover' ) ) {
			return true;
		}
	}
	return false;
}

/* For Post Content: the blocks of the post it shows. */
function er_fp_cover_children( $block, $instance ) {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) ) {
		return $block['innerBlocks'] ?? array();
	}
	$id = ( $instance instanceof WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	static $cache = array();
	if ( ! isset( $cache[ $id ] ) ) {
		$content      = $id ? (string) get_post_field( 'post_content', $id ) : '';
		$cache[ $id ] = false !== strpos( $content, '[er_frontpage_cover' ) ? parse_blocks( $content ) : array();
	}
	return $cache[ $id ];
}

/* ============================================================================
 * 3  STYLES — brand header, values row and stat tiles. rotate3d keyframe:
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
	.er-brand-frontpage-logo img {width: clamp(45px, 9vw, 85px); aspect-ratio: 1 / 1; height: auto; animation: rotate3d 5s linear infinite; position: relative; top: 0.05em;}
	.er-brand-frontpage-logo img:hover {animation-play-state: paused;}
	.er-brand-frontpage-title {font-size: clamp(2rem, 6vw, 3.5rem); font-weight: var(--er-fw-bold); color: var(--color-7); line-height: 1; letter-spacing: 0.1em; text-shadow: 0.10em 0.10em 0.15em rgba(0, 0, 0, 0.25);}
	.er-brand-frontpage-teaser {display: block; width: 100%; max-width: 850px; box-sizing: border-box; margin: 0 auto; border-radius: 25px;}

	/* Stat tiles over the cover (Views & Like | Dislike Voting): front-page colours.
	   Size, squares, centring and phone layout come from the Voting snippet. */
	.page-id-179 p:has(> .er-stat-line) {gap: 35px;}
	@media (max-width: 600px) {.page-id-179 p:has(> .er-stat-line) {gap: 10px;}}
	.page-id-179 .er-stat-line {background: var(--color-10) !important; border: 1px solid var(--color-4) !important; color: var(--color-5) !important;}
	.page-id-179 .er-stat-foot {border-top-color: var(--color-3) !important; color: var(--color-5) !important;}
	.page-id-179 .er-stat-icon {filter: brightness(0) invert(1) brightness(0.95) !important;}

	/* Counted values row (Number Counter). Each number reserves the width of its
	   final value (hidden copy behind it), so the row does not shift while counting. */
	.er-fp-values .er-num {display: inline-grid; font-size: var(--er-fs-xxl);}
	.er-fp-values .er-num > * {grid-area: 1 / 1; text-align: center; text-box: trim-both cap alphabetic;}
	.er-fp-values .er-num-final {visibility: hidden;}
	.er-fp-values .counter-value {font-size: inherit; vertical-align: baseline; color: var(--color-11); transition: color 0.2s ease;}
	.er-fp-values a:is(:hover, :focus-visible) .counter-value {color: var(--color-12);}
	@media (max-width: 600px) {.er-fp-values .er-num {font-size: var(--er-fs-xl);}}

	/* Each value is a link: the number carries the dark link colours (--color-11, hover
	   --color-12, rule above); the word keeps the row's text colour (color:inherit on the
	   link in the editor). The padding keeps the row's spacing. text-box trims the fonts'
	   built-in space (number: above the digits, link: below the baseline). */
	.er-fp-values a {display: inline-block; padding: 0.65em 0.5em 0.70em 0.5em; line-height: 1.1; text-box: trim-end cap alphabetic;}

	/* Space above and below the content once it is taller than the screen, so it clears the fixed header */
	@media (max-width: 1200px) {.wp-block-group.er-cover-host > .wp-block-group {padding-top: var(--wp--preset--spacing--x-large); padding-bottom: var(--wp--preset--spacing--x-large);}}
CSS;
}

/* ============================================================================
 * 4  PORTALS — the portal cards (.er-portal) in their grid (.er-portals).
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
 * 5  SURPRISE — /surprise/ sends the visitor to a random published entry
 *    (posts, pages, interests, quotes, traits; no password-protected ones).
 *    Excluded IDs = the exclude list of the [display-posts] random slider.
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
