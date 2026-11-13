<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ================================================
// LANGUAGE PAIRS
// ================================================

// A German page and its English version (set on the German page, Eric Companion PHP 1d)
// form a pair. On both pages: the browser-language redirect and the flag switcher.
// Returns the other page's path and this page's language, or null on every other page.
function er_ninja_pair() {
    static $pair = false;
    if ( false !== $pair ) {
        return $pair;
    }
    $pair = null;
    if ( is_page() && function_exists( 'er_lang_counterpart' ) ) {
        $id    = get_queried_object_id();
        $other = er_lang_counterpart( $id );
        if ( $other ) {
            $pair = array(
                'other' => wp_make_link_relative( get_permalink( $other ) ),
                'lang'  => er_is_german( $id ) ? 'de' : 'en',
            );
        }
    }
    return $pair;
}

// ================================================
// EARLY AUTO-DETECTION (runs before Page renders)
// ================================================

// No choice made yet (flag click): German browsers go to the German page, all
// others to the English one.
add_action('wp_head', function() {
    $pair = er_ninja_pair();
    if (!$pair) {
        return;
    }
    ?>
    <script>
        (function() {
            'use strict';
            var pair = <?php echo wp_json_encode($pair); ?>;
            try { if (localStorage.getItem('userLanguageChoice')) return; } catch (e) {}
            var browser = (navigator.language || (navigator.languages || [])[0] || '').toLowerCase().split('-')[0];
            if ((pair.lang === 'en') === (browser === 'de')) {
                window.location.replace(pair.other);
            }
        })();
    </script>
    <?php
}, 1);

// ================================================
// FOOTER SCRIPTS & STYLES (CONDITIONAL)
// ================================================

add_action('wp_footer', function() {

    // === SCRIPT + STYLE INJECTION (for flag switcher) ===

    $pair = er_ninja_pair();
    if ($pair) {
        ?>
        <script>
            (function() {
                'use strict';
                let isProcessing = false;

                function insertFlagBeforeHeading() {
                    const flagWrapper = document.querySelector('.lang-flag-wrapper');
                    const heading = document.querySelector('main h3, .content-area h3, article h3');
                    if (!flagWrapper || !heading) return false;

                    const row = document.createElement('div');
                    row.className = 'lang-heading-row';
                    heading.parentNode.insertBefore(row, heading);
                    row.appendChild(flagWrapper);
                    row.appendChild(heading);
                    return true;
                }

                function tryInsertFlag() {
                    if (!insertFlagBeforeHeading()) {
                        setTimeout(tryInsertFlag, 100);
                    }
                }

                document.addEventListener('DOMContentLoaded', function() {
                    try {
                        document.body.classList.add('lang-ready');
                        tryInsertFlag();

                        document.querySelectorAll('.lang-flag').forEach(function(flag) {
                            flag.addEventListener('click', function(e) {
                                e.preventDefault();
                                if (isProcessing) return;
                                const target = flag.getAttribute('data-target');
                                if (target) {
                                    isProcessing = true;
                                    try {
                                        // The flag always leads to the other language of the pair
                                        localStorage.setItem('userLanguageChoice', <?php echo wp_json_encode($pair['lang'] === 'de' ? 'en' : 'de'); ?>);
                                    } catch (error) {
                                        console.warn('Could not store language preference:', error);
                                    }
                                    window.location.href = target;
                                }
                            });
                            flag.addEventListener('keydown', function(e) {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    flag.click();
                                }
                            });
                        });
                    } catch (error) {
                        console.warn('Language switcher initialization failed:', error);
                    }
                });
            })();
        </script>
        <style>
            .lang-heading-row {
                display: flex;
                align-items: center;
                gap: 35px;
            }
            .lang-heading-row h3 {
                margin: 0;
            }
            .lang-flag-wrapper {
                position: static;
                display: flex;
                flex-shrink: 0;
            }
            .lang-flag {
                cursor: pointer;
                width: 36px;
                height: 20px;
                object-fit: cover;
                border: none;
                outline: none;
                transition: transform 0.2s ease;
            }
			.lang-flag:hover,
			.lang-flag:focus-visible {
				outline: var(--a11y-focus-width) solid var(--a11y-focus-color);
				outline-offset: var(--a11y-focus-offset);
			}
        </style>
        <?php
    }

    // === NINJA CHARACTER INJECTION ===

    if (
        is_page(array(
            // English Pages
            '179','87873','59078','55867','138768','113713','123635','65752','8977','100674','83147','55706','65756','87873','151412','51969')) ||
        // German Pages (Eric Companion PHP 1d)
        (is_page() && function_exists('er_is_german') && er_is_german(get_queried_object_id())) ||
        is_single(array(
            // English Singles
            '140909','127567','127484','121987','121984','141168','121985','135495','121986','121983','150592')) ||
        is_tax('topics', 124) ||
        is_tax('things', 137) ||
		is_tax('types', 182)
    ) {
        ?>
        <script>
            (function() {
                const initNinja = function() {
                    const targetContainer = document.querySelector(".er-hero-section .er-hero-inner"); // the dark panel itself (rgba(0,0,0,.65) in the theme's style.css); near-symmetric padding so top:50% centers cleanly
                    if (targetContainer) {
                        const wrapper = document.createElement("div");
                        wrapper.className = "custom-ninja-wrapper";

                        const link = document.createElement("a");
                        link.href  = "/services/";
                        link.style.pointerEvents = "auto";
                        link.title = "Ninja Services";
                        link.setAttribute("aria-label", "Ninja Services");

                        const img = document.createElement("img");
                        img.src     = "/wp-content/uploads/2025/08/Ninja-Character-Stroke-1px.png";
                        img.alt     = ""; // decorative: the link's label says where it goes
                        img.className = "custom-ninja-image daneden-slideInRight";
                        img.width   = 100;
                        img.height  = 100;
                        img.loading = "lazy";
                        img.decoding = "async";
                        img.setAttribute("fetchpriority", "low");

                        link.appendChild(img);
                        wrapper.appendChild(link);
                        targetContainer.appendChild(wrapper);
                    }
                };
                if ('requestIdleCallback' in window) {
                    requestIdleCallback(initNinja);
                } else {
                    window.addEventListener("load", initNinja);
                }
            })();
        </script>
        <style>
            .custom-ninja-wrapper {
                position: absolute;
                top: 50%;
                right: 25px;
                transform: translateY(-50%);
                z-index: 99;
                width: clamp(60px, calc(8vw + 4px), 100px);
                height: clamp(60px, calc(8vw + 4px), 100px);
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .custom-ninja-image {
                max-width: 100%;
                height: auto;
                border: 0;
            }
            /* Reserve right-side gutter so long description text wraps before the ninja zone.
               er_hero (own shortcode, Companion PHP) outputs the text bare in .page-description (no <p>), so target the div. */
            .er-hero-section .er-entry-header .page-description {
                margin-right: clamp(120px, calc(10vw + 20px), 150px);
            }
        </style>
        <?php
    }
});
