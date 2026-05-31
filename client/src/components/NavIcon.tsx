import iconsJson from '../../../assets/admin/icons/icons.json';

/**
 * Icon entries support two path-shapes:
 *   - bare string: renders with the SVG-level defaults (stroke=currentColor,
 *     fill=none, strokeWidth=1.75).
 *   - object: per-path override for filled accents (play triangle, recording
 *     dot, "?" tittle). Allows the JSON dictionary to be the sole source of
 *     truth for glyphs that previously had to be inlined as TSX.
 */
type IconPath = string | { d: string; fill?: string; stroke?: string; strokeWidth?: number };
type IconDef = { viewBox?: string; paths: IconPath[] };

const icons = iconsJson as unknown as Record<string, IconDef>;

type Props = {
  name?: string;
  className?: string;
};

/*
 * Curriculum content-type glyphs (video, live, quiz, assignment, text, audio,
 * scorm, h5p) live ENTIRELY in `assets/admin/icons/icons.json` — single source
 * of truth across PHP + React. KEEP IN SYNC with:
 *   - includes/template-functions.php  (PHP curriculum row icon helper)
 *   - templates/partials/learn-icons.php (lesson-shell header icons)
 */

/**
 * Drag handle (grip-vertical) — six filled dots in a 2x3 grid. Filled circles
 * keep the glyph readable down to ~14px where the JSON `h.01` stroke pattern
 * collapses into faint specks.
 */
function SvgDragHandle({ className }: { className: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor" aria-hidden>
      <circle cx="9" cy="6" r="1.6" />
      <circle cx="15" cy="6" r="1.6" />
      <circle cx="9" cy="12" r="1.6" />
      <circle cx="15" cy="12" r="1.6" />
      <circle cx="9" cy="18" r="1.6" />
      <circle cx="15" cy="18" r="1.6" />
    </svg>
  );
}

/**
 * Stroke icon from shared `assets/admin/icons/icons.json` (PHP: Sikshya admin icon registry).
 */
export function NavIcon({ name, className = 'h-5 w-5 shrink-0' }: Props) {
  if (!name) {
    return <span className={className} aria-hidden />;
  }
  if (name === 'dragHandle') {
    return <SvgDragHandle className={className} />;
  }
  const def = icons[name];
  if (!def?.paths?.length) {
    return <span className={className} aria-hidden />;
  }
  const vb = def.viewBox || '0 0 24 24';
  return (
    <svg
      className={className}
      viewBox={vb}
      fill="none"
      stroke="currentColor"
      strokeWidth={1.75}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden
    >
      {def.paths.map((p, i) => {
        if (typeof p === 'string') {
          return <path key={i} d={p} />;
        }
        return (
          <path
            key={i}
            d={p.d}
            fill={p.fill ?? 'none'}
            stroke={p.stroke ?? 'currentColor'}
            strokeWidth={p.strokeWidth ?? 1.75}
          />
        );
      })}
    </svg>
  );
}
