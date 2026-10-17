import { api } from '../../api/client.js';
import { useApi } from '../../hooks/useApi.js';
import { useSession } from '../../context/SessionContext.jsx';
import { ApiState } from '../../components/ApiState/ApiState.jsx';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import Icon from '../../components/Icon/Icon.jsx';
import PageHero from '../../components/PageHero/PageHero.jsx';
import PostForm from '../../components/PostForm/PostForm.jsx';
import SiteHeader from '../../components/SiteHeader/SiteHeader.jsx';
import { contactTopics, contactWays } from '../../config/contact.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './ContactPage.module.scss';

const minMessage = 20;
const empty = '—';

export default function ContactPage() {
  const { language, t } = useI18n();
  const resource = useApi('/contact');
  const { user } = useSession();
  const [attachment, setAttachment] = useState(null);
  const [ticket, setTicket] = useState(null);
  const contactPeople = (resource.data?.recipients || []).map((item) => ({
    ...item, name: item.name ?? t('contact.moderationName'),
  }));
  const topics = contactTopics.map((item) => ({
    ...item, ...t(`contact.topics.${item.id}`),
    hint: item.hint && { ...item.hint, ...t(`contact.topics.${item.id}.hint`) },
  }));
  const contactWeek = resource.data
    ? [
        { id: 'received', value: resource.data.week.received },
        { id: 'answered', value: resource.data.week.answered },
        { id: 'average', value: `${resource.data.week.averageReplyHours ?? '—'} h` },
      ]
    : [];
  const [values, setValues] = useState({});
  const [personId, setPersonId] = useState(null);
  const [topicId, setTopicId] = useState(null);

  const person = contactPeople.find((item) => item.id === personId) || contactPeople[0] || {};
  const topic = topics.find((item) => item.id === topicId);
  const message = values.message ?? '';
  const ready = Boolean(
    (values.name ?? '').trim() &&
    (values.subject ?? '').trim() &&
    message.length >= minMessage &&
    topic &&
    person.id &&
    (user || values.email?.trim()),
  );

  // Resolve navigation targets in the view; configuration supplies only target IDs.
  const targets = {
    requests: `/${language}#requests`,
    install: `/${language}/docs/install`,
    rules: `/${language}/rules`,
    account: `/${language}/login`,
  };

  /**
   * Show no note for an empty message, a prompt for short text, and a count once it is long enough.
   */
  function messageNote(value) {
    if (value.length === 0) return null;

    return value.length < minMessage
      ? { text: t('contact.moreDetail'), tone: 'muted' }
      : { text: t('contact.characters', { count: value.length }), tone: 'ok' };
  }

  return (
    <div className="site-frame">
      <AuthBar />
      <SiteHeader />
      <main className="app-shell">
        <PageHero
          aside={
            <div className={styles.status}>
              <span>{t('contact.statusLabel')}</span>
              <span className={styles.statusPill}>
                <span className={styles.statusDot} />
                {t('contact.queue', { count: resource.data?.queue ?? '—' })}
              </span>
            </div>
          }
          kicker={t('contact.kicker')}
          text={t('contact.intro')}
          title={t('contact.title')}
        />

        <ApiState {...resource} retry={resource.reload} />
        {ticket && (
          <p role="status">
            #{ticket.ticketId} · {ticket.estimatedReplyHours} h
          </p>
        )}
        <section className={styles.mailbox}>
          <div className={styles.steps}>
            <div className={styles.step}>
              <div className={styles.stepHead}>
                <span className={styles.stepLabel}>{t('contact.stepRecipient')}</span>
                <span className={styles.stepLine} />
              </div>

              <div className={styles.people}>
                {contactPeople.map((item) => (
                  <button
                    aria-pressed={item.id === person.id}
                    className={styles.person}
                    data-selected={item.id === person.id ? 'true' : 'false'}
                    data-status={item.status}
                    key={item.id}
                    type="button"
                    onClick={() => setPersonId(item.id)}
                  >
                    <span className={styles.personHead}>
                      <span
                        className={styles.personInitial}
                        style={{
                          '--person-accent': item.color,
                        }}
                      >
                        {Array.from(item.name)[0]?.toUpperCase()}
                      </span>
                      <span className={styles.personState}>
                        <span className={styles.personStateDot} />
                        {t(`contact.states.${item.status}`)}
                      </span>
                    </span>

                    <span className={styles.personName}>
                      <span className={styles.personTitle}>{item.name}</span>
                      <span className={styles.personRole}>{t(`contact.recipientKinds.${item.kind}`)}</span>
                    </span>

                    <span className={styles.personHandles}>
                      {item.description || t(`contact.recipientDescriptions.${item.kind}`)}
                    </span>
                  </button>
                ))}
                {resource.data && !contactPeople.length && (
                  <p className={styles.noRecipients} role="status">{t('contact.noRecipients')}</p>
                )}
              </div>
            </div>

            <div className={styles.step}>
              <div className={styles.stepHead}>
                <span className={styles.stepLabel}>{t('contact.stepReason')}</span>
                <span className={styles.stepLine} />
              </div>

              <div className={styles.topics}>
                {topics.map((item) => (
                  <button
                    aria-pressed={item.id === topicId}
                    className={styles.topic}
                    data-selected={item.id === topicId ? 'true' : 'false'}
                    key={item.id}
                    style={{ '--topic-dot': item.dot }}
                    type="button"
                    onClick={() => setTopicId(item.id)}
                  >
                    <span className={styles.topicDot} />
                    {item.label}
                  </button>
                ))}
              </div>

              {topic?.hint && (
                <div className={styles.hint}>
                  <span className={styles.hintLabel}>{t('contact.hintLabel')}</span>
                  <span>
                    {topic.hint.text}{' '}
                    <Link className={styles.hintLink} to={targets[topic.hint.target]}>
                      {topic.hint.link}
                    </Link>
                  </span>
                </div>
              )}
            </div>

            <div className={styles.step}>
              <div className={styles.stepHead}>
                <span className={styles.stepLabel}>{t('contact.stepLetter')}</span>
                <span className={styles.stepLine} />
              </div>

              <PostForm
                fields={[
                  [
                    {
                      name: 'name',
                      label: t('contact.nameLabel'),
                      placeholder: t('contact.namePlaceholder'),
                    },
                    {
                      name: 'email',
                      kind: 'email',
                      label: t('contact.emailLabel'),
                      placeholder: t('contact.emailPlaceholder'),
                    },
                  ],
                  {
                    name: 'subject',
                    label: t('contact.subjectLabel'),
                    placeholder: topic?.subjectPlaceholder ?? t('contact.subjectPlaceholder'),
                  },
                  {
                    name: 'message',
                    kind: 'textarea',
                    rows: 7,
                    label: t('contact.messageLabel'),
                    placeholder: topic?.messagePlaceholder ?? t('contact.messagePlaceholder'),
                    note: messageNote,
                  },
                ]}
                footer={
                  <label className={styles.attach}>
                    <input
                      type="file"
                      accept="image/png,image/jpeg"
                      aria-label={t('contact.attach')}
                      onChange={(e) => setAttachment(e.target.files?.[0] || null)}
                    />
                    <span className={styles.attachButton}>{t('contact.attach')}</span>
                    <span className={styles.attachName}>{attachment?.name || t('contact.attachHint')}</span>
                  </label>
                }
                submitLabel={t('contact.send')}
                submitReady={ready}
                onChange={setValues}
                onSubmit={async (values) => {
                  const body = new FormData();
                  Object.entries({ ...values, recipientId: person.id, topicId }).forEach(
                    ([key, value]) => {
                      if (value) body.append(key, value);
                    },
                  );
                  if (attachment) body.append('attachment', attachment);
                  const result = await api.post('/contact', body);
                  setTicket(result);
                  resource.reload();
                }}
              />
            </div>
          </div>

          <aside className={styles.aside}>
            <div className={styles.panel}>
              <span className={styles.panelKicker}>{t('contact.envelope')}</span>

              <div className={styles.envelope}>
                <div className={styles.stamp} />

                <dl className={styles.letter}>
                  <dt>{t('contact.to')}</dt>
                  <dd className={styles.letterTo}>{person.name || empty}</dd>
                  <dt>{t('contact.from')}</dt>
                  <dd>{(values.name ?? '').trim() || empty}</dd>
                  <dt>{t('contact.reason')}</dt>
                  <dd>{topic?.label ?? empty}</dd>
                  <dt>{t('contact.subject')}</dt>
                  <dd className={styles.letterSubject}>{(values.subject ?? '').trim() || empty}</dd>
                </dl>

                <div className={styles.eta}>
                  <div className={styles.etaHead}>
                    <span>{t('contact.estimatedReply')}</span>
                    <span className={styles.etaValue}>
                      {person.replyHours ? `${person.replyHours.min}–${person.replyHours.max} h` : empty}
                    </span>
                  </div>

                  <div className={styles.bar}>
                    <div
                      className={styles.barFill}
                      style={{ width: `${person.availabilityPercent ?? 0}%` }}
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className={styles.panel}>
              <span className={styles.panelKicker}>{t('contact.otherWays')}</span>

              {contactWays.map((way) => (
                <Link className={styles.way} key={way.id} to={targets[way.target]}>
                  <span className={styles.wayText}>
                    <span className={styles.wayTitle}>{t(`contact.ways.${way.id}.title`)}</span>
                    <span className={styles.wayNote}>{t(`contact.ways.${way.id}.text`)}</span>
                  </span>
                  <Icon name="chevronRight" size={15} />
                </Link>
              ))}
            </div>

            <div className={styles.panel}>
              <span className={styles.panelKicker}>{t('contact.week')}</span>

              <div className={styles.week}>
                {contactWeek.map((item) => (
                  <div className={styles.weekItem} key={item.id}>
                    <span className={styles.weekValue}>{item.value}</span>
                    <span className={styles.weekLabel}>{t(`contact.${item.id}`)}</span>
                  </div>
                ))}
              </div>
            </div>
          </aside>
        </section>
      </main>
      <Footer />
    </div>
  );
}
