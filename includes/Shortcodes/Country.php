<?php
namespace MCES\Shortcodes;

use MCES\Helpers\CountryHelper;

if (!defined('ABSPATH')) exit;

class Country {
  public static function hooks() {
    add_shortcode('mces_country', [__CLASS__, 'render']);
  }

  /**
   * [mces_country format="name|slug" fallback="Global"]
   */
  public static function render($atts = []): string {
    $a = shortcode_atts([
      'format'   => 'name',   // 'name' o 'slug'
      'fallback' => 'Global', // texto si no hay país detectado
    ], $atts, 'mces_country');

    $format   = ($a['format'] === 'slug') ? 'slug' : 'name';
    $fallback = wp_kses_post($a['fallback']);

    $value = CountryHelper::get($format, $fallback);

    // Escapar según formato de salida (texto plano)
    return esc_html($value);
  }
}
