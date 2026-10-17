import { headings } from './markdown.js';

// =============================================================================
// DOCUMENT GROUPS
// =============================================================================

// Folders group documents; filenames identify each document's language.

export const docGroups = ['guides', 'development', 'api'];

const files = import.meta.glob('../../content/{guides,development,api}/*.md', {
  query: '?raw',
  import: 'default',
  eager: true,
});

/**
 * Read the first Markdown H1 as the title and keep the remaining text as the body.
 */
function splitTitle(markdown) {
  const match = String(markdown).match(/^#\s+(.*)\r?\n/);

  if (!match) return { title: '', body: String(markdown) };

  return { title: match[1].trim(), body: String(markdown).slice(match[0].length).trimStart() };
}

/**
 * Extract the first ordinary paragraph for the next-document preview.
 * Skip headings, quotes, lists, tables, and fenced code blocks.
 */
function intro(markdown) {
  const lines = String(markdown).split('\n');
  let inFence = false;

  for (const line of lines) {
    const text = line.trim();

    if (text.startsWith('```')) {
      inFence = !inFence;
      continue;
    }

    if (inFence || text === '') continue;
    if (/^[#>|-]|^\d+\.\s/.test(text)) continue;

    return plain(text);
  }

  return '';
}

/**
 * Remove Markdown formatting so document previews contain plain text.
 */
function plain(text) {
  return text
    .replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1')
    .replace(/[`*_]/g, '')
    .trim();
}

// Limit document previews to 90 characters.
const previewLength = 90;

function preview(text) {
  if (text.length <= previewLength) return text;

  return text.slice(0, previewLength).trimEnd() + String.fromCharCode(8230);
}

/**
 * Parse content/guides/install.en.md into its slug, group, and language.
 */
function parseName(path) {
  const file = path.replace('../../content/', '');
  const group = file.split('/')[0];
  const name = path.split('/').pop().replace(/\.md$/, '');
  const parts = name.split('.');

  return { slug: parts[0], language: parts[1], group, path: file };
}

export const docs = Object.entries(files)
  .map(([path, raw]) => {
    const file = parseName(path);
    const { body, title } = splitTitle(raw);

    return { ...file, title, body, intro: preview(intro(body)), sections: headings(body) };
  })
  .sort((a, b) => docGroups.indexOf(a.group) - docGroups.indexOf(b.group) || a.slug.localeCompare(b.slug));

/**
 * Prefer the selected language, then English, then the available document.
 */
export function docsFor(language) {
  const selected = new Map();

  for (const doc of docs) {
    const current = selected.get(doc.slug);

    if (!current || doc.language === language || (current.language !== language && doc.language === 'en')) {
      selected.set(doc.slug, doc);
    }
  }

  return [...selected.values()];
}

export function findDoc(language, slug) {
  return docsFor(language).find((doc) => doc.slug === slug);
}

/**
 * Resolve Markdown file links to their corresponding web pages.
 * Leave private source references as text instead of creating broken links.
 */
export function docHref(doc, language, href) {
  if (!href || /^(?:[a-z][a-z\d+.-]*:|\/|#)/i.test(href)) return href;

  const target = new URL(href, 'https://docs.invalid/' + doc.path);
  const linked = docs.find((item) => '/' + item.path === decodeURIComponent(target.pathname));

  return linked ? `/${language}/docs/${linked.slug}${target.hash}` : null;
}

/**
 * Return the next document in the language's list, or nothing at the end.
 */
export function nextDoc(language, slug) {
  const own = docsFor(language);
  const index = own.findIndex((doc) => doc.slug === slug);

  return index >= 0 ? own[index + 1] : undefined;
}
