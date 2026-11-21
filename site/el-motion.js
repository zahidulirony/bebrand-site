/* BeBrand: motion for pages built from Elementor's own elements. See el-motion.css for what it does and which IDs it looks for.
   Nothing here holds content: every word and picture stays an Elementor element. Inside the editor the script does nothing. */
(function(){
'use strict';
var d=document,root=d.documentElement,body=d.body;
if(!body||body.classList.contains('elementor-editor-active'))return;
var $=function(s,r){return (r||d).querySelector(s)},$$=function(s,r){return [].slice.call((r||d).querySelectorAll(s))};
var calm=!!(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches);
var BASE=(window.bbMotion&&window.bbMotion.base)||'';
var hero=$('#top');
root.classList.add('bb-motion');

/* ---- live sky (the same code as the original site's hero) ---- */
var SKY=BASE+'img/hero.jpg';
function initSky(){
  var hero=$('#top'),copy=$('#bb-hero-copy');
  if(!hero||!copy)return null;
  var cv=document.createElement('canvas'),gl=null;
  try{gl=cv.getContext('webgl',{alpha:false,antialias:false,depth:false,stencil:false,powerPreference:'low-power'})}catch(e){}
  if(!gl)return null;
  /* the photograph: its size in px, and the heights above the horizon (degrees) of its top and bottom edges */
  var TW=3520,TH=890,ETOP=46,EBOT=1.5;
  /* The clouds, far ones first. Each is one whole cloud (or a small group) in the photograph: left, top, width, height there (px);
     its layer (0 far, 1 middle, 2 near); how high its base floats above the bottom edge, in band heights (negative: it dips below
     the edge); 1 if it is shown mirrored; and its size against the others of its layer. */
  var CL=[
    [2365,165,860,395,0,.2,1,1],
    [722,468,226,85,0,.72,0,1.3],
    [1205,148,855,320,0,.34,0,.9],
    [0,500,640,228,0,.46,0,1],
    [2640,616,460,184,1,.22,0,1.15],
    [0,500,640,228,1,.2,1,1],
    [832,546,171,106,1,.64,0,1.2],
    [1205,148,855,320,1,.05,1,1],
    [1205,148,855,320,2,-.12,0,1],
    [0,500,640,228,2,-.08,0,1.25],
    [2365,165,860,395,2,-.25,0,1],
    [1205,148,855,320,2,-.06,1,.86],
    [2365,165,860,395,2,-.18,1,.9]
  ],N=CL.length;
  /* The layers: size against the near layer; drift in px a second (for a band 400px tall); how far the layer slides with the pointer
     (px, for a band 250px tall); how much haze; how much room each cloud gets (in cloud widths); where its round starts (0 to 1) */
  var LAY=[[.4,7,6,.45,2.2,0],[.6,12,12,.16,2.3,.37],[1,20,22,0,1.4,.71]];
  var HEAD=[
    '#ifdef GL_FRAGMENT_PRECISION_HIGH',
    'precision highp float;',
    '#else',
    'precision mediump float;',
    '#endif',
    'uniform vec2 uRes,uSize;uniform float uY;',
    'float hash(vec2 p){vec3 v=fract(vec3(p.xyx)*.1031);v+=dot(v,v.yzx+33.33);return fract((v.x+v.y)*v.z);}'
  ];
  /* the sky: #006FD6 at the top, #0075DB at the foot of the buttons, then lighter down to a pale horizon */
  var SKYFS=HEAD.concat([
    'uniform float uT;',
    'void main(){',
    'float y=(1.-gl_FragCoord.y/uRes.y)*uSize.y,g=clamp((y-uY)/max(uSize.y-uY,1.),0.,1.);',
    'vec3 col=mix(vec3(0.,.435,.839),vec3(0.,.459,.859),clamp(y/max(uY,1.),0.,1.));',
    'col=mix(col,vec3(.071,.573,.953),smoothstep(0.,.55,g));',
    'col=mix(col,vec3(.396,.729,.976),smoothstep(.35,.9,g));',
    'col=mix(col,vec3(.78,.9,.992),smoothstep(.72,1.,g)*.85);',
    'gl_FragColor=vec4(col+(hash(gl_FragCoord.xy+fract(uT)*61.)-.5)/255.,1.);}'
  ]).join(String.fromCharCode(10));
  /* one cloud, drawn over the sky. uA: its box in the photograph (a negative width means mirrored). uB: where it is on screen (left,
     top), its size (px for each px of photograph) and its haze */
  var FS=HEAD.concat([
    'uniform vec2 uTx;uniform float uIn;uniform vec4 uA,uB;uniform sampler2D uS;',
    /* the photograph at a point (0 to 1 across and down), softened a touch so film grain does not read as cloud */
    'vec3 shot(vec2 u){vec2 e=1.6/uTx;return (2.*texture2D(uS,u).rgb+texture2D(uS,u+vec2(e.x,0.)).rgb+texture2D(uS,u-vec2(e.x,0.)).rgb+texture2D(uS,u+vec2(0.,e.y)).rgb+texture2D(uS,u-vec2(0.,e.y)).rgb)/6.;}',
    /* cloud or sky: sky is blue (blue well above red), cloud is white or grey. How blue clear sky is depends on the height in the photograph: pale near the horizon, deep higher up. Returns the colour and how much cloud (0 to 1); the faintest haze is dropped, so every cloud ends cleanly */
    'vec4 key(vec2 u){vec3 c=shot(u);float el='+ETOP+'.-u.y*'+(ETOP-EBOT)+',a=1.-smoothstep(.5,1.,(c.b-c.r)/max(c.b,.001)/(.1+.26*smoothstep(3.,16.,el)));return vec4(c,a*smoothstep(.04,.2,a));}',
    'void main(){',
    'vec2 px=vec2(gl_FragCoord.x/uRes.x,1.-gl_FragCoord.y/uRes.y)*uSize;',
    'vec2 sz=vec2(abs(uA.z),uA.w)*vec2('+TW+'.,'+TH+'.)*uB.z,l=(px-uB.xy)/sz;',
    'vec4 k=key(uA.xy+clamp(l,0.,1.)*uA.zw);',
    /* the box fades out over its last 16px (of photograph), so nothing at its edge shows as a cut */
    'vec2 e=min(l,1.-l)*sz/uB.z;float a=k.a*smoothstep(0.,16.,min(e.x,e.y));',
    /* clean the cloud up: thin edges go to white instead of to the photograph's own sky, greys become a soft pale blue */
    'vec3 cc=mix(vec3(1.),k.rgb,pow(k.a,.6));',
    'cc=mix(cc,mix(vec3(.7,.81,.96),vec3(1.),smoothstep(.5,.92,dot(cc,vec3(.2126,.7152,.0722)))),.9);',
    /* far clouds pale into the haze; and every cloud fades out just under the buttons */
    'cc=mix(cc,vec3(.86,.93,.995),uB.w);',
    'gl_FragColor=vec4(cc,a*smoothstep(uY+6.,uY+34.,px.y)*uIn);}'
  ]).join(String.fromCharCode(10));
  /* two drawings: the sky (P0), and one cloud (P1) laid over it at a time. Both are a rectangle, uQ: left, top, width, height in px (of uV, the hero) */
  var P0=null,P1=null,U0={},U1={},one=new Uint8Array(4);
  /* the photograph, the picture made from it on the drawing surface, how wide that was made, and how far the clouds have faded in */
  var pic=null,ptex=null,pw=0,shown=0;
  /* W, H: the hero. yb: where the band for the clouds starts (the foot of the buttons); hb: its height from 30px under the buttons to
     the bottom edge. S: screen px for each px of photograph, for a near cloud. CY: the length of each layer's round, in px; OFF: each
     cloud's place in it; AA: each cloud's box in the photograph, as fractions (a mirrored one starts at its right edge and runs backwards) */
  var W=0,H=0,yb=0,hb=200,S=.5,CY=[1,1,1],OFF=new Float32Array(N),AA=CL.map(function(c){return [(c[6]?c[0]+c[2]:c[0])/TW,c[1]/TH,(c[6]?-c[2]:c[2])/TW,c[3]/TH]});
  /* The photograph goes to the drawing surface no bigger than this screen can show, with smaller copies of itself so the far clouds
     do not shimmer as they move */
  function size(){var need=1.3*S*TW,p=need>2300?4096:need>1150?2048:1024,lim=gl.getParameter(gl.MAX_TEXTURE_SIZE);while(p>lim&&p>256)p/=2;return p}
  function paint(){
    pw=size();
    var c2=document.createElement('canvas');c2.width=pw;c2.height=pw/4;var x2=c2.getContext('2d');x2.imageSmoothingQuality='high';x2.drawImage(pic,0,0,pw,pw/4);
    gl.activeTexture(gl.TEXTURE1);if(ptex)gl.deleteTexture(ptex);ptex=gl.createTexture();gl.bindTexture(gl.TEXTURE_2D,ptex);
    gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_S,gl.CLAMP_TO_EDGE);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_T,gl.CLAMP_TO_EDGE);
    gl.texImage2D(gl.TEXTURE_2D,0,gl.RGB,gl.RGB,gl.UNSIGNED_BYTE,c2);gl.generateMipmap(gl.TEXTURE_2D);
    gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.LINEAR_MIPMAP_LINEAR);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MAG_FILTER,gl.LINEAR);
    gl.useProgram(P1);gl.uniform2f(U1.uTx,pw,pw/4);gl.activeTexture(gl.TEXTURE0);
  }
  function sh(type,src){var s=gl.createShader(type);gl.shaderSource(s,src);gl.compileShader(s);return gl.getShaderParameter(s,gl.COMPILE_STATUS)?s:null}
  function prog(v,fs,U,names){
    var f=sh(gl.FRAGMENT_SHADER,fs);if(!v||!f)return null;
    var pr=gl.createProgram();gl.attachShader(pr,v);gl.attachShader(pr,f);gl.bindAttribLocation(pr,0,'p');gl.linkProgram(pr);
    if(!gl.getProgramParameter(pr,gl.LINK_STATUS))return null;
    names.forEach(function(n){U[n]=gl.getUniformLocation(pr,n)});
    return pr;
  }
  function setup(){
    var v=sh(gl.VERTEX_SHADER,'attribute vec2 p;uniform vec2 uV;uniform vec4 uQ;void main(){vec2 c=(uQ.xy+p*uQ.zw)/uV;gl_Position=vec4(c.x*2.-1.,1.-c.y*2.,0.,1.);}');
    P0=prog(v,SKYFS,U0,['uQ','uV','uRes','uSize','uY','uT']);
    P1=prog(v,FS,U1,['uQ','uV','uRes','uSize','uY','uTx','uIn','uA','uB','uS']);
    if(!P0||!P1)return false;
    gl.bindBuffer(gl.ARRAY_BUFFER,gl.createBuffer());gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([0,0,1,0,0,1,0,1,1,0,1,1]),gl.STATIC_DRAW);
    gl.enableVertexAttribArray(0);gl.vertexAttribPointer(0,2,gl.FLOAT,false,0,0);
    gl.blendFunc(gl.SRC_ALPHA,gl.ONE_MINUS_SRC_ALPHA);
    gl.useProgram(P1);gl.uniform1i(U1.uS,1);
    ptex=null;if(pic)paint();
    return true;
  }
  if(!setup())return null;
  cv.className='sky';cv.setAttribute('aria-hidden','true');hero.insertBefore(cv,hero.firstChild);

  /* How big the canvas is drawn: at most this many pixels, stepping down the list on a device that needs it */
  var SIZES=[2200000,1400000,900000,520000,250000],lvl=0;
  /* The pointer: where it is in the hero and whether it is over it; and how far the clouds have followed it, across (par) and up or
     down (pay), each from -1 to 1. Across they stay where they were taken; up and down they come back once the pointer has left */
  var fixed=!!calm,ptx=0,pty=0,over=false,par=0,pay=0,gox=0;
  var T=fixed?40:40+Math.floor(Math.random()*900),prev=0,acc=0,drew=false,ema=0,cnt=0,hot=0,seen=true,run=false,slow=false,held=false,gone=false,ended=false,raf=0;
  /* how far a layer has travelled across: its drift, and its share of the way the clouds have followed the pointer (the near layer
     goes about a third of the band either way). And how far it has moved down (up, less: the near clouds must keep their feet
     below the bottom edge) */
  function way(l){return T*LAY[l][1]*hb/400+par*LAY[l][2]*hb/70}
  function drop(l){return pay*(pay<0?.5:1.3)*LAY[l][2]*hb/250}
  function fit(){
    var r=hero.getBoundingClientRect();W=Math.round(r.width);H=Math.round(r.height);
    if(W<2||H<2||gone)return;
    var s=Math.min(1,Math.sqrt(SIZES[lvl]/(W*H))),w=Math.max(2,Math.round(W*s)),h=Math.max(2,Math.round(H*s));
    if(cv.width!==w||cv.height!==h){cv.width=w;cv.height=h}
    gl.viewport(0,0,w,h);
    /* the foot of the buttons, measured on the words themselves */
    var b=0;
    [].forEach.call(copy.children,function(el){var g=document.createRange();g.selectNodeContents(el);var q=g.getBoundingClientRect();if(q.width>0)b=Math.max(b,q.bottom)});
    if(!b)b=copy.getBoundingClientRect().bottom;
    yb=Math.max(0,Math.min(H-40,b-r.top));
    [[P0,U0],[P1,U1]].forEach(function(o){gl.useProgram(o[0]);gl.uniform2f(o[1].uRes,w,h);gl.uniform2f(o[1].uSize,W,H);gl.uniform2f(o[1].uV,W,H);gl.uniform1f(o[1].uY,yb)});
    /* the clouds start 30px under the buttons; the near clouds stand a little taller than the band, with their feet below the bottom edge, and none is wider than the screen */
    hb=Math.max(40,H-yb-30);S=Math.min(1.2*hb/395,1.05*W/860);
    /* each layer's clouds are set out one after another round its round, which is long enough that no cloud is ever on screen twice */
    for(var l=0;l<3;l++){
      var k=S*LAY[l][0],sum=0,big=0;
      CL.forEach(function(c,i){if(c[4]===l){OFF[i]=sum;var cw=c[2]*c[7]*k;sum+=cw*LAY[l][4];big=Math.max(big,cw)}});
      CY[l]=Math.max(sum,W+big+60);
      CL.forEach(function(c,i){if(c[4]===l)OFF[i]*=CY[l]/sum});
    }
    if(pic&&size()!==pw)paint();
  }
  /* The sky first, then every cloud that is on screen right now, far ones first. Each layer goes round at its own pace, and follows
     the pointer by its own share: the near clouds most, the far ones least */
  function draw(){
    if(gone||!W)return;
    gl.disable(gl.BLEND);gl.useProgram(P0);gl.uniform1f(U0.uT,T);gl.uniform4f(U0.uQ,0,0,W,H);gl.drawArrays(gl.TRIANGLES,0,6);
    if(!pic||shown<=0)return;
    gl.enable(gl.BLEND);gl.useProgram(P1);gl.uniform1f(U1.uIn,shown);
    for(var i=0;i<N;i++){
      var c=CL[i],L=LAY[c[4]],k=S*L[0]*c[7],C=CY[c[4]],w=c[2]*k,h=c[3]*k,x=(OFF[i]+L[5]*C+way(c[4]))%C;
      x=(x<0?x+C:x)-w;
      if(x+w<0||x>W)continue;
      var y=H-c[5]*hb-h+drop(c[4]);
      gl.uniform4f(U1.uA,AA[i][0],AA[i][1],AA[i][2],AA[i][3]);gl.uniform4f(U1.uB,x,y,k,L[3]);
      gl.uniform4f(U1.uQ,x,y,w,h);gl.drawArrays(gl.TRIANGLES,0,6);
    }
  }
  /* each frame: the clouds glide after the pointer, and fade in once the photograph has arrived */
  function ease(dt){
    var goy=over?Math.max(-1,Math.min(1,(pty/H-.5)*2)):0,k=1-Math.exp(-dt/.45);
    if(over)gox=Math.max(-1,Math.min(1,(ptx/W-.5)*2));
    if(Math.abs(gox-par)>.002||Math.abs(goy-pay)>.002){par+=(gox-par)*k;pay+=(goy-pay)*k;hot=performance.now()+300}
    if(pic&&shown<1)shown=Math.min(1,shown+dt/1.1);
  }
  function halt(){slow=true;run=false;if(pic){shown=1;draw()}}
  function quit(){if(ended)return;ended=true;run=false;gone=true;if(ro)ro.disconnect();hero.classList.remove('live');hero.classList.add('flat');if(cv.parentNode)cv.parentNode.removeChild(cv)}
  function tick(now){
    raf=0;if(!run)return;
    raf=requestAnimationFrame(tick);
    var d=prev?now-prev:16;prev=now;
    /* a long run of slow frames after drawing means this device is not keeping up: draw smaller, or stop moving */
    if(drew&&d<400){ema=ema?ema*.9+d*.1:d;if(++cnt>45&&ema>50){cnt=0;ema=0;if(lvl<SIZES.length-1){lvl++;fit();draw()}else{halt();return}}}
    drew=false;acc+=Math.min(d,100);
    if(acc<(now<hot?13:30))return;
    var dt=acc/1000;acc=0;T+=dt;
    ease(dt);
    draw();drew=true;
  }
  function sync(){
    var want=seen&&!fixed&&!slow&&!held&&!gone;
    if(want&&!run){run=true;prev=0;if(!raf)raf=requestAnimationFrame(tick)}else if(!want)run=false;
  }
  /* A few timed drawings once the page has loaded set the size for this device. The quickest of them counts, since the page may be
     busy with something else at that moment. After a change of size it is timed again, and only if the smallest size is still too
     slow (over 14ms a frame) does the sky stay still. */
  function cost(){var t0=performance.now();draw();gl.readPixels(0,0,1,1,gl.RGBA,gl.UNSIGNED_BYTE,one);return performance.now()-t0}
  var best=1e9,tries=0;
  function gauge(){
    if(fixed||slow||held||gone)return;
    if(document.hidden||!W){setTimeout(gauge,600);return}
    best=Math.min(best,cost());
    if(++tries<3){setTimeout(gauge,350);return}
    var n=function(l){return Math.min(W*H,SIZES[l])},l=lvl;
    while(l<SIZES.length-1&&best*n(l)/n(lvl)>9)l++;
    if(l!==lvl){lvl=l;fit();draw();best=1e9;tries=0;setTimeout(gauge,350)}
    else if(best>14)halt();
  }
  fit();draw();
  requestAnimationFrame(function(){hero.classList.add('live')});
  /* the photograph: once it is in, the clouds fade in (at once for the still frame). If it cannot be read the stand-in takes over */
  var im=new Image();
  if(/^https?:/.test(SKY)&&SKY.indexOf(location.origin+'/')!==0)im.crossOrigin='anonymous';
  im.onload=function(){if(ended)return;try{pic=im;paint();if(fixed)shown=1;draw()}catch(e){pic=null;quit()}};
  im.onerror=quit;
  im.src=SKY;
  if(window.ResizeObserver){var ro=new ResizeObserver(function(){fit();draw()});ro.observe(hero);ro.observe(copy)}
  /* if the browser takes the drawing surface away, the stand-in photograph shows until it is given back */
  cv.addEventListener('webglcontextlost',function(e){e.preventDefault();run=false;gone=true;if(!ended){hero.classList.remove('live');hero.classList.add('flat')}});
  cv.addEventListener('webglcontextrestored',function(){gone=false;if(setup()){fit();draw();sync();hero.classList.remove('flat');hero.classList.add('live')}});
  /* the pointer is at x, y (px in the hero) */
  function move(x,y){ptx=x;pty=y;over=true;hot=performance.now()+400}
  if(!fixed){
    var at=function(e){var r=hero.getBoundingClientRect();move(e.clientX-r.left,e.clientY-r.top)},off=function(){over=false};
    hero.addEventListener('pointermove',function(e){if(e.pointerType!=='touch')at(e)},{passive:true});
    hero.addEventListener('pointerleave',off);
    hero.addEventListener('touchmove',function(e){var t=e.touches[0];if(t)at(t)},{passive:true});
    hero.addEventListener('touchend',off,{passive:true});
    hero.addEventListener('touchcancel',off,{passive:true});
    if('IntersectionObserver' in window)new IntersectionObserver(function(en){seen=en[0].isIntersecting;sync()}).observe(hero);
    sync();
    setTimeout(gauge,900);
  }
  /* cv.sky: for looking at the sky from the console: its state, hold (stop the clock, optionally at a given size from the list), a frame at a given time, the pointer put at a point (and taken away again) */
  return cv.sky={
    stop:function(){run=false;gone=true;ended=true;if(ro)ro.disconnect();var x=gl.getExtension('WEBGL_lose_context');if(x)x.loseContext()},
    hold:function(l){held=true;slow=false;run=false;if(l!=null){lvl=l;fit()}draw()},
    frame:function(t,dt){if(t!=null)T=t;if(pic&&!dt)shown=1;if(dt)ease(dt);draw()},
    point:move,leave:function(){over=false},
    state:function(){return {lvl:lvl,px:cv.width*cv.height,still:fixed||slow,run:run,par:+par.toFixed(3),pay:+pay.toFixed(3),photo:!!pic,wide:pw,shown:shown,band:Math.round(hb),scale:+S.toFixed(3)}}
  };
}
if(hero&&$('#bb-hero-copy')){try{initSky()}catch(e){}}

