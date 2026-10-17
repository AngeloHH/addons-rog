import { useSession } from '../../context/SessionContext.jsx';
import { useState } from 'react';
import { api, queryPath } from '../../api/client.js';
import { presentAddon, bytes, date, profilePath } from '../../api/presenters.js';
import { useApi, useAction } from '../../hooks/useApi.js';
import { ApiState, RequireAccount } from '../../components/ApiState/ApiState.jsx';
import Pagination from '../../components/Pagination/Pagination.jsx';
import { Link, Navigate, useParams } from 'react-router-dom';
import AddonHeader from '../../components/AddonHeader/AddonHeader.jsx';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import BadgeRow from '../../components/BadgeRow/BadgeRow.jsx';
import DetailList from '../../components/DetailList/DetailList.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import GridSection from '../../components/GridSection/GridSection.jsx';
import Icon from '../../components/Icon/Icon.jsx';
import SiteHeader from '../../components/SiteHeader/SiteHeader.jsx';
import StatTiles from '../../components/StatTiles/StatTiles.jsx';
import TagList from '../../components/TagList/TagList.jsx';
import ListSection from '../../components/ListSection/ListSection.jsx';
import MarkedLinks from '../../components/MarkedLinks/MarkedLinks.jsx';
import PostForm from '../../components/PostForm/PostForm.jsx';
import StarRating from '../../components/StarRating/StarRating.jsx';
import { findExpansion } from '../../config/expansions.js';
import { formatDate } from '../../lib/date.js';
import { addonPath, explorePath } from '../../router/paths.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import NotFoundPage from '../NotFoundPage/NotFoundPage.jsx';
import styles from './AddonPage.module.scss';
export default function AddonPage() {
  const { category, expansion, slug } = useParams();
  const { language, t } = useI18n();
  const { user, staff } = useSession();
  const resource = useApi(queryPath(`/addons/${encodeURIComponent(slug)}`, { expansion }));
  const [page, setPage] = useState(1);
  const reviews = useApi(queryPath(`/addons/${encodeURIComponent(slug)}/comments`, { page }));
  const action = useAction();
  const [rating, setRating] = useState(0);
  const [reporting, setReporting] = useState(false);
  if (resource.error?.status === 404) return <NotFoundPage />;
  if (!resource.data)
    return (
      <div className="site-frame">
        <AuthBar />
        <SiteHeader />
        <main className="app-shell">
          <ApiState {...resource} retry={resource.reload} />
        </main>
        <Footer />
      </div>
    );
  const detail = resource.data.addon;
  const addon = presentAddon(detail, language);
  if (addon.expansionId !== expansion || addon.categoryId !== category)
    return <Navigate to={addonPath(language, addon)} replace />;
  const about = (detail.description || detail.summary || '').split('\n').filter(Boolean);
  const versions = (detail.versions || []).map((v) => ({
    ...v,
    date: v.releasedAt,
    expansion: v.expansionId,
    client: v.clientPatch,
    size: bytes(v.size),
    author: v.author.name,
    authorUsername: v.author.username || v.author.name,
    latest: v.isLatest,
    href: v.download?.url,
  }));
  const comments = (reviews.data?.items || []).map((c) => ({
    ...c,
    user: (c.author || c.user).name,
    username: (c.author || c.user).username || (c.author || c.user).name,
    expansion: c.expansionId,
    when: date(c.createdAt, language),
    text: c.content || c.text,
  }));
  const installSteps = t('addon.installSteps');
  return (
    <div className="site-frame">
      <AuthBar />
      <SiteHeader />
      <main className="app-shell">
        <AddonHeader
          addon={addon}
          busy={action.busy}
          onFavorite={() =>
            action.run(async () => {
              await api.put(`/addons/${encodeURIComponent(slug)}/favorite`, {
                favorited: !detail.viewer?.isFavorite,
              });
              resource.reload();
            })
          }
          onReport={() => setReporting(!reporting)}
          tabs={{
            'addon.tabs.description': '#description',
            'addon.tabs.versions': '#versions',
            'addon.tabs.installation': '#installation',
            'addon.tabs.comments': '#comments',
          }}
        />
        {(staff || user?.id === detail.author.id) && (
          <Link to={`/${language}/upload?addonSlug=${encodeURIComponent(slug)}`}>
            {t("ui.uploadANewVersion")}
          </Link>
        )}
        <ApiState error={action.error} loading={action.busy} />
        {reporting && (
          <RequireAccount>
            <PostForm
              title={t("ui.reportAddon")}
              fields={[{ name: 'text', kind: 'textarea', label: t("contact.reason") }]}
              submitLabel={t("ui.sendReport")}
              onSubmit={async (values) => {
                await api.post('/reports', {
                  target: { kind: 'addon', id: addon.id },
                  type: 'listing',
                  text: values.text,
                });
                setReporting(false);
              }}
            />
          </RequireAccount>
        )}
        <section className={styles.detail}>
          <div className={styles.column}>
            <div className={styles.block} id="description">
              <h2 className={styles.heading}>{t('addon.sections.description')}</h2>
              <div className={styles.about}>
                {about.map((paragraph) => (
                  <p key={paragraph}>{paragraph}</p>
                ))}
              </div>
              {detail.features && (
                <GridSection
                  columns={{ sm: 1, md: 2, lg: 2 }}
                  items={detail.features}
                  title={t('addon.features')}
                />
              )}
              <GridSection
                columns={{ sm: 1, md: 2, lg: 3 }}
                images
                items={detail.screenshots || []}
                title={t('addon.screenshots')}
              />
            </div>
            <div className={styles.block} id="versions">
              <div className={styles.commentsHeader}>
                <h2 className={styles.heading}>{t('addon.sections.versions')}</h2>
                {detail.versionsSince && (
                  <span className={styles.commentsCount}>
                    {t('addon.versionsUploaded', {
                      count: versions.length,
                      year: detail.versionsSince,
                    })}
                  </span>
                )}
              </div>
              <ListSection
                items={versions.map((version) => {
                  const expansion = findExpansion(version.expansion);
                  return {
                    id: version.id,
                    mark: `v${version.number}`,
                    title: version.title,
                    badge: { label: version.expansion, color: expansion?.colorAccent },
                    description: version.notes,
                    meta: [
                      formatDate(version.date, language),
                      t('addon.client', { client: version.client }),
                      version.size,
                      t('addon.downloadsShort', { count: version.downloads }),
                    ].join(' · '),
                    aside: (
                      <>
                        {t('addon.by')}{' '}
                        <Link
                          className={styles.author}
                          to={profilePath(language, version.authorUsername)}
                        >
                          {version.author}
                        </Link>
                      </>
                    ),
                    highlight: version.latest === true,
                    action: {
                      icon: 'download',
                      label: t('addon.downloadVersionShort'),
                      href: version.href,
                    },
                    card: {
                      kicker: version.expansion,
                      footer: t('addon.downloadsCount', { count: version.downloads }),
                    },
                    tone: {
                      card:
                        expansion &&
                        `linear-gradient(160deg, ${expansion.colorDark} 0%, #0f0c08 70%)`,
                      ...version.tone,
                    },
                  };
                })}
              />
            </div>
            <div className={styles.block} id="installation">
              <h2 className={styles.heading}>{t('addon.sections.installation')}</h2>
              <ol className={styles.steps}>
                {installSteps.map((step, index) => (
                  <BadgeRow
                    as="li"
                    boxed
                    key={step.title}
                    mark={index + 1}
                    text={step.text}
                    title={step.title}
                  />
                ))}
              </ol>
              <a className={styles.guideLink} href={`/${language}/docs/install`}>
                {t('addon.fullGuide')}
                <Icon className={styles.guideArrow} name="arrowRight" size={14} />
              </a>
            </div>
            <div className={styles.block} id="comments">
              <div className={styles.commentsHeader}>
                <h2 className={styles.heading}>{t('addon.sections.comments')}</h2>
                {comments.length > 0 && (
                  <span className={styles.commentsCount}>
                    {t('addon.commentsCount', {
                      count: reviews.data?.page.total ?? comments.length,
                    })}
                  </span>
                )}
              </div>
              <ApiState {...reviews} retry={reviews.reload} />
              {comments.length > 0 ? (
                <div className={styles.comments}>
                  {comments.map((comment) => {
                    const tone = findExpansion(comment.expansion);
                    return (
                      <BadgeRow
                        as="article"
                        key={comment.id}
                        mark={comment.user[0]}
                        rating={comment.rating}
                        subtitle={comment.when}
                        text={comment.text}
                        title={comment.user}
                        titleTo={profilePath(language, comment.username)}
                        tone={{ accent: tone?.colorAccent, background: tone?.colorDark }}
                      />
                    );
                  })}
                </div>
              ) : (
                <p className={styles.noComments}>{t('addon.noComments')}</p>
              )}
              <Pagination page={reviews.data?.page} onChange={setPage} disabled={reviews.loading} />
              <RequireAccount>
                <PostForm
                  onSubmit={async (values) => {
                    await api.put(`/addons/${encodeURIComponent(slug)}/my-comment`, {
                      content: values.comment,
                      rating,
                      expansionId: expansion,
                    });
                    resource.reload();
                    reviews.reload();
                  }}
                  submitReady={rating > 0}
                  framed={false}
                  title={t('addon.leaveComment')}
                  fields={[
                    {
                      name: 'comment',
                      kind: 'textarea',
                      rows: 4,
                      placeholder: t('addon.commentPlaceholder'),
                    },
                  ]}
                  footer={
                    <StarRating
                      onChange={setRating}
                      label={t('addon.ratingLabel')}
                      starLabel={(count) => t('addon.rateStars', { count })}
                    />
                  }
                  submitLabel={t('addon.postComment')}
                />
              </RequireAccount>
            </div>
          </div>
          <aside className={styles.aside}>
            <StatTiles
              items={[
                { label: t('addon.stats.version'), value: addon.version },
                {
                  label: t('addon.stats.client'),
                  value: findExpansion(addon.expansion)?.patch ?? '-',
                },
                { label: t('addon.stats.downloads'), value: addon.downloads },
                { label: t('addon.stats.size'), value: addon.fileSize },
              ]}
            />
            <DetailList
              title={t('addon.details')}
              items={[
                {
                  term: t('addon.detailAuthor'),
                  value: (
                    <Link
                      className={styles.author}
                      to={profilePath(language, addon.authorUsername)}
                    >
                      {addon.author}
                    </Link>
                  ),
                },
                ...(detail.createdAt
                  ? [
                      {
                        term: t('addon.detailUploaded'),
                        value: formatDate(detail.createdAt, language),
                      },
                    ]
                  : []),
                { term: t('addon.detailUpdated'), value: formatDate(addon.releasedAt, language) },
                ...(detail.licence
                  ? [{ term: t('addon.detailLicence'), value: detail.licence }]
                  : []),
                ...(detail.languages
                  ? [{ term: t('addon.detailLanguages'), value: detail.languages.join(', ') }]
                  : []),
              ]}
            />
            {detail.compatibility && (
              <MarkedLinks
                title={t('addon.alsoOn')}
                items={detail.compatibility.map((entry) => {
                  const expansion = findExpansion(entry.expansionId);
                  return {
                    id: entry.expansionId,
                    label: expansion?.name ?? entry.expansionId,
                    trailing: `v${entry.latestVersion}`,
                    to: addonPath(language, { ...addon, expansionId: entry.expansionId }),
                    tone: { accent: expansion?.colorAccent, background: expansion?.colorDark },
                  };
                })}
              />
            )}
            {detail.tags && (
              <TagList
                title={t('addon.tags')}
                items={detail.tags.map((tag) => ({
                  label: tag,
                  to: `${explorePath(language)}?q=${encodeURIComponent(tag)}`,
                }))}
              />
            )}
          </aside>
        </section>
      </main>
      <Footer />
    </div>
  );
}