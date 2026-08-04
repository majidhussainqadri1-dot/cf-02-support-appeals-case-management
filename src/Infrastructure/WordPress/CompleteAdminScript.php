<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

final class CompleteAdminScript
{
    public static function render():string
    {
        return <<<'JS'
(function(){'use strict';const r=document.querySelector('[data-cf02-admin]');if(!r)return;const base=r.dataset.endpoint.replace(/\/$/,''),nonce=r.dataset.nonce,out=r.querySelector('[data-output]'),status=r.querySelector('[data-status]');async function api(path,opt={}){const x=await fetch(base+path,{credentials:'same-origin',...opt,headers:{'Content-Type':'application/json','X-WP-Nonce':nonce,'X-CF02-Purpose':'support_operations',...(opt.headers||{})}});let b={};try{b=await x.json()}catch(e){}if(!x.ok)throw new Error(b.message||'Request failed');return b}function table(items){const t=document.createElement('table');t.className='widefat striped';t.setAttribute('role','table');const keys=items[0]?Object.keys(items[0]).slice(0,8):[];const h=document.createElement('tr');keys.forEach(k=>{const th=document.createElement('th');th.scope='col';th.textContent=k;h.append(th)});t.append(h);items.forEach(i=>{const tr=document.createElement('tr');keys.forEach(k=>{const td=document.createElement('td');td.textContent=String(i[k]??'');tr.append(td)});t.append(tr)});return t}async function load(k){status.textContent='Loading…';let data;if(k==='queue')data=await api('/staff/queue?limit=50');if(k==='search')data=await api('/staff/cases/search?limit=50');if(k==='appeals')data=await api('/staff/appeals?limit=50');if(k==='sla')data={at_risk:await api('/staff/sla/at-risk'),metrics:await api('/staff/metrics/sla'),backlog:await api('/staff/metrics/backlog')};if(k==='quality')data=await api('/staff/metrics/quality');if(k==='retention')data=await api('/staff/retention/due');if(k==='repair')data=await api('/staff/repair/inspect');out.replaceChildren(Array.isArray(data?.items)?table(data.items):Object.assign(document.createElement('pre'),{textContent:JSON.stringify(data,null,2)}));if(k==='repair'){const f=document.createElement('form');f.className='cf02-repair-form';f.innerHTML='<label>Approved repair reference<input name="approval_ref" required pattern="CF02-REPAIR-[A-Za-z0-9_-]{8,64}" autocomplete="off"></label><button class="button button-primary">Run governed repair</button>';f.onsubmit=async e=>{e.preventDefault();if(!confirm('Run the approved non-destructive repair now?'))return;const ref=new FormData(f).get('approval_ref');const d=await api('/staff/repair/run',{method:'POST',body:JSON.stringify({approval_ref:ref})});out.replaceChildren(Object.assign(document.createElement('pre'),{textContent:JSON.stringify(d,null,2)}))};out.append(f)}status.textContent='Ready'}r.querySelectorAll('[data-viewkey]').forEach(b=>b.onclick=()=>load(b.dataset.viewkey).catch(e=>status.textContent=e.message));load('queue').catch(e=>status.textContent=e.message);})();
JS;
    }
}
