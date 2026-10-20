var e={TEXTURE_DOWNSAMPLE:1,DENSITY_DISSIPATION:.98,VELOCITY_DISSIPATION:.99,PRESSURE_DISSIPATION:.8,PRESSURE_ITERATIONS:25,CURL:35,SPLAT_RADIUS:.002,COLOR_EVERY_MOVES:25},t=`
  precision highp float;
  attribute vec2 aPosition;
  varying vec2 vUv, vL, vR, vT, vB;
  uniform vec2 texelSize;
  void main () {
    vUv = aPosition * 0.5 + 0.5;
    vL = vUv - vec2(texelSize.x, 0.0);
    vR = vUv + vec2(texelSize.x, 0.0);
    vT = vUv + vec2(0.0, texelSize.y);
    vB = vUv - vec2(0.0, texelSize.y);
    gl_Position = vec4(aPosition, 0.0, 1.0);
  }`,n=`precision highp float; precision highp sampler2D; varying vec2 vUv, vL, vR, vT, vB;`,r={clear:`${n}
    uniform sampler2D uTexture; uniform float value;
    void main () { gl_FragColor = value * texture2D(uTexture, vUv); }`,splat:`${n}
    uniform sampler2D uTarget; uniform float aspectRatio; uniform vec3 color; uniform vec2 point; uniform float radius;
    void main () {
      vec2 p = vUv - point;
      p.x *= aspectRatio;
      vec3 splat = exp(-dot(p, p) / radius) * color;
      gl_FragColor = vec4(texture2D(uTarget, vUv).xyz + splat, 1.0);
    }`,advection:`${n}
    uniform sampler2D uVelocity, uSource; uniform vec2 texelSize; uniform float dt, dissipation;
    void main () {
      vec2 coord = vUv - dt * texture2D(uVelocity, vUv).xy * texelSize;
      gl_FragColor = dissipation * texture2D(uSource, coord);
    }`,divergence:`${n}
    uniform sampler2D uVelocity;
    void main () {
      float L = texture2D(uVelocity, vL).x;
      float R = texture2D(uVelocity, vR).x;
      float T = texture2D(uVelocity, vT).y;
      float B = texture2D(uVelocity, vB).y;
      vec2 C = texture2D(uVelocity, vUv).xy;
      if (vL.x < 0.0) { L = -C.x; }
      if (vR.x > 1.0) { R = -C.x; }
      if (vT.y > 1.0) { T = -C.y; }
      if (vB.y < 0.0) { B = -C.y; }
      gl_FragColor = vec4(0.5 * (R - L + T - B), 0.0, 0.0, 1.0);
    }`,curl:`${n}
    uniform sampler2D uVelocity;
    void main () {
      float L = texture2D(uVelocity, vL).y;
      float R = texture2D(uVelocity, vR).y;
      float T = texture2D(uVelocity, vT).x;
      float B = texture2D(uVelocity, vB).x;
      gl_FragColor = vec4(0.5 * (R - L - T + B), 0.0, 0.0, 1.0);
    }`,vorticity:`${n}
    uniform sampler2D uVelocity, uCurl; uniform float curl, dt;
    void main () {
      float L = texture2D(uCurl, vL).x;
      float R = texture2D(uCurl, vR).x;
      float T = texture2D(uCurl, vT).x;
      float B = texture2D(uCurl, vB).x;
      float C = texture2D(uCurl, vUv).x;
      vec2 force = 0.5 * vec2(abs(T) - abs(B), abs(R) - abs(L));
      force /= length(force) + 0.0001;
      force *= curl * C;
      force.y *= -1.0;
      vec2 vel = texture2D(uVelocity, vUv).xy + force * dt;
      gl_FragColor = vec4(clamp(vel, -1000.0, 1000.0), 0.0, 1.0);
    }`,pressure:`${n}
    uniform sampler2D uPressure, uDivergence;
    void main () {
      float L = texture2D(uPressure, vL).x;
      float R = texture2D(uPressure, vR).x;
      float T = texture2D(uPressure, vT).x;
      float B = texture2D(uPressure, vB).x;
      float div = texture2D(uDivergence, vUv).x;
      gl_FragColor = vec4((L + R + B + T - div) * 0.25, 0.0, 0.0, 1.0);
    }`,gradientSubtract:`${n}
    uniform sampler2D uPressure, uVelocity;
    void main () {
      float L = texture2D(uPressure, vL).x;
      float R = texture2D(uPressure, vR).x;
      float T = texture2D(uPressure, vT).x;
      float B = texture2D(uPressure, vB).x;
      vec2 vel = texture2D(uVelocity, vUv).xy - vec2(R - L, T - B);
      gl_FragColor = vec4(vel, 0.0, 1.0);
    }`,display:`${n}
    uniform sampler2D uTexture; uniform vec3 background;
    void main () {
      vec3 c = texture2D(uTexture, vUv).rgb;
      gl_FragColor = vec4(min(background + c, vec3(1.0)), 1.0);
    }`};function i(n,i){let a={alpha:!1,depth:!1,stencil:!1,antialias:!1,preserveDrawingBuffer:!1},o=n.getContext(`webgl2`,a),s=!!o;if(o||=n.getContext(`webgl`,a),!o)return null;let c,l;if(s){let e=o;if(!e.getExtension(`EXT_color_buffer_float`)&&!e.getExtension(`EXT_color_buffer_half_float`))return null;c=e.RGBA16F,l=e.HALF_FLOAT}else{let e=o.getExtension(`OES_texture_half_float`);if(!e||!o.getExtension(`OES_texture_half_float_linear`))return null;c=o.RGBA,l=e.HALF_FLOAT_OES}let u=o,d=i.background??[0,0,0];function f(e,t){let n=u.createShader(e);if(u.shaderSource(n,t),u.compileShader(n),!u.getShaderParameter(n,u.COMPILE_STATUS))throw Error(u.getShaderInfoLog(n)??`shader`);return n}let p=f(u.VERTEX_SHADER,t);function m(e){let t=u.createProgram();if(u.attachShader(t,p),u.attachShader(t,f(u.FRAGMENT_SHADER,e)),u.bindAttribLocation(t,0,`aPosition`),u.linkProgram(t),!u.getProgramParameter(t,u.LINK_STATUS))throw Error(u.getProgramInfoLog(t)??`program`);let n={},r=u.getProgramParameter(t,u.ACTIVE_UNIFORMS);for(let e=0;e<r;e++){let r=u.getActiveUniform(t,e).name;n[r]=u.getUniformLocation(t,r)}return{program:t,uniforms:n}}let h;try{h=Object.fromEntries(Object.entries(r).map(([e,t])=>[e,m(t)]))}catch{return null}u.bindBuffer(u.ARRAY_BUFFER,u.createBuffer()),u.bufferData(u.ARRAY_BUFFER,new Float32Array([-1,-1,-1,1,1,1,1,-1]),u.STATIC_DRAW),u.bindBuffer(u.ELEMENT_ARRAY_BUFFER,u.createBuffer()),u.bufferData(u.ELEMENT_ARRAY_BUFFER,new Uint16Array([0,1,2,0,2,3]),u.STATIC_DRAW),u.vertexAttribPointer(0,2,u.FLOAT,!1,0,0),u.enableVertexAttribArray(0);function g(e){e?(u.viewport(0,0,e.width,e.height),u.bindFramebuffer(u.FRAMEBUFFER,e.fbo)):(u.viewport(0,0,u.drawingBufferWidth,u.drawingBufferHeight),u.bindFramebuffer(u.FRAMEBUFFER,null)),u.drawElements(u.TRIANGLES,6,u.UNSIGNED_SHORT,0)}function _(e,t){u.activeTexture(u.TEXTURE0);let n=u.createTexture();u.bindTexture(u.TEXTURE_2D,n),u.texParameteri(u.TEXTURE_2D,u.TEXTURE_MIN_FILTER,u.LINEAR),u.texParameteri(u.TEXTURE_2D,u.TEXTURE_MAG_FILTER,u.LINEAR),u.texParameteri(u.TEXTURE_2D,u.TEXTURE_WRAP_S,u.CLAMP_TO_EDGE),u.texParameteri(u.TEXTURE_2D,u.TEXTURE_WRAP_T,u.CLAMP_TO_EDGE),u.texImage2D(u.TEXTURE_2D,0,c,e,t,0,u.RGBA,l,null);let r=u.createFramebuffer();return u.bindFramebuffer(u.FRAMEBUFFER,r),u.framebufferTexture2D(u.FRAMEBUFFER,u.COLOR_ATTACHMENT0,u.TEXTURE_2D,n,0),u.viewport(0,0,e,t),u.clear(u.COLOR_BUFFER_BIT),{texture:n,fbo:r,width:e,height:t,texelSizeX:1/e,texelSizeY:1/t,attach(e){return u.activeTexture(u.TEXTURE0+e),u.bindTexture(u.TEXTURE_2D,n),e}}}function v(e,t){let n=_(e,t),r=_(e,t);return{width:e,height:t,texelSizeX:n.texelSizeX,texelSizeY:n.texelSizeY,get read(){return n},get write(){return r},swap(){[n,r]=[r,n]}}}let y,b,x,S,C;function w(){let t=u.drawingBufferWidth>>e.TEXTURE_DOWNSAMPLE,n=u.drawingBufferHeight>>e.TEXTURE_DOWNSAMPLE;return y=v(t,n),b=v(t,n),x=_(t,n),S=_(t,n),C=v(t,n),u.checkFramebufferStatus(u.FRAMEBUFFER)===u.FRAMEBUFFER_COMPLETE}function T(){let e=n.clientWidth,t=n.clientHeight;return e&&t&&(n.width!==e||n.height!==t)?(n.width=e,n.height=t,!0):!1}if(T(),!w())return null;function E(e){return u.useProgram(e.program),e.uniforms}function D(t,r,i,a,o){let s=E(h.splat);u.uniform1i(s.uTarget,b.read.attach(0)),u.uniform1f(s.aspectRatio,n.width/n.height),u.uniform2f(s.point,t/n.width,1-r/n.height),u.uniform3f(s.color,i,-a,1),u.uniform1f(s.radius,e.SPLAT_RADIUS),g(b.write),b.swap(),s=E(h.splat),u.uniform1i(s.uTarget,y.read.attach(0)),u.uniform3f(s.color,o[0]*.3,o[1]*.3,o[2]*.3),g(y.write),y.swap()}let O=()=>[Math.random()+.2,Math.random()+.2,Math.random()+.2];function k(t){u.disable(u.BLEND);let n=b.texelSizeX,r=b.texelSizeY,i=E(h.curl);u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uVelocity,b.read.attach(0)),g(S),i=E(h.vorticity),u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uVelocity,b.read.attach(0)),u.uniform1i(i.uCurl,S.attach(1)),u.uniform1f(i.curl,e.CURL),u.uniform1f(i.dt,t),g(b.write),b.swap(),i=E(h.divergence),u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uVelocity,b.read.attach(0)),g(x),i=E(h.clear),u.uniform1i(i.uTexture,C.read.attach(0)),u.uniform1f(i.value,e.PRESSURE_DISSIPATION),g(C.write),C.swap(),i=E(h.pressure),u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uDivergence,x.attach(0));for(let t=0;t<e.PRESSURE_ITERATIONS;t++)u.uniform1i(i.uPressure,C.read.attach(1)),g(C.write),C.swap();i=E(h.gradientSubtract),u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uPressure,C.read.attach(0)),u.uniform1i(i.uVelocity,b.read.attach(1)),g(b.write),b.swap(),i=E(h.advection),u.uniform2f(i.texelSize,n,r);let a=b.read.attach(0);u.uniform1i(i.uVelocity,a),u.uniform1i(i.uSource,a),u.uniform1f(i.dt,t),u.uniform1f(i.dissipation,e.VELOCITY_DISSIPATION),g(b.write),b.swap(),u.uniform1i(i.uVelocity,b.read.attach(0)),u.uniform1i(i.uSource,y.read.attach(1)),u.uniform1f(i.dissipation,e.DENSITY_DISSIPATION),g(y.write),y.swap()}function A(){let e=E(h.display);u.uniform1i(e.uTexture,y.read.attach(0)),u.uniform3f(e.background,...d),g(null)}let j=null,M=O(),N=0;function P(t){let r=n.getBoundingClientRect(),i=t.clientX-r.left,a=t.clientY-r.top;i<0||i>r.width||a<0||a>r.height?j=null:(++N>e.COLOR_EVERY_MOVES&&(N=0,M=O()),j&&D(i,a,(i-j.x)*10,(a-j.y)*10,M),j={x:i,y:a})}function F(){j=null}i.target.addEventListener(`pointermove`,P,{passive:!0}),i.target.addEventListener(`pointerleave`,F);let I=!0,L=new IntersectionObserver(([e])=>{I=e.isIntersecting,I&&H()});L.observe(n);let R=()=>document.visibilityState===`visible`&&H();document.addEventListener(`visibilitychange`,R);let z=0,B=performance.now(),V=!1;function H(){!z&&!V&&(B=performance.now(),z=requestAnimationFrame(U))}function U(e){if(z=0,V||!I||document.visibilityState!==`visible`)return;let t=Math.min((e-B)/1e3,.016);B=e,T()&&w(),k(t),A(),z=requestAnimationFrame(U)}return H(),()=>{V=!0,cancelAnimationFrame(z),L.disconnect(),document.removeEventListener(`visibilitychange`,R),i.target.removeEventListener(`pointermove`,P),i.target.removeEventListener(`pointerleave`,F)}}var a=document.querySelector(`[data-hero]`),o=a.querySelector(`canvas`);matchMedia(`(prefers-reduced-motion: reduce)`).matches||i(o,{target:a})&&a.classList.add(`is-live`);