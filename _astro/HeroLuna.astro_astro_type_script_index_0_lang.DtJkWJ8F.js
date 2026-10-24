var e=document.querySelector(`[data-luna]`),t=e?.querySelector(`[data-gl]`),n=t?.getContext(`webgl`,{premultipliedAlpha:!0,alpha:!0,antialias:!1});if(e&&t&&!n&&e.classList.add(`senza-gl`),e&&t&&n){let r=matchMedia(`(prefers-reduced-motion: reduce)`).matches;function i(e){let t=n.createProgram();for(let[r,i]of[[n.VERTEX_SHADER,`attribute vec2 p; varying vec2 uv; void main(){ uv = p * 0.5 + 0.5; gl_Position = vec4(p, 0.0, 1.0); }`],[n.FRAGMENT_SHADER,e]]){let e=n.createShader(r);n.shaderSource(e,i),n.compileShader(e),n.getShaderParameter(e,n.COMPILE_STATUS)||console.error(n.getShaderInfoLog(e)),n.attachShader(t,e)}n.bindAttribLocation(t,0,`p`),n.linkProgram(t);let r={},i=n.getProgramParameter(t,n.ACTIVE_UNIFORMS);for(let e=0;e<i;e++){let i=n.getActiveUniform(t,e).name;r[i]=n.getUniformLocation(t,i)}return{p:t,u:r}}let a=i(`precision highp float; varying vec2 uv;
      uniform vec3 punti[64]; uniform int n; uniform vec2 dim; uniform float morbido;
      float smin(float a, float b, float k){ float h = clamp(0.5 + 0.5 * (b - a) / k, 0.0, 1.0); return mix(b, a, h) - k * h * (1.0 - h); }
      void main(){
        vec2 px = uv * dim;
        float d = length(px - punti[0].xy) - punti[0].z;
        for (int i = 0; i < 63; i++) {
          if (i >= n - 1) break;
          vec3 A = punti[i], B = punti[i + 1];
          vec2 pa = px - A.xy, ba = B.xy - A.xy;
          float h = clamp(dot(pa, ba) / max(dot(ba, ba), 1e-4), 0.0, 1.0);
          d = smin(d, length(pa - ba * h) - mix(A.z, B.z, h), morbido);
        }
        // 0.42 = bordo della forma (la soglia del passaggio "luna"); dentro sale, fuori scende.
        gl_FragColor = vec4(vec3(clamp(0.42 - d / 60.0, 0.0, 1.0)), 1.0);
      }`),o=i(`precision highp float; varying vec2 uv;
      uniform sampler2D scia, luna; uniform vec2 dim, centro; uniform float diametro, tempo, buio;
      
      float hash(vec2 p){ return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453); }
      float noise(vec2 p){
        vec2 i = floor(p), f = fract(p); f = f * f * (3.0 - 2.0 * f);
        return mix(mix(hash(i), hash(i + vec2(1, 0)), f.x), mix(hash(i + vec2(0, 1)), hash(i + vec2(1, 1)), f.x), f.y);
      }
      void main(){
        vec2 px = uv * dim;
        vec2 e = 1.5 / dim;
        float m = texture2D(scia, uv).r;
        // Bordo liquido: la soglia oscilla col rumore.
        float s = 0.42 + (noise(px * 0.012 + tempo * 0.5) - 0.5) * 0.12;
        float dentro = smoothstep(s - 0.02, s + 0.02, m);
        // Rifrazione: vicino al bordo la luna si deforma seguendo la pendenza della scia.
        vec2 g = vec2(texture2D(scia, uv + vec2(e.x, 0.0)).r - texture2D(scia, uv - vec2(e.x, 0.0)).r,
                      texture2D(scia, uv + vec2(0.0, e.y)).r - texture2D(scia, uv - vec2(0.0, e.y)).r);
        vec2 lp = (px - centro) / diametro + 0.5 - g * 0.06 * dentro;
        lp.y = 1.0 - lp.y;
        // Fuori dall'immagine niente: con CLAMP_TO_EDGE il bordo si allungherebbe in strisce.
        vec4 c = texture2D(luna, lp) * step(0.0, lp.x) * step(lp.x, 1.0) * step(0.0, lp.y) * step(lp.y, 1.0);
        float bordo = (smoothstep(s - 0.02, s + 0.02, m) - smoothstep(s + 0.02, s + 0.12, m));
        // Dentro la scia la luna è più chiara: il testo in "difference" diventa scuro e ben leggibile.
        vec3 chiara = c.a - (c.a - c.rgb) * 0.4;
        vec3 col = mix(c.rgb, chiara, dentro) + bordo * 0.18 * c.a;
        gl_FragColor = vec4(col, c.a) * mix(buio, 1.0, dentro);
      }`);n.bindBuffer(n.ARRAY_BUFFER,n.createBuffer()),n.bufferData(n.ARRAY_BUFFER,new Float32Array([-1,-1,1,-1,-1,1,1,1]),n.STATIC_DRAW),n.enableVertexAttribArray(0),n.vertexAttribPointer(0,2,n.FLOAT,!1,0,0);function s(e,t){let r=n.createTexture();n.bindTexture(n.TEXTURE_2D,r),n.texImage2D(n.TEXTURE_2D,0,n.RGBA,e,t,0,n.RGBA,n.UNSIGNED_BYTE,null);for(let[e,t]of[[n.TEXTURE_MIN_FILTER,n.LINEAR],[n.TEXTURE_MAG_FILTER,n.LINEAR],[n.TEXTURE_WRAP_S,n.CLAMP_TO_EDGE],[n.TEXTURE_WRAP_T,n.CLAMP_TO_EDGE]])n.texParameteri(n.TEXTURE_2D,e,t);let i=n.createFramebuffer();return n.bindFramebuffer(n.FRAMEBUFFER,i),n.framebufferTexture2D(n.FRAMEBUFFER,n.COLOR_ATTACHMENT0,n.TEXTURE_2D,r,0),n.clearColor(0,0,0,1),n.clear(n.COLOR_BUFFER_BIT),{t:r,fb:i}}let c=n.createTexture(),l=!1,u=new Image;u.onload=()=>{n.bindTexture(n.TEXTURE_2D,c),n.pixelStorei(n.UNPACK_PREMULTIPLY_ALPHA_WEBGL,!0),n.texImage2D(n.TEXTURE_2D,0,n.RGBA,n.RGBA,n.UNSIGNED_BYTE,u);for(let[e,t]of[[n.TEXTURE_MIN_FILTER,n.LINEAR],[n.TEXTURE_MAG_FILTER,n.LINEAR],[n.TEXTURE_WRAP_S,n.CLAMP_TO_EDGE],[n.TEXTURE_WRAP_T,n.CLAMP_TO_EDGE]])n.texParameteri(n.TEXTURE_2D,e,t);if(l=!0,r)for(let e=0;e<40;e++)P(performance.now())},u.src=t.dataset.src;let d=0,f=0,p=1,m=160,h=2e3,g=30,_=0,v=0,y=0,b=[],x=5e3,S=new Float32Array(192),C=[],w=0,T=0,E={x:0,y:0},D={x:0,y:0},O=-1e9,k=!0,A=0,j=!1,M=0;function N(){let n=e.getBoundingClientRect();d=n.width,f=n.height,p=Math.min(devicePixelRatio||1,1.5),t.width=Math.round(d*p),t.height=Math.round(f*p);let r=d>=768;_=r?Math.max(f*1.5,d*.95):Math.max(d*1.5,f*.7),v=r?d*.64:d*.6,y=r?f*.58:f*.4,m=r?Math.min(220,Math.max(120,d*.115)):d*.17,h=Math.max(d,f)*2.2,g=h/60,w=Math.max(2,Math.round(d/2)),T=Math.max(2,Math.round(f/2)),C=[s(w,T)],j||(j=!0,E.x=D.x=d*.62,E.y=D.y=f*.5)}function P(e){if(A=0,!l){k&&!r&&(A=requestAnimationFrame(P));return}let i=e/1e3,s=M?Math.min(4,(e-M)/16.67):1;M=e,!r&&e-O>2500&&(E.x=d*.5+Math.cos(i*.35)*d*.3,E.y=f*.5+Math.sin(i*.55)*f*.26);let u=1-.84**s;D.x+=(E.x-D.x)*u,D.y+=(E.y-D.y)*u;let p=b[0]??{x:D.x,y:D.y,t:e};b.length||b.push(p);let j=Math.hypot(D.x-p.x,D.y-p.y);for(;j>=g;){let t=g/j;p={x:p.x+(D.x-p.x)*t,y:p.y+(D.y-p.y)*t,t:e},b.unshift(p),j-=g}for(;b.length>62||b.length&&e-b[b.length-1].t>x;)b.pop();let N=0,F=0,I=D.x,L=D.y;S.set([D.x,f-D.y,m],0),N=1;for(let t of b){F+=Math.hypot(t.x-I,t.y-L),I=t.x,L=t.y;let n=Math.min(1,(x-(e-t.t))/1200),r=m*Math.max(0,1-F/h)*Math.max(0,n);if(S.set([t.x,f-t.y,r],N*3),N++,r<=.5)break}let R=C[0];n.bindFramebuffer(n.FRAMEBUFFER,R.fb),n.viewport(0,0,w,T),n.useProgram(a.p),n.uniform3fv(a.u[`punti[0]`],S),n.uniform1i(a.u.n,N),n.uniform2f(a.u.dim,d,f),n.uniform1f(a.u.morbido,m*.35),n.drawArrays(n.TRIANGLE_STRIP,0,4),n.bindFramebuffer(n.FRAMEBUFFER,null),n.viewport(0,0,t.width,t.height),n.clearColor(0,0,0,0),n.clear(n.COLOR_BUFFER_BIT),n.useProgram(o.p),n.activeTexture(n.TEXTURE0),n.bindTexture(n.TEXTURE_2D,R.t),n.uniform1i(o.u.scia,0),n.activeTexture(n.TEXTURE1),n.bindTexture(n.TEXTURE_2D,c),n.uniform1i(o.u.luna,1),n.uniform2f(o.u.dim,d,f),n.uniform2f(o.u.centro,v,f-y),n.uniform1f(o.u.diametro,_),n.uniform1f(o.u.tempo,i),n.uniform1f(o.u.buio,.1),n.drawArrays(n.TRIANGLE_STRIP,0,4),k&&!r&&(A=requestAnimationFrame(P))}function F(t,n){let r=e.getBoundingClientRect();O=performance.now(),E.x=t-r.left,E.y=n-r.top}N();let I=0;addEventListener(`resize`,()=>{clearTimeout(I),I=window.setTimeout(()=>{if(N(),r)for(let e=0;e<40;e++)P(performance.now())},150)}),r||(e.addEventListener(`pointermove`,e=>F(e.clientX,e.clientY)),e.addEventListener(`pointerdown`,e=>F(e.clientX,e.clientY)),e.addEventListener(`touchmove`,e=>F(e.touches[0].clientX,e.touches[0].clientY),{passive:!0}),new IntersectionObserver(([e])=>{k=e.isIntersecting,k&&!A&&(A=requestAnimationFrame(P))}).observe(e))}