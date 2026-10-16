/* Render a small, safe formatting subset. All source text stays in text nodes. */
window.NBBAFormat = (text) => {
 'use strict';
 const root=document.createElement('div');root.className='ba-answer';
 const inline=(element,value)=>{
  const tokens=String(value).split(/(\*\*[^*\n]+\*\*|`[^`\n]+`)/g);
  for(const token of tokens){if(!token)continue;let child;if(token.startsWith('**')&&token.endsWith('**')){child=document.createElement('strong');child.textContent=token.slice(2,-2);}else if(token.startsWith('`')&&token.endsWith('`')){child=document.createElement('code');child.textContent=token.slice(1,-1);}else child=document.createTextNode(token);element.append(child);}
 };
 const block=(tag,value,parent=root)=>{const el=document.createElement(tag);inline(el,value);parent.append(el);return el;};
 const lines=String(text||'').replace(/\r\n?/g,'\n').split('\n');
 const cells=line=>line.trim().replace(/^\|/,'').replace(/\|$/,'').split('|').map(cell=>cell.trim());
 const divider=line=>line.includes('|')&&cells(line).every(cell=>/^:?-{3,}:?$/.test(cell));
 let i=0;
 while(i<lines.length){
  const line=lines[i].trim();if(!line){i++;continue;}
  // Older saved answers may contain pipe tables. Show each row as a readable topic card.
  if(line.includes('|')&&i+1<lines.length&&divider(lines[i+1])){
   const headers=cells(line);i+=2;const group=document.createElement('div');group.className='ba-answer-cards';root.append(group);
   while(i<lines.length&&lines[i].includes('|')&&lines[i].trim()){
    const values=cells(lines[i++]);const card=document.createElement('section');card.className='ba-answer-card';group.append(card);
    block('h4',values[0]||headers[0],card);
    for(let column=1;column<values.length;column++){if(!values[column])continue;const p=document.createElement('p');if(headers[column]){const label=document.createElement('strong');label.textContent=headers[column]+': ';p.append(label);}inline(p,values[column]);card.append(p);}
   }continue;
  }
  const heading=line.match(/^#{1,6}\s+(.+)$/);if(heading){block('h4',heading[1]);i++;continue;}
  if(/^([-*_])(?:\s*\1){2,}$/.test(line)){i++;continue;}
  const bullet=line.match(/^(?:[-*•]\s+|\d+[.)]\s+)(.+)$/);
  if(bullet){const ordered=/^\d/.test(line);const ul=document.createElement(ordered?'ol':'ul');root.append(ul);
   while(i<lines.length){const item=lines[i].trim().match(ordered?/^\d+[.)]\s+(.+)$/:/^[-*•]\s+(.+)$/);if(!item)break;block('li',item[1],ul);i++;}continue;
  }
  block('p',line);i++;
 }
 return root;
};
