import { useEffect } from 'react';
import { Link, Navigate, useLocation, useParams } from 'react-router-dom';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import Icon from '../../components/Icon/Icon.jsx';
import Markdown from '../../components/Markdown/Markdown.jsx';
import PageHero from '../../components/PageHero/PageHero.jsx';
import SiteHeader from '../../components/SiteHeader/SiteHeader.jsx';
import { useActiveSection } from '../../hooks/useActiveSection.js';
import { docGroups, docHref, docsFor, findDoc, nextDoc } from '../../lib/docs.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import NotFoundPage from '../NotFoundPage/NotFoundPage.jsx';
import styles from './DocsPage.module.scss';

/**
 * Render Markdown documents from content/ with grouped navigation and a heading index.
 * Use native anchors within a document and router links between documents.
 */
function SectionLink({ active, href, label, number, to }) {
  const Tag = href ? 'a' : Link;

  return (
    <Tag className={styles.section} data-active={active} {...(href ? { href } : { to })}>
      <span className={styles.sectionNumber}>{String(number).padStart(2, '0')}</span>
      {label}
    </Tag>
  );
}

export default function DocsPage() {
  const { language, t } = useI18n();
  const { slug } = useParams();
  const { hash } = useLocation();
  const documents = docsFor(language);
  const doc = findDoc(language, slug);
  const next = nextDoc(language, slug);
  const reading = useActiveSection(doc?.sections.map((section) => section.id) ?? []);

  // Handle direct hash entry and navigation from another document after rendering.
  // Native anchors handle jumps within the current document.
  useEffect(() => {
    if (!hash) return;

    document.getElementById(hash.slice(1))?.scrollIntoView();
  }, [slug]); // eslint-disable-line react-hooks/exhaustive-deps

  if (!doc) return <NotFoundPage />;

  return (
    <div className="site-frame">
      <AuthBar />
      <SiteHeader />
      <main className="app-shell">
        <PageHero
          kicker={t('install.kicker')}
          text={t(doc.group === 'guides' ? 'install.intro' : 'docs.intro')}
          title={doc.title}
        />

        <section className={styles.docs}>
          <aside className={styles.aside}>
            <nav className={styles.nav}>
              <span className={styles.navTitle}>{t('install.documents')}</span>

              {docGroups.map((group) => (
                <details className={styles.group} key={group} open={doc.group === group}>
                  <summary className={styles.groupTitle}>{t(`docs.groups.${group}`)}</summary>

                  {documents.filter((item) => item.group === group).map((item) => {
                    const current = item.slug === doc.slug;

                    return (
                      <details className={styles.doc} key={item.slug} open={current}>
                        <summary className={styles.docSummary} data-active={current}>
                          <Link
                            aria-current={current ? 'page' : undefined}
                            className={styles.docTitle}
                            lang={item.language}
                            to={`/${language}/docs/${item.slug}`}
                          >
                            {item.title}
                          </Link>
                        </summary>

                        <div className={styles.sections}>
                          {item.sections.map((section, index) => (
                            <SectionLink
                              active={current && reading === section.id}
                              href={current ? `#${section.id}` : null}
                              key={section.id}
                              label={section.label}
                              number={index + 1}
                              to={`/${language}/docs/${item.slug}#${section.id}`}
                            />
                          ))}
                        </div>
                      </details>
                    );
                  })}
                </details>
              ))}
            </nav>

            <div className={styles.help}>
              <span className={styles.helpKicker}>{t('install.stuck')}</span>
              <Link className={styles.helpAction} to={`/${language}/contact`}>
                {t('install.askCommunity')}
                <Icon name="chevronRight" size={14} />
              </Link>
            </div>
          </aside>

          <article className={styles.content} lang={doc.language}>
            {doc.language !== language && (
              <p className={styles.languageNote} lang={language}>
                {t('docs.availableIn', {
                  language: new Intl.DisplayNames([language], { type: 'language' }).of(doc.language),
                })}
              </p>
            )}

            <Markdown
              collapsible="h3"
              content={doc.body}
              resolveLink={(href) => docHref(doc, language, href)}
            />

            {next && (
              <div className={styles.next}>
                <div className={styles.nextText}>
                  <span className={styles.nextKicker}>{t('install.ready')}</span>
                  <span className={styles.nextTitle}>{next.title}</span>
                  <span className={styles.nextIntro}>{next.intro}</span>
                </div>

                <Link className={styles.nextAction} to={`/${language}/docs/${next.slug}`}>
                  {t('install.keepReading')}
                </Link>
              </div>
            )}
          </article>
        </section>
      </main>
      <Footer />
    </div>
  );
}

/**
 * Open the first available document when the URL contains no document slug.
 */
export function DocsIndex() {
  const { language } = useI18n();
  const [first] = docsFor(language);

  return <Navigate replace to={`/${language}/docs/${first?.slug ?? 'install'}`} />;
}

