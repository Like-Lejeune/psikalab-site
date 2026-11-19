// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

// Domaine de production : à modifier ici si le nom de domaine change.
export default defineConfig({
  site: 'https://psikalab.space',
  trailingSlash: 'always',
  build: { format: 'directory' },
  integrations: [
    sitemap({
      i18n: { defaultLocale: 'fr', locales: { fr: 'fr-CM', en: 'en' } },
    }),
  ],
});
