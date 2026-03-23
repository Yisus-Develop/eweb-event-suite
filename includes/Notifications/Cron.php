<?php
namespace MCES\Notifications;

if (!defined('ABSPATH')) exit;

class Cron {

  const HOOK = 'mces_dispatcher_tick';

  public static function hooks() {
    // Intervalo personalizado (p. ej., cada 5 min)
    add_filter('cron_schedules', [__CLASS__, 'add_schedule']);

    // Programar si no existe
    add_action('init', [__CLASS__, 'ensure']);

    // Por si quieres permitir ejecutar manualmente desde una URL segura (opcional)
    // add_action('admin_post_mces_tick_now', [__CLASS__, 'tick_now']);
  }

  public static function add_schedule($schedules) {
    $every = self::get_interval_minutes(); // minutos
    $schedules['mces_every_n_min'] = [
      'interval' => max(60, $every * 60), // al menos 1 minuto
      'display'  => sprintf(__('MCES every %d minutes','mc-event-suite'), $every),
    ];
    return $schedules;
  }

  public static function ensure() {
    if (!wp_next_scheduled(self::HOOK)) {
      wp_schedule_event(time() + 60, 'mces_every_n_min', self::HOOK);
    }
  }

  // Opcional: endpoint para disparar el tick manual desde el navegador si estás logueado como admin
  public static function tick_now() {
    if (!current_user_can('manage_options')) wp_die('forbidden');
    do_action(self::HOOK);
    wp_safe_redirect(admin_url());
    exit;
  }

  protected static function get_interval_minutes() : int {
    if (class_exists('\MCES\Core\Settings') && method_exists('\MCES\Core\Settings', 'get')) {
      $v = (int) \MCES\Core\Settings::get('dispatcher_interval_minutes', 5);
      return $v > 0 ? $v : 5;
    }
    return 5;
  }
}


//add_action('admin_init', function(){ do_action('mces_dispatcher_tick'); });