/* ---- the top bar, and the quick bar that takes over past the hero ---- */
var nav=$('#bb-nav');
if(nav){
  var dock=d.createElement('div');dock.id='bb-dock';dock.setAttribute('role','navigation');dock.setAttribute('aria-label','Quick navigation');
  $$('nav a',nav).forEach(function(a){var c=d.createElement('a');c.href=a.getAttribute('href')||'#';c.textContent=a.textContent.trim();dock.appendChild(c)});
  var cta=$$('a',nav).filter(function(a){return !a.closest('nav')&&!a.querySelector('img')})[0];
  if(cta){var g=d.createElement('a');g.href=cta.getAttribute('href')||'#';g.textContent=cta.textContent.trim();g.className='bb-go';dock.appendChild(g)}
  body.appendChild(dock);
  var tick=0,onScroll=function(){
    tick=0;var y=window.pageYOffset||0,h=hero?hero.offsetHeight:600,away=y>h-140,end=root.scrollHeight-(y+window.innerHeight)<240;
    root.classList.toggle('bb-scrolled',y>24);root.classList.toggle('bb-away',away);dock.classList.toggle('bb-show',away&&!end);
  };
  window.addEventListener('scroll',function(){if(!tick)tick=requestAnimationFrame(onScroll)},{passive:true});onScroll();
  /* phones: a menu button in the bar opens the same links */
  var mb=d.createElement('button'),mm=d.createElement('div');
  mb.type='button';mb.className='bb-menu-btn';mb.setAttribute('aria-label','Menu');mb.setAttribute('aria-expanded','false');
  mb.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 8h16M4 16h16"/></svg>';
  mm.className='bb-menu';mm.innerHTML=dock.innerHTML;
  nav.appendChild(mb);body.appendChild(mm);
  var menu=function(on){root.classList.toggle('bb-menu-open',on);mb.setAttribute('aria-expanded',on?'true':'false')};
  mb.addEventListener('click',function(){menu(!root.classList.contains('bb-menu-open'))});
  mm.addEventListener('click',function(e){if(e.target.closest('a'))menu(false)});
  d.addEventListener('keydown',function(e){if(e.key==='Escape')menu(false)});
}

