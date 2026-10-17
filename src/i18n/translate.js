import { defaultLanguage } from './languages.js';
import { translations } from './translations.js';

function resolve(dictionary, key) {
  return key.split('.').reduce((value, part) => value?.[part], dictionary);
}

export function translate(language, key, params = {}) {
  const value = resolve(translations[language], key) ?? resolve(translations[defaultLanguage], key) ?? key;
  return typeof value === 'string' ? value.replace(/\{(\w+)\}/g, (_, name) => params[name] ?? '') : value;
}
