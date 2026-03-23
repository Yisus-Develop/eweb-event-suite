<?php
namespace MCES\Data;

class ACFFields {
  public static function hooks(){
    add_action('acf/include_fields', [__CLASS__,'register_group']);
  }
  public static function register_group(){
    if (!function_exists('acf_add_local_field_group')) return;
    acf_add_local_field_group([
      'key'=>'group_mces_evento',
      'title'=>'Evento – Datos',
      'fields'=>[
        ['key'=>'field_mces_tipo','label'=>'Tipo de evento','name'=>'tipo_de_evento','type'=>'select',
          'choices'=>['hackatones'=>'Hackatones','talleres'=>'Talleres','charlas'=>'Charlas','networking'=>'Networking','final'=>'Final'],
          'return_format'=>'value','ui'=>1],
        ['key'=>'field_mces_format','label'=>'Formato','name'=>'event_format','type'=>'select',
          'choices'=>['presencial'=>'Presencial','virtual'=>'Virtual','hibrido'=>'Presencial / Virtual'],'return_format'=>'value','ui'=>1],
        ['key'=>'field_mces_start','label'=>'Fecha del evento','name'=>'event_start','type'=>'date_time_picker',
          'display_format'=>'Y-m-d H:i:s','return_format'=>'Y-m-d H:i:s','first_day'=>1],
        ['key'=>'field_mces_venue','label'=>'Lugar/Sede','name'=>'lugarsede','type'=>'text'],
        ['key'=>'field_mces_url','label'=>'URL de “Conoce más / Inscripción”','name'=>'event_url','type'=>'url'],
        ['key'=>'field_mces_uid','label'=>'UID','name'=>'event_uid','type'=>'text','wrapper'=>['class'=>'acf-hidden']],
      ],
      'location'=>[[['param'=>'post_type','operator'=>'==','value'=>'lp_evento']]],
    ]);
  }
}
