<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ============================================================
// This emits all custom block markup, shortcodes,
// head scripts, and front-end JS for the theme.
// 
// CONTENTS
// 
//   0. SETTINGS                numbers, wording, links: change things here
//   1. SHARED HELPERS          taxonomy map, breadcrumbs, reading time,
//                              German content: counting rule, English version,
//                              lang / hreflang (er_uncounted_ids, er_is_german …)
//                              (defined first; called by hero, cards, footer)
//   2. PERFORMANCE             featured-image eager + fetchpriority filter
//   3. HEAD SCRIPTS            dark-mode FOUC guard,
//                              dynamic per-term hero background injection
//   4. HERO                    [er_hero] context-aware hero block
//   5. CPT ARCHIVE + FILTER    [er_cpt_archive] grid, filter pills, render-card,
//                              infinite-scroll AJAX handler
//   6. RELATED POSTS           [er_related_posts] slider
//   7. POST FOOTER             [er_post_footer] share row + prev/next + helpers
//   8. HEADER / NAV            header-scroll/submenu JS, mobile-overlay inject
//   9. FOOTER SCRIPTS (JS)     dark-mode toggle, card reveal, infinite scroll,
//                              details-open persistence, whole-card click
//   10. TEMPLATE ROUTING       my-quotes + my-traits -> single-no-sidebar.html
//   
// CONVENTIONS
// 
//   - Shared helpers are defined in §1 so every later shortcode can call them
//     in any context (front end, admin-ajax, editor).
//   - Hook registrations (add_action/add_shortcode) fire at runtime, so their
//     order in this file does not affect execution; sections are grouped by topic.
//   - Static CSS lives in style.css / the CSS snippet, NOT here (only per-page
//     hero background URLs are printed inline).
// ============================================================

// ======================================
// 0. SETTINGS: CHANGE THINGS HERE
// ======================================
// Numbers, wording and links, all in one place. No styles in this snippet:
// they live in the theme's style.css and in the snippet "Eric Companion CSS".

defined( 'ER_CARDS_PER_LOAD' ) || define( 'ER_CARDS_PER_LOAD', 12 ); // collection grids: cards shown first and added per infinite-scroll step
defined( 'ER_RELATED_MAX' )    || define( 'ER_RELATED_MAX', 12 );    // related entries below an entry (at most)
defined( 'ER_SLIDER_FROM' )    || define( 'ER_SLIDER_FROM', 5 );     // card rows: from this many cards a slider, below that a static row
defined( 'ER_READING_WPM' )    || define( 'ER_READING_WPM', 200 );   // reading speed (words per minute): "min read" on cards and in [post_stats] (Site Essentials)

// Every text this snippet writes, grouped by where it shows (keep %d)
function er_companion_texts() {
	return array(
		// Breadcrumbs
		'crumbBlog'    => 'Blog',
		'crumbSearch'  => 'Search Results',
		'crumb404'     => '404 Not Found',
		// Cards and collection grids
		'minRead'      => '%d min read',
		'noEntries'    => 'No entries found.',
		// Below an entry: share row and previous / next
		'shareThis'    => 'Share This',
		'postNav'      => 'Post navigation',	// screen readers
		// Header, footer, mobile menu
		'socials'      => 'Socials',
		'support'      => 'Support',
		// Dark mode switch (screen readers)
		'darkOn'       => 'Dark mode enabled',
		'darkOff'      => 'Light mode enabled',
		// Editor: meta box on pages
		'enVersion'    => 'English version',
		'enNone'       => '— None —',
		'enHelp'       => 'German pages only: the English original.',
	);
}

// Links and icons
function er_companion_links() {
	return array(
		// Socials / Support: header, footer and mobile menu
		'socialsUrl'  => '/about-me/contact/',
		'socialsIcon' => '/wp-content/uploads/2025/12/users-solid-white.svg',
		'supportUrl'  => '/this-site/',
		'supportIcon' => '/wp-content/uploads/2025/12/hand-holding-dollar-solid-full-white.svg',
		// The row of other collections below a collection's grid: heading anchor, heading, overview page
		'overviews'   => array(
			'category' => array( 'My-Blog', 'My Blog', '/personal/my-blog/' ),
			'topics'   => array( 'My-Interests', 'My Interests', '/personal/my-interests/' ),
			'things'   => array( 'My-Pages', 'My Pages', '/my-pages/' ),
			'groups'   => array( 'My-Quotes', 'My Quotes', '/about-me/my-quotes/' ),
			'types'    => array( 'My-Traits', 'My Traits', '/professional/my-background/my-traits/' ),
		),
		// Share row below an entry, in this order: label => [ address ({url}, {title}), icon ]
		'share'       => array(
			'Bluesky'  => array( 'https://bsky.app/intent/compose?text={title}%20{url}', 'bluesky' ),
			'Facebook' => array( 'https://www.facebook.com/sharer/sharer.php?u={url}', 'facebook' ),
			'LINE'     => array( 'https://social-plugins.line.me/lineit/share?url={url}', 'line' ),
			'LinkedIn' => array( 'https://www.linkedin.com/sharing/share-offsite/?url={url}', 'linkedin' ),
			'Telegram' => array( 'https://t.me/share/url?url={url}&text={title}', 'telegram' ),
			'Threads'  => array( 'https://www.threads.net/intent/post?text={title}%20{url}', 'threads' ),
			'WhatsApp' => array( 'https://api.whatsapp.com/send?text={title}%20{url}', 'whatsapp' ),
			'Email'    => array( 'mailto:?subject={title}&body={url}', 'email' ),
		),
	);
}

// ======================================
// 1. SHARED HELPERS
// ======================================

// Defined before everything that calls them so they're available in all
// execution contexts: front end, admin-ajax (infinite scroll), and editor.

// ---- 1a. Taxonomy map — single source of truth for post_type → taxonomy ----
function er_taxonomy_map() {
	return array(
		'post'         => 'category',
		'page'         => 'things',
		'my-interests' => 'topics',
		'my-quotes'    => 'groups',
		'my-traits'    => 'types',
	);
}

// ---- Things hub: canonical "My Pages" landing for the `page` taxonomy ----
// Stored as an option so it survives slug / parent changes and edits in one place.
// er_things_hub_id() is the single accessor; both er_things_hub_url() and the breadcrumb trail read the option only through it.
function er_things_hub_id() {
	return (int) get_option( 'er_things_hub_page' );
}

function er_things_hub_url() {
	$id = er_things_hub_id();
	return $id ? get_permalink( $id ) : home_url( '/' );
}

// Per-row taxonomy for mixed-type (search) loops: map lookup, else 'category'.
function er_taxonomy_for_post( $post_id ) {
	$map  = er_taxonomy_map();
	$type = get_post_type( $post_id );
	return isset( $map[ $type ] ) ? $map[ $type ] : 'category';
}

