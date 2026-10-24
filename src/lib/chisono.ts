// Sezioni della pagina Chi sono: WordPress → "Testi del sito → Chi sono" (contenuto flessibile,
// endpoint portfolio/v1/chi-sono). Se nel CMS non c'è nessuna sezione si usano quelle qui sotto,
// scritte a partire dal curriculum.
import { CMS_URL } from 'astro:env/client';

interface Base { etichetta: string; titolo: string }
export type Sezione =
  | (Base & { tipo: 'testo'; colonne: number; blocchi: { titolo: string; testo: string }[] })
  | (Base & { tipo: 'percorso'; voci: { periodo: string; titolo: string; luogo: string; testo: string }[] })
  | (Base & { tipo: 'competenze'; elenco: string[] })
  | (Base & { tipo: 'frase'; testo: string })
  | (Base & { tipo: 'cta'; testo: string; pulsante: string; url: string; pulsante2: string; url2: string });

export const SEZIONI_DEFAULT: Sezione[] = [
  {
    tipo: 'testo',
    etichetta: 'Chi sono',
    titolo: '',
    colonne: 2,
    blocchi: [
      {
        titolo: 'Oltre dieci anni di WordPress',
        testo: 'Dal 2014 progetto e sviluppo siti WordPress per aziende e professionisti: temi su misura, page builder come Elementor e personalizzazioni grafiche e funzionali.',
      },
      {
        titolo: 'Oggi anche full-stack',
        testo: 'Nel 2026 ho completato il bootcamp Java Full-Stack & AI Engineer: Java, API REST, Hibernate, PostgreSQL e Angular. Così costruisco anche applicazioni web su misura.',
      },
      {
        titolo: 'Precisione e cura estetica',
        testo: 'Ho iniziato come web designer, con certificazioni Adobe e anni di Photoshop: per questo tengo allo stesso modo al codice pulito e a un risultato bello da vedere.',
      },
      {
        titolo: 'Un solo referente',
        testo: 'Parli direttamente con chi progetta e scrive il codice: niente passaggi di mano, tempi chiari e un sito che sai gestire anche da solo.',
      },
    ],
  },
  {
    tipo: 'competenze',
    etichetta: 'Competenze',
    titolo: 'Gli strumenti che uso ogni giorno',
    elenco: ['WordPress', 'Elementor', 'HTML5', 'CSS3', 'JavaScript', 'PHP', 'Java', 'Hibernate / JPA', 'API REST', 'MySQL', 'PostgreSQL', 'Angular', 'Git e GitHub', 'Photoshop'],
  },
  {
    tipo: 'percorso',
    etichetta: 'Percorso',
    titolo: '',
    voci: [
      { periodo: '2026', titolo: 'Java Full-Stack & AI Engineer', luogo: 'Bootcamp', testo: 'Java e programmazione a oggetti, API RESTful, Hibernate/JPA, Maven e Tomcat, database MySQL e PostgreSQL, Angular, Git.' },
      { periodo: '2014 – 2025', titolo: 'Web designer', luogo: 'Libero professionista', testo: 'Sviluppo e gestione di siti WordPress per clienti aziendali e professionisti, con personalizzazioni grafiche e funzionali.' },
      { periodo: '2015', titolo: 'Corsi WordPress e Social Media Marketing', luogo: 'Corsi Web Italia', testo: 'Due percorsi con attestato.' },
      { periodo: '2008 – 2011', titolo: 'Certificazioni web e design', luogo: 'CPU Academy, Microsales, Eureka Engineering', testo: 'Web Mastering e Design, Adobe Certified Associate (Photoshop e Dreamweaver CS3), Photoshop CS4 Advanced, 3D Studio Max.' },
      { periodo: '2007', titolo: 'Diploma di liceo scientifico', luogo: 'Liceo G. Galilei, Palermo', testo: '' },
    ],
  },
  {
    tipo: 'testo',
    etichetta: 'Fuori dal lavoro',
    titolo: '',
    colonne: 1,
    blocchi: [
      {
        titolo: 'Quando non scrivo codice',
        testo: 'Mi trovi in moto, a pesca o ai fornelli. Seguo il calcio, leggo fumetti e ho una grande passione per il Giappone.',
      },
    ],
  },
  {
    tipo: 'cta',
    etichetta: '',
    titolo: 'Lavoriamo insieme',
    testo: 'Hai un progetto in mente? Raccontamelo: ti rispondo entro un giorno lavorativo.',
    pulsante: 'Richiedi un preventivo',
    url: '/contatti/',
    pulsante2: 'Guarda i progetti',
    url2: '/progetti/',
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
