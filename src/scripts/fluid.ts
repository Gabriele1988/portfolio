// Effetto fumo della hero: porting fedele di "Smoke Streams" di Andry Zirka (BlackStar1991),
// https://codepen.io/BlackStar1991/pen/VYvXYNa, licenza MIT (vedi in fondo al file).
// Shader, parametri e ordine dei passaggi sono identici all'originale. Cambia solo il contorno:
// - la canvas e l'elemento che ascolta il mouse vengono passati dalla hero;
// - mouse e touch passano da Pointer Events, senza bloccare lo scorrimento su mobile;
// - l'animazione si ferma quando la hero non è visibile o la scheda è nascosta;
// - se WebGL non è disponibile restituisce null (la hero mostra uno sfondo statico).

export interface FluidOptions {
  /** Elemento che riceve i movimenti del puntatore (di solito la sezione hero). */
  target: HTMLElement;
}

const config = {
  TEXTURE_DOWNSAMPLE: 1,
  DENSITY_DISSIPATION: 0.98,
  VELOCITY_DISSIPATION: 0.99,
  PRESSURE_DISSIPATION: 0.8,
  PRESSURE_ITERATIONS: 25,
  CURL: 35,
  SPLAT_RADIUS: 0.002,
};

const baseVertexSrc =
  "precision highp float; precision mediump sampler2D; attribute vec2 aPosition; varying vec2 vUv; varying vec2 vL; varying vec2 vR; varying vec2 vT; varying vec2 vB; uniform vec2 texelSize; void main () {     vUv = aPosition * 0.5 + 0.5;     vL = vUv - vec2(texelSize.x, 0.0);     vR = vUv + vec2(texelSize.x, 0.0);     vT = vUv + vec2(0.0, texelSize.y);     vB = vUv - vec2(0.0, texelSize.y);     gl_Position = vec4(aPosition, 0.0, 1.0); }";
const clearSrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; uniform sampler2D uTexture; uniform float value; void main () {     gl_FragColor = value * texture2D(uTexture, vUv); }";
const displaySrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; uniform sampler2D uTexture; void main () {     gl_FragColor = texture2D(uTexture, vUv); }";
const splatSrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; uniform sampler2D uTarget; uniform float aspectRatio; uniform vec3 color; uniform vec2 point; uniform float radius; void main () {     vec2 p = vUv - point.xy;     p.x *= aspectRatio;     vec3 splat = exp(-dot(p, p) / radius) * color;     vec3 base = texture2D(uTarget, vUv).xyz;     gl_FragColor = vec4(base + splat, 1.0); }";
const advectionManualFilteringSrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; uniform sampler2D uVelocity; uniform sampler2D uSource; uniform vec2 texelSize; uniform float dt; uniform float dissipation; vec4 bilerp (in sampler2D sam, in vec2 p) {     vec4 st;     st.xy = floor(p - 0.5) + 0.5;     st.zw = st.xy + 1.0;     vec4 uv = st * texelSize.xyxy;     vec4 a = texture2D(sam, uv.xy);     vec4 b = texture2D(sam, uv.zy);     vec4 c = texture2D(sam, uv.xw);     vec4 d = texture2D(sam, uv.zw);     vec2 f = p - st.xy;     return mix(mix(a, b, f.x), mix(c, d, f.x), f.y); } void main () {     vec2 coord = gl_FragCoord.xy - dt * texture2D(uVelocity, vUv).xy;     gl_FragColor = dissipation * bilerp(uSource, coord);     gl_FragColor.a = 1.0; }";
const advectionSrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; uniform sampler2D uVelocity; uniform sampler2D uSource; uniform vec2 texelSize; uniform float dt; uniform float dissipation; void main () {     vec2 coord = vUv - dt * texture2D(uVelocity, vUv).xy * texelSize;     gl_FragColor = dissipation * texture2D(uSource, coord); }";
const divergenceSrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; varying vec2 vL; varying vec2 vR; varying vec2 vT; varying vec2 vB; uniform sampler2D uVelocity; vec2 sampleVelocity (in vec2 uv) {     vec2 multiplier = vec2(1.0, 1.0);     if (uv.x < 0.0) { uv.x = 0.0; multiplier.x = -1.0; }     if (uv.x > 1.0) { uv.x = 1.0; multiplier.x = -1.0; }     if (uv.y < 0.0) { uv.y = 0.0; multiplier.y = -1.0; }     if (uv.y > 1.0) { uv.y = 1.0; multiplier.y = -1.0; }     return multiplier * texture2D(uVelocity, uv).xy; } void main () {     float L = sampleVelocity(vL).x;     float R = sampleVelocity(vR).x;     float T = sampleVelocity(vT).y;     float B = sampleVelocity(vB).y;     float div = 0.5 * (R - L + T - B);     gl_FragColor = vec4(div, 0.0, 0.0, 1.0); }";
const curlSrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; varying vec2 vL; varying vec2 vR; varying vec2 vT; varying vec2 vB; uniform sampler2D uVelocity; void main () {     float L = texture2D(uVelocity, vL).y;     float R = texture2D(uVelocity, vR).y;     float T = texture2D(uVelocity, vT).x;     float B = texture2D(uVelocity, vB).x;     float vorticity = R - L - T + B;     gl_FragColor = vec4(vorticity, 0.0, 0.0, 1.0); }";
const vorticitySrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; varying vec2 vL; varying vec2 vR; varying vec2 vT; varying vec2 vB; uniform sampler2D uVelocity; uniform sampler2D uCurl; uniform float curl; uniform float dt; void main () {     float L = texture2D(uCurl, vL).y;     float R = texture2D(uCurl, vR).y;     float T = texture2D(uCurl, vT).x;     float B = texture2D(uCurl, vB).x;     float C = texture2D(uCurl, vUv).x;     vec2 force = vec2(abs(T) - abs(B), abs(R) - abs(L));     force *= 1.0 / length(force + 0.00001) * curl * C;     vec2 vel = texture2D(uVelocity, vUv).xy;     gl_FragColor = vec4(vel + force * dt, 0.0, 1.0); }";
const pressureSrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; varying vec2 vL; varying vec2 vR; varying vec2 vT; varying vec2 vB; uniform sampler2D uPressure; uniform sampler2D uDivergence; vec2 boundary (in vec2 uv) {     uv = min(max(uv, 0.0), 1.0);     return uv; } void main () {     float L = texture2D(uPressure, boundary(vL)).x;     float R = texture2D(uPressure, boundary(vR)).x;     float T = texture2D(uPressure, boundary(vT)).x;     float B = texture2D(uPressure, boundary(vB)).x;     float C = texture2D(uPressure, vUv).x;     float divergence = texture2D(uDivergence, vUv).x;     float pressure = (L + R + B + T - divergence) * 0.25;     gl_FragColor = vec4(pressure, 0.0, 0.0, 1.0); }";
const gradientSubtractSrc =
  "precision highp float; precision mediump sampler2D; varying vec2 vUv; varying vec2 vL; varying vec2 vR; varying vec2 vT; varying vec2 vB; uniform sampler2D uPressure; uniform sampler2D uVelocity; vec2 boundary (in vec2 uv) {     uv = min(max(uv, 0.0), 1.0);     return uv; } void main () {     float L = texture2D(uPressure, boundary(vL)).x;     float R = texture2D(uPressure, boundary(vR)).x;     float T = texture2D(uPressure, boundary(vT)).x;     float B = texture2D(uPressure, boundary(vB)).x;     vec2 velocity = texture2D(uVelocity, vUv).xy;     velocity.xy -= vec2(R - L, T - B);     gl_FragColor = vec4(velocity, 0.0, 1.0); }";

type FBO = [WebGLTexture, WebGLFramebuffer, number];
type DoubleFBO = { readonly first: FBO; readonly second: FBO; swap(): void };

