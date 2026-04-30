import iconsJson from '../../../assets/admin/icons/icons.json';

type IconDef = { viewBox?: string; paths: string[] };

const icons = iconsJson as Record<string, IconDef>;

type Props = {
  name?: string;
  className?: string;
};

/**
 * Video lesson glyph matching `sikshya_curriculum_outline_row_type_icon_html()` (filled play in rounded rect).
 */
function SvgCurriculumLessonVideo({ className }: { className: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden
    >
      <rect
        x="4"
        y="5"
        width="14"
        height="14"
        rx="2.5"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path d="M11 10.5v5l3.5-2.5L11 10.5z" fill="currentColor" stroke="none" />
    </svg>
  );
}

/** Live lesson glyph matching learn / single-course outline (monitor + strokes). */
function SvgCurriculumLessonLive({ className }: { className: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden>
      <rect x="3" y="5" width="18" height="16" rx="2" fill="none" stroke="currentColor" strokeWidth={2} />
      <path d="M8 3v4M16 3v4" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" />
      <path d="M7 11h10M7 15h6" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" />
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
  if (name === 'curriculumLessonVideo') {
    return <SvgCurriculumLessonVideo className={className} />;
  }
  if (name === 'curriculumLessonLive') {
    return <SvgCurriculumLessonLive className={className} />;
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
      {def.paths.map((d, i) => (
        <path key={i} d={d} />
      ))}
    </svg>
  );
}
