<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ==================================================================
// HOLD BACK THE PLAYER
// The browser starts loading a video player as soon as it reads an
// iframe's src, long before the placeholder script below runs. So the
// src becomes data-src here; the player loads only on click.
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
        .video-placeholder {position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer;}
        .video-placeholder img {width: 100%; height: 100%; object-fit: cover;}
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
                var img = document.createElement('img');
                img.src = platform === 'youtube'
                    ? 'https://img.youtube.com/vi/' + videoId + '/hqdefault.jpg'
                    : 'https://vumbnail.com/' + videoId + '.jpg';
                img.alt = ''; // the placeholder's label names the video
                img.loading = 'lazy';
                var playButton = document.createElement('div');
                playButton.className = platform === 'youtube' ? 'play-button-youtube' : 'play-button-vimeo';
                placeholder.appendChild(img);
                placeholder.appendChild(playButton);
                iframe.replaceWith(placeholder);
                function play() {
                    var newIframe = document.createElement('iframe');
                    newIframe.setAttribute('src', platform === 'youtube'
                        ? 'https://www.youtube.com/embed/' + videoId + '?autoplay=1'
                        : 'https://player.vimeo.com/video/' + videoId + '?autoplay=1');
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
