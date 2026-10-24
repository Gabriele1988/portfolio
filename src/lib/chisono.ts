// Sezioni della pagina Chi sono: WordPress → "Testi del sito → Chi sono" (contenuto flessibile,
// endpoint portfolio/v1/chi-sono). Se nel CMS non c'è nessuna sezione si usano quelle qui sotto,
// scritte a partire dal curriculum e dalla strategia SEO della pagina.
import { CMS_URL } from 'astro:env/client';

interface Base { etichetta: string; titolo: string; ancora?: string }
export type Sezione =
  | (Base & { tipo: 'statistiche'; voci: { numero: string; prefisso: string; suffisso: string; etichetta: string }[] })
  | (Base & { tipo: 'valori'; testo: string; card: { meta: string; titolo: string; testo: string }[] })
  | (Base & { tipo: 'bento'; box: { dimensione: 'grande' | 'medio' | 'piccolo'; titolo: string; testo: string; tag: string[] }[] })
  | (Base & { tipo: 'personale'; testo: string; passioni: string[] })
  | (Base & { tipo: 'contatti'; testo: string; pulsante: string; url: string })
  | (Base & { tipo: 'testo'; colonne: number; blocchi: { titolo: string; testo: string }[] })
  | (Base & { tipo: 'percorso'; voci: { periodo: string; titolo: string; luogo: string; testo: string }[] })
  | (Base & { tipo: 'competenze'; elenco: string[] })
  | (Base & { tipo: 'frase'; testo: string })
  | (Base & { tipo: 'cta'; testo: string; pulsante: string; url: string; pulsante2: string; url2: string });

