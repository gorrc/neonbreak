(() => {
 const form=document.getElementById('nb-chat-import');if(!form)return;
 const hr=NBChatBackup.language==='hr',t=(en,cro)=>hr?cro:en;
 form.addEventListener('submit',async event=>{
  event.preventDefault();const status=form.querySelector('[role=status]'),button=form.querySelector('button');button.disabled=true;
  try{
   const file=document.getElementById('nb-chat-import-file').files[0];if(!file||file.size>131072)throw Error(t('Select a settings JSON file up to 128 KB.','Odaberite JSON datoteku postavki do 128 KB.'));
   const data=JSON.parse(await file.text()),settings=data.settings,target=document.querySelector('.nb-client-form');
   if(!target||data.type!=='neonbreak-chat-settings'||data.version!==1||!settings||Array.isArray(settings))throw Error(t('This is not a supported Neonbreak settings file.','Ovo nije podržana Neonbreak datoteka postavki.'));
   for(const name of NBChatBackup.fields){const value=settings[name];if(name==='enabled'||name==='daily_limit'){if(!Number.isInteger(value)||value<0||(name==='daily_limit'&&(value<1||value>1000))||(name==='enabled'&&value>1))throw Error(t('Invalid numeric setting.','Neispravna brojčana postavka.'));}else if(typeof value!=='string')throw Error(t('Invalid settings file.','Neispravna datoteka postavki.'));}
   if(!confirm(t('Replace the values in the form? Nothing is saved until you review and save.','Zamijeniti vrijednosti u obrascu? Ništa se ne sprema dok ne pregledate i spremite.')))return;
   for(const name of NBChatBackup.fields){const input=target.elements.namedItem(name);if(!input)continue;if(name==='enabled')input.checked=false;else input.value=settings[name];input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));}
   status.textContent=t('Settings loaded. Review the form, enable the widget if needed, then check connection and save.','Postavke su učitane. Pregledajte obrazac, po potrebi uključite widget pa provjerite vezu i spremite.');target.scrollIntoView({block:'start'});
  }catch(e){status.textContent=e instanceof SyntaxError?t('The file is not valid JSON.','Datoteka nije valjani JSON.'):e.message;}
  finally{button.disabled=false;}
 });
})();
