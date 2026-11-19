import React from 'react';
import { SURFACE_CONDITION_COLORS } from '../../Components/Dental3D/types';

export const SURFACE_OPTIONS: { value: string; label: string }[] = [
  { value: 'healthy', label: 'Healthy' },
  { value: 'active_caries', label: 'Caries' },
  { value: 'composite_filled', label: 'Composite' },
  { value: 'amalgam', label: 'Amalgam' },
  { value: 'sealant', label: 'Sealant' },
];

/** Surface keys as stored: mesial, distal, occlusal, buccal, lingual. */
export const SURFACES = ['mesial', 'occlusal', 'distal', 'buccal', 'lingual'] as const;

interface Props {
  tooth: string;
  surfaces: Record<string, string>;
  brush: string;
  disabled?: boolean;
  onPaint: (surface: string) => void;
}

/**
 * Five-surface tooth diagram (occlusal/incisal in the middle). Mesial faces the midline, so it
 * sits on the right for quadrants 1/4 and on the left for quadrants 2/3, as on a paper chart.
 * Buccal is drawn on the outer side of each arch.
 */
export default function SurfaceDiagram({ tooth, surfaces, brush, disabled, onPaint }: Props) {
  const q = Number(String(tooth)[0]);
  const mesialRight = q === 1 || q === 4 || q === 5 || q === 8;
  const upper = q === 1 || q === 2 || q === 5 || q === 6;
  const anterior = Number(String(tooth).slice(-1)) <= 3;

  const fill = (s: string) => (SURFACE_CONDITION_COLORS as any)[surfaces[s] || 'healthy'] || '#F4F1EA';
  const label: Record<string, string> = {
    mesial: 'M', distal: 'D', occlusal: anterior ? 'I' : 'O', buccal: anterior ? 'F' : 'B', lingual: upper ? 'P' : 'L',
  };

  // Regions: outer ring split in 4 trapezoids + centre square.
  const regions: Record<string, string> = {
    top: 'M4,4 L116,4 L84,36 L36,36 Z',
    bottom: 'M4,116 L116,116 L84,84 L36,84 Z',
    left: 'M4,4 L36,36 L36,84 L4,116 Z',
    right: 'M116,4 L84,36 L84,84 L116,116 Z',
    centre: 'M36,36 L84,36 L84,84 L36,84 Z',
  };
  const place: Record<string, string> = {
    top: upper ? 'buccal' : 'lingual',
    bottom: upper ? 'lingual' : 'buccal',
    left: mesialRight ? 'distal' : 'mesial',
    right: mesialRight ? 'mesial' : 'distal',
    centre: 'occlusal',
  };
  const labelPos: Record<string, [number, number]> = { top: [60, 22], bottom: [60, 104], left: [18, 64], right: [102, 64], centre: [60, 64] };

  return (
    <svg viewBox="0 0 120 120" className="h-36 w-36 select-none" role="group" aria-label={`Surfaces of tooth ${tooth}`}>
      {Object.entries(regions).map(([key, d]) => {
        const surface = place[key];
        return (
          <g key={key}>
            <path
              d={d}
              fill={fill(surface)}
              stroke="currentColor"
              strokeWidth={2}
              className={`text-slate-400 dark:text-slate-500 ${disabled ? '' : 'cursor-pointer hover:opacity-80'}`}
              onClick={() => !disabled && onPaint(surface)}
            >
              <title>{`${surface}: ${(surfaces[surface] || 'healthy').replace('_', ' ')}${disabled ? '' : ` (tap to set ${brush.replace('_', ' ')})`}`}</title>
            </path>
            <text x={labelPos[key][0]} y={labelPos[key][1]} textAnchor="middle" dominantBaseline="middle" className="pointer-events-none fill-slate-700 text-[13px] font-bold">
              {label[surface]}
            </text>
          </g>
        );
      })}
    </svg>
  );
}
