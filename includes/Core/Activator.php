<?php
namespace MCES\Core;
use MCES\Data\DB;

class Activator {
  public static function activate(){
    DB::install(); // crea tablas
    if (!wp_next_scheduled('mces_cron_tick')) {
      wp_schedule_event(time()+60, 'five_minutes', 'mces_cron_tick');
    }
    flush_rewrite_rules();
  }
}
