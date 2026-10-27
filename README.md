# Portfolio · Astro + WordPress headless

Sito statico generato con [Astro](https://astro.build) a partire dai contenuti di un WordPress headless.
La build gira su GitHub Actions, il risultato viene pubblicato sul branch `deploy` e Plesk lo scarica via Git.

```
cms.<dominio>   WordPress (solo backend, REST API, non indicizzato)
      │  pubblichi un progetto → repository_dispatch "cms-update"
      ▼
GitHub Actions  npm run build (scarica i progetti e ottimizza le immagini)
      │  push sul branch "deploy"
      ▼
<dominio>       Plesk → Git → httpdocs (HTML statico)
```

Il dominio non è scritto da nessuna parte nel codice: arriva da variabili d'ambiente.

## Variabili

| Variabile            | Valore                           | A cosa serve                                                  |
| -------------------- | -------------------------------- | ------------------------------------------------------------- |
| `SITE_URL`           | `https://gabrielevilardo.it`     | URL pubblico del sito: canonical, sitemap, Open Graph         |
| `CMS_URL`            | `https://cms.gabrielevilardo.it` | WordPress: lettura dei progetti e invio del form contatti     |
| `NOINDEX`            | `true`                           | `true` = robots.txt blocca tutto + meta noindex. In produzione `false` |
| `TURNSTILE_SITE_KEY` | *(vuoto)*                        | Facoltativo, anti-spam Cloudflare Turnstile sul form          |

In locale vanno in `.env` (vedi `.env.example`). Su GitHub vanno in
**Settings → Secrets and variables → Actions → Variables** (sono variabili, non segreti: nessuna è riservata).

## Sviluppo in locale

Serve Node.js 22.22 o superiore.

```bash
cp .env.example .env
npm install
npm run dev        # http://localhost:4321
npm run build      # genera dist/
npm run preview
```

Senza `CMS_URL` il sito si genera lo stesso, senza progetti.
Con `CMS_URL` impostato, se il CMS non risponde la build fallisce: così non si pubblica mai un sito vuoto per errore.

## Struttura

```
astro.config.mjs            legge SITE_URL / CMS_URL / NOINDEX
src/site.config.ts          nome, ruolo, città, email, social
src/lib/wp.ts               client REST di WordPress (progetti, servizi, copertina)
src/pages/                  home, progetti, dettaglio progetto, contatti, privacy, 404, robots.txt
src/components/             header, footer, card progetto, form contatti
public/.htaccess            HTTPS, cache dei file /_astro/, pagina 404 (Apache su Plesk)
cms/portfolio-core.php      mu-plugin per WordPress (va copiato in wp-content/mu-plugins/)
.github/workflows/deploy.yml build e pubblicazione sul branch deploy
```

Le immagini dei progetti vengono scaricate dal CMS e convertite in WebP in più larghezze durante la build:
il sito pubblicato non carica nulla da `cms.`.

## Contratto con WordPress

`src/lib/wp.ts` legge `GET {CMS_URL}/wp-json/wp/v2/progetti?_embed` e si aspetta, per ogni progetto:

- `campi`: `cliente`, `anno`, `url`, `ruolo`, `tecnologie` (array), `risultato`, `in_evidenza` (bool).
  Se il tuo plugin li espone in `acf` o `meta` invece che in `campi` funziona lo stesso.
- `copertina`: `{ src, width, height, alt }`; se manca usa l'immagine in evidenza standard.
- tassonomia `servizi` (arriva con `_embed`).

Il form contatti invia `POST {CMS_URL}/wp-json/portfolio/v1/contatto` con
`{ nome, email, messaggio, privacy, sito_web, turnstile, tempo }`.

Tutti i testi delle pagine (home, progetti, contatti, privacy, 404, menu e dati generali) si modificano in WordPress
dal menu **Testi del sito** e arrivano da `GET {CMS_URL}/wp-json/portfolio/v1/testi`. Un campo lasciato vuoto usa il testo
predefinito di `src/lib/testi.ts`. Salvando una di queste pagine parte il rebuild.

`cms/portfolio-core.php` implementa tutto questo. Se usi già una tua versione del mu-plugin,
confrontala con questa: la parte nuova è l'endpoint `portfolio/v1/contatto` e l'evento `cms-update` per il rebuild.

## Messa online (dominio temporaneo)

1. **GitHub**: crea il repository `Gabriele1988/portfolio` (privato va bene), carica questi file sul branch `main`
   e aggiungi le variabili `SITE_URL`, `CMS_URL`, `NOINDEX=true`.
2. **Prima build**: GitHub → Actions → *Build e deploy* → *Run workflow*. Al termine esiste il branch `deploy`.
3. **Plesk** (dominio `visonuovomdb.com`): *Git* → repository remoto
   `https://github.com/Gabriele1988/portfolio.git` (se privato, usa l'URL SSH e aggiungi su GitHub la chiave
   che Plesk mostra, in *Settings → Deploy keys*, sola lettura), branch `deploy`, deploy automatico, percorso `httpdocs`.
   Copia il *Webhook URL* che Plesk mostra e aggiungilo su GitHub in *Settings → Webhooks* (evento: *Just the push event*).
4. **WordPress** su `cms.visonuovomdb.com`: carica `cms/portfolio-core.php` in `wp-content/mu-plugins/`
   e aggiungi a `wp-config.php`:

   ```php
   define( 'PORTFOLIO_SITE_URL', 'https://visonuovomdb.com' );
   define( 'PORTFOLIO_GITHUB_REPO', 'Gabriele1988/portfolio' );
   define( 'PORTFOLIO_GITHUB_TOKEN', 'github_pat_...' );
   ```

   Il token è un *fine-grained personal access token* limitato al solo repository `portfolio`,
   con permesso *Contents: Read and write* (serve per `repository_dispatch`).
5. Pubblica un progetto: parte la build e dopo un paio di minuti il sito è aggiornato.
   Il pulsante **Ripubblica sito** nella barra di WordPress fa la stessa cosa a mano.

## Passaggio al dominio definitivo (gabrielevilardo.it)

Il codice non cambia: cambiano solo le impostazioni. In ordine:

1. **WordPress** su `cms.gabrielevilardo.it`
   - Carica `cms/portfolio-core.php` (ultima versione) in `wp-content/mu-plugins/`.
   - In `wp-config.php`:
     ```php
     define( 'PORTFOLIO_SITE_URL', 'https://gabrielevilardo.it' );
     define( 'PORTFOLIO_GITHUB_REPO', 'Gabriele1988/portfolio' );
     define( 'PORTFOLIO_GITHUB_TOKEN', 'github_pat_...' );   // stesso token di prima o uno nuovo
     ```
   - *Impostazioni → Generali*: indirizzo WordPress e indirizzo sito = `https://cms.gabrielevilardo.it`.
   - Se il WordPress è una copia di quello vecchio, sostituisci nel database `cms.visonuovomdb.com`
     con `cms.gabrielevilardo.it` (WP Toolkit → *Cerca e sostituisci*, o il plugin Better Search Replace):
     così anche le immagini dentro i testi dei progetti puntano al nuovo CMS.
   - Controllo: `https://cms.gabrielevilardo.it/wp-json/portfolio/v1/testi` deve mostrare del JSON.
2. **GitHub** → *Settings → Secrets and variables → Actions → Variables*:
   `SITE_URL=https://gabrielevilardo.it`, `CMS_URL=https://cms.gabrielevilardo.it`,
   `NOINDEX=false` (quando vuoi che Google indicizzi il sito).
3. **Plesk** sul dominio `gabrielevilardo.it`
   - *Git* → stesso repository, branch `deploy`, deploy automatico, percorso `httpdocs`.
   - Copia il nuovo *Webhook URL* e aggiungilo su GitHub in *Settings → Webhooks*
     (quello del vecchio dominio si può togliere quando il vecchio sito non serve più).
   - Let's Encrypt per `gabrielevilardo.it`, `www.gabrielevilardo.it` e `cms.gabrielevilardo.it`;
     in *Hosting* scegli come dominio preferito quello senza `www`.
4. **Prima build**: GitHub → *Actions* → *Build e deploy* → *Run workflow*.
5. **Vecchio dominio**: su `visonuovomdb.com` imposta un redirect 301 verso `https://gabrielevilardo.it`
   (Plesk → *Hosting* → inoltro permanente), così i link già condivisi continuano a funzionare.
6. Se usi Cloudflare Turnstile per il form, aggiungi `gabrielevilardo.it` ai domini del widget.

## Immagini

`public/img/luna.webp`: faccia visibile della Luna ricavata dal mosaico NASA LRO (pubblico dominio), distribuito come `lunar_surface.jpg` negli esempi di globe.gl.
