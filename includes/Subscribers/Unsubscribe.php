<?php
namespace MCES\Subscribers;
use MCES\Data\Repositories\SubscribersRepo;
use MCES\Data\Repositories\OutboxRepo;

class Unsubscribe {
  public static function hooks(){
    add_action('init',[__CLASS__,'handle']);
  }
  public static function handle(){
    if (isset($_GET['alert-unsub'])) {
      $token = sanitize_text_field($_GET['alert-unsub']);
      if ($token) {
        SubscribersRepo::unsubscribeByToken($token);
        // podemos buscar email/country si hace falta para outbox UNSUBSCRIBE
        // OutboxRepo::push('UNSUBSCRIBE',$email,$country,[]);
        wp_die(__('Has sido dado de baja.','mc-event-suite'));
      }
    }
  }
}
