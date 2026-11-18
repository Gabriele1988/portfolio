// Feed per la condivisione automatica su LinkedIn con Make: /blog/linkedin.xml
// Come /blog/rss.xml, ma la descrizione è già il post completo: testo scritto da Gemini (riquadro
// "Testo per LinkedIn" nell'editor) + link all'articolo. In Make basta mappare Description nel contenuto.
import type { APIRoute } from 'astro';
import { getArticoli, type Articolo } from '../../lib/blog';
import { getTesti } from '../../lib/testi';

const x = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// LinkedIn (tramite Make) usa il "little text format": parentesi e simboli speciali possono rompere il post.
const pulisci = (s: string) =>
  s
    .replace(/\s*[(\[{]([^()\[\]{}]*)[)\]}]/g, ', $1,')
    .replace(/,\s*([.!?:;,])/g, '$1')
    .replace(/[()\[\]{}]/g, '')
    .replace(/[<>@*_~|\\]/g, '')
    .replace(/[ \t]+\n/g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim();

const post = (a: Articolo, url: string) => {
  const testo = a.social
    ? a.social
    : `${a.titolo}\n\n${a.descrizione}\n\nNel nuovo articolo del mio blog approfondisco il tema con consigli pratici, pensati per professionisti e attività che vogliono un sito capace di portare risultati concreti.\n\n#WebDesign #SitiWeb #WordPress`;
  // il link va prima degli hashtag, se ci sono in fondo
  const righe = pulisci(testo).split('\n');
  const ultima = righe[righe.length - 1] ?? '';
  const hashtag = /^(#\S+\s*)+$/.test(ultima.trim()) ? righe.pop()! : '';
  return `${righe.join('\n').trim()}\n\nLeggi l'articolo: ${url}${hashtag ? `\n\n${hashtag.trim()}` : ''}`;
};

export const GET: APIRoute = async ({ site }) => {
  const [articoli, testi] = await Promise.all([getArticoli(), getTesti()]);
  const base = new URL('/', site).href.replace(/\/$/, '');
  const voci = articoli
    .slice(0, 20)
    .map((a) => {
      const url = `${base}/blog/${a.slug}/`;
      return `<item><title>${x(a.titolo)}</title><link>${url}</link><guid isPermaLink="true">${url}</guid><pubDate>${new Date(a.data).toUTCString()}</pubDate><description>${x(post(a, url))}</description></item>`;
    })
    .join('');
  const xml = `<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>${x(`LinkedIn · ${testi.generale.nome}`)}</title><link>${base}/blog/</link><description>Post per LinkedIn degli articoli del blog.</description><language>it</language>${voci}</channel></rss>`;
  return new Response(xml, { headers: { 'Content-Type': 'application/rss+xml; charset=utf-8' } });
};
