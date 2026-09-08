(function(){
'use strict';

var cfg=window.D2D_ADS_R5||{};
if(!cfg.publisher||!cfg.mid||!cfg.end)return;

function q(sel,root){return (root||document).querySelector(sel)}
function qa(sel,root){return Array.prototype.slice.call((root||document).querySelectorAll(sel))}

// Keep Google intent/overlay behavior away from navigation, footers and floating UI.
qa('header,nav,footer,.d2d-u-header,.d2d-u-footer,.d2d-u-floating-ai,.d2d-floating-ai,.d2d-ai-fab').forEach(function(el){
  el.classList.add('google-anno-skip');
  el.setAttribute('google-side-rail-overlap','false');
});

function contentRoot(){
  var path=(cfg.path||'').replace(/^\/+|\/+$/g,'');
  if(/^blog\//.test(path)){
    return q('article .d2d-prose')||q('.d2d-article-body')||q('main article')||q('article')||q('main');
  }
  if(/^universities\//.test(path)){
    return q('.d2d-u-detail-grid article')||q('.d2d-profile-grid article')||q('main article')||q('main');
  }
  return q('.d2d-profile-grid article')||q('.opportunity-detail article')||q('main article')||q('article')||q('main');
}

function sidebarRoot(){
  var candidates=[
    '.d2d-u-detail-grid aside',
    '.d2d-profile-grid aside',
    '.article-sidebar',
    '.blog-sidebar',
    '.opportunity-sidebar',
    'main aside'
  ];
  for(var i=0;i<candidates.length;i++){
    var el=q(candidates[i]);
    if(el&&el.getBoundingClientRect().width>=240)return el;
  }
  return null;
}

function adBox(slot,kind){
  var wrap=document.createElement('aside');
  wrap.className='d2d-r5-ad d2d-r5-ad-'+kind;
  wrap.setAttribute('aria-label','Advertisement');
  wrap.setAttribute('data-d2d-r5-slot',slot);

  var label=document.createElement('div');
  label.className='d2d-r5-ad-label';
  label.textContent='ADVERTISEMENT';
  wrap.appendChild(label);

  var ins=document.createElement('ins');
  ins.className='adsbygoogle';
  ins.style.display='block';
  ins.setAttribute('data-ad-client',cfg.publisher);
  ins.setAttribute('data-ad-slot',slot);
  ins.setAttribute('data-ad-format','auto');
  ins.setAttribute('data-full-width-responsive','true');
  wrap.appendChild(ins);

  return wrap;
}

function pushAd(node){
  if(!node)return;
  requestAnimationFrame(function(){
    try{(window.adsbygoogle=window.adsbygoogle||[]).push({});}catch(e){}
  });
}

function placeMid(root){
  if(!root)return;
  var node=adBox(cfg.mid,'mid');
  var paras=qa('p',root).filter(function(p){return (p.textContent||'').trim().length>50});
  if(paras.length>=4){
    paras[Math.min(3,paras.length-1)].insertAdjacentElement('afterend',node);
  }else{
    var children=Array.prototype.slice.call(root.children||[]).filter(function(el){return el.offsetParent!==null});
    if(children.length>=2)children[0].insertAdjacentElement('afterend',node);else root.appendChild(node);
  }
  pushAd(node);
}

function placeEndOrRail(root,sidebar){
  var node=adBox(cfg.end,sidebar&&window.innerWidth>=1100?'rail':'end');

  if(sidebar&&window.innerWidth>=1100){
    var save=q('a,button',sidebar);
    var all=qa('a,button',sidebar);
    var saveTarget=null;
    all.forEach(function(el){
      if(/save this guide|save guide|save/i.test((el.textContent||'').trim()))saveTarget=el;
    });
    if(saveTarget){
      var holder=saveTarget.closest('section,div,aside')||saveTarget;
      holder.insertAdjacentElement('afterend',node);
    }else{
      sidebar.appendChild(node);
    }
  }else if(root){
    var sections=qa('section,.d2d-u-detail-card,.d2d-content-card',root);
    if(sections.length)sections[sections.length-1].insertAdjacentElement('afterend',node);else root.appendChild(node);
  }
  pushAd(node);
}

function boot(){
  if(q('[data-d2d-r5-placed="1"]'))return;
  var root=contentRoot();
  if(!root)return;
  var marker=document.createElement('span');
  marker.hidden=true;
  marker.setAttribute('data-d2d-r5-placed','1');
  root.appendChild(marker);

  placeMid(root);
  placeEndOrRail(root,sidebarRoot());
}

if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
