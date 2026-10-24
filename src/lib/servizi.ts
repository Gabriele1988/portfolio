// Elenchi della pagina Servizi: WordPress → "Testi del sito → Servizi" (endpoint portfolio/v1/servizi).
// Un elenco vuoto nel CMS, o il CMS non configurato, usa quelli qui sotto.
import { CMS_URL } from 'astro:env/client';

export interface Problema { icona: string; titolo: string; testo: string }
export interface Fase { icona: string; titolo: string; testo: string }
export interface Domanda { domanda: string; risposta: string }
export interface ElenchiServizi { problemi: Problema[]; processo: Fase[]; faq: Domanda[] }

export const ELENCHI_DEFAULT: ElenchiServizi = {
  problemi: [
    { icona: 'search', titolo: 'Nessuno ti trova su Google', testo: 'Costruisco siti ottimizzati SEO dalla base: struttura semantica, velocità e contenuti pensati per posizionarti.' },
    { icona: 'smartphone', titolo: 'Il tuo sito non funziona da mobile', testo: 'Progetto mobile-first: ogni elemento è pensato per funzionare perfettamente su qualsiasi schermo.' },
    { icona: 'snail', titolo: 'Il sito è lento e i clienti se ne vanno', testo: 'Tecnologie moderne e ottimizzazione delle performance per caricamenti sotto i 2 secondi.' },
    { icona: 'compass', titolo: 'Non sai da dove iniziare', testo: 'Ti guido passo dopo passo, dalla strategia al lancio. Non devi sapere nulla di tecnico, ci penso io.' },
    { icona: 'lock', titolo: 'Hai un sito vecchio e non sicuro', testo: 'Porto il tuo sito su tecnologie aggiornate, con HTTPS, backup e manutenzione continua.' },
    { icona: 'settings', titolo: 'Gestisci tutto a mano', testo: 'Applicazioni web su misura con Java, Spring Boot e Angular che automatizzano ordini, clienti e magazzino.' },
  ],
  processo: [
    { icona: 'message', titolo: 'Primo contatto', testo: 'Ci conosciamo con una call o un caffè. Mi racconti la tua attività, gli obiettivi e le esigenze. Io ascolto e prendo appunti.' },
    { icona: 'search', titolo: 'Analisi e proposta', testo: 'Studio il tuo settore, la concorrenza e le opportunità. Ti presento una proposta chiara con tempi, costi e cosa riceverai.' },
    { icona: 'palette', titolo: 'Design e prototipi', testo: 'Preparo wireframe e mockup del sito. Li rivediamo insieme finché non sei soddisfatto, prima di scrivere una riga di codice.' },
    { icona: 'code', titolo: 'Sviluppo', testo: 'Costruisco il sito con tecnologie moderne. Ti aggiorno a ogni passaggio e puoi seguire i progressi su un’anteprima online.' },
    { icona: 'rocket', titolo: 'Lancio', testo: 'Test finali, ottimizzazione delle performance e messa online. Ti mostro come gestire i contenuti in autonomia.' },
    { icona: 'wrench', titolo: 'Supporto', testo: 'Dopo il lancio resto al tuo fianco: aggiornamenti, backup, sicurezza e nuove funzioni quando servono.' },
  ],
  faq: [
    { domanda: 'Quanto costa un sito web?', risposta: 'Dipende da pagine, funzioni e contenuti. Dopo la prima call ti mando un preventivo chiaro, senza costi nascosti.' },
    { domanda: 'Quanto tempo ci vuole?', risposta: 'Un sito vetrina richiede in media 3–5 settimane. Per un’applicazione su misura dipende dalla complessità: i tempi sono sempre scritti nella proposta.' },
    { domanda: 'Lavori solo con attività di Palermo?', risposta: 'No, lavoro con clienti in tutta Italia. Call, documenti condivisi e anteprime online rendono la distanza un non-problema.' },
    { domanda: 'Posso aggiornare il sito da solo dopo la consegna?', risposta: 'Sì. Con WordPress gestisci testi, immagini e pagine in autonomia, e ti mostro come farlo.' },
    { domanda: 'Cosa succede dopo il lancio?', risposta: 'Puoi scegliere un piano di manutenzione con aggiornamenti, backup e monitoraggio, oppure chiamarmi solo quando serve.' },
    { domanda: 'Fai anche SEO?', risposta: 'La SEO tecnica è inclusa in ogni sito: struttura, velocità, dati strutturati e sitemap. Per la SEO sui contenuti possiamo concordare un percorso dedicato.' },
  ],
};

let cache: Promise<ElenchiServizi> | undefined;

export function getElenchiServizi(): Promise<ElenchiServizi> {
  cache ??= (async () => {
    if (!CMS_URL) return ELENCHI_DEFAULT;
    const url = `${CMS_URL}/wp-json/portfolio/v1/servizi`;
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (res.status === 404) {
      console.warn('[servizi] endpoint portfolio/v1/servizi assente: uso gli elenchi predefiniti.');
      return ELENCHI_DEFAULT;
    }
    if (!res.ok) throw new Error(`CMS: ${res.status} ${res.statusText} su ${url}`);
    const cms = (await res.json()) as Partial<ElenchiServizi>;
    return {
      problemi: cms.problemi?.length ? cms.problemi : ELENCHI_DEFAULT.problemi,
      processo: cms.processo?.length ? cms.processo : ELENCHI_DEFAULT.processo,
      faq: cms.faq?.length ? cms.faq : ELENCHI_DEFAULT.faq,
    };
  })();
  return cache;
}
