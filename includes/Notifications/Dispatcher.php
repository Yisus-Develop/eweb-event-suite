<?php
namespace MCES\Notifications;

if (!defined('ABSPATH')) exit;

use MCES\Providers\LocalLogProvider;

class Dispatcher {

  public static function hooks() {
    // Procesa cuando el cron dispare este hook
    add_action('mces_dispatcher_tick', [__CLASS__, 'process']);
  }

  /**
   * Procesa la cola agrupando por país y por ventana de consolidación (minutos).
   * Marca todas las filas del batch con sent_at (y suma attempts).
   */
  public static function process($limit = 200) {
    global $wpdb;

    $table = $wpdb->prefix . 'mc_event_alerts_queue';
    $now   = current_time('mysql');
    $window = (int) self::get_consolidation_minutes(); // p.ej. 10

    // Trae pendientes (sent_at IS NULL) hasta el límite
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE sent_at IS NULL
         AND scheduled_at <= %s
         ORDER BY scheduled_at ASC, id ASC
         LIMIT %d",
        $now, (int) $limit
      ),
      ARRAY_A
    );

    if (empty($rows)) return 0;

    // Agrupar por país y por bucket de tiempo
    $buckets = [];
    foreach ($rows as $r) {
      $country = $r['country_slug'] ?: 'global';
      $bucket  = self::bucket_key($r['scheduled_at'], $window);
      $buckets[$country][$bucket][] = $r;
    }

    $processed = 0;

    foreach ($buckets as $country => $groups) {
      foreach ($groups as $bucket => $items) {
        // Decodificar payloads para el log
        $payloads = array_map(function($r){
          $p = json_decode($r['payload_json'] ?? '{}', true);
          if (!is_array($p)) $p = [];
          return $p + ['_queue_id' => (int)$r['id']];
        }, $items);

        // MOCK de envío: registrar el batch localmente
        LocalLogProvider::publish('EVENT_NOTIF_BATCH', [
          'country'     => $country,
          'window_min'  => $window,
          'bucket'      => $bucket,
          'count'       => count($items),
          'first_sched' => $items[0]['scheduled_at'],
          'last_sched'  => $items[count($items)-1]['scheduled_at'],
          'payloads'    => $payloads,
        ]);

        // Marcar como enviados
        $ids = array_map(static fn($r) => (int)$r['id'], $items);
        if ($ids) {
          $placeholders = implode(',', array_fill(0, count($ids), '%d'));
          $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET sent_at = %s,
                 attempts = attempts + 1,
                 last_error = ''
             WHERE id IN ($placeholders)",
            array_merge([ current_time('mysql') ], $ids)
          );
          $wpdb->query($sql);
          $processed += count($ids);
        }
      }
    }

    return $processed;
  }

  /** Clave de bucket según ventana (minutos) */
  protected static function bucket_key(string $datetime, int $minutes) : string {
    $ts = strtotime($datetime);
    if ($minutes <= 1) return date('Y-m-d H:i', $ts);
    $minute = (int) date('i', $ts);
    $bucket_min = floor($minute / $minutes) * $minutes;
    return date('Y-m-d H:', $ts) . str_pad((string)$bucket_min, 2, '0', STR_PAD_LEFT);
  }

  /** Lee minutes desde Settings si existe; default 10 */
  protected static function get_consolidation_minutes() : int {
    if (class_exists('\MCES\Core\Settings') && method_exists('\MCES\Core\Settings', 'get')) {
      $v = (int) \MCES\Core\Settings::get('consolidation_minutes', 10);
      return $v > 0 ? $v : 10;
    }
    return 10;
  }
}