// ---- 1b. Breadcrumbs — [er_breadcrumbs]; called by er_hero_shortcode() ----
// Defined here (not in Site Essentials & Cookies PHP) so it's available on front end, admin, and AJAX.
function er_breadcrumbs() {
	// Never show on front page
	if ( is_front_page() ) {
		return '';
	}

	$separator = '<svg class="er-breadcrumb-separator" fill="currentColor" width="8" height="8" viewBox="0 0 8 8" aria-hidden="true" focusable="false"><path d="M2,6.9L4.8,4L2,1.1L2.6,0l4,4l-4,4L2,6.9z"></path></svg>';

	$home_icon = '<svg class="er-breadcrumb-home-icon" width="15" height="15" viewBox="0 0 15 15" fill="currentColor" aria-hidden="true" focusable="false"><path d="M7.5 1 0 7.8h2.1v6.1h4.1V9.8h2.7v4.1H13V7.8h2.1L7.5 1Z"></path></svg>';

	$crumbs = [];

	// Shared pieces of the trail
	$link = function ( $id ) {
		return '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
	};
	// Parent pages of $id, then $id itself (unless $self is false), all linked
	$page_trail = function ( $id, $self = true ) use ( $link ) {
		$trail = array_map( $link, array_reverse( get_post_ancestors( $id ) ) );
		if ( $self ) {
			$trail[] = $link( $id );
		}
		return $trail;
	};
	// A CPT's listing Page (a real Page whose slug equals the CPT archive slug, e.g. /my-interests/)
	// with its parents (e.g. "Personal"). Null when the post type has no archive.
	$listing_trail = function ( $post_type ) use ( $page_trail ) {
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj || ! $post_type_obj->has_archive ) {
			return null;
		}
		$archive_slug = is_string( $post_type_obj->has_archive ) ? $post_type_obj->has_archive : $post_type;

		// Look the listing Page up by SLUG, not path: get_page_by_path() requires the
		// full hierarchical path (e.g. 'personal/my-interests'), so a child page would
		// be missed. A slug query finds it regardless of its parent.
		$listing_pages = get_posts( array(
			'name'        => $archive_slug,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => 1,
		) );
		if ( ! empty( $listing_pages ) && $listing_pages[0] instanceof WP_Post ) {
			return $page_trail( $listing_pages[0]->ID );
		}

		// This line rarely runs. Visiting /my-interests/ (and /my-traits/, /my-quotes/) redirects to real Page instead
		// See redirect list in Site Essentials & Cookies PHP. This is just a backup in case that ever changes.
		$archive_link = get_post_type_archive_link( $post_type );
		return $archive_link ? [ '<a href="' . esc_url( $archive_link ) . '">' . esc_html( $post_type_obj->labels->name ) . '</a>' ] : [];
	};

	// Home
	$crumbs[] = '<a href="' . esc_url( home_url( '/' ) ) . '">' . $home_icon . '<span class="screen-reader-text">' . esc_html__( 'Home' ) . '</span></a>';

	if ( is_singular() ) {
		$post_type = get_post_type();

		if ( $post_type === 'post' ) {
			// Standard post: Home → Posts page (e.g. "My Blog") → Primary Category → Post title
			// The Posts-page crumb mirrors the is_home() branch: resolve the assigned
			// Posts page, walk its ancestors first, then the page itself — here LINKED,
			// because it is an ancestor level, not the current item.
			$posts_page_id = (int) get_option( 'page_for_posts' );
			if ( $posts_page_id ) {
				$crumbs = array_merge( $crumbs, $page_trail( $posts_page_id ) );
			}

			$categories = get_the_category();
			if ( ! empty( $categories ) ) {
				$primary_cat = $categories[0];
				$crumbs[] = '<a href="' . esc_url( get_category_link( $primary_cat->term_id ) ) . '">' . esc_html( $primary_cat->name ) . '</a>';
			}

		} elseif ( $post_type !== 'page' ) {
			// CPT single: Home → Listing-page ancestor(s) → Listing page → Primary term → Post title
			// Mirrors the 'post' branch; falls back to the bare post type archive link.
			$crumbs = array_merge( $crumbs, (array) $listing_trail( $post_type ) );

			// Primary term — the CPT equivalent of the post branch's primary category,
			// resolved through er_taxonomy_map() (the single source of truth).
			$taxonomy_map = er_taxonomy_map();
			if ( isset( $taxonomy_map[ $post_type ] ) ) {
				$post_terms = get_the_terms( get_the_ID(), $taxonomy_map[ $post_type ] );
				if ( ! empty( $post_terms ) && ! is_wp_error( $post_terms ) ) {
					$primary_term = $post_terms[0];
					$term_link    = get_term_link( $primary_term );
					if ( ! is_wp_error( $term_link ) ) {
						$crumbs[] = '<a href="' . esc_url( $term_link ) . '">' . esc_html( $primary_term->name ) . '</a>';
					}
				}
			}

		} else {
			// Static page: Home → Parent page(s) → Page title
			$crumbs = array_merge( $crumbs, $page_trail( get_the_ID(), false ) );

			// Filtered listing Page (e.g. /this-site/my-pages/?things=slug or
			// /personal/my-interests/?topics=slug): any Page hosting [er_cpt_archive]
			// with a taxonomy filter param in the URL. Iterate the taxonomies in
			// er_taxonomy_map() (single source of truth); for whichever filter param is
			// present and resolves to a real term, mirror the term-archive trail —
			// demote the page title to a LINK and make the filtered term the current
			// item, so the breadcrumb agrees with the hero, pills and grid (all of which
			// read this param). No valid param → this block is inert and the shared
			// current-item span below runs as before.
			$taxonomy_map = er_taxonomy_map();
			foreach ( $taxonomy_map as $map_taxonomy ) {
				if ( ! empty( $_GET[ $map_taxonomy ] ) ) {
					$filter_term = get_term_by( 'slug', sanitize_key( wp_unslash( $_GET[ $map_taxonomy ] ) ), $map_taxonomy );
					if ( $filter_term instanceof WP_Term ) {
						$crumbs[]            = '<a href="' . esc_url( get_permalink( get_the_ID() ) ) . '">' . esc_html( get_the_title() ) . '</a>';
						$crumbs[]            = '<span aria-current="page">' . esc_html( $filter_term->name ) . '</span>';
						$er_crumb_self_built = true;
						break;
					}
				}
			}
		}

		// Current item — no link
		// Skipped only when a branch above already appended its own current item (filtered listing page). All other singular contexts are unaffected.
		if ( empty( $er_crumb_self_built ) ) {
			$crumbs[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
		}

	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();

		// Standard category/tag: anchor under the assigned Posts page (e.g. Personal → My Blog),
		// mirroring the 'post' single branch so a category/tag trail matches its posts' trail.
		if ( is_category() || is_tag() ) {
			$posts_page_id = (int) get_option( 'page_for_posts' );
			if ( $posts_page_id ) {
				$crumbs = array_merge( $crumbs, $page_trail( $posts_page_id ) );
			}
		}

		if ( is_tax() ) {
			// Taxonomy archive: Home → Listing-page ancestor(s) → Listing page → Term name,
			// exactly like the CPT single branch, so a topics/groups/types trail matches its CPT's trail.
			$taxonomy   = $term->taxonomy;
			$tax_obj    = get_taxonomy( $taxonomy );
			$post_types = $tax_obj ? (array) $tax_obj->object_type : [];

			// Use first associated CPT that has an archive
			foreach ( $post_types as $pt ) {
				$trail = $listing_trail( $pt );
				if ( $trail !== null ) {
					$crumbs = array_merge( $crumbs, $trail );
					break;
				}
			}
		}

		// Parent term (if any)
		if ( ! empty( $term->parent ) ) {
			$parent_term = get_term( $term->parent, $term->taxonomy );
			if ( $parent_term && ! is_wp_error( $parent_term ) ) {
				$crumbs[] = '<a href="' . esc_url( get_term_link( $parent_term ) ) . '">' . esc_html( $parent_term->name ) . '</a>';
			}
		}

		// Current term — no link.
		// For `things` (taxonomy on the page post type, no CPT archive), route the trail through the My Pages hub, then end on the term name
		if ( $term->taxonomy === 'things' ) {
			$hub_id = er_things_hub_id();
			if ( $hub_id ) {
				$crumbs = array_merge( $crumbs, $page_trail( $hub_id ) );
			}
		}
		$crumbs[] = '<span aria-current="page">' . esc_html( $term->name ) . '</span>';

	} elseif ( is_home() ) {
		// Blog posts index — mirror the static-page crumb trail for the assigned Posts page
		$posts_page_id = (int) get_option( 'page_for_posts' );
		if ( $posts_page_id ) {
			$crumbs = array_merge( $crumbs, $page_trail( $posts_page_id, false ) );

			// Filtered blog listing (e.g. /personal/my-blog/?category=slug): same pattern
			// as the CPT listing branch above. The blog's taxonomy is 'category'
			// (er_taxonomy_map()['post']). When ?category= resolves to a real term, demote
			// the Posts-page title to a LINK and make the filtered term the current item,
			// matching the native /category/<slug>/ trail. No/invalid param → unchanged.
			$blog_taxonomy = er_taxonomy_map()['post']; // 'category'
			if ( ! empty( $_GET[ $blog_taxonomy ] ) ) {
				$filter_term = get_term_by( 'slug', sanitize_key( wp_unslash( $_GET[ $blog_taxonomy ] ) ), $blog_taxonomy );
			}
			if ( ! empty( $filter_term ) && $filter_term instanceof WP_Term ) {
				$crumbs[] = '<a href="' . esc_url( get_permalink( $posts_page_id ) ) . '">' . esc_html( get_the_title( $posts_page_id ) ) . '</a>';
				$crumbs[] = '<span aria-current="page">' . esc_html( $filter_term->name ) . '</span>';
			} else {
				$crumbs[] = '<span aria-current="page">' . esc_html( get_the_title( $posts_page_id ) ) . '</span>';
			}
		} else {
			$crumbs[] = '<span aria-current="page">' . esc_html( er_companion_texts()['crumbBlog'] ) . '</span>';
		}
	
	} elseif ( is_post_type_archive() ) {
		// CPT archive root: Home → CPT name
		$post_type_obj = get_queried_object();
		$crumbs[]      = '<span aria-current="page">' . esc_html( $post_type_obj->labels->name ) . '</span>';

	} elseif ( is_search() ) {
		$crumbs[] = '<span aria-current="page">' . esc_html( er_companion_texts()['crumbSearch'] ) . '</span>';

	} elseif ( is_404() ) {
		$crumbs[] = '<span aria-current="page">' . esc_html( er_companion_texts()['crumb404'] ) . '</span>';
	}

	// Build output
	$output  = '<nav class="er-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb' ) . '">';
	$output .= '<ol>';

	foreach ( $crumbs as $index => $crumb ) {
		$is_last = ( $index === count( $crumbs ) - 1 );
		$output .= '<li>';
		$output .= $crumb;
		if ( ! $is_last ) {
			$output .= $separator;
		}
		$output .= '</li>';
	}

	$output .= '</ol>';
	$output .= '</nav>';

	return $output;
}
add_shortcode( 'er_breadcrumbs', 'er_breadcrumbs' );

// ---- 1c. Reading time — used by er_render_card() for archive cards ----
// Reading speed: ER_READING_WPM in SETTINGS (this snippet runs everywhere, so front end, admin and AJAX agree).
// (On single posts, reading time shows via [post_stats] in the sidebar — no injection here.)

function er_reading_time( $post_id = null, $echo = false ) {
	$post_id    = $post_id ?: get_the_ID();
	$content    = get_post_field( 'post_content', $post_id );
	$word_count = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ) );
	$wpm        = ER_READING_WPM;
	$minutes    = max( 1, (int) ceil( $word_count / $wpm ) );
	$output = '<li class="er-reading-time">'
			.   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" '
			.       'width="1em" height="1em" '
			.       'fill="none" stroke="currentColor" '
			.       'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
			.       'aria-hidden="true">'
			.       '<circle cx="12" cy="12" r="10" fill="none"/>'
			.       '<polyline points="12 6 12 12 16 14" fill="none"/>'
			.   '</svg>'
			.   esc_html( sprintf( er_companion_texts()['minRead'], $minutes ) )
			. '</li>';
	if ( $echo ) {
		echo $output;
	} else {
		return $output;
	}
}

// ---- 1d. German content — the site-wide counting rule ----
// German = ticked "German" in the editor (panel "Language", taxonomy er_language,
// registered in CPTs & Taxonomies). German pages and quotes are translations for
// German-speaking clients, not more content, so they are a section of their own:
//   - never counted: counters, filter pills, tag clouds, Site Journey
//   - never in an automatic list: listings, random sliders, /surprise/, Related,
//     quote sliders, the Page List block, Display Posts (except where a shortcode
//     names them by id)
//   - still found by the site search, Google (sitemap) and the flags
// A German page names its English version (meta box below); the pairs drive the
// flags and redirect (Ninja Services) and lang / hreflang in the head (1e).
// Callers use these functions behind function_exists:
//   er_uncounted_ids()       ids of all published German items
//   er_is_german( $id )      one item
//   er_counted_total( $type ) published items of a type that count
//   er_lang_counterpart( $id ) the other language's page id, or 0

