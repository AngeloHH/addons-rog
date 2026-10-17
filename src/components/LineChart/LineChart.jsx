/**
 * Addons ROG - Line Chart
 *
 * Build SVG line paths from the supplied data series.
 *
 * series   [{ id, label, values, tone, dashed, area, dots }]
 * tone     'gold' by default, or 'muted'.
 * max      Vertical axis limit; defaults to the highest value.
 * xLabels  Labels distributed along the horizontal axis.
 * format   Formatter for vertical axis values.
 * tip      (value, index) => tooltip text.
 *
 * The 100-by-100 canvas stretches; vector-effect preserves stroke widths.
 */

import { useId } from 'react';
import styles from './LineChart.module.scss';

function points(values, max) {
  const last = Math.max(values.length - 1, 1);

  return values.map((value, index) => ({
    x: (index / last) * 100,
    y: 100 - (value / max) * 100,
  }));
}

function line(values, max) {
  return points(values, max)
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
    .join(' ');
}

function area(values, max) {
  return `${line(values, max)} L 100 100 L 0 100 Z`;
}

export default function LineChart({
  format = (value) => value,
  height = 160,
  max,
  note,
  series = [],
  tip,
  title,
  xLabels = [],
}) {
  const fillId = useId();
  const top = max ?? Math.max(...series.flatMap((item) => item.values), 1);
  const marked = series.find((item) => item.dots);

  return (
    <div className={styles.chart}>
      {(title || series.some((item) => item.label)) && (
        <div className={styles.head}>
          <span className={styles.heading}>
            {title && <span className={styles.title}>{title}</span>}
            {note && <span className={styles.note}>{note}</span>}
          </span>

          <span className={styles.legend}>
            {series
              .filter((item) => item.label)
              .map((item) => (
                <span className={styles.legendItem} key={item.id ?? item.label}>
                  <span
                    className={styles.swatch}
                    data-dashed={item.dashed ? 'true' : 'false'}
                    data-tone={item.tone ?? 'gold'}
                  />
                  {item.label}
                </span>
              ))}
          </span>
        </div>
      )}

      <div className={styles.body}>
        <div className={styles.yAxis}>
          <span>{format(top)}</span>
          <span>{format(Math.round(top / 2))}</span>
          <span>{format(0)}</span>
        </div>

        <div className={styles.plotWrap}>
          <div className={styles.plot} style={{ height: `${height}px` }}>
            <svg className={styles.svg} preserveAspectRatio="none" viewBox="0 0 100 100">
              <defs>
                <linearGradient id={fillId} x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0" stopColor="#e0bb62" stopOpacity=".35" />
                  <stop offset="1" stopColor="#e0bb62" stopOpacity="0" />
                </linearGradient>
              </defs>

              {series.map((item) => (
                <g key={item.id ?? item.label}>
                  {item.area && <path d={area(item.values, top)} fill={`url(#${fillId})`} />}
                  <path
                    className={styles.line}
                    d={line(item.values, top)}
                    data-dashed={item.dashed ? 'true' : 'false'}
                    data-tone={item.tone ?? 'gold'}
                    fill="none"
                    vectorEffect="non-scaling-stroke"
                  />
                </g>
              ))}
            </svg>

            {marked &&
              points(marked.values, top).map((point, index) => (
                <span
                  className={styles.dot}
                  key={index}
                  style={{ left: `${point.x}%`, top: `${point.y}%` }}
                  title={tip?.(marked.values[index], index)}
                />
              ))}
          </div>

          {xLabels.length > 0 && (
            <div className={styles.xAxis}>
              {xLabels.map((label) => (
                <span key={label}>{label}</span>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
