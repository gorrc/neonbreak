(() => {
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!open));
      nav.classList.toggle('is-open', !open);
      document.body.classList.toggle('nav-open', !open);
    });
    nav.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
      toggle.setAttribute('aria-expanded', 'false'); nav.classList.remove('is-open'); document.body.classList.remove('nav-open');
    }));
  }
  const header = document.querySelector('[data-header]');
  if (header) addEventListener('scroll', () => header.classList.toggle('is-scrolled', scrollY > 16), {passive:true});
})();

