<?php
namespace MCES\Data;

class PostTypes {
  public static function hooks(){
    add_action('init',[__CLASS__,'register']);
  }
  public static function register(){
    register_post_type('lp_evento', [
      'label' => __('Eventos','mc-event-suite'),
      'labels' => [
        'name'=>__('Eventos','mc-event-suite'),
        'singular_name'=>__('Evento','mc-event-suite'),
      ],
      'public'=>true,
      'show_in_rest'=>true,
      'menu_icon'=>'dashicons-calendar-alt',
      'supports'=>['title','editor','excerpt','thumbnail','custom-fields'],
      'has_archive'=>true,
      'rewrite'=>['slug'=>'evento'],
      'taxonomies'=>['country'], // asocia con tu taxonomía país existente
    ]);
  }
}
