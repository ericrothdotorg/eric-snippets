<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* =============================================================================
   1. DATABASE SETUP
   Creates wp_er_subscribers once per schema version (er_subscribers_schema),
   then never again: no table work and no upgrade.php on every request.
   Also adds the last_send columns to installs from before they existed.
============================================================================= */

add_action( 'init', function () {
    if ( get_option( 'er_subscribers_schema' ) === '2' ) return;
    global $wpdb;
    $table = $wpdb->prefix . 'er_subscribers';
    // CREATE TABLE IF NOT EXISTS never touches an existing table
    $wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} (
        id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email            VARCHAR(191)    NOT NULL,
        token            VARCHAR(64)     NOT NULL,
        status           ENUM('pending','active') NOT NULL DEFAULT 'pending',
        created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_post_title  VARCHAR(255)    NULL DEFAULT NULL,
        last_send_status ENUM('sent','failed') NULL DEFAULT NULL,
        last_send_error  TEXT            NULL DEFAULT NULL,
        last_send_at     DATETIME        NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email (email)
    ) {$wpdb->get_charset_collate()}" );
    // Tables created before the last_send columns existed
    $existing_columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );
    $to_add = [
        'last_post_title'  => "ALTER TABLE {$table} ADD COLUMN last_post_title  VARCHAR(255) NULL DEFAULT NULL",
        'last_send_status' => "ALTER TABLE {$table} ADD COLUMN last_send_status ENUM('sent','failed') NULL DEFAULT NULL",
        'last_send_error'  => "ALTER TABLE {$table} ADD COLUMN last_send_error  TEXT NULL DEFAULT NULL",
        'last_send_at'     => "ALTER TABLE {$table} ADD COLUMN last_send_at     DATETIME NULL DEFAULT NULL",
    ];
    foreach ( $to_add as $column => $sql ) {
        if ( ! in_array( $column, $existing_columns, true ) ) {
            $wpdb->query( $sql );
        }
    }
    delete_option( 'er_subscribers_table_created' );
    delete_option( 'er_subscribers_v2_migrated' );
    update_option( 'er_subscribers_schema', '2' );
} );

/* =============================================================================
   2. SUBSCRIBER HELPERS
============================================================================= */

function er_get_subscribers( $status = 'active' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'er_subscribers';
    return $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s", $status )
    );
}

function er_add_subscriber( $email ) {
    global $wpdb;
    $table    = $wpdb->prefix . 'er_subscribers';
    $email    = sanitize_email( $email );
    $existing = $wpdb->get_row(
        $wpdb->prepare( "SELECT id, status FROM {$table} WHERE email = %s", $email )
    );
    if ( $existing ) return 'exists';
    $token  = bin2hex( random_bytes( 32 ) );
    $result = $wpdb->insert( $table, [
        'email'  => $email,
        'token'  => $token,
        'status' => 'active',
    ] );
    return $result ? $token : false;
}

function er_remove_subscriber( $email, $token ) {
    global $wpdb;
    $table = $wpdb->prefix . 'er_subscribers';
    $email = sanitize_email( $email );
    $row   = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s AND token = %s",
            $email, $token
        )
    );
    if ( ! $row ) return false;
    $wpdb->delete( $table, [ 'id' => $row->id ] );
    return true;
}

/* =============================================================================
   3. SECURITY HELPERS
============================================================================= */

function er_get_ip() {
    return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
}

// No nonce: the form sits on full-page cached pages, so a nonce printed into
// them would be stale long before the page is (12-24 h) and every sign-up
// would fail with "Security check failed". For a public form it adds no
// protection anyway (every logged-out visitor gets the same one). Spam is
// kept out by the honeypot, the silent math check and the per-IP rate limit.

/* =============================================================================
   4. SUBSCRIPTION FORM  [er_subscribe_form]
============================================================================= */

add_shortcode( 'er_subscribe_form', function () {
    // Styles and script are printed in the footer only on pages that show the form.
    $GLOBALS['er_subscribe_form_used'] = true;
    ob_start(); ?>
    <div class="er-subscribe-wrapper">
        <form id="er-subscribe-form" novalidate>
            <div class="er-field">
                <label for="er-email">Your E-mail Address <span aria-hidden="true">*</span></label>
                <input
                    type="email"
                    id="er-email"
                    name="er_email"
                    required
                    aria-required="true"
                    autocomplete="email"
                >
            </div>
            <!-- Honeypot -->
            <div aria-hidden="true" style="position: absolute; left: -9999px; height: 1px; width: 1px; overflow: hidden;">
                <label for="er-website">Leave this empty</label>
                <input type="text" id="er-website" name="middle_name" tabindex="-1" autocomplete="off">
            </div>
            <!-- Silent math check -->
            <input type="hidden" name="math_check" value="7">
            <button type="submit" id="er-submit-btn">Subscribe</button>
            <div id="er-confirmation" role="status" aria-live="polite" aria-atomic="true" style="display: none;"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
} );

