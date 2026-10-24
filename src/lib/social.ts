// Profili social compilati in "Testi del sito → Generale → Social", nell'ordine in cui li mostriamo.
import type { Testi } from './testi';

const RETI = [
  { chiave: 'instagram', nome: 'Instagram' },
  { chiave: 'facebook', nome: 'Facebook' },
  { chiave: 'linkedin', nome: 'LinkedIn' },
  { chiave: 'github', nome: 'GitHub' },
  { chiave: 'youtube', nome: 'YouTube' },
] as const;

export function social(g: Testi['generale']) {
  return RETI.filter((r) => g[r.chiave]).map((r) => ({ icona: r.chiave, nome: r.nome, url: g[r.chiave] }));
}

/** Link WhatsApp dal numero (solo cifre, con prefisso internazionale). */
export const linkWhatsapp = (numero: string) => (numero ? `https://wa.me/${numero.replace(/\D/g, '')}` : '');
