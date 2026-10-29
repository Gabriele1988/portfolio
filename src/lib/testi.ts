// Testi modificabili delle pagine: WordPress → 'Testi del sito' (endpoint portfolio/v1/testi).
// Qui ci sono i testi predefiniti, usati per ogni campo lasciato vuoto nel CMS
// o quando il CMS non è configurato. Devono restare uguali a portfolio_testi_schema() nel mu-plugin.
import { CMS_URL } from 'astro:env/client';
import { sito } from '../site.config';

export const TESTI_DEFAULT = {
  generale: {
    nome: sito.nome,
    nome_completo: sito.nomeCompleto,
    ruolo: sito.ruolo,
    citta: sito.citta,
    descrizione: sito.descrizione,
    email: sito.email,
    telefono: sito.telefono,
    whatsapp: sito.whatsapp,
    indirizzo: sito.indirizzo,
    link_call: '',
    link_recensione: '',
    instagram: '',
    facebook: '',
    linkedin: sito.social.linkedin,
    github: sito.social.github,
    youtube: '',
    menu_home: 'Home',
    menu_servizi: 'Servizi',
    menu_progetti: 'Progetti',
    menu_chisono: 'Chi sono',
    menu_contatti: 'Contatti',
    menu_cta: 'Richiedi un preventivo',
    footer_descrizione: 'Sviluppatore web a Palermo.\nCreo siti che funzionano, per attività che contano.',
    footer_titolo_nav: 'Navigazione',
    footer_titolo_contatti: 'Contatti',
    footer_privacy: 'Privacy Policy',
    dati_legali: '',
    copyright: 'Tutti i diritti riservati.',
    credito: 'Sviluppato con Astro & ♥',
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
    stato_offline: 'Offline',
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
    label_tecnologie: 'Tecnologie',
    galleria_titolo: 'Galleria',
    cta_progetto_titolo: 'Hai un progetto simile?',
    cta_progetto: 'Richiedi un preventivo',
    meta_titolo: 'Progetti',
    meta_descrizione: 'Una selezione di lavori: il problema, la soluzione e il risultato.',
  },
  pag_servizi: {
    titolo: 'Quello che so fare,',
    titolo_evidenza: 'fatto bene',
    intro: 'Ogni progetto è diverso, ma il mio approccio è sempre lo stesso: ascoltare, progettare e costruire qualcosa che funziona davvero.',
    problemi_titolo: 'Quali problemi',
    problemi_evidenza: 'risolvo',
    processo_etichetta: 'Processo',
    processo_titolo: 'Come',
    processo_evidenza: 'lavoro',
    processo_intro: 'Dal primo contatto al supporto dopo il lancio, ogni fase è trasparente.',
    step: 'Step',
    faq_etichetta: 'FAQ',
    faq_titolo: 'Domande',
    faq_evidenza: 'frequenti',
    cta_titolo: 'Hai trovato quello che cercavi?',
    cta_testo: 'Raccontami il tuo progetto e troviamo insieme la soluzione giusta.',
    cta_pulsante: 'Parliamone',
    meta_titolo: 'Servizi',
    meta_descrizione: 'Siti web, applicazioni su misura e WordPress: i problemi che risolvo e come lavoro, passo dopo passo.',
  },
  chisono: {
    hero_occhiello: 'Gabriele Vilardo — Web Designer & Developer a Palermo',
    hero_titolo: 'Unisco precisione tecnica e cura estetica per dare forma al tuo business online.',
    hero_sottotitolo: 'Con oltre 10 anni di esperienza nello sviluppo di siti web a Palermo, aiuto aziende e professionisti a distinguersi sul web attraverso progetti WordPress su misura, veloci e pensati per convertire.',
    hero_cta: 'Richiedi una consulenza gratuita',
    hero_cta_url: '/contatti/',
    hero_cta2: 'Esplora il mio metodo ↓',
    hero_cta2_url: '#metodo',
    hero_foto: '',
    hero_foto_alt: 'Gabriele Vilardo, web designer a Palermo, al lavoro',
    hero_badge: 'WordPress\nElementor\nHTML5 / CSS3\nJava',
    meta_titolo: 'Gabriele Vilardo | Web Designer e Sviluppatore WordPress a Palermo',
    meta_descrizione: 'Cerchi un Web Designer a Palermo? Sono Gabriele Vilardo: +10 anni di esperienza in siti WordPress, Elementor, codice custom e sviluppo Full-Stack. Scopri di più.',
  },
  contatti: {
    titolo_evidenza: 'Inizia',
    titolo: 'tutto da qui.',
    intro: 'Pronto a creare qualcosa che ancora non esiste?',
    label_email: 'Email',
    label_telefono: 'Telefono',
    label_whatsapp: 'WhatsApp',
    testo_whatsapp: 'Scrivimi su WhatsApp',
    call_titolo: 'Preferisci una call?',
    call_testo: 'Prenota 30 minuti per parlare del tuo progetto',
    call_pulsante: 'Prenota una call',
    seguimi: 'Seguimi',
    recensione_titolo: 'Hai già lavorato con me?',
    recensione_testo: 'La tua opinione mi aiuta a crescere e a farmi trovare da nuovi clienti.',
    recensione_nota: 'Scansiona il QR code o clicca il bottone',
    recensione_pulsante: 'Recensione',
    form_titolo: 'Hai qualcosa da',
    form_titolo_evidenza: 'chiedermi',
    form_titolo_fine: '?',
    campo_nome: 'Nome',
    ph_nome: 'Il tuo nome',
    campo_cognome: 'Cognome',
    ph_cognome: 'Il tuo cognome',
    campo_azienda: 'Azienda',
    ph_azienda: 'Nome azienda (opzionale)',
    campo_email: 'Email',
    ph_email: 'la-tua@email.it',
    campo_contatto: 'Dove posso contattarti?',
    opz_email: 'Email',
    opz_telefono: 'Telefono',
    opz_whatsapp: 'WhatsApp',
    campo_telefono: 'Numero di telefono',
    ph_telefono: '+39 333 1234567',
    campo_sito: 'Hai già un sito?',
    opz_sito_no: 'No',
    opz_sito_si: 'Sì',
    opz_sito_rifare: 'Sì, ma va rifatto',
    campo_budget: 'Hai un budget?',
    opz_budget_si: 'Sì',
    opz_budget_no: 'No',
    campo_messaggio: 'Messaggio',
    ph_messaggio: 'Raccontami il tuo progetto…',
    privacy: 'Ho letto e accetto la [Privacy Policy]',
    invia: 'Invia messaggio',
    reset: 'Resetta il form',
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
