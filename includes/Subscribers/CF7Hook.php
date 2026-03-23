<?php
namespace MCES\Subscribers;

use MCES\Core\Settings;
use MCES\Data\Repositories\SubscribersRepo;
use MCES\Data\Repositories\OutboxRepo;

if (!defined('ABSPATH')) exit;

class CF7Hook {

  public static function hooks() {
    // Procesar envío
    add_action('wpcf7_mail_sent', [__CLASS__, 'on_submit']);

    // Registrar y validar el form-tag [mces_honeypot]
    add_action('wpcf7_init', [__CLASS__, 'register_honeypot_tag']);
    add_filter('wpcf7_validate_text*', [__CLASS__, 'validate_honeypot'], 10, 2);
    add_filter('wpcf7_validate_text',  [__CLASS__, 'validate_honeypot'], 10, 2);
  }

  /** Renderiza [mces_honeypot] como campo trampa invisible */
  public static function register_honeypot_tag() {
    if (!function_exists('wpcf7_add_form_tag')) return;

    wpcf7_add_form_tag('mces_honeypot', function($tag) {
      // Nombre fijo que usaremos para detectar bots en on_submit
      $name = 'ea_website';
      // Envuelto en un contenedor oculto por CSS del plugin
      $html = '<span class="mces-hide"><label>Leave this field empty'
            . '<input type="text" name="'.esc_attr($name).'" value="" tabindex="-1" autocomplete="off"></label></span>';
      return $html;
    }, ['name-attr' => true]);
  }

  /** Valida el honeypot: si viene con contenido, se invalida */
  public static function validate_honeypot($result, $tag) {
    $hp_name = 'ea_website';
    if (!isset($tag->name) || $tag->name !== $hp_name) return $result;

    if (class_exists('\WPCF7_Submission')) {
      $submission = \WPCF7_Submission::get_instance();
      if ($submission) {
        $posted = $submission->get_posted_data($hp_name);
        if (!empty($posted)) {
          $result->invalidate($tag, __('Spam detectado.', 'mc-event-suite'));
        }
      }
    }
    return $result;
  }

  /** Procesa el envío y guarda/actualiza suscriptor */
    /** Procesa el envío y guarda/actualiza suscriptor */
    public static function on_submit($form) {
      if (!class_exists('\WPCF7_Submission')) return;
  
      $s = \WPCF7_Submission::get_instance();
      if (!$s) return;
  
      $d = $s->get_posted_data();
      error_log('[MCES CF7] on_submit fired');
  
      // --- Anti-bot / seguridad ---
      // 1) Honeypot
      if (!empty($d['ea_website'])) {
        error_log('[MCES CF7] honeypot FAIL');
        return;
      }
      error_log('[MCES CF7] hp OK');
  
      // 2) Timestamp (acepta ea_ts o ea_timestamp) → máx 15 min
      $ts = isset($d['ea_ts']) ? intval($d['ea_ts']) : (isset($d['ea_timestamp']) ? intval($d['ea_timestamp']) : 0);
      if ($ts <= 0) {
        error_log('[MCES CF7] ts missing');
        return;
      }
      if (abs(time() - $ts) > 15 * 60) {
        error_log('[MCES CF7] ts window FAIL');
        return;
      }
      error_log('[MCES CF7] ts OK');
  
      // 3) Nonce (tolerante por caché de página). CF7 ya validó su propio nonce.
$nonce = isset($d['ea_nonce']) ? $d['ea_nonce'] : '';
if ($nonce && wp_verify_nonce($nonce, 'mces_form')) {
  error_log('[MCES CF7] nonce OK');
} else {
  // No bloqueamos: caché puede romper el nonce embebido
  error_log('[MCES CF7] nonce WARN (missing/invalid) — proceeding due to CF7 validation + timestamp + honeypot');
}

  
      // --- Datos principales ---
      $email   = sanitize_email($d['ea_email'] ?? '');
      $country = sanitize_text_field($d['ea_country'] ?? Settings::get('default_country',''));
      if (!$email || !$country) {
        error_log('[MCES CF7] invalid email or country');
        return;
      }
  
      $firstname   = sanitize_text_field($d['ea_firstname'] ?? '');
      $lastname    = sanitize_text_field($d['ea_lastname'] ?? '');
      $name        = trim($firstname . ' ' . $lastname);
      $role        = sanitize_text_field($d['ea_role'] ?? '');
      $institution = sanitize_text_field($d['ea_institution'] ?? '');
      $city        = sanitize_text_field($d['ea_city'] ?? '');
  
      // Metadatos
      $lang     = sanitize_text_field($d['ea_lang'] ?? (function_exists('determine_locale') ? determine_locale() : ''));
      $source   = sanitize_text_field($d['ea_source'] ?? 'popup');
      $referrer = sanitize_text_field($d['ea_referrer'] ?? '');
      $tags     = sanitize_text_field($d['ea_tags'] ?? '');
      $ip       = $s->get_meta('remote_ip');
      $consent_at = current_time('mysql');
  
      error_log("[MCES CF7] mapped email={$email}, country={$country}, lang={$lang}");
  
    // --- UPSERT local (firma legacy: upsert($email, $country, $meta)) ---
$meta = [
  'firstname'   => $firstname,
  'lastname'    => $lastname,
  'name'        => $name,
  'role'        => $role,
  'institution' => $institution,
  'city'        => $city,
  'lang'        => $lang,
  'source'      => $source,
  'referrer'    => $referrer,
  'tags'        => $tags,
  'consent_ip'  => $ip,
  'consent_at'  => $consent_at,
];

$res = \MCES\Data\Repositories\SubscribersRepo::upsert(
  strtolower($email),
  strtolower($country ?: 'global'),
  $meta
);
error_log('[MCES CF7] upsert done ' . wp_json_encode($res));

// --- Encolar en outbox (firma legacy: push($op, $email, $country, $payload)) ---
\MCES\Data\Repositories\OutboxRepo::push(
  'UPSERT_CONTACT',
  strtolower($email),
  strtolower($country ?: 'global'),
  [
    'firstname'   => $firstname,
    'lastname'    => $lastname,
    'institution' => $institution,
    'role'        => $role,
    'city'        => $city,
    'lang'        => $lang,
    'source'      => $source,
    'referrer'    => $referrer,
    'tags'        => $tags,
    'consent_ip'  => $ip,
    'consent_at'  => $consent_at,
  ]
);
error_log('[MCES CF7] outbox push');

    }
  
}
