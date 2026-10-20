// Simulazione di fluido ("fumo") in WebGL, basata sul metodo Stable Fluids:
// advezione, vorticità, divergenza, pressione (Jacobi) e sottrazione del gradiente.
// Parametri e comportamento ricalcano la demo "Smoke/Fluid Motion" di BlackStar1991
// (https://www.cssscript.com/smoke-fluid-motion/, MIT), a sua volta derivata da
// WebGL-Fluid-Simulation di Pavel Dobryakov (MIT).

export interface FluidOptions {
  /** Elemento che riceve i movimenti del puntatore (di solito la sezione hero). */
  target: HTMLElement;
  background?: [number, number, number];
}

// Stessi valori della demo. Le dissipazioni sono moltiplicatori per frame:
// più vicini a 1 = scia più lunga.
const CONFIG = {
  TEXTURE_DOWNSAMPLE: 1,
  DENSITY_DISSIPATION: 0.98,
  VELOCITY_DISSIPATION: 0.99,
  PRESSURE_DISSIPATION: 0.8,
  PRESSURE_ITERATIONS: 25,
  CURL: 35,
  SPLAT_RADIUS: 0.002,
  /** Ogni quanti movimenti del mouse cambia colore. */
  COLOR_EVERY_MOVES: 25,
};

const baseVertex = `
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
  }`;

const head = `precision highp float; precision highp sampler2D; varying vec2 vUv, vL, vR, vT, vB;`;

const shaders = {
  clear: `${head}
    uniform sampler2D uTexture; uniform float value;
    void main () { gl_FragColor = value * texture2D(uTexture, vUv); }`,

  splat: `${head}
    uniform sampler2D uTarget; uniform float aspectRatio; uniform vec3 color; uniform vec2 point; uniform float radius;
    void main () {
      vec2 p = vUv - point;
      p.x *= aspectRatio;
      vec3 splat = exp(-dot(p, p) / radius) * color;
      gl_FragColor = vec4(texture2D(uTarget, vUv).xyz + splat, 1.0);
    }`,

  advection: `${head}
    uniform sampler2D uVelocity, uSource; uniform vec2 texelSize; uniform float dt, dissipation;
    void main () {
      vec2 coord = vUv - dt * texture2D(uVelocity, vUv).xy * texelSize;
      gl_FragColor = dissipation * texture2D(uSource, coord);
    }`,

  divergence: `${head}
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
    }`,

  curl: `${head}
    uniform sampler2D uVelocity;
    void main () {
      float L = texture2D(uVelocity, vL).y;
      float R = texture2D(uVelocity, vR).y;
      float T = texture2D(uVelocity, vT).x;
      float B = texture2D(uVelocity, vB).x;
      gl_FragColor = vec4(0.5 * (R - L - T + B), 0.0, 0.0, 1.0);
    }`,

  vorticity: `${head}
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
    }`,

  pressure: `${head}
    uniform sampler2D uPressure, uDivergence;
    void main () {
      float L = texture2D(uPressure, vL).x;
      float R = texture2D(uPressure, vR).x;
      float T = texture2D(uPressure, vT).x;
      float B = texture2D(uPressure, vB).x;
      float div = texture2D(uDivergence, vUv).x;
      gl_FragColor = vec4((L + R + B + T - div) * 0.25, 0.0, 0.0, 1.0);
    }`,

  gradientSubtract: `${head}
    uniform sampler2D uPressure, uVelocity;
    void main () {
      float L = texture2D(uPressure, vL).x;
      float R = texture2D(uPressure, vR).x;
      float T = texture2D(uPressure, vT).x;
      float B = texture2D(uPressure, vB).x;
      vec2 vel = texture2D(uVelocity, vUv).xy - vec2(R - L, T - B);
      gl_FragColor = vec4(vel, 0.0, 1.0);
    }`,

  display: `${head}
    uniform sampler2D uTexture; uniform vec3 background;
    void main () {
      vec3 c = texture2D(uTexture, vUv).rgb;
      gl_FragColor = vec4(min(background + c, vec3(1.0)), 1.0);
    }`,
};

