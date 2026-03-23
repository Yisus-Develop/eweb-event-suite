<?php
namespace MCES\Providers;
use MCES\Providers\Contracts\EmailProviderInterface;

class LocalLogProvider implements EmailProviderInterface {
  private function log($tag,$data){ error_log('[MCES '.$tag.'] '. wp_json_encode($data)); }

  // 👇 nuevo: wrapper estático para logs desde otras capas (Dispatcher, etc.)
  public static function publish(string $tag, array $data): void {
    error_log('[MCES '.$tag.'] '. wp_json_encode($data));
  }

  public function upsertContact(array $contact): array { $this->log('UPSERT', $contact); return ['ok'=>true]; }
  public function sendDoubleOptIn(array $contact, array $meta=[]): array { $this->log('DOI', compact('contact','meta')); return ['ok'=>true]; }
  public function sendTransactional(array $message): array { $this->log('TX', $message); return ['ok'=>true]; }
  public function unsubscribe(string $email, ?string $country=null): array { $this->log('UNSUB', compact('email','country')); return ['ok'=>true]; }
}
