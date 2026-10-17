/**
 * Addons ROG - Radial Heatmap
 *
 * Build a radial heatmap from values between 0 and 1.
 *
 * rings   Nested value arrays, ordered from the inner ring outward.
 * labels  Labels distributed around the outer edge.
 * peak    { ring, slice } identifying the highlighted maximum.
 * center  { value, label } displayed in the central opening.
 * tip     (value, ring, slice) => tooltip text.
 */

import styles from './RadialHeatmap.module.scss';

const size = 320;
const middle = size / 2;
const innerRadius = 46;
const ringThickness = 11;

// =============================================================================
// INTENSITY COLORS
// =============================================================================

// Interpolate between muted, bronze, and gold.

const ramp = [
  [26, 20, 9],
  [142, 101, 32],
  [241, 214, 138],
];

function mix(value) {
  const eased = Math.min(Math.max(value, 0), 1) ** 1.2;
  const span = eased < 0.5 ? [ramp[0], ramp[1], eased * 2] : [ramp[1], ramp[2], (eased - 0.5) * 2];
  const [from, to, ratio] = span;
  const channel = (index) => Math.round(from[index] + (to[index] - from[index]) * ratio);

  return `rgb(${channel(0)}, ${channel(1)}, ${channel(2)})`;
}

function polar(radius, angle) {
  return [middle + radius * Math.cos(angle), middle + radius * Math.sin(angle)];
}

/**
 * Build the annular segment between two radii and two angles.
 */
function arc(inner, outer, start, end) {
  const [ax, ay] = polar(outer, start);
  const [bx, by] = polar(outer, end);
  const [cx, cy] = polar(inner, end);
  const [dx, dy] = polar(inner, start);

  return [
    `M${ax.toFixed(1)} ${ay.toFixed(1)}`,
    `A${outer} ${outer} 0 0 1 ${bx.toFixed(1)} ${by.toFixed(1)}`,
    `L${cx.toFixed(1)} ${cy.toFixed(1)}`,
    `A${inner} ${inner} 0 0 0 ${dx.toFixed(1)} ${dy.toFixed(1)}`,
    'Z',
  ].join(' ');
}

export default function RadialHeatmap({ center, labels = [], peak, rings = [], tip }) {
  const slices = rings[0]?.length ?? 0;
  const angle = (index) => (index / slices) * Math.PI * 2 - Math.PI / 2;
  const labelRadius = innerRadius + rings.length * ringThickness + 18;

  return (
    <div className={styles.wrap}>
      <svg className={styles.svg} viewBox={`0 0 ${size} ${size}`}>
        {rings.map((ring, ringIndex) =>
          ring.map((value, sliceIndex) => {
            const inner = innerRadius + ringIndex * ringThickness;

            return (
              <path
                d={arc(inner, inner + ringThickness - 1, angle(sliceIndex), angle(sliceIndex + 1))}
                fill={mix(value)}
                key={`${ringIndex}-${sliceIndex}`}
                stroke="#0f0c08"
                strokeWidth="1"
              >
                {tip && <title>{tip(value, ringIndex, sliceIndex)}</title>}
              </path>
            );
          }),
        )}

        {peak && (
          <path
            d={arc(
              innerRadius + peak.ring * ringThickness - 1,
              innerRadius + peak.ring * ringThickness + ringThickness,
              angle(peak.slice),
              angle(peak.slice + 1),
            )}
            fill="none"
            stroke="#f1d68a"
            strokeWidth="2"
          />
        )}

        {labels.map((label, index) => {
          const step = slices / labels.length;
          const [x, y] = polar(labelRadius, angle(index * step));

          return (
            <text
              className={styles.label}
              dominantBaseline="middle"
              key={label}
              textAnchor="middle"
              x={x.toFixed(1)}
              y={y.toFixed(1)}
            >
              {label}
            </text>
          );
        })}
      </svg>

      {center && (
        <div className={styles.center}>
          <span className={styles.centerValue}>{center.value}</span>
          <span className={styles.centerLabel}>{center.label}</span>
        </div>
      )}
    </div>
  );
}
