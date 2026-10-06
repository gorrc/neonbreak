(() => {
  const script = document.currentScript;
  if (!script || !/^[a-f0-9]{32}$/.test(script.dataset.chat || '')) return;
  const endpoint = new URL(script.dataset.host, script.src);
  if (!['http:', 'https:'].includes(endpoint.protocol)) return;
  const frame = document.createElement('iframe');
  endpoint.searchParams.set('action', 'nb_client_frame');
  endpoint.searchParams.set('chat', script.dataset.chat);
  endpoint.searchParams.set('origin', location.origin);
  frame.src = endpoint.href;
  frame.title = 'Business AI assistant';
  frame.referrerPolicy = 'no-referrer';
  frame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox');
  frame.style.cssText = 'position:fixed;right:8px;bottom:8px;width:min(430px,calc(100vw - 16px));height:80px;border:0;background:transparent;z-index:2147483000;color-scheme:normal;';
  window.addEventListener('message', event => {
    if (event.source !== frame.contentWindow || event.origin !== endpoint.origin || event.data?.type !== 'nb-chat-size') return;
    frame.style.height = event.data.open === true ? 'min(660px,calc(100dvh - 16px))' : '80px';
  });
  const mount = () => document.body.appendChild(frame);
  if (document.body) mount(); else document.addEventListener('DOMContentLoaded', mount, {once: true});
})();
