// Voci del menu (header e footer) nell'ordine scelto in WordPress: Testi del sito → Ordine del menu
// (endpoint portfolio/v1/menu). Senza CMS o con il plugin non aggiornato si usa l'ordine predefinito.
import { CMS_URL } from 'astro:env/client';
import type { Testi } from './testi';

export type ChiaveMenu = 'home' | 'servizi' | 'progetti' | 'chisono' | 'blog' | 'contatti';

const HREF: Record<ChiaveMenu, string> = {
  home: '/',
  servizi: '/servizi/',
  progetti: '/progetti/',
  chisono: '/chi-sono-web-designer-palermo/',
  blog: '/blog/',
  contatti: '/contatti/',
};
const PREDEFINITO: ChiaveMenu[] = ['home', 'servizi', 'progetti', 'chisono', 'blog', 'contatti'];

let cache: Promise<{ ordine: ChiaveMenu[]; nascoste: ChiaveMenu[] }> | undefined;

function carica() {
  cache ??= (async () => {
    const vuoto = { ordine: PREDEFINITO, nascoste: [] as ChiaveMenu[] };
    if (!CMS_URL) return vuoto;
    const res = await fetch(`${CMS_URL}/wp-json/portfolio/v1/menu`, { headers: { Accept: 'application/json' } });
    if (res.status === 404) return vuoto;
    if (!res.ok) throw new Error(`CMS: ${res.status} ${res.statusText} su /portfolio/v1/menu`);
    const d = await res.json();
    const valide = (v: unknown) => (Array.isArray(v) ? v.filter((k): k is ChiaveMenu => k in HREF) : []);
    const ordine = valide(d.ordine);
    return { ordine: [...ordine, ...PREDEFINITO.filter((k) => !ordine.includes(k))], nascoste: valide(d.nascoste) };
  })();
  return cache;
}

/** Voci visibili, in ordine. "Blog" solo se ci sono articoli pubblicati. */
export async function getVociMenu(g: Testi['generale'], conBlog: boolean) {
  const { ordine, nascoste } = await carica();
  const label: Record<ChiaveMenu, string> = {
    home: g.menu_home,
    servizi: g.menu_servizi,
    progetti: g.menu_progetti,
    chisono: g.menu_chisono,
    blog: g.menu_blog || 'Blog',
    contatti: g.menu_contatti,
  };
  return ordine
    .filter((k) => !nascoste.includes(k) && (k !== 'blog' || conBlog))
    .map((k) => ({ chiave: k, href: HREF[k], label: label[k] }));
}
