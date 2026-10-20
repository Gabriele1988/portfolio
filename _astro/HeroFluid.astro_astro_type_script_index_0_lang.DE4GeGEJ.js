var e={SIM_RESOLUTION:128,DYE_RESOLUTION:1024,DENSITY_DISSIPATION:.9,VELOCITY_DISSIPATION:.25,PRESSURE:.8,PRESSURE_ITERATIONS:20,CURL:28,SPLAT_RADIUS:.22,SPLAT_FORCE:6e3,AMBIENT_EVERY_MS:2600},t=`
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
      gl_FragColor = texture2D(uSource, coord) / (1.0 + dissipation * dt);
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
    }`};function i(n,i){let a={alpha:!1,depth:!1,stencil:!1,antialias:!1,preserveDrawingBuffer:!1},o=n.getContext(`webgl2`,a),s=!!o;if(o||=n.getContext(`webgl`,a),!o)return null;let c,l;if(s){let e=o;if(!e.getExtension(`EXT_color_buffer_float`)&&!e.getExtension(`EXT_color_buffer_half_float`))return null;c=e.RGBA16F,l=e.HALF_FLOAT}else{let e=o.getExtension(`OES_texture_half_float`);if(!e||!o.getExtension(`OES_texture_half_float_linear`))return null;c=o.RGBA,l=e.HALF_FLOAT_OES}let u=o,d=matchMedia(`(pointer: coarse)`).matches,f=d?512:e.DYE_RESOLUTION,p=i.background??[.043,.043,.063];function m(e,t){let n=u.createShader(e);if(u.shaderSource(n,t),u.compileShader(n),!u.getShaderParameter(n,u.COMPILE_STATUS))throw Error(u.getShaderInfoLog(n)??`shader`);return n}let h=m(u.VERTEX_SHADER,t);function g(e){let t=u.createProgram();if(u.attachShader(t,h),u.attachShader(t,m(u.FRAGMENT_SHADER,e)),u.bindAttribLocation(t,0,`aPosition`),u.linkProgram(t),!u.getProgramParameter(t,u.LINK_STATUS))throw Error(u.getProgramInfoLog(t)??`program`);let n={},r=u.getProgramParameter(t,u.ACTIVE_UNIFORMS);for(let e=0;e<r;e++){let r=u.getActiveUniform(t,e).name;n[r]=u.getUniformLocation(t,r)}return{program:t,uniforms:n}}let _;try{_=Object.fromEntries(Object.entries(r).map(([e,t])=>[e,g(t)]))}catch{return null}u.bindBuffer(u.ARRAY_BUFFER,u.createBuffer()),u.bufferData(u.ARRAY_BUFFER,new Float32Array([-1,-1,-1,1,1,1,1,-1]),u.STATIC_DRAW),u.bindBuffer(u.ELEMENT_ARRAY_BUFFER,u.createBuffer()),u.bufferData(u.ELEMENT_ARRAY_BUFFER,new Uint16Array([0,1,2,0,2,3]),u.STATIC_DRAW),u.vertexAttribPointer(0,2,u.FLOAT,!1,0,0),u.enableVertexAttribArray(0);function v(e){e?(u.viewport(0,0,e.width,e.height),u.bindFramebuffer(u.FRAMEBUFFER,e.fbo)):(u.viewport(0,0,u.drawingBufferWidth,u.drawingBufferHeight),u.bindFramebuffer(u.FRAMEBUFFER,null)),u.drawElements(u.TRIANGLES,6,u.UNSIGNED_SHORT,0)}function y(e,t){u.activeTexture(u.TEXTURE0);let n=u.createTexture();u.bindTexture(u.TEXTURE_2D,n),u.texParameteri(u.TEXTURE_2D,u.TEXTURE_MIN_FILTER,u.LINEAR),u.texParameteri(u.TEXTURE_2D,u.TEXTURE_MAG_FILTER,u.LINEAR),u.texParameteri(u.TEXTURE_2D,u.TEXTURE_WRAP_S,u.CLAMP_TO_EDGE),u.texParameteri(u.TEXTURE_2D,u.TEXTURE_WRAP_T,u.CLAMP_TO_EDGE),u.texImage2D(u.TEXTURE_2D,0,c,e,t,0,u.RGBA,l,null);let r=u.createFramebuffer();return u.bindFramebuffer(u.FRAMEBUFFER,r),u.framebufferTexture2D(u.FRAMEBUFFER,u.COLOR_ATTACHMENT0,u.TEXTURE_2D,n,0),u.viewport(0,0,e,t),u.clear(u.COLOR_BUFFER_BIT),{texture:n,fbo:r,width:e,height:t,texelSizeX:1/e,texelSizeY:1/t,attach(e){return u.activeTexture(u.TEXTURE0+e),u.bindTexture(u.TEXTURE_2D,n),e}}}function b(e,t){let n=y(e,t),r=y(e,t);return{width:e,height:t,texelSizeX:n.texelSizeX,texelSizeY:n.texelSizeY,get read(){return n},get write(){return r},swap(){[n,r]=[r,n]}}}function x(e){let t=u.drawingBufferWidth/u.drawingBufferHeight;t<1&&(t=1/t);let n=Math.round(e),r=Math.round(e*t);return u.drawingBufferWidth>u.drawingBufferHeight?{w:r,h:n}:{w:n,h:r}}let S,C,w,T,E;function D(){let t=x(e.SIM_RESOLUTION),n=x(f);return S=b(n.w,n.h),C=b(t.w,t.h),w=y(t.w,t.h),T=y(t.w,t.h),E=b(t.w,t.h),u.checkFramebufferStatus(u.FRAMEBUFFER)===u.FRAMEBUFFER_COMPLETE}function O(){let e=Math.min(window.devicePixelRatio||1,2),t=Math.floor(n.clientWidth*e),r=Math.floor(n.clientHeight*e);return t&&r&&(n.width!==t||n.height!==r)?(n.width=t,n.height=r,!0):!1}if(O(),!D())return null;function k(e){return u.useProgram(e.program),e.uniforms}function A(t,r,i,a,o,s=1){let c=n.width/n.height,l=e.SPLAT_RADIUS*s/100;c>1&&(l*=c);let d=k(_.splat);u.uniform1i(d.uTarget,C.read.attach(0)),u.uniform1f(d.aspectRatio,c),u.uniform2f(d.point,t,r),u.uniform3f(d.color,i,a,0),u.uniform1f(d.radius,l),v(C.write),C.swap(),d=k(_.splat),u.uniform1i(d.uTarget,S.read.attach(0)),u.uniform3f(d.color,...o),v(S.write),S.swap()}function j(e=.15){let t=Math.random()<.8?.55+Math.random()*.3:Math.random()*.1,n=Math.floor(t*6),r=t*6-n,i=1-r;return[[1,r,0],[i,1,0],[0,1,r],[0,i,1],[r,0,1],[1,0,i]][n%6].map(t=>t*e)}function M(e=1){A(Math.random(),Math.random(),(Math.random()-.5)*1e3*e,(Math.random()-.5)*1e3*e,j(.6*e),1.4)}function N(t){u.disable(u.BLEND);let n=C.texelSizeX,r=C.texelSizeY,i=k(_.curl);u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uVelocity,C.read.attach(0)),v(T),i=k(_.vorticity),u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uVelocity,C.read.attach(0)),u.uniform1i(i.uCurl,T.attach(1)),u.uniform1f(i.curl,e.CURL),u.uniform1f(i.dt,t),v(C.write),C.swap(),i=k(_.divergence),u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uVelocity,C.read.attach(0)),v(w),i=k(_.clear),u.uniform1i(i.uTexture,E.read.attach(0)),u.uniform1f(i.value,e.PRESSURE),v(E.write),E.swap(),i=k(_.pressure),u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uDivergence,w.attach(0));for(let t=0;t<e.PRESSURE_ITERATIONS;t++)u.uniform1i(i.uPressure,E.read.attach(1)),v(E.write),E.swap();i=k(_.gradientSubtract),u.uniform2f(i.texelSize,n,r),u.uniform1i(i.uPressure,E.read.attach(0)),u.uniform1i(i.uVelocity,C.read.attach(1)),v(C.write),C.swap(),i=k(_.advection),u.uniform2f(i.texelSize,n,r);let a=C.read.attach(0);u.uniform1i(i.uVelocity,a),u.uniform1i(i.uSource,a),u.uniform1f(i.dt,t),u.uniform1f(i.dissipation,e.VELOCITY_DISSIPATION),v(C.write),C.swap(),u.uniform1i(i.uVelocity,C.read.attach(0)),u.uniform1i(i.uSource,S.read.attach(1)),u.uniform1f(i.dissipation,e.DENSITY_DISSIPATION),v(S.write),S.swap()}function P(){let e=k(_.display);u.uniform1i(e.uTexture,S.read.attach(0)),u.uniform3f(e.background,...p),v(null)}let F=null,I=j(),L=0;function R(t){let r=n.getBoundingClientRect(),a=(t.clientX-r.left)/r.width,o=1-(t.clientY-r.top)/r.height;if(a<0||a>1||o<0||o>1)F=null;else{if(F){let n=r.width/r.height,s=a-F.x,c=o-F.y;if(n<1&&(s*=n),n>1&&(c/=n),s||c){let n=i.boostSelector&&t.target?.closest?.(i.boostSelector)?1.8:1,r=I.map(e=>e*n);A(a,o,s*e.SPLAT_FORCE,c*e.SPLAT_FORCE,r,n>1?1.4:1)}}F={x:a,y:o}}}function z(){F=null}function B(e){let t=n.getBoundingClientRect();A((e.clientX-t.left)/t.width,1-(e.clientY-t.top)/t.height,(Math.random()-.5)*800,(Math.random()-.5)*800,j(.8),2)}i.target.addEventListener(`pointermove`,R,{passive:!0}),i.target.addEventListener(`pointerleave`,z),i.target.addEventListener(`pointerdown`,B,{passive:!0});let V=!0,H=new IntersectionObserver(([e])=>{V=e.isIntersecting,V&&J()});H.observe(n);let U=()=>document.visibilityState===`visible`&&J();document.addEventListener(`visibilitychange`,U);for(let e=0;e<5;e++)M();let W=0,G=performance.now(),K=0,q=!1;function J(){!W&&!q&&(G=performance.now(),W=requestAnimationFrame(Y))}function Y(t){if(W=0,q||!V||document.visibilityState!==`visible`)return;let n=Math.min((t-G)/1e3,.016666);G=t,O()&&D(),L+=n,L>.6&&(L=0,I=j()),K+=n*1e3,K>e.AMBIENT_EVERY_MS*(d?.6:1)&&(K=0,M(d?.9:.6)),N(n),P(),W=requestAnimationFrame(Y)}return J(),()=>{q=!0,cancelAnimationFrame(W),H.disconnect(),document.removeEventListener(`visibilitychange`,U),i.target.removeEventListener(`pointermove`,R),i.target.removeEventListener(`pointerleave`,z),i.target.removeEventListener(`pointerdown`,B)}}var a=document.querySelector(`[data-hero]`),o=a.querySelector(`canvas`);matchMedia(`(prefers-reduced-motion: reduce)`).matches||i(o,{target:a,boostSelector:`[data-fluid-boost]`})&&a.classList.add(`is-live`);