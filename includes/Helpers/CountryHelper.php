<?php
namespace MCES\Helpers;

if (!defined('ABSPATH')) exit;

class CountryHelper {
  protected static $cache = [];

  /** Detecta el slug ISO-2 del país (por ?pais=xx o taxonomía 'country'). */
  public static function detect_slug(): string {
    if (isset(self::$cache['slug'])) return self::$cache['slug'];

    // 1) Query string ?pais=xx
    if (!empty($_GET['pais'])) {
      $slug = strtolower(sanitize_title(wp_unslash($_GET['pais'])));
      self::$cache['slug'] = $slug;
      return $slug;
    }

    // 2) Contexto del post actual con taxonomía 'country'
    $slug = '';
    $post  = get_queried_object();
    $post_id = ( $post && isset($post->ID) ) ? (int) $post->ID : 0;
    if (!$post_id && function_exists('get_the_ID') && get_the_ID()) {
      $post_id = (int) get_the_ID();
    }

    if ($post_id) {
      $terms = get_the_terms($post_id, 'country');
      if ($terms && !is_wp_error($terms) && !empty($terms[0])) {
        $slug = sanitize_title($terms[0]->slug);
      }
    }

    self::$cache['slug'] = $slug;
    return $slug;
  }

  /** Devuelve el nombre (label) del término country desde un slug. */
  public static function label_from_slug(string $slug): string {
    if (!$slug) return '';
    if (isset(self::$cache['label_'.$slug])) return self::$cache['label_'.$slug];

    $term  = get_term_by('slug', $slug, 'country');
    $label = ($term && !is_wp_error($term)) ? $term->name : strtoupper($slug);
    self::$cache['label_'.$slug] = $label;
    return $label;
  }

  /** Atajo: retorna name/slug con fallback. */
  public static function get(string $format = 'name', string $fallback = 'Global'): string {
    $slug = self::detect_slug();

    if ($format === 'slug') {
      return $slug ?: '';
    }

    // format = name (default)
    if ($slug) {
      $label = self::label_from_slug($slug);
      return $label ?: $fallback;
    }

    return $fallback;
  }
}
