// Legge i contenuti dal WordPress headless durante la build.
// Il formato atteso è quello esposto da cms/portfolio-core.php.
import { CMS_URL } from 'astro:env/client';

export interface Immagine {
  src: string;
  width?: number;
  height?: number;
  alt: string;
}

export interface Servizio {
  id: number;
  nome: string;
  slug: string;
}

export interface Progetto {
  id: number;
  slug: string;
  titolo: string;
  estratto: string;
  contenuto: string;
  data: string;
  cliente: string;
  anno: string;
  url: string;
  ruolo: string;
  tecnologie: string[];
  risultato: string;
  inEvidenza: boolean;
  copertina?: Immagine;
  servizi: Servizio[];
}

const API = CMS_URL ? `${CMS_URL}/wp-json/wp/v2` : '';

async function getJson(url: string): Promise<{ data: any; totalPages: number }> {
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!res.ok) {
    throw new Error(`CMS: ${res.status} ${res.statusText} su ${url}`);
  }
  return { data: await res.json(), totalPages: Number(res.headers.get('X-WP-TotalPages') ?? 1) };
}

/** Scarica tutte le pagine di una collezione REST. */
async function getAll(path: string): Promise<any[]> {
  const items: any[] = [];
  let page = 1;
  let totalPages = 1;
  do {
    const sep = path.includes('?') ? '&' : '?';
    const r = await getJson(`${API}/${path}${sep}per_page=100&page=${page}`);
    items.push(...r.data);
    totalPages = r.totalPages;
    page++;
  } while (page <= totalPages);
  return items;
}

const decode = (s = '') =>
  s
    .replace(/&#8217;|&rsquo;/g, '’')
    .replace(/&#8216;|&lsquo;/g, '‘')
    .replace(/&#8220;|&ldquo;/g, '“')
    .replace(/&#8221;|&rdquo;/g, '”')
    .replace(/&#8211;|&ndash;/g, '–')
    .replace(/&#8212;|&mdash;/g, '—')
    .replace(/&#038;|&amp;/g, '&')
    .replace(/&nbsp;/g, ' ');

const stripTags = (s = '') => decode(s.replace(/<[^>]*>/g, '')).trim();

function toList(v: unknown): string[] {
  if (Array.isArray(v)) return v.map(String).filter(Boolean);
  if (typeof v === 'string') return v.split(/[,\n]/).map((s) => s.trim()).filter(Boolean);
  return [];
}

function copertina(p: any): Immagine | undefined {
  // Campo pronto esposto dal mu-plugin…
  const c = p.copertina;
  if (c?.src || c?.url) {
    return { src: c.src ?? c.url, width: c.width, height: c.height, alt: c.alt ?? '' };
  }
  // …altrimenti l'immagine in evidenza standard di WordPress (_embed).
  const m = p._embedded?.['wp:featuredmedia']?.[0];
  if (m?.source_url) {
    return { src: m.source_url, width: m.media_details?.width, height: m.media_details?.height, alt: m.alt_text ?? '' };
  }
  return undefined;
}

function toProgetto(p: any): Progetto {
  // I campi possono arrivare in p.campi (mu-plugin), p.meta o p.acf.
  const f = { ...(p.acf ?? {}), ...(p.meta ?? {}), ...(p.campi ?? {}) };
  const termini: any[] = (p._embedded?.['wp:term'] ?? []).flat();
  return {
    id: p.id,
    slug: p.slug,
    titolo: decode(p.title?.rendered),
    estratto: stripTags(p.excerpt?.rendered),
    contenuto: p.content?.rendered ?? '',
    data: p.date,
    cliente: f.cliente ?? '',
    anno: String(f.anno ?? ''),
    url: f.url ?? f.url_progetto ?? '',
    ruolo: f.ruolo ?? '',
    tecnologie: toList(f.tecnologie),
    risultato: f.risultato ?? '',
    inEvidenza: Boolean(f.in_evidenza ?? f.inEvidenza),
    copertina: copertina(p),
    servizi: termini
      .filter((t) => t?.taxonomy === 'servizi')
      .map((t) => ({ id: t.id, nome: decode(t.name), slug: t.slug })),
  };
}

let cache: Promise<Progetto[]> | undefined;

/** Tutti i progetti pubblicati, dal più recente. Il risultato è condiviso tra le pagine della build. */
export function getProgetti(): Promise<Progetto[]> {
  if (!API) {
    cache ??= Promise.resolve([]).then(() => {
      console.warn('[wp] CMS_URL non impostato: il sito viene generato senza progetti.');
      return [];
    });
    return cache;
  }
  cache ??= getAll('progetti?_embed=wp:featuredmedia,wp:term&orderby=date&order=desc').then((items) =>
    items.map(toProgetto),
  );
  return cache;
}

export async function getProgettiInEvidenza(max = 3): Promise<Progetto[]> {
  const tutti = await getProgetti();
  const evidenza = tutti.filter((p) => p.inEvidenza);
  return (evidenza.length ? evidenza : tutti).slice(0, max);
}
