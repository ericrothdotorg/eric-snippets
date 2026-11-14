<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* ============================================================================
 * SITE JOURNEY — how much of this site a visitor has explored
 * ----------------------------------------------------------------------------
 * Scope: "Run snippet everywhere" (mandatory). The progress number arrives via
 * admin-ajax (Visitor Tracking's beacon) and the cleanup runs via wp-cron; in
 * both contexts a front-end-only snippet would never register its hooks.
 *
 * WHAT IT DOES
 *   Every place on the journey adds its own id to a list in the visitor's
 *   browser (localStorage "er_journey"; logged-in users "er_journey_admin", so
 *   their own reading never mixes with a visitor list in the same browser).
 *   Nothing about which pages were opened ever leaves the browser. Only the
 *   number of explored places travels with Visitor Tracking's beacon and is
 *   kept per visitor (logged-out only) for the level and the explorer figures
 *   on the Site Journey page. The page is built in the editor from normal
 *   blocks plus the building blocks below ([er_sj_progress], [er_sj_sky],
 *   [er_sj_list], [er_sj_popular], [er_sj_countries], [er_sj_stat],
 *   [er_sj_qr_code]): progress, achievement and explorer cards, one sky per
 *   content type (the front page above each) with a "most read" view, the
 *   explorers' countries, and a journey link / QR code to continue on another
 *   device. On every place, a line in the footer shows the progress in that
 *   place's collection and links the next unread one.
 *
 * OWNS
 *   Table er_journey (visitor_id, explored, updated_at): created here if
 *   missing, cleaned here (ER_SJ_DAYS, daily cron er_sj_daily_cleanup).
 *
 * READS (behind function_exists, never writes)
 *   er_live_visitors (Visitor Tracking): the explorers' countries
 *   er_map_cached() (World Map): its cached top pages, for "most read"
 *
 * PROVIDES (used by Visitor Tracking, behind function_exists)
 *   er_sj_store( $visitor_id, $count )  stores a visitor's progress number
 *   In the browser: window.erJourneyCount() — number of places in the list
 *
 * COUNTED
 *   Every published page, post, interest, quote and trait, except the German
 *   items er_uncounted_ids() returns (Eric Companion PHP 1d). The same rule as
 *   every counter on the site; private pages never count.
 *
 * SECTIONS (in this order)
 *   Settings              numbers, constellation colours, all wording, preview samples
 *   Helpers               the browser list's name, shared JS rules, types, hubs
 *   The places            all places (cached 12 h), collection links
 *   Table & progress      er_journey table, er_sj_store(), daily cleanup
 *   Recording             every place adds itself to the browser list (wp_footer 20)
 *   Footer line           "Read 2 of 11 in Karma · Next: …" (wp_footer 22)
 *   Data for the page     admin-ajax er_sj_data: places + what all explorers add up to
 *   The page              shortcodes, texts, then its CSS + JS (wp_footer 21)
 *   QR code library       third-party, loaded only on request (admin-ajax er_sj_qr)
 *
 * COMMON CHANGES (where to make them)
 *   In the editor, no code:
 *     Headings, intro texts, cards, buttons   normal blocks on the Site Journey page
 *     Sky key words, tab label, view buttons  [er_sj_sky label visited notyet next mine popular]
 *     Button order / position                 move the Button blocks freely
 *     Where the QR code appears               a Shortcode block with [er_sj_qr_code];
 *                                             aligned right / centred: inside a Row group
 *   In this snippet: everything in SETTINGS, right below this header
 *     Numbers (90-day window, most read)      ER_SJ_DAYS, ER_SJ_TOP
 *     Colours of the constellations           er_sj_hues()
 *     Wording the script writes               er_sj_texts()       (keep %d / %s)
 *     Sample values in the editor preview     er_sj_stat_sample()
 *     Sizes, spacing, other colours           search "SITE JOURNEY STYLES"
 *   Afterwards: purge LiteSpeed; in the editor, ↻ Refresh in the shortcode preview.
 * ========================================================================== */

// ======================================
// SETTINGS: CHANGE THINGS HERE
// ======================================
// Numbers, colours, wording and the editor preview's samples, all in one place.
// Styles (sizes, spacing, other colours): search for "SITE JOURNEY STYLES".

defined( 'ER_SJ_DAYS' ) || define( 'ER_SJ_DAYS', 90 ); // same window as Visitor Tracking
defined( 'ER_SJ_PAGE' ) || define( 'ER_SJ_PAGE', 162357 ); // the Site Journey page (linked from the footer line)
defined( 'ER_SJ_TOP' ) || define( 'ER_SJ_TOP', 10 ); // "most read" places per type: gold in the sky, listed by [er_sj_popular]

// Colours of the constellations in the sky, used in this order (then repeated)
function er_sj_hues() {
	return array( '#2283d8', '#8b7fe8', '#2bb38a', '#e0679a', '#d08a2c', '#4aa3f0', '#6fd0b0', '#c59bf2', '#f0a35c', '#5cc1d6', '#e8807a', '#9fcf5a', '#7d9cf0', '#d9b44a' );
}

// Every text the snippet writes itself, grouped by where it shows. Change the wording
// here only. %d / %1$d / %2$d / %s are filled in by the script: keep them.
function er_sj_texts() {
	return array(
		// [er_sj_progress] and [er_sj_sky]: type names, "42 of 350"
		'pages'        => 'Pages',
		'posts'        => 'Posts',
		'interests'    => 'Interests',
		'quotes'       => 'Quotes',
		'traits'       => 'Traits',
		'of'           => '%1$d of %2$d',
		'visited'      => 'read',	// screen readers, after a read entry in [er_sj_list] / [er_sj_popular]
		'open'         => 'Open',	// [er_sj_sky] on touch screens: link in the title shown by the first tap

		// [er_sj_stat show="level"] and "level-note" (card: level)
		// Levels by where a visitor stands among all visitors of the last 90 days
		'levels'       => array( 'Newcomer', 'Explorer', 'Pathfinder', 'Voyager', 'Legend' ),
		'lvTop'        => 'Among the top %s of visitors',	// "Among the top 25% of visitors · "
		'lvIn1'        => 'in 1 more entry',	// after the next level in bold: "Pathfinder in 1 more entry"
		'lvInN'        => 'in %d more entries',	// "Pathfinder in 3 more entries"
		'lvLegend'     => 'You\'ve read more than anyone',	// the top level, Legend

		// [er_sj_stat show="completed-note"] (card: collections completed; the value is the number)
		// A collection = one term of a type's taxonomy (Pages: things, Posts: category, …), drawn as one constellation in the sky
		'done0'        => 'Collections completed – your first one is waiting',	// 0
		'done1'        => 'Collection completed',	// 1
		'doneN'        => 'Collections completed',	// 2 or more

		// [er_sj_stat show="favourite-note"] (card: favourite collection; the value is its name)
		'fav1'         => 'Your favourite collection · 1 entry',
		'favN'         => 'Your favourite collection · %d entries',
		'favNone'      => 'Your favourite collection shows up here',	// nothing read yet

		// [er_sj_stat show="almost-note"] (card: almost there; the value is the collection's name)
		// The next unread entry follows as a link: "… · next: Deeds"
		'near1'        => 'Almost there: 1 entry to go · next:',
		'nearN'        => 'Almost there: %d entries to go · next:',
		'nearAll'      => 'You\'ve read them all!',	// every collection finished

		// Footer line on every place: "Read 2 of 11 in Karma · Next: Deeds"
		'footRead'     => 'Read %1$d of %2$d',
		'footAll'      => 'Read all %d',	// collection finished: "Read all 11 in Karma ✓"
		'footIn'       => 'in %s',
		'footNext'     => 'Next:',

		// Buttons #journey-new, #journey-link, #journey-qr (feedback on the button, 3 s)
		'everywhere'   => 'You\'ve read them all!',	// #journey-new with nothing left unread
		'copied'       => 'Link copied ✓',
		'selectCopy'   => 'Copy the link below',	// the browser refused copying
		'linkLabel'    => 'Your personal link',	// screen readers: the link field
		'hideQr'       => 'Hide QR Code',
		'qrShown'      => 'QR code shown below.',	// screen readers only: confirms the click (the page text explains the scanning)
		'qrAlt'        => 'QR code of your personal link',
		'qrFailed'     => 'QR code not available',

		// Notes above the first building block on the Site Journey page
		'restored'     => 'Welcome back! %d pages from your personal link were added here.',
		'restoredNone' => 'Your personal link holds nothing new for this browser.',
		'failed'       => 'Your progress could not be loaded right now. Please try again later.',
	);
}

// Sample values for the editor preview only, built from the real texts so the wording
// always matches what visitors see. Numbers and collection names are made up.
function er_sj_stat_sample( $show ) {
	$t     = er_sj_texts();
	$collection = '<a href="#">Karma</a>';
	$samples = array(
		'percent'        => '12%',
		'explored'       => '42',
		'total'          => '350',
		'level'          => esc_html( $t['levels'][1] ),
		'level-note'     => esc_html( sprintf( $t['lvTop'], '25%' ) ) . ' · <b>' . esc_html( $t['levels'][2] ) . '</b> ' . esc_html( sprintf( $t['lvInN'], 3 ) ),
		'completed'      => '2',
		'completed-note' => esc_html( $t['doneN'] ),
		'favourite'      => $collection,
		'favourite-note' => esc_html( sprintf( $t['favN'], 7 ) ),
		'almost'         => $collection,
		'almost-note'    => esc_html( sprintf( $t['nearN'], 3 ) ) . ' <a href="#">Deeds</a>',
		'explorers'      => '1,234',
		'active-week'    => '56',
		'countries'      => '40',
		'my-country'     => 'Switzerland',
		'my-country-n'   => '12',
	);
	return isset( $samples[ $show ] ) ? $samples[ $show ] : '—';
}

// ======================================
// HELPERS
// ======================================


// The browser list of the current user: logged-in users keep their own, which no beacon counts
function er_sj_key() {
	return is_user_logged_in() ? 'er_journey_admin' : 'er_journey';
}

// The two rules every script on the page shares, written once: how the browser list is read,
// and which entry is "next". Printed at the top of each script that needs them (recording,
// footer line, Site Journey page); the first one defines window.erSJlib, the others reuse it.
function er_sj_js_lib() {
	return 'window.erSJlib = window.erSJlib || {'
		// The browser list of read entries (ids); empty if missing or unreadable
		. 'load: function (key) { var v = []; try { v = JSON.parse(localStorage.getItem(key)) || []; } catch (e) {} return Array.isArray(v) ? v : []; },'
		// The next unread entry of a collection (ids in site order): the first unread one after
		// "self", wrapping around; from the start if self is not in it. Footer line and the
		// Almost-there card both use it, so they always name the same entry.
		. 'next: function (ids, self, isRead) { var at = ids.indexOf(self); for (var k = 1; k <= ids.length; k++) { var id = ids[(at + k + ids.length) % ids.length]; if (!isRead(id)) return id; } return 0; }'
		. '};' . "\n";
}

function er_sj_excluded() {
	return function_exists( 'er_uncounted_ids' ) ? er_uncounted_ids() : array(); // owner: Eric Companion PHP
}

// Content types on the journey, in tab order: label and the taxonomy that groups
// them (the same as the filter pills and the footer)
function er_sj_types() {
	return array(
		'page'         => array( 'Pages', 'things' ),
		'post'         => array( 'Posts', 'category' ),
		'my-interests' => array( 'Interests', 'topics' ),
		'my-quotes'    => array( 'Quotes', 'groups' ),
		'my-traits'    => array( 'Traits', 'types' ),
	);
}

// The overview page of a type, shown as the hub star in the middle of its sky. Found
// the way the breadcrumbs find it (Eric Companion PHP 1b): My Pages from the hub
// setting, My Blog from WordPress's Posts page, the others as the page whose slug is
// the type's archive slug (my-interests, my-quotes, my-traits). No ids to maintain.
function er_sj_hub( $type ) {
	if ( 'page' === $type ) {
		return function_exists( 'er_things_hub_id' ) ? er_things_hub_id() : 0;
	}
	if ( 'post' === $type ) {
		return (int) get_option( 'page_for_posts' );
	}
	$obj  = get_post_type_object( $type );
	$slug = ( $obj && is_string( $obj->has_archive ) ) ? $obj->has_archive : $type;
	$hub  = get_posts( array( 'name' => $slug, 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
	return $hub ? (int) $hub[0] : 0;
}

// ======================================
// THE PLACES
// ======================================

// All places: id, title, relative url, type, groups (g: its collections = the terms
// of its type's taxonomy, as on the filter pills, one constellation each; h: the sky
// it is the hub of). A place in several collections shines in each of their
// constellations but is counted once, as its own type, so the numbers match the
// site's real counts. A page without a collection (the front page) sits in the middle of the Pages sky. An overview page
// (er_sj_types()) is also drawn as the hub in the middle of its sky; My Pages,
// the hub of the Pages sky itself, sits only there.
// Cached 12 h, cleared whenever content or terms change.
function er_sj_items() {
	$items = get_transient( 'er_sj_items_v5' );
	if ( is_array( $items ) ) {
		return $items;
	}
	$types = er_sj_types();
	$hubs  = array();
	foreach ( $types as $type => $t ) {
		$hubs[ er_sj_hub( $type ) ] = $type;
	}
	$posts = get_posts( array(
		'post_type'      => array_keys( $types ),
		'post_status'    => 'publish',
		'has_password'   => false,
		'post__not_in'   => er_sj_excluded(),
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	) );
	$items = array();
	foreach ( $posts as $p ) {
		$type   = $p->post_type;
		$hub    = isset( $hubs[ $p->ID ] ) ? $hubs[ $p->ID ] : '';
		$groups = array();
		if ( $hub !== $type ) {
			$terms = get_the_terms( $p, $types[ $type ][1] );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$groups[] = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
				}
			} elseif ( 'page' !== $type ) {
				$groups[] = 'my-traits' === $type ? 'My Traits' : $types[ $type ][0];
			}
		}
		$item = array(
			'i' => $p->ID,
			't' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'u' => wp_make_link_relative( get_permalink( $p ) ),
			'y' => $type,
			'g' => $groups,
		);
		if ( 'page' === $type && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $p->ID ) {
			$item['r'] = 1; // the front page: drawn above every sky, everything sits under it
		}
		if ( $hub ) {
			$item['h'] = $hub;
			// As the hub star it links to WordPress's own list of its type (/my-quotes/ …;
			// for posts the Posts page): with a redirect that lands on this page, without
			// one on the list. Pages have no such list: My Pages itself.
			$list      = 'page' === $hub ? get_permalink( $p ) : get_post_type_archive_link( $hub );
			$item['a'] = wp_make_link_relative( $list ? $list : get_permalink( $p ) );
		}
		$items[] = $item;
	}
	set_transient( 'er_sj_items_v5', $items, 12 * HOUR_IN_SECONDS );
	return $items;
}
function er_sj_flush() { delete_transient( 'er_sj_items_v5' ); delete_transient( 'er_sj_links' ); }

// Each collection's own page ("post|Karma" => /category/karma/), so the cards can link a collection.
// Cached like the places, cleared with them.
function er_sj_collection_links() {
	$links = get_transient( 'er_sj_links' );
	if ( is_array( $links ) ) {
		return $links;
	}
	$links = array();
	foreach ( er_sj_types() as $type => $t ) {
		$terms = get_terms( array( 'taxonomy' => $t[1], 'hide_empty' => true ) );
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			$url = get_term_link( $term );
			if ( ! is_wp_error( $url ) ) {
				$links[ $type . '|' . html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ] = wp_make_link_relative( $url );
			}
		}
	}
	set_transient( 'er_sj_links', $links, 12 * HOUR_IN_SECONDS );
	return $links;
}
add_action( 'save_post', 'er_sj_flush' );
add_action( 'deleted_post', 'er_sj_flush' );
add_action( 'set_object_terms', 'er_sj_flush' );
add_action( 'edited_term', 'er_sj_flush' );
add_action( 'delete_term', 'er_sj_flush' );