export const SEZIONI_DEFAULT: Sezione[] = [
  {
    tipo: 'statistiche',
    etichetta: '',
    titolo: '',
    voci: [
      { numero: '11', prefisso: '+', suffisso: '', etichetta: 'anni di esperienza nel web design' },
      { numero: '2014–2025', prefisso: '', suffisso: '', etichetta: 'in agenzia a Palermo' },
      { numero: 'ACA', prefisso: '', suffisso: '', etichetta: 'Adobe Certified Associate' },
      { numero: '2026', prefisso: '', suffisso: '', etichetta: 'Java Full-Stack & AI Engineering' },
    ],
  },
  {
    tipo: 'valori',
    ancora: 'metodo',
    etichetta: 'Il metodo',
    titolo: 'Più di un semplice Web Designer: perché scegliere un profilo ibrido',
    testo: 'Molti designer creano layout bellissimi ma difficili da navigare; molti programmatori scrivono ottimo codice trascurando l’impatto visivo. Il mio approccio nasce dall’incontro tra questi due mondi, insieme all’abitudine al contatto diretto con le persone e alla gestione dei clienti che ho maturato sul campo.',
    card: [
      {
        meta: '2014 – 2025',
        titolo: 'Esperienza consolidata sul campo',
        testo: 'Dal 2014 al 2025 ho lavorato come Web Designer presso l’agenzia Tivitti Web e Comunicazione a Palermo, sviluppando e gestendo decine di siti web per imprese e professionisti locali e nazionali.',
      },
      {
        meta: 'WordPress · Elementor',
        titolo: 'Padronanza di WordPress ed Elementor',
        testo: 'Non mi limito a installare template predefiniti. Utilizzo i page builder in modo avanzato, scrivendo codice HTML5, CSS3 e PHP personalizzato per superare ogni limite grafico o funzionale.',
      },
      {
        meta: '2026',
        titolo: 'Visione Full-Stack e innovazione',
        testo: 'Il web evolve velocemente. Per offrire soluzioni sempre all’avanguardia, nel 2026 ho ampliato il mio bagaglio tecnico con un percorso intensivo in Java Full-Stack & AI Engineering, integrando logiche di programmazione avanzata, gestione database (MySQL/PostgreSQL) e framework moderni come Angular.',
      },
    ],
  },
  {
    tipo: 'bento',
    etichetta: 'Competenze',
    titolo: 'Il mio arsenale tecnico al servizio del tuo progetto',
    box: [
      {
        dimensione: 'grande',
        titolo: 'CMS & Front-End Design',
        testo: 'Progettazione di interfacce responsive e orientate all’utente.',
        tag: ['WordPress', 'Elementor Pro', 'HTML5', 'CSS3', 'JavaScript (ES6+)', 'Angular'],
      },
      {
        dimensione: 'medio',
        titolo: 'Architettura Back-End & Dati',
        testo: 'Logica lato server, API e database relazionali.',
        tag: ['PHP', 'Java', 'API RESTful', 'MySQL', 'PostgreSQL', 'Git / GitHub'],
      },
      {
        dimensione: 'medio',
        titolo: 'Visual Design & Certificazioni',
        testo: 'Suite Adobe (Photoshop). Certificato Adobe Certified Associate per la comunicazione visiva e web.',
        tag: ['Photoshop', 'Adobe Certified Associate'],
      },
      {
        dimensione: 'piccolo',
        titolo: 'Comunicazione & Marketing',
        testo: 'Campagne DEM e newsletter con MailUp, fondamenti di Social Media Marketing e redazione contenuti, grazie al mio passato nella redazione editoriale locale.',
        tag: ['MailUp', 'Social Media', 'Copywriting'],
      },
    ],
  },
  {
    tipo: 'percorso',
    etichetta: 'Percorso',
    titolo: 'La mia evoluzione professionale',
    voci: [
      { periodo: '2026', titolo: 'L’evoluzione Full-Stack & AI', luogo: '', testo: 'Specializzazione in sviluppo Java Full-Stack, Angular e tecnologie AI per portare l’ingegneria del software dentro i progetti web.' },
      { periodo: '2014 – 2025', titolo: 'Un decennio in agenzia a Palermo', luogo: 'Tivitti Web e Comunicazione', testo: 'Web Designer presso Tivitti Web e Comunicazione. Oltre dieci anni dedicati a tradurre le esigenze di aziende e professionisti in siti web performanti.' },
      { periodo: '2012 – 2015', titolo: 'Content & Digital Marketing', luogo: '', testo: 'Collaborazioni editoriali giornalistiche (L’Informatore) e specializzazione in WordPress e Social Media Marketing. Perché un sito senza contenuti efficaci è solo una scatola vuota.' },
      { periodo: '2008 – 2011', titolo: 'Le fondamenta e le certificazioni', luogo: '', testo: 'Formazione intensiva in Web Mastering, Multimedia e conseguimento delle certificazioni ufficiali Adobe.' },
    ],
  },
  {
    tipo: 'personale',
    etichetta: 'Dietro lo schermo',
    titolo: 'Quando chiudo l’editor di codice…',
    testo: 'Credo che la curiosità fuori dall’ufficio alimenti la creatività nel lavoro. Quando non progetto interfacce o non scrivo codice a Palermo, mi trovi probabilmente in sella alla mia moto, a cercare la pazienza perfetta durante una sessione di pesca, o ai fornelli a sperimentare in cucina (dove, proprio come nel codice, servono gli ingredienti giusti e tempi precisi). Sono inoltre un grande appassionato di fumetti, cultura del Giappone e calcio.',
    passioni: ['🏍️ Moto', '🎣 Pesca', '🍳 Cucina', '📚 Fumetti', '🗾 Giappone', '⚽ Calcio'],
  },
  {
    tipo: 'contatti',
    etichetta: 'Contatti',
    titolo: 'Hai un progetto in mente? Parliamone davanti a un caffè a Palermo (o in videochiamata).',
    testo: 'Che tu abbia bisogno di creare un nuovo sito web da zero, fare il restyling della tua piattaforma attuale o risolvere problemi tecnici su WordPress, sono qui per aiutarti.',
    pulsante: 'Scrivimi ora per un preventivo gratuito',
    url: '/contatti/',
  },
];

let cache: Promise<Sezione[]> | undefined;

export function getSezioniChiSono(): Promise<Sezione[]> {
  cache ??= (async () => {
    if (!CMS_URL) return SEZIONI_DEFAULT;
    const url = `${CMS_URL}/wp-json/portfolio/v1/chi-sono`;
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (res.status === 404) {
      console.warn('[chi-sono] endpoint portfolio/v1/chi-sono assente: uso le sezioni predefinite.');
      return SEZIONI_DEFAULT;
    }
    if (!res.ok) throw new Error(`CMS: ${res.status} ${res.statusText} su ${url}`);
    const { sezioni } = (await res.json()) as { sezioni?: Sezione[] };
    return sezioni?.length ? sezioni : SEZIONI_DEFAULT;
  })();
  return cache;
}