/* ---- "We work with": one line of names separated by dots becomes a running list ---- */
var tk=$('#bb-ticker');
if(tk){
  var names=tk.textContent.split(/\s*[·•|]\s*/).map(function(s){return s.trim()}).filter(Boolean);
  if(names.length>2){
    var run=d.createElement('span'),html='';run.className='bb-tk';
    for(var k=0;k<2;k++)names.forEach(function(s){html+='<span'+(k?' aria-hidden="true"':'')+'>'+s.replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]})+'</span>'});
    run.innerHTML=html;tk.textContent='';tk.appendChild(run);tk.classList.add('bb-on');
  }
}

/* ---- work photos: the two rows slide against each other with the scroll ---- */
var rows=[$('#bb-work-a'),$('#bb-work-b')].filter(Boolean),work=$('#work'),pics=[];
rows.forEach(function(r){$$('img',r).forEach(function(im){pics.push(im.currentSrc||im.src)})});
if(rows.length&&work&&!calm){
  rows.forEach(function(r){
    var kids=[].slice.call(r.children);
    for(var k=0;k<2;k++)kids.forEach(function(c){var n=c.cloneNode(true);n.setAttribute('aria-hidden','true');n.removeAttribute('id');r.appendChild(n)});
    r.classList.add('bb-row');
  });
  work.classList.add('bb-rows');
  var room=[],was=-1,wt=0;
  var place=function(force){
    wt=0;var b=work.getBoundingClientRect(),vh=window.innerHeight,p=Math.max(0,Math.min(1,(vh-b.top)/(vh+b.height)));
    if(!force&&Math.abs(p-was)<.0005)return;was=p;
    rows.forEach(function(r,i){var x=i%2?-(room[i]*(1-p)):-(room[i]*p);r.style.transform='translate3d('+x.toFixed(1)+'px,0,0)'});
  };
  var measure=function(){room=rows.map(function(r){return Math.max(0,Math.min(r.scrollWidth-root.clientWidth,r.scrollWidth/3*1.2))});place(true)};
  window.addEventListener('scroll',function(){if(!wt)wt=requestAnimationFrame(function(){place()})},{passive:true});
  window.addEventListener('resize',measure);window.addEventListener('load',measure);measure();
}

