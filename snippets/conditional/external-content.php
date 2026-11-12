<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ==================================================================
// HOLD BACK THE PLAYER
// The browser starts loading a video player as soon as it reads an
// iframe's src, long before the placeholder script below runs. So the
// src becomes data-src here; the player loads only on click.
// The YouTube / Vimeo thumbnail loads only after Accept on the cookie
// notice (cookieaccepted=1); before that, a dark tile with the title.
// Without JavaScript, the original iframe in <noscript> shows the player.
// Also marks the page, so style and script are only printed where a
// video is (block themes render the page before the footer).
// ==================================================================

function er_video_hold( $html ) {
	if ( strpos( $html, '<iframe' ) === false || is_feed() ) return $html; // feeds keep the real player
	return preg_replace_callback(
		// <noscript> blocks are matched first and left alone, so a second pass changes nothing
		'#<noscript>.*?</noscript>|(<iframe\b[^>]*?\ssrc=(["\'])(?:https?:)?//(?:www\.)?(?:youtube\.com/embed|player\.vimeo\.com/video)[^"\']*\2[^>]*>\s*</iframe>)#is',
		function ( $m ) {
			if ( empty( $m[1] ) ) return $m[0];
			$GLOBALS['er_has_video'] = true;
			return preg_replace( '/\ssrc=/', ' data-src=', $m[1], 1 ) . '<noscript>' . $m[1] . '</noscript>';
		},
		$html
	);
}
add_filter( 'render_block', 'er_video_hold' );
add_filter( 'the_content', 'er_video_hold', 99 ); // videos from shortcodes appear after the blocks

add_action('wp_footer', function () {
    if ( empty( $GLOBALS['er_has_video'] ) ) return;
    ?>
    <style>
        .video-placeholder {position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer; background: var(--color-10);}
        .video-placeholder img {width: 100%; height: 100%; object-fit: cover;}
        /* Before "Accept": no thumbnail (it would send the visitor's IP to YouTube / Vimeo) - dark tile, play button, title */
        .video-placeholder-title {position: absolute; left: 0; right: 0; bottom: 0; padding: 12px 16px; color: var(--color-5); font-size: var(--er-fs-sm); text-align: center;}
        .play-button-youtube,
        .play-button-vimeo {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 65px; height: 65px;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            transition: transform 0.25s ease;
            z-index: 2;
        }
        .play-button-youtube:hover,
        .play-button-vimeo:hover {transform: translate(-50%, -50%) scale(1.25);}
        .play-button-youtube {background-image: url('/wp-content/uploads/2025/01/YT-Play-Button.png');}
        .play-button-vimeo {background-image: url('/wp-content/uploads/2025/09/Vimeo-Play-Button.png');}
        .video-placeholder:hover::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.25);
            z-index: 1;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function createPlaceholder(iframe, videoId, platform) {
                var title = iframe.getAttribute('title') || (platform === 'youtube' ? 'YouTube video' : 'Vimeo video');
                var placeholder = document.createElement('div');
                placeholder.className = 'video-placeholder';
                placeholder.setAttribute('data-video-id', videoId);
                // A real control for keyboard and screen reader users, not only for the mouse
                placeholder.setAttribute('role', 'button');
                placeholder.setAttribute('tabindex', '0');
                placeholder.setAttribute('aria-label', 'Play video: ' + title);
                // Thumbnail from YouTube / Vimeo only after "Accept"; otherwise the title on a dark tile
                if (/(?:^|;\s*)cookieaccepted=1(?:;|$)/.test(document.cookie)) {
                    var img = document.createElement('img');
                    img.src = platform === 'youtube'
                        ? 'https://img.youtube.com/vi/' + videoId + '/hqdefault.jpg'
                        : 'https://vumbnail.com/' + videoId + '.jpg';
                    img.alt = ''; // the placeholder's label names the video
                    img.loading = 'lazy';
                    placeholder.appendChild(img);
                } else {
                    var caption = document.createElement('span');
                    caption.className = 'video-placeholder-title';
                    caption.textContent = title;
                    placeholder.appendChild(caption);
                }
                var playButton = document.createElement('div');
                playButton.className = platform === 'youtube' ? 'play-button-youtube' : 'play-button-vimeo';
                placeholder.appendChild(playButton);
                iframe.replaceWith(placeholder);
                function play() {
                    var newIframe = document.createElement('iframe');
                    newIframe.setAttribute('src', platform === 'youtube'
                        ? 'https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1'
                        : 'https://player.vimeo.com/video/' + videoId + '?autoplay=1&dnt=1');
                    newIframe.setAttribute('frameborder', '0');
                    newIframe.setAttribute('allow', platform === 'youtube'
                        ? 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
                        : 'autoplay; fullscreen; picture-in-picture');
                    newIframe.setAttribute('allowfullscreen', 'true');
                    newIframe.setAttribute('title', title);
                    placeholder.replaceWith(newIframe);
                    newIframe.focus();
                }
                placeholder.addEventListener('click', play);
                placeholder.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); play(); }
                });
            }
            // data-src: set by er_video_hold() above
            document.querySelectorAll('iframe[data-src*="youtube.com/embed"]').forEach(function(iframe) {
                createPlaceholder(iframe, iframe.dataset.src.split('/embed/')[1].split('?')[0], 'youtube');
            });
            document.querySelectorAll('iframe[data-src*="player.vimeo.com/video"]').forEach(function(iframe) {
                createPlaceholder(iframe, iframe.dataset.src.split('/video/')[1].split('?')[0], 'vimeo');
            });
        });
    </script>
    <?php
});

