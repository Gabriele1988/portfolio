// Feed RSS del blog: /blog/rss.xml
import type { APIRoute } from 'astro';
import { getArticoli } from '../../lib/blog';
import { getTesti } from '../../lib/testi';

const x = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

export const GET: APIRoute = async ({ site }) => {
  const [articoli, testi] = await Promise.all([getArticoli(), getTesti()]);
  const base = new URL('/', site).href.replace(/\/$/, '');
  const voci = articoli
    .slice(0, 30)
    .map(
      (a) => `<item><title>${x(a.titolo)}</title><link>${base}/blog/${a.slug}/</link><guid isPermaLink="true">${base}/blog/${a.slug}/</guid><pubDate>${new Date(a.data).toUTCString()}</pubDate><description>${x(a.descrizione)}</description></item>`,
    )
    .join('');
  const xml = `<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>${x(`Blog · ${testi.generale.nome}`)}</title><link>${base}/blog/</link><description>Guide pratiche su siti web, WordPress, SEO e velocità.</description><language>it</language>${voci}</channel></rss>`;
  return new Response(xml, { headers: { 'Content-Type': 'application/rss+xml; charset=utf-8' } });
};
