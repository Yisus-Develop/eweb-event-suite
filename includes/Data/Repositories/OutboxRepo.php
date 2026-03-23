<?php
namespace MCES\Data\Repositories;
class OutboxRepo {
  public static function push($op,$email,$country=null,$payload=[]){
    global $wpdb; $t=$wpdb->prefix.'mc_event_sync_outbox';
    $wpdb->insert($t,[
      'op'=>$op,'email'=>$email,'country_slug'=>$country,
      'payload_json'=>wp_json_encode($payload),'status'=>'PENDING',
      'created_at'=>current_time('mysql')
    ]);
  }
  public static function takePending($limit=50){
    global $wpdb; $t=$wpdb->prefix.'mc_event_sync_outbox';
    return $wpdb->get_results("SELECT * FROM $t WHERE status='PENDING' ORDER BY id ASC LIMIT ".intval($limit));
  }
  public static function markDone($id){ global $wpdb; $t=$wpdb->prefix.'mc_event_sync_outbox'; $wpdb->update($t,['status'=>'SENT','processed_at'=>current_time('mysql')],['id'=>$id]); }
  public static function markFailed($id,$err){ global $wpdb; $t=$wpdb->prefix.'mc_event_sync_outbox'; $wpdb->update($t,['status'=>'FAILED','last_error'=>$err],['id'=>$id]); }
}
