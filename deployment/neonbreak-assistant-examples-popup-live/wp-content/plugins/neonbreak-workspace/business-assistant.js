(() => {

 'use strict';

 const app=document.getElementById('ba-app');if(!app)return;

 const hr=NBBA.language==='hr',t=(en,cro)=>hr?cro:en;

 const log=document.getElementById('ba-messages'),list=document.getElementById('ba-threads'),form=document.getElementById('ba-form'),input=form.elements.message,status=document.getElementById('ba-status'),title=document.getElementById('ba-title'),remove=document.getElementById('ba-delete'),exportButton=document.getElementById('ba-export'),newButton=document.getElementById('ba-new');

 const modeSelect=document.getElementById('ba-mode');
 const examples=document.getElementById('ba-examples');
 const openExamples=()=>{if(examples&&!examples.open)examples.showModal();};
 document.getElementById('ba-show-examples')?.addEventListener('click',openExamples);
 document.getElementById('ba-close-examples')?.addEventListener('click',()=>examples.close());
 examples?.addEventListener('click',event=>{if(event.target===examples)examples.close();});
 app.addEventListener('click',event=>{const button=event.target.closest('[data-ba-example]');if(!button||busy||!canSend())return;modeSelect.value=button.dataset.baExample;input.value=button.textContent.trim();examples.close();input.focus();});

 let current=null,busy=false;

 const canSend=()=>NBBA.allowed&&NBBA.ready;

 const setBusy=value=>{busy=value;if(modeSelect)modeSelect.disabled=value||!canSend();app.setAttribute('aria-busy',String(value));form.querySelector('button').disabled=value||!canSend();app.querySelectorAll('[data-ba-example]').forEach(button=>button.disabled=value||!canSend());input.disabled=value||!canSend();newButton.disabled=value||!canSend();remove.disabled=value;list.querySelectorAll('button').forEach(button=>button.disabled=value);};

 const request=async(operation,data={})=>{

  const body=new URLSearchParams({action:'nb_business_assistant',_ajax_nonce:NBBA.nonce,operation,language:NBBA.language,...data});

  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),300000);

  try{const response=await fetch(NBBA.endpoint,{method:'POST',credentials:'same-origin',body,signal:controller.signal});let payload;try{payload=await response.json();}catch{throw Error(t('Unexpected server response. Refresh and sign in again.','Neočekivan odgovor poslužitelja. Osvježite stranicu i ponovno se prijavite.'));}if(!response.ok||!payload.success)throw Error(payload.data?.message||t('Request failed.','Zahtjev nije uspio.'));return payload.data;}

  catch(error){if(error.name==='AbortError')throw Error(t('Request timed out. Reload the conversation before sending again.','Zahtjev je istekao. Ponovno učitajte razgovor prije novog slanja.'));throw error;}finally{clearTimeout(timer);}

 };

 const node=(tag,text,className)=>{const element=document.createElement(tag);if(text!==undefined)element.textContent=text;if(className)element.className=className;return element;};

 const render=()=>{

  title.textContent=current?.title||t('Search your knowledge','Pretražite svoju bazu znanja');remove.hidden=!current;exportButton.hidden=!current;log.replaceChildren();

  if(!current?.messages.length){const empty=node('div',undefined,'ba-empty');empty.append(node('span','✦','ba-symbol'),node('h2',t('What would you like to find?','Što želite pronaći?')),node('p',t('Ask about business documents or saved visitor conversations.','Pitajte o poslovnim dokumentima ili spremljenim razgovorima posjetitelja.')));const showExamples=node('button',t('Explore question examples','Pogledaj primjere pitanja'));showExamples.type='button';showExamples.addEventListener('click',openExamples);empty.append(showExamples);log.append(empty);}

  for(const message of current?.messages||[]){const article=node('article',undefined,'ba-message '+(message.role==='user'?'is-user':'is-assistant')+(message.failed?' is-failed':''));article.append(node('strong',message.role==='user'?t('You','Vi'):'AI Business Assistant'),message.role==='assistant'&&window.NBBAFormat?window.NBBAFormat(message.content):node('p',message.content));

   if(message.sources?.length){const details=node('details',undefined,'ba-sources');details.append(node('summary',t('Sources','Izvori')+' · '+message.sources.length));message.sources.forEach((source,index)=>{details.append(node('h4','['+(index+1)+'] '+source.document),node('p',source.text));if(source.source_url){try{const url=new URL(source.source_url);if(url.protocol==='https:'||url.protocol==='http:'){const link=node('a',t('Open source ↗','Otvori izvor ↗'));link.href=url.href;link.target='_blank';link.rel='noopener noreferrer';details.append(link);}}catch{}}});article.append(details);}

   if(message.tool&&message.tool!=='none')article.append(node('small',message.tool==='search_conversations'?t('Visitor conversations','Razgovori posjetitelja'):t('Knowledge base','Baza znanja'),'ba-tool-label'));

   log.append(article);

  }log.scrollTop=log.scrollHeight;

 };

 const refreshList=async()=>{const data=await request('list');list.replaceChildren();for(const thread of data.threads){const button=node('button',thread.title);button.type='button';if(current?.id===thread.id)button.setAttribute('aria-current','page');button.disabled=busy;button.addEventListener('click',()=>run(async()=>{current=await request('load',{id:thread.id});render();await refreshList();}));list.append(button);}if(!data.threads.length)list.append(node('p',t('No conversations yet.','Još nema razgovora.')));};

 const run=async(callback)=>{if(busy)return;setBusy(true);status.textContent='';try{await callback();}catch(error){status.textContent=error.message;}finally{setBusy(false);}};

 newButton.addEventListener('click',()=>{if(busy)return;current=null;render();refreshList().catch(error=>status.textContent=error.message);input.focus();});

 form.addEventListener('submit',event=>{event.preventDefault();const question=input.value.trim();if(!question||!canSend())return;if(!modeSelect.value){modeSelect.reportValidity();return;}run(async()=>{if(!current)current=await request('create');status.textContent=t('Searching the selected source…','Pretražujem odabrani izvor…');const requestId=Array.from(crypto.getRandomValues(new Uint8Array(16)),v=>v.toString(16).padStart(2,'0')).join('');try{current=await request('send',{id:current.id,message:question,request:requestId,mode:modeSelect.value});input.value='';render();status.textContent='';await refreshList();}catch(error){try{current=await request('load',{id:current.id});render();await refreshList();}catch{}throw error;}});});

 input.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.shiftKey&&!event.isComposing){event.preventDefault();if(!busy)form.requestSubmit();}});

 remove.addEventListener('click',()=>{if(!current||!confirm(t('Permanently delete this assistant conversation?','Trajno izbrisati ovaj razgovor s asistentom?')))return;run(async()=>{await request('delete',{id:current.id});current=null;render();await refreshList();});});

 exportButton.addEventListener('click',()=>{if(!current)return;const blob=new Blob([JSON.stringify({type:'neonbreak-business-assistant-conversation',version:1,conversation:current},null,2)],{type:'application/json'}),url=URL.createObjectURL(blob),link=document.createElement('a');link.href=url;link.download='assistant-'+current.id+'.json';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);});

 render();run(refreshList);

})();