// Everything above in one cached lookup; cleared whenever content, a tick or a pair changes.
function er_german() {
	static $building = false;
	$g = get_transient( 'er_german' );
	if ( is_array( $g ) ) {
		return $g;
	}
	$g = array( 'ids' => array(), 'types' => array(), 'terms' => array(), 'pairs' => array() );
	if ( $building ) {
		return $g; // a filter below called back while the lookup runs
	}
	$building = true;
	$posts = get_posts( array(
		'post_type'      => array_keys( er_taxonomy_map() ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'tax_query'      => array( array( 'taxonomy' => 'er_language', 'field' => 'slug', 'terms' => 'german' ) ),
	) );
	foreach ( $posts as $p ) {
		$g['ids'][] = (int) $p->ID;
		$g['types'][ $p->post_type ] = ( $g['types'][ $p->post_type ] ?? 0 ) + 1;
		$en = (int) get_post_meta( $p->ID, 'er_en_version', true );
		if ( 'page' === $p->post_type && $en && 'publish' === get_post_status( $en ) ) {
			$g['pairs'][ $p->ID ] = $en; // German id => English id
		}
	}
	if ( $g['ids'] ) {
		// How many German items each topic holds, so shown counts can leave them out
		$taxonomies = array_values( array_diff( get_object_taxonomies( array_keys( er_taxonomy_map() ) ), array( 'er_language' ) ) );
		foreach ( wp_get_object_terms( $g['ids'], $taxonomies, array( 'fields' => 'all_with_object_id' ) ) as $t ) {
			$g['terms'][ $t->term_id ] = ( $g['terms'][ $t->term_id ] ?? 0 ) + 1;
		}
	}
	set_transient( 'er_german', $g, DAY_IN_SECONDS );
	$building = false;
	return $g;
}
function er_german_flush() { delete_transient( 'er_german' ); }
add_action( 'save_post', 'er_german_flush', 20 ); // after the meta box below has saved
add_action( 'deleted_post', 'er_german_flush' );
add_action( 'set_object_terms', 'er_german_flush' );

function er_uncounted_ids() { return er_german()['ids']; }

function er_is_german( $post_id ) {
	return has_term( 'german', 'er_language', $post_id );
}

function er_counted_total( $type ) {
	$counts = wp_count_posts( $type );
	return max( 0, (int) ( $counts->publish ?? 0 ) - ( er_german()['types'][ $type ] ?? 0 ) );
}

function er_lang_counterpart( $post_id ) {
	$pairs = er_german()['pairs'];
	if ( isset( $pairs[ $post_id ] ) ) {
		return $pairs[ $post_id ];
	}
	$en = array_search( (int) $post_id, $pairs, true );
	return false === $en ? 0 : (int) $en;
}

// Shown topic counts leave German items out, and a topic that only holds German items
// disappears where empty topics are hidden: [merged_tag_cloud], filter pills,
// [display-taxonomies]. Stored counts stay WordPress's own; admin and the editor see those.
add_filter( 'get_terms', function ( $terms, $taxonomies, $args ) {
	if ( ( is_admin() && ! wp_doing_ajax() ) || wp_is_serving_rest_request() || ! is_array( $terms ) ) {
		return $terms;
	}
	$minus = er_german()['terms'];
	if ( ! $minus ) {
		return $terms;
	}
	$dropped = false;
	foreach ( $terms as $k => $term ) {
		if ( ! $term instanceof WP_Term || empty( $minus[ $term->term_id ] ) ) {
			continue;
		}
		$term->count = max( 0, $term->count - $minus[ $term->term_id ] );
		if ( ! $term->count && ! empty( $args['hide_empty'] ) ) {
			unset( $terms[ $k ] );
			$dropped = true;
		}
	}
	return $dropped ? array_values( $terms ) : $terms;
}, 10, 3 );

// Page lists WordPress builds itself (the Page List block on Site Overview) leave German pages out too
add_filter( 'get_pages', function ( $pages ) {
	if ( ( is_admin() && ! wp_doing_ajax() ) || wp_is_serving_rest_request() || ! $pages ) {
		return $pages;
	}
	$german = er_uncounted_ids();
	return $german ? array_values( array_filter( $pages, fn( $p ) => ! in_array( (int) $p->ID, $german, true ) ) ) : $pages;
} );

// Meta box "English version" on pages: only filled in on German pages
add_action( 'add_meta_boxes_page', function () {
	add_meta_box( 'er_en_version', esc_html( er_companion_texts()['enVersion'] ), function ( $post ) {
		wp_nonce_field( 'er_en_version', 'er_en_version_nonce' );
		wp_dropdown_pages( array(
			'name'              => 'er_en_version',
			'selected'          => (int) get_post_meta( $post->ID, 'er_en_version', true ),
			'show_option_none'  => er_companion_texts()['enNone'],
			'option_none_value' => '0',
			'exclude'           => array( $post->ID ),
		) );
		echo '<p class="description">' . esc_html( er_companion_texts()['enHelp'] ) . '</p>';
	}, 'page', 'side' );
} );
add_action( 'save_post_page', function ( $post_id ) {
	if ( ! isset( $_POST['er_en_version_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['er_en_version_nonce'] ), 'er_en_version' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$en = absint( $_POST['er_en_version'] ?? 0 );
	if ( $en ) {
		update_post_meta( $post_id, 'er_en_version', $en );
	} else {
		delete_post_meta( $post_id, 'er_en_version' );
	}
} );

// ---- 1e. Language in the head: lang="de-DE" on German pages, hreflang on paired pages ----
add_filter( 'language_attributes', function ( $output ) {
	if ( is_singular() && er_is_german( get_queried_object_id() ) ) {
		$output = preg_replace( '/lang="[^"]*"/', 'lang="de-DE"', $output );
	}
	return $output;
} );
add_action( 'wp_head', function () {
	if ( ! is_page() ) {
		return;
	}
	$id    = get_queried_object_id();
	$other = er_lang_counterpart( $id );
	if ( ! $other ) {
		return;
	}
	$de = er_is_german( $id ) ? $id : $other;
	$en = $de === $id ? $other : $id;
	printf( '<link rel="alternate" hreflang="en" href="%1$s">' . "\n" . '<link rel="alternate" hreflang="de" href="%2$s">' . "\n" . '<link rel="alternate" hreflang="x-default" href="%1$s">' . "\n",
		esc_url( get_permalink( $en ) ),
		esc_url( get_permalink( $de ) )
	);
}, 2 );

// ======================================
// 2. PERFORMANCE & ASSET LOADING
// ======================================

// Switch singular featured images from lazy -> eager + fetchpriority="high".

add_filter( 'render_block', function( $block_content, $block ) {
	if ( $block['blockName'] !== 'core/post-featured-image' ) {
		return $block_content;
	}
	if ( ! is_singular() ) {
		return $block_content;
	}
	// Remove loading="lazy"
	$block_content = preg_replace( '/\s*loading=["\']lazy["\']/', '', $block_content );
	// Add fetchpriority="high" if not present
	if ( strpos( $block_content, 'fetchpriority' ) === false ) {
		$block_content = str_replace( '<img', '<img fetchpriority="high"', $block_content );
	}
	// Add loading="eager" if not present
	if ( strpos( $block_content, 'loading=' ) === false ) {
		$block_content = str_replace( '<img', '<img loading="eager"', $block_content );
	}
	return $block_content;
}, 10, 2 );

// ======================================
// 3. HEAD SCRIPTS
// ======================================

// The dark-mode FOUC guard and the per-term hero background.

// Dark mode, step 1 of 2 (before the page is painted): decide once and paint <html> dark.
// An explicit choice wins, with no choice on record we follow the OS.
// The decision is kept in data-er-dark for step 2 (wp_body_open below), so the condition exists only here.
// data-no-optimize: LiteSpeed must not defer it, it only works when it runs right away.
add_action( 'wp_head', function() {
	?>
	<script data-no-optimize="1">
(function() {
	try {
		var stored      = localStorage.getItem('changeMode');
		var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
		if (stored === 'true' || (stored === null && prefersDark)) {
			document.documentElement.dataset.erDark = '1';
			document.documentElement.style.background = '#070c12';
		}
	} catch(e) {}
})();
	</script>

	<?php
	// Term Image Meta - Inject paired with registered er_term_image_id
	// NOTE: This intentionally uses two Strategies and reflects two genuinely different Page Contexts with different DOM States
	// Valid taxonomy filter params = the taxonomies in er_taxonomy_map() (single
	// source of truth). The filter-tab URL uses the taxonomy slug as the GET param
	// (see [er_cpt_archive]: add_query_arg( $taxonomy, ... )), so the taxonomy IS
	// the param name. Deriving from the map means a new CPT added there is picked
	// up here automatically — no separate list to keep in sync.
	$er_term = null;

	// Strategy 1: GET param present (filter-tab URL on a static page)
	foreach (er_taxonomy_map() as $er_taxonomy) {
		if (!empty($_GET[$er_taxonomy])) {
			$er_t = get_term_by('slug', sanitize_key($_GET[$er_taxonomy]), $er_taxonomy);
			if ($er_t instanceof WP_Term) { $er_term = $er_t; break; }
		}
	}

	// Strategy 2: Native Taxonomy Archive (no GET param, FSE renders no background span)
	if (!$er_term && (is_tax() || is_category() || is_tag())) {
		$er_queried = get_queried_object();
		if ($er_queried instanceof WP_Term) {
			$er_term = get_term_by('slug', $er_queried->slug, $er_queried->taxonomy);
		}
	}

	if ($er_term instanceof WP_Term) {
		$er_img_id  = (int) get_term_meta($er_term->term_id, 'er_term_image_id', true);
		$er_img_url = $er_img_id ? wp_get_attachment_image_url($er_img_id, 'full') : '';
		if ($er_img_url) :
			if (is_tax() || is_category() || is_tag()) :
			// Defer term image injection to footer — inject as background span into cover structure
			add_action( 'wp_footer', function() use ( $er_img_url ) {
				?>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var hero = document.querySelector('.er-hero-section');
			if (!hero || hero.querySelector('.er-hero-bg')) return;
			var span = document.createElement('span');
			span.setAttribute('aria-hidden', 'true');
			span.className = 'has-background-dim er-hero-bg';
			span.style.backgroundImage = 'url(<?php echo esc_url($er_img_url); ?>)';
			span.style.backgroundPosition = 'center center';
			hero.insertBefore(span, hero.firstChild);
			hero.classList.add('has-background-dim');
		});
	</script>
			<?php
			}, 5 );
			?>
			<?php else : ?>
	<style>
		/* Term Image Meta — override background on the cover span */
		.er-hero-section[data-type] .er-hero-bg {background-image: url('<?php echo esc_url($er_img_url); ?>') !important;}
	</style>
			<?php endif;
		endif;
	}
	?>

<?php

}, 5 ); // Head scripts early

// Dark mode, step 2 of 2 (first thing in <body>): set body.dark-mode before any content is
// painted, so cards, text and images are dark from the first frame, not only after the footer JS.
add_action( 'wp_body_open', function() {
	?>
	<script data-no-optimize="1">if (document.documentElement.dataset.erDark) document.body.classList.add('dark-mode');</script>
	<?php
}, 1 );

// ======================================
// 4. HERO
// ======================================

// [er_hero] — self-contained hero (div.er-hero-section), styled by er-hero-*
// in the theme's style.css §11. Context-aware: taxonomy / singular / page / home / archive.

function er_hero_shortcode() {
	$output = '';

	// --------------------------------------------------------
	// Shared helper: build background span from attachment ID
	// --------------------------------------------------------
	
	// NOTE on the "has-background-dim" class below:
	// This is WordPress's core cover-block class name, reused here ONLY as a
	// flag meaning "this hero has a background image". It does NOT add any
	// overlay/tint over the image — there is no layer over the image at all.
	// The only dimming in the hero is the rgba(0,0,0,0.65) box behind the
	// title/breadcrumbs, defined on .er-hero-inner in the theme style.css
	
	$build_bg_span = function( $img_id ) {
		if ( ! $img_id ) return '';
		$full = wp_get_attachment_image_url( $img_id, 'full' );
		if ( ! $full ) return '';
		$mobile = wp_get_attachment_image_url( $img_id, 'large' );
		// Phones get 'large' (matches the preload). Term Image Meta overrides this
		// on filter pages via the more specific [data-type] selector.
		$css = ( $mobile && $mobile !== $full )
			? '<style>@media (max-width:768px){.er-hero-section .er-hero-bg{background-image:url(' . esc_url( $mobile ) . ') !important;}}</style>'
			: '';
		return $css . '<span'
			. ' aria-hidden="true"'
			. ' class="has-background-dim er-hero-bg"'
			. ' style="background-image:url(' . esc_url( $full ) . ');background-position:center center;">'
			. '</span>';
	};

	// Shared helper: build background span from post thumbnail
	$build_bg_from_post = function( $post_id ) use ( $build_bg_span ) {
		if ( ! has_post_thumbnail( $post_id ) ) return '';
		return $build_bg_span( get_post_thumbnail_id( $post_id ) );
	};

	// --------------------------------------------------------
	// CONTEXT: Taxonomy Archive (is_tax, is_category, is_tag)
	// --------------------------------------------------------
	if ( is_tax() || is_category() || is_tag() ) {
		$term        = get_queried_object();
		$title       = $term->name;
		$description = term_description();
		$term_img_id = (int) get_term_meta( $term->term_id, 'er_term_image_id', true );
		$bg_span     = $build_bg_span( $term_img_id );
		$has_image   = $bg_span ? ' has-background-dim' : ' er-hero--no-image';

		$output .= '<div class="er-hero-section' . $has_image . '" data-type="taxonomy">';
		$output .= $bg_span;
		$output .= '<div class="er-hero-inner">';
		$output .= '<header class="er-entry-header">';
		$output .= '<h1 class="page-title">' . esc_html( $title ) . '</h1>';
		if ( $description ) {
			$output .= '<div class="page-description er-hidden-sm">' . wp_kses_post( $description ) . '</div>';
		}
		$output .= er_breadcrumbs();
		$output .= '</header>';
		$output .= '</div>';
		$output .= '</div>';

	// --------------------------------------------------------
	// CONTEXT: Singular Post or CPT (not Page)
	// --------------------------------------------------------
	} elseif ( is_singular() && ! is_page() ) {
		$post_id  = get_the_ID();
		$title    = get_the_title();
		$bg_span  = $build_bg_from_post( $post_id );
		$has_image = $bg_span ? ' has-background-dim' : ' er-hero--no-image';

		$output .= '<div class="er-hero-section' . $has_image . '" data-type="singular">';
		$output .= $bg_span;
		$output .= '<div class="er-hero-inner">';
		$output .= '<header class="er-entry-header">';
		$output .= '<h1 class="page-title">' . esc_html( $title ) . '</h1>';
		if ( has_excerpt( $post_id ) ) {
			$output .= '<div class="page-description er-hidden-sm">' . wp_kses_post( get_the_excerpt( $post_id ) ) . '</div>';
		}
		$output .= er_breadcrumbs();
		$output .= '</header>';
		$output .= '</div>';
		$output .= '</div>';

	// --------------------------------------------------------
	// CONTEXT: Static Page
	// --------------------------------------------------------
	} elseif ( is_page() ) {
		$post_id   = get_the_ID();
		$title     = get_the_title();
		$bg_span   = $build_bg_from_post( $post_id );
		$has_image = $bg_span ? ' has-background-dim' : ' er-hero--no-image';

		$output .= '<div class="er-hero-section' . $has_image . '" data-type="page">';
		$output .= $bg_span;
		$output .= '<div class="er-hero-inner">';
		$output .= '<header class="er-entry-header">';
		$output .= '<h1 class="page-title">' . esc_html( $title ) . '</h1>';
		if ( has_excerpt( $post_id ) ) {
			$output .= '<div class="page-description er-hidden-sm">' . wp_kses_post( get_the_excerpt( $post_id ) ) . '</div>';
		}
		$output .= er_breadcrumbs();
		$output .= '</header>';
		$output .= '</div>';
		$output .= '</div>';

	// --------------------------------------------------------
	// CONTEXT: Blog Posts Index (is_home — assigned Posts page)
	// --------------------------------------------------------
	} elseif ( is_home() ) {
		// Blog posts index (the Page assigned under Settings → Reading → Posts page)
		$post_id   = (int) get_option( 'page_for_posts' );
		$title     = $post_id ? get_the_title( $post_id ) : get_bloginfo( 'name' );
		$bg_span   = $build_bg_from_post( $post_id );
		$has_image = $bg_span ? ' has-background-dim' : ' er-hero--no-image';

		$output .= '<div class="er-hero-section' . $has_image . '" data-type="home">';
		$output .= $bg_span;
		$output .= '<div class="er-hero-inner">';
		$output .= '<header class="er-entry-header">';
		$output .= '<h1 class="page-title">' . esc_html( $title ) . '</h1>';
		if ( $post_id && has_excerpt( $post_id ) ) {
			$output .= '<div class="page-description er-hidden-sm">' . wp_kses_post( get_the_excerpt( $post_id ) ) . '</div>';
		}
		$output .= er_breadcrumbs();
		$output .= '</header>';
		$output .= '</div>';
		$output .= '</div>';
	}
	return $output;
}
add_shortcode( 'er_hero', 'er_hero_shortcode' );

// ======================================
// 5. CPT ARCHIVE + FILTER
// ======================================

// [er_cpt_archive] renders the filtered grid with infinite scroll.
// er_render_card() is shared with the AJAX load-more handler.

// ---- 5a. Shared card renderer (used by the shortcode AND the AJAX handler) ----
function er_render_card( $post_id, $taxonomy ) {
	$url        = get_permalink( $post_id );
	$title      = get_the_title( $post_id );
	$thumb      = get_the_post_thumbnail( $post_id, 'medium_large', array(
		'loading'  => 'lazy',
		'decoding' => 'async',
		'style'    => 'aspect-ratio: 16/9;',
	) );
	$post_terms = get_the_terms( $post_id, $taxonomy );
	$term_obj   = ( $post_terms && ! is_wp_error( $post_terms ) ) ? $post_terms[0] : null;

	echo '<article class="' . esc_attr( implode( ' ', get_post_class( 'entry-card card-content is-clickable-card', $post_id ) ) ) . '">';
	echo '<h2 class="entry-title"><a href="' . esc_url( $url ) . '" rel="bookmark">' . esc_html( $title ) . '</a></h2>';

	if ( $thumb ) {
		echo '<a class="er-media-container" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $title ) . '">'
		   . $thumb
		   . '</a>';
	}

	echo '<ul class="entry-meta" data-type="icons:slash" data-id="eQBHSW">';
	er_reading_time( $post_id, true );

	if ( $term_obj ) {
		echo '<li class="meta-categories" data-type="simple">'
		   . '<svg width="13" height="13" viewBox="0 0 15 15"><path d="M14.4,1.2H0.6C0.3,1.2,0,1.5,0,1.9V5c0,0.3,0.3,0.6,0.6,0.6h0.6v7.5c0,0.3,0.3,0.6,0.6,0.6h11.2c0.3,0,0.6-0.3,0.6-0.6V5.6h0.6C14.7,5.6,15,5.3,15,5V1.9C15,1.5,14.7,1.2,14.4,1.2z M12.5,12.5h-10V5.6h10V12.5z M13.8,4.4H1.2V2.5h12.5V4.4z M5.6,7.5c0-0.3,0.3-0.6,0.6-0.6h2.5c0.3,0,0.6,0.3,0.6,0.6S9.1,8.1,8.8,8.1H6.2C5.9,8.1,5.6,7.8,5.6,7.5z"></path></svg>'
		   . '<a href="' . esc_url( get_term_link( $term_obj ) ) . '" rel="tag" class="er-term-' . esc_attr( $term_obj->term_id ) . '">'
		   . esc_html( $term_obj->name )
		   . '</a>'
		   . '</li>';
	}

	echo '</ul>';
	echo '</article>';
}

// ---- 5b. [er_cpt_archive] — filtered grid + filter pills + infinite-scroll sentinel ----
add_shortcode( 'er_cpt_archive', function( $atts ) {

	$taxonomy_map = er_taxonomy_map();

	// Context detection:
	// Term archive (/topics/x/, /category/y/ ...): post_type, taxonomy and term are all
	// derived from the URL; the grid is locked to that single term.
	// Listing page (My Blog and CPTs): attribute-driven with ?taxonomy tabs.
	$queried         = get_queried_object();
	$is_term_archive = ( $queried instanceof WP_Term ) && in_array( $queried->taxonomy, $taxonomy_map, true );
	$has_explicit_pt = is_array( $atts ) && ! empty( $atts['post_type'] );

	if ( $is_term_archive ) {
		$taxonomy  = $queried->taxonomy;
		$post_type = array_search( $taxonomy, $taxonomy_map, true );
		$atts      = shortcode_atts( array(
			'limit'   => ER_CARDS_PER_LOAD,
			'orderby' => 'date',
			'order'   => 'DESC',
		), $atts, 'er_cpt_archive' );
		$show_tabs   = true; // Term archives show the full taxonomy pill row (current term marked active)
		$active_slug = $queried->slug; // grid is locked to this term (query + infinite scroll)

	} elseif ( $has_explicit_pt || ! is_archive() ) {
		// Default Values as Fallback if no Attributes defined in Shortcode
		$atts = shortcode_atts( array(
			'post_type' => 'my-interests',
			'limit'     => -1,
			'orderby'   => 'rand',
			'order'     => 'DESC',
			'filtering' => 'yes',
		), $atts, 'er_cpt_archive' );

		$post_type = sanitize_key( $atts['post_type'] );

		if ( ! isset( $taxonomy_map[ $post_type ] ) ) {
			return '<p>Invalid post_type.</p>';
		}

		$taxonomy    = $taxonomy_map[ $post_type ];
		$show_tabs   = ( $atts['filtering'] !== 'no' );
		$active_slug = isset( $_GET[ $taxonomy ] ) ? sanitize_key( $_GET[ $taxonomy ] ) : '';

	} elseif ( is_post_type_archive( array_keys( $taxonomy_map ) ) ) {
		// A type's own archive (/my-interests/, /my-quotes/, /my-traits/): all its items with the
		// filter pills, like its overview page - so the address works with or without a redirect.
		$post_type = get_queried_object()->name;
		$taxonomy  = $taxonomy_map[ $post_type ];
		$atts      = shortcode_atts( array(
			'limit'   => ER_CARDS_PER_LOAD,
			'orderby' => 'date',
			'order'   => 'DESC',
		), $atts, 'er_cpt_archive' );
		$show_tabs   = true;
		$active_slug = isset( $_GET[ $taxonomy ] ) ? sanitize_key( $_GET[ $taxonomy ] ) : '';

	} else {
		// Archive context we deliberately do not render here (tags, dates, authors, unmapped taxonomies)
		return '';
	}

	$base_url = esc_url_raw( strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) );

	// Filter Tabs
	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => true,
	) );

	ob_start();

	if ( ! empty( $terms ) && ! is_wp_error( $terms ) && $show_tabs ) {
		$total      = er_counted_total( $post_type ); // German items do not count (1d)
		$all_active = $active_slug === '' ? ' er-active' : '';

		// Pill URLs differ by mode:
		// - Listing page: ?taxonomy=slug query args on the current page (original behaviour).
		// - Term archive: Clean term-archive permalinks; the ALL pill points back to the
		//   listing page (the assigned Posts page for 'post', the post type archive for CPTs).
		if ( $is_term_archive ) {
			if ( $post_type === 'post' ) {
				$posts_page_id = (int) get_option( 'page_for_posts' );
				$all_url       = $posts_page_id ? get_permalink( $posts_page_id ) : home_url( '/' );
			} elseif ( $post_type === 'page' ) {
				$all_url = er_things_hub_url();
			} else {
				// This line rarely runs. Visiting /my-interests/ (and /my-traits/, /my-quotes/) redirects to real Page instead
				// See redirect list in Site Essentials & Cookies PHP. This is just a backup in case that ever changes.
				$archive_link = get_post_type_archive_link( $post_type );
				$all_url      = $archive_link ? $archive_link : home_url( '/' );
			}
		} else {
			$all_url = $base_url;
		}

		echo '<div class="er-archive-filter">';
		printf(
			'<a href="%s" class="er-filter-tab%s">All <span>(%d)</span></a>',
			esc_url( $all_url ),
			esc_attr( $all_active ),
			intval( $total )
		);
		foreach ( $terms as $term ) {
			$active = ( $active_slug === $term->slug ) ? ' er-active' : '';
			if ( $is_term_archive ) {
				$term_link = get_term_link( $term );
				$url       = is_wp_error( $term_link ) ? $base_url : $term_link;
			} else {
				$url = add_query_arg( $taxonomy, $term->slug, $base_url );
			}
			printf(
				'<a href="%s" class="er-filter-tab%s">%s <span>(%d)</span></a>',
				esc_url( $url ),
				esc_attr( $active ),
				esc_html( $term->name ), /* natural case, matching the tag-cloud pills */
				intval( $term->count )
			);
		}
		echo '</div>';
	}

	// Query Args
	$query_args = array(
		'post_type'      => $post_type,
		'posts_per_page' => intval( $atts['limit'] ),
		'post_status'    => 'publish',
		'orderby'        => in_array( sanitize_key( $atts['orderby'] ), [ 'date', 'title', 'rand', 'menu_order' ], true )
					       ? sanitize_key( $atts['orderby'] )
					       : 'rand',
		'order'          => in_array( strtoupper( $atts['order'] ), [ 'ASC', 'DESC' ] ) ? strtoupper( $atts['order'] ) : 'DESC',
	);
	
	// German items never in a listing (1d); pages also without My Pages itself (hub setting, 1a) — must match 5c
	$query_args['post__not_in'] = $post_type === 'page' ? array_merge( er_uncounted_ids(), array( er_things_hub_id() ) ) : er_uncounted_ids();
	
	if ( $active_slug !== '' ) {
		$term = get_term_by( 'slug', $active_slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			);
		}
	}

	// Post Grid
	$loop = new WP_Query( $query_args );

	if ( ! $loop->have_posts() ) {
		echo '<p>' . esc_html( er_companion_texts()['noEntries'] ) . '</p>';
		return ob_get_clean();
	}

	$initial_count = $loop->post_count;

	echo '<div class="er-cpt-grid" data-prefix="blog" data-archive="default"'
		. ' data-post-type="' . esc_attr( $post_type ) . '"'
		. ' data-taxonomy="' . esc_attr( $taxonomy ) . '"'
		. ' data-limit="' . esc_attr( $atts['limit'] ) . '"'
		. ' data-orderby="' . esc_attr( $atts['orderby'] ) . '"'
		. ' data-order="' . esc_attr( $atts['order'] ) . '"'
		. ' data-offset="' . esc_attr( $initial_count ) . '"'
		. ' data-active-slug="' . esc_attr( $active_slug ) . '"'
		. '>';

	while ( $loop->have_posts() ) {
		$loop->the_post();
		er_render_card( get_the_ID(), $taxonomy );
	}

	echo '</div>';

	// Only render Sentinel when there are more Posts to fetch
	if ( intval( $atts['limit'] ) !== -1 && $loop->found_posts > $initial_count ) {
		echo '<div class="er-load-more-sentinel"></div>';
	}

	wp_reset_postdata();
	
	// Other terms of the same taxonomy below the grid: category, topics, things, groups and types,
	// on the term archive (/topics/x/) and on the filtered listing page (?topics=x).
	// Placed after the sentinel: the infinite-scroll JS expects the sentinel as the grid's next sibling.
	$er_current_term = $is_term_archive
		? $queried
		: ( $active_slug !== '' ? get_term_by( 'slug', $active_slug, $taxonomy ) : false );
	if ( $er_current_term instanceof WP_Term ) {
		$er_other_terms = er_companion_links()['overviews'];
		if ( isset( $er_other_terms[ $taxonomy ] ) ) {
			// IDs of all terms except the current one → passed as include_id
			$er_other_ids = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'fields'     => 'ids',
				'exclude'    => array( $er_current_term->term_id ),
			) );
			if ( ! empty( $er_other_ids ) && ! is_wp_error( $er_other_ids ) ) {
				list( $er_anchor, $er_label, $er_url ) = $er_other_terms[ $taxonomy ];
				printf(
					'<h3 id="%s" class="wp-block-heading" style="margin-top: var(--wp--preset--spacing--medium)"><a href="%s">%s</a></h3>',
					esc_attr( $er_anchor ),
					esc_url( $er_url ),
					esc_html( $er_label )
				);
				echo '<div style="margin-bottom: var(--wp--preset--spacing--medium)">';
				// As Related: fewer than ER_SLIDER_FROM → a static row (a slider would only repeat them), more → the slider
				$er_layout = count( $er_other_ids ) >= ER_SLIDER_FROM ? 'slideshow-multiple-items-4' : 'is-layout-grid';
				echo do_shortcode( '[display-taxonomies taxonomy="' . $taxonomy . '" include_id="' . implode( ',', $er_other_ids ) . '" show_count="true" order="ASC" orderby="title" image_size="large" wrapper="div" wrapper_class="display-posts-listing ' . $er_layout . ' daneden-slideInUp"]' );
				echo '</div>';
			}
		}
	}
	
	return ob_get_clean();
} );