add_action( 'wp_footer', function () {
    if ( empty( $GLOBALS['er_subscribe_form_used'] ) ) return;
    $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
    ?>
    <style>
        .er-subscribe-wrapper {max-width: 350px; overflow: visible;}
        .er-subscribe-wrapper .er-field {margin-bottom: 1em;}
        .er-subscribe-wrapper label {margin-bottom: 0.5em;}
        .er-subscribe-wrapper input[type="email"] {
            display: block; width: 100%; padding: 10px;
            font-size: var(--er-fs-sm);
        }
        .er-subscribe-wrapper button[type="submit"] {
            display: inline-flex; align-items: center; justify-content: center;
            width: auto; padding: 10px 20px;
            font-size: var(--er-fs-sm); font-weight: var(--er-fw-bold); cursor: pointer; border-radius: 4px;
            border: 2px solid transparent; background: var(--color-1); color: var(--color-8);
            text-align: center; transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }
        .er-subscribe-wrapper button[type="submit"]:hover {background: var(--color-2); transform: scale(1.05);}
        .er-subscribe-wrapper button[type="submit"]:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
        .er-subscribe-wrapper button[type="submit"]:focus:not(:focus-visible) {outline: none;}
        .er-subscribe-wrapper #er-confirmation {margin-top: 1em; width: 100%; word-wrap: break-word; overflow-wrap: break-word;}
    </style>
    <script>
    document.addEventListener( 'DOMContentLoaded', function () {
        const form = document.getElementById( 'er-subscribe-form' );
        if ( ! form ) return;
        const confirmation = document.getElementById( 'er-confirmation' );
        const submitBtn    = document.getElementById( 'er-submit-btn' );
        const formLoadTime = Date.now();
        let lastSubmit     = 0;

        form.addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            if ( ( Date.now() - formLoadTime ) / 1000 < 3 ) return;
            if ( Date.now() - lastSubmit < 5000 ) {
                showMessage( 'Please wait a few seconds before submitting again.' );
                return;
            }
            lastSubmit = Date.now();
            const honeypot = form.querySelector( 'input[name="middle_name"]' );
            if ( honeypot && honeypot.value.trim() !== '' ) return;
            submitBtn.disabled    = true;
            submitBtn.textContent = 'Sending\u2026';
            const formData = new FormData( form );
            formData.append( 'action',   'er_subscribe_ajax' );
            fetch( '<?php echo esc_js( $ajax_url ); ?>', { method: 'POST', body: formData } )
                .then( r => r.json() )
                .then( data => {
                    if ( data.success ) {
                        form.reset();
                        showMessage( data.data.message );
                    } else {
                        showMessage( data.data && data.data.message ? data.data.message : 'Something went wrong. Please try again.' );
                    }
                } )
                .catch( () => showMessage( 'Submission failed. Please try again.' ) )
                .finally( () => {
                    submitBtn.disabled    = false;
                    submitBtn.textContent = 'Subscribe';
                } );
        } );

        function showMessage( msg ) {
            confirmation.textContent   = msg;
            confirmation.style.display = 'block';
            confirmation.focus();
        }
    } );
    </script>
    <?php
} );

/* =============================================================================
   5. AJAX FORM HANDLER
============================================================================= */

add_action( 'wp_ajax_er_subscribe_ajax',        'er_handle_subscribe_ajax' );
add_action( 'wp_ajax_nopriv_er_subscribe_ajax', 'er_handle_subscribe_ajax' );

