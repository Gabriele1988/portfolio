// @ts-check
import { defineConfig, envField } from 'astro/config';
import { loadEnv } from 'vite';
import sitemap from '@astrojs/sitemap';

// Legge .env in locale e le variabili d'ambiente su GitHub Actions.
// Il dominio non è mai scritto nel codice: per cambiarlo basta cambiare SITE_URL e CMS_URL.
const env = { ...loadEnv(process.env.NODE_ENV ?? 'production', process.cwd(), ''), ...process.env };
const SITE_URL = (env.SITE_URL || 'http://localhost:4321').replace(/\/$/, '');
const CMS_URL = (env.CMS_URL || '').replace(/\/$/, '');
const NOINDEX = env.NOINDEX !== 'false';

const cms = CMS_URL ? new URL(CMS_URL) : undefined;

// Progetti con "Nascondi dai motori di ricerca": fuori dalla sitemap.
async function progettiNascosti() {
  if (!CMS_URL || NOINDEX) return new Set();
  try {
    const res = await fetch(`${CMS_URL}/wp-json/wp/v2/progetti?per_page=100&_fields=slug,seo`);
    const items = res.ok ? await res.json() : [];
    return new Set(items.filter((p) => p.seo?.noindex).map((p) => `${SITE_URL}/progetti/${p.slug}/`));
  } catch {
    return new Set();
  }
}
const nascosti = await progettiNascosti();

export default defineConfig({
  site: SITE_URL,
  output: 'static',
  trailingSlash: 'ignore',
  build: {
    format: 'directory',
  },
  integrations: NOINDEX ? [] : [sitemap({ filter: (url) => !nascosti.has(url) })],
  image: {
    // Le immagini del CMS vengono scaricate e ottimizzate (WebP/AVIF) durante la build,
    // quindi il sito pubblicato non dipende dal dominio del CMS.
    remotePatterns: cms ? [{ protocol: cms.protocol.replace(':', ''), hostname: cms.hostname }] : [],
  },
  env: {
    schema: {
      CMS_URL: envField.string({ context: 'client', access: 'public', optional: true, default: '' }),
      NOINDEX: envField.boolean({ context: 'client', access: 'public', default: true }),
      TURNSTILE_SITE_KEY: envField.string({ context: 'client', access: 'public', optional: true, default: '' }),
    },
  },
});