// ---- 5c. Infinite-scroll AJAX handler (load-more) ----
add_action( 'wp_ajax_er_load_more', 'er_load_more_handler' );
add_action( 'wp_ajax_nopriv_er_load_more', 'er_load_more_handler' );

function er_load_more_handler() {
	// Read-only (published posts only, every input validated below), so no nonce:
	// a nonce baked into a page cached for days expires and would break loading silently.

	// Search mode: mixed post types, no taxonomy/term filter.
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	if ( $search !== '' ) {
		$limit  = max( 1, min( 48, absint( $_POST['limit'] ?? ER_CARDS_PER_LOAD ) ) );
		$offset = absint( $_POST['offset'] ?? 0 );

		$loop = new WP_Query( array(
			's'              => $search,
			'post_type'      => get_post_types( array( 'exclude_from_search' => false ), 'names' ),
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'offset'         => $offset,
		) );

		if ( ! $loop->have_posts() ) {
			wp_send_json_success( array( 'html' => '', 'has_more' => false ) );
			wp_die();
		}

		ob_start();
		while ( $loop->have_posts() ) {
			$loop->the_post();
			er_render_card( get_the_ID(), er_taxonomy_for_post( get_the_ID() ) );
		}
		wp_reset_postdata();

		wp_send_json_success( array(
			'html'     => ob_get_clean(),
			'has_more' => $loop->found_posts > ( $offset + $limit ),
		) );
		wp_die();
	}

	$post_type       = sanitize_key( wp_unslash( $_POST['post_type'] ?? '' ) );
	$limit           = max( 1, min( 48, absint( $_POST['limit'] ?? ER_CARDS_PER_LOAD ) ) );
	$allowed_orderby = [ 'date', 'title', 'rand', 'menu_order' ];
	$orderby         = sanitize_key( wp_unslash( $_POST['orderby'] ?? '' ) );
	$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'date';
	$order           = strtoupper( sanitize_key( wp_unslash( $_POST['order'] ?? '' ) ) ) === 'ASC' ? 'ASC' : 'DESC';
	$offset          = absint( $_POST['offset'] ?? 0 );
	$active_slug     = sanitize_key( wp_unslash( $_POST['active_slug'] ?? '' ) );

	$taxonomy_map = er_taxonomy_map();

	if ( ! isset( $taxonomy_map[ $post_type ] ) ) {
		wp_send_json_error( 'Invalid post_type.' );
		wp_die();
	}

	$taxonomy = $taxonomy_map[ $post_type ]; // derived from validated post_type — $_POST['taxonomy'] ignored

	$query_args = array(
		'post_type'      => $post_type,
		'posts_per_page' => $limit,
		'post_status'    => 'publish',
		'orderby'        => $orderby,
		'order'          => $order,
		'offset'         => $offset,
	);

	// German items never in a listing (1d); pages also without My Pages itself (hub setting, 1a) — must match 5b
	$query_args['post__not_in'] = $post_type === 'page' ? array_merge( er_uncounted_ids(), array( er_things_hub_id() ) ) : er_uncounted_ids();

	if ( $active_slug !== '' ) {
		$term = get_term_by( 'slug', $active_slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			);
		}
	}

	$loop = new WP_Query( $query_args );

	if ( ! $loop->have_posts() ) {
		wp_send_json_success( [ 'html' => '', 'has_more' => false ] );
		wp_die();
	}

	ob_start();

	while ( $loop->have_posts() ) {
		$loop->the_post();
		er_render_card( get_the_ID(), $taxonomy );
	}

	wp_reset_postdata();

	$html     = ob_get_clean();
	$has_more = $loop->found_posts > ( $offset + $limit );

	wp_send_json_success( [ 'html' => $html, 'has_more' => $has_more ] );
}