/* ---- reviews: the cards take the middle place in turn (they stay Elementor cards; add or remove cards freely) ---- */
var rv=$('#bb-reviews');
if(rv&&rv.children.length>1){
  var cards=[].slice.call(rv.children),ri=0,rtimer=0,M=cards.length;
  var lay=function(){cards.forEach(function(c,k){var r=((k-ri)%M+M)%M;c.classList.toggle('bb-on',r===0);c.classList.toggle('bb-next',r===1&&M>1);c.classList.toggle('bb-prev',r===M-1&&M>2)})};
  var turnTo=function(i){
    var a=cards.map(function(c){return c.getBoundingClientRect()});ri=((i%M)+M)%M;lay();
    if(calm)return;
    cards.forEach(function(c,k){var b=c.getBoundingClientRect();if(!a[k].width||!b.width)return;c.style.transition='none';c.style.transformOrigin='0 0';c.style.transform='translate('+(a[k].left-b.left)+'px,'+(a[k].top-b.top)+'px) scale('+(a[k].width/b.width)+')'});
    requestAnimationFrame(function(){requestAnimationFrame(function(){cards.forEach(function(c){c.style.transition='transform .75s cubic-bezier(.2,.7,.2,1),opacity .5s ease';c.style.transform=''})})});
  };
  rv.classList.add('bb-live');lay();
  cards.forEach(function(c,k){c.addEventListener('click',function(){if(k!==ri){turnTo(k);clearInterval(rtimer);rtimer=-1}})});
  if(!calm&&'IntersectionObserver' in window)new IntersectionObserver(function(en){var on=en[0].isIntersecting;if(on&&!rtimer)rtimer=setInterval(function(){if(!d.hidden&&!rv.matches(':hover'))turnTo(ri+1)},4200);else if(!on&&rtimer>0){clearInterval(rtimer);rtimer=0}},{threshold:.25}).observe(rv);
}