type Program = { program: WebGLProgram; uniforms: Record<string, WebGLUniformLocation | null> };
type FBO = {
  texture: WebGLTexture;
  fbo: WebGLFramebuffer;
  width: number;
  height: number;
  texelSizeX: number;
  texelSizeY: number;
  attach(id: number): number;
};
type DoubleFBO = { width: number; height: number; texelSizeX: number; texelSizeY: number; read: FBO; write: FBO; swap(): void };

/** Avvia l'effetto. Restituisce una funzione di stop, oppure null se il browser non lo supporta. */
export function startFluid(canvas: HTMLCanvasElement, opts: FluidOptions): (() => void) | null {
  const params = { alpha: false, depth: false, stencil: false, antialias: false, preserveDrawingBuffer: false };
  let gl = canvas.getContext('webgl2', params) as WebGL2RenderingContext | WebGLRenderingContext | null;
  const isWebGL2 = !!gl;
  if (!gl) gl = canvas.getContext('webgl', params) as WebGLRenderingContext | null;
  if (!gl) return null;

  // Texture half-float con filtro lineare: indispensabili per un fumo morbido.
  let internalFormat: number;
  let halfFloat: number;
  if (isWebGL2) {
    const gl2 = gl as WebGL2RenderingContext;
    if (!gl2.getExtension('EXT_color_buffer_float') && !gl2.getExtension('EXT_color_buffer_half_float')) return null;
    internalFormat = gl2.RGBA16F;
    halfFloat = gl2.HALF_FLOAT;
  } else {
    const ext = gl.getExtension('OES_texture_half_float');
    if (!ext || !gl.getExtension('OES_texture_half_float_linear')) return null;
    internalFormat = gl.RGBA;
    halfFloat = ext.HALF_FLOAT_OES;
  }
  const g = gl;

  const background = opts.background ?? [0, 0, 0];

  function compile(type: number, source: string) {
    const s = g.createShader(type)!;
    g.shaderSource(s, source);
    g.compileShader(s);
    if (!g.getShaderParameter(s, g.COMPILE_STATUS)) throw new Error(g.getShaderInfoLog(s) ?? 'shader');
    return s;
  }
  const vertex = compile(g.VERTEX_SHADER, baseVertex);
  function program(fragment: string): Program {
    const p = g.createProgram()!;
    g.attachShader(p, vertex);
    g.attachShader(p, compile(g.FRAGMENT_SHADER, fragment));
    g.bindAttribLocation(p, 0, 'aPosition');
    g.linkProgram(p);
    if (!g.getProgramParameter(p, g.LINK_STATUS)) throw new Error(g.getProgramInfoLog(p) ?? 'program');
    const uniforms: Program['uniforms'] = {};
    const n = g.getProgramParameter(p, g.ACTIVE_UNIFORMS);
    for (let i = 0; i < n; i++) {
      const name = g.getActiveUniform(p, i)!.name;
      uniforms[name] = g.getUniformLocation(p, name);
    }
    return { program: p, uniforms };
  }

  let progs: Record<keyof typeof shaders, Program>;
  try {
    progs = Object.fromEntries(Object.entries(shaders).map(([k, src]) => [k, program(src)])) as typeof progs;
  } catch {
    return null;
  }

  // Quadrato a tutto schermo.
  g.bindBuffer(g.ARRAY_BUFFER, g.createBuffer());
  g.bufferData(g.ARRAY_BUFFER, new Float32Array([-1, -1, -1, 1, 1, 1, 1, -1]), g.STATIC_DRAW);
  g.bindBuffer(g.ELEMENT_ARRAY_BUFFER, g.createBuffer());
  g.bufferData(g.ELEMENT_ARRAY_BUFFER, new Uint16Array([0, 1, 2, 0, 2, 3]), g.STATIC_DRAW);
  g.vertexAttribPointer(0, 2, g.FLOAT, false, 0, 0);
  g.enableVertexAttribArray(0);

  function blit(target: FBO | null) {
    if (target) {
      g.viewport(0, 0, target.width, target.height);
      g.bindFramebuffer(g.FRAMEBUFFER, target.fbo);
    } else {
      g.viewport(0, 0, g.drawingBufferWidth, g.drawingBufferHeight);
      g.bindFramebuffer(g.FRAMEBUFFER, null);
    }
    g.drawElements(g.TRIANGLES, 6, g.UNSIGNED_SHORT, 0);
  }

  function createFBO(w: number, h: number): FBO {
    g.activeTexture(g.TEXTURE0);
    const texture = g.createTexture()!;
    g.bindTexture(g.TEXTURE_2D, texture);
    g.texParameteri(g.TEXTURE_2D, g.TEXTURE_MIN_FILTER, g.LINEAR);
    g.texParameteri(g.TEXTURE_2D, g.TEXTURE_MAG_FILTER, g.LINEAR);
    g.texParameteri(g.TEXTURE_2D, g.TEXTURE_WRAP_S, g.CLAMP_TO_EDGE);
    g.texParameteri(g.TEXTURE_2D, g.TEXTURE_WRAP_T, g.CLAMP_TO_EDGE);
    g.texImage2D(g.TEXTURE_2D, 0, internalFormat, w, h, 0, g.RGBA, halfFloat, null);
    const fbo = g.createFramebuffer()!;
    g.bindFramebuffer(g.FRAMEBUFFER, fbo);
    g.framebufferTexture2D(g.FRAMEBUFFER, g.COLOR_ATTACHMENT0, g.TEXTURE_2D, texture, 0);
    g.viewport(0, 0, w, h);
    g.clear(g.COLOR_BUFFER_BIT);
    return {
      texture,
      fbo,
      width: w,
      height: h,
      texelSizeX: 1 / w,
      texelSizeY: 1 / h,
      attach(id) {
        g.activeTexture(g.TEXTURE0 + id);
        g.bindTexture(g.TEXTURE_2D, texture);
        return id;
      },
    };
  }

  function createDoubleFBO(w: number, h: number): DoubleFBO {
    let a = createFBO(w, h);
    let b = createFBO(w, h);
    return {
      width: w,
      height: h,
      texelSizeX: a.texelSizeX,
      texelSizeY: a.texelSizeY,
      get read() { return a; },
      get write() { return b; },
      swap() { [a, b] = [b, a]; },
    };
  }

  let dye: DoubleFBO, velocity: DoubleFBO, divergence: FBO, curl: FBO, pressure: DoubleFBO;

  function initFramebuffers() {
    const w = g.drawingBufferWidth >> CONFIG.TEXTURE_DOWNSAMPLE;
    const h = g.drawingBufferHeight >> CONFIG.TEXTURE_DOWNSAMPLE;
    dye = createDoubleFBO(w, h);
    velocity = createDoubleFBO(w, h);
    divergence = createFBO(w, h);
    curl = createFBO(w, h);
    pressure = createDoubleFBO(w, h);
    // Se il framebuffer half-float non è utilizzabile, rinunciamo all'effetto.
    return g.checkFramebufferStatus(g.FRAMEBUFFER) === g.FRAMEBUFFER_COMPLETE;
  }

  // Come nella demo, la canvas ha la dimensione CSS (niente devicePixelRatio):
  // il fumo è morbido comunque e si risparmia GPU.
  function resizeCanvas() {
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    if (w && h && (canvas.width !== w || canvas.height !== h)) {
      canvas.width = w;
      canvas.height = h;
      return true;
    }
    return false;
  }

  resizeCanvas();
  if (!initFramebuffers()) return null;

  function use(p: Program) {
    g.useProgram(p.program);
    return p.uniforms;
  }

  function splat(x: number, y: number, dx: number, dy: number, color: number[]) {
    let u = use(progs.splat);
    g.uniform1i(u.uTarget, velocity.read.attach(0));
    g.uniform1f(u.aspectRatio, canvas.width / canvas.height);
    g.uniform2f(u.point, x / canvas.width, 1 - y / canvas.height);
    g.uniform3f(u.color, dx, -dy, 1);
    g.uniform1f(u.radius, CONFIG.SPLAT_RADIUS);
    blit(velocity.write);
    velocity.swap();

    u = use(progs.splat);
    g.uniform1i(u.uTarget, dye.read.attach(0));
    g.uniform3f(u.color, color[0] * 0.3, color[1] * 0.3, color[2] * 0.3);
    blit(dye.write);
    dye.swap();
  }

  const randomColor = () => [Math.random() + 0.2, Math.random() + 0.2, Math.random() + 0.2];

  function step(dt: number) {
    g.disable(g.BLEND);
    const tx = velocity.texelSizeX;
    const ty = velocity.texelSizeY;

    let u = use(progs.curl);
    g.uniform2f(u.texelSize, tx, ty);
    g.uniform1i(u.uVelocity, velocity.read.attach(0));
    blit(curl);

    u = use(progs.vorticity);
    g.uniform2f(u.texelSize, tx, ty);
    g.uniform1i(u.uVelocity, velocity.read.attach(0));
    g.uniform1i(u.uCurl, curl.attach(1));
    g.uniform1f(u.curl, CONFIG.CURL);
    g.uniform1f(u.dt, dt);
    blit(velocity.write);
    velocity.swap();

    u = use(progs.divergence);
    g.uniform2f(u.texelSize, tx, ty);
    g.uniform1i(u.uVelocity, velocity.read.attach(0));
    blit(divergence);

    u = use(progs.clear);
    g.uniform1i(u.uTexture, pressure.read.attach(0));
    g.uniform1f(u.value, CONFIG.PRESSURE_DISSIPATION);
    blit(pressure.write);
    pressure.swap();

    u = use(progs.pressure);
    g.uniform2f(u.texelSize, tx, ty);
    g.uniform1i(u.uDivergence, divergence.attach(0));
    for (let i = 0; i < CONFIG.PRESSURE_ITERATIONS; i++) {
      g.uniform1i(u.uPressure, pressure.read.attach(1));
      blit(pressure.write);
      pressure.swap();
    }

    u = use(progs.gradientSubtract);
    g.uniform2f(u.texelSize, tx, ty);
    g.uniform1i(u.uPressure, pressure.read.attach(0));
    g.uniform1i(u.uVelocity, velocity.read.attach(1));
    blit(velocity.write);
    velocity.swap();

    u = use(progs.advection);
    g.uniform2f(u.texelSize, tx, ty);
    const v = velocity.read.attach(0);
    g.uniform1i(u.uVelocity, v);
    g.uniform1i(u.uSource, v);
    g.uniform1f(u.dt, dt);
    g.uniform1f(u.dissipation, CONFIG.VELOCITY_DISSIPATION);
    blit(velocity.write);
    velocity.swap();

    g.uniform1i(u.uVelocity, velocity.read.attach(0));
    g.uniform1i(u.uSource, dye.read.attach(1));
    g.uniform1f(u.dissipation, CONFIG.DENSITY_DISSIPATION);
    blit(dye.write);
    dye.swap();
  }

  function render() {
    const u = use(progs.display);
    g.uniform1i(u.uTexture, dye.read.attach(0));
    g.uniform3f(u.background, ...background);
    blit(null);
  }

  // Puntatore: come nella demo, il fumo segue il mouse senza bisogno di cliccare
  // (spostamento in pixel × 10) e il colore cambia ogni 25 movimenti.
  // Le coordinate sono relative alla canvas, quindi funziona anche sopra testo e pulsanti.
  let last: { x: number; y: number } | null = null;
  let color = randomColor();
  let moves = 0;

  function onMove(e: PointerEvent) {
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    if (x < 0 || x > rect.width || y < 0 || y > rect.height) {
      last = null;
      return;
    }
    if (++moves > CONFIG.COLOR_EVERY_MOVES) {
      moves = 0;
      color = randomColor();
    }
    if (last) splat(x, y, (x - last.x) * 10, (y - last.y) * 10, color);
    last = { x, y };
  }
  function onLeave() {
    last = null;
  }

  opts.target.addEventListener('pointermove', onMove, { passive: true });
  opts.target.addEventListener('pointerleave', onLeave);

  // Gira solo quando la hero è visibile e la scheda è attiva.
  let visible = true;
  const io = new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (visible) schedule();
  });
  io.observe(canvas);
  const onVisibility = () => document.visibilityState === 'visible' && schedule();
  document.addEventListener('visibilitychange', onVisibility);

  let raf = 0;
  let lastTime = performance.now();
  let stopped = false;

  function schedule() {
    if (!raf && !stopped) {
      lastTime = performance.now();
      raf = requestAnimationFrame(frame);
    }
  }

  function frame(now: number) {
    raf = 0;
    if (stopped || !visible || document.visibilityState !== 'visible') return;
    const dt = Math.min((now - lastTime) / 1000, 0.016);
    lastTime = now;

    if (resizeCanvas()) initFramebuffers();
    step(dt);
    render();
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
