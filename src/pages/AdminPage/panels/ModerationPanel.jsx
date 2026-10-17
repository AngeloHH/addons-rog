import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api, queryPath } from '../../../api/client.js';
import { date, bytes, profilePath } from '../../../api/presenters.js';
import { useApi } from '../../../hooks/useApi.js';
import { useI18n } from '../../../i18n/I18nProvider.jsx';
import { addonPath } from '../../../router/paths.js';
import { ApiState } from '../../../components/ApiState/ApiState.jsx';
import Pagination from '../../../components/Pagination/Pagination.jsx';
import Panel from '../../../components/Panel/Panel.jsx';
import RecordRow from '../../../components/RecordRow/RecordRow.jsx';
import StatTiles from '../../../components/StatTiles/StatTiles.jsx';
import Toolbar from './Toolbar.jsx';
import SectionLink from './SectionLink.jsx';
import ActionForm from './ActionForm.jsx';
const paths = { queue: '/admin/submissions', reports: '/admin/reports', requests: '/requests' };
const filtersByKind = {
  queue: { kind: ['addon', 'version'], flagged: ['flagged', 'clean'] },
  reports: {
    severity: ['critical', 'high', 'medium', 'low'],
    type: ['file', 'credit', 'listing', 'comment'],
    state: ['open', 'closed'],
  },
  requests: { state: ['open', 'volunteered', 'inQueue', 'fulfilled', 'closed'] },
};
const rules = ['clean', 'free', 'credit', 'version', 'duplicates', 'honest', 'privacy'];
export function SubmissionInspection({ id }) {
  const resource = useApi(`/admin/submissions/${id}`);
  const { t } = useI18n();
  const item = resource.data?.submission;
  return (
    <Panel>
      <ApiState {...resource} retry={resource.reload} />
      {item && (
        <>
          <h3>
            {item.name} · {item.version}
          </h3>
          <p>
            {item.status} · {item.expansionId} · {item.categoryId}
          </p>
          <p>{item.summary}</p>
          <p style={{ whiteSpace: 'pre-wrap' }}>{item.description}</p>
          <p>
            {item.originalAuthor}{' '}
            {item.sourceUrl && (
              <a href={item.sourceUrl} target="_blank" rel="noreferrer">
                {item.sourceUrl}
              </a>
            )}
          </p>
          <p>{(item.tags || []).join(', ')}</p>
          {item.icon && <img src={item.icon.url} width="96" alt="" />}
          {item.screenshots.map((image) => (
            <img src={image.url} key={image.id} width="240" alt="" />
          ))}
          <p>
            {item.upload?.status} · {bytes(item.upload?.size)} · {item.upload?.folders.join(', ')}
          </p>
          {item.upload?.warnings.map((warning, index) => (
            <p key={index}>{warning.message || warning.code}</p>
          ))}
          <a href={`/api/admin/submissions/${id}/file`}>{t("ui.downloadZip")}</a>
        </>
      )}
    </Panel>
  );
}
export default function ModerationPanel({ kind, summary, range = '7d', onChanged }) {
  const { language, t } = useI18n();

  const [filters, setFilters] = useState({});
  const [page, setPage] = useState(1);
  const [action, setAction] = useState(null);
  const [inspected, setInspected] = useState(null);
  const resource = useApi(
    queryPath(paths[kind], {
      ...filters,
      page,
      pageSize: summary ? 5 : 20,
      ...(kind === 'queue' ? { range } : {}),
    }),
  );
  function change(key, value) {
    setPage(1);
    setFilters((previous) => ({ ...previous, [key]: value }));
  }
  function choose(item, operation) {
    if (operation === 'inspect') {
      setInspected(kind === 'requests' ? item.submissionId : item.id);
      return;
    }
    const fields =
      operation === 'reject'
        ? [
            {
              name: 'ruleId',
              kind: 'select',
              label: t('admin.rule'),
              options: rules.map((id) => ({ id, name: id })),
            },
            { name: 'reason', kind: 'textarea', label: t("contact.reason") },
          ]
        : operation === 'link'
          ? [
              {
                name: 'addonSlug',
                label: t("ui.publishedAddonSlug"),
              },
              { name: 'submissionId', label: t("ui.submissionId") },
            ]
          : ['close', 'warn', 'takedown', 'dismiss', 'reopen'].includes(operation)
            ? [
                {
                  name: kind === 'reports' ? 'note' : 'reason',
                  kind: 'textarea',
                  label: t("ui.reasonNote"),
                },
              ]
            : [];
    setAction({
      key: `${item.id}:${operation}`,
      title: `${t(`admin.${operation === 'close' ? 'close' : operation === 'link' ? 'linkUpload' : operation === 'ping' ? 'pingAuthors' : operation}`)} · ${item.name || item.addonName || item.target?.label || `#${item.id}`}`,
      fields,
      item,
      operation,
    });
  }
  async function submit(values) {
    const { item, operation } = action;
    if (kind === 'queue')
      await api.post(`/admin/submissions/${item.id}/decision`, { decision: operation, ...values });
    if (kind === 'reports')
      await api.post(`/admin/reports/${item.id}/action`, { action: operation, ...values });
    if (kind === 'requests')
      await api.post(`/admin/requests/${item.id}/action`, {
        action: operation,
        ...Object.fromEntries(Object.entries(values).filter(([, value]) => value)),
        ...(values.submissionId ? { submissionId: Number(values.submissionId) } : {}),
      });
    setAction(null);
    setInspected(null);
    resource.reload();
    onChanged?.();
  }
  const counts =
    kind === 'queue'
      ? resource.data?.counts
      : kind === 'reports'
        ? resource.data?.severityCounts
        : resource.data?.stateCounts;
  const titles = {
    queue: t('admin.reviewQueue'),
    reports: t('admin.reports'),
    requests: t('admin.topRequests'),
  };
  return (
    <>
      {!summary && counts && (
        <StatTiles
          columns={{ sm: 2, md: 4, lg: 4 }}
          items={Object.entries(counts).map(([id, value]) => ({
            id,
            label: id,
            value: value ?? '—',
          }))}
        />
      )}
      <Panel
        padded={false}
        title={titles[kind]}
        aside={summary && <SectionLink section={kind} label={t("ui.viewAll")} />}
      >
        {!summary && (
          <Toolbar
            query={filters.q || ''}
            onQuery={(value) => change('q', value)}
            placeholder={t("explore.search")}
            filters={Object.entries({
              ...filtersByKind[kind],
              ...(kind === 'requests'
                ? { expansion: Object.keys(resource.data?.expansionCounts || {}) }
                : {}),
            }).map(([key, options]) => ({
              id: key,
              label: key,
              value: filters[key] || '',
              onChange: (value) => change(key, value),
              options: [
                { value: '', label: t("ui.allItems") },
                ...options.map((value) => ({ value, label: value })),
              ],
            }))}
            sort={
              kind !== 'reports'
                ? {
                    value: filters.sort || (kind === 'queue' ? 'oldest' : 'votes'),
                    onChange: (value) => change('sort', value),
                    options: (kind === 'queue'
                      ? ['oldest', 'newest', 'flagged', 'size']
                      : ['votes', 'newest', 'oldest']
                    ).map((value) => ({ value, label: value })),
                  }
                : undefined
            }
          />
        )}
        <ApiState {...resource} retry={resource.reload} empty={resource.data?.items.length === 0} />
        {(resource.data?.items || []).map((item) => {
          const operations =
            kind === 'queue'
              ? ['inspect', 'approve', 'reject']
              : kind === 'reports'
                ? item.closed
                  ? ['reopen']
                  : ['warn', 'takedown', 'dismiss']
                : ['closed', 'fulfilled'].includes(item.state)
                  ? []
                  : [
                      ...(['open', 'volunteered', 'inQueue'].includes(item.state)
                        ? [...(item.submissionId ? ['inspect'] : []), 'link', 'ping']
                        : []),
                      ...(['open', 'volunteered'].includes(item.state) ? ['close'] : []),
                    ];
          const author = item.author || item.reporter;
          return (
            <RecordRow
              key={item.id}
              compact={summary}
              title={item.name || item.addonName || item.target?.label || `#${item.id}`}
              titleTo={item.addon ? addonPath(language, item.addon) : undefined}
              image={item.iconUrl}
              score={kind === 'requests' ? item.votes : undefined}
              text={item.text || item.note}
              flag={item.flag ? { text: item.flag.message, tone: item.flag.severity } : undefined}
              tags={[
                { label: item.expansionId || item.severity || item.kind },
                {
                  label:
                    item.state ||
                    (kind === 'reports' ? (item.closed ? 'closed' : 'open') : item.version),
                },
              ]}
              meta={
                <>
                  {author &&
                    (author.id ? (
                      <Link to={profilePath(language, author.username || author.name)}>
                        {author.name}
                      </Link>
                    ) : (
                      author.name
                    ))}{' '}
                  · {date(item.createdAt || item.submittedAt, language)}
                  {kind === 'queue' && ` · ${bytes(item.size)} · ${item.folders}`}
                  {kind === 'reports' && item.target?.addonSlug && (
                    <>
                      {' '}
                      ·{' '}
                      <Link
                        to={`/${language}/explore?q=${encodeURIComponent(item.target.addonSlug)}`}
                      >
                        {t("ui.viewAddon")}
                      </Link>
                    </>
                  )}
                </>
              }
              actions={[
                ...(kind === 'queue'
                  ? [{ id: 'zip', label: 'ZIP', href: `/api/admin/submissions/${item.id}/file` }]
                  : []),
                ...operations.map((operation) => ({
                  id: operation,
                  label: t(
                    `admin.${operation === 'link' ? 'linkUpload' : operation === 'ping' ? 'pingAuthors' : operation}`,
                  ),
                  onClick: () => choose(item, operation),
                })),
              ]}
            />
          );
        })}
        {!summary && (
          <Pagination page={resource.data?.page} onChange={setPage} disabled={resource.loading} />
        )}
      </Panel>
      {inspected && (
        <>
          <button type="button" onClick={() => setInspected(null)}>
            {t("ui.closeDetails")}
          </button>
          <SubmissionInspection id={inspected} />
        </>
      )}
      <ActionForm action={action} close={() => setAction(null)} onSubmit={submit} />
    </>
  );
}
