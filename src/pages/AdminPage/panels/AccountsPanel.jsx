import { api, queryPath } from '../../../api/client.js';
import { date, profilePath } from '../../../api/presenters.js';
import { useApi } from '../../../hooks/useApi.js';
import { useSession } from '../../../context/SessionContext.jsx';
import { ApiState } from '../../../components/ApiState/ApiState.jsx';
import ActionForm from './ActionForm.jsx';
import { useState } from 'react';
import Icon from '../../../components/Icon/Icon.jsx';
import Pager from '../../../components/Pager/Pager.jsx';
import Panel from '../../../components/Panel/Panel.jsx';
import StatTiles from '../../../components/StatTiles/StatTiles.jsx';
import {
  accountColor,
  accountRoles,
  accountStatuses,
  threatLevel,
} from '../../../config/accounts.js';
import { adminRoles } from '../../../config/admin.js';
import { useI18n } from '../../../i18n/I18nProvider.jsx';
import shared from '../admin.module.scss';
import Toolbar from './Toolbar.jsx';
import styles from './AccountsPanel.module.scss';

// Number of accounts requested per page.
const size = 10;

/**
 * Display role totals above the account directory and the selected profile beside it.
 */
export default function AccountsPanel({ range = '7d', onChanged }) {
  const { t, language } = useI18n();
  const [query, setQuery] = useState('');
  const [role, setRole] = useState();
  const [status, setStatus] = useState('all');
  const [chosen, setChosen] = useState(null);
  const [page, setPage] = useState(1);

  const session = useSession();

  const [action, setAction] = useState(null);
  const wanted = adminRoles.find((item) => item.id === role)?.role.toLowerCase();
  const resource = useApi(
    queryPath('/admin/accounts', {
      q: query,
      role: wanted,
      status: status === 'all' ? undefined : status.toLowerCase(),
      page,
      pageSize: size,
    }),
  );
  const cards = adminRoles.map((item) => ({
    id: item.id,
    color: item.color,
    value: resource.data?.roleCounts[item.role.toLowerCase()] ?? '—',
    label: t(`admin.roles.${item.id}.label`),
    sub: t(`admin.roles.${item.id}.sub`),
  }));
  const capital = (value) => value[0].toUpperCase() + value.slice(1);
  const shown = (resource.data?.items || []).map((item) => ({
    ...item,
    role: capital(item.role),
    status: capital(item.status),
    colorAccent: item.color,
    colorDark: '#17110b',
    since: new Date(item.createdAt).getFullYear(),
    seen: date(item.lastSeenAt, language),
  }));
  const pages = resource.data?.page.totalPages || 0;
  const current = page;
  const from = (page - 1) * size;
  const visible = shown;
  // Keep the selected account while it remains visible; otherwise choose the first result.
  const account = visible.find((item) => item.id === chosen) ?? visible[0];
  const level = account && threatLevel(account.threat);
  const log = useApi(
    account ? queryPath('/admin/activity', { actorId: account.id, limit: 5, range }) : null,
  );
  const canManage =
    account &&
    account.id !== session.user.id &&
    (session.founder || ['Player', 'Author'].includes(account.role));
  function choose(operation, value) {
    const fields =
      operation === 'invite'
        ? [{ name: 'email', kind: 'email', label: 'Email' }]
        : operation === 'message'
          ? [
              { name: 'subject', label: t("contact.subjectPlaceholder") },
              { name: 'body', kind: 'textarea', label: t("contact.messageLabel") },
            ]
          : [
              { name: 'reason', kind: 'textarea', label: t("contact.reason") },
              ...(value === 'suspended'
                ? [{ name: 'suspendDays', label: t("ui.suspendDays") }]
                : []),
            ];
    setAction({
      title: `${{ invite: t("ui.invite"), message: t("contact.messageLabel"), role: t("ui.changeRole"), status: t("ui.changeStatus") }[operation]} · ${operation === 'invite' ? t("admin.roleNames.Moderator") : account.name}${value ? ` → ${value}` : ''}`,
      operation,
      value,
      account,
      fields,
      values: value === 'suspended' ? { suspendDays: '30' } : {},
    });
  }
  async function submit(values) {
    const { operation, value, account: target } = action;
    if (operation === 'invite')
      await api.post('/admin/invitations', { ...values, role: 'moderator' });
    else if (operation === 'message')
      await api.post(`/admin/accounts/${target.id}/message`, values);
    else
      await api.patch(`/admin/accounts/${target.id}`, {
        ...values,
        [operation]: value,
        ...(values.suspendDays ? { suspendDays: Number(values.suspendDays) } : {}),
      });
    setAction(null);
    resource.reload();
    log.reload();
    onChanged?.();
  }

  return (
    <>
      <ApiState {...resource} retry={resource.reload} />
      <ActionForm action={action} close={() => setAction(null)} onSubmit={submit} />
      <StatTiles
        columns={{ sm: 2, md: 4, lg: 4 }}
        items={cards}
        size="md"
        value={role}
        onSelect={(value) => {
          setRole(value);
          setPage(1);
        }}
      />

      <div className={styles.columns}>
        <Panel padded={false}>
          <Toolbar
            placeholder={t('admin.searchAccounts')}
            query={query}
            onQuery={(value) => {
              setQuery(value);
              setPage(1);
            }}
            action={
              <button
                className={styles.invite}
                type="button"
                disabled={!session.founder}
                onClick={() => choose('invite')}
              >
                <Icon name="plus" size={14} />
                {t('admin.inviteModerator')}
              </button>
            }
            filters={[
              {
                id: 'status',
                label: t('admin.state'),
                value: status,
                onChange: (value) => {
                  setStatus(value);
                  setPage(1);
                },
                options: [
                  { value: 'all', label: t('admin.all') },
                  ...accountStatuses.map((item) => ({
                    value: item.id,
                    label: t(`admin.statuses.${item.id}`),
                  })),
                ],
              },
            ]}
          />

          <div className={styles.head}>
            <span>{t('admin.account')}</span>
            <span>{t('admin.role')}</span>
            <span>{t('admin.uploadsColumn')}</span>
            <span>{t('admin.lastSeen')}</span>
            <span>{t('admin.status')}</span>
          </div>

          <div className={styles.list}>
            {visible.map((item) => (
              <button
                className={styles.account}
                data-chosen={item.id === chosen ? 'true' : undefined}
                key={item.id}
                type="button"
                onClick={() => setChosen(item.id)}
              >
                <span className={styles.who}>
                  <span
                    className={styles.avatar}
                    style={{
                      '--avatar-color': item.colorAccent,
                      '--avatar-background': item.colorDark,
                    }}
                  >
                    {item.name[0].toUpperCase()}
                  </span>

                  <span className={styles.whoText}>
                    <span className={styles.name}>{item.name}</span>
                    <span className={styles.email}>
                      {t('admin.since', { email: item.email, year: item.since })}
                    </span>
                  </span>
                </span>

                <span
                  className={styles.roleTag}
                  style={{ '--role-color': accountColor(accountRoles, item.role) }}
                >
                  {t(`admin.roleNames.${item.role}`)}
                </span>

                <span className={styles.uploads}>{item.uploads}</span>
                <span className={styles.seen}>{item.seen}</span>

                <span
                  className={styles.status}
                  style={{ '--status-color': accountColor(accountStatuses, item.status) }}
                >
                  <span className={styles.statusDot} />
                  {t(`admin.statuses.${item.status}`)}
                </span>
              </button>
            ))}

            {shown.length === 0 && <div className={styles.empty}>{t('admin.noAccounts')}</div>}
          </div>

          <Pager
            nextLabel={t('admin.nextPage')}
            page={current}
            pages={pages}
            previousLabel={t('admin.previousPage')}
            onPage={setPage}
            summary={t('admin.showing', {
              from: visible.length ? from + 1 : 0,
              to: from + visible.length,
              total: resource.data?.page.total ?? 0,
            })}
          />
        </Panel>

        {account && (
          <div className={shared.column}>
            <div className={styles.card}>
              <div className={styles.cardHead}>
                <span
                  className={styles.bigAvatar}
                  style={{
                    '--avatar-color': account.colorAccent,
                    '--avatar-background': account.colorDark,
                  }}
                >
                  {account.name[0].toUpperCase()}
                </span>

                <span className={styles.cardText}>
                  <span className={styles.cardName}>{account.name}</span>
                  <span className={styles.email}>{account.email}</span>
                  <span
                    className={styles.cardRole}
                    style={{ '--role-color': accountColor(accountRoles, account.role) }}
                  >
                    <Icon name="shield" size={11} />
                    {t(`admin.roleNames.${account.role}`)}
                  </span>
                </span>
              </div>

              <div className={styles.figures}>
                <span className={styles.figure}>
                  <span className={styles.figureValue}>{account.uploads}</span>
                  <span className={styles.figureLabel}>{t('admin.uploadsColumn')}</span>
                </span>
                <span className={styles.figure}>
                  <span className={styles.figureValue}>{account.downloads}</span>
                  <span className={styles.figureLabel}>{t('admin.downloads')}</span>
                </span>
                <span className={styles.figure}>
                  <span className={styles.figureValue}>{account.reports}</span>
                  <span className={styles.figureLabel}>{t('admin.reports')}</span>
                </span>
              </div>

              <div className={styles.threat}>
                <div className={styles.threatHead}>
                  <span>{t('admin.threatLevel')}</span>
                  <span className={styles.threatLabel} style={{ color: level.color }}>
                    {t(`admin.threats.${level.id}`)}
                  </span>
                </div>

                <div className={styles.threatBar}>
                  <div style={{ width: `${account.threat}%`, background: level.color }} />
                </div>
              </div>

              <label className={styles.field}>
                {t('admin.role')}
                <select
                  aria-label={t('admin.role')}
                  className={styles.select}
                  value={account.role}
                  disabled={!session.founder || !canManage}
                  onChange={(e) => choose('role', e.target.value.toLowerCase())}
                >
                  {accountRoles
                    .filter((item) => item.id !== 'Author' || account.role === 'Author')
                    .map((item) => (
                      <option key={item.id} value={item.id} disabled={item.id === 'Author'}>
                        {t(`admin.roleNames.${item.id}`)}
                      </option>
                    ))}
                </select>
              </label>

              <div className={styles.buttons}>
                <button
                  className={`${shared.framed} ${styles.button}`}
                  type="button"
                  onClick={() => choose('message')}
                >
                  <Icon name="mail" size={14} />
                  {t('admin.message')}
                </button>

                <button
                  className={`${shared.framed} ${styles.button}`}
                  disabled={!canManage}
                  onClick={() =>
                    choose('status', account.status === 'Active' ? 'suspended' : 'active')
                  }
                  data-tone={account.status === 'Active' ? 'warning' : 'ok'}
                  type="button"
                >
                  <Icon name="close" size={14} />
                  {account.status === 'Active' ? t('admin.suspend') : t('admin.reinstate')}
                </button>
              </div>

              {session.founder && canManage && account.status !== 'Banned' && (
                <button type="button" onClick={() => choose('status', 'banned')}>
                  {t("ui.banAccount")}
                </button>
              )}
              <a
                className={styles.profile}
                href={profilePath(language, account.username || account.name)}
              >
                <Icon name="external" size={12} />
                {t('admin.publicProfile')}
              </a>
            </div>

            <Panel>
              <span className={styles.kicker}>{t('admin.recentActivity')}</span>

              <div className={styles.log}>
                <ApiState {...log} retry={log.reload} />
                {(log.data?.items || [])
                  .map((item) => ({
                    what: `${item.kind} · ${item.target.label}`,
                    when: date(item.createdAt, language),
                  }))
                  .map((entry) => (
                    <div className={styles.logRow} key={entry.what}>
                      <span>{entry.what}</span>
                      <span className={styles.logWhen}>{entry.when}</span>
                    </div>
                  ))}
              </div>
            </Panel>
          </div>
        )}
      </div>
    </>
  );
}
