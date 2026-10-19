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

export default defineConfig({
  site: SITE_URL,
  output: 'static',
  trailingSlash: 'ignore',
  build: {
    format: 'directory',
  },
  integrations: NOINDEX ? [] : [sitemap()],
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
