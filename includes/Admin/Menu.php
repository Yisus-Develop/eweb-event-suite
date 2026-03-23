<?php
namespace MCES\Admin;

if ( ! defined('ABSPATH') ) exit;

class Menu {
    const SLUG_MAIN   = 'mces';
    const SLUG_QUEUE  = 'mces-queue';
    const SLUG_OUTBOX = 'mces-outbox';
    const PER_PAGE    = 20;

    /** Hook registry */
    public static function hooks(){
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_head', [__CLASS__, 'admin_css']);

        // Export CSV (Subscribers)
        add_action('admin_post_mces_export_csv', [__CLASS__, 'handle_export_csv']);

        // Actions (mutating) + nonce
        add_action('admin_post_mces_subscriber_action', [__CLASS__, 'handle_subscriber_action']);
        add_action('admin_post_mces_queue_action',      [__CLASS__, 'handle_queue_action']);
        add_action('admin_post_mces_outbox_action',     [__CLASS__, 'handle_outbox_action']);
    }

    /** Admin menu */
    public static function register_menu(){
        add_menu_page(
            __( 'Event Alerts', 'mc-event-suite' ),
            __( 'Event Alerts', 'mc-event-suite' ),
            'manage_options',
            self::SLUG_MAIN,
            [__CLASS__, 'render_subscribers'],
            'dashicons-email-alt2',
            58
        );

        add_submenu_page(
            self::SLUG_MAIN,
            __( 'Subscribers', 'mc-event-suite' ),
            __( 'Subscribers', 'mc-event-suite' ),
            'manage_options',
            self::SLUG_MAIN,
            [__CLASS__, 'render_subscribers']
        );

        add_submenu_page(
            self::SLUG_MAIN,
            __( 'Queue', 'mc-event-suite' ),
            __( 'Queue', 'mc-event-suite' ),
            'manage_options',
            self::SLUG_QUEUE,
            [__CLASS__, 'render_queue']
        );

        add_submenu_page(
            self::SLUG_MAIN,
            __( 'Outbox', 'mc-event-suite' ),
            __( 'Outbox', 'mc-event-suite' ),
            'manage_options',
            self::SLUG_OUTBOX,
            [__CLASS__, 'render_outbox']
        );
    }

    /* ========== Helpers comunes ========== */

    protected static function h($str){ return esc_html( (string) $str ); }

    protected static function print_admin_notices(){
        if (!empty($_GET['mces_msg'])){
            $msg = sanitize_text_field(wp_unslash($_GET['mces_msg']));
            echo '<div class="notice notice-success is-dismissible"><p>' . self::h($msg) . '</p></div>';
        }
    }

    protected static function pagination($total, $page, $per_page, $base_url){
        $total_pages = (int) ceil(max(0, (int)$total) / max(1, (int)$per_page));
        if ($total_pages <= 1) return;
        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ($p = 1; $p <= $total_pages; $p++){
            $url = add_query_arg('paged', $p, $base_url);
            $cls = $p === $page ? ' class="page-numbers current"' : ' class="page-numbers"';
            echo '<a' . $cls . ' href="' . esc_url($url) . '">' . self::h($p) . '</a> ';
        }
        echo '</div></div>';
    }

    protected static function get_country_options(){
        global $wpdb;
        $table = $wpdb->prefix . 'mc_event_alerts';
        $rows  = $wpdb->get_col( "SELECT DISTINCT country_slug FROM {$table} WHERE country_slug <> '' ORDER BY country_slug ASC" );
        return array_filter( array_map('sanitize_text_field', (array)$rows) );
    }

