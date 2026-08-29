document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.site-nav');
  if (!toggle || !nav) return;
  toggle.addEventListener('click', () => {
    const open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  nav.addEventListener('click', (event) => {
    if (event.target.closest('a')) {
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });

  const dropdown = nav.querySelector('.nav-dropdown');
  const dropdownToggle = nav.querySelector('.nav-dropdown-toggle');
  if (dropdown && dropdownToggle) {
    dropdownToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      const open = dropdown.classList.toggle('is-open');
      dropdownToggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', (event) => {
      if (!dropdown.contains(event.target)) {
        dropdown.classList.remove('is-open');
        dropdownToggle.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        dropdown.classList.remove('is-open');
        dropdownToggle.setAttribute('aria-expanded', 'false');
        dropdownToggle.focus();
      }
    });
  }
});