// ======================================
// 6. RELATED POSTS
// ======================================

// [er_related_posts] — auto-detects post_type, matches by taxonomy (CPTs) or
// category (posts), excludes current post, renders 12 via Eric Slider

add_shortcode( 'er_related_posts', function() {
	global $post;
	if ( ! $post ) return '';

	$post_type    = get_post_type( $post->ID );
	$taxonomy_map = er_taxonomy_map();

	// Resolve the relating taxonomy up front so it is ALWAYS defined:
	// a CPT's custom taxonomy, or 'category' for a standard post.
	// er_render_card() requires this as its second argument.
	$taxonomy = isset( $taxonomy_map[ $post_type ] ) ? $taxonomy_map[ $post_type ] : 'category';

	$query_args = array(
		'post_type'      => $post_type,
		'posts_per_page' => ER_RELATED_MAX,
		'post_status'    => 'publish',
		'orderby'        => 'rand',
		'post__not_in'   => array_merge( array( $post->ID ), er_uncounted_ids() ), // German items are never related (1d)
	);

	if ( isset( $taxonomy_map[ $post_type ] ) ) {
		// CPT — match by its custom taxonomy
		$terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$query_args['tax_query'] = array( array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $terms,
			) );
		}
	} else {
		// Standard post — match by category
		$categories = wp_get_post_categories( $post->ID );
		if ( ! empty( $categories ) ) {
			$query_args['category__in'] = $categories;
		}
	}

	$related = new WP_Query( $query_args );
		if ( ! $related->have_posts() ) return '';
	
		// Count what we actually got back so we can pick the layout.
		$count = $related->post_count;
		// Fewer than ER_SLIDER_FROM → static grid (no slider, no clones, no duplicates); from there on the slider.
		$is_slider = ( $count >= ER_SLIDER_FROM );
		$layout_class = $is_slider
			? 'slideshow-multiple-items-4 is-layout-slider daneden-slideInUp'
			: 'is-layout-grid is-count-' . $count . ' daneden-slideInUp';
	
		ob_start();
		echo '<div class="er-related-posts-container">';
		echo '<h3 class="er-related-title">Related</h3>';
		echo '<div class="er-related-posts ' . esc_attr( $layout_class ) . '">';
	
		while ( $related->have_posts() ) {
			$related->the_post();
			er_render_card( get_the_ID(), $taxonomy );
		}
		wp_reset_postdata();
	
		echo '</div>';
		echo '</div>';
	
		return ob_get_clean();
} );

