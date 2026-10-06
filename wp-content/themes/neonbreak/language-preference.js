(() => {
  'use strict';
  const base = new URL(window.NeonbreakLanguage.base);
  const current = new URL(window.location.href);
  const key = `neonbreak-language:${base.pathname}`;
  const get = () => { try { return localStorage.getItem(key); } catch { return null; } };
  const set = value => { try { localStorage.setItem(key, value); } catch {} };
  const normal = path => path.replace(/\/+$/, '') || '/';
  // Detect only on the entry homepage. Direct links to a language remain explicit.
  // Preserve form outcomes, login redirects and translation-editor URLs.
  if (normal(current.pathname) === normal(base.pathname) && !current.search) {
    const saved = get();
    const preferred = (navigator.languages?.[0] || navigator.language || '').toLowerCase();
    if (saved === 'hr' || (!saved && /^hr(?:-|$)/.test(preferred))) {
      const target = new URL('hr/', base);
      target.hash = current.hash;
      window.location.replace(target.href);
    }
  }
  document.addEventListener('click', event => {
    const link = event.target.closest('.trp-language-switcher a, .trp-language-switcher-container a');
    if (!link || event.defaultPrevented || event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const url = new URL(link.href);
    if (url.origin !== base.origin || !url.pathname.startsWith(base.pathname)) return;
    const path = url.pathname.slice(base.pathname.length);
    set(/^hr(?:\/|$)/.test(path) ? 'hr' : 'en');
  });
})();
