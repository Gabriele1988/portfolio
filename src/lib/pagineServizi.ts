// Pagine dei singoli servizi (/servizi/<slug>/).
// In WordPress sono il tipo di contenuto "Pagine servizi" (cms/portfolio-core.php), che al primo avvio
// viene riempito con gli stessi testi di src/data/pagine-servizi.json. Finché il CMS non le espone
// (plugin non aggiornato o nessuna pagina pubblicata) il sito usa quei testi predefiniti.
import { CMS_URL } from 'astro:env/client';
import predefinite from '../data/pagine-servizi.json';
import type { Progetto, ServizioHome } from './wp';

export interface Passo {
  icona: string;
  /** Nome breve della fase, es. "Analisi". */
  etichetta: string;
  /** Domanda a cui risponde la fase, es. "Chi deve trovarti?". */
  domanda: string;
  titolo: string;
  testo: string;
}

export interface PaginaServizio {
  slug: string;
  titolo: string;
  icona: string;
  sottotitolo: string;
  intro: string;
  include: string[];
  passi: Passo[];
  dopo: string;
  seo_titolo: string;
  seo_descrizione: string;
}

const righe = (v: unknown): string[] =>
  (Array.isArray(v) ? v.map(String) : String(v ?? '').split('\n')).map((s) => s.trim()).filter(Boolean);

function normalizza(p: any): PaginaServizio {
  return {
    slug: String(p.slug ?? ''),
    titolo: String(p.titolo ?? ''),
    icona: String(p.icona || 'globe'),
    sottotitolo: String(p.sottotitolo ?? ''),
    intro: String(p.intro ?? ''),
    include: righe(p.include),
    passi: (Array.isArray(p.passi) ? p.passi : [])
      .filter((x: any) => x?.titolo)
      .map((x: any) => ({
        icona: String(x.icona || 'code'),
        etichetta: String(x.etichetta ?? ''),
        domanda: String(x.domanda ?? ''),
        titolo: String(x.titolo),
        testo: String(x.testo ?? ''),
      })),
    dopo: String(p.dopo ?? ''),
    seo_titolo: String(p.seo_titolo ?? ''),
    seo_descrizione: String(p.seo_descrizione ?? ''),
  };
}

const PREDEFINITE = (predefinite as any[]).map(normalizza);

let cache: Promise<PaginaServizio[]> | undefined;

async function carica(): Promise<PaginaServizio[]> {
  if (!CMS_URL) return PREDEFINITE;
  const res = await fetch(`${CMS_URL}/wp-json/portfolio/v1/pagine-servizi`, { headers: { Accept: 'application/json' } });
  if (res.status === 404) {
    console.warn('[wp] portfolio/v1/pagine-servizi non trovato: aggiorna portfolio-core.php. Uso i testi predefiniti.');
    return PREDEFINITE;
  }
  if (!res.ok) throw new Error(`CMS: ${res.status} ${res.statusText} su /portfolio/v1/pagine-servizi`);
  const dati = ((await res.json()) as any[]).map(normalizza).filter((p) => p.slug && p.titolo);
  return dati.length ? dati : PREDEFINITE;
}

/** Tutte le pagine servizio pubblicate, nell'ordine scelto in WordPress. */
export function getPagineServizi(): Promise<PaginaServizio[]> {
  cache ??= carica();
  return cache;
}

const chiave = (s: string) =>
  s
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/&/g, ' ')
    .replace(/[^a-z0-9]+/g, '');

/** Pagina di una card servizio (home e /servizi/): stesso slug o stesso titolo. */
export function paginaDi(servizio: Pick<ServizioHome, 'titolo'>, pagine: PaginaServizio[]): PaginaServizio | undefined {
  const k = chiave(servizio.titolo);
  return pagine.find((p) => chiave(p.titolo) === k || chiave(p.slug) === k);
}

/** Progetti collegati: quelli con un termine "Servizi" che corrisponde alla pagina (per slug o nome). */
export function progettiDi(pagina: PaginaServizio, progetti: Progetto[]): Progetto[] {
  const chiavi = new Set([chiave(pagina.slug), chiave(pagina.titolo)]);
  return progetti.filter((p) => p.servizi.some((s) => chiavi.has(chiave(s.slug)) || chiavi.has(chiave(s.nome))));
}
