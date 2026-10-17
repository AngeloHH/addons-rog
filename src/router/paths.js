export function explorePath(language) {
  return `/${language}/explore`;
}

export function exploreFilterPath(language, key, value) {
  return `/${language}/explore?${key}=${value}`;
}

export function addonPath(language, addon) {
  return `/${language}/explore/${addon.expansionId}/${addon.categoryId}/${addon.slug}`;
}
