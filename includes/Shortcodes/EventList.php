<?php
namespace MCES\Shortcodes;
use MCES\Core\Settings;

class EventList {
  public static function hooks(){ add_shortcode('mc_event_list',[__CLASS__,'render']); }

  public static function render($atts = []){
    static $assets_done = false;

    $a = shortcode_atts([
      'country'  => 'auto',          // auto | mx | es ...
      'scope'    => 'future',        // future | past | all
      'limit'    => 12,
      'order'    => 'ASC',           // ASC (futuros) | DESC (si quieres forzar)
      'accent'   => '#f7b500',       // color de acento
      'class'    => '',
      'btn_text' => __('Conoce más','mc-event-suite'),
    ], $atts, 'mc_event_list');

    $country = self::resolve_country($a['country']);

    // Query CPT
    $args = [
      'post_type'      => 'lp_evento',
      'posts_per_page' => (int) $a['limit'],
      'orderby'        => 'meta_value',
      'meta_key'       => 'event_start',
      'order'          => ($a['scope']==='past' ? 'DESC' : $a['order']),
    ];
    if ($country !== '') {
      $args['tax_query'] = [[
        'taxonomy' => 'country',
        'field'    => 'slug',
        'terms'    => $country,
      ]];
    }

    // Filtro por fecha (según scope)
    $now = current_time('mysql');
    $meta_query = [];
    if ($a['scope']==='future') $meta_query[] = ['key'=>'event_start','value'=>$now,'compare'=>'>=','type'=>'DATETIME'];
    if ($a['scope']==='past')   $meta_query[] = ['key'=>'event_start','value'=>$now,'compare'=>'<','type'=>'DATETIME'];
    if ($meta_query) $args['meta_query'] = $meta_query;

    $q = new \WP_Query($args);

    // Mapas traducibles
    $types_map = apply_filters('mces_types_map', [
      'hackatones' => __('Hackatones','mc-event-suite'),
      'talleres'   => __('Talleres','mc-event-suite'),
      'charlas'    => __('Charlas','mc-event-suite'),
      'networking' => __('Networking','mc-event-suite'),
      'final'      => __('Final','mc-event-suite'),
    ]);
    $format_map = apply_filters('mces_format_map', [
      'presencial' => __('Presencial','mc-event-suite'),
      'virtual'    => __('Virtual','mc-event-suite'),
      'hibrido'    => __('Presencial / Virtual','mc-event-suite'),
    ]);

    $label_all     = __('Todos','mc-event-suite');
    $label_empty_1 = __('No hay eventos aún.','mc-event-suite');
    $label_empty_2 = __('No hay eventos para mostrar.','mc-event-suite');

    // Recolectar items
    $items = []; $tipos_presentes = [];
    if ($q->have_posts()){
      while($q->have_posts()){ $q->the_post();
        $title  = get_the_title();
        $tipo   = (string) get_field('tipo_de_evento');
        $format = (string) get_field('event_format');
        $start  = (string) get_field('event_start');
        $venue  = (string) get_field('lugarsede');
        $url    = (string) get_field('event_url');
        if (!$title || !$start) continue;

        $ts = strtotime($start); if (!$ts) continue;
        $day   = date_i18n('j', $ts);
        $month = date_i18n('M', $ts);
        $time  = date_i18n('H:i', $ts);

        $tipo_slug  = ($tipo ? sanitize_title($tipo) : '');
        $tipo_label = $types_map[$tipo] ?? ($tipo ? ucfirst($tipo) : '');
        if ($tipo_slug) $tipos_presentes[$tipo_slug] = $tipo_label;

        $format_label = $format_map[$format] ?? ($format ? ucfirst($format) : '');

        $items[] = [
          'ts'=>$ts,'day'=>$day,'month'=>$month,'time'=>$time,
          'title'=>$title,'place'=>$venue,'format'=>$format_label,
          'url'=>esc_url($url),'tipo'=>$tipo_slug,'tipo_lb'=>$tipo_label,
        ];
      }
      wp_reset_postdata();
    }

    // Orden extra por seguridad
    usort($items, fn($A,$B)=> $a['scope']==='past' ? ($B['ts'] <=> $A['ts']) : ($A['ts'] <=> $B['ts']));

    // Render
    ob_start();

    // CSS + JS (solo 1 vez por página)
    if (!$assets_done) { $assets_done = true; ?>
<style>
  .mc-agenda{ --mc-accent: var(--mc-accent-fallback, #f7b500); --mc-ink:#221b29; --mc-muted:#6b6b6b }
  .mc-agenda .mc-filter{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 18px}
  .mc-agenda .mc-chip{padding:8px 14px;border:2px solid var(--mc-accent);border-radius:999px;background:#fff;color:var(--mc-ink);cursor:pointer;font:600 13px/1.1 system-ui,Segoe UI,Roboto,sans-serif;transition:all .15s ease}
  .mc-agenda .mc-chip:hover{transform:translateY(-1px)}
  .mc-agenda .mc-chip.is-active{background:var(--mc-accent);color:#111;border-color:var(--mc-accent)}
  .mc-agenda .mc-grid{display:grid;gap:14px}
  @media(min-width:680px){ .mc-agenda .mc-grid{grid-template-columns:1fr 1fr} }
  @media(min-width:1024px){ .mc-agenda .mc-grid{grid-template-columns:1fr 1fr 1fr} }
  .mc-agenda .mc-card{display:grid;grid-template-columns:64px 1fr;gap:12px;border:1px solid #eee;border-radius:12px;background:#fff;padding:14px 14px;box-shadow:0 1px 0 rgba(0,0,0,.03)}
  .mc-agenda .mc-date{display:flex;flex-direction:column;align-items:center;justify-content:center;width:64px;min-height:64px;border-radius:10px;background:rgba(247,181,0,.12);border:2px solid var(--mc-accent); color:#111}
  .mc-agenda .mc-date .mc-day{font:700 22px/1 system-ui,Segoe UI,Roboto}
  .mc-agenda .mc-date .mc-mon{font:700 12px/1 system-ui;text-transform:uppercase;letter-spacing:.04em}
  .mc-agenda .mc-body .mc-top{display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center; margin:-2px 0 6px}
  .mc-agenda .mc-title{font:700 16px/1.2 system-ui,Segoe UI,Roboto;color:var(--mc-ink); margin:0}
  .mc-agenda .mc-badges{display:flex;flex-wrap:wrap;gap:6px}
  .mc-agenda .mc-badge{padding:4px 8px;border-radius:999px;background:#f2f2f2;color:#333;font:600 11px/1 system-ui}
  .mc-agenda .mc-badge.mc-type{background:rgba(247,181,0,.16); color:#111; border:1px solid rgba(247,181,0,.45)}
  .mc-agenda .mc-meta{color:var(--mc-muted);font:500 13px/1.45 system-ui;margin-top:2px}
  .mc-agenda .mc-meta .mc-dot::before{content:"•";margin:0 8px;opacity:.4}
  .mc-agenda .mc-cta{margin-top:8px}
  .mc-agenda .mc-cta a.elementor-button-link{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid var(--mc-accent);text-decoration:none}
  .mc-agenda .mc-cta a.elementor-button-link[href=""], .mc-agenda .mc-cta a.elementor-button-link:not([href]), .mc-agenda .mc-cta a.elementor-button-link[href="#"]{display:none}
</style>
<script>
document.addEventListener('click', (ev) => {
  const btn = ev.target.closest('.mc-agenda .mc-chip'); if(!btn) return;
  const wrap = btn.closest('.mc-agenda'); if(!wrap) return;
  const slug = btn.getAttribute('data-type') || 'all';
  wrap.querySelectorAll('.mc-chip').forEach(b => b.classList.toggle('is-active', b===btn));
  wrap.querySelectorAll('.mc-card').forEach(card => {
    if (slug === 'all') { card.style.display=''; return; }
    const t = (card.getAttribute('data-type') || '').split(' ');
    card.style.display = t.includes(slug) ? '' : 'none';
  });
});
</script>
<?php }

    // Wrapper
    $classes = 'mc-agenda'.($a['class'] ? ' '.esc_attr($a['class']) : '');
    printf('<div class="%s" style="--mc-accent-fallback:%s" data-source="cpt" data-scope="%s">', esc_attr($classes), esc_attr($a['accent']), esc_attr($a['scope']));

    // Vacíos
    if (!$q->found_posts){
      echo '<div class="mc-agenda">'.esc_html($label_empty_1).'</div></div>';
      return ob_get_clean();
    }
    if (empty($items)) {
      echo '<div class="mc-agenda">'.esc_html($label_empty_2).'</div></div>';
      return ob_get_clean();
    }

    // Chips
    echo '<div class="mc-filter" role="tablist">';
    echo '<button class="mc-chip is-active" data-type="all">'.esc_html($label_all).'</button>';
    if (!empty($tipos_presentes)) {
      asort($tipos_presentes);
      foreach ($tipos_presentes as $slug => $label) {
        echo '<button class="mc-chip" data-type="'.esc_attr($slug).'">'.esc_html($label).'</button>';
      }
    }
    echo '</div>';

    // Grid
    echo '<div class="mc-grid">';
    foreach ($items as $it) {
      $type_attr = trim($it['tipo']);
      echo '<article class="mc-card" data-type="'.esc_attr($type_attr).'">';
        echo '<div class="mc-date"><div class="mc-day">'.esc_html($it['day']).'</div><div class="mc-mon">'.esc_html($it['month']).'</div></div>';
        echo '<div class="mc-body">';
          echo '<div class="mc-top">';
            echo '<h3 class="mc-title">'.esc_html($it['title']).'</h3>';
            echo '<div class="mc-badges">';
              if (!empty($it['tipo_lb']))  echo '<span class="mc-badge mc-type">'.esc_html($it['tipo_lb']).'</span>';
              if (!empty($it['format']))   echo '<span class="mc-badge">'.esc_html($it['format']).'</span>';
            echo '</div>';
          echo '</div>';
          echo '<div class="mc-meta">';
            if (!empty($it['time']))  echo '<span class="mc-time">'.esc_html($it['time']).'</span>';
            if (!empty($it['place'])) echo '<span class="mc-dot"></span><span class="mc-place">'.esc_html($it['place']).'</span>';
          echo '</div>';
          echo '<div class="mc-cta">';
            if (!empty($it['url'])) {
              echo '<a class="elementor-button-link elementor-button" href="'.esc_url($it['url']).'" target="_blank" rel="noopener">'.esc_html($a['btn_text']).'</a>';
            }
          echo '</div>';
        echo '</div>';
      echo '</article>';
    }
    echo '</div>'; // grid

    echo '</div>'; // wrapper

    return ob_get_clean();
  }

  private static function resolve_country($param){
    if ($param !== 'auto') return sanitize_text_field($param);

    if (!empty($_GET['pais'])) return sanitize_text_field($_GET['pais']);

    global $post;
    if ($post && $post->post_type === 'landing_paises') {
      $terms = wp_get_post_terms($post->ID, 'country');
      if ($terms && !is_wp_error($terms) && !empty($terms[0]) && !empty($terms[0]->slug)) return sanitize_text_field($terms[0]->slug);
    }
    if ($post && $post->post_type === 'lp_evento') {
      $terms = wp_get_post_terms($post->ID, 'country');
      if ($terms && !is_wp_error($terms) && !empty($terms[0]) && !empty($terms[0]->slug)) return sanitize_text_field($terms[0]->slug);
    }

    return sanitize_text_field(Settings::get('default_country',''));
  }
}
