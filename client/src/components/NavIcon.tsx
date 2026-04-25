import iconsJson from '../../../assets/admin/icons/icons.json';

type IconDef = { viewBox?: string; paths: string[] };

const icons = iconsJson as Record<string, IconDef>;

type Props = {
  name?: string;
  className?: string;
};

/**
 * Stroke icon from shared `assets/admin/icons/icons.json` (PHP: {@see \Sikshya\Helpers\Icons}).
 */
export function NavIcon({ name, className = 'h-5 w-5 shrink-0' }: Props) {
  if (!name) {
    return <span className={className} aria-hidden />;
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
