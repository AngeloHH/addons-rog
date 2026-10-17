import { api, queryPath } from '../../api/client.js';
import { useApi, useAction } from '../../hooks/useApi.js';
import { useSession } from '../../context/SessionContext.jsx';
import { ApiState, RequireAccount } from '../../components/ApiState/ApiState.jsx';
import Pagination from '../../components/Pagination/Pagination.jsx';
import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import Icon from '../../components/Icon/Icon.jsx';
import TagSelect from '../../components/TagSelect/TagSelect.jsx';
import MarkedLinks from '../../components/MarkedLinks/MarkedLinks.jsx';
import { addonPath } from '../../router/paths.js';
import SiteHeader from '../../components/SiteHeader/SiteHeader.jsx';
import { uploadLimits, uploadReminders } from '../../config/upload.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './UploadPage.module.scss';

function formatSize(bytes) {
  const mb = bytes / (1024 * 1024);
  return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function Step({ children, number, subtitle, title }) {
  return (
    <section className={styles.step}>
      <div className={styles.stepHead}>
        <span className={styles.stepNumber}>{number}</span>
        <div className={styles.stepText}>
          <h2 className={styles.stepTitle}>{title}</h2>
          <span className={styles.stepSubtitle}>{subtitle}</span>
        </div>
      </div>

      {children}
    </section>
  );
}

export default function UploadPage() {
  const [params] = useSearchParams();
  return (
    <div className={styles.page}>
      <RequireAccount>
        <UploadForm key={params.toString()} />
      </RequireAccount>
    </div>
  );
}

function UploadForm() {
  const { language, t } = useI18n();
  const fileInput = useRef(null);
  const iconInput = useRef(null);
  const screenshotInput = useRef(null);

  const { user } = useSession();

  const uploader = { name: user.name, initial: user.name[0] };
  const [params, setParams] = useSearchParams();
  const [page, setPage] = useState(1);
  const list = useApi(queryPath('/submissions', { page }));
  const draftId = params.get('submissionId');
  const draft = useApi(draftId ? `/submissions/${encodeURIComponent(draftId)}` : null);
  const taxonomy = useApi('/taxonomy');
  const stats = useApi('/site/stats');
  const expansions = taxonomy.data?.expansions || [];
  const [saved, setSaved] = useState(null);
  const [upload, setUpload] = useState(null);
  const [icon, setIcon] = useState(null);
  const [screenshots, setScreenshots] = useState([]);
  const [values, setValues] = useState({
    name: '',
    version: '',
    short: '',
    full: '',
    tags: '',
    addonSlug: params.get('addonSlug') || '',
  });
  const [roles, setRoles] = useState([]);
  const [checks, setChecks] = useState({ rules: false, tested: false });
  const [categoryIds, setCategoryIds] = useState([]);
  const [classIds, setClassIds] = useState([]);
  const [professionIds, setProfessionIds] = useState([]);
  const action = useAction();
  const fileAction = useAction();
  const [nameQuery, setNameQuery] = useState(null);
  useEffect(() => {
    const timer = setTimeout(
      () =>
        setNameQuery(
          values.name.trim()
            ? queryPath('/addons/check-name', {
                name: values.name.trim(),
                expansion: values.expansion,
                version: values.version,
              })
            : null,
        ),
      400,
    );
    return () => clearTimeout(timer);
  }, [values.name, values.expansion, values.version]);
  const nameCheck = useApi(nameQuery);
  useEffect(() => {
    if (!draft.data) return;
    const item = draft.data.submission;
    setSaved(item);
    setUpload(item.upload);
    setIcon(item.icon);
    setScreenshots(item.screenshots || []);
    setRoles(item.roles);
    setCategoryIds(item.categoryIds || (item.categoryId ? [item.categoryId] : []));
    setClassIds(item.classIds || []);
    setProfessionIds(item.professionIds || []);
    setChecks({ rules: item.rulesAccepted, tested: item.testedConfirmed });
    setValues({
      name: item.name || '',
      version: item.version || '',
      short: item.summary || '',
      full: item.description || '',
      tags: (item.tags || []).join(', '),
      expansion: item.expansionId || '',
      author: item.originalAuthor || '',
      site: item.sourceUrl || '',
      addonSlug: item.addonSlug || '',
    });
  }, [draft.data]);
  const file = upload ? { name: upload.fileName, size: formatSize(upload.size) } : null;
  const scanned = !!upload;
  const progress = scanned ? 100 : 0;
  const uploadDetectedFolders = upload?.folders || [];
  const taken = nameCheck.data?.available === false && !values.addonSlug;
  const locked =
    action.busy ||
    fileAction.busy ||
    (saved && saved.status !== 'draft') ||
    (draftId && !draft.data) ||
    user.status !== 'active';
  async function save(submit = false) {
    const body = {
      uploadId: upload?.id || null,
      addonSlug: values.addonSlug || null,
      requestId: saved?.requestId || Number(params.get('requestId')) || null,
      name: values.name || null,
      version: values.version || null,
      summary: values.short || null,
      description: values.full || null,
      categoryIds,
      expansionId: values.expansion || null,
      originalAuthor: values.author || null,
      sourceUrl: values.site || null,
      roles,
      classIds,
      professionIds,
      tags: values.tags
        .split(',')
        .map((v) => v.trim().toLowerCase())
        .filter(Boolean),
      iconMediaId: icon?.id || null,
      screenshotMediaIds: screenshots.map((item) => item.id),
      rulesAccepted: checks.rules,
      testedConfirmed: checks.tested,
    };
    let result = saved
      ? await api.patch(`/submissions/${saved.id}`, { ...body, revision: saved.revision })
      : await api.post('/submissions', body);
    setSaved(result.submission);
    list.reload();
    if (submit) {
      result = await api.post(`/submissions/${result.submission.id}/submit`, {
        revision: result.submission.revision,
      });
      setSaved(result.submission);
      list.reload();
    }
    if (!draftId) setParams({ submissionId: String(result.submission.id) }, { replace: true });
  }
  async function pickMedia(event, kind) {
    const chosen = event.target.files?.[0];
    event.target.value = '';
    if (
      !chosen ||
      locked ||
      (kind === 'screenshot' && screenshots.length >= uploadLimits.screenshots)
    )
      return;
    await fileAction.run(async () => {
      const body = new FormData();
      body.append('file', chosen);
      body.append('kind', kind);
      const result = await api.post('/media', body);
      if (kind === 'icon') setIcon(result.media);
      else setScreenshots((items) => [...items, result.media]);
    });
  }
  const categoryLabels = {
    pve: 'PvE / Raids',
    pvp: 'PvP',
    dps: 'DPS',
    healing: t("ui.healing"),
    tank: t("ui.tank"),
    gui: t("ui.interfaceGui"),
    map: t("ui.mapWorld"),
    professions: t("homeCategories.groups.professions"),
  };
  const selectionGroups = [
    ['category', t("ui.generalCategories")],
    ['classes', t("homeCategories.groups.classes")],
    ['professions', t("homeCategories.groups.professions")],
  ].map(([id, label]) => ({
    id,
    label,
    items: (taxonomy.data?.groups.find((group) => group.id === id)?.items || []).map((item) => ({
      id: `${id}:${item.id}`,
      label: (id === 'category' && categoryLabels[item.id]) || item.name,
    })),
  }));
  const selections = [
    ...categoryIds.map((id) => `category:${id}`),
    ...classIds.map((id) => `classes:${id}`),
    ...professionIds.map((id) => `professions:${id}`),
  ];
  function changeSelections(items) {
    const ids = (group) =>
      items
        .filter((item) => item.startsWith(`${group}:`))
        .map((item) => item.slice(group.length + 1));
    setCategoryIds(ids('category'));
    setClassIds(ids('classes'));
    setProfessionIds(ids('professions'));
  }
  const shortLeft = uploadLimits.shortMax - values.short.length;
  const ready = Boolean(
    upload &&
    upload.status !== 'blocked' &&
    (values.addonSlug || (values.name.trim() && values.short.trim() && categoryIds.length > 0)) &&
    values.expansion &&
    values.version.trim() &&
    checks.rules &&
    checks.tested &&
    !locked,
  );

  const missing = [
    !file && t('upload.missingFile'),
    !values.name.trim() && t('upload.missingName'),
    !values.version.trim() && t('upload.missingVersion'),
    !values.short.trim() && t('upload.missingShort'),
    !values.addonSlug &&
      categoryIds.length === 0 &&
      t("ui.requiredCategory"),
    (!checks.rules || !checks.tested) && t('upload.missingChecks'),
  ].filter(Boolean);

  function set(field, value) {
    setValues((current) => ({ ...current, [field]: value }));
  }

  function pick(event) {
    const chosen = event.target.files?.[0];
    event.target.value = '';
    uploadFile(chosen);
  }

  function uploadFile(chosen) {
    if (!chosen || locked) return;
    fileAction.run(async () => {
      const body = new FormData();
      body.append('file', chosen);
      const result = await api.post('/uploads', body);
      setUpload(result.upload);
      if (result.upload.detectedExpansionId) set('expansion', result.upload.detectedExpansionId);
    });
  }

  function toggleRole(role) {
    setRoles((current) =>
      current.includes(role) ? current.filter((item) => item !== role) : [...current, role],
    );
  }

  return (
    <div className="site-frame">
      <AuthBar />
      <SiteHeader />
      <main className={`app-shell ${styles.content}`}>
        <section className={styles.intro}>
          <div className={styles.introInner}>
            <div className={styles.introContent}>
              <div className={styles.introKicker}>
                <span />
                {t('upload.kicker')}
              </div>
              <h1 className={styles.introTitle}>{t('upload.title')}</h1>
              <p className={styles.introText}>{t('upload.intro')}</p>
            </div>
            <div className={styles.uploader}>
              <span>{t('upload.uploadingAs')}</span>
              <Link className={styles.uploaderLink} to={`/${language}/profile`}>
                <span className={styles.uploaderInitial}>{uploader.initial}</span>
                {uploader.name}
              </Link>
            </div>
          </div>
        </section>

        <ApiState {...taxonomy} retry={taxonomy.reload} />
        <ApiState {...draft} retry={draft.reload} />
        <ApiState
          error={action.error || fileAction.error}
          loading={action.busy || fileAction.busy}
          done={action.done}
        />
        {(saved || user.status !== 'active' || upload?.warnings?.length > 0) && (
          <div className={styles.submissionBar}>
            <Link className={styles.fileChange} to={`/${language}/upload`}>
              {t("ui.startANewSubmission")}
            </Link>
            {saved && (
              <p className={styles.submissionStatus} role="status">
                #{saved.id} · {saved.status}
                {saved.decision?.reason ? ` · ${saved.decision.reason}` : ''}
              </p>
            )}
            {user.status !== 'active' && <ApiState error={{ status: 403 }} />}
            {upload?.warnings?.map((warning, index) => (
              <p className={styles.submissionStatus} role="status" key={index}>
                {warning.message || warning.code}
              </p>
            ))}
          </div>
        )}
        {params.get('view') !== 'submissions' && (
          <fieldset disabled={!!locked} className={styles.formFieldset}>
            <div className={styles.layout}>
              <div className={styles.steps}>
                <Step number="1" subtitle={t('upload.fileSubtitle')} title={t('upload.fileTitle')}>
                  <input
                    accept=".zip"
                    className={styles.hiddenInput}
                    ref={fileInput}
                    type="file"
                    onChange={pick}
                  />

                  {file ? (
                    <div className={styles.fileCard}>
                      <div className={styles.fileHead}>
                        <span className={styles.fileKind}>ZIP</span>
                        <span className={styles.fileText}>
                          <span className={styles.fileName}>{file.name}</span>
                          <span className={styles.fileMeta}>
                            {file.size} · {scanned ? t('upload.analysed') : t('upload.uploading')}
                          </span>
                        </span>
                        <button
                          className={styles.fileChange}
                          type="button"
                          onClick={() => fileInput.current?.click()}
                        >
                          {t('upload.change')}
                        </button>
                      </div>

                      <div className={styles.progress}>
                        <div className={styles.progressHead}>
                          <span>{scanned ? t('upload.uploadDone') : t('upload.uploadingTo')}</span>
                          <span>{progress}%</span>
                        </div>
                        <div className={styles.bar}>
                          <div className={styles.barFill} style={{ width: `${progress}%` }} />
                        </div>
                      </div>

                      {scanned && (
                        <div className={styles.folders}>
                          <div className={styles.folderList}>
                            {uploadDetectedFolders.map((folder) => (
                              <span className={styles.folder} key={folder}>
                                {folder}
                              </span>
                            ))}
                          </div>
                          <span className={styles.folderNote}>
                            <Icon name="check" size={13} />
                            {t('upload.validStructure', { count: uploadDetectedFolders.length })}
                          </span>
                        </div>
                      )}
                    </div>
                  ) : (
                    <button
                      className={styles.dropzone}
                      onDragOver={(event) => event.preventDefault()}
                      onDrop={(event) => {
                        event.preventDefault();
                        uploadFile(event.dataTransfer.files?.[0]);
                      }}
                      type="button"
                      onClick={() => fileInput.current?.click()}
                    >
                      <span className={styles.dropIcon}>
                        <Icon name="upload" size={24} />
                      </span>
                      <span className={styles.dropTitle}>{t('upload.drop')}</span>
                      <span className={styles.dropNote}>{t('upload.dropNote')}</span>
                    </button>
                  )}
                </Step>

                <Step
                  number="2"
                  subtitle={t('upload.listingSubtitle')}
                  title={t('upload.listingTitle')}
                >
                  <div className={styles.fields}>
                    <label className={styles.label} data-wide="true">
                      {t('upload.nameLabel')}
                      <input
                        className={styles.field}
                        placeholder={t('upload.namePlaceholder')}
                        type="text"
                        value={values.name}
                        onChange={(event) => set('name', event.target.value)}
                      />
                      {taken && (
                        <span className={styles.warning}>
                          {t('upload.nameTaken')}{' '}
                          {nameCheck.data?.existing && !nameCheck.data.existing.pending && (
                            <Link
                              className={styles.warningLink}
                              to={addonPath(language, nameCheck.data.existing)}
                            >
                              {t('upload.nameTakenLink')} <Icon name="arrowRight" size={14} />
                            </Link>
                          )}
                        </span>
                      )}
                    </label>
                    {nameCheck.error && <ApiState error={nameCheck.error} />}
                    <label className={styles.label}>
                      {t('upload.versionLabel')}
                      <span className={styles.hintText}>{t('upload.versionHint')}</span>
                      <input
                        className={styles.field}
                        placeholder={t('upload.versionPlaceholder')}
                        type="text"
                        value={values.version}
                        onChange={(event) => set('version', event.target.value)}
                      />
                    </label>
                    <div className={styles.label}>
                      <span id="upload-roles-label">{t('upload.rolesLabel')}</span>
                      <span className={styles.hintText}>{t('upload.rolesHint')}</span>
                      <div
                        className={styles.roles}
                        role="group"
                        aria-labelledby="upload-roles-label"
                      >
                        {['tank', 'healer', 'dps'].map((role, index) => (
                          <button
                            aria-pressed={roles.includes(role)}
                            className={styles.role}
                            data-selected={roles.includes(role) ? 'true' : 'false'}
                            key={role}
                            type="button"
                            onClick={() => toggleRole(role)}
                          >
                            {['Tank', 'Healer', 'DPS'][index]}
                          </button>
                        ))}
                      </div>
                    </div>
                    <TagSelect
                      label={t("homeCategories.title")}
                      placeholder={t("ui.typeToSearch")}
                      groups={selectionGroups}
                      value={selections}
                      onChange={changeSelections}
                      disabled={!!locked || taxonomy.loading || !!taxonomy.error}
                      emptyText={t("ui.noMatchingOptions")}
                      removeLabel={(label) => t("ui.removeOption", { label })}
                      hint={
                        selections.length > 0 && categoryIds.length === 0 && !values.addonSlug
                          ? t("ui.categoryRequired")
                          : undefined
                      }
                    />
                    <label className={styles.label}>
                      {t('upload.expansionLabel')}
                      <select
                        className={styles.field}
                        value={values.expansion ?? ''}
                        onChange={(event) => set('expansion', event.target.value)}
                      >
                        <option value="">{t('upload.expansionPlaceholder')}</option>
                        {expansions.map((option) => (
                          <option key={option.id} value={option.id}>
                            {option.name}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label className={styles.label} data-wide="true">
                      {t('upload.shortLabel')}
                      <span className={styles.hintText}>
                        {t('upload.shortHint', { count: shortLeft })}
                      </span>
                      <input
                        className={styles.field}
                        placeholder={t('upload.shortPlaceholder')}
                        type="text"
                        value={values.short}
                        onChange={(event) =>
                          set('short', event.target.value.slice(0, uploadLimits.shortMax))
                        }
                      />
                    </label>
                    <label className={styles.label} data-wide="true">
                      {t('upload.fullLabel')}
                      <span className={styles.hintText}>{t('upload.fullHint')}</span>
                      <textarea
                        className={styles.textarea}
                        placeholder={t('upload.fullPlaceholder')}
                        rows={7}
                        value={values.full}
                        onChange={(event) => set('full', event.target.value)}
                      />
                    </label>
                    <label className={styles.label} data-wide="true">
                      {t('upload.tagsLabel')}
                      <span className={styles.hintText}>{t('upload.tagsHint')}</span>
                      <input
                        className={styles.field}
                        placeholder={t('upload.tagsPlaceholder')}
                        type="text"
                        value={values.tags}
                        onChange={(event) => set('tags', event.target.value)}
                      />
                    </label>
                  </div>
                </Step>

                <Step
                  number="3"
                  subtitle={t('upload.mediaSubtitle')}
                  title={t('upload.mediaTitle')}
                >
                  <div className={styles.media}>
                    <input
                      ref={iconInput}
                      className={styles.hiddenInput}
                      type="file"
                      aria-label={t('upload.icon')}
                      accept="image/png,image/jpeg"
                      onChange={(event) => pickMedia(event, 'icon')}
                    />
                    <input
                      ref={screenshotInput}
                      className={styles.hiddenInput}
                      type="file"
                      aria-label={t('upload.screenshot')}
                      accept="image/png,image/jpeg"
                      disabled={screenshots.length >= uploadLimits.screenshots}
                      onChange={(event) => pickMedia(event, 'screenshot')}
                    />
                    <div className={styles.iconGroup}>
                      <button
                        className={styles.icon}
                        type="button"
                        aria-label={t('upload.icon')}
                        onClick={() => iconInput.current?.click()}
                      >
                        {icon ? (
                          <img src={icon.url} alt={t('upload.icon')} />
                        ) : (
                          <>
                            <Icon name="plus" size={20} />
                            <span>
                              {t('upload.icon')}
                              <br />
                              256×256
                            </span>
                          </>
                        )}
                      </button>
                      {icon && (
                        <button
                          className={styles.fileChange}
                          type="button"
                          onClick={() => setIcon(null)}
                        >
                          {t("ui.remove")}
                        </button>
                      )}
                    </div>
                    <div className={styles.shots}>
                      <div className={styles.shotGrid}>
                        {Array.from(
                          {
                            length: Math.min(
                              uploadLimits.screenshots,
                              Math.max(3, screenshots.length + 1),
                            ),
                          },
                          (_, index) => {
                            const item = screenshots[index];
                            return item ? (
                              <div className={styles.shotPreview} key={item.id}>
                                <img
                                  src={item.url}
                                  alt={`${t('upload.screenshot')} ${index + 1}`}
                                />
                                <button
                                  className={styles.removeMedia}
                                  type="button"
                                  aria-label={`${t("ui.removeScreenshot")} ${index + 1}`}
                                  onClick={() =>
                                    setScreenshots((items) =>
                                      items.filter((shot) => shot.id !== item.id),
                                    )
                                  }
                                >
                                  <Icon name="close" size={14} />
                                </button>
                              </div>
                            ) : (
                              <button
                                className={styles.shot}
                                type="button"
                                key={`slot-${index}`}
                                aria-label={`${t('upload.screenshot')} ${index + 1}`}
                                onClick={() => screenshotInput.current?.click()}
                              >
                                <Icon name="plus" size={18} />
                                {t('upload.screenshot')}
                              </button>
                            );
                          },
                        )}
                      </div>
                      <span className={styles.hintText}>{t('upload.mediaNote')}</span>
                    </div>
                  </div>

                  <div className={styles.authorRow}>
                    <label className={styles.label}>
                      <span className={styles.labelStack}>
                        <span>{t('upload.authorLabel')}</span>
                        <span className={styles.hintText}>{t('upload.authorHint')}</span>
                      </span>
                      <input
                        className={styles.field}
                        placeholder={t('upload.authorPlaceholder')}
                        type="text"
                        value={values.author ?? ''}
                        onChange={(event) => set('author', event.target.value)}
                      />
                    </label>

                    <label className={styles.label}>
                      <span className={styles.labelStack}>
                        <span>{t('upload.siteLabel')}</span>
                        <span className={styles.hintText}>{t('upload.siteHint')}</span>
                      </span>
                      <input
                        className={styles.field}
                        placeholder={t('upload.sitePlaceholder')}
                        type="url"
                        value={values.site ?? ''}
                        onChange={(event) => set('site', event.target.value)}
                      />
                    </label>
                  </div>
                </Step>

                <Step
                  number="4"
                  subtitle={t('upload.reviewSubtitle')}
                  title={t('upload.reviewTitle')}
                >
                  <div className={styles.checks}>
                    <label className={styles.check}>
                      <input
                        checked={checks.rules}
                        className={styles.checkInput}
                        type="checkbox"
                        onChange={(event) =>
                          setChecks((c) => ({ ...c, rules: event.target.checked }))
                        }
                      />
                      <span className={styles.checkBox} />
                      <span>
                        {t('upload.checkRulesBefore')}{' '}
                        <Link className={styles.warningLink} to={`/${language}/rules`}>
                          {t('upload.checkRulesLink')}
                        </Link>
                        {t('upload.checkRulesAfter')}
                      </span>
                    </label>

                    <label className={styles.check}>
                      <input
                        checked={checks.tested}
                        className={styles.checkInput}
                        type="checkbox"
                        onChange={(event) =>
                          setChecks((c) => ({ ...c, tested: event.target.checked }))
                        }
                      />
                      <span className={styles.checkBox} />
                      <span>{t('upload.checkTested')}</span>
                    </label>
                  </div>

                  <div className={styles.submitRow}>
                    <span className={styles.ready} data-ready={ready ? 'true' : 'false'}>
                      {ready
                        ? t('upload.allSet')
                        : t('upload.missing', { list: missing.join(', ') })}
                    </span>

                    <div className={styles.submitActions}>
                      <button
                        className={styles.draft}
                        type="button"
                        onClick={() => action.run(() => save())}
                      >
                        {t('upload.saveDraft')}
                      </button>
                      <button
                        className={styles.submit}
                        data-ready={ready ? 'true' : 'false'}
                        type="button"
                        disabled={!ready}
                        onClick={() => action.run(() => save(true))}
                      >
                        {t('upload.submit')}
                      </button>
                    </div>
                  </div>
                </Step>
              </div>

              <aside className={styles.aside}>
                <div className={styles.previewPanel}>
                  <span className={styles.panelKicker}>{t('upload.preview')}</span>

                  <article className={styles.card}>
                    <div className={styles.cardHead}>
                      <span className={styles.cardInitial}>
                        {(values.name.trim()[0] ?? '?').toUpperCase()}
                      </span>
                      <span className={styles.cardText}>
                        <span className={styles.cardName}>
                          {values.name.trim() || t('upload.previewName')}
                        </span>
                        <span className={styles.cardMeta}>
                          {{ tank: 'Tank', healer: 'Healer', dps: 'DPS' }[roles[0]] ??
                            t('upload.previewCategory')}{' '}
                          · v{values.version.trim() || '0.0.0'}
                        </span>
                      </span>
                    </div>

                    <p className={styles.cardShort}>
                      {values.short.trim() || t('upload.previewShort')}
                    </p>

                    <div className={styles.cardFoot}>
                      <span className={styles.cardStats}>
                        <span className={styles.cardStars}>
                          {Array.from({ length: 5 }, (_, index) => (
                            <Icon key={index} fill="currentColor" name="star" size={12} />
                          ))}
                        </span>
                        · 0 · {t('upload.by')} {uploader.name}
                      </span>
                      <span className={styles.cardDownload}>{t('upload.download')}</span>
                    </div>
                  </article>
                </div>

                <MarkedLinks
                  boxed={false}
                  items={uploadReminders.map((reminder) => ({ id: reminder.id, label: t(reminder.labelKey) }))}
                  link={{ label: t('upload.fullRules'), to: `/${language}/rules` }}
                  title={t('upload.beforeUploading')}
                />

                <div className={styles.panel}>
                  <span className={styles.panelKicker}>{t('upload.reviewKicker')}</span>
                  <p className={styles.reviewText}>
                    {t('upload.reviewBefore')}{' '}
                    <strong className={styles.reviewTime}>
                      {stats.data?.averageReviewHours ?? '—'} h
                    </strong>
                    {t('upload.reviewAfter')}
                  </p>
                </div>
              </aside>
            </div>
          </fieldset>
        )}
        {params.get('view') === 'submissions' && (
          <section className={styles.submissions}>
            <Link className={styles.fileChange} to={`/${language}/upload`}>
              {t("ui.startANewSubmission")}
            </Link>
            <h2 className={styles.stepTitle}>{t("ui.mySubmissions")}</h2>
            <ApiState {...list} retry={list.reload} empty={list.data?.items.length === 0} />
            {(list.data?.items || []).map((item) => (
              <div className={styles.submissionItem} key={item.id}>
                <Link to={`/${language}/upload?submissionId=${item.id}`}>
                  {item.name || `#${item.id}`}
                </Link>
                <span>
                  {item.status}
                  {item.decision?.reason ? ` · ${item.decision.reason}` : ''}
                </span>
              </div>
            ))}
            <Pagination page={list.data?.page} onChange={setPage} disabled={list.loading} />
          </section>
        )}
      </main>
      <Footer />
    </div>
  );
}