// ======================================
// TABLE & PROGRESS NUMBERS
// ======================================

function er_sj_install_table() {
	global $wpdb;
	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}er_journey (
		visitor_id varchar(64) NOT NULL,
		explored smallint(5) unsigned NOT NULL DEFAULT 0,
		updated_at datetime NOT NULL,
		PRIMARY KEY (visitor_id),
		KEY updated_at (updated_at)
	) {$wpdb->get_charset_collate()}" );
}
add_action( 'init', function () {
	if ( get_option( 'er_sj_schema' ) !== '1' ) {
		er_sj_install_table();
		update_option( 'er_sj_schema', '1' );
	}
	if ( ! wp_next_scheduled( 'er_sj_daily_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'er_sj_daily_cleanup' );
	}
} );

// Called by Visitor Tracking's beacon handler. The number comes from the
// visitor's browser, so it is capped at the number of places; it never goes
// down (clearing the browser does not undo a journey). Logged-in users are
// never counted. Rate limiting: the beacon's own per-IP limit.
function er_sj_store( $visitor_id, $count ) {
	if ( is_user_logged_in() || '' === (string) $visitor_id ) {
		return;
	}
	$count = min( absint( $count ), count( er_sj_items() ) );
	if ( $count < 1 ) {
		return;
	}
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->prefix}er_journey (visitor_id, explored, updated_at) VALUES (%s, %d, %s)
		ON DUPLICATE KEY UPDATE explored = GREATEST(explored, VALUES(explored)), updated_at = VALUES(updated_at)",
		$visitor_id, $count, current_time( 'mysql' )
	) );
}

// Rows are stored in site time, so the cutoff is computed in site time too.
add_action( 'er_sj_daily_cleanup', function () {
	global $wpdb;
	er_sj_install_table(); // safety net after a partial restore
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->prefix}er_journey WHERE updated_at < %s",
		wp_date( 'Y-m-d H:i:s', time() - ER_SJ_DAYS * DAY_IN_SECONDS )
	) );
} );

// ======================================
// RECORDING (every front-end page)
// ======================================

// The place on screen: pages, posts, interests, quotes and traits that are not
// excluded. Archives, search and 404 get 0. Logged-in users are recorded in their
// own list (er_sj_key()); er_sj_store() never counts them. Cached per request
// (recording and footer line).
function er_sj_place() {
	static $id = null;
	if ( null !== $id ) {
		return $id;
	}
	$id = 0;
	// The posts page (My Blog) is an archive for WordPress, but a place on the journey
	$posts_page = is_home() && ! is_front_page() && get_option( 'page_for_posts' );
	if ( ! ( $posts_page || is_singular( array_keys( er_sj_types() ) ) ) || ! empty( $GLOBALS['er_synthetic_page'] ) ) {
		return $id;
	}
	$place = $posts_page ? (int) get_option( 'page_for_posts' ) : (int) get_queried_object_id();
	if ( $place && ! in_array( $place, er_sj_excluded(), true ) ) {
		$id = $place;
	}
	return $id;
}

add_action( 'wp_footer', function () {
	$id = er_sj_place();
	if ( ! $id ) {
		return;
	}
	echo '<script>' . er_sj_js_lib() . '(function(){var k="' . er_sj_key() . '",v=erSJlib.load(k);'
		. 'var id=' . $id . ';if(v.indexOf(id)<0){v.push(id);try{localStorage.setItem(k,JSON.stringify(v))}catch(e){}}'
		. 'window.erJourneyCount=function(){return v.length}})();</script>' . "\n";
}, 20 );

// ======================================
// FOOTER LINE (every place; on the Site Journey page without the link to itself)
// ======================================

// On the footer's breadcrumb row, right-aligned: "Read 2 of 11 in Karma · Next: Deeds".
// "Read 2 of 11" opens the Site Journey page (plain text on that page itself), "Deeds"
// the next unread place of the same collection: for pages in the order of the page tree, for
// everything else in the order of publication. A finished collection reads "Read all 11 in
// Karma ✓". The collection is the one the breadcrumb shows: the first term, on pages the one
// named like their top-level page. Pages without a collection get no line. Runs after the
// recording, so this page counts.
add_action( 'wp_footer', function () {
	$id = er_sj_place();
	if ( ! $id ) {
		return;
	}
	$items = er_sj_items();
	$self  = null;
	foreach ( $items as $it ) {
		if ( $it['i'] === $id ) {
			$self = $it;
			break;
		}
	}
	if ( ! $self || ! $self['g'] ) {
		return;
	}
	$collection = $self['g'][0];
	if ( 'page' === $self['y'] ) {
		$anc = get_post_ancestors( $id );
		$top = html_entity_decode( get_the_title( $anc ? end( $anc ) : $id ), ENT_QUOTES, 'UTF-8' );
		if ( in_array( $top, $self['g'], true ) ) {
			$collection = $top;
		}
	}
	$places = array();
	foreach ( $items as $it ) {
		if ( $it['y'] === $self['y'] && in_array( $collection, $it['g'], true ) ) {
			$places[] = array( $it['i'], $it['u'], $it['t'] );
		}
	}
	if ( 'page' === $self['y'] ) {
		usort( $places, function ( $a, $b ) { return strcmp( $a[1], $b[1] ); } ); // by address = the page tree
	}
	$data = array(
		'self'   => $id,
		'collection' => $collection,
		'places' => $places, // in site order: the next unread one comes after this page
		'page'   => ER_SJ_PAGE === $id ? '' : wp_make_link_relative( get_permalink( ER_SJ_PAGE ) ), // no link to itself
		'quote'  => 'my-quotes' === $self['y'], // a quote collection carries the quote ring
		'key'    => er_sj_key(),
		'texts'  => array_intersect_key( er_sj_texts(), array_flip( array( 'footRead', 'footAll', 'footIn', 'footNext' ) ) ),
	);
	?>
	<style>
		.er-sj-crumbrow {display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 6px 24px; max-width: var(--er-wide-row); margin: 0 auto 25px;}
		footer.wp-block-template-part .er-sj-crumbrow > .er-breadcrumbs {max-width: none; margin: 0;} /* the row takes over width and spacing */
		.er-sj-line {margin: 0; color: var(--color-8);} /* size: the footer's own (Eric Companion CSS) */
		.er-sj-line .er-sj-sep {margin-inline: 6px; color: var(--color-9);}
	</style>
	<script>
	<?php echo er_sj_js_lib(); ?>
	(function () {
		var d = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE ); ?>, T = d.texts;
		var crumbs = document.querySelector('footer.wp-block-template-part .er-breadcrumbs');
		if (!crumbs) return;
		var seen = erSJlib.load(d.key), isRead = function (id) { return seen.indexOf(id) >= 0; };
		var read = d.places.filter(function (p) { return isRead(p[0]); }).length;
		var nextId = erSJlib.next(d.places.map(function (p) { return p[0]; }), d.self, isRead);
		var next = d.places.filter(function (p) { return p[0] === nextId; })[0];
		function link(href, text) { if (!href) return text; var a = document.createElement('a'); a.href = href; a.textContent = text; return a; }
		var line = document.createElement('p');
		line.className = 'er-sj-line';
		var done = read >= d.places.length;
		var name = document.createElement('span');
		name.textContent = d.collection;
		if (d.quote) name.className = 'er-quote-mark'; // the quote ring (Eric Companion CSS)
		var parts = T.footIn.split('%s');
		line.append(
			link(d.page, done ? T.footAll.replace('%d', d.places.length) : T.footRead.replace('%1$d', read).replace('%2$d', d.places.length)),
			' ' + parts[0], name, (parts[1] || '') + (done ? ' ✓' : '')
		);
		if (next) {
			var sep = document.createElement('span'); sep.className = 'er-sj-sep'; sep.textContent = '·'; sep.setAttribute('aria-hidden', 'true');
			line.append(sep, T.footNext + ' ', link(next[1], next[2]));
		}
		var row = document.createElement('div');
		row.className = 'er-sj-crumbrow';
		crumbs.before(row);
		row.append(crumbs, line);
	})();
	</script>
	<?php
}, 22 );

// ======================================
// DATA FOR THE PAGE (admin-ajax, GET, read-only)
// ======================================

// What all explorers of the last 90 days add up to, cached for 15 minutes:
//   hist      { explored: visitors } - levels and the explorer figures
//   week      explorers who visited in the last 7 days (every beacon refreshes updated_at)
//   countries [[country code, explorers], …], most first (Visitor Tracking's table)
//   popular   place ids, most read first (World Map's cached top pages)
// Rows are stored in site time, so the cutoff is computed in site time too.
function er_sj_crowd() {
	$crowd = get_transient( 'er_sj_crowd' );
	if ( is_array( $crowd ) ) {
		return $crowd;
	}
	global $wpdb;
	$crowd = array( 'hist' => array(), 'week' => 0, 'countries' => array(), 'popular' => array() );
	$rows  = $wpdb->get_results( $wpdb->prepare(
		"SELECT explored, COUNT(*) AS n, SUM(updated_at >= %s) AS week FROM {$wpdb->prefix}er_journey GROUP BY explored",
		wp_date( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS )
	), ARRAY_A );
	foreach ( (array) $rows as $r ) {
		$crowd['hist'][ (int) $r['explored'] ] = (int) $r['n'];
		$crowd['week'] += (int) $r['week'];
	}
	if ( function_exists( 'er_trk_visitor_id' ) ) { // owner of er_live_visitors: Visitor Tracking
		$rows = $wpdb->get_results(
			"SELECT l.country_code AS cc, COUNT(*) AS n FROM {$wpdb->prefix}er_journey j
			JOIN {$wpdb->prefix}er_live_visitors l ON l.visitor_id = j.visitor_id
			WHERE l.country_code <> '' GROUP BY l.country_code ORDER BY n DESC",
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			$crowd['countries'][] = array( strtoupper( $r['cc'] ), (int) $r['n'] );
		}
	}
	if ( function_exists( 'er_map_cached' ) && function_exists( 'er_map_build_history' ) && defined( 'ER_MAP_CACHE_HISTORY' ) ) {
		$history = er_map_cached( 'er_map_history', 'er_map_build_history', ER_MAP_CACHE_HISTORY );
		$ids     = array();
		foreach ( er_sj_items() as $it ) {
			$ids[ trailingslashit( $it['u'] ) ] = $it['i'];
		}
		foreach ( (array) ( $history['pages'] ?? array() ) as $p ) {
			$path = trailingslashit( (string) $p['url'] );
			if ( isset( $ids[ $path ] ) ) {
				$crowd['popular'][] = $ids[ $path ];
			}
		}
	}
	set_transient( 'er_sj_crowd', $crowd, 15 * MINUTE_IN_SECONDS );
	return $crowd;
}

// Places, collection links and what all explorers add up to.
function er_sj_data() {
	$crowd = er_sj_crowd();
	wp_send_json_success( array(
		'items'     => er_sj_items(),
		'links'     => (object) er_sj_collection_links(),
		'hist'      => (object) $crowd['hist'],
		'week'      => $crowd['week'],
		'countries' => $crowd['countries'],
		'popular'   => $crowd['popular'],
	) );
}
add_action( 'wp_ajax_er_sj_data', 'er_sj_data' );
add_action( 'wp_ajax_nopriv_er_sj_data', 'er_sj_data' );

