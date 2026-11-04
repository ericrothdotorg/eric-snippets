<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ======================================
// HELPER: Render a private Page
// ======================================

function er_render_private_page( $slug, $loop_html = '' ) {
    $pages = get_posts([
        'name'        => $slug,
        'post_type'   => 'page',
        'post_status' => 'private',
        'numberposts' => 1,
    ]);
    $page = $pages ? $pages[0] : null;

    if ( ! $page ) {
        return false;
    }

    // Strip the "Private: " prefix WP adds to private Page Titles
    $page->post_title = preg_replace( '/^Private:\s*/i', '', $page->post_title );
    $remove_private_prefix = fn( $format ) => '%s';
    add_filter( 'private_title_format', $remove_private_prefix );

    // If Search Results: Inject Loop into Content before anything renders
    if ( $loop_html ) {
        $page->post_content = str_replace( '<!--SEARCH RESULTS-->', $loop_html, $page->post_content );
    }

    // Override the main Query so Page renders as if it were a normal published Page
    global $wp_query, $post;
    $page->post_status   = 'publish';
    $page->post_password = '';
    $post                = $page;
    setup_postdata( $post );
    $wp_query->posts              = [ $page ];
    $wp_query->post               = $page;
    $wp_query->found_posts        = 1;
    $wp_query->post_count         = 1;
    $wp_query->is_404             = false;
	$GLOBALS['er_synthetic_page'] = $page->ID;
    $wp_query->is_page            = true;
    $wp_query->is_singular        = true;
    $wp_query->is_search          = false;
    $wp_query->queried_object     = $page;
    $wp_query->queried_object_id  = $page->ID;

    $template = get_page_template();
    if ( ! $template ) {
        $template = get_template_directory() . '/index.php';
    }

    load_template( $template );
	remove_filter( 'private_title_format', $remove_private_prefix );
    exit;
}

// ======================================
// 404 ERROR PAGE (set to PRIVATE)
// ======================================

add_filter( 'template_include', function( $template ) {
    if ( ! is_404() ) {
        return $template;
    }
    er_render_private_page( '404-error' );
    return $template;
}, 99 );

// ======================================
// SEARCH RESULTS PAGE (set to PRIVATE)
// ======================================

add_filter( 'template_include', function( $template ) {
    if ( ! is_search() ) {
        return $template;
    }
    if ( have_posts() ) {
        $limit = 12;
        ob_start();
        echo '<div class="er-cpt-grid" data-search="' . esc_attr( get_search_query() ) . '"'
           . ' data-limit="' . esc_attr( $limit ) . '" data-offset="' . esc_attr( $limit ) . '">';
        $shown = 0;
        while ( have_posts() && $shown < $limit ) {
            the_post();
            if ( function_exists( 'er_render_card' ) && function_exists( 'er_taxonomy_for_post' ) ) {
                er_render_card( get_the_ID(), er_taxonomy_for_post( get_the_ID() ) );
            } else {
                echo '<article class="entry-card"><h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2></article>';
            }
            $shown++;
        }
        echo '</div>';
        global $wp_query;
        if ( intval( $wp_query->found_posts ) > $limit ) {
            echo '<div class="er-load-more-sentinel"></div>';
        }
        $loop_html = ob_get_clean();
        er_render_private_page( 'search-results', $loop_html );
    } else {
        er_render_private_page( 'search-no-results' );
    }
    return $template;
}, 99 );

// ======================================
// SEO TITLE for the synthetic Pages
// ======================================

// The SEO Framework reads the query on template_redirect, before the swap above
// (template_include), and keeps that state: it then sees neither the search / 404
// nor the page and falls back to "Untitled". Give it the private page's title.

add_filter( 'the_seo_framework_title_from_generation', function( $title, $args ) {
    if ( null === $args && ! empty( $GLOBALS['er_synthetic_page'] ) ) {
        return get_the_title( $GLOBALS['er_synthetic_page'] );
    }
    return $title;
}, 10, 2 );

// ======================================
// NOINDEX for the synthetic Pages
// ======================================

// Same cause as the title above: The SEO Framework no longer sees the search / 404,
// so its default noindex for them is missing. Set it back.

add_filter( 'the_seo_framework_robots_meta_array', function( $meta, $args ) {
    if ( null === $args && ! empty( $GLOBALS['er_synthetic_page'] ) ) {
        $meta['noindex'] = 'noindex';
    }
    return $meta;
}, 10, 2 );
