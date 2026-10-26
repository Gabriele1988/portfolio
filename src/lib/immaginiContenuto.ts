// Immagini inserite nel testo di un progetto (editor di WordPress): WordPress le restituisce come <img>
// che puntano al CMS, spesso a piena risoluzione. Qui vengono scaricate e ottimizzate durante la build
// come le altre immagini del sito: WebP in più larghezze, caricamento lazy, dimensioni dichiarate.
import { getImage } from 'astro:assets';

const LARGHEZZE = [480, 800, 1200, 1600];
const SIZES = '(min-width: 48rem) 44rem, 100vw'; // larghezza del testo (.prose)

const attributo = (tag: string, nome: string) =>
  tag.match(new RegExp(`\\s${nome}\\s*=\\s*("([^"]*)"|'([^']*)')`, 'i'))?.slice(2).find((v) => v !== undefined);

// I valori arrivano già codificati dall'HTML di WordPress: si codificano solo & e " rimasti "nudi".
const escape = (s: string) => s.replace(/&(?!#?\w+;)/g, '&amp;').replace(/"/g, '&quot;');

export async function ottimizzaImmaginiContenuto(html: string, cmsUrl: string): Promise<string> {
  if (!html.includes('<img') || !cmsUrl) return html;
  const host = new URL(cmsUrl).host;
  const tag = [...new Set(html.match(/<img\b[^>]*>/gi) ?? [])];

  const sostituzioni = await Promise.all(
    tag.map(async (img) => {
      const src = attributo(img, 'src')?.replace(/&amp;/g, '&');
      if (!src) return img;
      let url: URL;
      try { url = new URL(src, cmsUrl); } catch { return img; }
      if (url.host !== host || /\.(svg|gif)$/i.test(url.pathname)) return img; // vettoriali e GIF animate restano come sono

      // WordPress mette nel src una versione ridimensionata (foto-1024x683.jpg): si parte dall'originale.
      const originale = url.href.replace(/-\d+x\d+(\.\w+)$/, '$1');
      const alt = attributo(img, 'alt') ?? '';
      const classi = attributo(img, 'class');
      const w = Number(attributo(img, 'width')) || undefined;
      const h = Number(attributo(img, 'height')) || undefined;
      try {
        const ott = await getImage({
          src: originale,
          inferSize: true,
          widths: LARGHEZZE,
          sizes: SIZES,
          format: 'webp',
        }).catch(() => getImage({ src: url.href, inferSize: true, widths: LARGHEZZE, sizes: SIZES, format: 'webp' }));
        const larg = Number(ott.attributes.width) || w;
        const alt_ = Number(ott.attributes.height) || h;
        return `<img src="${escape(ott.src)}" srcset="${escape(ott.srcSet.attribute)}" sizes="${SIZES}" alt="${escape(alt)}"${larg ? ` width="${larg}"` : ''}${alt_ ? ` height="${alt_}"` : ''}${classi ? ` class="${escape(classi)}"` : ''} loading="lazy" decoding="async">`;
      } catch (e) {
        console.warn(`[immagini] non ottimizzata: ${src}`, e instanceof Error ? e.message : e);
        return img;
      }
    }),
  );

  let out = html;
  tag.forEach((img, i) => { out = out.split(img).join(sostituzioni[i]); });
  return out;
}