/* ---- comparison table: the words Yes, Sometimes and No are shown as marks ---- */
$$('#why p').forEach(function(p){
  var t=p.textContent.trim().toLowerCase(),k=t==='yes'?'y':t==='sometimes'?'h':t==='no'?'n':'';
  if(k){p.setAttribute('role','img');p.setAttribute('aria-label',p.textContent.trim());p.classList.add('bb-mark','bb-mark-'+k)}
});

/* ---- how it works: the step cards become tabs with one card on show ---- */
var how=$('#how'),steps=how?$$('.bb-step-card',how):[];
if(how&&steps.length>1){
  var sgrid=steps[0].parentNode,bar=d.createElement('div'),si=0,stimer=0;
  bar.className='bbx-tabs';bar.setAttribute('role','tablist');bar.setAttribute('aria-label','Steps');
  steps.forEach(function(c,i){
    var lab=$('.bb-label',c),name=(lab?lab.textContent:'Step '+(i+1)).split(/[\u00B7\u2022:\u2013\u2014]/).pop().trim(),b=d.createElement('button');
    b.type='button';b.className='bbx-tab';b.setAttribute('role','tab');b.innerHTML='<span class="bbx-tab-n">'+(i+1)+'</span><span class="bbx-tab-t"></span><i class="bbx-tab-p"></i>';
    b.querySelector('.bbx-tab-t').textContent=name;b.addEventListener('click',function(){pick(i);clearInterval(stimer);stimer=-1;bar.classList.remove('bb-run')});
    bar.appendChild(b);c.setAttribute('role','tabpanel');
  });
  sgrid.parentNode.insertBefore(bar,sgrid);sgrid.classList.add('bbx-steps');
  var stabs=[].slice.call(bar.children);
  var pick=function(i){si=i;steps.forEach(function(c,k){c.classList.toggle('bb-on',k===i)});stabs.forEach(function(t,k){t.classList.toggle('bb-on',k===i);t.setAttribute('aria-selected',k===i?'true':'false')})};
  pick(0);
  if(!calm&&'IntersectionObserver' in window)new IntersectionObserver(function(en){var on=en[0].isIntersecting;if(on&&!stimer){bar.classList.add('bb-run');pick(si);stimer=setInterval(function(){if(!d.hidden)pick((si+1)%steps.length)},6000)}else if(!on&&stimer>0){clearInterval(stimer);stimer=0;bar.classList.remove('bb-run')}},{threshold:.3}).observe(how);
}

