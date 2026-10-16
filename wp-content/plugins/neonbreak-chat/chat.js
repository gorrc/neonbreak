(() => {
  'use strict';
  const hr = document.documentElement.lang.toLowerCase().startsWith('hr');
  const translations = {
    'Preparing your answer…': 'Pripremam odgovor…',
    'Unable to send your question. Please try again.': 'Pitanje nije moguće poslati. Pokušajte ponovno.',
    'No answer was returned. Please try again.': 'Odgovor nije primljen. Pokušajte ponovno.',
    'The request timed out. Please try again.': 'Zahtjev je istekao. Pokušajte ponovno.',
    'Refresh the page and try again.': 'Osvježite stranicu i pokušajte ponovno.',
    'The assistant is not configured yet.': 'Asistent još nije podešen.',
    'Please start a new chat.': 'Započnite novi razgovor.',
    'Invalid conversation.': 'Razgovor nije valjan.',
    'Invalid message.': 'Poruka nije valjana.',
    'A question is required.': 'Unesite pitanje.',
    'The chat limit has been reached. Please try again later.': 'Dosegnuto je ograničenje chata. Pokušajte ponovno kasnije.',
    'The assistant is temporarily unavailable. Please try again shortly.': 'Asistent trenutačno nije dostupan. Pokušajte ponovno uskoro.',
    'Refresh the chat to continue.': 'Osvježite chat za nastavak.',
    'Chat is unavailable on this website.': 'Chat nije dostupan na ovoj stranici.',
    'The chat connection needs attention.': 'Potrebno je provjeriti vezu chata.',
    'Conversation storage is unavailable.': 'Pohrana razgovora trenutačno nije dostupna.',
    'Refresh the chat to start a new conversation.': 'Osvježite chat za novi razgovor.',
    'Start a new chat. This conversation has expired.': 'Pokrenite novi razgovor. Prethodni je istekao.',
    'Conversation storage limit reached. Contact the website owner.': 'Dosegnut je limit pohrane razgovora. Kontaktirajte vlasnika stranice.',
    'Start a new chat or retry the original question.': 'Pokrenite novi razgovor ili ponovite izvorno pitanje.',
    'Your previous question is still processing. Please wait.': 'Prethodno pitanje još se obrađuje. Pričekajte.'
  };
  const text = value => hr ? (translations[value] || value) : value;
  document.querySelectorAll('.nb-chat').forEach(root => {
    if (NeonbreakChat.inline && NeonbreakChat.parentOrigin) {
      const reportHeight = () => window.parent.postMessage({type: 'nb-chat-size', height: root.getBoundingClientRect().height + 16}, NeonbreakChat.parentOrigin);
      new ResizeObserver(reportHeight).observe(root);
      reportHeight();
    }
    const panel = root.querySelector('.nb-chat-panel');
    const toggle = root.querySelector('.nb-chat-toggle');
    const log = root.querySelector('.nb-chat-messages');
    const form = root.querySelector('form');
    const input = form.elements.message;
    const send = form.querySelector('button');
    const reset = root.querySelector('.nb-chat-reset');
    const status = root.querySelector('.nb-chat-status');
    const suggestions = root.querySelector('.nb-chat-suggestions');
    const welcome = log.firstElementChild.textContent;
    let history = [];
    let busy = false;
    let retry = null;
    const randomId = bytes => Array.from(crypto.getRandomValues(new Uint8Array(bytes)), value => value.toString(16).padStart(2, '0')).join('');
    const persistent = NeonbreakChat.historyEnabled === true && !!NeonbreakChat.request?.chat;
    const sessionKey = 'nb_conversation_' + (NeonbreakChat.request?.chat || '') + '_' + (NeonbreakChat.request?.origin || '');
    let conversationToken = '';
    const newConversation = () => {
      if (!persistent) return;
      conversationToken = randomId(32);
      try { sessionStorage.setItem(sessionKey, conversationToken); } catch {}
    };
    if (persistent) {
      try { conversationToken = sessionStorage.getItem(sessionKey) || ''; } catch {}
      if (!/^[a-f0-9]{64}$/.test(conversationToken)) newConversation();
      const notice = root.querySelector('.nb-chat-notice');
      if (notice) notice.textContent += hr
        ? ` Razgovori se spremaju do ${NeonbreakChat.historyDays} dana nakon zadnje aktivnosti i dostupni su vlasniku stranice.`
        : ` Conversations are stored for up to ${NeonbreakChat.historyDays} days after last activity and can be reviewed by the website owner.`;
    }
    const append = (content, role) => {
      const p = document.createElement('p');
      p.className = role === 'user' ? 'nb-chat-user' : 'nb-chat-bot';
      p.textContent = content;
      log.appendChild(p);
      log.scrollTop = log.scrollHeight;
      return p;
    };
    const appendSources = items => {
        if (NeonbreakChat.showSources !== true) return;
        if (Array.isArray(items) && items.length) {
          const sources = document.createElement('section');
          sources.className = 'nb-chat-sources';
          const heading = document.createElement('div');
          heading.textContent = hr ? 'Dohvaćeni izvori' : 'Retrieved sources';
          sources.appendChild(heading);
          items.slice(0, 5).forEach((source, index) => {
            if (typeof source.title !== 'string' || typeof source.text !== 'string') return;
            const details = document.createElement('details');
            const summary = document.createElement('summary');
            const excerpt = document.createElement('p');
            summary.textContent = `[${index + 1}] ${source.title} · ${hr ? 'odlomak' : 'chunk'} ${Number(source.chunk) || 1}`;
            excerpt.textContent = source.text;
            details.append(summary, excerpt);
            sources.appendChild(details);
          });
          log.appendChild(sources);
          log.scrollTop = log.scrollHeight;
        }
    };
    const open = value => {
      panel.hidden = !value;
      toggle?.setAttribute('aria-expanded', String(value));
      if (value) input.focus(); else toggle?.focus();
      if (NeonbreakChat.parentOrigin) window.parent.postMessage({type: 'nb-chat-size', open: value}, NeonbreakChat.parentOrigin);
    };
    toggle?.addEventListener('click', () => open(panel.hidden));
    root.querySelector('.nb-chat-close')?.addEventListener('click', () => open(false));
    root.addEventListener('keydown', event => {
      if (event.key === 'Escape' && toggle && !panel.hidden) open(false);
    });
    reset.addEventListener('click', () => {
      if (busy) return;
      history = [];
      retry = null;
      newConversation();
      log.replaceChildren();
      append(welcome, 'assistant');
      suggestions.hidden = false;
      status.textContent = '';
      input.value = '';
      input.focus();
    });
    suggestions.querySelectorAll('button').forEach(button => button.addEventListener('click', () => {
      if (busy) return;
      input.value = button.textContent;
      form.requestSubmit();
    }));
    input.addEventListener('keydown', event => {
      if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        form.requestSubmit();
      }
    });
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const question = input.value.trim();
      if (!question || busy) return;
      busy = true;
      send.disabled = reset.disabled = true;
      input.disabled = true;
      suggestions.hidden = true;
      log.setAttribute('aria-busy', 'true');
      status.textContent = text('Preparing your answer…');
      const bubble = append(question, 'user');
      const pending = append('•••', 'assistant');
      pending.classList.add('nb-chat-pending');
      pending.setAttribute('aria-hidden', 'true');
      // Whole user/assistant pairs keep the bounded history valid.
      const messages = [...history.slice(-10), {role: 'user', content: question}];
      const requestId = persistent ? (retry?.question === question ? retry.id : randomId(16)) : '';
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 100000);
      try {
        const response = await fetch(NeonbreakChat.endpoint, {
          method: 'POST', credentials: 'same-origin', signal: controller.signal,
          body: new URLSearchParams({...NeonbreakChat.request, action: NeonbreakChat.action || 'neonbreak_chat', nonce: NeonbreakChat.nonce || '', messages: JSON.stringify(messages), conversation_token: conversationToken, request_id: requestId})
        });
        const raw = await response.text();
        let payload;
        try { payload = JSON.parse(raw); }
        catch {
          throw new Error(hr
            ? 'Poslužitelj je vratio stranicu greške umjesto odgovora chata. Administrator treba provjeriti PHP zapisnik grešaka.'
            : 'The server returned an error page instead of a chat response. The administrator needs to check the PHP error log.');
        }
        if (!response.ok || !payload.success) throw new Error(payload.data?.message || 'Unable to send your question. Please try again.');
        if (typeof payload.data?.message !== 'string') throw new Error('No answer was returned. Please try again.');
        pending.remove();
        append(payload.data.message, 'assistant');
        appendSources(payload.data.sources);
        // Bound stored replies so a long model response cannot break the next request.
        const answer = Array.from(payload.data.message).slice(0, 1500).join('');
        history = [...messages, {role: 'assistant', content: answer}];
        retry = null;
        input.value = '';
        status.textContent = payload.data.history_saved === false ? (hr ? 'Odgovor je primljen, ali povijest nije potpuno spremljena.' : 'Answer received, but history was not fully saved.') : '';
      } catch (error) {
        if (persistent) retry = {question, id: requestId};
        bubble.remove();
        if (!history.length) suggestions.hidden = false;
        status.textContent = error.name === 'AbortError' ? text('The request timed out. Please try again.') : text(error.message);
      } finally {
        clearTimeout(timer);
        pending.remove();
        log.setAttribute('aria-busy', 'false');
        busy = false;
        send.disabled = reset.disabled = false;
        input.disabled = false;
        if (!panel.hidden) input.focus();
      }
    });
    if (persistent) {
      busy = true; send.disabled = reset.disabled = input.disabled = true;
      fetch(NeonbreakChat.endpoint, {method:'POST',credentials:'same-origin',body:new URLSearchParams({...NeonbreakChat.request,action:'nb_client_history',conversation_token:conversationToken})})
        .then(response=>response.json()).then(payload=>{
          if (!payload.success || !Array.isArray(payload.data?.messages)) throw Error('history');
          const rows=payload.data.messages;
          if(!rows.length){newConversation();return;}
          log.replaceChildren();append(welcome,'assistant');suggestions.hidden=true;
          const pairs=[];
          for(let index=0;index<rows.length;index++){
            const row=rows[index];
            if(row.role==='user'&&row.status==='complete'&&rows[index+1]?.role==='assistant'){
              const answer=rows[++index];append(row.content,'user');append(answer.content,'assistant');appendSources(answer.sources);
              pairs.push({role:'user',content:row.content},{role:'assistant',content:Array.from(answer.content).slice(0,1500).join('')});
            }
          }
          history=pairs.slice(-10);
          if(rows.some(row=>row.role==='user'&&row.status!=='complete'))status.textContent=hr?'Prethodno pitanje nije dovršeno. Možete ga ponovno postaviti.':'A previous question was not completed. You can ask it again.';
        }).catch(()=>{status.textContent=hr?'Povijest nije moguće učitati. Pokušajte osvježiti stranicu.':'Conversation history could not be loaded. Try refreshing the page.';})
        .finally(()=>{busy=false;send.disabled=reset.disabled=input.disabled=false;});
    }
  });
})();