export function startFluid(canvas: HTMLCanvasElement, opts: FluidOptions): (() => void) | null {
  canvas.width = canvas.clientWidth;
  canvas.height = canvas.clientHeight;

  const params = { alpha: false, depth: false, stencil: false, antialias: false };
  let gl = canvas.getContext('webgl2', params) as WebGL2RenderingContext | WebGLRenderingContext | null;
  const isWebGL2 = !!gl;
  if (!isWebGL2) gl = canvas.getContext('webgl', params) as WebGLRenderingContext | null;
  if (!gl) return null;
  const g = gl;

  const halfFloat = g.getExtension('OES_texture_half_float');
  let supportLinearFloat: unknown = g.getExtension('OES_texture_half_float_linear');
  if (isWebGL2) {
    g.getExtension('EXT_color_buffer_float');
    supportLinearFloat = g.getExtension('OES_texture_float_linear');
  } else if (!halfFloat) {
    return null;
  }
  g.clearColor(0, 0, 0, 1);

  const g2 = g as WebGL2RenderingContext;
  const ext = {
    internalFormat: isWebGL2 ? g2.RGBA16F : g.RGBA,
    internalFormatRG: isWebGL2 ? g2.RG16F : g.RGBA,
    formatRG: isWebGL2 ? g2.RG : g.RGBA,
    texType: isWebGL2 ? g2.HALF_FLOAT : halfFloat!.HALF_FLOAT_OES,
  };

  function compileShader(type: number, source: string) {
    const shader = g.createShader(type)!;
    g.shaderSource(shader, source);
    g.compileShader(shader);
    if (!g.getShaderParameter(shader, g.COMPILE_STATUS)) throw new Error(g.getShaderInfoLog(shader) ?? 'shader');
    return shader;
  }

  class GLProgram {
    uniforms: Record<string, WebGLUniformLocation | null> = {};
    program: WebGLProgram;
    constructor(vertexShader: WebGLShader, fragmentShader: WebGLShader) {
      this.program = g.createProgram()!;
      g.attachShader(this.program, vertexShader);
      g.attachShader(this.program, fragmentShader);
      g.linkProgram(this.program);
      if (!g.getProgramParameter(this.program, g.LINK_STATUS)) throw new Error(g.getProgramInfoLog(this.program) ?? 'program');
      const uniformCount = g.getProgramParameter(this.program, g.ACTIVE_UNIFORMS);
      for (let i = 0; i < uniformCount; i++) {
        const name = g.getActiveUniform(this.program, i)!.name;
        this.uniforms[name] = g.getUniformLocation(this.program, name);
      }
    }
    bind() {
      g.useProgram(this.program);
    }
  }

  let clearProgram: GLProgram, displayProgram: GLProgram, splatProgram: GLProgram, advectionProgram: GLProgram;
  let divergenceProgram: GLProgram, curlProgram: GLProgram, vorticityProgram: GLProgram, pressureProgram: GLProgram;
  let gradienSubtractProgram: GLProgram;
  try {
    const baseVertexShader = compileShader(g.VERTEX_SHADER, baseVertexSrc);
    const frag = (s: string) => compileShader(g.FRAGMENT_SHADER, s);
    clearProgram = new GLProgram(baseVertexShader, frag(clearSrc));
    displayProgram = new GLProgram(baseVertexShader, frag(displaySrc));
    splatProgram = new GLProgram(baseVertexShader, frag(splatSrc));
    advectionProgram = new GLProgram(
      baseVertexShader,
      frag(supportLinearFloat ? advectionSrc : advectionManualFilteringSrc),
    );
    divergenceProgram = new GLProgram(baseVertexShader, frag(divergenceSrc));
    curlProgram = new GLProgram(baseVertexShader, frag(curlSrc));
    vorticityProgram = new GLProgram(baseVertexShader, frag(vorticitySrc));
    pressureProgram = new GLProgram(baseVertexShader, frag(pressureSrc));
    gradienSubtractProgram = new GLProgram(baseVertexShader, frag(gradientSubtractSrc));
  } catch {
    return null;
  }

  let textureWidth = 0;
  let textureHeight = 0;
  let density: DoubleFBO, velocity: DoubleFBO, divergence: FBO, curl: FBO, pressure: DoubleFBO;

  function createFBO(texId: number, w: number, h: number, internalFormat: number, format: number, type: number, param: number): FBO {
    g.activeTexture(g.TEXTURE0 + texId);
    const texture = g.createTexture()!;
    g.bindTexture(g.TEXTURE_2D, texture);
    g.texParameteri(g.TEXTURE_2D, g.TEXTURE_MIN_FILTER, param);
    g.texParameteri(g.TEXTURE_2D, g.TEXTURE_MAG_FILTER, param);
    g.texParameteri(g.TEXTURE_2D, g.TEXTURE_WRAP_S, g.CLAMP_TO_EDGE);
    g.texParameteri(g.TEXTURE_2D, g.TEXTURE_WRAP_T, g.CLAMP_TO_EDGE);
    g.texImage2D(g.TEXTURE_2D, 0, internalFormat, w, h, 0, format, type, null);
    const fbo = g.createFramebuffer()!;
    g.bindFramebuffer(g.FRAMEBUFFER, fbo);
    g.framebufferTexture2D(g.FRAMEBUFFER, g.COLOR_ATTACHMENT0, g.TEXTURE_2D, texture, 0);
    g.viewport(0, 0, w, h);
    g.clear(g.COLOR_BUFFER_BIT);
    return [texture, fbo, texId];
  }

  function createDoubleFBO(texId: number, w: number, h: number, internalFormat: number, format: number, type: number, param: number): DoubleFBO {
    let fbo1 = createFBO(texId, w, h, internalFormat, format, type, param);
    let fbo2 = createFBO(texId + 1, w, h, internalFormat, format, type, param);
    return {
      get first() { return fbo1; },
      get second() { return fbo2; },
      swap() { [fbo1, fbo2] = [fbo2, fbo1]; },
    };
  }

  function initFramebuffers() {
    textureWidth = g.drawingBufferWidth >> config.TEXTURE_DOWNSAMPLE;
    textureHeight = g.drawingBufferHeight >> config.TEXTURE_DOWNSAMPLE;
    const { internalFormat: iFormat, internalFormatRG: iFormatRG, formatRG, texType } = ext;
    const filter = supportLinearFloat ? g.LINEAR : g.NEAREST;
    density = createDoubleFBO(0, textureWidth, textureHeight, iFormat, g.RGBA, texType, filter);
    velocity = createDoubleFBO(2, textureWidth, textureHeight, iFormatRG, formatRG, texType, filter);
    divergence = createFBO(4, textureWidth, textureHeight, iFormatRG, formatRG, texType, g.NEAREST);
    curl = createFBO(5, textureWidth, textureHeight, iFormatRG, formatRG, texType, g.NEAREST);
    pressure = createDoubleFBO(6, textureWidth, textureHeight, iFormatRG, formatRG, texType, g.NEAREST);
    return g.checkFramebufferStatus(g.FRAMEBUFFER) === g.FRAMEBUFFER_COMPLETE;
  }
  if (!initFramebuffers()) return null;

  g.bindBuffer(g.ARRAY_BUFFER, g.createBuffer());
  g.bufferData(g.ARRAY_BUFFER, new Float32Array([-1, -1, -1, 1, 1, 1, 1, -1]), g.STATIC_DRAW);
  g.bindBuffer(g.ELEMENT_ARRAY_BUFFER, g.createBuffer());
  g.bufferData(g.ELEMENT_ARRAY_BUFFER, new Uint16Array([0, 1, 2, 0, 2, 3]), g.STATIC_DRAW);
  g.vertexAttribPointer(0, 2, g.FLOAT, false, 0, 0);
  g.enableVertexAttribArray(0);
  const blit = (destination: WebGLFramebuffer | null) => {
    g.bindFramebuffer(g.FRAMEBUFFER, destination);
    g.drawElements(g.TRIANGLES, 6, g.UNSIGNED_SHORT, 0);
  };

  function resizeCanvas() {
    if (canvas.width !== canvas.clientWidth || canvas.height !== canvas.clientHeight) {
      canvas.width = canvas.clientWidth;
      canvas.height = canvas.clientHeight;
      initFramebuffers();
    }
  }

  function splat(x: number, y: number, dx: number, dy: number, color: number[]) {
    splatProgram.bind();
    g.uniform1i(splatProgram.uniforms.uTarget, velocity.first[2]);
    g.uniform1f(splatProgram.uniforms.aspectRatio, canvas.width / canvas.height);
    g.uniform2f(splatProgram.uniforms.point, x / canvas.width, 1.0 - y / canvas.height);
    g.uniform3f(splatProgram.uniforms.color, dx, -dy, 1.0);
    g.uniform1f(splatProgram.uniforms.radius, config.SPLAT_RADIUS);
    blit(velocity.second[1]);
    velocity.swap();

    g.uniform1i(splatProgram.uniforms.uTarget, density.first[2]);
    g.uniform3f(splatProgram.uniforms.color, color[0] * 0.3, color[1] * 0.3, color[2] * 0.3);
    blit(density.second[1]);
    density.swap();
  }

  // Come l'originale: un solo puntatore, al massimo uno splat per frame, colore nuovo ogni 25 movimenti.
  const pointer = { x: 0, y: 0, dx: 0, dy: 0, moved: false, color: [30, 0, 300] };
  let count = 0;
  let colorArr = [Math.random() + 0.2, Math.random() + 0.2, Math.random() + 0.2];

  function update(dt: number) {
    resizeCanvas();
    g.viewport(0, 0, textureWidth, textureHeight);

    advectionProgram.bind();
    g.uniform2f(advectionProgram.uniforms.texelSize, 1.0 / textureWidth, 1.0 / textureHeight);
    g.uniform1i(advectionProgram.uniforms.uVelocity, velocity.first[2]);
    g.uniform1i(advectionProgram.uniforms.uSource, velocity.first[2]);
    g.uniform1f(advectionProgram.uniforms.dt, dt);
    g.uniform1f(advectionProgram.uniforms.dissipation, config.VELOCITY_DISSIPATION);
    blit(velocity.second[1]);
    velocity.swap();

    g.uniform1i(advectionProgram.uniforms.uVelocity, velocity.first[2]);
    g.uniform1i(advectionProgram.uniforms.uSource, density.first[2]);
    g.uniform1f(advectionProgram.uniforms.dissipation, config.DENSITY_DISSIPATION);
    blit(density.second[1]);
    density.swap();

    if (pointer.moved) {
      splat(pointer.x, pointer.y, pointer.dx, pointer.dy, pointer.color);
      pointer.moved = false;
    }

    curlProgram.bind();
    g.uniform2f(curlProgram.uniforms.texelSize, 1.0 / textureWidth, 1.0 / textureHeight);
    g.uniform1i(curlProgram.uniforms.uVelocity, velocity.first[2]);
    blit(curl[1]);

    vorticityProgram.bind();
    g.uniform2f(vorticityProgram.uniforms.texelSize, 1.0 / textureWidth, 1.0 / textureHeight);
    g.uniform1i(vorticityProgram.uniforms.uVelocity, velocity.first[2]);
    g.uniform1i(vorticityProgram.uniforms.uCurl, curl[2]);
    g.uniform1f(vorticityProgram.uniforms.curl, config.CURL);
    g.uniform1f(vorticityProgram.uniforms.dt, dt);
    blit(velocity.second[1]);
    velocity.swap();

    divergenceProgram.bind();
    g.uniform2f(divergenceProgram.uniforms.texelSize, 1.0 / textureWidth, 1.0 / textureHeight);
    g.uniform1i(divergenceProgram.uniforms.uVelocity, velocity.first[2]);
    blit(divergence[1]);

    clearProgram.bind();
    let pressureTexId = pressure.first[2];
    g.activeTexture(g.TEXTURE0 + pressureTexId);
    g.bindTexture(g.TEXTURE_2D, pressure.first[0]);
    g.uniform1i(clearProgram.uniforms.uTexture, pressureTexId);
    g.uniform1f(clearProgram.uniforms.value, config.PRESSURE_DISSIPATION);
    blit(pressure.second[1]);
    pressure.swap();

    pressureProgram.bind();
    g.uniform2f(pressureProgram.uniforms.texelSize, 1.0 / textureWidth, 1.0 / textureHeight);
    g.uniform1i(pressureProgram.uniforms.uDivergence, divergence[2]);
    pressureTexId = pressure.first[2];
    g.activeTexture(g.TEXTURE0 + pressureTexId);
    for (let i = 0; i < config.PRESSURE_ITERATIONS; i++) {
      g.bindTexture(g.TEXTURE_2D, pressure.first[0]);
      g.uniform1i(pressureProgram.uniforms.uPressure, pressureTexId);
      blit(pressure.second[1]);
      pressure.swap();
    }

    gradienSubtractProgram.bind();
    g.uniform2f(gradienSubtractProgram.uniforms.texelSize, 1.0 / textureWidth, 1.0 / textureHeight);
    g.uniform1i(gradienSubtractProgram.uniforms.uPressure, pressure.first[2]);
    g.uniform1i(gradienSubtractProgram.uniforms.uVelocity, velocity.first[2]);
    blit(velocity.second[1]);
    velocity.swap();

    g.viewport(0, 0, g.drawingBufferWidth, g.drawingBufferHeight);
    displayProgram.bind();
    g.uniform1i(displayProgram.uniforms.uTexture, density.first[2]);
    blit(null);
  }

  // Coordinate relative alla canvas, così il fumo segue il mouse anche sopra testo e pulsanti.
  let hasLast = false;
  function onMove(e: PointerEvent) {
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    count++;
    if (count > 25) {
      colorArr = [Math.random() + 0.2, Math.random() + 0.2, Math.random() + 0.2];
      count = 0;
    }
    pointer.color = colorArr;
    pointer.moved = true;
    pointer.dx = hasLast ? (x - pointer.x) * 10.0 : 0;
    pointer.dy = hasLast ? (y - pointer.y) * 10.0 : 0;
    pointer.x = x;
    pointer.y = y;
    hasLast = true;
  }
  function onLeave() {
    hasLast = false;
  }
  opts.target.addEventListener('pointermove', onMove, { passive: true });
  opts.target.addEventListener('pointerleave', onLeave);

  // Gira solo quando la hero è visibile e la scheda è attiva.
  let visible = true;
  let raf = 0;
  let stopped = false;
  let lastTime = Date.now();
  const io = new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (visible) schedule();
  });
  io.observe(canvas);
  const onVisibility = () => document.visibilityState === 'visible' && schedule();
  document.addEventListener('visibilitychange', onVisibility);

  function schedule() {
    if (!raf && !stopped) {
      lastTime = Date.now();
      raf = requestAnimationFrame(frame);
    }
  }
  function frame() {
    raf = 0;
    if (stopped || !visible || document.visibilityState !== 'visible') return;
    const dt = Math.min((Date.now() - lastTime) / 1000, 0.016);
    lastTime = Date.now();
    update(dt);
    raf = requestAnimationFrame(frame);
  }
  schedule();

  return () => {
    stopped = true;
    cancelAnimationFrame(raf);
    io.disconnect();
    document.removeEventListener('visibilitychange', onVisibility);
    opts.target.removeEventListener('pointermove', onMove);
    opts.target.removeEventListener('pointerleave', onLeave);
  };
}

/*
The MIT License (MIT)

Copyright (c) 2025 Andry Zirka (https://codepen.io/BlackStar1991/pen/VYvXYNa)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/
