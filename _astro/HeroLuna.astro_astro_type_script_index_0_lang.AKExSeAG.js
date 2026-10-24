var e=document.querySelector(`[data-luna]`),t=e?.querySelector(`[data-gl]`),n=t?.getContext(`webgl`,{premultipliedAlpha:!0,alpha:!0,antialias:!1});if(e&&t&&!n&&e.classList.add(`senza-gl`),e&&t&&n){let r=matchMedia(`(prefers-reduced-motion: reduce)`).matches,i=`
      float hash(vec2 p){ return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453); }
      float noise(vec2 p){
        vec2 i = floor(p), f = fract(p); f = f * f * (3.0 - 2.0 * f);
        return mix(mix(hash(i), hash(i + vec2(1, 0)), f.x), mix(hash(i + vec2(0, 1)), hash(i + vec2(1, 1)), f.x), f.y);
      }`,a=`precision highp float; varying vec2 uv;
      uniform sampler2D prec; uniform vec2 dim, da, a; uniform float raggio, tempo, sbiadisci;
      ${i}
      float segmento(vec2 p, vec2 a0, vec2 b0){ vec2 pa = p - a0, ba = b0 - a0; float h = clamp(dot(pa, ba) / max(dot(ba, ba), 1e-4), 0.0, 1.0); return length(pa - ba * h); }
      void main(){
        // La scia ondeggia come un liquido: si legge il fotogramma prima da un punto spostato dal rumore.
        vec2 n = vec2(noise(uv * 6.0 + tempo * 0.4), noise(uv * 6.0 + 7.3 - tempo * 0.4)) - 0.5;
        float v = texture2D(prec, uv + n * 0.004).r * sbiadisci - 0.0015;
        vec2 px = uv * dim;
        float d = segmento(px, da, a);
        float m = clamp(1.0 - d / raggio, 0.0, 1.0);
        gl_FragColor = vec4(vec3(max(v, m)), 1.0);
      }`,o=`precision highp float; varying vec2 uv;
      uniform sampler2D scia, luna; uniform vec2 dim, centro, testa; uniform float diametro, tempo, buio, raggioTesta;
      ${i}
      void main(){
        vec2 px = uv * dim;
        vec2 e = 1.5 / dim;
        float h = clamp(1.0 - length(px - testa) / raggioTesta, 0.0, 1.0);
        float m = texture2D(scia, uv).r + h * h * 1.6;
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
      }`;function s(e){let t=n.createProgram();for(let[r,i]of[[n.VERTEX_SHADER,`attribute vec2 p; varying vec2 uv; void main(){ uv = p * 0.5 + 0.5; gl_Position = vec4(p, 0.0, 1.0); }`],[n.FRAGMENT_SHADER,e]]){let e=n.createShader(r);n.shaderSource(e,i),n.compileShader(e),n.getShaderParameter(e,n.COMPILE_STATUS)||console.error(n.getShaderInfoLog(e)),n.attachShader(t,e)}n.bindAttribLocation(t,0,`p`),n.linkProgram(t);let r={},i=n.getProgramParameter(t,n.ACTIVE_UNIFORMS);for(let e=0;e<i;e++){let i=n.getActiveUniform(t,e).name;r[i]=n.getUniformLocation(t,i)}return{p:t,u:r}}let c=s(a),l=s(o);n.bindBuffer(n.ARRAY_BUFFER,n.createBuffer()),n.bufferData(n.ARRAY_BUFFER,new Float32Array([-1,-1,1,-1,-1,1,1,1]),n.STATIC_DRAW),n.enableVertexAttribArray(0),n.vertexAttribPointer(0,2,n.FLOAT,!1,0,0);function u(e,t){let r=n.createTexture();n.bindTexture(n.TEXTURE_2D,r),n.texImage2D(n.TEXTURE_2D,0,n.RGBA,e,t,0,n.RGBA,n.UNSIGNED_BYTE,null);for(let[e,t]of[[n.TEXTURE_MIN_FILTER,n.LINEAR],[n.TEXTURE_MAG_FILTER,n.LINEAR],[n.TEXTURE_WRAP_S,n.CLAMP_TO_EDGE],[n.TEXTURE_WRAP_T,n.CLAMP_TO_EDGE]])n.texParameteri(n.TEXTURE_2D,e,t);let i=n.createFramebuffer();return n.bindFramebuffer(n.FRAMEBUFFER,i),n.framebufferTexture2D(n.FRAMEBUFFER,n.COLOR_ATTACHMENT0,n.TEXTURE_2D,r,0),n.clearColor(0,0,0,1),n.clear(n.COLOR_BUFFER_BIT),{t:r,fb:i}}let d=n.createTexture(),f=!1,p=new Image;p.onload=()=>{n.bindTexture(n.TEXTURE_2D,d),n.pixelStorei(n.UNPACK_PREMULTIPLY_ALPHA_WEBGL,!0),n.texImage2D(n.TEXTURE_2D,0,n.RGBA,n.RGBA,n.UNSIGNED_BYTE,p);for(let[e,t]of[[n.TEXTURE_MIN_FILTER,n.LINEAR],[n.TEXTURE_MAG_FILTER,n.LINEAR],[n.TEXTURE_WRAP_S,n.CLAMP_TO_EDGE],[n.TEXTURE_WRAP_T,n.CLAMP_TO_EDGE]])n.texParameteri(n.TEXTURE_2D,e,t);if(f=!0,r)for(let e=0;e<40;e++)P(performance.now())},p.src=t.dataset.src;let m=0,h=0,g=1,_=160,v=30,y=0,b=0,x=0,S=[],C=0,w=0,T={x:0,y:0},E={x:0,y:0},D={x:0,y:0},O=-1e9,k=!0,A=0,j=!1,M=0;function N(){let n=e.getBoundingClientRect();m=n.width,h=n.height,g=Math.min(devicePixelRatio||1,1.5),t.width=Math.round(m*g),t.height=Math.round(h*g);let r=m>=768;y=r?Math.max(h*1.5,m*.95):Math.max(m*1.5,h*.7),b=r?m*.64:m*.6,x=r?h*.58:h*.4,_=r?Math.min(380,Math.max(200,m*.2)):m*.42,v=_*.16,C=Math.max(2,Math.round(m/2)),w=Math.max(2,Math.round(h/2)),S=[u(C,w),u(C,w)],j||(j=!0,T.x=E.x=D.x=m*.62,T.y=E.y=D.y=h*.5)}function P(e){if(A=0,!f){k&&!r&&(A=requestAnimationFrame(P));return}let i=e/1e3,a=M?Math.min(4,(e-M)/16.67):1;M=e,!r&&e-O>2500&&(T.x=m*.5+Math.cos(i*.35)*m*.3,T.y=h*.5+Math.sin(i*.55)*h*.26),D={...E};let o=1-.84**a;E.x+=(T.x-E.x)*o,E.y+=(T.y-E.y)*o;let[s,u]=S;n.bindFramebuffer(n.FRAMEBUFFER,u.fb),n.viewport(0,0,C,w),n.useProgram(c.p),n.activeTexture(n.TEXTURE0),n.bindTexture(n.TEXTURE_2D,s.t),n.uniform1i(c.u.prec,0),n.uniform2f(c.u.dim,m,h),n.uniform2f(c.u.da,D.x,h-D.y),n.uniform2f(c.u.a,E.x,h-E.y),n.uniform1f(c.u.raggio,v),n.uniform1f(c.u.tempo,i),n.uniform1f(c.u.sbiadisci,.9935**a),n.drawArrays(n.TRIANGLE_STRIP,0,4),S=[u,s],n.bindFramebuffer(n.FRAMEBUFFER,null),n.viewport(0,0,t.width,t.height),n.clearColor(0,0,0,0),n.clear(n.COLOR_BUFFER_BIT),n.useProgram(l.p),n.activeTexture(n.TEXTURE0),n.bindTexture(n.TEXTURE_2D,u.t),n.uniform1i(l.u.scia,0),n.activeTexture(n.TEXTURE1),n.bindTexture(n.TEXTURE_2D,d),n.uniform1i(l.u.luna,1),n.uniform2f(l.u.dim,m,h),n.uniform2f(l.u.centro,b,h-x),n.uniform1f(l.u.diametro,y),n.uniform1f(l.u.tempo,i),n.uniform2f(l.u.testa,E.x,h-E.y),n.uniform1f(l.u.raggioTesta,_),n.uniform1f(l.u.buio,.1),n.drawArrays(n.TRIANGLE_STRIP,0,4),k&&!r&&(A=requestAnimationFrame(P))}function F(t,n){let r=e.getBoundingClientRect();O=performance.now(),T.x=t-r.left,T.y=n-r.top}N();let I=0;addEventListener(`resize`,()=>{clearTimeout(I),I=window.setTimeout(()=>{if(N(),r)for(let e=0;e<40;e++)P(performance.now())},150)}),r||(e.addEventListener(`pointermove`,e=>F(e.clientX,e.clientY)),e.addEventListener(`pointerdown`,e=>F(e.clientX,e.clientY)),e.addEventListener(`touchmove`,e=>F(e.touches[0].clientX,e.touches[0].clientY),{passive:!0}),new IntersectionObserver(([e])=>{k=e.isIntersecting,k&&!A&&(A=requestAnimationFrame(P))}).observe(e))}