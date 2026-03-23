<?php
namespace MCES\Shortcodes;

if (!defined('ABSPATH')) exit;

class Popup {
  public static function hooks() {
    add_shortcode('mc_event_alert_popup', [__CLASS__, 'render']);
  }

  public static function render($atts = []) {
    $a = shortcode_atts([
      'form_id' => '',
      'label'   => __('Suscribirme a alertas', 'mc-event-suite'),
    ], $atts, 'mc_event_alert_popup');

    if (!$a['form_id']) return '<!-- mc_event_alert_popup: falta form_id -->';

    $form_html = do_shortcode('[contact-form-7 id="'.intval($a['form_id']).'"]');

    ob_start(); ?>
    <div class="mces-popup-wrap">
      <a href="#" class="mces-btn mces-popup-open" aria-haspopup="dialog" aria-controls="mces-modal">
        <?php echo esc_html($a['label']); ?>
      </a>

      <div class="mces-modal-overlay" id="mces-modal" aria-hidden="true">
        <div class="mces-modal" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Suscripción a alertas', 'mc-event-suite'); ?>">
          <button type="button" class="mces-modal__close" aria-label="<?php esc_attr_e('Cerrar', 'mc-event-suite'); ?>">×</button>

          <div class="mces-modal__body">
            <?php echo $form_html; ?>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
  }
}