    /* ========== CSS Admin ========== */
    public static function admin_css(){
        if (!isset($_GET['page'])) return;
        $page = sanitize_key($_GET['page']);
        if (!in_array($page, [self::SLUG_MAIN, self::SLUG_QUEUE, self::SLUG_OUTBOX], true)) return;

        echo '<style>
        .mces-table{ table-layout:fixed; }
        .mces-ellipsis{ display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mces-nowrap{ white-space:nowrap; }
        .mces-mono{ font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; }

        /* Subscribers */
        .mces-subs th:nth-child(1), .mces-subs td:nth-child(1){ width:60px; }    /* ID */
        .mces-subs th:nth-child(2), .mces-subs td:nth-child(2){ width:300px; }   /* Email */
        .mces-subs th:nth-child(3), .mces-subs td:nth-child(3){ width:100px; }   /* Country */
        .mces-subs th:nth-child(4), .mces-subs td:nth-child(4){ width:80px; }    /* Lang */
        .mces-subs th:nth-child(5), .mces-subs td:nth-child(5){ width:140px; }   /* IP */
        .mces-subs th:nth-child(6), .mces-subs td:nth-child(6){ width:170px; }   /* Consent */
        .mces-subs th:nth-child(7), .mces-subs td:nth-child(7){ width:140px; }   /* Verified */
        .mces-subs th:nth-child(8), .mces-subs td:nth-child(8){ width:140px; }   /* Unsub */
        .mces-subs th:nth-child(9), .mces-subs td:nth-child(9){ width:220px; }   /* Actions */

        /* Queue */
        .mces-queue th:nth-child(1), .mces-queue td:nth-child(1){ width:60px; }   /* ID */
        .mces-queue th:nth-child(2), .mces-queue td:nth-child(2){ width:90px; }   /* Event */
        .mces-queue th:nth-child(3), .mces-queue td:nth-child(3){ width:90px; }   /* Country */
        .mces-queue th:nth-child(4), .mces-queue td:nth-child(4){ width:140px; }  /* Action */
        .mces-queue th:nth-child(5), .mces-queue td:nth-child(5){ width:170px; }  /* Scheduled */
        .mces-queue th:nth-child(6), .mces-queue td:nth-child(6){ width:170px; }  /* Sent */
        .mces-queue th:nth-child(7), .mces-queue td:nth-child(7){ width:200px; }  /* Created */
        .mces-queue th:nth-child(8), .mces-queue td:nth-child(8){ width:200px; }  /* Actions */

        /* Outbox */
        .mces-outbox th:nth-child(1), .mces-outbox td:nth-child(1){ width:60px; }   /* ID */
        .mces-outbox th:nth-child(2), .mces-outbox td:nth-child(2){ width:140px; }  /* Operation */
        .mces-outbox th:nth-child(3), .mces-outbox td:nth-child(3){ width:120px; }  /* Status */
        .mces-outbox th:nth-child(6), .mces-outbox td:nth-child(6){ width:170px; }  /* Created */
        .mces-outbox th:nth-child(7), .mces-outbox td:nth-child(7){ width:170px; }  /* Processed */
        .mces-outbox th:nth-child(8), .mces-outbox td:nth-child(8){ width:200px; }  /* Actions */
        </style>';
    }

    /* ========== SUSCRIPTORES (read + actions) ========== */
    public static function render_subscribers(){
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;
        self::print_admin_notices();

        $table = $wpdb->prefix . 'mc_event_alerts';
        $per   = self::PER_PAGE;
        $page  = max(1, (int)($_GET['paged'] ?? 1));
        $off   = ($page - 1) * $per;

        $search  = trim((string)($_GET['s'] ?? ''));
        $country = trim((string)($_GET['country'] ?? ''));

        $where = 'WHERE 1=1';
        $params = [];
        if ($search !== ''){
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (email LIKE %s)';
            $params[] = $like;
        }
        if ($country !== ''){
            $where .= ' AND (country_slug = %s)';
            $params[] = $country;
        }

        $sql_total = $wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where}", $params);
        $total     = (int) $wpdb->get_var($sql_total);

        $sql_rows = $wpdb->prepare(
            "SELECT id,email,country_slug,lang,consent_ip,consent_at,verified_at,unsubscribed_at
             FROM {$table} {$where}
             ORDER BY id DESC LIMIT %d OFFSET %d",
            array_merge($params, [$per, $off])
        );
        $rows = (array) $wpdb->get_results($sql_rows, ARRAY_A);

        $base = menu_page_url(self::SLUG_MAIN, false);
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=mces_export_csv&type=subscribers'), 'mces_export_csv');

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . self::h(__('Subscribers', 'mc-event-suite')) . '</h1>';
        echo '&nbsp; <a class="button" href="' . esc_url($export_url) . '">' . self::h(__('Export CSV', 'mc-event-suite')) . '</a>';
        echo '<hr class="wp-header-end">';

        // filtros
        echo '<form method="get" style="margin-bottom:10px;">';
        echo '<input type="hidden" name="page" value="' . self::h(self::SLUG_MAIN) . '">';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="mces-search">' . self::h(__('Search Email', 'mc-event-suite')) . '</label>';
        echo '<input id="mces-search" type="search" name="s" value="' . self::h($search) . '" placeholder="email@dominio..."> ';
        echo '<select name="country">';
        echo '<option value="">' . self::h(__('All countries', 'mc-event-suite')) . '</option>';
        foreach ( self::get_country_options() as $opt ){
            $sel = selected($country, $opt, false);
            echo '<option value="' . self::h($opt) . '" ' . $sel . '>' . self::h(strtoupper($opt)) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button">' . self::h(__('Filter', 'mc-event-suite')) . '</button>';
        echo '</p>';
        echo '</form>';

        echo '<table class="widefat fixed striped mces-table mces-subs">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Email</th><th>' . self::h(__('Country', 'mc-event-suite')) . '</th><th>' . self::h(__('Lang', 'mc-event-suite')) . '</th><th>IP</th><th>' . self::h(__('Consent at', 'mc-event-suite')) . '</th><th>' . self::h(__('Verified', 'mc-event-suite')) . '</th><th>' . self::h(__('Unsubscribed', 'mc-event-suite')) . '</th><th>' . self::h(__('Actions', 'mc-event-suite')) . '</th>';
        echo '</tr></thead><tbody>';

        if (!$rows){
            echo '<tr><td colspan="9">' . self::h(__('No subscribers found.', 'mc-event-suite')) . '</td></tr>';
        } else {
            $dash = '–';
            foreach ($rows as $r){
                $email = (string)($r['email'] ?? '');
                $country = (string)($r['country_slug'] ?? '');
                $lang = (string)($r['lang'] ?? '');
                $ip = (string)($r['consent_ip'] ?? '');
                $consent = (string)($r['consent_at'] ?? '');
                $verified = (string)($r['verified_at'] ?? '');
                $unsub = (string)($r['unsubscribed_at'] ?? '');

                $verify_url = wp_nonce_url(
                    admin_url('admin-post.php?action=mces_subscriber_action&op=verify&id=' . $r['id']),
                    'mces_subscriber_action_' . $r['id']
                );
                $unsubscribe_url = wp_nonce_url(
                    admin_url('admin-post.php?action=mces_subscriber_action&op=unsubscribe&id=' . $r['id']),
                    'mces_subscriber_action_' . $r['id']
                );
                $resubscribe_url = wp_nonce_url(
                    admin_url('admin-post.php?action=mces_subscriber_action&op=resubscribe&id=' . $r['id']),
                    'mces_subscriber_action_' . $r['id']
                );
                $delete_url = wp_nonce_url(
                    admin_url('admin-post.php?action=mces_subscriber_action&op=delete&id=' . $r['id']),
                    'mces_subscriber_action_' . $r['id']
                );

                $actions = [];
                if (empty($verified)) {
                    $actions[] = '<a href="'.esc_url($verify_url).'">'.self::h(__('Verify','mc-event-suite')).'</a>';
                }
                if (empty($unsub)) {
                    $actions[] = '<a href="'.esc_url($unsubscribe_url).'">'.self::h(__('Unsubscribe','mc-event-suite')).'</a>';
                } else {
                    $actions[] = '<a href="'.esc_url($resubscribe_url).'">'.self::h(__('Resubscribe','mc-event-suite')).'</a>';
                }
                $actions[] = '<a href="'.esc_url($delete_url).'" onclick="return confirm(\''.esc_js(__('Delete permanently?', 'mc-event-suite')).'\');">'.self::h(__('Delete','mc-event-suite')).'</a>';

                echo '<tr>';
                echo '<td>' . self::h($r['id']) . '</td>';
                echo '<td><span class="mces-ellipsis" title="' . self::h($email) . '">' . self::h($email) . '</span></td>';
                echo '<td class="mces-nowrap">' . self::h($country !== '' ? strtoupper($country) : $dash) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($lang !== '' ? strtolower($lang) : $dash) . '</td>';
                echo '<td class="mces-mono mces-nowrap">' . self::h($ip !== '' ? $ip : $dash) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($consent !== '' ? $consent : $dash) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($verified !== '' ? $verified : $dash) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($unsub !== '' ? $unsub : $dash) . '</td>';
                echo '<td class="mces-nowrap">'. implode(' | ', $actions) .'</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        self::pagination($total, $page, $per, $base);
        echo '</div>';
    }

    /* ========== QUEUE (read + actions) ========== */
    public static function render_queue(){
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;
        self::print_admin_notices();

        $table = $wpdb->prefix . 'mc_event_alerts_queue';
        $per   = self::PER_PAGE;
        $page  = max(1, (int)($_GET['paged'] ?? 1));
        $off   = ($page - 1) * $per;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $rows  = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, event_id, country_slug, action, scheduled_for, sent_at, created_at
                 FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
                $per, $off
            ), ARRAY_A
        );

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . self::h(__('Queue', 'mc-event-suite')) . '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<table class="widefat fixed striped mces-table mces-queue">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>' . self::h(__('Event', 'mc-event-suite')) . '</th><th>' . self::h(__('Country', 'mc-event-suite')) . '</th><th>' . self::h(__('Action', 'mc-event-suite')) . '</th><th>' . self::h(__('Scheduled for', 'mc-event-suite')) . '</th><th>' . self::h(__('Sent at', 'mc-event-suite')) . '</th><th>' . self::h(__('Created', 'mc-event-suite')) . '</th><th>' . self::h(__('Actions', 'mc-event-suite')) . '</th>';
        echo '</tr></thead><tbody>';

        if (!$rows){
            echo '<tr><td colspan="8">' . self::h(__('No rows.', 'mc-event-suite')) . '</td></tr>';
        } else {
            $dash = '–';
            foreach ($rows as $r){
                $mark_url = wp_nonce_url(
                    admin_url('admin-post.php?action=mces_queue_action&op=mark_sent&id=' . $r['id']),
                    'mces_queue_action_' . $r['id']
                );
                $del_url = wp_nonce_url(
                    admin_url('admin-post.php?action=mces_queue_action&op=delete&id=' . $r['id']),
                    'mces_queue_action_' . $r['id']
                );

                $qa = [];
                if (empty($r['sent_at'])) {
                    $qa[] = '<a href="'.esc_url($mark_url).'">'.self::h(__('Mark sent','mc-event-suite')).'</a>';
                }
                $qa[] = '<a href="'.esc_url($del_url).'" onclick="return confirm(\''.esc_js(__('Delete this queue row?', 'mc-event-suite')).'\');">'.self::h(__('Delete','mc-event-suite')).'</a>';

                echo '<tr>';
                echo '<td>' . self::h($r['id']) . '</td>';
                echo '<td>#' . self::h($r['event_id']) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($r['country_slug'] ?: $dash) . '</td>';
                echo '<td>' . self::h($r['action'] ?: $dash) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($r['scheduled_for'] ?: $dash) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($r['sent_at'] ?: $dash) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($r['created_at'] ?: $dash) . '</td>';
                echo '<td class="mces-nowrap">'. implode(' | ', $qa) .'</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        self::pagination($total, $page, $per, menu_page_url(self::SLUG_QUEUE, false));
        echo '</div>';
    }

    /* ========== OUTBOX (read + actions) ========== */
    public static function render_outbox(){
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;
        self::print_admin_notices();

        $table = $wpdb->prefix . 'mc_event_sync_outbox';
        $per   = self::PER_PAGE;
        $page  = max(1, (int)($_GET['paged'] ?? 1));
        $off   = ($page - 1) * $per;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $rows  = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, op_type, status, payload, error_message, created_at, processed_at
                 FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
                $per, $off
            ), ARRAY_A
        );

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . self::h(__('Outbox', 'mc-event-suite')) . '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<table class="widefat fixed striped mces-table mces-outbox">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>' . self::h(__('Operation', 'mc-event-suite')) . '</th><th>' . self::h(__('Status', 'mc-event-suite')) . '</th><th>' . self::h(__('Payload', 'mc-event-suite')) . '</th><th>' . self::h(__('Error', 'mc-event-suite')) . '</th><th>' . self::h(__('Created', 'mc-event-suite')) . '</th><th>' . self::h(__('Processed', 'mc-event-suite')) . '</th><th>' . self::h(__('Actions', 'mc-event-suite')) . '</th>';
        echo '</tr></thead><tbody>';

        if (!$rows){
            echo '<tr><td colspan="8">' . self::h(__('No rows.', 'mc-event-suite')) . '</td></tr>';
        } else {
            foreach ($rows as $r){
                $payload = (string) ($r['payload'] ?? '');
                $payload = strlen($payload) > 160 ? substr($payload, 0, 157) . '…' : $payload;

                $retry_url = wp_nonce_url(
                    admin_url('admin-post.php?action=mces_outbox_action&op=retry&id=' . $r['id']),
                    'mces_outbox_action_' . $r['id']
                );
                $del_url = wp_nonce_url(
                    admin_url('admin-post.php?action=mces_outbox_action&op=delete&id=' . $r['id']),
                    'mces_outbox_action_' . $r['id']
                );

                $oa = [];
                if (strtoupper((string)$r['status']) !== 'PENDING') {
                    $oa[] = '<a href="'.esc_url($retry_url).'">'.self::h(__('Retry','mc-event-suite')).'</a>';
                }
                $oa[] = '<a href="'.esc_url($del_url).'" onclick="return confirm(\''.esc_js(__('Delete this outbox row?', 'mc-event-suite')).'\');">'.self::h(__('Delete','mc-event-suite')).'</a>';

                echo '<tr>';
                echo '<td>' . self::h($r['id']) . '</td>';
                echo '<td>' . self::h($r['op_type']) . '</td>';
                echo '<td>' . self::h($r['status']) . '</td>';
                echo '<td><code class="mces-ellipsis" title="' . self::h($r['payload']) . '">' . self::h($payload) . '</code></td>';
                echo '<td class="mces-ellipsis" title="' . self::h($r['error_message']) . '">' . self::h($r['error_message']) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($r['created_at']) . '</td>';
                echo '<td class="mces-nowrap">' . self::h($r['processed_at']) . '</td>';
                echo '<td class="mces-nowrap">'. implode(' | ', $oa) .'</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        self::pagination($total, $page, $per, menu_page_url(self::SLUG_OUTBOX, false));
        echo '</div>';
    }

    /* ========== Export CSV (Subscribers) ========== */
    public static function handle_export_csv(){
        if ( ! current_user_can('manage_options') ) wp_die('forbidden');
        check_admin_referer('mces_export_csv');

        $type = sanitize_key($_GET['type'] ?? 'subscribers');
        if ($type !== 'subscribers'){
            wp_redirect( add_query_arg('mces_msg', rawurlencode(__('Not implemented yet for this list.', 'mc-event-suite')), menu_page_url(self::SLUG_MAIN, false)) );
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mc_event_alerts';

        // Reusar filtros de la vista si vienen
        $search  = trim((string)($_GET['s'] ?? ''));
        $country = trim((string)($_GET['country'] ?? ''));
        $where = 'WHERE 1=1';
        $params = [];
        if ($search !== ''){
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (email LIKE %s)';
            $params[] = $like;
        }
        if ($country !== ''){
            $where .= ' AND (country_slug = %s)';
            $params[] = $country;
        }

        $sql = $wpdb->prepare(
            "SELECT id,email,country_slug,lang,consent_ip,consent_at,verified_at,unsubscribed_at
             FROM {$table} {$where} ORDER BY id DESC",
            $params
        );
        $rows = (array) $wpdb->get_results($sql, ARRAY_A);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=mces-subscribers-' . date('Ymd-His') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','email','country_slug','lang','consent_ip','consent_at','verified_at','unsubscribed_at']);
        foreach ($rows as $r){ fputcsv($out, $r); }
        fclose($out);
        exit;
    }

    /* ========== Handlers de acciones (mutaciones) ========== */

    // Subscribers: verify / unsubscribe / resubscribe / delete
    public static function handle_subscriber_action(){
        if ( ! current_user_can('manage_options') ) wp_die('forbidden');

        $op = sanitize_key($_GET['op'] ?? '');
        $id = (int) ($_GET['id'] ?? 0);
        $nonce = (string) ($_GET['_wpnonce'] ?? '');

        if (!$id || !in_array($op, ['verify','unsubscribe','resubscribe','delete'], true)) {
            wp_redirect( add_query_arg('mces_msg', rawurlencode(__('Invalid request', 'mc-event-suite')), menu_page_url(self::SLUG_MAIN, false)) );
            exit;
        }
        if (! wp_verify_nonce($nonce, 'mces_subscriber_action_'.$id)) {
            wp_redirect( add_query_arg('mces_msg', rawurlencode(__('Nonce failed', 'mc-event-suite')), menu_page_url(self::SLUG_MAIN, false)) );
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mc_event_alerts';
        $msg = '';
        $ok  = false;

        if ($op === 'verify'){
            $ok = (false !== $wpdb->update($table, ['verified_at' => current_time('mysql')], ['id' => $id], ['%s'], ['%d']));
            $msg = $ok ? __('Subscriber verified', 'mc-event-suite') : __('Could not verify', 'mc-event-suite');
        } elseif ($op === 'unsubscribe'){
            $ok = (false !== $wpdb->update($table, ['unsubscribed_at' => current_time('mysql')], ['id' => $id], ['%s'], ['%d']));
            $msg = $ok ? __('Unsubscribed', 'mc-event-suite') : __('Could not unsubscribe', 'mc-event-suite');
        } elseif ($op === 'resubscribe'){
            // wpdb->update no maneja bien NULL con formatos; mejor query directa:
            $ok = ( false !== $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET unsubscribed_at = NULL WHERE id = %d",
                $id
            ) ) );
            $msg = $ok ? __('Resubscribed', 'mc-event-suite') : __('Could not resubscribe', 'mc-event-suite');
        } elseif ($op === 'delete'){
            $ok = (false !== $wpdb->delete($table, ['id' => $id], ['%d']));
            $msg = $ok ? __('Deleted', 'mc-event-suite') : __('Could not delete', 'mc-event-suite');
        }

        $redir = menu_page_url(self::SLUG_MAIN, false);
        wp_redirect( add_query_arg('mces_msg', rawurlencode($msg), $redir) );
        exit;
    }

    // Queue: mark_sent / delete
    public static function handle_queue_action(){
        if ( ! current_user_can('manage_options') ) wp_die('forbidden');

        $op = sanitize_key($_GET['op'] ?? '');
        $id = (int) ($_GET['id'] ?? 0);
        $nonce = (string) ($_GET['_wpnonce'] ?? '');

        if (!$id || !in_array($op, ['mark_sent','delete'], true)) {
            wp_redirect( add_query_arg('mces_msg', rawurlencode(__('Invalid request', 'mc-event-suite')), menu_page_url(self::SLUG_QUEUE, false)) );
            exit;
        }
        if (! wp_verify_nonce($nonce, 'mces_queue_action_'.$id)) {
            wp_redirect( add_query_arg('mces_msg', rawurlencode(__('Nonce failed', 'mc-event-suite')), menu_page_url(self::SLUG_QUEUE, false)) );
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mc_event_alerts_queue';
        $msg = '';
        $ok  = false;

        if ($op === 'mark_sent'){
            $ok = (false !== $wpdb->update($table, ['sent_at' => current_time('mysql')], ['id' => $id], ['%s'], ['%d']));
            $msg = $ok ? __('Marked as sent', 'mc-event-suite') : __('Could not mark as sent', 'mc-event-suite');
        } elseif ($op === 'delete'){
            $ok = (false !== $wpdb->delete($table, ['id' => $id], ['%d']));
            $msg = $ok ? __('Deleted', 'mc-event-suite') : __('Could not delete', 'mc-event-suite');
        }

        $redir = menu_page_url(self::SLUG_QUEUE, false);
        wp_redirect( add_query_arg('mces_msg', rawurlencode($msg), $redir) );
        exit;
    }

    // Outbox: retry / delete
    public static function handle_outbox_action(){
        if ( ! current_user_can('manage_options') ) wp_die('forbidden');

        $op = sanitize_key($_GET['op'] ?? '');
        $id = (int) ($_GET['id'] ?? 0);
        $nonce = (string) ($_GET['_wpnonce'] ?? '');

        if (!$id || !in_array($op, ['retry','delete'], true)) {
            wp_redirect( add_query_arg('mces_msg', rawurlencode(__('Invalid request', 'mc-event-suite')), menu_page_url(self::SLUG_OUTBOX, false)) );
            exit;
        }
        if (! wp_verify_nonce($nonce, 'mces_outbox_action_'.$id)) {
            wp_redirect( add_query_arg('mces_msg', rawurlencode(__('Nonce failed', 'mc-event-suite')), menu_page_url(self::SLUG_OUTBOX, false)) );
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mc_event_sync_outbox';
        $msg = '';
        $ok  = false;

        if ($op === 'retry'){
            // Usamos query directa para setear NULLs correctamente
            $ok = ( false !== $wpdb->query( $wpdb->prepare(
                "UPDATE {$table}
                 SET status = %s, processed_at = NULL, error_message = NULL
                 WHERE id = %d",
                'PENDING', $id
            ) ) );
            $msg = $ok ? __('Queued for retry', 'mc-event-suite') : __('Could not queue retry', 'mc-event-suite');
        } elseif ($op === 'delete'){
            $ok = (false !== $wpdb->delete($table, ['id' => $id], ['%d']));
            $msg = $ok ? __('Deleted', 'mc-event-suite') : __('Could not delete', 'mc-event-suite');
        }

        $redir = menu_page_url(self::SLUG_OUTBOX, false);
        wp_redirect( add_query_arg('mces_msg', rawurlencode($msg), $redir) );
        exit;
    }
}
