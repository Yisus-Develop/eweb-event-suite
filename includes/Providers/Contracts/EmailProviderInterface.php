<?php
namespace MCES\Providers\Contracts;
interface EmailProviderInterface {
  public function upsertContact(array $contact): array;
  public function sendDoubleOptIn(array $contact, array $meta=[]): array;
  public function sendTransactional(array $message): array;
  public function unsubscribe(string $email, ?string $country=null): array;
}
