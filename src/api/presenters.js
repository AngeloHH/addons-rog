import { formatDate } from '../lib/date.js';
import { findExpansion } from '../config/expansions.js';

export const number = (value, language = 'en') =>
  new Intl.NumberFormat(language, { maximumFractionDigits: 1 }).format(Number(value) || 0);
export const bytes = (value) =>
  value >= 1048576 ? `${(value / 1048576).toFixed(1)} MB` : `${Math.ceil((value || 0) / 1024)} KB`;
export const date = formatDate;
export const profilePath = (language, username) =>
  `/${language}/profile/${encodeURIComponent(username)}`;
export function presentAddon(item, language = 'en') {
  return {
    ...item,
    image: item.iconUrl || '/api/default-icon.svg',
    category: item.categoryId,
    expansion: item.expansionId,
    description: item.summary,
    author: item.author?.name || item.author?.username || '',
    authorUsername: item.author?.username,
    downloads: number(item.downloads, language),
    updatedLabel: date(item.updatedAt, language),
    releasedAt: item.updatedAt,
    downloadUrl: item.download?.url,
    fileType: 'ZIP',
    fileSize: bytes(item.download?.size),
  };
}
export function presentExpansion(item) {
  return { ...findExpansion(item.id), ...item, count: item.addonCount };
}