// ======================================
// THE PAGE: BUILDING BLOCKS
// ======================================
// Headings, texts and buttons are normal blocks in the editor. The snippet only
// provides the live parts. Each one is optional: its script part runs only where
// the block is on the page.
//
// [er_sj_progress]
//     Ring and bars.
// [er_sj_sky label="…" visited="…" notyet="…" next="…" mine="…" popular="…"]
//     Tab bar, sky and key. mine + popular add a switch to a view where the
//     ER_SJ_TOP most read places of the tab shine gold (rank only, never a count).
//     In the own view: lines between two read stars light up, a completed collection
//     shines with a ✓, the "next" entry (the Almost-there card's) pulses, a collection's
//     name opens its page (site link style, dark pair; so do the names of the overview
//     and front page stars). A constellation's size follows its
//     number of entries; its tilt, elongation and star pattern come from its name.
//     On touch screens the first tap on a star shows its title, the second opens it.
// [er_sj_list]
//     The places of the selected tab as a list of links (e.g. inside a Details
//     block): the way into the sky for keyboard and screen reader users.
// [er_sj_popular]
//     The ER_SJ_TOP most read places of the selected tab, in five columns. On tabs
//     without a ranking (or without World Map) hidden together with the Group block
//     around it, so its heading goes too.
// [er_sj_countries count="…" skip="…"]
//     The explorers' countries, most first, in the footer tag cloud's format.
//     count = only the first n; skip = all after the first n (e.g. inside a Details
//     block, which hides when nothing is left for it).
// [er_sj_stat show="…" size="big"]
//     One live value, usable inside any text. size="big": large, on its own line.
//     show = percent, explored, total
//          achievement cards: level, completed, favourite, almost
//                             (each also as …-note: the line under the value)
//          explorer cards:    explorers, active-week, countries, my-country,
//                             my-country-n (explorers there, the visitor included)
//     The editor preview (Editor Live Previews) shows sample values: er_sj_stat_sample().
//     It is recognised by its route, er_sj_is_editor_preview(); Editor Live Previews needs no change.
// [er_sj_qr_code]
//     Where the QR code appears: place it in the editor (e.g. right below the buttons; for
//     right or centred, put the Shortcode block in a Row group and set its justification).
//     Without it, the code appears right below the QR button's own Buttons block.
//
// Buttons: any Button block linking to #journey-new (random unread place),
// #journey-link (copy the journey link) or #journey-qr (show / hide the QR code).
// Feedback such as "Link copied ✓" shows on the button itself for 3 seconds.
// Cards: the columns holding the four achievement cards carry the class
// card-highlight (Advanced → Additional CSS class), styled in Eric Companion CSS.
//
// Rule for every value about other visitors: it never shows how little they read.
// The level only says where a visitor stands ("among the top 5%"), the explorer
// values count people, "most read" is a rank.

function er_sj_mark() { $GLOBALS['er_sj_page'] = true; } // style and script go into this page's footer only

add_shortcode( 'er_sj_progress', function () {
	er_sj_mark();
	return '<div class="er-sj-progress"><div class="er-sj-ringbox">'
		. '<svg class="er-sj-ring" viewBox="0 0 112 112" aria-hidden="true">'
		. '<circle cx="56" cy="56" r="46" fill="none" stroke-width="10" class="er-sj-track"/>'
		. '<circle cx="56" cy="56" r="46" fill="none" stroke-width="10" stroke-linecap="round" stroke-dasharray="0 289" transform="rotate(-90 56 56)" class="er-sj-arc"/>'
		. '</svg><div class="er-sj-ringtxt"><b class="er-sj-pct">0%</b><span class="er-sj-of"></span></div></div>'
		. '<div class="er-sj-bars"></div></div>';
} );

add_shortcode( 'er_sj_sky', function ( $atts ) {
	er_sj_mark();
	$a = shortcode_atts( array( 'label' => 'Choose a type:', 'visited' => 'read', 'notyet' => 'unread', 'next' => 'next', 'mine' => '', 'popular' => '' ), $atts );
	// mine + popular: a switch between the visitor's own reading and the most read places
	$views = '' !== $a['mine'] && '' !== $a['popular']
		? '<div class="er-sj-views" role="group" hidden>'
			. '<button type="button" class="er-sj-tab" aria-pressed="true" data-view="mine">' . esc_html( $a['mine'] ) . '</button>'
			. '<button type="button" class="er-sj-tab" aria-pressed="false" data-view="popular">' . esc_html( $a['popular'] ) . '</button></div>'
		: '';
	return '<div class="er-sj-skywrap" data-visited="' . esc_attr( $a['visited'] ) . '" data-notyet="' . esc_attr( $a['notyet'] ) . '" data-next="' . esc_attr( $a['next'] ) . '" data-popular="' . esc_attr( $a['popular'] ) . '">'
		. '<div class="er-sj-skybox">'
		. '<div class="er-sj-tabbar"><span class="er-sj-tablabel" id="er-sj-tablabel">' . esc_html( $a['label'] ) . '</span>'
		. '<div class="er-sj-tabs" role="tablist" aria-labelledby="er-sj-tablabel"></div>' . $views . '</div>'
		. '<div id="er-sj-sky" role="tabpanel"><svg class="er-sj-svg" viewBox="0 0 1000 420" aria-hidden="true"></svg></div>'
		. '<div class="er-sj-tip" hidden></div>'
		. '<div class="er-sj-key" aria-hidden="true"><span><i class="is-seen"></i>' . esc_html( $a['visited'] ) . '</span><span><i></i>' . esc_html( $a['notyet'] ) . '</span>'
		. '<span class="er-sj-keynext" hidden><i class="is-next"></i>' . esc_html( $a['next'] ) . '</span></div></div></div>'; // shown while a next entry exists
} );

add_shortcode( 'er_sj_list', function () {
	er_sj_mark();
	return '<div class="er-sj-listbox"></div>';
} );

add_shortcode( 'er_sj_popular', function () {
	er_sj_mark();
	return '<ul class="er-sj-popular" hidden></ul>';
} );

add_shortcode( 'er_sj_countries', function ( $atts ) {
	er_sj_mark();
	$a = shortcode_atts( array( 'count' => 0, 'skip' => 0 ), $atts );
	// The footer tag cloud's format and separator (Eric Companion CSS); left-align: not justified
	return '<p class="er-sj-countries tag-cloud-footer left-align" data-count="' . absint( $a['count'] ) . '" data-skip="' . absint( $a['skip'] ) . '"></p>';
} );

add_shortcode( 'er_sj_stat', function ( $atts ) {
	er_sj_mark();
	$a = shortcode_atts( array( 'show' => 'percent', 'size' => '' ), $atts );
	$cls = 'big' === $a['size'] ? 'er-sj-stat is-big' : 'er-sj-stat'; // size="big": a large number on its own line
	if ( '-note' === substr( $a['show'], -5 ) ) {
		$cls .= ' is-note'; // the line under a card's value: its own line, italic
	}
	// In the editor preview a sample instead of "—"; visitors get the live value
	$value = er_sj_is_editor_preview() ? er_sj_stat_sample( $a['show'] ) : '—';
	return '<span class="' . $cls . '" data-sj-stat="' . esc_attr( $a['show'] ) . '">' . $value . '</span>';
} );

// The editor preview (Editor Live Previews) renders a shortcode in a REST request to its
// route custom/v1/shortcode-preview. If that route is ever renamed there, rename it here
// too, or the editor shows "—" again (visitors are never affected).
function er_sj_is_editor_preview() {
	$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
	return '/custom/v1/shortcode-preview' === untrailingslashit( $route );
}

add_shortcode( 'er_sj_qr_code', function () {
	er_sj_mark();
	return '<div class="er-sj-qrcode"></div>';
} );

