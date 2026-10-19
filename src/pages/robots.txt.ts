import type { APIRoute } from 'astro';
import { NOINDEX } from 'astro:env/client';

// NOINDEX=true (dominio temporaneo): blocca tutto. In produzione: tutto aperto + sitemap.
export const GET: APIRoute = ({ site }) => {
  const body = NOINDEX
    ? 'User-agent: *\nDisallow: /\n'
    : `User-agent: *\nAllow: /\n\nSitemap: ${new URL('sitemap-index.xml', site).href}\n`;
  return new Response(body, { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
};
