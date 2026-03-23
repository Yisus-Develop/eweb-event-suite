<?php
namespace MCES\Data\Repositories;
class SubscribersRepo {
  public static function upsert($email,$country,$data=[]){
    global $wpdb; $t=$wpdb->prefix.'mc_event_alerts';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE email=%s AND country_slug=%s",$email,$country));
    $token = $row ? $row->token : wp_generate_password(32,false,false);
    $now = current_time('mysql');
    $payload = [
      'name'=>$data['name']??'','city'=>$data['city']??'','lang'=>$data['lang']??'',
      'consent_ip'=>$data['ip']??'','consent_at'=> $row && $row->consent_at ? $row->consent_at : $now,
      'token'=>$token,'unsubscribed_at'=>null
    ];
    if($row){
      $wpdb->update($t,$payload,['id'=>$row->id]);
    }else{
      $wpdb->insert($t, array_merge(['email'=>$email,'country_slug'=>$country], $payload));
    }
    return $token;
  }
  public static function markVerifiedByToken($token){
    global $wpdb; $t=$wpdb->prefix.'mc_event_alerts';
    return $wpdb->update($t,['verified_at'=>current_time('mysql')],['token'=>$token]);
  }
  public static function unsubscribeByToken($token){
    global $wpdb; $t=$wpdb->prefix.'mc_event_alerts';
    return $wpdb->update($t,['unsubscribed_at'=>current_time('mysql')],['token'=>$token]);
  }
  public static function getVerifiedEmailsByCountry($country){
    global $wpdb; $t=$wpdb->prefix.'mc_event_alerts';
    return $wpdb->get_col($wpdb->prepare("SELECT email FROM $t WHERE country_slug=%s AND verified_at IS NOT NULL AND unsubscribed_at IS NULL",$country));
  }
}
