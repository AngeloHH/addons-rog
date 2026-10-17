/**
 * Convert a heading to a stable lowercase anchor with hyphen separators.
 */
export function slugify(text) {
  return String(text)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/**
 * Extract second-level headings for the document index.
 * Ignore heading-like text inside fenced code blocks.
 */
export function headings(markdown) {
  const lines = String(markdown).split('\n');
  const found = [];
  let inFence = false;

  for (const line of lines) {
    if (line.startsWith('```')) {
      inFence = !inFence;
      continue;
    }

    if (inFence) continue;

    const match = line.match(/^##\s+(.*)$/);

    if (match) {
      const label = match[1].trim();
      found.push({ id: slugify(label), label });
    }
  }

  return found;
}
