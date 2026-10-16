(() => {
 'use strict';
 const config=window.NBHistoryPanel;if(!config)return;
 const hr=config.language==='hr',t=(en,cro)=>hr?cro:en;
 let list=document.querySelector('.ws-history-list');
 // Detail URLs remain usable without JavaScript.
 if(!list){document.addEventListener('submit',e=>{if(e.target.matches('[data-history-delete]')&&!confirm(config.confirm))e.preventDefault();});return;}
 const notice=document.createElement('p');notice.className='ws-history-live-status';notice.setAttribute('role','status');list.before(notice);
 const dialog=document.createElement('dialog');dialog.className='ws-history-drawer';
 const head=document.createElement('header'),heading=document.createElement('strong'),close=document.createElement('button'),content=document.createElement('div');
 heading.textContent=t('Conversation','Razgovor');close.type='button';close.textContent='×';close.setAttribute('aria-label',t('Close','Zatvori'));heading.id='ws-history-drawer-title';dialog.setAttribute('aria-labelledby',heading.id);
 head.append(heading,close);content.className='ws-history-drawer-content';dialog.append(head,content);document.body.append(dialog);
 let opener=null,listController=null,detailController=null,listVersion=0,detailVersion=0;
 close.addEventListener('click',()=>dialog.close());dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close();});
 dialog.addEventListener('close',()=>{detailVersion++;detailController?.abort();if(opener?.isConnected)opener.focus();});
 const request=async(operation,params,signal)=>{
  const body=new URLSearchParams({action:'nb_history_fragment',_ajax_nonce:config.nonce,language:config.language,operation});
  for(const key of ['q','period','role','sort','order','history_page','conversation'])if(params.has(key))body.set(key,params.get(key));
  const response=await fetch(config.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',body,signal});
  let data;try{data=await response.json();}catch{throw Error(t('Refresh the page and sign in again.','Osvježite stranicu i ponovno se prijavite.'));}
  if(!response.ok||!data.success)throw Error(data.data?.message||t('Request failed.','Zahtjev nije uspio.'));return data.data;
 };
 const fragment=html=>{const doc=new DOMParser().parseFromString(html,'text/html'),section=doc.querySelector('.ws-history-section');if(!section)throw Error(t('Unable to load conversations.','Razgovore nije moguće učitati.'));return section;};
 const update=async(url,push=true)=>{
  listController?.abort();listController=new AbortController();const version=++listVersion;list.setAttribute('aria-busy','true');notice.textContent=t('Loading…','Učitavanje…');
  try{const data=await request('list',url.searchParams,listController.signal);if(version!==listVersion)return;const next=fragment(data.html);list.replaceWith(next);list=next;notice.textContent='';if(push)history.pushState({nbHistory:true},'',url);}
  catch(error){if(error.name!=='AbortError'&&version===listVersion)notice.textContent=error.message;}
  finally{if(version===listVersion)list.removeAttribute('aria-busy');}
 };
 const open=async(link)=>{
  opener=link;detailController?.abort();detailController=new AbortController();const version=++detailVersion;
  content.textContent=t('Loading conversation…','Učitavanje razgovora…');if(!dialog.open)dialog.showModal();
  try{const data=await request('detail',new URL(link.href).searchParams,detailController.signal);if(version!==detailVersion)return;const section=fragment(data.html);section.querySelector(':scope > a')?.remove();content.replaceChildren(section);}
  catch(error){if(error.name!=='AbortError'&&version===detailVersion)content.textContent=error.message;}
 };
 document.addEventListener('click',e=>{
  const link=e.target.closest('a');if(!link||!list.contains(link)||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||e.button!==0)return;
  const url=new URL(link.href);if(url.origin!==location.origin)return;
  if(url.searchParams.has('conversation')){e.preventDefault();open(link);}
  else if(link.closest('thead,.ws-history-filter-actions')||link.classList.contains('page-numbers')){e.preventDefault();update(url);}
 });
 document.addEventListener('submit',async e=>{
  const form=e.target;
  if(form.matches('.ws-history-filter')&&list.contains(form)){e.preventDefault();const url=new URL(location.href);url.search='';for(const [key,value]of new FormData(form))url.searchParams.set(key,value);update(url);return;}
  if(!form.matches('[data-history-delete]'))return;
  e.preventDefault();if(!confirm(config.confirm))return;
  const button=form.querySelector('button');if(button.disabled)return;button.disabled=true;
  try{await request('delete',new URLSearchParams(new FormData(form)));if(dialog.open)dialog.close();await update(new URL(location.href),false);}
  catch(error){notice.textContent=error.message;if(dialog.open){const errorNode=document.createElement('p');errorNode.setAttribute('role','alert');errorNode.textContent=error.message;content.prepend(errorNode);}}
  finally{button.disabled=false;}
 });
 window.addEventListener('popstate',()=>{if(dialog.open)dialog.close();update(new URL(location.href),false);});
})();