/* ---- book a call: the section opens as a window over the page from every "Book a call" link ---- */
var call=$('#call');
if(call){
  var box=call.firstElementChild||call,x=d.createElement('button'),lastF=null;
  x.type='button';x.className='bb-dlg-x';x.setAttribute('aria-label','Close');
  x.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>';
  box.appendChild(x);call.classList.add('bb-dlg');call.setAttribute('role','dialog');call.setAttribute('aria-modal','true');call.setAttribute('aria-label','Book a call');
  var shut=function(){if(!root.classList.contains('bb-call-open'))return;root.classList.remove('bb-call-open');if(lastF&&lastF.focus)lastF.focus()};
  var openCall=function(){lastF=d.activeElement;root.classList.add('bb-call-open');root.classList.remove('bb-menu-open');var f=call.querySelector('input,select,textarea');if(f)setTimeout(function(){f.focus()},80)};
  d.addEventListener('click',function(e){
    var a=e.target.closest?e.target.closest('a[href$="#call"]'):null;
    if(a){e.preventDefault();openCall();return}
    if(e.target===call||(e.target.closest&&e.target.closest('.bb-dlg-x')))shut();
    else if(call.contains(e.target)&&e.target.closest('a[href^="#"]'))shut();
  });
  d.addEventListener('keydown',function(e){if(e.key==='Escape')shut()});
  if(location.hash==='#call')openCall();
}

