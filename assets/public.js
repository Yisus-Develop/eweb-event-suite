(() => {
  'use strict';

  const qs  = (s, c=document) => c.querySelector(s);
  const qsa = (s, c=document) => Array.from(c.querySelectorAll(s));

  document.addEventListener('DOMContentLoaded', () => {
    // ----- abrir modal -----
    qsa('.mces-popup-open').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const wrap = btn.closest('.mces-popup-wrap');
        const ov   = qs('.mces-modal-overlay', wrap || document);
        if (!ov) return;
        ov.style.display = 'flex';
        ov.setAttribute('aria-hidden', 'false');
        document.body.classList.add('mces-lock');

        // foco al primer input del form
        const firstInput = qs('.wpcf7 input[type="text"], .wpcf7 input[type="email"], .wpcf7 select, .wpcf7 textarea', ov);
        if (firstInput) firstInput.focus();
      });
    });

    // ----- cerrar modal (X, overlay, ESC) -----
    document.addEventListener('click', e => {
      const closeBtn = e.target.closest?.('.mces-modal__close');
      const isOverlay = e.target?.classList?.contains('mces-modal-overlay');
      if (!closeBtn && !isOverlay) return;

      const ov = closeBtn ? closeBtn.closest('.mces-modal-overlay') : e.target;
      if (!ov) return;
      e.preventDefault();
      ov.style.display = 'none';
      ov.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('mces-lock');
    });

    document.addEventListener('keydown', e => {
      if (e.key !== 'Escape') return;
      const ov = qs('.mces-modal-overlay[style*="display: flex"]');
      if (!ov) return;
      ov.style.display = 'none';
      ov.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('mces-lock');
    });

    // ----- honeypot y nuestros hidden -----
    const hp = qs('input[name="ea_website"], textarea[name="ea_website"]');
    if (hp) {
      hp.style.display = 'none';
      const w = hp.closest('.mces-hide');
      if (w) w.style.display = 'none';
    }
    qsa('input[type="hidden"][name^="ea_"]').forEach(i => { try { i.style.display = 'none'; } catch(e){} });

    // ----- timestamp + nonce + país (si los pasas por MCESVARS) -----
    const nowSec = () => Math.floor(Date.now() / 1000);
    qsa('input[name="ea_ts"], input[name="ea_timestamp"]').forEach(el => { try { el.value = nowSec(); } catch(e){} });
    qsa('input[name="ea_nonce"]').forEach(el => {
      try {
        if (window.MCESVARS?.nonce) el.value = MCESVARS.nonce;
      } catch(e){}
    });

    if (window.MCESVARS?.country) {
      qsa('select[name="ea_country"], input[name="ea_country"]').forEach(el => {
        try {
          if (el.tagName === 'SELECT') {
            if (![...el.options].some(o => o.value === MCESVARS.country)) {
              const opt = document.createElement('option');
              opt.value = MCESVARS.country;
              opt.textContent = String(MCESVARS.country).toUpperCase();
              el.appendChild(opt);
            }
            el.value = MCESVARS.country;
            el.dispatchEvent(new Event('change', { bubbles: true }));
          } else {
            el.value = MCESVARS.country;
          }
        } catch(e){}
      });
    }

    // ----- feedback CF7 y cierre rápido -----
    document.addEventListener('wpcf7mailsent', () => {
      const ov = qs('.mces-modal-overlay[style*="display: flex"]');
      if (!ov) return;
      const box = qs('.mces-modal', ov);
      if (box) {
        const msg = document.createElement('div');
        msg.className = 'mces-popup-success';
        msg.style.cssText = 'text-align:center;font-size:16px;margin:10px 0;';
        const txt = (window.MCES_I18N && MCES_I18N.success_message) ? MCES_I18N.success_message : '✅ Suscripción realizada';
        msg.textContent = txt;
        box.appendChild(msg);
      }
      setTimeout(() => {
        ov.style.display = 'none';
        ov.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('mces-lock');
        const m = qs('.mces-popup-success', ov);
        if (m) m.remove();
      }, 1200);
    }, false);

    // helper para setear si está vacío
    const mcesSetVal = (form, name, val) => {
      const el = form.querySelector(`[name="${name}"]`);
      if (el && !el.value) el.value = val;
    };

    // Rellenar hidden justo antes de enviar (fase de captura)
    document.addEventListener('submit', function onMcesSubmit(e){
      const form = e.target;
      if (!form?.classList?.contains('wpcf7-form')) return;

      // Idioma del navegador o del sitio (fallbacks)
      const siteLocale = window.MCESVARS?.locale || document.documentElement.lang || '';
      const navLang = (navigator.language || navigator.userLanguage || siteLocale || '').replace('_','-');
      mcesSetVal(form, 'ea_lang', navLang);

      // Referrer o URL actual
      const ref = document.referrer || window.location.href;
      mcesSetVal(form, 'ea_referrer', ref);

      // Country (por si el shortcode no lo puso)
      if (window.MCESVARS?.country) {
        mcesSetVal(form, 'ea_country', MCESVARS.country);
      }

      // Nonce (debe emparejar con wp_verify_nonce(..., 'mces_form') en el servidor)
      if (window.MCESVARS?.nonce) {
        mcesSetVal(form, 'ea_nonce', MCESVARS.nonce);
      }

      // Timestamp fresco SIEMPRE en submit (sobrescribe)
      ['ea_timestamp','ea_ts'].forEach(name => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) el.value = nowSec();
      });
    }, true);
  });
})();
