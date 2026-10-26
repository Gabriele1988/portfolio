// Logo di una tecnologia a partire dal nome scritto nel CMS (es. "React", "WordPress headless", "Spring Boot").
// I loghi arrivano da simple-icons e vengono letti solo durante la build: nel sito finisce solo l'SVG usato.
import * as si from 'simple-icons';

type Logo = { title: string; path: string };

// slug di simple-icons → logo
const perSlug = new Map<string, Logo>();
for (const v of Object.values(si)) {
  if (v && typeof v === 'object' && 'slug' in v && 'path' in v) perSlug.set(v.slug, v as Logo & { slug: string });
}

// Nomi scritti in modo diverso da simple-icons, o tecnologie che lì hanno un altro nome.
const ALIAS: Record<string, string> = {
  java: 'openjdk',
  css3: 'css',
  html: 'html5',
  js: 'javascript',
  ts: 'typescript',
  node: 'nodedotjs',
  nodejs: 'nodedotjs',
  vue: 'vuedotjs',
  vuejs: 'vuedotjs',
  next: 'nextdotjs',
  nextjs: 'nextdotjs',
  threejs: 'threedotjs',
  tailwind: 'tailwindcss',
  postgres: 'postgresql',
  maven: 'apachemaven',
  tomcat: 'apachetomcat',
  jpa: 'hibernate',
  elementorpro: 'elementor',
};

// Stessa regola di simple-icons per trasformare un titolo in slug.
const slug = (s: string) =>
  s
    .toLowerCase()
    .replace(/\+/g, 'plus')
    .replace(/\./g, 'dot')
    .replace(/&/g, 'and')
    .normalize('NFD')
    .replace(/[^a-z0-9]/g, '');

/** Logo della tecnologia, o undefined se non c'è (il nome si mostra lo stesso). */
export function logoTecnologia(nome: string): Logo | undefined {
  const parti = nome.split(/\s*[/,(]\s*/)[0]; // "Hibernate / JPA" → "Hibernate", "JavaScript (ES6+)" → "JavaScript"
  const parole = parti.trim().split(/\s+/);
  // Prima il nome intero, poi togliendo parole dalla fine ("WordPress headless" → "WordPress").
  for (let n = parole.length; n > 0; n--) {
    const s = slug(parole.slice(0, n).join(' '));
    const trovato = perSlug.get(ALIAS[s] ?? s);
    if (trovato) return trovato;
  }
  return undefined;
}