// ==================================================================
// WIKIPEDIA PREVIEWS
// Hovering or focusing a Wikipedia link shows a card with title,
// thumbnail and opening lines from Wikipedia's page summary API.
// The browser contacts Wikimedia directly, so this runs only after
// Accept on the cookie notice (cookieaccepted=1), and only on devices
// with a mouse. Like the videos above, the page is marked while it
// renders, so style and script are only printed where a Wikipedia link is.
// ==================================================================

function er_wiki_mark( $html ) {
	if ( empty( $GLOBALS['er_has_wiki'] ) && strpos( $html, 'wikipedia.org/wiki/' ) !== false ) {
		$GLOBALS['er_has_wiki'] = true;
	}
	return $html;
}
add_filter( 'render_block', 'er_wiki_mark' );
add_filter( 'the_content', 'er_wiki_mark', 99 ); // links from shortcodes appear after the blocks

add_action('wp_footer', function () {
    if ( empty( $GLOBALS['er_has_wiki'] ) ) return;
    ?>
    <style>
        .er-wiki {
            position: absolute; z-index: 9999;
            display: flex; gap: 12px;
            width: min(360px, calc(100vw - 16px));
            padding: 12px;
            background: var(--color-8); color: var(--color-3);
            border: 1px solid var(--color-5); border-radius: 10px;
            box-shadow: 0 6px 24px rgba(7, 12, 18, 0.15);
            font-size: var(--er-fs-xs); line-height: 1.5;
            opacity: 0; visibility: hidden;
            transition: opacity 0.15s ease, visibility 0.15s;
        }
        .er-wiki.is-on {opacity: 1; visibility: visible;}
        .er-wiki img {flex: 0 0 72px; width: 72px; height: 72px; object-fit: cover; border-radius: 6px;}
        .er-wiki b {display: block; margin-bottom: 2px; color: var(--color-4); font-size: var(--er-fs-sm);}
        .er-wiki p {margin: 0; display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden;}
        .er-wiki small {display: block; margin-top: 6px; font-style: italic;}
        body.dark-mode .er-wiki {background: var(--color-10); color: var(--color-5); border-color: var(--color-3);}
        body.dark-mode .er-wiki b {color: var(--color-7);}
        @media (prefers-reduced-motion: reduce) {.er-wiki {transition: none;}}
    </style>

    <script>
        (function () {
            if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return; // no hover on touch devices
            var SEL = 'a[href*="wikipedia.org/wiki/"]';
            // Wikimedia asks every caller to identify itself; browsers send this header instead of User-Agent
            var UA = 'ericroth.org link previews (https://ericroth.org/)';
            var cache = {}, card = null, current = null, wait = 0, out = 0;

            function consent() { return /(?:^|;\s*)cookieaccepted=1(?:;|$)/.test(document.cookie); }

            // https://xx.wikipedia.org/wiki/Title#Section -> https://xx.wikipedia.org/api/rest_v1/page/summary/Title
            function api(a) {
                var u;
                try { u = new URL(a.href); } catch (e) { return null; }
                if (!/^[a-z-]+(\.m)?\.wikipedia\.org$/.test(u.hostname) || u.pathname.indexOf('/wiki/') !== 0) return null;
                var title = u.pathname.slice(6);
                return title ? 'https://' + u.hostname.replace('.m.', '.') + '/api/rest_v1/page/summary/' + title : null;
            }

            function get(url) {
                if (!cache[url]) {
                    cache[url] = fetch(url, {headers: {'Api-User-Agent': UA}})
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .catch(function () { return null; });
                }
                return cache[url];
            }

            function hide() {
                clearTimeout(wait); clearTimeout(out);
                current = null;
                if (card) card.classList.remove('is-on');
            }

            function build() {
                card = document.createElement('div');
                card.className = 'er-wiki';
                card.setAttribute('aria-hidden', 'true'); // the link itself leads to the article
                // The pointer may move onto the card without it closing (WCAG 1.4.13)
                card.addEventListener('mouseenter', function () { clearTimeout(out); });
                card.addEventListener('mouseleave', hide);
                document.body.appendChild(card);
            }

            function show(a, d) {
                if (current !== a || !d || !d.extract) return;
                if (!card) build();
                card.textContent = '';
                if (d.thumbnail && d.thumbnail.source) {
                    var img = document.createElement('img');
                    img.src = d.thumbnail.source;
                    img.alt = '';
                    card.appendChild(img);
                }
                var box = document.createElement('div');
                var b = document.createElement('b');
                var p = document.createElement('p');
                var s = document.createElement('small');
                b.textContent = d.title;
                p.textContent = d.extract;
                s.textContent = 'From Wikipedia';
                box.append(b, p, s);
                card.appendChild(box);
                // Above the link, or below it when there is no room; kept inside the window
                var r = a.getBoundingClientRect(), w = card.offsetWidth, h = card.offsetHeight;
                var left = Math.min(Math.max(8, r.left), document.documentElement.clientWidth - w - 8);
                var top = (r.top - h - 8 < 8) ? r.bottom + 8 : r.top - h - 8;
                card.style.left = (left + window.scrollX) + 'px';
                card.style.top = (top + window.scrollY) + 'px';
                card.classList.add('is-on');
            }

            function enter(e) {
                var a = e.target.closest && e.target.closest(SEL);
                if (!a) return;
                clearTimeout(out);
                if (a === current || !consent()) return;
                var url = api(a);
                if (!url) return;
                hide();
                current = a;
                wait = setTimeout(function () { get(url).then(function (d) { show(a, d); }); }, 300);
            }

            function leave(e) {
                var a = e.target.closest && e.target.closest(SEL);
                if (!a || a !== current || a.contains(e.relatedTarget)) return;
                clearTimeout(out);
                out = setTimeout(hide, 150); // time to reach the card
            }

            document.addEventListener('mouseover', enter);
            document.addEventListener('mouseout', leave);
            document.addEventListener('focusin', enter);
            document.addEventListener('focusout', leave);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
            window.addEventListener('scroll', hide, {passive: true});
        })();
    </script>
    <?php
});
