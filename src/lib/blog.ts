// Articoli del blog (/blog/): i normali "Articoli" di WordPress, scritti a mano o dal blog automatico
// del plugin (Gemini). Senza CMS o senza articoli pubblicati il blog non compare nel menu.
import { CMS_URL } from 'astro:env/client';
import { copertina, decode, getAll, pulisciEstratto, stripTags, type Immagine } from './wp';

export interface Articolo {
  id: number;
  slug: string;
  titolo: string;
  estratto: string;
  /** Meta description (riquadro "Meta description" nell'editor); se vuota, l'estratto. */
  descrizione: string;
  contenuto: string;
  data: string;
  modificato: string;
  copertina?: Immagine;
  tag: string[];
  /** Minuti di lettura stimati (200 parole al minuto). */
  minuti: number;
}

export const ARTICOLI_PER_PAGINA = 9;

function toArticolo(p: any): Articolo {
  const termini: any[] = (p._embedded?.['wp:term'] ?? []).flat();
  const estratto = pulisciEstratto(p.excerpt?.rendered);
  const contenuto = String(p.content?.rendered ?? '');
  const parole = stripTags(contenuto).split(/\s+/).filter(Boolean).length;
  return {
    id: p.id,
    slug: p.slug,
    titolo: decode(p.title?.rendered),
    estratto,
    descrizione: String(p.meta?.portfolio_meta_descrizione ?? '').trim() || estratto,
    contenuto,
    data: p.date,
    modificato: p.modified ?? p.date,
    copertina: copertina(p),
    tag: termini.filter((t) => t?.taxonomy === 'post_tag').map((t) => decode(t.name)),
    minuti: Math.max(1, Math.round(parole / 200)),
  };
}

let cache: Promise<Articolo[]> | undefined;

/** Tutti gli articoli pubblicati, dal più recente. */
export function getArticoli(): Promise<Articolo[]> {
  if (!CMS_URL) return Promise.resolve([]);
  cache ??= getAll('posts?_embed=wp:featuredmedia,wp:term&orderby=date&order=desc').then((items) => items.map(toArticolo));
  return cache;
}

export const dataItaliana = (iso: string) =>
  new Date(iso).toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' });