function er_handle_subscribe_ajax() {
    $ip = er_get_ip();

    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
        wp_send_json_error();
    }
    if ( get_transient( 'er_subscribe_ip_' . md5( $ip ) ) ) {
        wp_send_json_error( [ 'message' => 'Please wait before submitting again.' ] );
    }
    if ( sanitize_text_field( wp_unslash( $_POST['middle_name'] ?? '' ) ) !== '' ) {
        wp_send_json_error();
    }
    if ( sanitize_text_field( wp_unslash( $_POST['math_check'] ?? '' ) ) !== '7' ) {
        wp_send_json_error();
    }

    $email = sanitize_email( wp_unslash( $_POST['er_email'] ?? '' ) );
    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
    }

    set_transient( 'er_subscribe_ip_' . md5( $ip ), time(), 60 );
    $token = er_add_subscriber( $email );

    if ( $token === 'exists' ) {
        wp_send_json_error( [ 'message' => 'This email address is already registered.' ] );
    }
    if ( ! $token ) {
        wp_send_json_error( [ 'message' => 'Something went wrong. Please try again.' ] );
    }

    // Single opt-in: subscriber is already active (see er_add_subscriber).
    // Welcome email is fire-and-forget; its delivery does not gate the success response.
    $subject = "You're now subscribed — ericroth.org";
    $body    = "Hi,\n\n"
             . "Your subscription to ericroth.org is confirmed.\n"
             . "You'll receive a notification whenever new content is published.\n\n"
             . get_bloginfo( 'name' );

    wp_mail( $email, $subject, $body );
    wp_send_json_success( [ 'message' => "You're subscribed - welcome!" ] );
}

/* =============================================================================
   6. UNSUBSCRIBE HANDLER
   The link in the e-mail only shows a confirmation button (GET), so mail
   scanners that open every link on their own unsubscribe no one. The button
   (POST) unsubscribes, and so does the one-click unsubscribe of mail programs
   (List-Unsubscribe-Post header, see 7).
============================================================================= */

add_action( 'init', function () {
    if ( empty( $_GET['er_unsub'] ) ) return;
    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'er unsubscribe' );
    $email = sanitize_email( wp_unslash( $_GET['email'] ?? '' ) );
    $token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
    $home  = esc_url( home_url() );

    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
        wp_die(
            '<p>Unsubscribe <strong>' . esc_html( $email ) . '</strong> from ericroth.org?</p>'
            . '<form method="post" action="' . esc_url( er_unsub_url( $email, $token ) ) . '"><button type="submit">Unsubscribe</button></form>'
            . '<p><a href="' . $home . '">No, back to ericroth.org</a></p>',
            'Unsubscribe',
            [ 'response' => 200 ]
        );
    }

    if ( er_remove_subscriber( $email, $token ) ) {
        wp_die(
            '<p>You have been unsubscribed successfully.</p>'
            . '<p>You will be redirected to ericroth.org in 2 seconds.</p>'
            . '<p><a href="' . $home . '">Click here if you are not redirected automatically.</a></p>'
            . '<script>setTimeout(function(){ window.location.href="' . $home . '"; }, 2000);</script>',
            'Unsubscribed',
            [ 'response' => 200 ]
        );
    } else {
        wp_die(
            '<p>This unsubscribe link is invalid.</p>',
            'Error',
            [ 'response' => 400 ]
        );
    }
} );

// The personal unsubscribe address: in the e-mail, its List-Unsubscribe header and the confirmation form
function er_unsub_url( $email, $token ) {
    return add_query_arg(
        [
            'er_unsub' => 1,
            'email'    => rawurlencode( $email ),
            'token'    => rawurlencode( $token ),
        ],
        home_url( '/' )
    );
}

/* =============================================================================
   7. PUBLISH TRIGGER + SENDING QUEUE
   Publishing only queues the notification. wp-cron sends it in batches of
   ER_SUB_BATCH subscribers, one batch a minute, so the publish request never
   waits for the mail server. A cursor (post meta _er_sub_cursor = last
   subscriber id done) makes an interrupted run continue where it stopped:
   nobody gets the e-mail twice, nobody is skipped. _new_post_email_sent marks
   the post as done. Send status and errors are logged per subscriber.
============================================================================= */

defined( 'ER_SUB_BATCH' ) || define( 'ER_SUB_BATCH', 20 ); // e-mails per batch (one batch per minute)

add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
    if ( $new_status !== 'publish' || $old_status === 'publish' ) {
        return;
    }
    if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
        return;
    }
    $watched = [ 'post', 'my-interests', 'my-quotes' ];
    if ( ! in_array( $post->post_type, $watched, true ) ) {
        return;
    }
    // Already sent, or already queued
    if ( get_post_meta( $post->ID, '_new_post_email_sent', true ) || metadata_exists( 'post', $post->ID, '_er_sub_cursor' ) ) {
        return;
    }
    add_post_meta( $post->ID, '_er_sub_cursor', 0, true );
    wp_schedule_single_event( time(), 'er_sub_send_batch', [ $post->ID ] );
}, 10, 3 );

add_action( 'er_sub_send_batch', 'er_sub_send_batch' );

