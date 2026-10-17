/**
 * Addons ROG - Profile Hero
 *
 * Render the character-style profile header from supplied values.
 *
 * kicker      { label, status: { label, color } }
 * meta        [{ id, label, icon, tone }]; the component inserts separators.
 * actions     Content to the right of the profile name.
 * moderation  Optional banner above the header.
 * slots       { left, right } arrays of { id, name, detail, image, rating, color, to }.
 *             Slots without a name remain empty and do not navigate.
 * avatar      { badge, initial, color, spec, bars }
 * stats       [{ id, label, value, delta, deltaColor }]
 * tabs        [{ id, label, count }], controlled by activeTab and onTab.
 */

import { Fragment } from 'react';
import { Link } from 'react-router-dom';
import Icon from '../Icon/Icon.jsx';
import styles from './ProfileHero.module.scss';

function Slot({ align, slot }) {
  // Keep unnamed slots decorative, without navigation or screen-reader output.
  if (!slot.name) {
    return (
      <span aria-hidden="true" className={styles.slot} data-empty="true">
        <span className={styles.slotIcon} />
      </span>
    );
  }

  const icon = (
    <span className={styles.slotIcon} style={{ '--slot-color': slot.color }}>
      {slot.image && <img alt="" className={styles.slotImage} src={slot.image} />}
      {slot.rating && <span className={styles.slotRating}>{slot.rating}</span>}
    </span>
  );

  const text = (
    <span className={styles.slotText}>
      <span className={styles.slotName} style={{ '--slot-color': slot.color }}>
        {slot.name}
      </span>
      <span className={styles.slotDetail}>{slot.detail}</span>
    </span>
  );

  return (
    <Link className={styles.slot} data-align={align} to={slot.to}>
      {align === 'right' ? (
        <>
          {text}
          {icon}
        </>
      ) : (
        <>
          {icon}
          {text}
        </>
      )}
    </Link>
  );
}

function Bar({ bar }) {
  return (
    <div className={styles.bar}>
      <div className={styles.barHead}>
        <span className={styles.barLabel}>
          {bar.icon && <Icon name={bar.icon} size={12} />}
          {bar.label}
        </span>
        <span className={styles.barValue} style={bar.color ? { color: bar.color } : undefined}>
          {bar.value}
        </span>
      </div>

      <div className={styles.barTrack}>
        <div
          className={styles.barFill}
          data-plain={bar.color ? 'true' : 'false'}
          style={{ width: `${bar.percent}%`, ...(bar.color && { '--bar-color': bar.color }) }}
        />
      </div>

      {bar.hint && <span className={styles.barHint}>{bar.hint}</span>}
    </div>
  );
}

export default function ProfileHero({
  actions,
  activeTab,
  avatar,
  background,
  kicker,
  meta = [],
  moderation,
  name,
  slots = {},
  stats = [],
  tabs = [],
  onTab,
}) {
  return (
    <section className={styles.hero}>
      {background && <img alt="" className={styles.background} src={background} />}
      <div className={styles.fade} />
      <div className={styles.glow} />

      <div className={styles.inner}>
        {moderation && <div className={styles.moderation}>{moderation}</div>}

        <div className={styles.identity}>
          <div className={styles.identityText}>
            <div className={styles.kicker}>
              <span className={styles.kickerLine} />
              <span>{kicker?.label}</span>

              {kicker?.status && (
                <>
                  <span className={styles.separator}>·</span>
                  <span className={styles.status} style={{ '--status-color': kicker.status.color }}>
                    <span className={styles.statusDot} />
                    {kicker.status.label}
                  </span>
                </>
              )}
            </div>

            <h1 className={styles.name}>{name}</h1>

            <div className={styles.meta}>
              {meta.map((item, index) => (
                <Fragment key={item.id ?? item.label}>
                  {index > 0 && <span className={styles.separator}>·</span>}
                  <span
                    className={styles.metaItem}
                    style={item.tone ? { '--meta-tone': item.tone } : undefined}
                  >
                    {item.icon && <Icon name={item.icon} size={14} />}
                    {item.label}
                  </span>
                </Fragment>
              ))}
            </div>
          </div>

          {actions && <div className={styles.actions}>{actions}</div>}
        </div>

        <div className={styles.loadout}>
          <div className={styles.slots}>
            {(slots.left ?? []).map((slot) => (
              <Slot align="left" key={slot.id ?? slot.name} slot={slot} />
            ))}
          </div>

          <div className={styles.avatar}>
            {avatar?.badge && <span className={styles.avatarBadge}>{avatar.badge}</span>}

            <div className={styles.rune}>
              <span className={styles.runeOuter} />
              <span className={styles.runeInner} />
              <span className={styles.initial} style={{ '--avatar-color': avatar?.color }}>
                {avatar?.initial}
              </span>
            </div>

            {avatar?.spec && (
              <div className={styles.spec}>
                <span className={styles.specLabel}>{avatar.spec.kicker}</span>
                <span className={styles.specValue} style={{ '--spec-color': avatar.spec.color }}>
                  {avatar.spec.image && (
                    <img alt="" className={styles.specImage} src={avatar.spec.image} />
                  )}
                  {avatar.spec.label}
                </span>
              </div>
            )}

            {(avatar?.bars ?? []).map((bar) => (
              <Bar bar={bar} key={bar.id ?? bar.label} />
            ))}
          </div>

          <div className={styles.slots}>
            {(slots.right ?? []).map((slot) => (
              <Slot align="right" key={slot.id ?? slot.name} slot={slot} />
            ))}
          </div>
        </div>

        {stats.length > 0 && (
          <div className={styles.stats}>
            {stats.map((stat) => (
              <div className={styles.stat} key={stat.id ?? stat.label}>
                <span className={styles.statLabel}>{stat.label}</span>
                <span className={styles.statRow}>
                  <span className={styles.statValue}>{stat.value}</span>
                  {stat.delta && (
                    <span
                      className={styles.statDelta}
                      style={stat.deltaColor ? { '--delta-color': stat.deltaColor } : undefined}
                    >
                      {stat.delta}
                    </span>
                  )}
                </span>
              </div>
            ))}
          </div>
        )}

        {tabs.length > 0 && (
          <div className={styles.tabs}>
            {tabs.map((tab) => (
              <button
                aria-pressed={tab.id === activeTab}
                className={styles.tab}
                data-active={tab.id === activeTab ? 'true' : 'false'}
                key={tab.id}
                type="button"
                onClick={() => onTab?.(tab.id)}
              >
                {tab.label}
                {tab.count != null && <span className={styles.tabCount}>{tab.count}</span>}
              </button>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
