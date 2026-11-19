// Source du contenu du site.
//
// - Si la variable CONTENT_API_URL est définie (ex. https://api.psikalab.space/api/content),
//   le contenu est lu dans le back-office Laravel au moment de la compilation.
// - Sinon, ce sont les fichiers src/data/*.json qui servent (pratique en local).
//
// Si l'API est configurée mais ne répond pas, la compilation échoue volontairement :
// le site déjà en ligne reste en place au lieu d'être remplacé par un site vide.

import localSite from '../data/site.json';
import localEvents from '../data/events.json';
import localProjects from '../data/projects.json';
import localTeam from '../data/team.json';

export type Site = typeof localSite;
export type EventItem = (typeof localEvents)[number];
export type ProjectItem = (typeof localProjects)[number];
export type TeamMember = (typeof localTeam)[number];

export interface Content {
  site: Site;
  events: EventItem[];
  projects: ProjectItem[];
  team: TeamMember[];
  source: 'api' | 'local';
}

let cached: Promise<Content> | undefined;

async function load(): Promise<Content> {
  const url = import.meta.env.CONTENT_API_URL ?? process.env.CONTENT_API_URL;
  if (!url) {
    return { site: localSite, events: localEvents, projects: localProjects, team: localTeam, source: 'local' };
  }

  let lastError: unknown;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (!data?.site || !Array.isArray(data.events) || !Array.isArray(data.projects) || !Array.isArray(data.team)) {
        throw new Error('Réponse inattendue de l’API de contenu');
      }
      console.log(`[content] ${data.events.length} événements, ${data.projects.length} projets, ${data.team.length} membres lus depuis ${url}`);
      return { site: data.site, events: data.events, projects: data.projects, team: data.team, source: 'api' };
    } catch (e) {
      lastError = e;
      await new Promise((r) => setTimeout(r, 1000 * attempt));
    }
  }
  throw new Error(`[content] Impossible de lire ${url} : ${String(lastError)}`);
}

export function getContent(): Promise<Content> {
  cached ??= load();
  return cached;
}
