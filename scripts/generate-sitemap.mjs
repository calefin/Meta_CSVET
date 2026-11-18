#!/usr/bin/env node
import fs from 'fs';
import path from 'path';
import dotenv from 'dotenv';
dotenv.config();

const siteBase = process.env.SITE_BASE || 'https://www.prof-aid.com';
const now = new Date().toISOString().split('T')[0];

// Páginas públicas que devem ser indexadas
const routes = ['/', '/login', '/signup', '/pricing', '/faq', '/quote'];
const langs = ['pt', 'en', 'es'];

let urlsXml = '';

routes.forEach(r => {
  // Entrada para a URL padrão
  urlsXml += `  <url>\n`;
  urlsXml += `    <loc>${siteBase}${r}</loc>\n`;
  langs.forEach(lang => {
    const urlWithLang = lang === 'pt' ? `${siteBase}${r}` : `${siteBase}${r}?lang=${lang}`;
    urlsXml += `    <xhtml:link rel="alternate" hreflang="${lang}" href="${urlWithLang}" />\n`;
  });
  urlsXml += `    <xhtml:link rel="alternate" hreflang="x-default" href="${siteBase}${r}" />\n`;
  urlsXml += `    <lastmod>${now}</lastmod>\n`;
  urlsXml += `    <changefreq>weekly</changefreq>\n`;
  urlsXml += `    <priority>${r === '/' ? '1.0' : '0.7'}</priority>\n`;
  urlsXml += `  </url>\n`;

  // Entradas para as URLs com lang explícito (en, es)
  langs.forEach(currentLang => {
    if (currentLang !== 'pt') {
      const locUrl = `${siteBase}${r}?lang=${currentLang}`;
      urlsXml += `  <url>\n`;
      urlsXml += `    <loc>${locUrl}</loc>\n`;
      langs.forEach(lang => {
        const urlWithLang = lang === 'pt' ? `${siteBase}${r}` : `${siteBase}${r}?lang=${lang}`;
        urlsXml += `    <xhtml:link rel="alternate" hreflang="${lang}" href="${urlWithLang}" />\n`;
      });
      urlsXml += `    <xhtml:link rel="alternate" hreflang="x-default" href="${siteBase}${r}" />\n`;
      urlsXml += `    <lastmod>${now}</lastmod>\n`;
      urlsXml += `    <changefreq>weekly</changefreq>\n`;
      urlsXml += `    <priority>0.7</priority>\n`;
      urlsXml += `  </url>\n`;
    }
  });
});

const xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n${urlsXml}</urlset>`;

const outDir = path.resolve(process.cwd(), 'client', 'public');
if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(path.join(outDir, 'sitemap.xml'), xml, 'utf8');
console.log('Wrote sitemap.xml with multilingual support to', path.join(outDir, 'sitemap.xml'));