function er_sub_send_batch( $post_id ) {
    global $wpdb;
    $post = get_post( $post_id );
    // Unpublished or deleted in the meantime: stop, nothing more is sent
    if ( ! $post || $post->post_status !== 'publish' ) {
        delete_post_meta( $post_id, '_er_sub_cursor' );
        return;
    }
    $cursor = (int) get_post_meta( $post_id, '_er_sub_cursor', true );
    $subs   = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}er_subscribers WHERE status = 'active' AND id > %d ORDER BY id ASC LIMIT %d",
        $cursor, ER_SUB_BATCH
    ) );
    foreach ( $subs as $sub ) {
        er_sub_send_one( $sub, $post );
        update_post_meta( $post_id, '_er_sub_cursor', (int) $sub->id );
    }
    if ( count( $subs ) === ER_SUB_BATCH ) {
        wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'er_sub_send_batch', [ $post_id ] );
        return;
    }
    delete_post_meta( $post_id, '_er_sub_cursor' );
    update_post_meta( $post_id, '_new_post_email_sent', true );
}

// One notification: HTML + plain-text body, one-click unsubscribe header, result logged on the subscriber
function er_sub_send_one( $sub, $post ) {
    global $wpdb, $phpmailer;
	$title    = get_the_title( $post );
    $url      = get_permalink( $post );
    $excerpt  = has_excerpt( $post )
        ? get_the_excerpt( $post )
        : wp_trim_words( strip_tags( $post->post_content ), 30 );
    $sitename = get_bloginfo( 'name' );

    // HTML-escaped copies for the HTML body. Raw versions above stay for the plain-text body and the email subject
    $title_html    = esc_html( $title );
    $excerpt_html  = esc_html( $excerpt );
    $sitename_html = esc_html( $sitename );
    $url_html      = esc_url( $url );

    $unsub_url = er_unsub_url( $sub->email, $sub->token );
    $subject   = "New post: {$title} — ericroth.org";

    // HTML + PLAIN TEXT BODIES

	$body_html = "
    <p>Hi,</p>
    <p>A new post has just been published on ericroth.org:</p>
    <p><strong>• {$title_html}</strong></p>
    <p><em>{$excerpt_html}</em></p>
    <p><strong>Read it here:</strong><br>
    <a href=\"{$url_html}\">{$url_html}</a></p>
    <p>{$sitename_html}</p>
    <p>-----</p>
    <p><em>To unsubscribe:</em><br>
    <a href=\"" . esc_url( $unsub_url ) . "\">" . esc_html( $unsub_url ) . "</a></p>
    ";

    $body_plain = "Hi,

A new post has just been published on ericroth.org:

• {$title}

{$excerpt}

Read it here:
{$url}

{$sitename}

To unsubscribe:
{$unsub_url}
";

	// EMAIL SENDING + LOGGING

    // Attach the plain-text alternative for THIS send only, then detach it immediately so no other wp_mail() call on the site is affected
    $attach_altbody = function ( $phpmailer ) use ( $body_plain ) {
        $phpmailer->AltBody     = $body_plain;
        $phpmailer->ContentType = 'multipart/alternative';
    };
    add_action( 'phpmailer_init', $attach_altbody );

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'List-Unsubscribe: <' . esc_url_raw( $unsub_url ) . '>',  // unsubscribe button in mail programs
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',       // ...in one click (POST to the address above)
    ];
    $sent = wp_mail( $sub->email, $subject, $body_html, $headers );

    remove_action( 'phpmailer_init', $attach_altbody );
    $error = null;
    if ( ! $sent ) {
        $error = ! empty( $phpmailer->ErrorInfo )
            ? $phpmailer->ErrorInfo
            : 'wp_mail() returned false';
    }
    $wpdb->update(
        $wpdb->prefix . 'er_subscribers',
        [
            'last_post_title'  => $title,
            'last_send_status' => $sent ? 'sent' : 'failed',
            'last_send_error'  => $error,
            'last_send_at'     => current_time( 'mysql' ),
        ],
        [ 'id' => $sub->id ]
    );
}

/* =============================================================================
   8. ADMIN PAGE
============================================================================= */

add_action( 'admin_menu', function () {
    add_menu_page(
        'Subscribers',
        'Subscribers',
        'manage_options',
        'er-subscribers',
        'er_render_admin_page',
        'dashicons-groups',
        30
    );
} );