// ======================================
// 7. POST FOOTER  (blog + CPT)
// ======================================

// [er_post_footer] — share row + prev/next navigation, single posts only.
// CSS lives in style.css; the share-link opener JS is in 7d.

// ---- 7a. [er_post_footer] — share row + prev/next ----
add_shortcode( 'er_post_footer', function () {

	global $post;
	if ( ! $post || ! is_singular() ) {
		return '';
	}

	// Share Targets: Label => [ href, icon-key ]
	$url   = get_permalink( $post );
	$title = get_the_title( $post );
	$u     = rawurlencode( $url );
	$t     = rawurlencode( $title );

	$networks = array();
	foreach ( er_companion_links()['share'] as $label => $net ) { // SETTINGS: {url} / {title} filled in here
		$networks[ $label ] = array( strtr( $net[0], array( '{url}' => $u, '{title}' => $t ) ), $net[1] );
	}

	$icons = er_post_footer_icons();

	// Prev / Next Resolution
	$post_type    = get_post_type( $post );
	$taxonomy_map = function_exists( 'er_taxonomy_map' ) ? er_taxonomy_map() : array();
	$taxonomy     = isset( $taxonomy_map[ $post_type ] ) ? $taxonomy_map[ $post_type ] : 'category';

	$prev = get_previous_post( true, '', $taxonomy ); // true = within same term
	$next = get_next_post( true, '', $taxonomy );

	ob_start();
	echo er_post_footer_js(); // Prints share-opener JS once per Page (CSS is in style.css)
	?>
	<div class="er-post-footer">

		<div class="er-pf-net">
			<span class="er-pf-net-label"><?php echo esc_html( er_companion_texts()['shareThis'] ); ?></span>
			<div class="er-pf-net-links">
				<?php
				foreach ( $networks as $label => $data ) :
					list( $href, $key ) = $data;
					if ( empty( $icons[ $key ] ) ) {
						continue;
					}
					?>
					<a class="er-pf-net-link"
					   href="#"
					   data-share="<?php echo esc_url( $href ); ?>"
					   role="button"
					   aria-label="Share on <?php echo esc_attr( $label ); ?>"
					   title="<?php echo esc_attr( $label ); ?>"><?php
						echo $icons[ $key ]; // inline SVG, trusted literal
					?></a>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( $prev || $next ) : ?>
		<nav class="er-postnav" aria-label="<?php echo esc_attr( er_companion_texts()['postNav'] ); ?>">

			<div class="er-postnav-side er-postnav-prev">
				<?php if ( $prev ) :
					$prev_url = get_permalink( $prev ); ?>
					<div class="er-postnav-card">
						<?php echo er_post_footer_thumb( $prev, $prev_url ); ?>
						<span class="er-postnav-text">
							<span class="er-postnav-tag">Previous Post</span>
							<a class="er-postnav-title" href="<?php echo esc_url( $prev_url ); ?>"><?php echo esc_html( get_the_title( $prev ) ); ?></a>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<div class="er-postnav-side er-postnav-next">
				<?php if ( $next ) :
					$next_url = get_permalink( $next ); ?>
					<div class="er-postnav-card">
						<span class="er-postnav-text">
							<span class="er-postnav-tag">Next Post</span>
							<a class="er-postnav-title" href="<?php echo esc_url( $next_url ); ?>"><?php echo esc_html( get_the_title( $next ) ); ?></a>
						</span>
						<?php echo er_post_footer_thumb( $next, $next_url ); ?>
					</div>
				<?php endif; ?>
			</div>

		</nav>
		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
} );

