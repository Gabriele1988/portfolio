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

// ---------------------------------------------------------------------------
// Contenuti della Home (pagina "Home" nel CMS, endpoint portfolio/v1/home)

export interface ServizioHome {
  icona: string;
  titolo: string;
  sottotitolo: string;
  descrizione: string;
  include: string[];
  link: { testo: string; url: string };
}

export interface Home {
  servizi: {
    etichetta: string;
    titolo: string;
    titoloEvidenza: string;
    intro: string;
    elenco: ServizioHome[];
  };
}

// Usati finché nel CMS la pagina "Home" è vuota o il mu-plugin non è aggiornato.
const nessunLink = { testo: '', url: '' };
const HOME_DEFAULT: Home = {
  servizi: {
    etichetta: '',
    titolo: 'Servizi nel',
    titoloEvidenza: 'dettaglio',
    intro: '',
    elenco: [
      {
        icona: 'globe',
        titolo: 'Sviluppo Web',
        sottotitolo: 'Siti veloci, moderni e su misura',
        descrizione:
          'Siti custom costruiti per caricare in un attimo e convertire. Niente page builder pesanti: codice pulito, accessibile e facile da mantenere.',
        include: ['Siti vetrina e istituzionali', 'Landing page ad alta conversione', 'Performance ottimizzate (Core Web Vitals)', 'Design responsive su ogni dispositivo'],
        link: nessunLink,
      },
      {
        icona: 'server',
        titolo: 'Applicazioni web',
        sottotitolo: 'Gestionali e piattaforme su misura',
        descrizione:
          'Dal database all’interfaccia: applicazioni con Java, Spring Boot, Angular e PostgreSQL, pensate per crescere insieme al tuo lavoro.',
        include: ['Analisi dei processi', 'API REST con Spring Boot', 'Interfacce in Angular', 'Database PostgreSQL', 'Rilascio e manutenzione'],
        link: nessunLink,
      },
      {
        icona: 'layout',
        titolo: 'WordPress',
        sottotitolo: 'Flessibilità e autonomia nella gestione',
        descrizione:
          'Oltre dieci anni di WordPress: temi custom, siti classici o headless collegati a frontend moderni, per unire facilità di gestione e prestazioni.',
        include: ['Temi custom da zero', 'WordPress headless', 'ACF e custom post type', 'Migrazione da altri CMS', 'Formazione per la gestione autonoma'],
        link: nessunLink,
      },
      {
        icona: 'cart',
        titolo: 'E-Commerce',
        sottotitolo: 'Vendi online senza complicazioni',
        descrizione: 'Negozi online pensati per vendere davvero, con pagamenti sicuri e una gestione dei prodotti semplice.',
        include: ['WooCommerce personalizzato', 'Pagamenti e spedizioni', 'Catalogo prodotti ottimizzato', 'Integrazione con gestionali'],
        link: nessunLink,
      },
      {
        icona: 'zap',
        titolo: 'SEO & Performance',
        sottotitolo: 'Farsi trovare e caricare in un attimo',
        descrizione: 'Ottimizzazione tecnica per posizionarti su Google e offrire un sito rapido anche su hosting condiviso.',
        include: ['Audit tecnico', 'Core Web Vitals', 'Ottimizzazione immagini', 'SEO on-page e locale'],
        link: nessunLink,
      },
      {
        icona: 'chart',
        titolo: 'Consulenza Digitale',
        sottotitolo: 'Strategia prima della tecnologia',
        descrizione: 'Non sempre serve un sito nuovo: ti aiuto a capire cosa non funziona e dove conviene investire.',
        include: ['Audit del sito esistente', 'Scelta degli strumenti giusti', 'Pianificazione budget e priorità'],
        link: nessunLink,
      },
    ],
  },
};

export async function getHome(): Promise<Home> {
  if (!API) return HOME_DEFAULT;
  const res = await fetch(`${CMS_URL}/wp-json/portfolio/v1/home`, { headers: { Accept: 'application/json' } });
  if (res.status === 404) {
    console.warn('[wp] portfolio/v1/home non trovato: aggiorna portfolio-core.php. Uso i testi predefiniti.');
    return HOME_DEFAULT;
  }
  if (!res.ok) throw new Error(`CMS: ${res.status} ${res.statusText} su /portfolio/v1/home`);
  const data = (await res.json()) as Home;
  const s = data.servizi;
  return {
    servizi: {
      ...HOME_DEFAULT.servizi,
      ...s,
      elenco: s?.elenco?.length ? s.elenco : HOME_DEFAULT.servizi.elenco,
    },
  };
}
