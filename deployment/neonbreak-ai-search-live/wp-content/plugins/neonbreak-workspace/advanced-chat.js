(() => {
 const t=text=>NBAdvanced.language==='hr'?text:(NBAdvanced.strings[text]||text);
 const file=document.getElementById('nb-advanced-file');
 const ingest=document.querySelector('[data-action="nb_advanced_ingest"]');
 const provenance={format:'text',url:''};
 document.querySelectorAll('[data-document-replace]').forEach(button=>button.addEventListener('click',()=>{
  if(ingest.dataset.busy)return;
  if(ingest.elements.text.value.trim()&&!confirm(NBAdvanced.language==='hr'?'Odbaciti trenutačni tekst i odabrati dokument za zamjenu?':'Discard the current text and select this document for replacement?'))return;
  ingest.elements.replace_document.value=button.dataset.documentReplace;
  ingest.elements.title.value=button.dataset.title;ingest.elements.text.value='';file.value='';provenance.format='text';provenance.url='';
  ingest.querySelector('[role=status]').textContent=NBAdvanced.language==='hr'?'Odabrano za zamjenu. Učitajte novu datoteku, URL ili unesite novi tekst.':'Selected for replacement. Load a new file, URL or enter new text.';
  ingest.scrollIntoView({block:'start'});ingest.elements.title.focus();
 }));
 document.querySelectorAll('[data-document-preview]').forEach(button=>button.addEventListener('click',async()=>{
  const row=button.closest('.nb-document-row'),output=row.querySelector('.nb-document-preview'),status=row.querySelector('.nb-document-preview-status');
  if(!output.hidden){output.hidden=true;button.setAttribute('aria-expanded','false');return;}
  if(output.dataset.loaded){output.hidden=false;button.setAttribute('aria-expanded','true');return;}
  button.disabled=true;status.textContent=t('Obrada je u tijeku…');
  try{
   const body=new FormData();body.set('action','nb_advanced_preview');body.set('document',button.dataset.documentPreview);body.set('_ajax_nonce',NBAdvanced.nonce);body.set('language',NBAdvanced.language);
   const response=await fetch(NBAdvanced.endpoint,{method:'POST',credentials:'same-origin',body}),result=await response.json();if(!result.success)throw Error(result.data?.message||t('Zahtjev nije uspio.'));
   output.replaceChildren();const note=document.createElement('p');note.textContent=NBAdvanced.language==='hr'?'Indeksirani odlomci mogu se djelomično preklapati.':'Indexed chunks may partially overlap.';output.append(note);
   for(const chunk of result.data.chunks){const section=document.createElement('section'),heading=document.createElement('strong'),text=document.createElement('p');heading.textContent=`${t('odlomak')} ${chunk.chunk}`;text.textContent=chunk.text;section.append(heading,text);output.append(section);}
   if(!result.data.chunks.length)note.textContent=NBAdvanced.language==='hr'?'Nema indeksiranih odlomaka.':'No indexed chunks found.';
   output.dataset.loaded='1';output.hidden=false;button.setAttribute('aria-expanded','true');status.textContent='';
  }catch(e){status.textContent=e.message;}finally{button.disabled=false;}
 }));
 const urlForm=document.getElementById('nb-advanced-url-form');
 urlForm?.addEventListener('submit',async event=>{
  event.preventDefault();
  const target=document.querySelector('[data-action="nb_advanced_ingest"]');
  if(urlForm.dataset.busy||target?.dataset.busy)return;
  const button=urlForm.querySelector('button'),status=urlForm.querySelector('[role=status]'),indexButton=target.querySelector('button');
  urlForm.dataset.busy='1';target.dataset.busy='1';button.disabled=true;indexButton.disabled=true;file.disabled=true;
  status.textContent=NBAdvanced.language==='hr'?'Učitavanje stranice…':'Loading page…';
  try{
   const body=new FormData(urlForm);body.set('action','nb_advanced_url');body.set('_ajax_nonce',NBAdvanced.nonce);body.set('language',NBAdvanced.language);
   const response=await fetch(NBAdvanced.endpoint,{method:'POST',credentials:'same-origin',body}),result=await response.json();
   if(!result.success)throw Error(result.data?.message||t('Zahtjev nije uspio.'));
   target.elements.text.value=result.data.text;target.elements.title.value=result.data.title;file.value='';
   provenance.format='url';provenance.url=result.data.url;
   status.textContent=result.data.message;target.elements.text.focus();
  }catch(e){status.textContent=e.message;}
  finally{delete urlForm.dataset.busy;delete target.dataset.busy;button.disabled=false;indexButton.disabled=false;file.disabled=false;}
 });
 file?.addEventListener('change',async()=>{
  const selected=file.files[0];if(!selected)return;
  const form=file.closest('form'),status=form.querySelector('[role=status]'),button=form.querySelector('button');
  if(form.dataset.busy)return;
  form.dataset.busy='1';button.disabled=true;file.disabled=true;form.elements.text.value='';
  provenance.format='text';provenance.url='';
  let task;
  try{
   const pdf=/\.pdf$/i.test(selected.name);
   if(!pdf&&!/\.(txt|md)$/i.test(selected.name))throw Error(t('Odaberite PDF, TXT ili Markdown datoteku.'));
   if(selected.size>(pdf?5*1024*1024:24000))throw Error(pdf?t('PDF smije imati najviše 5 MB.'):t('Tekstualna datoteka smije imati najviše 24 KB.'));
   let text;
   if(pdf){
    status.textContent=t('Izdvajanje teksta iz PDF-a…');
    const lib=await import(NBAdvanced.pdfLibrary);lib.GlobalWorkerOptions.workerSrc=NBAdvanced.pdfWorker;
    task=lib.getDocument({data:new Uint8Array(await selected.arrayBuffer()),isEvalSupported:false,stopAtErrors:true});
    const doc=await task.promise;
    if(doc.numPages>30)throw Error(t('PDF smije imati najviše 30 stranica.'));
    const pages=[];
    for(let n=1;n<=doc.numPages;n++){
     status.textContent=t('Obrada stranice {current} od {total}…').replace('{current}',n).replace('{total}',doc.numPages);
     const page=await doc.getPage(n),content=await page.getTextContent();
     pages.push(content.items.map(item=>item.str+(item.hasEOL?'\n':' ')).join(''));
     if(new TextEncoder().encode(pages.join('\n\n')).length>24000)throw Error(t('Izdvojeni tekst prelazi 24 KB. Podijelite dokument na manje cjeline.'));
     page.cleanup();
    }
    text=pages.join('\n\n').trim();
    if(!text)throw Error(t('PDF nema čitljiv tekst. Za skenirane dokumente potreban je OCR.'));
   }else text=new TextDecoder('utf-8',{fatal:true}).decode(await selected.arrayBuffer()).trim();
   if(!text)throw Error(t('Datoteka nema teksta.'));
   if(new TextEncoder().encode(text).length>24000)throw Error(t('Tekst smije imati najviše 24 KB.'));
   form.elements.text.value=text;
   provenance.format=pdf?'pdf':(/\.md$/i.test(selected.name)?'md':'txt');provenance.url='';
   if(!form.elements.title.value.trim())form.elements.title.value=selected.name.replace(/\.[^.]+$/,'').slice(0,200);
   status.textContent=t('Tekst je spreman. Pregledajte ga pa kliknite „Indeksiraj dokument”.');
  }catch(e){file.value='';status.textContent=e.name==='PasswordException'?t('PDF je zaštićen lozinkom. Učitajte nezaštićenu kopiju.'):(e.message||t('PDF nije moguće pročitati.'));}
  finally{if(task)await task.destroy().catch(()=>{});delete form.dataset.busy;button.disabled=false;file.disabled=false;}
 });
 document.querySelectorAll('.nb-advanced-form').forEach(form=>form.addEventListener('submit',async event=>{
  event.preventDefault();if(form.dataset.busy)return;
  if(form.dataset.action==='nb_advanced_delete'&&!confirm(t('Izbrisati dokument i njegove vektore?')))return;
  form.dataset.busy='1';const button=form.querySelector('button'),status=form.querySelector('[role=status]');button.disabled=true;status.textContent=t('Obrada je u tijeku…');
  try{const body=new FormData(form);body.delete('file');body.set('action',form.dataset.action);body.set('_ajax_nonce',NBAdvanced.nonce);body.set('language',NBAdvanced.language);if(form===ingest){body.set('source_format',provenance.format);body.set('source_url',provenance.url);}const response=await fetch(NBAdvanced.endpoint,{method:'POST',credentials:'same-origin',body});const result=await response.json();if(!result.success)throw Error(result.data?.message||t('Zahtjev nije uspio.'));status.textContent=result.data.message;
   const output=form.querySelector('.nb-advanced-answer');if(output){output.replaceChildren();(result.data.sources||[]).forEach((s,i)=>{const details=document.createElement('details'),summary=document.createElement('summary'),p=document.createElement('p');summary.textContent=`[${i+1}] ${s.title} · ${t('odlomak')} ${s.chunk}`;p.textContent=s.text;details.append(summary,p);output.append(details);});}
  }catch(e){status.textContent=e.message;}finally{delete form.dataset.busy;button.disabled=false;}
 }));
})();
