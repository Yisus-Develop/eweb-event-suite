<?php
namespace MCES\Core;

class Settings {
  const KEY = 'mces_settings';
  public static function hooks(){
    add_action('admin_init',[__CLASS__,'register']);
    add_action('admin_menu',[__CLASS__,'menu']);
    add_filter('cron_schedules', function($s){
      $s['five_minutes']=['interval'=>300,'display'=>'Every 5 minutes']; return $s;
    });
  }
  public static function register(){
    register_setting('mces', self::KEY);
    add_settings_section('mces_main','MC Event Suite','__return_false','mces');
    foreach ([
      'default_country' => 'País por defecto (slug ISO2, ej: mx)',
      'provider' => 'Proveedor activo (local_log | brevo)',
      'consolidation_minutes' => 'Consolidación de cambios (minutos)',
      'max_daily_notifications' => 'Máx notificaciones por día/usuario (0=sin límite)',
      'pause_sending' => 'Pausar envíos (1/0)'
    ] as $k=>$label){
      add_settings_field($k,$label,[__CLASS__,'field'], 'mces','mces_main',['key'=>$k]);
    }
  }
  public static function menu(){
    add_options_page('MC Event Suite','MC Event Suite','manage_options','mces',[__CLASS__,'render']);
  }
  public static function field($args){
    $opt = get_option(self::KEY, ['provider'=>'local_log','consolidation_minutes'=>5,'pause_sending'=>0]);
    $val = isset($opt[$args['key']]) ? esc_attr($opt[$args['key']]) : '';
    echo '<input type="text" name="'.self::KEY.'['.esc_attr($args['key']).']" value="'.$val.'" style="width:320px">';
  }
  public static function get($key, $default=null){
    $opt = get_option(self::KEY, []);
    return $opt[$key] ?? $default;
  }
  public static function render(){
    echo '<div class="wrap"><h1>MC Event Suite</h1><form method="post" action="options.php">';
    settings_fields('mces'); do_settings_sections('mces'); submit_button();
    echo '</form></div>';
  }
}
