(() => {
  document.querySelectorAll('.nb-client-form').forEach(form => {
    const root = form.closest('.nb-client');
    const preview = root.querySelector('.nb-client-preview');
    const update = () => {
      ['title', 'welcome', 'launcher', 'contact_label'].forEach(name => {
        preview.querySelector(`[data-preview="${name}"]`).textContent = form.elements[name].value;
      });
      preview.style.setProperty('--client-accent', form.elements.accent.value);
      const contact = preview.querySelector('[data-preview="contact_label"]');
      try {
        const url = new URL(form.elements.contact.value);
        if (!['https:', 'http:'].includes(url.protocol)) throw new Error();
        contact.href = url.href;
      } catch { contact.removeAttribute('href'); }
      const questions = preview.querySelector('[data-preview="suggestions"]');
      questions.replaceChildren();
      form.elements.suggestions.value.split('\n').map(s => s.trim()).filter(Boolean).slice(0, 3).forEach(text => {
        const button = document.createElement('button');
        button.type = 'button'; button.textContent = text.slice(0, 150);
        questions.appendChild(button);
      });
    };
    form.addEventListener('input', update);
    update();
  });
  document.querySelectorAll('.nb-client-copy').forEach(button => button.addEventListener('click', async () => {
    const root = button.closest('.nb-client-embed');
    const code = root.querySelector('textarea');
    const status = root.querySelector('[role=status]');
    const croatian = document.documentElement.lang.toLowerCase().startsWith('hr');
    try { await navigator.clipboard.writeText(code.value); status.textContent = croatian ? ' Kod kopiran.' : ' Code copied.'; }
    catch { code.focus(); code.select(); status.textContent = croatian ? ' Označite i kopirajte kod ručno.' : ' Select and copy the code manually.'; }
  }));
})();
