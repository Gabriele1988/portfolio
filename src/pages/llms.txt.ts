import type { APIRoute } from 'astro';
import { getHome, getProgetti } from '../lib/wp';
import { getTesti } from '../lib/testi';
import { getElenchiServizi } from '../lib/servizi';

// /llms.txt (https://llmstxt.org): sintesi del sito in Markdown per gli assistenti AI e gli agenti.
// Si rigenera a ogni build con i testi, i servizi e i progetti presi da WordPress.
export const GET: APIRoute = async ({ site }) => {
  const [testi, home, progetti, elenchi] = await Promise.all([getTesti(), getHome(), getProgetti(), getElenchiServizi()]);
  const g = testi.generale;
  const url = (path: string) => new URL(path, site).href;
  const riga = (s: string) => s.replace(/\s+/g, ' ').trim();

  const righe = [
    `# ${g.nome_completo || g.nome}`,
    '',
    `> ${riga(`${g.ruolo} a ${g.citta}. ${g.descrizione}`)}`,
    '',
    `Contatti: ${[g.email, g.telefono].filter(Boolean).join(' · ')}. Per un preventivo: ${url('/contatti/')}`,
    '',
    '## Pagine',
    '',
    `- [Home](${url('/')}): presentazione, servizi e progetti in evidenza`,
    `- [Servizi](${url('/servizi/')}): ${riga(testi.pag_servizi.meta_descrizione)}`,
    `- [Progetti](${url('/progetti/')}): ${riga(testi.progetti.meta_descrizione)}`,
    `- [Chi sono](${url('/chi-sono-web-designer-palermo/')}): ${riga(testi.chisono.meta_descrizione)}`,
    `- [Contatti](${url('/contatti/')}): modulo di contatto, email, telefono e WhatsApp`,
    '',
    '## Servizi',
    '',
    ...home.servizi.elenco.map((s) => `- ${s.titolo}: ${riga(s.descrizione)}`),
    '',
    '## Progetti',
    '',
    ...progetti
      .filter((p) => !p.seo.noindex)
      .map((p) => `- [${p.titolo}](${url(`/progetti/${p.slug}/`)}): ${riga(p.introduzione || p.estratto)}`),
    '',
    '## Domande frequenti',
    '',
    ...elenchi.faq.map((d) => `- ${riga(d.domanda)} ${riga(d.risposta)}`),
    '',
  ];
  return new Response(righe.join('\n'), { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
};
