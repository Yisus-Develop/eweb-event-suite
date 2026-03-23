<?php
namespace MCES\Core;

class I18n {
  public static function load(){
    add_action('plugins_loaded', function(){
      load_plugin_textdomain('mc-event-suite', false, dirname(plugin_basename(MCES_FILE)).'/languages');
    });
  }

  public static function js_strings(){
    return [
      'success'   => __('✅ Suscripción realizada correctamente','mc-event-suite'),
      'error'     => __('Hubo un error. Intenta nuevamente.','mc-event-suite'),
      'close'     => __('Cerrar','mc-event-suite'),
      'loading'   => __('Procesando...','mc-event-suite'),
      'subscribe' => __('Suscribirme','mc-event-suite'),
      // aquí vas agregando todos los que necesites
    ];
  }
}
