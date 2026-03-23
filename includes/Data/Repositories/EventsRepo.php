<?php
namespace MCES\Data\Repositories;

if (!defined('ABSPATH')) exit;

class EventsRepo {

  /**
   * Snapshot normalizado del evento (única fuente de verdad para notificaciones).
   * Usa exclusivamente los ACF detectados en tu ZIP.
   */
  public static function build_snapshot(int $post_id) : array {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'lp_evento') return [];

    // País por taxonomía 'country' (slug principal)
    $country_terms = wp_get_post_terms($post_id, 'country', ['fields' => 'slugs']);
    $country_slug  = is_wp_error($country_terms) ? null : ($country_terms[0] ?? null);

    // ACF/meta existentes en tu plugin
    $event_start   = get_post_meta($post_id, 'event_start', true);    // datetime (Y-m-d H:i:s)
    $lugarsede     = get_post_meta($post_id, 'lugarsede', true);      // sede/venue
    $event_url     = get_post_meta($post_id, 'event_url', true);      // URL externa
    $event_format  = get_post_meta($post_id, 'event_format', true);   // online|presencial|mixto (si lo usas)
    $tipo_evento   = get_post_meta($post_id, 'tipo_de_evento', true); // opcional
    $event_uid     = get_post_meta($post_id, 'event_uid', true);      // id interno/externo (si lo usas)

    return [
      'post_id'      => $post_id,
      'post_title'   => get_the_title($post_id),
      'post_status'  => $post->post_status,
      'permalink'    => get_permalink($post_id) ?: '',
      'country_slug' => $country_slug ?: 'global',

      // Campos ACF reales
      'event_start'  => (string)($event_start ?? ''),
      'lugarsede'    => (string)($lugarsede ?? ''),
      'event_url'    => (string)($event_url ?? ''),
      'event_format' => (string)($event_format ?? ''),
      'tipo_de_evento'=> (string)($tipo_evento ?? ''),
      'event_uid'    => (string)($event_uid ?? ''),
    ];
  }
}
