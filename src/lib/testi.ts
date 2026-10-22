// Testi modificabili delle pagine: WordPress → "Testi del sito" (endpoint portfolio/v1/testi).
// Qui ci sono i testi predefiniti, usati per ogni campo lasciato vuoto nel CMS
// o quando il CMS non è configurato. Devono restare uguali a portfolio_testi_schema() nel mu-plugin.
import { CMS_URL } from 'astro:env/client';
import { sito } from '../site.config';

export const TESTI_DEFAULT = {
  generale: {
    nome: sito.nome,
    ruolo: sito.ruolo,
    citta: sito.citta,
    descrizione: sito.descrizione,
    email: sito.email,
    telefono: sito.telefono,
    github: sito.social.github,
    linkedin: sito.social.linkedin,
    menu_home: 'Home',
    menu_servizi: 'Servizi',
    menu_progetti: 'Progetti',
    menu_contatti: 'Contatti',
    menu_cta: 'Richiedi un preventivo',
    footer_privacy: 'Privacy',
  },
  home: {
    hero_occhiello: '',
    hero_titolo: 'Siti veloci e applicazioni web su misura.',
    hero_testo: '',
    hero_cta: 'Richiedi un preventivo',
    hero_cta2: 'Guarda i progetti',
    progetti_titolo: 'Progetti in evidenza',
    progetti_vuoto: 'I progetti arriveranno presto.',
    meta_titolo: '',
    meta_descrizione: '',
  },
  progetti: {
    titolo: 'Lavori',
    titolo_evidenza: 'recenti',
    intro: 'Ogni progetto è una storia diversa. Ecco quelli già online e quelli a cui sto lavorando.',
    etichetta_evidenza: 'Progetto in evidenza',
    funzionalita_titolo: 'Funzionalità chiave',
    altri_titolo: 'Altri',
    altri_titolo_evidenza: 'progetti',
    vuoto: 'I progetti arriveranno presto.',
    precedente: 'Precedente',
    successiva: 'Successiva',
    stato_online: 'Online',
    stato_sviluppo: 'In sviluppo',
    presto: 'Presto online',
    visita: 'Visita il sito',
    dettagli: 'Scopri il progetto',
    torna: '← Tutti i progetti',
    label_cliente: 'Cliente',
    label_anno: 'Anno',
    label_ruolo: 'Ruolo',
    label_servizi: 'Servizi',
    label_online: 'Online',
    label_risultato: 'Risultato',
    cta_progetto: 'Hai un progetto simile? Richiedi un preventivo',
    meta_titolo: 'Progetti',
    meta_descrizione: 'Una selezione di lavori: il problema, la soluzione e il risultato.',
  },
  contatti: {
    titolo: 'Richiedi un preventivo',
    intro: 'Raccontami il tuo progetto, rispondo entro un giorno lavorativo. Oppure scrivi a',
    campo_nome: 'Nome',
    campo_email: 'Email',
    campo_messaggio: 'Messaggio',
    privacy: "Ho letto l'[informativa privacy] e acconsento al trattamento dei dati per ricevere una risposta.",
    invia: 'Invia richiesta',
    invio: 'Invio in corso…',
    grazie: 'Grazie! Ho ricevuto il tuo messaggio e ti risponderò a breve.',
    errore: 'Riprova oppure scrivimi via email.',
    meta_titolo: 'Contatti',
    meta_descrizione: 'Raccontami il tuo progetto: rispondo entro un giorno lavorativo.',
  },
  privacy: {
    titolo: 'Privacy e cookie',
    contenuto: '',
    meta_titolo: 'Privacy e cookie',
    meta_descrizione: 'Informativa sul trattamento dei dati personali.',
  },
  notfound: {
    titolo: 'Pagina non trovata',
    testo: 'La pagina che cerchi non esiste o è stata spostata.',
    pulsante: 'Torna alla home',
  },
};

export type Testi = typeof TESTI_DEFAULT;

/** Ogni valore non vuoto arrivato dal CMS sostituisce il predefinito. */
function unisci(cms: any): Testi {
  const out: any = {};
  for (const [pagina, campi] of Object.entries(TESTI_DEFAULT)) {
    out[pagina] = { ...campi };
    for (const nome of Object.keys(campi)) {
      const v = cms?.[pagina]?.[nome];
      if (typeof v === 'string' && v.trim()) out[pagina][nome] = v.trim();
    }
  }
  return out;
}

let cache: Promise<Testi> | undefined;

export function getTesti(): Promise<Testi> {
  cache ??= (async () => {
    if (!CMS_URL) return TESTI_DEFAULT;
    const url = `${CMS_URL}/wp-json/portfolio/v1/testi`;
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    // 404: mu-plugin non ancora aggiornato, si usano i predefiniti.
    if (res.status === 404) {
      console.warn('[testi] endpoint portfolio/v1/testi assente: uso i testi predefiniti.');
      return TESTI_DEFAULT;
    }
    if (!res.ok) throw new Error(`CMS: ${res.status} ${res.statusText} su ${url}`);
    return unisci(await res.json());
  })();
  return cache;
}