/* ---- closing: the work photos in an arch that drifts slowly ---- */
var closing=$('#closing');
if(closing&&pics.length>3){
  var ring=d.createElement('div'),N=10,STEP=24,SPAN=N*STEP,tiles=[],rot=0,last=0,seen=false;
  ring.id='bb-ring';ring.setAttribute('aria-hidden','true');
  for(var i=0;i<N;i++){var t=d.createElement('div'),im=new Image();t.className='bb-tile';im.alt='';im.decoding='async';im.loading='lazy';im.src=pics[i%pics.length];t.appendChild(im);ring.appendChild(t);tiles.push(t)}
  closing.insertBefore(ring,closing.firstChild);closing.classList.add('bb-arch');
  var arc=function(){tiles.forEach(function(t,i){var a=((i*STEP+rot)%SPAN+SPAN)%SPAN-SPAN/2;t.style.setProperty('--a',a.toFixed(2)+'deg');t.style.opacity=Math.abs(a)>104?0:1})};
  var turn=function(ts){if(!seen)return;if(last)rot+=Math.min(60,ts-last)*.0032;last=ts;arc();requestAnimationFrame(turn)};
  arc();
  if(!calm&&'IntersectionObserver' in window)new IntersectionObserver(function(en){var on=en[0].isIntersecting;if(on&&!seen){seen=true;last=0;requestAnimationFrame(turn)}else if(!on)seen=false}).observe(closing);
}

