const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
class Element{
 constructor(tag){this.tag=tag;this.children=[];this.className='';this.value='';}
 append(...children){this.children.push(...children);}
 set textContent(value){this.value=String(value);this.children=[];}
 get textContent(){return this.value+this.children.map(child=>child.textContent).join('');}
 set innerHTML(value){throw Error('Unsafe HTML insertion attempted');}
}
const context={window:{},document:{createElement:tag=>new Element(tag),createTextNode:text=>{const e=new Element('#text');e.textContent=text;return e;}}};
vm.runInNewContext(fs.readFileSync('wp-content/plugins/neonbreak-workspace/assistant-format.js','utf8'),context);
const render=context.window.NBBAFormat;
const all=(root,tag)=>[...(root.tag===tag?[root]:[]),...root.children.flatMap(child=>all(child,tag))];
const table=render('| Tema | Pitanja | Izvori |\n| --- | --- | --- |\n| PHP | **Migracija** i cijena | [1] |\n| Privatnost | Brisanje | [2] |');
assert.equal(all(table,'section').length,2);assert.equal(all(table,'h4')[0].textContent,'PHP');assert.equal(all(table,'strong').some(node=>node.textContent==='Migracija'),true);assert.equal(table.textContent.includes('|'),false);
const formatted=render('## Zaključak\n\nPosjetitelji traže **cijene**.\n\n- Uredi cjenik [1]\n- Objasni rokove [2]');assert.equal(all(formatted,'h4').length,1);assert.equal(all(formatted,'li').length,2);assert.equal(all(formatted,'strong')[0].textContent,'cijene');
const attack=render('<img src=x onerror=alert(1)>\n\n**<script>alert(1)</script>**\n\n[klik](javascript:alert(1))');assert.equal(all(attack,'img').length,0);assert.equal(all(attack,'script').length,0);assert.equal(all(attack,'a').length,0);assert.ok(attack.textContent.includes('<img'));
assert.equal(render('Običan odgovor.').textContent,'Običan odgovor.');
console.log('PASS: saved tables become topic cards; headings, lists and emphasis render; HTML and unsafe links remain inert text.');