function er_render_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_subscribers';

    // Single Delete
    if (
        isset( $_GET['er_delete'], $_GET['_wpnonce'] ) &&
        wp_verify_nonce( $_GET['_wpnonce'], 'er_delete_subscriber' )
    ) {
        $wpdb->delete( $table, [ 'id' => intval( $_GET['er_delete'] ) ] );
        echo '<div class="notice notice-success is-dismissible"><p>Subscriber deleted.</p></div>';
    }

    // Bulk Delete
    if (
        isset( $_POST['bulk_delete'], $_POST['subscriber_ids'], $_POST['_wpnonce'] ) &&
        check_admin_referer( 'er_subscribers_action', '_wpnonce' )
    ) {
        $ids          = array_map( 'intval', (array) $_POST['subscriber_ids'] );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids ) );
        echo '<div class="notice notice-success is-dismissible"><p>Selected subscribers deleted.</p></div>';
    }

    $status_filter = sanitize_text_field( $_GET['status'] ?? 'all' );
    $where         = $status_filter !== 'all'
        ? $wpdb->prepare( 'WHERE status = %s', $status_filter )
        : '';
    $subscribers = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC" );
    $total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    $active      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );
    $pending     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
    $current_url = admin_url( 'admin.php?page=er-subscribers' );
    ?>
    <div class="wrap">
        <h1>Subscribers</h1>
        <ul class="subsubsub" role="navigation" aria-label="Filter subscribers by status">
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'all',     $current_url ) ); ?>" <?php echo $status_filter === 'all'     ? 'aria-current="page"' : ''; ?>>All <span class="count">(<?php echo $total; ?>)</span></a></li> |
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'active',  $current_url ) ); ?>" <?php echo $status_filter === 'active'  ? 'aria-current="page"' : ''; ?>>Active <span class="count">(<?php echo $active; ?>)</span></a></li> |
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'pending', $current_url ) ); ?>" <?php echo $status_filter === 'pending' ? 'aria-current="page"' : ''; ?>>Pending <span class="count">(<?php echo $pending; ?>)</span></a></li>
        </ul>

        <?php if ( empty( $subscribers ) ) : ?>
            <p>No subscribers found.</p>
        <?php else : ?>
            <form method="post">
                <?php wp_nonce_field( 'er_subscribers_action', '_wpnonce' ); ?>
                <table class="wp-list-table widefat fixed striped" role="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="er-select-all" aria-label="Select all"></th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Subscribed</th>
                            <th>Last Send</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $subscribers as $sub ) :
                            $delete_url = wp_nonce_url(
                                add_query_arg( [ 'page' => 'er-subscribers', 'er_delete' => $sub->id ], admin_url( 'admin.php' ) ),
                                'er_delete_subscriber'
                            ); ?>
                            <tr>
                                <td><input type="checkbox" name="subscriber_ids[]" value="<?php echo esc_attr( $sub->id ); ?>" aria-label="Select <?php echo esc_attr( $sub->email ); ?>"></td>
                                <td><?php echo esc_html( $sub->email ); ?></td>
                                <td><?php echo esc_html( ucfirst( $sub->status ) ); ?></td>
                                <!-- created_at is stored by MySQL in UTC, shown in site time -->
                                <td><?php echo esc_html( wp_date( 'F j, Y H:i', strtotime( $sub->created_at . ' UTC' ) ) ); ?></td>
                                <td>
                                    <?php if ( ! empty( $sub->last_send_at ) ) : ?>
                                        <?php if ( $sub->last_send_status === 'sent' ) : ?>
                                            <span style="color: green;">&#10003; Sent</span><br>
                                        <?php else : ?>
                                            <span style="color: red;">&#10007; Failed</span><br>
                                            <?php if ( ! empty( $sub->last_post_title ) ) echo esc_html( $sub->last_post_title ) . '<br>'; ?>
                                            <?php echo esc_html( date( 'F j, Y H:i', strtotime( $sub->last_send_at ) ) ); ?>
                                            <?php if ( ! empty( $sub->last_send_error ) ) : ?>
                                                <br><small style="color: red;"><?php echo esc_html( $sub->last_send_error ); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><a href="<?php echo esc_url( $delete_url ); ?>" aria-label="Delete <?php echo esc_attr( $sub->email ); ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <br>
                <button type="submit" name="bulk_delete" class="button button-primary">Delete Selected</button>
            </form>
            <script>
            document.getElementById( 'er-select-all' ).addEventListener( 'change', function () {
                document.querySelectorAll( 'input[name="subscriber_ids[]"]' ).forEach( cb => cb.checked = this.checked );
            } );
            </script>
        <?php endif; ?>
    </div>
    <?php
}
