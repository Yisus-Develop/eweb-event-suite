<?php
namespace MCES\Notifications;

if (!defined('ABSPATH')) exit;

use MCES\Data\Repositories\EventsRepo;
use MCES\Data\Repositories\QueueRepo;

class ChangeDetector {

  const CPT               = 'lp_evento';
  const SNAP_META         = '_mces_event_snapshot_v1';
  const HASH_META         = '_mces_event_hash_v1';
  const PREV_START_META   = '_mces_prev_start';
  const PREV_VENUE_META   = '_mces_prev_venue';
  const PREV_COUNTRY_META = '_mces_prev_country';

  public static function hooks() {
    add_action('save_post_' . self::CPT, [__CLASS__, 'on_save'], 20, 3);
    add_action('trash_' . self::CPT,       [__CLASS__, 'on_trash'],   10, 1);
    add_action('untrash_' . self::CPT,     [__CLASS__, 'on_untrash'], 10, 1);
    add_action('deleted_post',             [__CLASS__, 'on_deleted'], 10, 1);
  }

  public static function on_save($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if ($post->post_type !== self::CPT) return;

    $snap = EventsRepo::build_snapshot($post_id);
    if (!$snap) return;

    // valores previos
    $prev_hash    = (string) get_post_meta($post_id, self::HASH_META, true);
    $prev_start   = (string) get_post_meta($post_id, self::PREV_START_META, true);
    $prev_venue   = (string) get_post_meta($post_id, self::PREV_VENUE_META, true);
    $prev_country = (string) get_post_meta($post_id, self::PREV_COUNTRY_META, true);

    // hash actual
    $hash = md5(wp_json_encode($snap));

    // detectar tipo de cambio específico
    $change_type = null;
    $diff = [];

    if ($prev_start !== '' && $prev_start !== (string)$snap['event_start']) {
      $change_type = 'reschedule';
      $diff['event_start'] = ['from' => $prev_start, 'to' => (string)$snap['event_start']];
    }

    if ($prev_venue !== '' && $prev_venue !== (string)$snap['lugarsede']) {
      // si ya hubo reschedule, complementa; si no, venue_change
      $diff['lugarsede'] = ['from' => $prev_venue, 'to' => (string)$snap['lugarsede']];
      if (!$change_type) $change_type = 'venue_change';
    }

    if ($prev_country !== '' && $prev_country !== (string)$snap['country_slug']) {
      $diff['country_slug'] = ['from' => $prev_country, 'to' => (string)$snap['country_slug']];
      if (!$change_type) $change_type = 'moved_country';
    }

    // si no hubo específicos, decidir create/update por hash
    if (!$change_type) {
      $change_type = $prev_hash ? (($hash !== $prev_hash) ? 'update' : null) : 'create';
    }

    // persistir metas clave (siempre)
    update_post_meta($post_id, self::HASH_META,        $hash);
    update_post_meta($post_id, self::SNAP_META,        $snap);
    update_post_meta($post_id, self::PREV_START_META,  (string)$snap['event_start']);
    update_post_meta($post_id, self::PREV_VENUE_META,  (string)$snap['lugarsede']);
    update_post_meta($post_id, self::PREV_COUNTRY_META,(string)$snap['country_slug']);

    // encolar solo si es visible (publish|future) y tenemos cambio
    if ($change_type && in_array($post->post_status, ['publish','future'], true)) {
      self::enqueue($change_type, $post_id, $snap['country_slug'] ?: 'global', $snap, $diff);
    }
  }

  public static function on_trash($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== self::CPT) return;

    $snap = get_post_meta($post_id, self::SNAP_META, true);
    if (!$snap) $snap = EventsRepo::build_snapshot($post_id);

    self::enqueue('trash', $post_id, $snap['country_slug'] ?? 'global', $snap ?: [], [
      'post_status' => ['from' => $post->post_status, 'to' => 'trash']
    ]);
  }

  public static function on_untrash($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== self::CPT) return;

    $snap = EventsRepo::build_snapshot($post_id);
    // restablecer metas
    update_post_meta($post_id, self::HASH_META,         md5(wp_json_encode($snap)));
    update_post_meta($post_id, self::SNAP_META,         $snap);
    update_post_meta($post_id, self::PREV_START_META,   (string)$snap['event_start']);
    update_post_meta($post_id, self::PREV_VENUE_META,   (string)$snap['lugarsede']);
    update_post_meta($post_id, self::PREV_COUNTRY_META, (string)$snap['country_slug']);

    self::enqueue('restore', $post_id, $snap['country_slug'] ?? 'global', $snap ?: [], [
      'post_status' => ['from' => 'trash', 'to' => $post->post_status]
    ]);
  }

  public static function on_deleted($post_id) {
    // Puede no existir ya el post: usa snapshot previo si existe
    $snap = get_post_meta($post_id, self::SNAP_META, true);
    $country = is_array($snap) && !empty($snap['country_slug']) ? $snap['country_slug'] : 'global';

    self::enqueue('delete', $post_id, $country, $snap ?: [], ['removed' => ['*']]);

    // limpia metas
    delete_post_meta($post_id, self::SNAP_META);
    delete_post_meta($post_id, self::HASH_META);
    delete_post_meta($post_id, self::PREV_START_META);
    delete_post_meta($post_id, self::PREV_VENUE_META);
    delete_post_meta($post_id, self::PREV_COUNTRY_META);
  }

  /** Helper encolado estándar */
  protected static function enqueue(string $type, int $post_id, string $country, array $snap, array $diff = []) {
    $payload = [
      'event_type' => $type,              // create|update|reschedule|venue_change|moved_country|trash|restore|delete
      'post_id'    => $post_id,
      'country'    => $country ?: 'global',
      'when'       => current_time('mysql'),
      'diff'       => $diff,
      'data'       => $snap,
    ];
    QueueRepo::enqueue_for_country($country ?: 'global', $payload);
  }
}
