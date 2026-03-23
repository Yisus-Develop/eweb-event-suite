<?php
namespace MCES\Data\Repositories;

if (!defined('ABSPATH')) exit;

class QueueRepo {

  protected static function table() {
    global $wpdb;
    return $wpdb->prefix . 'mc_event_alerts_queue';
  }

  /**
   * Encola payload segmentado por país.
   */
  public static function enqueue_for_country(string $country, array $payload) : bool {
    global $wpdb;
    $data = [
      'country_slug' => sanitize_text_field($country ?: 'global'),
      'change_type'  => sanitize_text_field($payload['event_type'] ?? 'update'),
      'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
      'scheduled_at' => current_time('mysql'),
      'sent_at'      => null,
      'attempts'     => 0,
      'last_error'   => '',
      'event_id'     => (int)($payload['post_id'] ?? 0),
    ];
    return (bool) $wpdb->insert(self::table(), $data, ['%s','%s','%s','%s','%s','%d','%s','%d']);
  }
}
