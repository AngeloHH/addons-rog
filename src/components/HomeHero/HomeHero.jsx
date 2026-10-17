import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';

import { homeHeroSlides, popularAddonSearches } from '../../config/homeHero.js';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './HomeHero.module.scss';

export default function HomeHero({ expansions = [] }) {
  const [activeIndex, setActiveIndex] = useState(0);
  const [query, setQuery] = useState('');
  const [expansion, setExpansion] = useState('all');
  const { language, t } = useI18n();
  const currentSlide = homeHeroSlides[activeIndex];

  useEffect(() => {
    const timer = window.setInterval(() => {
      setActiveIndex((index) => (index + 1) % homeHeroSlides.length);
    }, 6500);

    return () => window.clearInterval(timer);
  }, []);

  const searchHref = useMemo(() => {
    const params = new URLSearchParams();
    const trimmedQuery = query.trim();

    if (trimmedQuery) params.set('q', trimmedQuery);
    if (expansion !== 'all') params.set('expansion', expansion);

    const queryString = params.toString();
    return `/${language}/explore${queryString ? `?${queryString}` : ''}`;
  }, [expansion, language, query]);

  function goToSlide(index) {
    setActiveIndex((index + homeHeroSlides.length) % homeHeroSlides.length);
  }

  function handleSearchKey(event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      window.location.href = searchHref;
    }
  }

  return (
    <>
      <section className={styles.hero} data-screen-label="Hero">
        {homeHeroSlides.map((slide, index) => (
          <div className={styles.slide} data-active={index === activeIndex} key={slide.image}>
            <img src={slide.image} alt="" />
          </div>
        ))}

        <div className={styles.sideOverlay}></div>
        <div className={styles.bottomOverlay}></div>

        <div className={styles.contentWrap}>
          <div className={styles.content}>
            <div className={styles.kicker}>
              <span></span>
              <span>{t(currentSlide.kickerKey)}</span>
            </div>
            <h1>{t(currentSlide.titleKey)}</h1>
            <p>{t(currentSlide.textKey)}</p>
            <div className={styles.actions}>
              <a className={styles.primaryAction} href={currentSlide.href}>
                {t(currentSlide.ctaKey)}
              </a>
              <a className={styles.secondaryAction} href="#categories">
                {t('homeHero.viewCategories')}
              </a>
            </div>
          </div>
        </div>

        <div className={styles.controls}>
          <button
            type="button"
            onClick={() => goToSlide(activeIndex - 1)}
            aria-label={t('homeHero.previous')}
          >
            <Icon name="chevronUp" size={18} />
          </button>
          <div className={styles.dots}>
            {homeHeroSlides.map((slide, index) => (
              <button
                type="button"
                onClick={() => goToSlide(index)}
                aria-label={t('homeHero.goToSlide')}
                key={slide.image}
              >
                <span data-active={index === activeIndex}></span>
              </button>
            ))}
          </div>
          <button
            type="button"
            onClick={() => goToSlide(activeIndex + 1)}
            aria-label={t('homeHero.next')}
          >
            <Icon name="chevronDown" size={18} />
          </button>
        </div>
      </section>

      <section className={styles.searchSection} data-screen-label="Search">
        <div className={styles.searchBox}>
          <div className={styles.searchControls}>
            <div className={styles.searchInput}>
              <span>
                <Icon name="search" size={18} />
              </span>
              <input
                type="text"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                onKeyDown={handleSearchKey}
                placeholder={t('homeHero.searchPlaceholder')}
              />
            </div>

            <select value={expansion} onChange={(event) => setExpansion(event.target.value)}>
              <option value="all">{t('homeHero.allExpansions')}</option>
              {expansions.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>

            <Link className={styles.searchButton} to={searchHref}>
              {t('homeHero.search')}
            </Link>
          </div>

          <div className={styles.popular}>
            <span>{t('homeHero.popular')}</span>
            {popularAddonSearches.map((label) => (
              <Link key={label} to={`/${language}/explore?q=${encodeURIComponent(label)}`}>
                {label}
              </Link>
            ))}
          </div>
        </div>
      </section>
    </>
  );
}
