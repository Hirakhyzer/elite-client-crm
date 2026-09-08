(function(){
'use strict';

const ITEMS = [
  {href:'/opportunities', label:'Opportunities'},
  {href:'/jobs', label:'Jobs Abroad'},
  {href:'/internships', label:'Internships'}
];

function cleanPath(href){
  try { return new URL(href, window.location.origin).pathname.replace(/\/+$/,'') || '/'; }
  catch(e){ return href || ''; }
}

function textOf(el){ return (el.textContent || '').trim().toLowerCase(); }

function candidateContainers(){
  const out = new Set();
  document.querySelectorAll('nav, header').forEach(el => {
    if (el.matches('nav')) out.add(el);
    el.querySelectorAll('nav').forEach(n => out.add(n));
    el.querySelectorAll('div,ul').forEach(c => {
      const links = c.querySelectorAll(':scope > a, :scope > li > a');
      if (links.length >= 4) out.add(c);
    });
  });
  return Array.from(out);
}

function score(container){
  const text = textOf(container);
  let score = 0;
  ['scholarships','universities','tools','consultants','blog','about'].forEach(k => {
    if (text.includes(k)) score++;
  });
  return score;
}

function directLinks(container){
  let links = Array.from(container.querySelectorAll(':scope > a'));
  if (!links.length) links = Array.from(container.querySelectorAll(':scope > li > a'));
  if (!links.length) links = Array.from(container.querySelectorAll('a')).filter(a => {
    const p = a.parentElement;
    return p === container || p?.parentElement === container;
  });
  return links;
}

function setLinkText(anchor, label){
  const simple = anchor.children.length === 0;
  if (simple) {
    anchor.textContent = label;
    return;
  }
  const textNode = Array.from(anchor.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
  if (textNode) textNode.textContent = label;
  else {
    const span = anchor.querySelector('span');
    if (span) span.textContent = label;
    else anchor.textContent = label;
  }
}

function removeActiveState(anchor){
  anchor.removeAttribute('aria-current');
  anchor.classList.remove('active','current','selected','is-active');
}

function makeClone(base, item){
  const clone = base.cloneNode(true);
  clone.href = item.href;
  setLinkText(clone, item.label);
  removeActiveState(clone);
  clone.dataset.d2dNavAdded = '1';
  return clone;
}

function ensureItems(container){
  let links = directLinks(container);
  if (links.length < 4) return;

  const base = links.find(a => cleanPath(a.getAttribute('href') || '') === '/universities')
            || links.find(a => textOf(a) === 'universities')
            || links[0];
  const tools = links.find(a => cleanPath(a.getAttribute('href') || '') === '/tools')
             || links.find(a => textOf(a) === 'tools');
  if (!base) return;

  // Normalize any older opportunity label rather than adding a duplicate.
  links.forEach(a => {
    const p = cleanPath(a.getAttribute('href') || '');
    if (p === '/opportunities') setLinkText(a, 'Opportunities');
    if (p === '/jobs') setLinkText(a, 'Jobs Abroad');
    if (p === '/internships') setLinkText(a, 'Internships');
  });

  const parent = base.parentElement === container ? container : base.parentElement;
  if (!parent) return;

  ITEMS.forEach(item => {
    links = directLinks(container);
    const exists = links.some(a => cleanPath(a.getAttribute('href') || '') === item.href);
    if (exists) return;

    const clone = makeClone(base, item);
    const toolsNow = links.find(a => cleanPath(a.getAttribute('href') || '') === '/tools')
                  || links.find(a => textOf(a) === 'tools');
    const insertBefore = toolsNow && toolsNow.parentElement === parent ? toolsNow : null;
    parent.insertBefore(clone, insertBefore);
  });

  // Active state for the new routes, matching existing nav behavior.
  const current = window.location.pathname.replace(/\/+$/,'') || '/';
  directLinks(container).forEach(a => {
    const p = cleanPath(a.getAttribute('href') || '');
    if (['/opportunities','/jobs','/internships'].includes(p) && (current === p || current.startsWith(p + '/'))) {
      a.classList.add('active');
      a.setAttribute('aria-current','page');
    }
  });
}

function run(){
  candidateContainers()
    .filter(c => score(c) >= 4)
    .forEach(ensureItems);
}

document.readyState === 'loading'
  ? document.addEventListener('DOMContentLoaded', run, {once:true})
  : run();

// Some pages hydrate or replace the header after load.
setTimeout(run, 400);
setTimeout(run, 1200);
})();
