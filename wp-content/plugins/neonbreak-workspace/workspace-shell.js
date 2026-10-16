(() => {
 const button=document.getElementById('ws-menu-toggle'),shell=document.getElementById('ws-shell');if(!button||!shell)return;
 const close=()=>{shell.classList.remove('is-menu-open');button.setAttribute('aria-expanded','false');};
 button.addEventListener('click',()=>{const open=shell.classList.toggle('is-menu-open');button.setAttribute('aria-expanded',String(open));});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&shell.classList.contains('is-menu-open')){close();button.focus();}});
 document.addEventListener('click',event=>{if(!event.target.closest('.ws-sidebar,.ws-menu-toggle'))close();});
 const mobile=matchMedia('(max-width: 760px)');mobile.addEventListener('change',close);
})();
