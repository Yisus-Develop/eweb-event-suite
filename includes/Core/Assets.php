<?php
namespace MCES\Core;

class Assets {
  public static function hooks(){
    add_action('wp_enqueue_scripts',   [__CLASS__, 'enqueue_public']);
    add_action('admin_enqueue_scripts',[__CLASS__, 'enqueue_admin']);
  }

  public static function enqueue_public(){
    wp_enqueue_style('mces-public', MCES_URL . 'assets/public.css', [], MCES_VERSION);
    wp_enqueue_script('mces-public', MCES_URL . 'assets/public.js', ['jquery'], MCES_VERSION, true);

    wp_localize_script('mces-public', 'MCES_I18N', I18n::js_strings());

    wp_localize_script('mces-public', 'MCESVARS', [
      'ajax'     => admin_url('admin-ajax.php'),
      'nonce'    => wp_create_nonce('mces_form'),
      'now'      => time(),
      'locale' => get_locale(), // ej. es_ES
'country'=> \MCES\Helpers\CountryHelper::detect_slug(),
      'i18n'     => I18n::js_strings(),     // <- textos traducibles
    ]);
  }

  public static function enqueue_admin(){
    wp_enqueue_script('mces-admin', MCES_URL . 'assets/admin.js', [], MCES_VERSION, true);
  }

  /** Detecta país por: ?pais=xx → landing_paises → lp_evento → ajustes */
  private static function detect_country(){
    if (!empty($_GET['pais'])) {
      return sanitize_text_field($_GET['pais']);
    }

    global $post;
    if ($post && $post->post_type === 'landing_paises') {
      $terms = wp_get_post_terms($post->ID, 'country');
      if ($terms && !is_wp_error($terms) && !empty($terms[0]->slug)) {
        return $terms[0]->slug;
      }
    }
    if ($post && $post->post_type === 'lp_evento') {
      $terms = wp_get_post_terms($post->ID, 'country');
      if ($terms && !is_wp_error($terms) && !empty($terms[0]->slug)) {
        return $terms[0]->slug;
      }
    }

    return Settings::get('default_country', '');
  }
}
