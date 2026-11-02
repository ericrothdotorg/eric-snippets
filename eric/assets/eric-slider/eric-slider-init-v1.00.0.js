/**
 * Eric Slider — initialization.
 *
 * Loaded as a real enqueued file with 'eric-slider-js' as a dependency, so the
 * EricSlider library is guaranteed to be defined before this runs.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TO CONFIGURE: edit SHARED and SLIDER_PROFILES below. Nothing else in this file
 * needs to change. Each key in SLIDER_PROFILES is a CSS class you add to a
 * container; every element carrying that class becomes a slider with those
 * options.
 *
 * HOW THE VALUES RESOLVE — three layers, each overriding the one before:
 *
 *   1. library defaults  (DEFAULTS in eric-slider.js — full list in the README)
 *   2. SHARED            (applies to every profile)
 *   3. the profile       (wins over both)
 *
 * So a profile that needs no autoplay just adds `autoplay: false`, even though
 * SHARED turns it on. Same for anything else: `slidesToScroll: 2`, `speed: 800`,
 * `draggable: false`. See the commented example at the end of SLIDER_PROFILES.
 *
 * KEEP IN SYNC: the FOUC guard in PHP lists the same classes. Add a profile here,
 * add its class there.
 * ─────────────────────────────────────────────────────────────────────────────
 */

(function () {

    /* =========================================================
       CONFIG — applied to every profile below
    ========================================================= */

    var SHARED = {
        autoplay:       true,
        autoplaySpeed:  2000,
        infinite:       true,
        slidesToScroll: 1
    };

    /* =========================================================
       CONFIG — one entry per slider class
    ========================================================= */

    var SLIDER_PROFILES = {

        // ── Single item ──────────────────────────────────────

        'slideshow-single-item': {                 // slide, with controls, with dots
            label:          'Images Slideshow',
            controls:       true,
            dots:           true,
            fade:           false,
            adaptiveHeight: true,
            slidesToShow:   1
        },

        'slideshow-single-item-fade': {            // fade, no controls, with dots
            label:          'Images Slideshow',
            controls:       false,
            dots:           true,
            fade:           true,
            adaptiveHeight: true,
            slidesToShow:   1
        },

        'slideshow-single-item-no-dots': {         // fade, no controls, no dots
            label:          'Images Slideshow',
            controls:       false,
            dots:           false,
            fade:           true,
            adaptiveHeight: true,
            slidesToShow:   1
        },

        'slideshow-single-item-chromeless': {      // slide, no controls, no dots
            label:          'Images Slideshow',
            controls:       false,
            dots:           false,
            fade:           false,
            adaptiveHeight: true,
            slidesToShow:   1
        },

        // ── Multiple items ───────────────────────────────────

        'slideshow-multiple-items': {              // slide, with controls, with dots
            label:          'Posts Slideshow',
            controls:       true,
            dots:           true,
            fade:           false,
            adaptiveHeight: false,
            slidesToShow:   2,
            responsive: [
                { breakpoint: 768, settings: { slidesToShow: 1 } }
            ]
        },

        'slideshow-multiple-items-3': {            // slide, with controls, with dots
            label:          'Posts Slideshow',
            controls:       true,
            dots:           true,
            fade:           false,
            adaptiveHeight: false,
            slidesToShow:   3,
            responsive: [
                { breakpoint: 992, settings: { slidesToShow: 2 } },
                { breakpoint: 768, settings: { slidesToShow: 1 } }
            ]
        },

        'slideshow-multiple-items-4': {            // slide, with controls, with dots
            label:          'Posts Slideshow',
            controls:       true,
            dots:           true,
            fade:           false,
            adaptiveHeight: false,
            slidesToShow:   4,
            responsive: [
                { breakpoint: 992, settings: { slidesToShow: 3 } },
                { breakpoint: 768, settings: { slidesToShow: 2 } },
                { breakpoint: 350, settings: { slidesToShow: 1 } }
            ]
        },

        'slideshow-multiple-items-vertical': {     // slide, no controls, no dots
            label:          'Posts Slideshow',
            controls:       false,
            dots:           false,
            fade:           false,
            adaptiveHeight: false,
            vertical:       true,
            slidesToShow:   3
        },

        'slideshow-multiple-items-center-mode': {  // slide, with controls, with dots
            label:          'Services Slideshow',
            controls:       true,
            dots:           true,
            fade:           false,
            adaptiveHeight: false,
            centerMode:     true,
            centerPadding:  '175px',
            slidesToShow:   1,
            responsive: [
                { breakpoint: 992, settings: { centerPadding: '75px' } },
                { breakpoint: 768, settings: { centerPadding: '0px' } }
            ]
        },

        // ── Quotes ───────────────────────────────────────────

        'slideshow-quotes': {                      // fade, no controls, with dots
            label:          'Quotes Slideshow',
            controls:       false,
            dots:           true,
            fade:           true,
            adaptiveHeight: true,
            pauseOnHover:   true,
            pauseOnFocus:   true,
            slidesToShow:   1
        }

        // ── Your own profile ─────────────────────────────────
        //
        // Uncomment, rename, and add <div class="my-gallery"> ... </div> to a page.
        // Every value here overrides SHARED and the library defaults.
        //
        // 'my-gallery': {
        //     label:          'My Gallery',
        //     controls:       true,
        //     dots:           true,
        //     slidesToShow:   3,
        //     slidesToScroll: 3,      // step three at a time, not one
        //     autoplay:       false,  // off, even though SHARED turns it on
        //     speed:          800,    // slower transition than the 400ms default
        //     responsive: [
        //         { breakpoint: 992, settings: { slidesToShow: 2, slidesToScroll: 2 } },
        //         { breakpoint: 768, settings: { slidesToShow: 1, slidesToScroll: 1 } }
        //     ]
        // }

    };

    /* =========================================================
       RUNNER — no need to edit below this line
    ========================================================= */

    function merge(base, extra) {
        var out = {}, k;
        for (k in base)  { if (Object.prototype.hasOwnProperty.call(base, k))  out[k] = base[k]; }
        for (k in extra) { if (Object.prototype.hasOwnProperty.call(extra, k)) out[k] = extra[k]; }
        return out;
    }

    function initEricSlider() {
        if (typeof EricSlider !== 'function') return;

        var classes = Object.keys(SLIDER_PROFILES);

        for (var i = 0; i < classes.length; i++) {
            var cls      = classes[i];
            var settings = merge(SHARED, SLIDER_PROFILES[cls]);
            var nodes    = Array.prototype.slice.call(document.querySelectorAll('.' + cls));

            for (var j = 0; j < nodes.length; j++) {
                new EricSlider(nodes[j], settings);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEricSlider);
    } else {
        initEricSlider();
    }

})();