add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['er_sj_page'] ) ) {
		return;
	}
	$cfg = array( 'ajax' => admin_url( 'admin-ajax.php' ), 'self' => er_sj_place(), 'key' => er_sj_key(), 'top' => ER_SJ_TOP, 'texts' => er_sj_texts(), 'hues' => er_sj_hues() ); // self: this page, if it is a place
	?>
	<style>
		/* SITE JOURNEY STYLES: sizes, spacing and colours of the page's building blocks */
		:is(.er-sj-progress, .er-sj-skywrap, .er-sj-msg) {--sj-card: var(--color-8); --sj-line: var(--color-5); --sj-head: var(--color-4);}
		body.dark-mode :is(.er-sj-progress, .er-sj-skywrap, .er-sj-msg) {--sj-card: var(--color-10); --sj-line: var(--color-3); --sj-head: var(--color-7);}
		.er-sj-skywrap [hidden] {display: none !important;}
		.er-sj-progress {display: flex; flex-wrap: wrap; align-items: center; gap: 12px 24px;}
		.er-sj-ringbox {position: relative; flex: 0 0 132px; width: 132px; height: 132px;}
		.er-sj-ring {display: block; width: 100%; height: 100%;}
		.er-sj-track {stroke: var(--sj-line);}
		.er-sj-arc {stroke: var(--color-1); transition: stroke-dasharray 0.8s ease;}
		.er-sj-ringtxt {position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;}
		.er-sj-ringtxt b {font-size: var(--er-fs-xl); color: var(--sj-head); line-height: 1.1;}
		.er-sj-ringtxt span {font-size: var(--er-fs-xs);}
		.er-sj-bars {flex: 1 1 280px; display: grid; grid-template-columns: max-content 1fr max-content; gap: 8px 12px; align-items: center; font-size: var(--er-fs-sm);}
		.er-sj-bar {height: 8px; border-radius: 4px; background: var(--sj-line); overflow: hidden;}
		.er-sj-bar i {display: block; height: 100%; background: var(--color-1); border-radius: 4px; transition: width 0.8s ease;}
		.er-sj-tabbar {display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 22px 18px; background: var(--color-10); border-bottom: 1px solid var(--color-4);}
		.er-sj-tablabel {font-size: var(--er-fs-sm); font-weight: var(--er-fw-bold); color: var(--color-5);}
		.er-sj-tabs {display: flex; flex-wrap: wrap; gap: 8px;}
		.er-sj-tab {font: inherit; font-size: var(--er-fs-sm); padding: 7px 16px; border-radius: 20px; border: 1px solid var(--color-1); background: var(--color-10); color: var(--color-5); cursor: pointer; transition: border-color 0.2s, background 0.2s;}
		.er-sj-tab:hover {background: var(--color-6); border-color: var(--color-2);}
		.er-sj-tab:is([aria-selected="true"], [aria-pressed="true"]) {background: var(--color-1); border-color: var(--color-1); color: var(--color-8);}
		.er-sj-tabnum {margin-left: 4px; font-size: var(--er-fs-xs); font-weight: var(--er-fw-bold); color: #ffd700;} /* gold, as the front-page stat numbers */
		.er-sj-tab[aria-selected="true"] .er-sj-tabnum {color: var(--color-8);}
		.er-sj-skybox {position: relative; background: var(--color-6); border-radius: 12px; overflow: hidden; --er-link: var(--color-11); --er-link-hover: var(--color-12);} /* dark background: the dark link pair (style.css), ≥ 4.9:1 on --color-6 */
		body.dark-mode .er-sj-skybox {box-shadow: 0 0 0 1px var(--color-4);} /* the sky's black is the dark page's black */
		.er-sj-svg {display: block; width: 100%; height: auto;}
		.er-sj-label {fill: var(--color-5); letter-spacing: 0.4px; font-family: inherit;}
		.er-sj-label.is-done {fill: var(--color-8); font-weight: var(--er-fw-bold);} /* a completed collection */
		.er-sj-labellink .er-sj-label {fill: var(--er-link); font-weight: bold;} /* the site's link style (SVG text: fill) */
		.er-sj-labellink:hover .er-sj-label {fill: var(--er-link-hover);}
		.er-sj-next {fill: none; stroke: #ffd700; stroke-width: 1.5; transform-box: fill-box; transform-origin: center; animation: er-sj-pulse 1.8s ease-out infinite;}
		@keyframes er-sj-pulse {from {transform: scale(1); opacity: 0.9;} to {transform: scale(2.2); opacity: 0;}}
		.er-sj-star {cursor: pointer;}
		.er-sj-tw {animation: er-sj-tw 3.2s ease-in-out infinite alternate;}
		@keyframes er-sj-tw {from {opacity: 1;} to {opacity: 0.45;}}
		.er-sj-tip {position: absolute; z-index: 2; max-width: 260px; padding: 7px 10px; border-radius: 8px; background: rgba(14, 24, 37, 0.95); border: 1px solid var(--color-3); color: var(--color-5); font-size: var(--er-fs-xs); line-height: 1.4; pointer-events: none;}
		.er-sj-tip b {display: block; color: var(--color-8);}
		.er-sj-tip.is-tap {pointer-events: auto;} /* touch: the title carries an Open link */
		.er-sj-tip a {display: inline-block; margin-top: 4px; font-weight: var(--er-fw-bold);}
		.er-sj-key {position: absolute; left: 12px; bottom: 10px; display: flex; gap: 16px; font-size: var(--er-fs-xs); color: var(--color-5); pointer-events: none;}
		.er-sj-key i {display: inline-block; width: 9px; height: 9px; margin-right: 6px; border-radius: 50%; border: 1.5px solid #8b7fe8; vertical-align: -1px;}
		.er-sj-key i.is-seen {background: #fff; border-color: #fff; box-shadow: 0 0 0 3px rgba(231, 240, 248, 0.15);}
		.er-sj-key i.is-top {background: #ffd700; border-color: #ffd700; box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15);}
		.er-sj-key i.is-next {border-color: #ffd700; box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.25);}
		.er-sj-views {display: flex; gap: 8px; margin-left: auto;}
		:is(.er-sj-tab, .er-sj-link):focus-visible {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
		:is(.er-sj-listbox, .er-sj-popular) {columns: 4 180px; column-gap: 24px; font-size: var(--er-fs-sm);}
		.er-sj-popular {columns: 5 180px; margin: 0; padding-left: 1.1em;} /* ER_SJ_TOP 10: two per column */
		.er-sj-popular li {break-inside: avoid;}
		.er-sj-group {break-inside: avoid; padding-bottom: 0.75rem;}
		.er-sj-listbox h3 {font-size: var(--er-fs-sm); margin: 0 0 0.25rem;}
		.er-sj-listbox ul {margin: 0; padding-left: 1.1em;}
		:is(.er-sj-listbox, .er-sj-popular) li.is-seen::marker {content: "✓ "; color: var(--color-1);}
		.er-sj-sr {position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap;}
		.er-sj-link {display: block; width: 100%; font-size: var(--er-fs-xs);}
		.er-sj-qrcode:has(svg) {margin-top: 16px; width: 220px; padding: 10px; background: #fff; border-radius: 10px;}
		.er-sj-qrcode:empty {display: none;} /* until it shows something: no space, no gap, even inside a Row */
		.er-sj-qrcode svg {display: block; width: 100%; height: auto;}
		.er-sj-qrcode .er-sj-link {margin-top: 16px;}
		.er-sj-stat.is-big {display: block; font-size: var(--er-fs-xl); font-weight: var(--er-fw-bold); line-height: 1.2; color: var(--color-4);} /* xl: long collection names wrap between words */
		.er-sj-stat.is-note {display: block; font-style: italic; text-align: left;} /* the note under a card's value: no justified gaps */
		body.dark-mode .er-sj-stat.is-big {color: var(--color-7);}
		.er-sj-msg {margin: 0.5rem 0 0; font-size: var(--er-fs-sm); color: var(--sj-head);}
		.er-sj-msg:empty {display: none;}
		@media (prefers-reduced-motion: reduce) {.er-sj-arc, .er-sj-bar i {transition: none;} .er-sj-next {animation: none; transform: scale(1.6); opacity: 0.8;}}
	</style>
	<script>
	<?php echo er_sj_js_lib(); ?>
	window.erSJ = <?php echo wp_json_encode( $cfg ); ?>;
	(function () {
		var CFG = window.erSJ || {};
		var T = CFG.texts || {};
		var KEY = CFG.key;
		var TYPES = [['page', T.pages], ['post', T.posts], ['my-interests', T.interests], ['my-quotes', T.quotes], ['my-traits', T.traits]];
		var HUES = CFG.hues; // er_sj_hues() in SETTINGS
		var NS = 'http://www.w3.org/2000/svg';
		var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var items = [], byId = {}, seen = {}, hist = {}, links = {}, current = 'page';
		var week = 0, countries = [], popular = [], view = 'mine';
		var nextId = 0; // the Almost-there card's next entry: it pulses in the sky

		// The building blocks are optional: each part only runs where its block is on the page
		var P = document.querySelector('.er-sj-progress');
		var S = document.querySelector('.er-sj-skywrap');
		var L = document.querySelector('.er-sj-listbox');
		var Q = document.querySelector('.er-sj-qrcode');
		var R = document.querySelector('.er-sj-popular');
		var C = document.querySelectorAll('.er-sj-countries');
		var first = document.querySelector('.er-sj-progress, .er-sj-skywrap, .er-sj-listbox, .er-sj-popular, .er-sj-countries, .er-sj-qrcode, [data-sj-stat]');
		if (!first) return;

		function el(tag, attrs, parent) {
			var e = document.createElementNS(NS, tag);
			for (var k in attrs) e.setAttribute(k, attrs[k]);
			if (parent) parent.appendChild(e);
			return e;
		}
		// A visible note only where the visitor arrives (welcome back, loading failed)
		function say(text) {
			var spot = first.closest('p, h1, h2, h3, h4, h5, h6') || first, note = spot.__note;
			if (!note) {
				note = spot.__note = document.createElement('p');
				note.className = 'er-sj-msg';
				note.setAttribute('role', 'status');
				spot.before(note);
			}
			note.textContent = text;
		}
		// Button feedback: the button itself says what happened, then returns to its own text.
		// A hidden live region tells screen readers the same.
		var live = document.createElement('span');
		live.className = 'er-sj-sr';
		live.setAttribute('role', 'status');
		document.body.appendChild(live);
		function label(btn) {
			if (!btn.__label) btn.__label = btn.textContent;
			return btn.__label;
		}
		function flash(btn, text) {
			label(btn);
			btn.textContent = text;
			live.textContent = text;
			clearTimeout(btn.__timer);
			btn.__timer = setTimeout(function () { btn.textContent = btn.__label; }, 3000);
		}

		// ---- Stored journey (written on every page by the recording script) ----
		function load() { return erSJlib.load(KEY); }
		function save(list) {
			try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {}
		}

		// ---- Journey link: visited ids, sorted, as delta varints in base64url ----
		function encode(ids) {
			var bytes = [], prev = 0;
			ids.slice().sort(function (a, b) { return a - b; }).forEach(function (id) {
				var d = id - prev; prev = id;
				while (d > 127) { bytes.push((d & 127) | 128); d = Math.floor(d / 128); }
				bytes.push(d);
			});
			var s = '';
			bytes.forEach(function (b) { s += String.fromCharCode(b); });
			return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
		}
		function decode(code) {
			var s;
			try { s = atob(code.replace(/-/g, '+').replace(/_/g, '/')); } catch (e) { return []; }
			var ids = [], prev = 0, d = 0, mul = 1;
			for (var i = 0; i < s.length; i++) {
				var b = s.charCodeAt(i);
				d += (b & 127) * mul;
				if (b & 128) { mul *= 128; continue; }
				prev += d; ids.push(prev); d = 0; mul = 1;
			}
			return ids;
		}
		function link() {
			var ids = items.filter(function (it) { return seen[it.i]; }).map(function (it) { return it.i; });
			return location.origin + location.pathname + '#j=' + encode(ids);
		}

		// A journey link opened here: merge it, then clean the address bar
		function restore() {
			var m = /^#j=([A-Za-z0-9_-]*)$/.exec(location.hash);
			if (!m) return;
			var list = load(), added = 0;
			decode(m[1]).forEach(function (id) {
				if (byId[id] && list.indexOf(id) < 0) { list.push(id); added++; }
			});
			save(list);
			history.replaceState(null, '', location.pathname + location.search);
			say(added ? T.restored.replace('%d', added) : T.restoredNone);
		}

		// ---- Seeded randomness, so every star keeps its place on every visit ----
		function rng(seed) {
			var s = seed % 2147483647; if (s <= 0) s += 2147483646;
			return function () { s = s * 16807 % 2147483647; return (s - 1) / 2147483646; };
		}
		function hash(text) { // a stable number from a name, as a seed for rng()
			var h = 7;
			for (var i = 0; i < text.length; i++) h = (h * 31 + text.charCodeAt(i)) % 2147483647;
			return h;
		}

		// ---- Numbers ----
		function pct(n, of) {
			if (!of) return '0%';
			var p = n / of * 100;
			return (p > 0 && p < 1) ? '<1%' : Math.floor(p) + '%';
		}
		function counts() {
			var c = {}, all = 0, done = 0;
			TYPES.forEach(function (t) { c[t[0]] = [0, 0]; });
			items.forEach(function (it) { c[it.y][1]++; all++; if (seen[it.i]) { c[it.y][0]++; done++; } });
			return { per: c, all: all, done: done };
		}
		// Level: where this visitor stands among the visitors of the last 90 days. Only the
		// standing is shown ("among the top 5%"), never a number about the others.
		function level(own) {
			var names = T.levels, visitors = 0, max = 0;
			Object.keys(hist).forEach(function (k) { visitors += hist[k]; max = Math.max(max, +k); });
			function share(n) { // share of visitors who read fewer than n entries, in %
				var below = 0;
				Object.keys(hist).forEach(function (k) { if (+k < n) below += hist[k]; });
				return visitors ? below / visitors * 100 : 100;
			}
			function rank(n) { // 0 Newcomer … 4 Legend
				var p = share(n);
				return p < 50 ? 0 : (p >= 99 && n >= max ? 4 : (p >= 99 ? 3 : (p >= 90 ? 2 : 1)));
			}
			var now = rank(own), need = own + 1;
			while (need <= max + 1 && rank(need) <= now) need++;
			var more = need - own, up = rank(need);
			if (now === 4) return [names[4], T.lvLegend];
			var next = [{ b: names[up] }, ' ' + (more === 1 ? T.lvIn1 : T.lvInN.replace('%d', more))]; // "Explorer in 1 more entry"
			if (now === 0) return [names[0], next];
			var top = [1, 2, 5, 10, 25, 50].filter(function (x) { return x >= 100 - share(own); })[0];
			return [names[now], [T.lvTop.replace('%s', top + '%') + ' · '].concat(next)];
		}
		// Collections: one per type and term (Personal pages and Personal quotes are two). In the
		// same order as the footer line: pages along the page tree, the rest by publication.
		function collections() {
			var map = {};
			items.forEach(function (it) {
				it.g.forEach(function (g) {
					var k = it.y + '|' + g;
					(map[k] = map[k] || { name: g, url: links[k], quote: it.y === 'my-quotes', list: [] }).list.push(it);
				});
			});
			return Object.keys(map).map(function (k) {
				var t = map[k];
				if (t.list[0].y === 'page') t.list.sort(function (a, b) { return a.u < b.u ? -1 : 1; });
				t.read = t.list.filter(function (it) { return seen[it.i]; }).length;
				t.left = t.list.length - t.read;
				return t;
			});
		}
		// A collection name links to the collection's own page; a quote collection carries the quote ring
		function collectionPart(t) { return { a: [t.url || '', t.name], q: t.quote }; }
		function achievements(c) {
			var lv = level(c.done), all = collections(), done = 0, fav = null, near = null;
			all.forEach(function (t) {
				if (!t.left) done++;
				if (t.read && (!fav || t.read > fav.read || (t.read === fav.read && t.left < fav.left))) fav = t;
				if (t.left && (!near || (t.read > 0) > (near.read > 0) || ((t.read > 0) === (near.read > 0) && t.left < near.left))) near = t;
			});
			// The next unread entry: the same rule as the footer line (erSJlib.next)
			var next = near ? byId[erSJlib.next(near.list.map(function (it) { return it.i; }), CFG.self, function (id) { return seen[id]; })] : null;
			nextId = next ? next.i : 0;
			return {
				level: lv[0], 'level-note': lv[1],
				completed: String(done), 'completed-note': done ? (done === 1 ? T.done1 : T.doneN) : T.done0,
				favourite: fav ? [collectionPart(fav)] : '—', 'favourite-note': fav ? (fav.read === 1 ? T.fav1 : T.favN.replace('%d', fav.read)) : T.favNone,
				almost: near ? [collectionPart(near)] : '✓',
				'almost-note': near ? [(near.left === 1 ? T.near1 : T.nearN.replace('%d', near.left)) + ' ', { a: [next.u, next.t] }] : T.nearAll
			};
		}
		// Country names in English, from the codes (as World Map does)
		var regions = (function () { try { return new Intl.DisplayNames(['en'], { type: 'region' }); } catch (e) { return null; } })();
		function country(cc) { try { return regions ? regions.of(cc) : cc; } catch (e) { return cc; } }
		// The visitor's own country: Visitor Tracking's answer on this page, or the last one this session
		function myCountry() {
			var v = window.ERVisitor;
			if (!v) { try { v = JSON.parse(sessionStorage.getItem('er_visitor')); } catch (e) {} }
			return v && v.cc ? String(v.cc).toUpperCase() : '';
		}
		// All explorers: how many and where from, never how much they read
		function crowd(s, own) {
			var n = 0;
			Object.keys(hist).forEach(function (k) { n += hist[k]; });
			s.explorers = n.toLocaleString('en');
			s['active-week'] = week.toLocaleString('en');
			s.countries = String(countries.length);
			var cc = myCountry();
			if (cc) {
				var here = countries.filter(function (c) { return c[0] === cc; })[0];
				s['my-country'] = country(cc);
				s['my-country-n'] = String(Math.max(here ? here[1] : 0, own ? 1 : 0)); // the visitor counts too
			}
		}
		function stats(c) {
			var s = achievements(c);
			s.percent = pct(c.done, c.all);
			s.explored = String(c.done);
			s.total = String(c.all);
			crowd(s, c.done);
			return s;
		}
		function numbers() {
			var c = counts(), s = stats(c);
			document.querySelectorAll('[data-sj-stat]').forEach(function (e) {
				var v = s[e.getAttribute('data-sj-stat')];
				if (v === undefined) return;
				e.textContent = '';
				// A value is a text, or a list of parts: texts, { b: bold }, { a: [href, text] }
				(Array.isArray(v) ? v : [v]).forEach(function (part) {
					var node = part;
					if (part && part.b) { node = document.createElement('b'); node.textContent = part.b; }
					else if (part && part.a) {
						node = document.createElement(part.a[0] ? 'a' : 'span');
						if (part.a[0]) node.href = part.a[0];
						node.textContent = part.a[1];
						if (part.q) node.className = 'er-quote-mark'; // the quote ring (Eric Companion CSS)
					}
					e.append(node);
				});
			});
			if (P) progress(c);
			return c;
		}

		// ---- [er_sj_progress]: ring and bars ----
		function progress(c) {
			var len = 2 * Math.PI * 46;
			P.querySelector('.er-sj-arc').setAttribute('stroke-dasharray', (c.all ? c.done / c.all * len : 0) + ' ' + len);
			P.querySelector('.er-sj-pct').textContent = pct(c.done, c.all);
			P.querySelector('.er-sj-of').textContent = T.of.replace('%1$d', c.done).replace('%2$d', c.all);
			var bars = P.querySelector('.er-sj-bars');
			bars.textContent = '';
			TYPES.forEach(function (t) {
				var v = c.per[t[0]];
				var name = document.createElement('span'); name.textContent = t[1];
				var bar = document.createElement('span'); bar.className = 'er-sj-bar';
				var fill = document.createElement('i'); fill.style.width = (v[1] ? v[0] / v[1] * 100 : 0) + '%';
				bar.appendChild(fill);
				bar.setAttribute('role', 'progressbar');
				bar.setAttribute('aria-label', t[1]);
				bar.setAttribute('aria-valuemin', '0');
				bar.setAttribute('aria-valuemax', String(v[1]));
				bar.setAttribute('aria-valuenow', String(v[0]));
				var num = document.createElement('span'); num.textContent = v[0] + '/' + v[1];
				bars.append(name, bar, num);
			});
		}

		// ---- Most read: the ER_SJ_TOP places of the selected tab, by World Map's ranking ----
		var tops = {};
		function topList() {
			var out = popular.map(function (id) { return byId[id]; }).filter(function (it) { return it && it.y === current; }).slice(0, CFG.top);
			tops = {};
			out.forEach(function (it) { tops[it.i] = true; });
			return out;
		}
		// [er_sj_popular]: the same places as links, in the list's columns
		function popularList() {
			var word = (S && S.getAttribute('data-visited')) || T.visited, top = topList();
			R.textContent = '';
			var box = R.parentElement.matches('.wp-block-group') ? R.parentElement : R; // its own Group only, never an outer one
			R.hidden = box.hidden = !top.length;
			top.forEach(function (it) {
				var li = document.createElement('li'), a = document.createElement('a');
				a.href = it.u; a.textContent = it.t;
				li.appendChild(a);
				if (seen[it.i]) {
					li.className = 'is-seen';
					var v = document.createElement('span'); v.className = 'er-sj-sr'; v.textContent = ' (' + word + ')';
					li.appendChild(v);
				}
				R.appendChild(li);
			});
		}
		// [er_sj_countries]: "Switzerland (4) | Germany (40)", the numbers bold, most explorers first. A block holding
		// nothing (e.g. skip="21" with 21 countries or fewer) hides, with its Details block if in one.
		function countryList() {
			C.forEach(function (box) {
				var skip = +box.getAttribute('data-skip'), count = +box.getAttribute('data-count');
				var part = countries.slice(skip, count ? skip + count : undefined);
				box.textContent = '';
				part.forEach(function (c, i) {
					if (i) {
						var sep = document.createElement('span');
						sep.className = 'tag-separator';
						sep.textContent = '|';
						box.append('\u00a0', sep, ' '); // no break before "|": a line never starts with it
					}
					var n = document.createElement('b');
					n.textContent = '(' + c[1] + ')';
					box.append(country(c[0]) + ' ', n);
				});
				box.hidden = !part.length;
				if (box.parentElement.matches('details')) box.parentElement.hidden = !part.length;
			});
		}

		// ---- [er_sj_sky] ----
		function clusters(type) {
			var map = {}, order = [], hub = [];
			items.forEach(function (it) {
				if (it.r) return;                                                            // the front page: above every sky
				if (it.h === type || (it.y === type && !it.g.length && !it.h)) hub.push(it); // the middle
				if (it.y !== type) return;
				it.g.forEach(function (g) {                                                  // a star in each of its constellations
					if (!map[g]) { map[g] = []; order.push(g); }
					map[g].push(it);
				});
			});
			order.sort(function (a, b) { return a.localeCompare(b); });
			hub.sort(function (a, b) { return (b.h === type) - (a.h === type); });
			return { list: order.map(function (g) { return [g, map[g]]; }), hub: hub };
		}

		function tabs() {
			var box = S.querySelector('.er-sj-tabs'), c = counts();
			box.textContent = '';
			TYPES.forEach(function (t) {
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'er-sj-tab';
				b.setAttribute('role', 'tab');
				b.setAttribute('aria-selected', t[0] === current ? 'true' : 'false');
				b.setAttribute('aria-controls', 'er-sj-sky');
				var n = document.createElement('span');
				n.className = 'er-sj-tabnum';
				n.textContent = c.per[t[0]][0] + '/' + c.per[t[0]][1];
				b.append(t[1] + ' ', n);
				b.addEventListener('click', function () { current = t[0]; ranked(); tabs(); sky(); if (L) list(); if (R) popularList(); });
				box.appendChild(b);
			});
		}

		function sky() {
			var svg = S.querySelector('.er-sj-svg'), wide = S.clientWidth >= 600;
			hideTip(); // its star is about to be redrawn
			topList();
			var cl = clusters(current), n = cl.list.length, hub = cl.hub.length > 0;
			var vw = wide ? 1000 : 420;
			// The front page sits above everything, in every sky, in a band of its own at the top.
			// Its line runs straight down to the middle, so no constellation may sit in the centre
			// column above it: an even number of columns, and a lone constellation goes below.
			var root = items.filter(function (it) { return it.r; })[0], top = root ? (wide ? 70 : 84) : 0;
			var cols = wide ? (n > 8 ? (hub ? 4 : 5) : (n > 4 ? (hub && !root ? 3 : 4) : Math.min(n, hub ? 2 : 4))) : Math.min(n, 2);
			cols = Math.max(1, cols);
			var rows = Math.max(1, Math.ceil(n / cols));
			// With a hub the clusters sit above and below a free band in the middle
			var topRows = hub ? (root && n === 1 ? 0 : Math.ceil(rows / 2)) : rows, band = hub ? 90 : 0;
			var ch = wide ? 210 : 190, cw = vw / cols, vh = top + rows * ch + band + 26; // 26: room for the key
			svg.textContent = '';
			svg.setAttribute('viewBox', '0 0 ' + vw + ' ' + vh);
			var r = rng(4242), nameBottom = 0;
			// A constellation's size follows its number of entries (the largest fills its cell), and
			// each collection gets its own tilt and elongation from its name: no two look alike
			var most = Math.max.apply(null, cl.list.map(function (c) { return c[1].length; }).concat(1));
			// Nebulae first, then the far stars, then the constellations on top
			var centers = cl.list.map(function (c, i) {
				var col = i % cols, row = Math.floor(i / cols), inLast = n - (rows - 1) * cols;
				var off = (row === rows - 1) ? (cols - inLast) * cw / 2 : 0;
				var y = top + ch * row + ch / 2 - 8 + (row >= topRows ? band : 0);
				var size = Math.max(0.45, c[1].length / most), shape = rng(hash(c[0]));
				return [off + cw * col + cw / 2 + (r() - 0.5) * 24, y + (r() - 0.5) * 14, Math.min(cw, ch) * 0.38 * size, shape() * Math.PI, 0.45 + shape() * 0.5];
			});
			centers.forEach(function (c, i) {
				var h = HUES[i % HUES.length], t = c[3], tilt = 'rotate(' + (t * 180 / Math.PI) + ' ' + c[0] + ' ' + c[1] + ')', ry = Math.max(c[4], 0.6);
				c[5] = c[1]; // the nebula's lowest point: the collection's name goes below it
				[[0, 0, 1.2, 0.95, 0.07], [0.25, -0.1, 1, 0.7, 0.07], [-0.2, 0.12, 0.5, 0.4, 0.09]].forEach(function (e) {
					var dx = c[2] * e[0], dy = c[2] * e[1], ex = c[2] * e[2], ey = c[2] * e[3] * ry;
					el('ellipse', { cx: c[0] + dx, cy: c[1] + dy, rx: ex, ry: ey, fill: h, opacity: e[4], transform: tilt }, svg);
					c[5] = Math.max(c[5], c[1] + dx * Math.sin(t) + dy * Math.cos(t) + Math.sqrt(Math.pow(ex * Math.sin(t), 2) + Math.pow(ey * Math.cos(t), 2)));
				});
			});
			for (var s = 0; s < (wide ? 260 : 130); s++) {
				var big = r() > 0.9;
				el('circle', { cx: r() * vw, cy: r() * vh, r: big ? 1.1 : 0.6, fill: big ? '#8da6b9' : '#3a4f66', opacity: 0.4 + r() * 0.6 }, svg);
			}
			// Hub stars in the middle: side by side if there is more than one
			var hubY = top + topRows * ch + band / 2 - 6, step = wide ? 150 : 110;
			var hubs = cl.hub.map(function (it, k) { return [vw / 2 + (k - (cl.hub.length - 1) / 2) * step, hubY, it]; });
			var rx = vw / 2, ry = top - (wide ? 24 : 28); // the front page's spot (drawn last)
			hubs.forEach(function (hb) {
				centers.forEach(function (c) {
					el('line', { x1: hb[0], y1: hb[1], x2: c[0], y2: c[1], stroke: '#8da6b9', 'stroke-opacity': 0.25, 'stroke-dasharray': '2 5', 'stroke-width': 1 }, svg);
				});
				if (root) el('line', { x1: rx, y1: ry, x2: hb[0], y2: hb[1], stroke: '#e7f0f8', 'stroke-opacity': 0.35, 'stroke-dasharray': '2 5', 'stroke-width': 1 }, svg);
			});
			cl.list.forEach(function (c, i) {
				var cx = centers[i][0], cy = centers[i][1], rad = centers[i][2], tilt = centers[i][3], flat = centers[i][4], h = HUES[i % HUES.length];
				// Stars scattered like a real constellation: each takes, of 12 random spots in the
				// collection's tilted, elongated area, the one farthest from the stars already
				// placed. Seeded by entry and collection: every star keeps its place on every visit,
				// and a new entry adds a star without moving the others.
				var placed = [];
				var pts = c[1].map(function (it) {
					var rr = rng(it.i + hash(c[0]) % 100000), best = null;
					for (var k = 0; k < 12; k++) {
						var a = rr() * 6.283, d = Math.sqrt(rr()), px = Math.cos(a) * d, py = Math.sin(a) * d * flat, gap = 9;
						placed.forEach(function (q) { gap = Math.min(gap, Math.pow(q[0] - px, 2) + Math.pow(q[1] - py, 2)); });
						if (!best || gap > best[2]) best = [px, py, gap];
					}
					placed.push(best);
					var x = best[0] * Math.cos(tilt) - best[1] * Math.sin(tilt), y = best[0] * Math.sin(tilt) + best[1] * Math.cos(tilt);
					return [cx + x * rad, cy + y * rad * 0.85, it];
				});
				var lowest = Math.max(centers[i][5], Math.max.apply(null, pts.map(function (p) { return p[1]; })) + 8); // below nebula and stars
				// Constellation lines: each star joins its nearest star already in the figure
				var inFig = [0];
				while (inFig.length < pts.length) {
					var best = null;
					for (var a = 0; a < inFig.length; a++) {
						for (var b = 0; b < pts.length; b++) {
							if (inFig.indexOf(b) >= 0) continue;
							var dd = Math.pow(pts[inFig[a]][0] - pts[b][0], 2) + Math.pow(pts[inFig[a]][1] - pts[b][1], 2);
							if (!best || dd < best[2]) best = [inFig[a], b, dd];
						}
					}
					inFig.push(best[1]);
					// Own view: a line between two read stars lights up, so reading draws the figure
					var lit = view === 'mine' && seen[pts[best[0]][2].i] && seen[pts[best[1]][2].i];
					el('line', { x1: pts[best[0]][0], y1: pts[best[0]][1], x2: pts[best[1]][0], y2: pts[best[1]][1], stroke: h, 'stroke-opacity': lit ? 0.95 : 0.35, 'stroke-width': lit ? 1.6 : 1 }, svg);
				}
				var done = 0;
				pts.forEach(function (p) { star(svg, p[0], p[1], p[2], h, false); if (seen[p[2].i]) done++; });
				var full = view === 'mine' && done === c[1].length; // a completed collection
				// The name opens the collection's own page (where there is one)
				var url = links[current + '|' + c[0]], holder = url ? el('a', { href: url, tabindex: '-1', 'class': 'er-sj-labellink' }, svg) : svg;
				var ty = lowest + (wide ? 24 : 30); // a clear gap below the nebula (more on narrow screens: larger text)
				nameBottom = Math.max(nameBottom, ty);
				var t = el('text', { x: cx, y: ty, 'text-anchor': 'middle', 'class': 'er-sj-label' + (full ? ' is-done' : ''), 'font-size': wide ? 13 : 16 }, holder);
				t.textContent = c[0] + '  ' + done + '/' + c[1].length + (full ? ' ✓' : '');
			});
			hubs.forEach(function (hb) {
				star(svg, hb[0], hb[1], hb[2], '#e7f0f8', true);
				// Its name links like a collection's: to its type's list, as the star does
				var hl = el('a', { href: hb[2].a || hb[2].u, tabindex: '-1', 'class': 'er-sj-labellink' }, svg);
				var t = el('text', { x: hb[0], y: hb[1] + 26, 'text-anchor': 'middle', 'class': 'er-sj-label', 'font-size': wide ? 13 : 16 }, hl);
				t.textContent = hb[2].t;
			});
			// The front page on top, in gold (its line down to the overview is drawn with the others)
			if (root) {
				star(svg, rx, ry, root, '#ffd700', true);
				var rl = el('a', { href: root.u, tabindex: '-1', 'class': 'er-sj-labellink' }, svg); // its name links too
				var rt = el('text', { x: rx, y: ry - 16, 'text-anchor': 'middle', 'class': 'er-sj-label', 'font-size': wide ? 13 : 16 }, rl);
				rt.textContent = root.t;
			}
			// A name low in the bottom row makes the sky a bit taller rather than being cut off
			if (nameBottom + 34 > vh) svg.setAttribute('viewBox', '0 0 ' + vw + ' ' + (nameBottom + 34));
			// The key names "next" only while its star is in this sky (own view only)
			var keyNext = S.querySelector('.er-sj-keynext');
			if (keyNext) keyNext.hidden = !svg.querySelector('.er-sj-next');
		}

		function star(svg, x, y, it, hue, big) {
			var a = el('a', { href: (big && it.a) || it.u, tabindex: '-1', 'class': 'er-sj-star' }, svg); // hub star: its type's list
			a.__it = it;
			el('circle', { cx: x, cy: y, r: S.clientWidth < 600 ? 12 : 9, fill: 'transparent' }, a); // easier to hit (more on narrow screens)
			// Own view: read stars shine white. Most read view: the top places shine gold.
			var lit = view === 'popular' ? tops[it.i] : seen[it.i], glow = view === 'popular' ? '#ffd700' : '#ffffff';
			if (lit) {
				el('circle', { cx: x, cy: y, r: big ? 11 : 7.5, fill: view === 'popular' ? glow : '#e7f0f8', opacity: 0.13 }, a);
				var c = el('circle', { cx: x, cy: y, r: big ? 5.5 : 3.6, fill: glow, 'class': reduce ? '' : 'er-sj-tw' }, a);
				if (!reduce) c.style.animationDelay = (it.i % 37) / 10 + 's';
			} else {
				el('circle', { cx: x, cy: y, r: big ? 4.6 : 2.9, fill: '#070c12', stroke: hue, 'stroke-width': big ? 1.6 : 1.2 }, a);
			}
			// Own view: the next entry (the Almost-there card's) pulses
			if (view === 'mine' && it.i === nextId) el('circle', { cx: x, cy: y, r: big ? 6 : 4.5, 'class': 'er-sj-next' }, a);
		}

		// The switch between the own view and the most read view (only with mine + popular, and
		// only on tabs with a ranking: elsewhere it hides and the own view returns). The key
		// below the sky follows the view.
		var switcher = null;
		function setView(v) {
			view = v;
			switcher.box.querySelectorAll('[data-view]').forEach(function (x) { x.setAttribute('aria-pressed', String(x.getAttribute('data-view') === v)); });
			switcher.key.replaceChildren.apply(switcher.key, v === 'popular' ? [switcher.top] : switcher.mine);
		}
		function views() {
			var box = S.querySelector('.er-sj-views'), key = S.querySelector('.er-sj-key');
			if (!box) return;
			var top = document.createElement('span'), dot = document.createElement('i');
			dot.className = 'is-top';
			top.append(dot, S.getAttribute('data-popular'));
			switcher = { box: box, key: key, top: top, mine: Array.prototype.slice.call(key.childNodes) };
			box.addEventListener('click', function (e) {
				var b = e.target.closest('[data-view]');
				if (!b || b.getAttribute('data-view') === view) return;
				setView(b.getAttribute('data-view'));
				sky();
			});
			ranked();
		}
		function ranked() {
			if (!switcher) return;
			var has = topList().length > 0;
			switcher.box.hidden = !has;
			if (!has && view === 'popular') setView('mine');
		}

		// Tooltip: the title of a star. Mouse: on hover. Touch: the first tap shows the title with
		// an Open link, a second tap on the same star opens it, a tap on the empty sky closes it.
		var hideTip = function () {};
		function tips() {
			var tip = S.querySelector('.er-sj-tip'), box = S.querySelector('.er-sj-skybox');
			var visited = S.getAttribute('data-visited'), notyet = S.getAttribute('data-notyet'), nextWord = S.getAttribute('data-next');
			var touch = false, tapped = null;
			function show(a, withLink) {
				var it = a.__it, r = a.getBoundingClientRect(), b = box.getBoundingClientRect();
				tip.textContent = '';
				var t = document.createElement('b'); t.textContent = it.t;
				var s = document.createElement('span');
				s.textContent = (it.g.length ? it.g.join(', ') + ' · ' : '') + (seen[it.i] ? visited : notyet)
					+ (view === 'mine' && it.i === nextId ? ' · ' + nextWord : '');
				tip.append(t, s);
				if (withLink) {
					var go = document.createElement('a');
					go.href = a.getAttribute('href');
					go.textContent = T.open + ' →';
					tip.append(document.createElement('br'), go);
				}
				tip.classList.toggle('is-tap', !!withLink);
				tip.hidden = false;
				var left = Math.min(Math.max(4, r.left - b.left + r.width / 2 - tip.offsetWidth / 2), b.width - tip.offsetWidth - 4);
				var top = r.top - b.top - tip.offsetHeight - 6;
				tip.style.left = left + 'px';
				tip.style.top = (top < 4 ? r.bottom - b.top + 6 : top) + 'px';
			}
			hideTip = function () { tip.hidden = true; tip.classList.remove('is-tap'); tapped = null; };
			box.addEventListener('pointerdown', function (e) { touch = e.pointerType !== 'mouse'; }, true);
			box.addEventListener('pointerover', function (e) {
				if (e.pointerType !== 'mouse') return;
				var a = e.target.closest && e.target.closest('.er-sj-star');
				if (a && a.__it) show(a, false);
			});
			box.addEventListener('pointerout', function (e) {
				if (e.pointerType !== 'mouse' || tapped) return;
				var a = e.target.closest && e.target.closest('.er-sj-star');
				if (a && !a.contains(e.relatedTarget)) hideTip();
			});
			box.addEventListener('click', function (e) {
				if (!touch || e.target.closest('.er-sj-tip')) return; // the Open link opens as usual
				var a = e.target.closest('.er-sj-star');
				if (!a || !a.__it) { hideTip(); return; }               // empty sky or a collection name
				if (tapped === a) return;                               // second tap: open the entry
				e.preventDefault();
				tapped = a;
				show(a, true);
			});
		}


		// [er_sj_list]: the places of the selected tab as plain links, grouped and counted like the
		// sky - for keyboards, screen readers and anyone who prefers text
		function list() {
			var cl = clusters(current), word = (S && S.getAttribute('data-visited')) || T.visited;
			var middle = items.filter(function (it) { return it.r; }).concat(cl.hub); // the front page first, as on top of the sky
			var groups = (middle.length ? [['', middle]] : []).concat(cl.list);
			L.textContent = '';
			groups.forEach(function (g) {
				var box = document.createElement('div');
				box.className = 'er-sj-group';
				var done = g[1].filter(function (it) { return seen[it.i]; }).length;
				if (g[0]) { // a collection: its name opens its page, a completed one gets a ✓ (as in the sky)
					var h = document.createElement('h3'), url = links[current + '|' + g[0]], name = g[0];
					if (url) { name = document.createElement('a'); name.href = url; name.textContent = g[0]; }
					h.append(name, ' ' + done + '/' + g[1].length + (done === g[1].length ? ' ✓' : ''));
					box.appendChild(h);
				}
				var ul = document.createElement('ul');
				g[1].slice().sort(function (a, b) { return a.t.localeCompare(b.t); }).forEach(function (it) {
					var li = document.createElement('li'), a = document.createElement('a');
					a.href = (!g[0] && it.a) || it.u; a.textContent = it.t; // hub: its type's list
					li.appendChild(a);
					if (seen[it.i]) {
						li.className = 'is-seen';
						var v = document.createElement('span'); v.className = 'er-sj-sr'; v.textContent = ' (' + word + ')';
						li.appendChild(v);
					}
					ul.appendChild(li);
				});
				box.appendChild(ul);
				L.appendChild(box);
			});
		}

		// ---- Buttons: any link or button block pointing to #journey-new, #journey-link or #journey-qr ----
		function somewhereNew(btn) {
			var open = items.filter(function (it) { return !seen[it.i]; });
			if (!open.length) { flash(btn, T.everywhere); return; }
			location.href = open[Math.floor(Math.random() * open.length)].u;
		}
		// Where the QR code (or the link to copy by hand) appears: the [er_sj_qr_code] block, placed
		// in the editor. Without one, right below the button's own Buttons block.
		function spot(btn) {
			if (!Q) {
				Q = document.createElement('div');
				Q.className = 'er-sj-qrcode';
				(btn.closest('.wp-block-buttons') || btn).after(Q);
			}
			return Q;
		}
		// Copies the link; only if the browser refuses, the link is shown below for copying by hand
		function copyLink(btn) {
			var url = link();
			var byHand = function () {
				var box = spot(btn);
				box.textContent = '';
				var out = document.createElement('input');
				out.type = 'text'; out.readOnly = true; out.className = 'er-sj-link'; out.value = url;
				out.setAttribute('aria-label', T.linkLabel);
				box.appendChild(out);
				out.select();
				flash(btn, T.selectCopy);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(function () { flash(btn, T.copied); }, byHand);
			} else { byHand(); }
		}
		// On / off: shows the QR code below the buttons, the button switches to "Hide"
		var qrLoading = null;
		function showQr(btn) {
			var box = spot(btn);
			label(btn);
			if (box.querySelector('svg')) {
				box.textContent = '';
				btn.textContent = btn.__label;
				btn.setAttribute('aria-expanded', 'false');
				return;
			}
			if (!qrLoading) {
				qrLoading = new Promise(function (ok, fail) {
					var s = document.createElement('script');
					s.src = CFG.ajax + '?action=er_sj_qr';
					s.onload = ok; s.onerror = fail;
					document.head.appendChild(s);
				});
			}
			qrLoading.then(function () {
				var q = window.qrcode(0, 'L');
				q.addData(link());
				q.make();
				box.innerHTML = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true, alt: T.qrAlt });
				clearTimeout(btn.__timer);
				btn.textContent = T.hideQr;
				btn.setAttribute('aria-expanded', 'true');
				live.textContent = T.qrShown;
			}, function () { qrLoading = null; flash(btn, T.qrFailed); });
		}
		document.addEventListener('click', function (e) {
			var a = e.target.closest && e.target.closest('a[href$="#journey-new"], a[href$="#journey-link"], a[href$="#journey-qr"]');
			if (!a) return;
			e.preventDefault();
			if (!items.length) return;
			var h = a.getAttribute('href');
			if (/#journey-new$/.test(h)) somewhereNew(a);
			else if (/#journey-link$/.test(h)) copyLink(a);
			else showQr(a);
		});

		// ---- Start ----
		function start(data) {
			items = data.items || [];
			hist = data.hist || {};
			links = data.links || {};
			week = data.week || 0;
			countries = data.countries || [];
			popular = data.popular || [];
			items.forEach(function (it) { byId[it.i] = it; });
			restore();
			load().forEach(function (id) { if (byId[id]) seen[id] = true; });
			numbers();
			if (L) list();
			if (R) popularList();
			countryList();
			document.addEventListener('er:visitor', numbers); // the own country arrives after the beacon
			if (S) {
				views(); tabs(); sky(); tips();
				var w = S.clientWidth;
				window.addEventListener('resize', function () {
					if ((S.clientWidth >= 600) !== (w >= 600)) { w = S.clientWidth; sky(); }
				});
			}
		}
		fetch(CFG.ajax + '?action=er_sj_data', { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) { if (j && j.success) start(j.data); else throw 0; })
			.catch(function () { say(T.failed); });
	})();
	</script>
	<?php
}, 21 );

// ======================================
// QR CODE LIBRARY (third-party, minified: do not edit)
// ======================================
// QR code generator, only loaded when a visitor asks for the QR code.
// qrcode-generator 2.0.4, (c) 2009 Kazuhiko Arase, MIT licence (minified).
function er_sj_qr() {
	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Cache-Control: public, max-age=2592000' );
	header_remove( 'Expires' );
	header_remove( 'Pragma' );
	echo <<<'JS'
var qrcode=function(){var t=function(t,r){var e=t,n=g[r],o=null,i=0,a=null,u=[],f={},c=function(t,r){o=function(t){for(var r=new Array(t),e=0;e<t;e+=1){r[e]=new Array(t);for(var n=0;n<t;n+=1)r[e][n]=null}return r}(i=4*e+17),l(0,0),l(i-7,0),l(0,i-7),s(),h(),d(t,r),e>=7&&v(t),null==a&&(a=p(e,n,u)),w(a,r)},l=function(t,r){for(var e=-1;e<=7;e+=1)if(!(t+e<=-1||i<=t+e))for(var n=-1;n<=7;n+=1)r+n<=-1||i<=r+n||(o[t+e][r+n]=0<=e&&e<=6&&(0==n||6==n)||0<=n&&n<=6&&(0==e||6==e)||2<=e&&e<=4&&2<=n&&n<=4)},h=function(){for(var t=8;t<i-8;t+=1)null==o[t][6]&&(o[t][6]=t%2==0);for(var r=8;r<i-8;r+=1)null==o[6][r]&&(o[6][r]=r%2==0)},s=function(){for(var t=B.getPatternPosition(e),r=0;r<t.length;r+=1)for(var n=0;n<t.length;n+=1){var i=t[r],a=t[n];if(null==o[i][a])for(var u=-2;u<=2;u+=1)for(var f=-2;f<=2;f+=1)o[i+u][a+f]=-2==u||2==u||-2==f||2==f||0==u&&0==f}},v=function(t){for(var r=B.getBCHTypeNumber(e),n=0;n<18;n+=1){var a=!t&&1==(r>>n&1);o[Math.floor(n/3)][n%3+i-8-3]=a}for(n=0;n<18;n+=1){a=!t&&1==(r>>n&1);o[n%3+i-8-3][Math.floor(n/3)]=a}},d=function(t,r){for(var e=n<<3|r,a=B.getBCHTypeInfo(e),u=0;u<15;u+=1){var f=!t&&1==(a>>u&1);u<6?o[u][8]=f:u<8?o[u+1][8]=f:o[i-15+u][8]=f}for(u=0;u<15;u+=1){f=!t&&1==(a>>u&1);u<8?o[8][i-u-1]=f:u<9?o[8][15-u-1+1]=f:o[8][15-u-1]=f}o[i-8][8]=!t},w=function(t,r){for(var e=-1,n=i-1,a=7,u=0,f=B.getMaskFunction(r),c=i-1;c>0;c-=2)for(6==c&&(c-=1);;){for(var g=0;g<2;g+=1)if(null==o[n][c-g]){var l=!1;u<t.length&&(l=1==(t[u]>>>a&1)),f(n,c-g)&&(l=!l),o[n][c-g]=l,-1==(a-=1)&&(u+=1,a=7)}if((n+=e)<0||i<=n){n-=e,e=-e;break}}},p=function(t,r,e){for(var n=A.getRSBlocks(t,r),o=b(),i=0;i<e.length;i+=1){var a=e[i];o.put(a.getMode(),4),o.put(a.getLength(),B.getLengthInBits(a.getMode(),t)),a.write(o)}var u=0;for(i=0;i<n.length;i+=1)u+=n[i].dataCount;if(o.getLengthInBits()>8*u)throw"code length overflow. ("+o.getLengthInBits()+">"+8*u+")";for(o.getLengthInBits()+4<=8*u&&o.put(0,4);o.getLengthInBits()%8!=0;)o.putBit(!1);for(;!(o.getLengthInBits()>=8*u||(o.put(236,8),o.getLengthInBits()>=8*u));)o.put(17,8);return function(t,r){for(var e=0,n=0,o=0,i=new Array(r.length),a=new Array(r.length),u=0;u<r.length;u+=1){var f=r[u].dataCount,c=r[u].totalCount-f;n=Math.max(n,f),o=Math.max(o,c),i[u]=new Array(f);for(var g=0;g<i[u].length;g+=1)i[u][g]=255&t.getBuffer()[g+e];e+=f;var l=B.getErrorCorrectPolynomial(c),h=k(i[u],l.getLength()-1).mod(l);for(a[u]=new Array(l.getLength()-1),g=0;g<a[u].length;g+=1){var s=g+h.getLength()-a[u].length;a[u][g]=s>=0?h.getAt(s):0}}var v=0;for(g=0;g<r.length;g+=1)v+=r[g].totalCount;var d=new Array(v),w=0;for(g=0;g<n;g+=1)for(u=0;u<r.length;u+=1)g<i[u].length&&(d[w]=i[u][g],w+=1);for(g=0;g<o;g+=1)for(u=0;u<r.length;u+=1)g<a[u].length&&(d[w]=a[u][g],w+=1);return d}(o,n)};f.addData=function(t,r){var e=null;switch(r=r||"Byte"){case"Numeric":e=M(t);break;case"Alphanumeric":e=x(t);break;case"Byte":e=m(t);break;case"Kanji":e=L(t);break;default:throw"mode:"+r}u.push(e),a=null},f.isDark=function(t,r){if(t<0||i<=t||r<0||i<=r)throw t+","+r;return o[t][r]},f.getModuleCount=function(){return i},f.make=function(){if(e<1){for(var t=1;t<40;t++){for(var r=A.getRSBlocks(t,n),o=b(),i=0;i<u.length;i++){var a=u[i];o.put(a.getMode(),4),o.put(a.getLength(),B.getLengthInBits(a.getMode(),t)),a.write(o)}var g=0;for(i=0;i<r.length;i++)g+=r[i].dataCount;if(o.getLengthInBits()<=8*g)break}e=t}c(!1,function(){for(var t=0,r=0,e=0;e<8;e+=1){c(!0,e);var n=B.getLostPoint(f);(0==e||t>n)&&(t=n,r=e)}return r}())},f.createTableTag=function(t,r){t=t||2;var e="";e+='<table style="',e+=" border-width: 0px; border-style: none;",e+=" border-collapse: collapse;",e+=" padding: 0px; margin: "+(r=void 0===r?4*t:r)+"px;",e+='">',e+="<tbody>";for(var n=0;n<f.getModuleCount();n+=1){e+="<tr>";for(var o=0;o<f.getModuleCount();o+=1)e+='<td style="',e+=" border-width: 0px; border-style: none;",e+=" border-collapse: collapse;",e+=" padding: 0px; margin: 0px;",e+=" width: "+t+"px;",e+=" height: "+t+"px;",e+=" background-color: ",e+=f.isDark(n,o)?"#000000":"#ffffff",e+=";",e+='"/>';e+="</tr>"}return e+="</tbody>",e+="</table>"},f.createSvgTag=function(t,r,e,n){var o={};"object"==typeof arguments[0]&&(t=(o=arguments[0]).cellSize,r=o.margin,e=o.alt,n=o.title),t=t||2,r=void 0===r?4*t:r,(e="string"==typeof e?{text:e}:e||{}).text=e.text||null,e.id=e.text?e.id||"qrcode-description":null,(n="string"==typeof n?{text:n}:n||{}).text=n.text||null,n.id=n.text?n.id||"qrcode-title":null;var i,a,u,c,g=f.getModuleCount()*t+2*r,l="";for(c="l"+t+",0 0,"+t+" -"+t+",0 0,-"+t+"z ",l+='<svg version="1.1" xmlns="http://www.w3.org/2000/svg"',l+=o.scalable?"":' width="'+g+'px" height="'+g+'px"',l+=' viewBox="0 0 '+g+" "+g+'" ',l+=' preserveAspectRatio="xMinYMin meet"',l+=n.text||e.text?' role="img" aria-labelledby="'+y([n.id,e.id].join(" ").trim())+'"':"",l+=">",l+=n.text?'<title id="'+y(n.id)+'">'+y(n.text)+"</title>":"",l+=e.text?'<description id="'+y(e.id)+'">'+y(e.text)+"</description>":"",l+='<rect width="100%" height="100%" fill="white" cx="0" cy="0"/>',l+='<path d="',a=0;a<f.getModuleCount();a+=1)for(u=a*t+r,i=0;i<f.getModuleCount();i+=1)f.isDark(a,i)&&(l+="M"+(i*t+r)+","+u+c);return l+='" stroke="transparent" fill="black"/>',l+="</svg>"},f.createDataURL=function(t,r){t=t||2,r=void 0===r?4*t:r;var e=f.getModuleCount()*t+2*r,n=r,o=e-r;return I(e,e,function(r,e){if(n<=r&&r<o&&n<=e&&e<o){var i=Math.floor((r-n)/t),a=Math.floor((e-n)/t);return f.isDark(a,i)?0:1}return 1})},f.createImgTag=function(t,r,e){t=t||2,r=void 0===r?4*t:r;var n=f.getModuleCount()*t+2*r,o="";return o+="<img",o+=' src="',o+=f.createDataURL(t,r),o+='"',o+=' width="',o+=n,o+='"',o+=' height="',o+=n,o+='"',e&&(o+=' alt="',o+=y(e),o+='"'),o+="/>"};var y=function(t){for(var r="",e=0;e<t.length;e+=1){var n=t.charAt(e);switch(n){case"<":r+="&lt;";break;case">":r+="&gt;";break;case"&":r+="&amp;";break;case'"':r+="&quot;";break;default:r+=n}}return r};return f.createASCII=function(t,r){if((t=t||1)<2)return function(t){t=void 0===t?2:t;var r,e,n,o,i,a=1*f.getModuleCount()+2*t,u=t,c=a-t,g={"██":"█","█ ":"▀"," █":"▄","  ":" "},l={"██":"▀","█ ":"▀"," █":" ","  ":" "},h="";for(r=0;r<a;r+=2){for(n=Math.floor((r-u)/1),o=Math.floor((r+1-u)/1),e=0;e<a;e+=1)i="█",u<=e&&e<c&&u<=r&&r<c&&f.isDark(n,Math.floor((e-u)/1))&&(i=" "),u<=e&&e<c&&u<=r+1&&r+1<c&&f.isDark(o,Math.floor((e-u)/1))?i+=" ":i+="█",h+=t<1&&r+1>=c?l[i]:g[i];h+="\n"}return a%2&&t>0?h.substring(0,h.length-a-1)+Array(a+1).join("▀"):h.substring(0,h.length-1)}(r);t-=1,r=void 0===r?2*t:r;var e,n,o,i,a=f.getModuleCount()*t+2*r,u=r,c=a-r,g=Array(t+1).join("██"),l=Array(t+1).join("  "),h="",s="";for(e=0;e<a;e+=1){for(o=Math.floor((e-u)/t),s="",n=0;n<a;n+=1)i=1,u<=n&&n<c&&u<=e&&e<c&&f.isDark(o,Math.floor((n-u)/t))&&(i=0),s+=i?g:l;for(o=0;o<t;o+=1)h+=s+"\n"}return h.substring(0,h.length-1)},f.renderTo2dContext=function(t,r){r=r||2;for(var e=f.getModuleCount(),n=0;n<e;n++)for(var o=0;o<e;o++)t.fillStyle=f.isDark(n,o)?"black":"white",t.fillRect(o*r,n*r,r,r)},f};t.stringToBytes=(t.stringToBytesFuncs={default:function(t){for(var r=[],e=0;e<t.length;e+=1){var n=t.charCodeAt(e);r.push(255&n)}return r}}).default,t.createStringToBytes=function(t,r){var e=function(){for(var e=S(t),n=function(){var t=e.read();if(-1==t)throw"eof";return t},o=0,i={};;){var a=e.read();if(-1==a)break;var u=n(),f=n()<<8|n();i[String.fromCharCode(a<<8|u)]=f,o+=1}if(o!=r)throw o+" != "+r;return i}(),n="?".charCodeAt(0);return function(t){for(var r=[],o=0;o<t.length;o+=1){var i=t.charCodeAt(o);if(i<128)r.push(i);else{var a=e[t.charAt(o)];"number"==typeof a?(255&a)==a?r.push(a):(r.push(a>>>8),r.push(255&a)):r.push(n)}}return r}};var r,e,n,o,i,a=1,u=2,f=4,c=8,g={L:1,M:0,Q:3,H:2},l=0,h=1,s=2,v=3,d=4,w=5,p=6,y=7,B=(r=[[],[6,18],[6,22],[6,26],[6,30],[6,34],[6,22,38],[6,24,42],[6,26,46],[6,28,50],[6,30,54],[6,32,58],[6,34,62],[6,26,46,66],[6,26,48,70],[6,26,50,74],[6,30,54,78],[6,30,56,82],[6,30,58,86],[6,34,62,90],[6,28,50,72,94],[6,26,50,74,98],[6,30,54,78,102],[6,28,54,80,106],[6,32,58,84,110],[6,30,58,86,114],[6,34,62,90,118],[6,26,50,74,98,122],[6,30,54,78,102,126],[6,26,52,78,104,130],[6,30,56,82,108,134],[6,34,60,86,112,138],[6,30,58,86,114,142],[6,34,62,90,118,146],[6,30,54,78,102,126,150],[6,24,50,76,102,128,154],[6,28,54,80,106,132,158],[6,32,58,84,110,136,162],[6,26,54,82,110,138,166],[6,30,58,86,114,142,170]],e=1335,n=7973,i=function(t){for(var r=0;0!=t;)r+=1,t>>>=1;return r},(o={}).getBCHTypeInfo=function(t){for(var r=t<<10;i(r)-i(e)>=0;)r^=e<<i(r)-i(e);return 21522^(t<<10|r)},o.getBCHTypeNumber=function(t){for(var r=t<<12;i(r)-i(n)>=0;)r^=n<<i(r)-i(n);return t<<12|r},o.getPatternPosition=function(t){return r[t-1]},o.getMaskFunction=function(t){switch(t){case l:return function(t,r){return(t+r)%2==0};case h:return function(t,r){return t%2==0};case s:return function(t,r){return r%3==0};case v:return function(t,r){return(t+r)%3==0};case d:return function(t,r){return(Math.floor(t/2)+Math.floor(r/3))%2==0};case w:return function(t,r){return t*r%2+t*r%3==0};case p:return function(t,r){return(t*r%2+t*r%3)%2==0};case y:return function(t,r){return(t*r%3+(t+r)%2)%2==0};default:throw"bad maskPattern:"+t}},o.getErrorCorrectPolynomial=function(t){for(var r=k([1],0),e=0;e<t;e+=1)r=r.multiply(k([1,C.gexp(e)],0));return r},o.getLengthInBits=function(t,r){if(1<=r&&r<10)switch(t){case a:return 10;case u:return 9;case f:case c:return 8;default:throw"mode:"+t}else if(r<27)switch(t){case a:return 12;case u:return 11;case f:return 16;case c:return 10;default:throw"mode:"+t}else{if(!(r<41))throw"type:"+r;switch(t){case a:return 14;case u:return 13;case f:return 16;case c:return 12;default:throw"mode:"+t}}},o.getLostPoint=function(t){for(var r=t.getModuleCount(),e=0,n=0;n<r;n+=1)for(var o=0;o<r;o+=1){for(var i=0,a=t.isDark(n,o),u=-1;u<=1;u+=1)if(!(n+u<0||r<=n+u))for(var f=-1;f<=1;f+=1)o+f<0||r<=o+f||0==u&&0==f||a==t.isDark(n+u,o+f)&&(i+=1);i>5&&(e+=3+i-5)}for(n=0;n<r-1;n+=1)for(o=0;o<r-1;o+=1){var c=0;t.isDark(n,o)&&(c+=1),t.isDark(n+1,o)&&(c+=1),t.isDark(n,o+1)&&(c+=1),t.isDark(n+1,o+1)&&(c+=1),0!=c&&4!=c||(e+=3)}for(n=0;n<r;n+=1)for(o=0;o<r-6;o+=1)t.isDark(n,o)&&!t.isDark(n,o+1)&&t.isDark(n,o+2)&&t.isDark(n,o+3)&&t.isDark(n,o+4)&&!t.isDark(n,o+5)&&t.isDark(n,o+6)&&(e+=40);for(o=0;o<r;o+=1)for(n=0;n<r-6;n+=1)t.isDark(n,o)&&!t.isDark(n+1,o)&&t.isDark(n+2,o)&&t.isDark(n+3,o)&&t.isDark(n+4,o)&&!t.isDark(n+5,o)&&t.isDark(n+6,o)&&(e+=40);var g=0;for(o=0;o<r;o+=1)for(n=0;n<r;n+=1)t.isDark(n,o)&&(g+=1);return e+=Math.abs(100*g/r/r-50)/5*10},o),C=function(){for(var t=new Array(256),r=new Array(256),e=0;e<8;e+=1)t[e]=1<<e;for(e=8;e<256;e+=1)t[e]=t[e-4]^t[e-5]^t[e-6]^t[e-8];for(e=0;e<255;e+=1)r[t[e]]=e;var n={glog:function(t){if(t<1)throw"glog("+t+")";return r[t]},gexp:function(r){for(;r<0;)r+=255;for(;r>=256;)r-=255;return t[r]}};return n}();function k(t,r){if(void 0===t.length)throw t.length+"/"+r;var e=function(){for(var e=0;e<t.length&&0==t[e];)e+=1;for(var n=new Array(t.length-e+r),o=0;o<t.length-e;o+=1)n[o]=t[o+e];return n}(),n={getAt:function(t){return e[t]},getLength:function(){return e.length},multiply:function(t){for(var r=new Array(n.getLength()+t.getLength()-1),e=0;e<n.getLength();e+=1)for(var o=0;o<t.getLength();o+=1)r[e+o]^=C.gexp(C.glog(n.getAt(e))+C.glog(t.getAt(o)));return k(r,0)},mod:function(t){if(n.getLength()-t.getLength()<0)return n;for(var r=C.glog(n.getAt(0))-C.glog(t.getAt(0)),e=new Array(n.getLength()),o=0;o<n.getLength();o+=1)e[o]=n.getAt(o);for(o=0;o<t.getLength();o+=1)e[o]^=C.gexp(C.glog(t.getAt(o))+r);return k(e,0).mod(t)}};return n}var A=function(){var t=[[1,26,19],[1,26,16],[1,26,13],[1,26,9],[1,44,34],[1,44,28],[1,44,22],[1,44,16],[1,70,55],[1,70,44],[2,35,17],[2,35,13],[1,100,80],[2,50,32],[2,50,24],[4,25,9],[1,134,108],[2,67,43],[2,33,15,2,34,16],[2,33,11,2,34,12],[2,86,68],[4,43,27],[4,43,19],[4,43,15],[2,98,78],[4,49,31],[2,32,14,4,33,15],[4,39,13,1,40,14],[2,121,97],[2,60,38,2,61,39],[4,40,18,2,41,19],[4,40,14,2,41,15],[2,146,116],[3,58,36,2,59,37],[4,36,16,4,37,17],[4,36,12,4,37,13],[2,86,68,2,87,69],[4,69,43,1,70,44],[6,43,19,2,44,20],[6,43,15,2,44,16],[4,101,81],[1,80,50,4,81,51],[4,50,22,4,51,23],[3,36,12,8,37,13],[2,116,92,2,117,93],[6,58,36,2,59,37],[4,46,20,6,47,21],[7,42,14,4,43,15],[4,133,107],[8,59,37,1,60,38],[8,44,20,4,45,21],[12,33,11,4,34,12],[3,145,115,1,146,116],[4,64,40,5,65,41],[11,36,16,5,37,17],[11,36,12,5,37,13],[5,109,87,1,110,88],[5,65,41,5,66,42],[5,54,24,7,55,25],[11,36,12,7,37,13],[5,122,98,1,123,99],[7,73,45,3,74,46],[15,43,19,2,44,20],[3,45,15,13,46,16],[1,135,107,5,136,108],[10,74,46,1,75,47],[1,50,22,15,51,23],[2,42,14,17,43,15],[5,150,120,1,151,121],[9,69,43,4,70,44],[17,50,22,1,51,23],[2,42,14,19,43,15],[3,141,113,4,142,114],[3,70,44,11,71,45],[17,47,21,4,48,22],[9,39,13,16,40,14],[3,135,107,5,136,108],[3,67,41,13,68,42],[15,54,24,5,55,25],[15,43,15,10,44,16],[4,144,116,4,145,117],[17,68,42],[17,50,22,6,51,23],[19,46,16,6,47,17],[2,139,111,7,140,112],[17,74,46],[7,54,24,16,55,25],[34,37,13],[4,151,121,5,152,122],[4,75,47,14,76,48],[11,54,24,14,55,25],[16,45,15,14,46,16],[6,147,117,4,148,118],[6,73,45,14,74,46],[11,54,24,16,55,25],[30,46,16,2,47,17],[8,132,106,4,133,107],[8,75,47,13,76,48],[7,54,24,22,55,25],[22,45,15,13,46,16],[10,142,114,2,143,115],[19,74,46,4,75,47],[28,50,22,6,51,23],[33,46,16,4,47,17],[8,152,122,4,153,123],[22,73,45,3,74,46],[8,53,23,26,54,24],[12,45,15,28,46,16],[3,147,117,10,148,118],[3,73,45,23,74,46],[4,54,24,31,55,25],[11,45,15,31,46,16],[7,146,116,7,147,117],[21,73,45,7,74,46],[1,53,23,37,54,24],[19,45,15,26,46,16],[5,145,115,10,146,116],[19,75,47,10,76,48],[15,54,24,25,55,25],[23,45,15,25,46,16],[13,145,115,3,146,116],[2,74,46,29,75,47],[42,54,24,1,55,25],[23,45,15,28,46,16],[17,145,115],[10,74,46,23,75,47],[10,54,24,35,55,25],[19,45,15,35,46,16],[17,145,115,1,146,116],[14,74,46,21,75,47],[29,54,24,19,55,25],[11,45,15,46,46,16],[13,145,115,6,146,116],[14,74,46,23,75,47],[44,54,24,7,55,25],[59,46,16,1,47,17],[12,151,121,7,152,122],[12,75,47,26,76,48],[39,54,24,14,55,25],[22,45,15,41,46,16],[6,151,121,14,152,122],[6,75,47,34,76,48],[46,54,24,10,55,25],[2,45,15,64,46,16],[17,152,122,4,153,123],[29,74,46,14,75,47],[49,54,24,10,55,25],[24,45,15,46,46,16],[4,152,122,18,153,123],[13,74,46,32,75,47],[48,54,24,14,55,25],[42,45,15,32,46,16],[20,147,117,4,148,118],[40,75,47,7,76,48],[43,54,24,22,55,25],[10,45,15,67,46,16],[19,148,118,6,149,119],[18,75,47,31,76,48],[34,54,24,34,55,25],[20,45,15,61,46,16]],r=function(t,r){var e={};return e.totalCount=t,e.dataCount=r,e},e={};return e.getRSBlocks=function(e,n){var o=function(r,e){switch(e){case g.L:return t[4*(r-1)+0];case g.M:return t[4*(r-1)+1];case g.Q:return t[4*(r-1)+2];case g.H:return t[4*(r-1)+3];default:return}}(e,n);if(void 0===o)throw"bad rs block @ typeNumber:"+e+"/errorCorrectionLevel:"+n;for(var i=o.length/3,a=[],u=0;u<i;u+=1)for(var f=o[3*u+0],c=o[3*u+1],l=o[3*u+2],h=0;h<f;h+=1)a.push(r(c,l));return a},e}(),b=function(){var t=[],r=0,e={getBuffer:function(){return t},getAt:function(r){var e=Math.floor(r/8);return 1==(t[e]>>>7-r%8&1)},put:function(t,r){for(var n=0;n<r;n+=1)e.putBit(1==(t>>>r-n-1&1))},getLengthInBits:function(){return r},putBit:function(e){var n=Math.floor(r/8);t.length<=n&&t.push(0),e&&(t[n]|=128>>>r%8),r+=1}};return e},M=function(t){var r=a,e=t,n={getMode:function(){return r},getLength:function(t){return e.length},write:function(t){for(var r=e,n=0;n+2<r.length;)t.put(o(r.substring(n,n+3)),10),n+=3;n<r.length&&(r.length-n==1?t.put(o(r.substring(n,n+1)),4):r.length-n==2&&t.put(o(r.substring(n,n+2)),7))}},o=function(t){for(var r=0,e=0;e<t.length;e+=1)r=10*r+i(t.charAt(e));return r},i=function(t){if("0"<=t&&t<="9")return t.charCodeAt(0)-"0".charCodeAt(0);throw"illegal char :"+t};return n},x=function(t){var r=u,e=t,n={getMode:function(){return r},getLength:function(t){return e.length},write:function(t){for(var r=e,n=0;n+1<r.length;)t.put(45*o(r.charAt(n))+o(r.charAt(n+1)),11),n+=2;n<r.length&&t.put(o(r.charAt(n)),6)}},o=function(t){if("0"<=t&&t<="9")return t.charCodeAt(0)-"0".charCodeAt(0);if("A"<=t&&t<="Z")return t.charCodeAt(0)-"A".charCodeAt(0)+10;switch(t){case" ":return 36;case"$":return 37;case"%":return 38;case"*":return 39;case"+":return 40;case"-":return 41;case".":return 42;case"/":return 43;case":":return 44;default:throw"illegal char :"+t}};return n},m=function(r){var e=f,n=t.stringToBytes(r),o={getMode:function(){return e},getLength:function(t){return n.length},write:function(t){for(var r=0;r<n.length;r+=1)t.put(n[r],8)}};return o},L=function(r){var e=c,n=t.stringToBytesFuncs.SJIS;if(!n)throw"sjis not supported.";!function(){var t=n("友");if(2!=t.length||38726!=(t[0]<<8|t[1]))throw"sjis not supported."}();var o=n(r),i={getMode:function(){return e},getLength:function(t){return~~(o.length/2)},write:function(t){for(var r=o,e=0;e+1<r.length;){var n=(255&r[e])<<8|255&r[e+1];if(33088<=n&&n<=40956)n-=33088;else{if(!(57408<=n&&n<=60351))throw"illegal char at "+(e+1)+"/"+n;n-=49472}n=192*(n>>>8&255)+(255&n),t.put(n,13),e+=2}if(e<r.length)throw"illegal char at "+(e+1)}};return i},D=function(){var t=[],r={writeByte:function(r){t.push(255&r)},writeShort:function(t){r.writeByte(t),r.writeByte(t>>>8)},writeBytes:function(t,e,n){e=e||0,n=n||t.length;for(var o=0;o<n;o+=1)r.writeByte(t[o+e])},writeString:function(t){for(var e=0;e<t.length;e+=1)r.writeByte(t.charCodeAt(e))},toByteArray:function(){return t},toString:function(){var r="";r+="[";for(var e=0;e<t.length;e+=1)e>0&&(r+=","),r+=t[e];return r+="]"}};return r},S=function(t){var r=t,e=0,n=0,o=0,i={read:function(){for(;o<8;){if(e>=r.length){if(0==o)return-1;throw"unexpected end of file./"+o}var t=r.charAt(e);if(e+=1,"="==t)return o=0,-1;t.match(/^\s$/)||(n=n<<6|a(t.charCodeAt(0)),o+=6)}var i=n>>>o-8&255;return o-=8,i}},a=function(t){if(65<=t&&t<=90)return t-65;if(97<=t&&t<=122)return t-97+26;if(48<=t&&t<=57)return t-48+52;if(43==t)return 62;if(47==t)return 63;throw"c:"+t};return i},I=function(t,r,e){for(var n=function(t,r){var e=t,n=r,o=new Array(t*r),i={setPixel:function(t,r,n){o[r*e+t]=n},write:function(t){t.writeString("GIF87a"),t.writeShort(e),t.writeShort(n),t.writeByte(128),t.writeByte(0),t.writeByte(0),t.writeByte(0),t.writeByte(0),t.writeByte(0),t.writeByte(255),t.writeByte(255),t.writeByte(255),t.writeString(","),t.writeShort(0),t.writeShort(0),t.writeShort(e),t.writeShort(n),t.writeByte(0);var r=a(2);t.writeByte(2);for(var o=0;r.length-o>255;)t.writeByte(255),t.writeBytes(r,o,255),o+=255;t.writeByte(r.length-o),t.writeBytes(r,o,r.length-o),t.writeByte(0),t.writeString(";")}},a=function(t){for(var r=1<<t,e=1+(1<<t),n=t+1,i=u(),a=0;a<r;a+=1)i.add(String.fromCharCode(a));i.add(String.fromCharCode(r)),i.add(String.fromCharCode(e));var f,c,g,l=D(),h=(f=l,c=0,g=0,{write:function(t,r){if(t>>>r!=0)throw"length over";for(;c+r>=8;)f.writeByte(255&(t<<c|g)),r-=8-c,t>>>=8-c,g=0,c=0;g|=t<<c,c+=r},flush:function(){c>0&&f.writeByte(g)}});h.write(r,n);var s=0,v=String.fromCharCode(o[s]);for(s+=1;s<o.length;){var d=String.fromCharCode(o[s]);s+=1,i.contains(v+d)?v+=d:(h.write(i.indexOf(v),n),i.size()<4095&&(i.size()==1<<n&&(n+=1),i.add(v+d)),v=d)}return h.write(i.indexOf(v),n),h.write(e,n),h.flush(),l.toByteArray()},u=function(){var t={},r=0,e={add:function(n){if(e.contains(n))throw"dup key:"+n;t[n]=r,r+=1},size:function(){return r},indexOf:function(r){return t[r]},contains:function(r){return void 0!==t[r]}};return e};return i}(t,r),o=0;o<r;o+=1)for(var i=0;i<t;i+=1)n.setPixel(i,o,e(i,o));var a=D();n.write(a);for(var u=function(){var t=0,r=0,e=0,n="",o={},i=function(t){n+=String.fromCharCode(a(63&t))},a=function(t){if(t<0);else{if(t<26)return 65+t;if(t<52)return t-26+97;if(t<62)return t-52+48;if(62==t)return 43;if(63==t)return 47}throw"n:"+t};return o.writeByte=function(n){for(t=t<<8|255&n,r+=8,e+=1;r>=6;)i(t>>>r-6),r-=6},o.flush=function(){if(r>0&&(i(t<<6-r),t=0,r=0),e%3!=0)for(var o=3-e%3,a=0;a<o;a+=1)n+="="},o.toString=function(){return n},o}(),f=a.toByteArray(),c=0;c<f.length;c+=1)u.writeByte(f[c]);return u.flush(),"data:image/gif;base64,"+u};return t}();qrcode.stringToBytesFuncs["UTF-8"]=function(t){return function(t){for(var r=[],e=0;e<t.length;e++){var n=t.charCodeAt(e);n<128?r.push(n):n<2048?r.push(192|n>>6,128|63&n):n<55296||n>=57344?r.push(224|n>>12,128|n>>6&63,128|63&n):(e++,n=65536+((1023&n)<<10|1023&t.charCodeAt(e)),r.push(240|n>>18,128|n>>12&63,128|n>>6&63,128|63&n))}return r}(t)},function(t){"function"==typeof define&&define.amd?define([],t):"object"==typeof exports&&(module.exports=t())}(function(){return qrcode});
JS;
	exit;
}
add_action( 'wp_ajax_er_sj_qr', 'er_sj_qr' );
add_action( 'wp_ajax_nopriv_er_sj_qr', 'er_sj_qr' );
