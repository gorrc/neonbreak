(() => {
  const script = document.currentScript;
  if (!script || !/^[a-f0-9]{32}$/.test(script.dataset.chat || '')) return;
  const endpoint = new URL(script.dataset.host, script.src);
  if (!['http:', 'https:'].includes(endpoint.protocol)) return;
  const frame = document.createElement('iframe');
  const inline = script.dataset.layout === 'inline';
  endpoint.searchParams.set('action', 'nb_client_frame');
  endpoint.searchParams.set('chat', script.dataset.chat);
  endpoint.searchParams.set('origin', location.origin);
  endpoint.searchParams.set('lang', (script.dataset.language || document.documentElement.lang || 'en').toLowerCase().startsWith('hr') ? 'hr' : 'en');
  if (inline) endpoint.searchParams.set('layout', 'inline');
  frame.src = endpoint.href;
  frame.title = 'Business AI assistant';
  frame.referrerPolicy = 'no-referrer';
  frame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox');
  frame.style.cssText = inline
    ? 'display:block;width:100%;max-width:600px;height:520px;margin:0 auto;border:0;background:transparent;color-scheme:normal;'
    : 'position:fixed;right:8px;bottom:8px;width:min(430px,calc(100vw - 16px));height:80px;border:0;background:transparent;z-index:2147483000;color-scheme:normal;';
  window.addEventListener('message', event => {
    if (event.source !== frame.contentWindow || event.origin !== endpoint.origin || event.data?.type !== 'nb-chat-size') return;
    if (inline && Number.isFinite(event.data.height)) frame.style.height = Math.min(1400, Math.max(200, Math.ceil(event.data.height))) + 'px';
    if (!inline) frame.style.height = event.data.open === true ? 'min(660px,calc(100dvh - 16px))' : '80px';
  });
  const mount = () => { if (inline) script.after(frame); else document.body.appendChild(frame); };
  if (document.body) mount(); else document.addEventListener('DOMContentLoaded', mount, {once: true});
})();
