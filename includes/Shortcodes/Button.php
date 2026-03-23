<?php
namespace MCES\Shortcodes;
class Button {
  public static function hooks(){ add_shortcode('mc_event_alert_button',[__CLASS__,'render']); }
  public static function render($atts=[]){
    $a = shortcode_atts(['label'=>__('Suscribirme a alertas','mc-event-suite'),'popup'=>'','target'=>''], $atts);
    $label = esc_html($a['label']);
    $attrs = $a['popup'] ? ' data-popup="'.esc_attr($a['popup']).'"' : '';
    $href  = $a['target'] ?: '#';
    return '<a class="mces-btn" href="'.esc_attr($href).'"'.$attrs.'>'.$label.'</a>';
  }
}
