/**
 * Addons ROG - Chord Diagram
 *
 * Display circular nodes joined by lines whose width represents flow.
 *
 * nodes  [{ id, label, color }] in display order.
 * flows  [{ from, to, value }] using node IDs.
 * tip    (flow) => tooltip text.
 */

import styles from './ChordDiagram.module.scss';

const size = 320;
const middle = size / 2;
const radius = 128;
const gap = 0.06;

function polar(distance, angle) {
  return [middle + distance * Math.cos(angle), middle + distance * Math.sin(angle)];
}

export default function ChordDiagram({ flows = [], nodes = [], tip }) {
  const segment = (Math.PI * 2) / Math.max(nodes.length, 1);
  const centre = (index) => -Math.PI / 2 + index * segment + segment / 2;
  const indexOf = (id) => nodes.findIndex((node) => node.id === id);
  const max = Math.max(...flows.map((flow) => flow.value), 1);

  return (
    <svg className={styles.svg} viewBox={`0 0 ${size} ${size}`}>
      {flows.map((flow) => {
        const from = indexOf(flow.from);
        const to = indexOf(flow.to);

        if (from < 0 || to < 0) return null;

        const [ax, ay] = polar(radius - 6, centre(from));
        const [bx, by] = polar(radius - 6, centre(to));
        const share = flow.value / max;

        return (
          <path
            d={`M${ax.toFixed(1)} ${ay.toFixed(1)} Q${middle} ${middle} ${bx.toFixed(1)} ${by.toFixed(1)}`}
            fill="none"
            key={`${flow.from}-${flow.to}`}
            stroke={nodes[from].color}
            strokeLinecap="round"
            strokeOpacity={(0.25 + share * 0.5).toFixed(2)}
            strokeWidth={(2 + share * 14).toFixed(1)}
          >
            {tip && <title>{tip(flow)}</title>}
          </path>
        );
      })}

      {nodes.map((node, index) => {
        const start = -Math.PI / 2 + index * segment + gap;
        const end = -Math.PI / 2 + (index + 1) * segment - gap;
        const [ax, ay] = polar(radius, start);
        const [bx, by] = polar(radius, end);
        const [lx, ly] = polar(radius + 20, centre(index));

        return (
          <g key={node.id}>
            <path
              d={`M${ax.toFixed(1)} ${ay.toFixed(1)} A${radius} ${radius} 0 0 1 ${bx.toFixed(1)} ${by.toFixed(1)}`}
              fill="none"
              stroke={node.color}
              strokeWidth="10"
            />
            <text
              className={styles.label}
              dominantBaseline="middle"
              fill={node.color}
              textAnchor="middle"
              x={lx.toFixed(1)}
              y={ly.toFixed(1)}
            >
              {node.label}
            </text>
          </g>
        );
      })}
    </svg>
  );
}
