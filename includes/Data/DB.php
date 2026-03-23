<?php
namespace MCES\Data;

class DB {
  public static function hooks(){ /* reservado por si añadimos cosas */ }
  public static function install(){
    global $wpdb; 
    $charset = $wpdb->get_charset_collate();
  
    $t1 = $wpdb->prefix.'mc_event_alerts';
    $t2 = $wpdb->prefix.'mc_event_alerts_queue';
    $t3 = $wpdb->prefix.'mc_event_sync_outbox';
  
    $sql = [];
  
    // SUSCRIPTORES
    $sql[] = "CREATE TABLE IF NOT EXISTS `$t1` (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(190) NOT NULL,
      country_slug VARCHAR(20) NOT NULL,
      name VARCHAR(190) NULL,
      city VARCHAR(190) NULL,
      lang VARCHAR(10) NULL,
      token VARCHAR(64) NOT NULL,
      consent_ip VARCHAR(64) NULL,
      consent_at DATETIME NULL,
      verified_at DATETIME NULL,
      unsubscribed_at DATETIME NULL,
      UNIQUE KEY email_country (email, country_slug),
      KEY country (country_slug),
      KEY token (token)
    ) $charset;";
  
    // COLA DE NOTIFICACIONES (añadimos índices para consolidación y envíos)
    $sql[] = "CREATE TABLE IF NOT EXISTS `$t2` (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      event_id BIGINT UNSIGNED NOT NULL,
      country_slug VARCHAR(20) NOT NULL,
      change_type VARCHAR(20) NOT NULL,
      payload_json LONGTEXT NULL,
      scheduled_at DATETIME NOT NULL,
      sent_at DATETIME NULL,
      attempts INT DEFAULT 0,
      last_error TEXT NULL,
      KEY event_id (event_id),
      KEY country (country_slug),
      KEY scheduled_at (scheduled_at),
      KEY sent_at (sent_at),
      KEY country_scheduled (country_slug, scheduled_at)
    ) $charset;";
  
    // OUTBOX (proveedor externo)
    $sql[] = "CREATE TABLE IF NOT EXISTS `$t3` (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      op VARCHAR(32) NOT NULL,
      email VARCHAR(190) NOT NULL,
      country_slug VARCHAR(20) NULL,
      payload_json LONGTEXT NULL,
      status VARCHAR(16) DEFAULT 'PENDING',
      attempts INT DEFAULT 0,
      last_error TEXT NULL,
      created_at DATETIME NOT NULL,
      processed_at DATETIME NULL,
      KEY status (status),
      KEY email (email)
    ) $charset;";
  
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    foreach ($sql as $q) { dbDelta($q); }
  }
  
}