/* ---- things rise into view once ---- */
if(!calm&&'IntersectionObserver' in window){
  var tg=[];
  $$('.elementor>*').forEach(function(sec){
    if(sec.id==='call')return;
    var box=sec.children.length===1?sec.firstElementChild:sec;
    [].slice.call(box.children).forEach(function(c){
      if(c.id==='bb-nav'||c.id==='bb-ring'||c.classList.contains('sky')||c.classList.contains('bb-row')||c.classList.contains('bb-menu-btn'))return;
      var cs=getComputedStyle(c),many=c.children.length>1&&c.id!=='bb-reviews'&&!c.classList.contains('bbx-steps')&&!c.querySelector('#bb-ticker');
      if(many&&(cs.display==='grid'||cs.display==='inline-grid'))[].slice.call(c.children).forEach(function(k,i){k.style.setProperty('--d',(i%4)*70+'ms');tg.push(k)});
      else tg.push(c);
    });
  });
  var io=new IntersectionObserver(function(en){en.forEach(function(e){
    if(!e.isIntersecting)return;var el=e.target;io.unobserve(el);el.classList.add('bb-in');
    setTimeout(function(){el.classList.remove('bb-rv','bb-in');el.style.removeProperty('--d')},1300);
  })},{rootMargin:'0px 0px -6% 0px',threshold:.06});
  tg.forEach(function(el){el.classList.add('bb-rv');io.observe(el)});
}
})();