// ---- 7b. Helper: round neighbour thumbnail (empty if no featured image) ----
function er_post_footer_thumb( $p, $url ) {
	if ( ! has_post_thumbnail( $p ) ) {
		return '';
	}
	return '<a class="er-postnav-thumb" href="' . esc_url( $url ) . '">'
		. get_the_post_thumbnail( $p, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) )
		. '</a>';
}

// ---- 7c. Helper: inline-SVG icon library (Simple Icons CC0 paths) ----
function er_post_footer_icons() {

	$wrap = function ( $path ) {
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>';
	};

	return array(
		'bluesky'  => $wrap( 'M5.202 2.857C7.954 4.922 10.913 9.11 12 11.358c1.087-2.247 4.046-6.436 6.798-8.501C20.783 1.366 24 .213 24 3.883c0 .732-.42 6.156-.667 7.037-.856 3.061-3.978 3.842-6.755 3.37 4.854.826 6.089 3.562 3.422 6.299-5.065 5.196-7.28-1.304-7.847-2.97-.104-.305-.152-.448-.153-.327 0-.121-.05.022-.153.327-.568 1.666-2.782 8.166-7.847 2.97-2.667-2.737-1.432-5.473 3.422-6.3-2.777.473-5.899-.308-6.755-3.369C.42 10.04 0 4.615 0 3.883c0-3.67 3.217-2.517 5.202-1.026' ),
		'facebook' => $wrap( 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z' ),
		'line'     => $wrap( 'M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314' ),
		'linkedin' => $wrap( 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z' ),
		'telegram' => $wrap( 'M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z' ),
		'threads'  => $wrap( 'M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.29 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.783 3.631 2.698 6.54 2.717 2.623-.02 4.358-.631 5.8-2.045 1.647-1.613 1.618-3.593 1.09-4.798-.31-.71-.873-1.3-1.634-1.75-.192 1.352-.622 2.446-1.284 3.272-.886 1.102-2.14 1.704-3.73 1.79-1.202.065-2.361-.218-3.259-.801-1.063-.689-1.685-1.74-1.752-2.964-.065-1.19.408-2.285 1.33-3.082.88-.76 2.119-1.207 3.583-1.291a13.853 13.853 0 0 1 3.02.142c-.126-.742-.375-1.332-.75-1.757-.513-.586-1.308-.883-2.359-.89h-.029c-.844 0-1.992.232-2.721 1.32L7.734 7.847c.98-1.454 2.568-2.256 4.478-2.256h.044c3.194.02 5.097 1.975 5.287 5.388.108.046.216.094.321.142 1.49.7 2.58 1.761 3.154 3.07.797 1.82.871 4.79-1.548 7.158-1.85 1.81-4.094 2.628-7.277 2.65Zm1.003-11.69c-.242 0-.487.007-.739.021-1.836.103-2.98.946-2.916 2.143.067 1.256 1.452 1.839 2.784 1.767 1.224-.065 2.818-.543 3.086-3.71a10.5 10.5 0 0 0-2.215-.221z' ),
		'whatsapp' => $wrap( 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z' ),
		'email'    => $wrap( 'M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z' ),
	);
}

// ---- 7d. Helper: share-link opener JS (printed once via static guard) ----
function er_post_footer_js() {

	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;

	ob_start();
	?>
	<script>
	( function () {
		if ( window.erPostFooterShareBound ) { return; }
		window.erPostFooterShareBound = true;
		document.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( '.er-pf-net-link' );
			if ( ! link ) { return; }
			e.preventDefault();
			var url = link.getAttribute( 'data-share' );
			if ( ! url ) { return; }
			if ( url.indexOf( 'mailto:' ) === 0 ) {
				window.location.href = url;
			} else {
				window.open( url, '_blank', 'noopener' );
			}
		} );
	} )();
	</script>
	<?php
	return ob_get_clean();
}

// ======================================
// 8. HEADER / NAV
// ======================================

// Header-scroll JS and the mobile-overlay Socials/Support injection.
// Bar and submenu CSS (incl. the submenu position) are in style.css §10.

// ---- 8a. Header scroll (own wp_footer closure) ----
// Adds .er-scrolled past 50px; the bar height and the submenu position follow from it in CSS.
add_action( 'wp_footer', function() {
	?>
	<script>
		document.addEventListener("DOMContentLoaded", function() {
			var header = document.querySelector(".er-header");
			if (!header) return;
			var wasShrunk = false;
			function update() {
				var shrunk = window.scrollY > 50;
				if (shrunk === wasShrunk) return;
				wasShrunk = shrunk;
				header.classList.toggle("er-scrolled", shrunk);
			}
			window.addEventListener("scroll", update, { passive: true });
			update(); // a page can load already scrolled (anchor link, back-navigation)
		});
	</script>
	<?php
}, 15 );

// ---- 8b. Mobile-overlay Socials/Support pair (injected into the nav overlay) ----
// Injected inside the nav's responsive-container-content, after the menu <ul> closes.
// Markup comes from [er_socials_support context="overlay"] — single source (see 8c).
add_filter( 'render_block', function ( $block_content, $block ) {
	if ( ( $block['blockName'] ?? '' ) !== 'core/navigation' ) {
		return $block_content;
	}
	$pair = do_shortcode( '[er_socials_support context="overlay"]' );
	// The responsive-container-content holds the menu <ul>. Inject right after
	// that <ul> closes, before the container's own closing </div>.
	// Anchor: the content container has id="...-content". We insert before the
	// FIRST </div> that follows it.
	$marker = '__responsive-container-content"';
	$start  = strpos( $block_content, $marker );
	if ( $start === false ) {
		return $block_content;
	}
	// Find the closing </ul> of the menu after that container opens.
	$ul_close = strrpos( $block_content, '</ul>' );
	if ( $ul_close === false ) {
		return $block_content;
	}
	$insert_at = $ul_close + strlen( '</ul>' );
	return substr_replace( $block_content, $pair, $insert_at, 0 );
}, 10, 2 );

// ---- 8c. Socials + Support icons — [er_socials_support]; single source for header, footer, mobile overlay ----
// URLs, icon files and labels: SETTINGS at the top; two skins differ only in wrapper/classes/icon size:
//   context="bar"     (default) → desktop header + footer  (.er-socials-support / .er-header-icon, 22px)
//   context="overlay"           → mobile hamburger overlay (.er-nav-overlay-extra, 20px)
add_shortcode( 'er_socials_support', function ( $atts ) {
	$atts = shortcode_atts( array( 'context' => 'bar' ), $atts, 'er_socials_support' );

	// Links, icons and labels: SETTINGS (er_companion_links, er_companion_texts)
	$l = er_companion_links();
	$t = er_companion_texts();
	$socials_url  = $l['socialsUrl'];
	$socials_icon = $l['socialsIcon'];
	$support_url  = $l['supportUrl'];
	$support_icon = $l['supportIcon'];

	if ( $atts['context'] === 'overlay' ) {
		// Mobile overlay skin: .er-nav-overlay-extra, plain anchors, 20px icons.
		return '<div class="er-nav-overlay-extra">'
		     . '<a href="' . esc_url( $socials_url ) . '"><img src="' . esc_url( $socials_icon ) . '" alt="" width="20" height="20"><span>' . esc_html( $t['socials'] ) . '</span></a>'
		     . '<a href="' . esc_url( $support_url ) . '"><img src="' . esc_url( $support_icon ) . '" alt="" width="20" height="20"><span>' . esc_html( $t['support'] ) . '</span></a>'
		     . '</div>';
	}

	// Default bar skin: .er-socials-support, .er-header-icon anchors, 22px icons.
	return '<span class="er-socials-support">'
	     . '<a class="er-header-icon" href="' . esc_url( $socials_url ) . '"><img src="' . esc_url( $socials_icon ) . '" alt="" width="22" height="22"><span class="er-header-label">' . esc_html( $t['socials'] ) . '</span></a>'
	     . '<a class="er-header-icon" href="' . esc_url( $support_url ) . '"><img src="' . esc_url( $support_icon ) . '" alt="" width="22" height="22"><span class="er-header-label">' . esc_html( $t['support'] ) . '</span></a>'
	     . '</span>';
} );

// ======================================
// 9. FOOTER SCRIPTS  (JS)
// ======================================

// Deferred front-end JS: dark-mode toggle, card-reveal animation, infinite
// scroll, and <details>-open persistence across filter clicks.
// All CSS lives in style.css / the CSS snippet — none here.

add_action( 'wp_footer', function() {
	?>
	<script>
	(function () { // own scope: erLoadMore, getColumns, revealCards, grids stay off the global namespace

		// ---- 9a. Dark-mode toggle: apply stored preference, sync the switch UI ----
		document.addEventListener('DOMContentLoaded', function() {
			var body             = document.body;
			var changeModeSwitch = document.getElementById('change-mode-switch');
			var visualToggle     = document.querySelector('#dark-mode-toggle-btn .toggle-visual');
			var statusEl         = document.getElementById('dark-mode-status');

			// body.dark-mode is already set at the start of <body> (see 3. HEAD SCRIPTS).
			// Drop the wp_head guard's dark <html> background: body.dark-mode paints the dark page
			// from here on, and a later switch to light must not leave it behind.
			document.documentElement.style.background = '';

			// Sync toggle with current state
			var isDark = body.classList.contains('dark-mode');
			if (changeModeSwitch) { changeModeSwitch.checked = isDark; changeModeSwitch.setAttribute('aria-checked', isDark); }

			// Toggle function
			var changeMode = function() {
				var isDark = body.classList.toggle('dark-mode');
				if (changeModeSwitch) { changeModeSwitch.checked = isDark; changeModeSwitch.setAttribute('aria-checked', isDark); }
				if (statusEl) { statusEl.textContent = isDark ? <?php echo wp_json_encode( er_companion_texts()['darkOn'] ); ?> : <?php echo wp_json_encode( er_companion_texts()['darkOff'] ); ?>; }
				try { localStorage.setItem('changeMode', isDark ? 'true' : 'false'); } catch(e) { console.warn('LocalStorage unavailable'); }
			};

			// Accessibility setup
			var addAccessibility = function(el) {
				if (!el) return;
				el.setAttribute('role', 'switch');
				el.setAttribute('aria-checked', body.classList.contains('dark-mode'));
				el.setAttribute('tabindex', '0');
				el.addEventListener('keydown', function(e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); changeMode(); } });
			};
			addAccessibility(changeModeSwitch);
			if (visualToggle) { visualToggle.setAttribute('aria-hidden', 'true'); }

			// Event listeners
			if (changeModeSwitch) { changeModeSwitch.addEventListener('change', changeMode); }
			if (visualToggle) {
				visualToggle.addEventListener('click', function() {
					if (changeModeSwitch) { changeModeSwitch.checked = !changeModeSwitch.checked; }
					changeMode();
				});
			}
		});

		// ---- 9b. Infinite scroll: ajax config + card-reveal helpers ----
		// Infinite Scroll - erLoadMore Object injected here for use by Observer below.
		// No nonce: pages are cached for days and a baked-in nonce expires after 12–24 h (see doc 2.3).
		const erLoadMore = {
			ajaxUrl: '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>'
		};

		// Card Reveal Animation - Helpers defined outside DOMContentLoaded so revealCards() is accessible from the Infinite Scroll Handler below
		const getColumns = function() {
			if ( window.innerWidth < 600 )  return 2;
			if ( window.innerWidth < 1024 ) return 3;
			return 4;
		};

		const revealCards = function( cards ) {
			cards.forEach( function( card ) {
				card.style.opacity   = '0';
				card.style.transform = 'translateY(150px)';
			} );
			const revealObserver = new IntersectionObserver( function( entries ) {
				entries.forEach( function( entry ) {
					if ( ! entry.isIntersecting ) return;
					const allCards = Array.from( document.querySelectorAll( '.er-cpt-grid article' ) );
					const index    = allCards.indexOf( entry.target );
					const delay    = ( index % getColumns() ) * 250;
					entry.target.style.transition = 'opacity 1.5s cubic-bezier(0.2, 1, 0.2, 1) ' + delay + 'ms, transform 1.5s cubic-bezier(0.2, 1, 0.2, 1) ' + delay + 'ms';
					entry.target.style.opacity    = '1';
					entry.target.style.transform  = 'translateY(0)';
					revealObserver.unobserve( entry.target );
				} );
			}, { threshold: 0.1 } );
			cards.forEach( function( card ) { revealObserver.observe( card ); } );
		};

		// ---- 9c. On load: reveal initial cards + slider entrance animation ----
		document.addEventListener( 'DOMContentLoaded', function() {

			// Card Reveal Animation: Mark + animate initial server-rendered Cards
			const initialCards = Array.from( document.querySelectorAll( '.er-cpt-grid article' ) );
			if ( initialCards.length ) {
				initialCards.forEach( function( card ) { card.setAttribute( 'data-revealed', '1' ); } );
				revealCards( initialCards );
			}

		} );

		// ---- 9d. Infinite scroll: observer that fetches & appends more cards ----
		// Infinite Scroll
		const grids = document.querySelectorAll( '.er-cpt-grid' );

		grids.forEach( function( grid ) {
			// Each grid emits its sentinel as the immediately-following sibling.
			const sentinel = grid.nextElementSibling;
			if ( ! sentinel || ! sentinel.classList.contains( 'er-load-more-sentinel' ) ) return;

			// Per-grid state — kept in this closure so multiple grids on one
			// page don't share loading/offset/hasMore.
			let loading  = false;
			let hasMore  = true;
			let offset   = parseInt( grid.dataset.offset );

			const postType  = grid.dataset.postType;
			const taxonomy  = grid.dataset.taxonomy;
			const limit     = parseInt( grid.dataset.limit );
			const orderby   = grid.dataset.orderby;
			const order     = grid.dataset.order;

			// Read active Filter from the grid (server-set: ?taxonomy on listing pages, the term on archives)
			const activeSlug = grid.dataset.activeSlug || '';

			// Fetch next batch and append — callable from the observer and the <details> open handler.
			function loadMore() {
				if ( loading || ! hasMore ) return;
				loading = true;

				const formData = new FormData();
				formData.append( 'action',      'er_load_more' );
				formData.append( 'post_type',   postType );
				formData.append( 'taxonomy',    taxonomy );
				formData.append( 'limit',       limit );
				formData.append( 'orderby',     orderby );
				formData.append( 'order',       order );
				formData.append( 'offset',      offset );
				formData.append( 'active_slug', activeSlug );

				const searchTerm = grid.dataset.search || '';
				if ( searchTerm ) { formData.append( 'search', searchTerm ); }

				fetch( erLoadMore.ajaxUrl, { method: 'POST', body: formData } )
					.then( function( res ) { return res.json(); } )
					.then( function( data ) {
						if ( data.success && data.data.html ) {
							grid.insertAdjacentHTML( 'beforeend', data.data.html );
							const newCards = Array.from( grid.querySelectorAll( 'article:not([data-revealed])' ) );
							newCards.forEach( function( card ) { card.setAttribute( 'data-revealed', '1' ); } );
							revealCards( newCards );
							offset  += limit;
							hasMore  = data.data.has_more;
						} else {
							hasMore = false;
						}
						loading = false;
						maybeLoad();
					} )
					.catch( function() { loading = false; } );
			}

			// Load if this grid's sentinel is within 200px of the viewport now.
			// A hidden sentinel (collapsed <details>) has a zero-size rect, so it's skipped.
			function maybeLoad() {
				if ( loading || ! hasMore ) return;
				const rect = sentinel.getBoundingClientRect();
				const inView = rect.top < ( window.innerHeight + 200 ) && rect.bottom > 0;
				const hasLayout = rect.width > 0 || rect.height > 0;
				if ( inView && hasLayout ) { loadMore(); }
			}

			const observer = new IntersectionObserver( function( entries ) {
				entries.forEach( function( entry ) {
					if ( entry.isIntersecting ) { loadMore(); }
				} );
			}, { rootMargin: '200px' } );

			observer.observe( sentinel );

			// If this grid sits inside a <details>, its sentinel has no layout
			// while collapsed, so the observer never fires. Re-check on open.
			const detailsParent = grid.closest( 'details' );
			if ( detailsParent ) {
				detailsParent.addEventListener( 'toggle', function() {
					if ( detailsParent.open ) { maybeLoad(); }
				} );
			}
		} );

		// ---- 9e. <details> persistence across filter-tab navigation ----
		document.addEventListener('DOMContentLoaded', function() {

			// On Load: Restore any <details> that were open before the Filter Navigation
			var allDetails    = Array.from(document.querySelectorAll('details'));
			var openIndexes   = JSON.parse(sessionStorage.getItem('er_open_details') || '[]');
			var firstRestored = null;
			openIndexes.forEach(function(index) {
				if (allDetails[index]) {
					allDetails[index].open = true;
					if (!firstRestored) { firstRestored = allDetails[index]; }
				}
			});
			sessionStorage.removeItem('er_open_details');
			if (firstRestored) {
				var top = firstRestored.getBoundingClientRect().top + window.scrollY - 120;
				window.scrollTo({ top: top, behavior: 'smooth' });
			}

			// On filter-tab mousedown: Save Index of each Ancestor <details> in the page-wide Details List
			document.querySelectorAll('.er-archive-filter .er-filter-tab').forEach(function(tab) {
				tab.addEventListener('mousedown', function() {
					var allDetails  = Array.from(document.querySelectorAll('details'));
					var openIndexes = [];
					var node        = tab.parentElement;
					while (node && node !== document.body) {
						if (node.tagName === 'DETAILS') {
							var index = allDetails.indexOf(node);
							if (index !== -1) { openIndexes.push(index); }
						}
						node = node.parentElement;
					}
					if (openIndexes.length) {
						sessionStorage.setItem('er_open_details', JSON.stringify(openIndexes));
					}
				});
			});

		});

		// ---- 9f. Whole-card click: forward any .is-clickable-card to its title link ----
		// One delegated listener covers both card types (.listing-item and .entry-card)
		// via the shared .is-clickable-card marker. Listening on document means slider
		// clones and cards appended by 9d work without re-binding. Real links and buttons
		// inside the card are left alone.
		(function () {
			var SEL = '.is-clickable-card';
			function cardLink(card) {
				return card.querySelector('.title a[href]')
					|| card.querySelector('.entry-title a[href]')
					|| card.querySelector('a[href]');
			}
			function markCursors() {
				document.querySelectorAll(SEL).forEach(function (card) {
					if (cardLink(card)) card.style.cursor = 'pointer';
				});
			}
			document.addEventListener('click', function (e) {
				if (e.target.closest('a, button, select, input, textarea, label')) return;
				var card = e.target.closest(SEL);
				if (!card) return;
				if (window.getSelection && String(window.getSelection()).length > 0) return;
				var link = cardLink(card);
				if (!link) return;
				if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) {
					window.open(link.href, '_blank');
					return;
				}
				window.location.href = link.href;
			});
			markCursors();
		})();

	})();
	</script>
	<?php
}, 15 ); // Load deferred Footer JS after theme defaults

// ======================================
// 10. TEMPLATE ROUTING
// ======================================
// Routes my-quotes + my-traits singles to the theme's templates/single-no-sidebar.html
// via one shared file instead of duplicate per-CPT templates.

function er_route_no_sidebar( $templates ) {
	if ( is_singular( array( 'my-quotes', 'my-traits' ) ) ) {
		array_unshift( $templates, 'single-no-sidebar' );
	}
	return $templates;
}
add_filter( 'single_template_hierarchy', 'er_route_no_sidebar' );
