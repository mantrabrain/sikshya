import {
  useCallback,
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type Dispatch,
  type MutableRefObject,
  type SetStateAction,
} from 'react';
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  closestCorners,
  useDroppable,
  useDraggable,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core';
import { NavIcon } from '../../components/NavIcon';
import { WPMediaPickerField } from '../../components/shared/WPMediaPickerField';
import {
  type CertBlock,
  type CertBlockType,
  type CertificatePageFinish,
  type MergeFieldKey,
  MERGE_FIELD_KEYS,
  type PaletteItem as PaletteItemDef,
  PALETTE_ITEMS,
  CERT_PAGE_DECO_ORDER,
  CERT_PAGE_DECO_LABELS,
  CERT_PAGE_DECO_SHOW_FIRST,
  CERTIFICATE_THEME_QUICK_PRESETS,
  CERT_PAGE_SWATCHES,
  CERT_PAGE_PATTERN_ORDER,
  CERT_LAYOUT_VERSION,
  type BlockFrame,
  createBlock,
  getBlockFrame,
  getCertificatePageBackgroundStyle,
  getCertificatePageDecoGradient,
  getCertificatePagePatternLayer,
  getCertificatePagePhysicalSize,
  mergeFieldLabel,
  mergeFieldToken,
  nextDropFrame,
  substituteMergePreview,
  type CertLayoutFile,
} from './certificateLayout';
import { cloneRegaliaSeed, cloneVertexSeed } from './certificateTemplateSeeds';

const FIELD =
  'mt-1.5 w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200/80 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:focus:border-slate-500 dark:focus:ring-slate-600/50';
const LABEL = 'block text-sm font-medium text-slate-700 dark:text-slate-200';
const HINT = 'mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400';
const PANEL_CARD = 'bg-white dark:bg-slate-900';
/** Page / drawer titles */
const PANEL_TITLE = 'text-base font-semibold tracking-tight text-slate-900 dark:text-slate-50';
const PANEL_LEDE = 'mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400';
const PANEL_HEAD_RULE = 'border-b border-slate-200/90 pb-4 dark:border-slate-800';
/** Small caps section labels (inspector + theme) */
const SECTION_LABEL = 'text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
/** Inspector & grouped controls */
const INSPECTOR_CARD =
  'rounded-lg border border-slate-200/90 bg-slate-50/40 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40';
const COLOR_INPUT =
  'mt-1.5 h-10 w-full max-w-[5.5rem] cursor-pointer rounded-md border border-slate-200 bg-white p-0.5 dark:border-slate-600 dark:bg-slate-800';

const CANVAS_ID = 'sikshya-cert-canvas-root';
const CERT_LEFT_TAB_PANEL_ID = 'sikshya-cert-left-tab-panel';

function clampNumber(raw: unknown, min: number, max: number, fallback: number): number {
  const n = typeof raw === 'number' ? raw : Number(raw);
  if (!Number.isFinite(n)) {
    return fallback;
  }
  return Math.min(max, Math.max(min, n));
}

function certTextAlign(v: unknown, fallback: 'left' | 'center' | 'right'): NonNullable<CSSProperties['textAlign']> {
  const s = String(v ?? '').toLowerCase();
  if (s === 'left' || s === 'center' || s === 'right') {
    return s;
  }
  if (s === 'start') {
    return 'left';
  }
  if (s === 'end') {
    return 'right';
  }
  return fallback;
}

function certBlockLayerTitle(block: CertBlock): string {
  const p = block.props;
  switch (block.type) {
    case 'heading':
      return String(p.text || '').trim().slice(0, 56) || 'Heading';
    case 'text':
      return String(p.text || '').trim().slice(0, 56) || 'Text';
    case 'merge_field': {
      const fk = MERGE_FIELD_KEYS.includes(String(p.field) as MergeFieldKey)
        ? (String(p.field) as MergeFieldKey)
        : 'student_name';
      return mergeFieldLabel(fk);
    }
    case 'spacer':
      return 'Spacer';
    case 'divider':
      return 'Divider';
    case 'image':
      return String(p.src || '').trim() ? 'Image' : 'Image (no URL)';
    case 'qr':
      return 'QR';
    default:
      return block.type;
  }
}

function certBlockTypeLabel(type: CertBlockType): string {
  const hit = PALETTE_ITEMS.find((x) => x.type === type);
  return hit?.label ?? type;
}

type Props = {
  layout: CertLayoutFile;
  onLayoutChange: Dispatch<SetStateAction<CertLayoutFile>>;
  orientation: 'landscape' | 'portrait';
  onOrientationChange: (v: 'landscape' | 'portrait') => void;
  pageSize: 'letter' | 'a4' | 'a5';
  onPageSizeChange: (v: 'letter' | 'a4' | 'a5') => void;
  featuredPreview: string;
  onFeaturedPreviewChange: (url: string) => void;
  onFeaturedIdChange: (id: number) => void;
  pageFinish: CertificatePageFinish;
  onPageFinishChange: Dispatch<SetStateAction<CertificatePageFinish>>;
  /** Public template preview URL including `?hash=...` (template-level preview hash). */
  templatePreviewUrl?: string;
};

type LeftTabId = 'templates' | 'elements' | 'layers' | 'media' | 'backgrounds';

function paletteDraggableId(item: PaletteItemDef): string {
  const slug = `${item.type}-${item.label}`.toLowerCase().replace(/[^a-z0-9]+/g, '-');
  return `palette-${slug}`;
}

/** Map palette entries to a NavIcon for compact tile UI. */
function paletteItemIcon(item: PaletteItemDef): string {
  if (item.type === 'image') {
    return 'photoImage';
  }
  if (item.type === 'qr') {
    // No dedicated QR icon in icons.json; use a neutral “preview” glyph.
    return 'iconPreview';
  }
  if (item.type === 'heading') {
    return 'badge';
  }
  if (item.type === 'text') {
    return 'bookOpen';
  }
  if (item.type === 'divider') {
    return 'table';
  }
  if (item.type === 'spacer') {
    return 'dotsVertical';
  }
  if (item.type === 'merge_field') {
    const p = item.preset;
    const fieldKey = p && typeof p === 'object' && p !== null && 'field' in p ? String((p as { field?: string }).field || '') : '';
    const l = (fieldKey || item.label).toLowerCase();
    if (l.includes('student') || l.includes('name')) {
      return 'userCircle';
    }
    if (l.includes('course')) {
      return 'course';
    }
    if (l.includes('instructor') || l.includes('time') || l.includes('date') || l.includes('duration')) {
      return 'schedule';
    }
    if (l.includes('verification') || l.includes('certificate') || l.includes('grade') || l.includes('point')) {
      return 'key';
    }
    return 'tag';
  }
  return 'puzzle';
}

function PaletteItem(props: PaletteItemDef) {
  const { type, label, preset } = props;
  const id = paletteDraggableId(props);
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id,
    data: { source: 'palette', blockType: type, preset },
  });

  const style = transform ? { transform: `translate3d(${transform.x}px,${transform.y}px,0)` } : undefined;
  const icon = paletteItemIcon(props);

  return (
    <div ref={setNodeRef} style={style} className={`${isDragging ? 'opacity-50' : ''} min-w-0`}>
      <button
        type="button"
        title={`${label} — drag to the canvas`}
        className="group flex h-full w-full min-h-[5.25rem] flex-col items-center justify-center gap-2 rounded-lg border border-slate-200/90 bg-white px-2 py-2.5 text-center shadow-sm transition hover:border-slate-300 hover:bg-slate-50/90 hover:shadow dark:border-slate-600 dark:bg-slate-800/60 dark:hover:border-slate-500 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/35 active:cursor-grabbing"
        aria-label={`Drag ${label} to the certificate canvas`}
        {...listeners}
        {...attributes}
      >
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 transition group-hover:bg-slate-200/90 group-hover:text-slate-800 dark:bg-slate-700/80 dark:text-slate-200 dark:group-hover:bg-slate-600/90 dark:group-hover:text-slate-50">
          <NavIcon name={icon} className="h-6 w-6" />
        </span>
        <span className="w-full min-w-0 break-words text-center text-xs font-medium leading-snug text-slate-800 dark:text-slate-100">
          {label}
        </span>
      </button>
    </div>
  );
}

function BlockCanvasPreview({
  block,
  templatePreviewUrl,
}: {
  block: CertBlock;
  templatePreviewUrl: string;
}) {
  let templatePreviewHash = '';
  try {
    const u = new URL(templatePreviewUrl);
    templatePreviewHash = u.searchParams.get('hash') || '';
  } catch {
    templatePreviewHash = '';
  }
  const p = block.props;
  switch (block.type) {
    case 'heading': {
      const Tag = ['h1', 'h2', 'h3'].includes(String(p.tag)) ? (String(p.tag) as 'h1' | 'h2' | 'h3') : 'h1';
      const fam = String(p.fontFamily || 'serif');
      const fontFamily =
        fam === 'mono'
          ? 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace'
          : fam === 'sans'
            ? 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif'
            : 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif';
      return (
        <Tag
          className="box-border max-h-full min-h-0 overflow-hidden"
          style={{
            textAlign: certTextAlign(p.align, 'center'),
            fontSize: Number(p.fontSize) || 28,
            color: (p.color as string) || '#0f172a',
            fontWeight: (p.fontWeight as string) || '700',
            fontFamily,
            lineHeight: (p.lineHeight as number) || 1.12,
            letterSpacing: `${Number(p.letterSpacing) || 0}em`,
            margin: 0,
          }}
        >
          {String(p.text || '')}
        </Tag>
      );
    }
    case 'text':
      {
      const fam = String(p.fontFamily || 'sans');
      const fontFamily =
        fam === 'mono'
          ? 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace'
          : fam === 'serif'
            ? 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif'
            : 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif';
      return (
        <p
          className="box-border max-h-full min-h-0 overflow-hidden"
          style={{
            textAlign: certTextAlign(p.align, 'left'),
            fontSize: Number(p.fontSize) || 14,
            color: (p.color as string) || '#334155',
            margin: 0,
            lineHeight: (p.lineHeight as number) || 1.5,
            fontWeight: (p.fontWeight as string) || '400',
            fontFamily,
            letterSpacing: `${Number(p.letterSpacing) || 0}em`,
            whiteSpace: 'pre-wrap',
          }}
        >
          {String(p.text || '')}
        </p>
      );
      }
    case 'merge_field': {
      const fam = String(p.fontFamily || 'sans');
      const fontFamily =
        fam === 'mono'
          ? 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace'
          : fam === 'serif'
            ? 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif'
            : 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif';
      const field: MergeFieldKey = MERGE_FIELD_KEYS.includes(String(p.field) as MergeFieldKey)
        ? (String(p.field) as MergeFieldKey)
        : 'student_name';
      const token = mergeFieldToken(field);
      const sample =
        field === 'verification_code' && templatePreviewHash
          ? templatePreviewHash
          : substituteMergePreview(token);
      return (
        <div
          className="box-border max-h-full min-h-0 overflow-hidden"
          style={{
            textAlign: certTextAlign(p.align, 'center'),
            fontSize: Number(p.fontSize) || 22,
            color: (p.color as string) || '#0f172a',
            fontWeight: (p.fontWeight as string) || '600',
            fontFamily,
            lineHeight: (p.lineHeight as number) || 1.2,
            letterSpacing: `${Number(p.letterSpacing) || 0}em`,
            margin: 0,
          }}
        >
          {sample}
        </div>
      );
    }
    case 'spacer':
      return <div className="h-full min-h-0 w-full bg-transparent" style={{ minHeight: Number(p.height) || 16 }} aria-hidden />;
    case 'divider': {
      const t = Number(p.thickness) || 2;
      const c = (p.color as string) || '#cbd5e1';
      return <hr style={{ border: 'none', borderTop: `${t}px solid ${c}`, margin: '0.5em 0', width: '100%' }} />;
    }
    case 'image': {
      const src = String(p.src || '').trim();
      const w = Number(p.width) || 120;
      const align = (p.align as string) || 'center';
      const jc = align === 'left' ? 'flex-start' : align === 'right' ? 'flex-end' : 'center';
      if (!src) {
        return (
          <div
            className="flex h-full w-full min-h-0 min-w-0 items-center justify-center border border-dashed border-slate-300 p-1 text-center text-xs text-slate-400 dark:border-slate-600"
            style={{ justifyContent: jc }}
          >
            Image — set URL in settings
          </div>
        );
      }
      return (
        <div className="box-border flex h-full w-full min-h-0 min-w-0 items-center py-0.5" style={{ justifyContent: jc }}>
          <img
            src={src}
            alt=""
            className="max-h-full w-auto max-w-full object-contain"
            style={{ maxWidth: Math.min(w, 2000) }}
          />
        </div>
      );
    }
    case 'qr': {
      const imgSrc = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(templatePreviewUrl)}`;
      return (
        <div className="flex h-full w-full min-h-0 min-w-0 items-center justify-center">
          <img
            src={imgSrc}
            alt=""
            className="h-full w-full rounded-md object-contain"
            style={{ maxWidth: '100%', maxHeight: '100%' }}
          />
        </div>
      );
    }
    default:
      return null;
  }
}

function PositionedCanvasBlock(props: {
  block: CertBlock;
  pageRef: MutableRefObject<HTMLDivElement | null>;
  selected: boolean;
  onSelect: () => void;
  onUpdate: (id: string, patch: Record<string, unknown>) => void;
  onRemove: (id: string) => void;
  templatePreviewUrl: string;
}) {
  const { block, pageRef, selected, onSelect, onUpdate, onRemove, templatePreviewUrl } = props;
  const f = getBlockFrame(block.props, block.type);
  const drag = useRef<{
    kind: 'move' | ResizeHandle;
    startClientX: number;
    startClientY: number;
    start: BlockFrame;
  } | null>(null);

  type ResizeHandle = 'n' | 'e' | 's' | 'w' | 'nw' | 'ne' | 'sw' | 'se';
  const MIN_W = 5;
  const MIN_H = 2;

  const clampFrameToPage = (fr: BlockFrame): BlockFrame => {
    const w = Math.max(MIN_W, Math.min(100, fr.w));
    const h = Math.max(MIN_H, Math.min(100, fr.h));
    const x = Math.max(0, Math.min(100 - w, fr.x));
    const y = Math.max(0, Math.min(100 - h, fr.y));
    return { ...fr, x, y, w, h };
  };

  const frameFromResize = (handle: ResizeHandle, start: BlockFrame, dx: number, dy: number): BlockFrame => {
    let x = start.x;
    let y = start.y;
    let w = start.w;
    let h = start.h;

    // Horizontal
    if (handle === 'e' || handle === 'ne' || handle === 'se') {
      w = start.w + dx;
    }
    if (handle === 'w' || handle === 'nw' || handle === 'sw') {
      x = start.x + dx;
      w = start.w - dx;
    }

    // Vertical
    if (handle === 's' || handle === 'sw' || handle === 'se') {
      h = start.h + dy;
    }
    if (handle === 'n' || handle === 'nw' || handle === 'ne') {
      y = start.y + dy;
      h = start.h - dy;
    }

    // Enforce mins by shifting the anchored edge back
    if (w < MIN_W) {
      const diff = MIN_W - w;
      w = MIN_W;
      if (handle === 'w' || handle === 'nw' || handle === 'sw') {
        x -= diff;
      }
    }
    if (h < MIN_H) {
      const diff = MIN_H - h;
      h = MIN_H;
      if (handle === 'n' || handle === 'nw' || handle === 'ne') {
        y -= diff;
      }
    }

    // Clamp to page bounds; keep opposite edge anchored where possible.
    if (x < 0) {
      if (handle === 'w' || handle === 'nw' || handle === 'sw') {
        w += x; // x is negative
      }
      x = 0;
    }
    if (y < 0) {
      if (handle === 'n' || handle === 'nw' || handle === 'ne') {
        h += y;
      }
      y = 0;
    }
    if (x + w > 100) {
      if (handle === 'e' || handle === 'ne' || handle === 'se') {
        w = 100 - x;
      } else {
        x = 100 - w;
      }
    }
    if (y + h > 100) {
      if (handle === 's' || handle === 'sw' || handle === 'se') {
        h = 100 - y;
      } else {
        y = 100 - h;
      }
    }

    return clampFrameToPage({ ...start, x, y, w, h });
  };

  const startMove = (e: React.PointerEvent<HTMLDivElement>) => {
    e.stopPropagation();
    e.preventDefault();
    onSelect();
    const fr = getBlockFrame(block.props, block.type);
    drag.current = { kind: 'move' as const, startClientX: e.clientX, startClientY: e.clientY, start: { ...fr } };
    const surface = e.currentTarget;
    try {
      surface.setPointerCapture(e.pointerId);
    } catch {
      /* ignore if unsupported */
    }
    const move = (ev: PointerEvent) => {
      const s = drag.current;
      const page = pageRef.current;
      if (!s || s.kind !== 'move' || !page) {
        return;
      }
      const rect = page.getBoundingClientRect();
      if (rect.width < 1 || rect.height < 1) {
        return;
      }
      const dxPct = ((ev.clientX - s.startClientX) / rect.width) * 100;
      const dyPct = ((ev.clientY - s.startClientY) / rect.height) * 100;
      const nx = Math.min(100 - s.start.w, Math.max(0, s.start.x + dxPct));
      const ny = Math.min(100 - s.start.h, Math.max(0, s.start.y + dyPct));
      onUpdate(block.id, { x: nx, y: ny });
    };
    const up = (ev: PointerEvent) => {
      try {
        surface.releasePointerCapture(ev.pointerId);
      } catch {
        /* */
      }
      drag.current = null;
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };

  const startResize = (handleKind: ResizeHandle) => (e: React.PointerEvent<HTMLButtonElement>) => {
    e.stopPropagation();
    e.preventDefault();
    onSelect();
    const fr = getBlockFrame(block.props, block.type);
    drag.current = { kind: handleKind, startClientX: e.clientX, startClientY: e.clientY, start: { ...fr } };
    const handle = e.currentTarget;
    try {
      handle.setPointerCapture(e.pointerId);
    } catch {
      /* ignore */
    }
    const move = (ev: PointerEvent) => {
      const s = drag.current;
      const page = pageRef.current;
      if (!s || s.kind === 'move' || !page) {
        return;
      }
      const rect = page.getBoundingClientRect();
      if (rect.width < 1 || rect.height < 1) {
        return;
      }
      const dxPct = ((ev.clientX - s.startClientX) / rect.width) * 100;
      const dyPct = ((ev.clientY - s.startClientY) / rect.height) * 100;
      const next = frameFromResize(s.kind as ResizeHandle, s.start, dxPct, dyPct);
      onUpdate(block.id, { x: next.x, y: next.y, w: next.w, h: next.h });
    };
    const up = (ev: PointerEvent) => {
      try {
        handle.releasePointerCapture(ev.pointerId);
      } catch {
        /* */
      }
      drag.current = null;
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };

  const handleChrome = 'opacity-0 transition-opacity duration-150 group-hover/canvas-block:opacity-100';
  const blockAria = `${certBlockTypeLabel(block.type)}: ${certBlockLayerTitle(block)}`;

  return (
    <div
      className={`group/canvas-block absolute box-border select-none rounded-sm outline-none ${
        selected
          ? 'z-[2] ring-2 ring-inset ring-blue-500'
          : 'z-[1] ring-2 ring-inset ring-transparent hover:ring-blue-500/85 focus-visible:ring-slate-400/45 dark:hover:ring-blue-400/85 dark:focus-visible:ring-slate-500/40'
      } cursor-grab overflow-hidden bg-transparent shadow-none transition-[box-shadow,ring-color] duration-150 hover:shadow-[0_1px_6px_rgba(15,23,42,0.08)] focus-visible:shadow-[0_1px_6px_rgba(15,23,42,0.08)] active:cursor-grabbing dark:hover:shadow-[0_1px_8px_rgba(0,0,0,0.35)] dark:focus-visible:shadow-[0_1px_8px_rgba(0,0,0,0.35)]`}
      style={{ left: `${f.x}%`, top: `${f.y}%`, width: `${f.w}%`, height: `${f.h}%`, zIndex: f.z + 1 }}
      onPointerDown={startMove}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onSelect();
        }
      }}
      role="button"
      tabIndex={0}
      aria-pressed={selected}
      aria-label={blockAria}
    >
      <div className="relative h-full min-h-0 w-full overflow-hidden rounded-sm bg-transparent">
        <div className="h-full min-h-0 w-full min-w-0 overflow-hidden p-0">
          <BlockCanvasPreview block={block} templatePreviewUrl={templatePreviewUrl} />
        </div>
        <button
          type="button"
          className={`absolute right-0.5 top-0.5 z-[6] flex h-6 w-6 items-center justify-center rounded border border-slate-200/90 bg-white/95 text-sm font-semibold leading-none text-slate-600 shadow-sm transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400/50 dark:border-slate-600 dark:bg-slate-900/95 dark:text-slate-300 dark:hover:bg-red-950/50 dark:hover:text-red-300 ${handleChrome}`}
          tabIndex={-1}
          aria-label="Remove block"
          onPointerDown={(e) => e.stopPropagation()}
          onClick={(e) => {
            e.stopPropagation();
            onRemove(block.id);
          }}
        >
          ×
        </button>
        {/* Resize handles (corners + edges). */}
        {(
          [
            // Corners: larger invisible hit areas.
            { k: 'nw' as const, cls: 'left-0 top-0 -translate-x-1/2 -translate-y-1/2 cursor-nwse-resize', size: 'h-6 w-6' },
            { k: 'ne' as const, cls: 'right-0 top-0 translate-x-1/2 -translate-y-1/2 cursor-nesw-resize', size: 'h-6 w-6' },
            { k: 'sw' as const, cls: 'left-0 bottom-0 -translate-x-1/2 translate-y-1/2 cursor-nesw-resize', size: 'h-6 w-6' },
            { k: 'se' as const, cls: 'right-0 bottom-0 translate-x-1/2 translate-y-1/2 cursor-nwse-resize', size: 'h-6 w-6' },
            // Edges: thin invisible strips along the border.
            { k: 'n' as const, cls: 'left-1/2 top-0 -translate-x-1/2 -translate-y-1/2 cursor-ns-resize', size: 'h-4 w-10' },
            { k: 's' as const, cls: 'left-1/2 bottom-0 -translate-x-1/2 translate-y-1/2 cursor-ns-resize', size: 'h-4 w-10' },
            { k: 'w' as const, cls: 'left-0 top-1/2 -translate-x-1/2 -translate-y-1/2 cursor-ew-resize', size: 'h-10 w-4' },
            { k: 'e' as const, cls: 'right-0 top-1/2 translate-x-1/2 -translate-y-1/2 cursor-ew-resize', size: 'h-10 w-4' },
          ] as const
        ).map((h) => (
          <button
            key={h.k}
            type="button"
            tabIndex={-1}
            aria-label={`Resize (${h.k.toUpperCase()})`}
            title="Drag to resize"
            onPointerDown={startResize(h.k)}
            className={`absolute z-[7] touch-none focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400/50 ${
              selected ? 'opacity-100' : handleChrome
            } ${h.size} ${h.cls} bg-transparent border border-transparent shadow-none`}
          />
        ))}
      </div>
    </div>
  );
}

function CanvasDropArea(props: {
  children: React.ReactNode;
  hasBlocks: boolean;
  orientation: 'landscape' | 'portrait';
  pageSize: 'letter' | 'a4' | 'a5';
  featuredPreview: string;
  pageFinish: CertificatePageFinish;
  onBackgroundClick: () => void;
  pageRef: MutableRefObject<HTMLDivElement | null>;
}) {
  const { children, hasBlocks, orientation, pageSize, featuredPreview, pageFinish, onBackgroundClick, pageRef } = props;
  const { setNodeRef, isOver } = useDroppable({ id: CANVAS_ID });

  const pagePhysical = getCertificatePagePhysicalSize(orientation, pageSize);
  const pageBg = getCertificatePageBackgroundStyle({
    pageColor: pageFinish.pageColor,
    pagePattern: pageFinish.pagePattern,
    pageDeco: pageFinish.pageDeco,
    featuredImageUrl: featuredPreview,
  });
  const hasFeatured = String(featuredPreview || '').trim().length > 0;
  const useDarkScrim = !hasFeatured && (pageFinish.pageDeco === 'night' || pageFinish.pageDeco === 'dusk');

  const setMergedRef = (el: HTMLDivElement | null) => {
    setNodeRef(el);
    pageRef.current = el;
  };

  const viewportRef = useRef<HTMLDivElement | null>(null);
  const [viewportSize, setViewportSize] = useState<{ w: number; h: number }>({ w: 0, h: 0 });

  useLayoutEffect(() => {
    const el = viewportRef.current;
    if (!el) return;
    const update = () => {
      const r = el.getBoundingClientRect();
      setViewportSize({ w: r.width, h: r.height });
    };
    update();
    const ro = new ResizeObserver(() => update());
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  const parseLenPx = (v: string): number => {
    const s = String(v || '').trim().toLowerCase();
    const n = parseFloat(s);
    if (!Number.isFinite(n)) return 0;
    if (s.endsWith('mm')) return (n / 25.4) * 96;
    if (s.endsWith('in')) return n * 96;
    if (s.endsWith('px')) return n;
    return n;
  };

  // Fit the paper sheet into the visible canvas viewport so portrait/size changes feel stable.
  const paperPx = useMemo(() => {
    const w0 = parseLenPx(pagePhysical.width);
    const h0 = parseLenPx(pagePhysical.height);
    return { w0, h0 };
  }, [pagePhysical.width, pagePhysical.height]);

  const fitted = useMemo(() => {
    const pad = 32; // viewport padding inside scroll area
    const vw = Math.max(0, viewportSize.w - pad);
    const vh = Math.max(0, viewportSize.h - pad);
    const w0 = paperPx.w0 || 1000;
    const h0 = paperPx.h0 || 700;
    const ar = w0 / h0;

    // Fill the available workspace (do not cap to physical size),
    // so portrait doesn't look tiny and the canvas stays visually balanced.
    const vwSafe = vw || 900;
    const vhSafe = vh || 600;

    let w = vwSafe;
    let h = w / ar;
    if (h > vhSafe) {
      h = vhSafe;
      w = h * ar;
    }

    w = Math.max(320, w);
    h = Math.max(320, h);
    // One rounded edge + height from exact paper ratio — avoids footer/frame drift vs exported HTML.
    const rw = Math.round(w);
    const rh = Math.max(320, Math.round((rw * paperPx.h0) / paperPx.w0));
    return { w: rw, h: rh };
  }, [paperPx.h0, paperPx.w0, viewportSize.h, viewportSize.w]);

  return (
    <div className="flex h-full min-h-0 w-full min-w-0 flex-1 flex-col text-left">
      <div
        className={`flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden transition-colors ${
          isOver
            ? 'bg-emerald-50/40 ring-1 ring-inset ring-emerald-300/70 dark:bg-emerald-950/15 dark:ring-emerald-700/50'
            : 'bg-slate-100/90 ring-1 ring-inset ring-slate-200/80 dark:bg-slate-950/50 dark:ring-slate-800'
        }`}
        role="region"
        aria-label={`Certificate canvas (${pagePhysical.label}) — click empty area to clear block selection`}
      >
        <div ref={viewportRef} className="min-h-0 w-full min-w-0 flex-1 overflow-y-auto overflow-x-auto">
          <div className="flex w-full min-w-0 justify-center px-2 pb-10 pt-5 sm:px-4 sm:pb-12 sm:pt-6">
            <div
              ref={setMergedRef}
              className="relative box-border shrink-0 overflow-hidden rounded-sm bg-white text-left shadow-[0_16px_48px_rgba(15,23,42,0.08)] ring-1 ring-slate-200/90 dark:shadow-[0_16px_48px_rgba(0,0,0,0.35)] dark:ring-slate-600/70"
              style={{
                width: `${Math.round(fitted.w)}px`,
                height: `${Math.round(fitted.h)}px`,
                backgroundColor: pageBg.backgroundColor,
                backgroundImage: pageBg.backgroundImage,
                backgroundSize: pageBg.backgroundSize,
                backgroundRepeat: pageBg.backgroundRepeat,
                backgroundPosition: pageBg.backgroundPosition,
              }}
              title={pagePhysical.label}
            >
              <div
                className={`pointer-events-none absolute inset-0 ${
                  useDarkScrim ? 'bg-slate-900/25' : 'bg-white/82 dark:bg-slate-950/80'
                }`}
                style={useDarkScrim ? { backdropFilter: 'blur(0.5px)' } : { backdropFilter: 'blur(1px)' }}
                aria-hidden
              />
              <div
                className="absolute inset-0 z-[1] overflow-visible"
                onClick={(e) => {
                  if (e.target === e.currentTarget) {
                    onBackgroundClick();
                  }
                }}
              >
                {children}
                {!hasBlocks ? (
                  <div className="pointer-events-none absolute inset-0 z-0 flex items-center justify-center p-6">
                    <div className="max-w-sm rounded-xl border border-slate-200/90 bg-white/95 px-6 py-8 text-center shadow-sm dark:border-slate-600 dark:bg-slate-900/95">
                      <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <NavIcon name="plusCircle" className="h-7 w-7" />
                      </div>
                      <p className="mt-4 text-sm font-semibold text-slate-800 dark:text-slate-100">Start your certificate</p>
                      <p className="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                        Open <span className="font-medium text-slate-700 dark:text-slate-300">Elements</span> to drag blocks
                        here, <span className="font-medium text-slate-700 dark:text-slate-300">Layers</span> to reorder the
                        stack, <span className="font-medium text-slate-700 dark:text-slate-300">Media</span> for images, and{' '}
                        <span className="font-medium text-slate-700 dark:text-slate-300">Theme</span> for page finish.
                      </p>
                    </div>
                  </div>
                ) : null}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function Inspector(props: {
  selected: CertBlock | null;
  onUpdateBlock: (id: string, patch: Record<string, unknown>) => void;
  onRemoveBlock: (id: string) => void;
}) {
  const { selected, onUpdateBlock, onRemoveBlock } = props;

  if (!selected) {
    return (
      <div
        className={`${INSPECTOR_CARD} border-dashed text-center`}
        role="status"
        aria-live="polite"
      >
        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
          <NavIcon name="pencil" className="h-6 w-6" />
        </div>
        <p className={`mt-3 ${SECTION_LABEL}`}>Nothing selected</p>
        <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
          Click a block on the certificate to edit layout, text, merge fields, or images. Use the{' '}
          <span className="font-medium text-slate-700 dark:text-slate-300">Layers</span> tab to reorder the stack in the
          side panel. Press{' '}
          <kbd className="rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono text-[11px] text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
            Esc
          </kbd>{' '}
          to clear selection (when not typing in a field).
        </p>
      </div>
    );
  }

  const p = selected.props;
  const patch = (o: Record<string, unknown>) => onUpdateBlock(selected.id, o);
  const frame = getBlockFrame(p, selected.type);
  const mergeFieldValue: MergeFieldKey = MERGE_FIELD_KEYS.includes(String(p.field) as MergeFieldKey)
    ? (String(p.field) as MergeFieldKey)
    : 'student_name';
  const inspectorTitle =
    selected.type === 'merge_field'
      ? mergeFieldLabel(mergeFieldValue)
      : selected.type === 'image'
        ? String(p.src || '').trim()
          ? 'Image'
          : 'Image'
        : PALETTE_ITEMS.find((x) => x.type === selected.type)?.label ?? selected.type;

  return (
    <div className="space-y-4">
      <div className={INSPECTOR_CARD}>
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className={SECTION_LABEL}>Selected</p>
            <p className="mt-1.5 truncate text-sm font-semibold text-slate-900 dark:text-white">{inspectorTitle}</p>
          </div>
          <button
            type="button"
            className="shrink-0 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-red-600 shadow-sm transition hover:border-red-200 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400/45 dark:border-slate-600 dark:bg-slate-800 dark:text-red-400 dark:hover:border-red-900/50 dark:hover:bg-red-950/30"
            onClick={() => onRemoveBlock(selected.id)}
          >
            Remove
          </button>
        </div>
      </div>

      <div className={INSPECTOR_CARD}>
        <h3 className={SECTION_LABEL}>Position & size</h3>
        <p className={HINT}>
          Values are % of the page. Drag the block to move, drag the corner handle on the canvas to resize, or edit numbers
          here.
        </p>
        <div className="mt-3 grid grid-cols-2 gap-3">
          <div>
            <label className={LABEL} htmlFor="insp-pos-x">
              X
            </label>
            <input
              id="insp-pos-x"
              type="number"
              step={0.1}
              min={0}
              max={100}
              className={FIELD}
              value={Math.round(frame.x * 10) / 10}
              onChange={(e) => {
                const v = Number(e.target.value);
                if (!Number.isFinite(v)) {
                  return;
                }
                const w = getBlockFrame(p, selected.type).w;
                const nx = Math.min(100 - w, Math.max(0, v));
                patch({ x: nx });
              }}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-pos-y">
              Y
            </label>
            <input
              id="insp-pos-y"
              type="number"
              step={0.1}
              min={0}
              max={100}
              className={FIELD}
              value={Math.round(frame.y * 10) / 10}
              onChange={(e) => {
                const v = Number(e.target.value);
                if (!Number.isFinite(v)) {
                  return;
                }
                const h0 = getBlockFrame(p, selected.type).h;
                const ny = Math.min(100 - h0, Math.max(0, v));
                patch({ y: ny });
              }}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-pos-w">
              Width
            </label>
            <input
              id="insp-pos-w"
              type="number"
              step={0.1}
              min={5}
              max={100}
              className={FIELD}
              value={Math.round(frame.w * 10) / 10}
              onChange={(e) => {
                const v = Number(e.target.value);
                if (!Number.isFinite(v)) {
                  return;
                }
                const nw = Math.min(100, Math.max(5, v));
                const cx = getBlockFrame(p, selected.type).x;
                patch({ w: nw, x: Math.min(cx, 100 - nw) });
              }}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-pos-h">
              Height
            </label>
            <input
              id="insp-pos-h"
              type="number"
              step={0.1}
              min={2}
              max={100}
              className={FIELD}
              value={Math.round(frame.h * 10) / 10}
              onChange={(e) => {
                const v = Number(e.target.value);
                if (!Number.isFinite(v)) {
                  return;
                }
                const nh = Math.min(100, Math.max(2, v));
                const cy = getBlockFrame(p, selected.type).y;
                patch({ h: nh, y: Math.min(cy, 100 - nh) });
              }}
            />
          </div>
          <div className="col-span-2">
            <label className={LABEL} htmlFor="insp-pos-z">
              Stack (z)
            </label>
            <input
              id="insp-pos-z"
              type="number"
              min={0}
              max={200}
              className={FIELD}
              value={frame.z}
              onChange={(e) => patch({ z: Math.min(200, Math.max(0, Math.floor(Number(e.target.value) || 0))) })}
            />
            <p className={HINT}>Higher numbers draw in front of lower ones.</p>
          </div>
        </div>
      </div>

      {selected.type === 'heading' ? (
        <div className={INSPECTOR_CARD}>
          <h3 className={SECTION_LABEL}>Heading</h3>
          <div className="mt-3 space-y-3">
          <div>
            <label className={LABEL} htmlFor="insp-h-text">
              Text
            </label>
            <textarea
              id="insp-h-text"
              className={`${FIELD} min-h-[4rem]`}
              value={String(p.text ?? '')}
              onChange={(e) => patch({ text: e.target.value })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-h-tag">
              Level
            </label>
            <select
              id="insp-h-tag"
              className={FIELD}
              value={String(p.tag ?? 'h1')}
              onChange={(e) => patch({ tag: e.target.value })}
            >
              <option value="h1">Heading 1</option>
              <option value="h2">Heading 2</option>
              <option value="h3">Heading 3</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-h-align">
              Align
            </label>
            <select
              id="insp-h-align"
              className={FIELD}
              value={String(p.align ?? 'center')}
              onChange={(e) => patch({ align: e.target.value })}
            >
              <option value="left">Left</option>
              <option value="center">Center</option>
              <option value="right">Right</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-h-fs">
              Font size (px)
            </label>
            <input
              id="insp-h-fs"
              type="number"
              min={10}
              max={96}
              className={FIELD}
              value={clampNumber(p.fontSize, 10, 96, 28)}
              onChange={(e) => patch({ fontSize: clampNumber(e.target.value, 10, 96, 28) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-h-color">
              Color
            </label>
            <input
              id="insp-h-color"
              type="color"
              className={COLOR_INPUT}
              value={/^#[0-9A-Fa-f]{6}$/.test(String(p.color)) ? String(p.color) : '#0f172a'}
              onChange={(e) => patch({ color: e.target.value })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-h-fw">
              Weight
            </label>
            <select
              id="insp-h-fw"
              className={FIELD}
              value={String(p.fontWeight ?? '700')}
              onChange={(e) => patch({ fontWeight: e.target.value })}
            >
              <option value="400">Normal</option>
              <option value="600">Semibold</option>
              <option value="700">Bold</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-h-ff">
              Font family
            </label>
            <select
              id="insp-h-ff"
              className={FIELD}
              value={String(p.fontFamily ?? 'serif')}
              onChange={(e) => patch({ fontFamily: e.target.value })}
            >
              <option value="serif">Serif (diploma)</option>
              <option value="sans">Sans (modern)</option>
              <option value="mono">Mono (code)</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-h-lh">
              Line height
            </label>
            <input
              id="insp-h-lh"
              type="number"
              step={0.02}
              min={1}
              max={2.4}
              className={FIELD}
              value={clampNumber(p.lineHeight, 1, 2.4, 1.12)}
              onChange={(e) => patch({ lineHeight: clampNumber(e.target.value, 1, 2.4, 1.12) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-h-ls">
              Letter spacing (em)
            </label>
            <input
              id="insp-h-ls"
              type="number"
              step={0.01}
              min={-0.05}
              max={0.5}
              className={FIELD}
              value={clampNumber(p.letterSpacing, -0.05, 0.5, 0)}
              onChange={(e) => patch({ letterSpacing: clampNumber(e.target.value, -0.05, 0.5, 0) })}
            />
          </div>
          </div>
        </div>
      ) : null}

      {selected.type === 'text' ? (
        <div className={INSPECTOR_CARD}>
          <h3 className={SECTION_LABEL}>Paragraph</h3>
          <div className="mt-3 space-y-3">
          <div>
            <label className={LABEL} htmlFor="insp-t-text">
              Text
            </label>
            <textarea
              id="insp-t-text"
              className={`${FIELD} min-h-[6rem]`}
              value={String(p.text ?? '')}
              onChange={(e) => patch({ text: e.target.value })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-t-align">
              Align
            </label>
            <select
              id="insp-t-align"
              className={FIELD}
              value={String(p.align ?? 'left')}
              onChange={(e) => patch({ align: e.target.value })}
            >
              <option value="left">Left</option>
              <option value="center">Center</option>
              <option value="right">Right</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-t-fs">
              Font size (px)
            </label>
            <input
              id="insp-t-fs"
              type="number"
              min={10}
              max={48}
              className={FIELD}
              value={clampNumber(p.fontSize, 10, 48, 14)}
              onChange={(e) => patch({ fontSize: clampNumber(e.target.value, 10, 48, 14) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-t-color">
              Color
            </label>
            <input
              id="insp-t-color"
              type="color"
              className={COLOR_INPUT}
              value={/^#[0-9A-Fa-f]{6}$/.test(String(p.color)) ? String(p.color) : '#334155'}
              onChange={(e) => patch({ color: e.target.value })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-t-fw">
              Weight
            </label>
            <select
              id="insp-t-fw"
              className={FIELD}
              value={String(p.fontWeight ?? '400')}
              onChange={(e) => patch({ fontWeight: e.target.value })}
            >
              <option value="400">Normal</option>
              <option value="500">Medium</option>
              <option value="600">Semibold</option>
              <option value="700">Bold</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-t-ff">
              Font family
            </label>
            <select
              id="insp-t-ff"
              className={FIELD}
              value={String(p.fontFamily ?? 'sans')}
              onChange={(e) => patch({ fontFamily: e.target.value })}
            >
              <option value="sans">Sans (default)</option>
              <option value="serif">Serif</option>
              <option value="mono">Mono</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-t-lh">
              Line height
            </label>
            <input
              id="insp-t-lh"
              type="number"
              step={0.02}
              min={1}
              max={2.4}
              className={FIELD}
              value={clampNumber(p.lineHeight, 1, 2.4, 1.5)}
              onChange={(e) => patch({ lineHeight: clampNumber(e.target.value, 1, 2.4, 1.5) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-t-ls">
              Letter spacing (em)
            </label>
            <input
              id="insp-t-ls"
              type="number"
              step={0.01}
              min={-0.05}
              max={0.5}
              className={FIELD}
              value={clampNumber(p.letterSpacing, -0.05, 0.5, 0)}
              onChange={(e) => patch({ letterSpacing: clampNumber(e.target.value, -0.05, 0.5, 0) })}
            />
          </div>
          </div>
        </div>
      ) : null}

      {selected.type === 'merge_field' ? (
        <div className={INSPECTOR_CARD}>
          <h3 className={SECTION_LABEL}>Merge field</h3>
          <div className="mt-3 space-y-3">
          <div>
            <label className={LABEL} htmlFor="insp-m-field">
              Field
            </label>
            <select
              id="insp-m-field"
              className={FIELD}
              value={mergeFieldValue}
              onChange={(e) => patch({ field: e.target.value as MergeFieldKey })}
            >
              {MERGE_FIELD_KEYS.map((k) => (
                <option key={k} value={k}>
                  {mergeFieldLabel(k)}
                </option>
              ))}
            </select>
            <p className={HINT}>Stored as {mergeFieldToken(mergeFieldValue)} in HTML export.</p>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-m-align">
              Align
            </label>
            <select
              id="insp-m-align"
              className={FIELD}
              value={String(p.align ?? 'center')}
              onChange={(e) => patch({ align: e.target.value })}
            >
              <option value="left">Left</option>
              <option value="center">Center</option>
              <option value="right">Right</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-m-fs">
              Font size (px)
            </label>
            <input
              id="insp-m-fs"
              type="number"
              min={10}
              max={72}
              className={FIELD}
              value={clampNumber(p.fontSize, 10, 72, 22)}
              onChange={(e) => patch({ fontSize: clampNumber(e.target.value, 10, 72, 22) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-m-color">
              Color
            </label>
            <input
              id="insp-m-color"
              type="color"
              className={COLOR_INPUT}
              value={/^#[0-9A-Fa-f]{6}$/.test(String(p.color)) ? String(p.color) : '#0f172a'}
              onChange={(e) => patch({ color: e.target.value })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-m-fw">
              Weight
            </label>
            <select
              id="insp-m-fw"
              className={FIELD}
              value={String(p.fontWeight ?? '600')}
              onChange={(e) => patch({ fontWeight: e.target.value })}
            >
              <option value="400">Normal</option>
              <option value="500">Medium</option>
              <option value="600">Semibold</option>
              <option value="700">Bold</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-m-ff">
              Font family
            </label>
            <select
              id="insp-m-ff"
              className={FIELD}
              value={String(p.fontFamily ?? 'sans')}
              onChange={(e) => patch({ fontFamily: e.target.value })}
            >
              <option value="sans">Sans (default)</option>
              <option value="serif">Serif</option>
              <option value="mono">Mono</option>
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-m-lh">
              Line height
            </label>
            <input
              id="insp-m-lh"
              type="number"
              step={0.02}
              min={1}
              max={2.4}
              className={FIELD}
              value={clampNumber(p.lineHeight, 1, 2.4, 1.2)}
              onChange={(e) => patch({ lineHeight: clampNumber(e.target.value, 1, 2.4, 1.2) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-m-ls">
              Letter spacing (em)
            </label>
            <input
              id="insp-m-ls"
              type="number"
              step={0.01}
              min={-0.05}
              max={0.5}
              className={FIELD}
              value={clampNumber(p.letterSpacing, -0.05, 0.5, 0)}
              onChange={(e) => patch({ letterSpacing: clampNumber(e.target.value, -0.05, 0.5, 0) })}
            />
          </div>
          </div>
        </div>
      ) : null}

      {selected.type === 'spacer' ? (
        <div className={INSPECTOR_CARD}>
          <h3 className={SECTION_LABEL}>Spacer</h3>
          <div className="mt-3">
          <label className={LABEL} htmlFor="insp-s-h">
            Height (px)
          </label>
          <input
            id="insp-s-h"
            type="range"
            min={0}
            max={200}
            className="mt-2 h-2 w-full cursor-pointer appearance-none rounded-full bg-slate-200 accent-slate-700 dark:bg-slate-700 dark:accent-slate-300"
            value={clampNumber(p.height, 0, 200, 24)}
            onChange={(e) => patch({ height: clampNumber(e.target.value, 0, 200, 24) })}
          />
          <p className="mt-2 text-xs font-medium tabular-nums text-slate-600 dark:text-slate-400">
            {clampNumber(p.height, 0, 200, 24)} px
          </p>
          </div>
        </div>
      ) : null}

      {selected.type === 'divider' ? (
        <div className={INSPECTOR_CARD}>
          <h3 className={SECTION_LABEL}>Divider</h3>
          <div className="mt-3 space-y-3">
          <div>
            <label className={LABEL} htmlFor="insp-d-thick">
              Thickness (px)
            </label>
            <input
              id="insp-d-thick"
              type="number"
              min={1}
              max={20}
              className={FIELD}
              value={clampNumber(p.thickness, 1, 20, 2)}
              onChange={(e) => patch({ thickness: clampNumber(e.target.value, 1, 20, 2) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-d-color">
              Color
            </label>
            <input
              id="insp-d-color"
              type="color"
              className={COLOR_INPUT}
              value={/^#[0-9A-Fa-f]{6}$/.test(String(p.color)) ? String(p.color) : '#cbd5e1'}
              onChange={(e) => patch({ color: e.target.value })}
            />
          </div>
          </div>
        </div>
      ) : null}

      {selected.type === 'image' ? (
        <div className={INSPECTOR_CARD}>
          <h3 className={SECTION_LABEL}>Image</h3>
          <div className="mt-3 space-y-3">
          <div>
            <label className={LABEL} htmlFor="insp-i-src">
              Image
            </label>
            <WPMediaPickerField
              id="insp-i-src"
              value={String(p.src || '')}
              onChange={(url) => patch({ src: url })}
              placeholder="Pick or paste image URL"
              imageOnly
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-i-w">
              Width (px)
            </label>
            <input
              id="insp-i-w"
              type="number"
              min={20}
              max={800}
              className={FIELD}
              value={clampNumber(p.width, 20, 800, 120)}
              onChange={(e) => patch({ width: clampNumber(e.target.value, 20, 800, 120) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-i-align">
              Align
            </label>
            <select
              id="insp-i-align"
              className={FIELD}
              value={String(p.align ?? 'center')}
              onChange={(e) => patch({ align: e.target.value })}
            >
              <option value="left">Left</option>
              <option value="center">Center</option>
              <option value="right">Right</option>
            </select>
          </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function LayersPanel(props: {
  blocks: CertBlock[];
  selectedId: string | null;
  onSelect: (id: string) => void;
  onLayoutChange: Dispatch<SetStateAction<CertLayoutFile>>;
  onRemoveBlock: (id: string) => void;
}) {
  const { blocks, selectedId, onSelect, onLayoutChange, onRemoveBlock } = props;

  const orderedFrontFirst = useMemo(
    () =>
      [...blocks].sort((a, b) => {
        const za = getBlockFrame(a.props, a.type).z;
        const zb = getBlockFrame(b.props, b.type).z;
        if (zb !== za) {
          return zb - za;
        }
        return a.id.localeCompare(b.id);
      }),
    [blocks]
  );

  const swapStackNeighbor = useCallback(
    (displayIndex: number, direction: -1 | 1) => {
      onLayoutChange((prev) => {
        const ordered = [...prev.blocks].sort((a, b) => {
          const za = getBlockFrame(a.props, a.type).z;
          const zb = getBlockFrame(b.props, b.type).z;
          if (zb !== za) {
            return zb - za;
          }
          return a.id.localeCompare(b.id);
        });
        const j = displayIndex + direction;
        if (j < 0 || j >= ordered.length) {
          return prev;
        }
        const A = ordered[displayIndex];
        const B = ordered[j];
        const za = getBlockFrame(A.props, A.type).z;
        const zb = getBlockFrame(B.props, B.type).z;
        return {
          ...prev,
          blocks: prev.blocks.map((b) => {
            if (b.id === A.id) {
              return { ...b, props: { ...b.props, z: zb } };
            }
            if (b.id === B.id) {
              return { ...b, props: { ...b.props, z: za } };
            }
            return b;
          }),
        };
      });
    },
    [onLayoutChange]
  );

  if (!blocks.length) {
    return (
      <div className={`${INSPECTOR_CARD} border-dashed text-center`} role="status">
        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
          <NavIcon name="layers" className="h-6 w-6" />
        </div>
        <p className="mt-3 text-sm font-medium text-slate-800 dark:text-slate-100">No blocks on the sheet</p>
        <p className="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
          Use <span className="font-medium text-slate-700 dark:text-slate-300">Templates</span> or{' '}
          <span className="font-medium text-slate-700 dark:text-slate-300">Elements</span>, then return here to reorder.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-2">
      <p className={`${SECTION_LABEL} px-0.5`}>Front → back</p>
      <ul className="space-y-1.5" aria-label="Certificate block layers">
        {orderedFrontFirst.map((block, displayIndex) => {
          const active = selectedId === block.id;
          const z = getBlockFrame(block.props, block.type).z;
          return (
            <li key={block.id}>
              <div
                className={`flex items-stretch gap-1 rounded-lg border shadow-sm transition ${
                  active
                    ? 'border-blue-500/80 bg-blue-50/90 ring-1 ring-blue-500/25 dark:border-blue-500/60 dark:bg-blue-950/35 dark:ring-blue-400/20'
                    : 'border-slate-200/90 bg-white dark:border-slate-600 dark:bg-slate-800/60'
                }`}
              >
                <button
                  type="button"
                  className="min-w-0 flex-1 px-2.5 py-2 text-left transition hover:bg-slate-50/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-400/30 dark:hover:bg-slate-800/80"
                  onClick={() => onSelect(block.id)}
                >
                  <span className="block truncate text-xs font-semibold text-slate-900 dark:text-slate-100">
                    {certBlockLayerTitle(block)}
                  </span>
                  <span className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                    <span>{certBlockTypeLabel(block.type)}</span>
                    <span className="tabular-nums">z {z}</span>
                  </span>
                </button>
                <div className="flex shrink-0 flex-col border-l border-slate-200/80 dark:border-slate-600">
                  <button
                    type="button"
                    className="flex min-h-[2.25rem] flex-1 items-center justify-center px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-400/35 disabled:cursor-not-allowed disabled:opacity-35 dark:text-slate-300 dark:hover:bg-slate-700/80"
                    disabled={displayIndex === 0}
                    title="Bring forward (draw on top)"
                    aria-label={`Bring ${certBlockLayerTitle(block)} forward`}
                    onClick={(e) => {
                      e.stopPropagation();
                      swapStackNeighbor(displayIndex, -1);
                    }}
                  >
                    ↑
                  </button>
                  <button
                    type="button"
                    className="flex min-h-[2.25rem] flex-1 items-center justify-center border-t border-slate-200/80 px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-400/35 disabled:cursor-not-allowed disabled:opacity-35 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700/80"
                    disabled={displayIndex === orderedFrontFirst.length - 1}
                    title="Send backward"
                    aria-label={`Send ${certBlockLayerTitle(block)} backward`}
                    onClick={(e) => {
                      e.stopPropagation();
                      swapStackNeighbor(displayIndex, 1);
                    }}
                  >
                    ↓
                  </button>
                </div>
                <button
                  type="button"
                  className="shrink-0 border-l border-slate-200/80 px-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-400/45 dark:border-slate-600 dark:text-red-400 dark:hover:bg-red-950/40"
                  title="Remove block"
                  aria-label={`Remove ${certBlockLayerTitle(block)}`}
                  onClick={(e) => {
                    e.stopPropagation();
                    onRemoveBlock(block.id);
                  }}
                >
                  ×
                </button>
              </div>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

export function CertificateVisualBuilder(props: Props) {
  const {
    layout,
    onLayoutChange,
    orientation,
    onOrientationChange,
    pageSize,
    onPageSizeChange,
    featuredPreview,
    onFeaturedPreviewChange,
    onFeaturedIdChange,
    pageFinish,
    onPageFinishChange,
    templatePreviewUrl: templatePreviewUrlProp,
  } = props;

  const templatePreviewUrl =
    (templatePreviewUrlProp && String(templatePreviewUrlProp)) ||
    (typeof window !== 'undefined' && window.location?.origin ? `${window.location.origin}/` : 'https://example.com/');

  const blocks = layout.blocks;
  const [showAllPatterns, setShowAllPatterns] = useState(false);
  const [showAllDecos, setShowAllDecos] = useState(false);

  const patchPageFinish = useCallback(
    (patch: Partial<CertificatePageFinish>) => {
      onPageFinishChange((prev) => ({ ...prev, ...patch }));
    },
    [onPageFinishChange]
  );

  const applyDecoPreset = useCallback(
    (decoId: string) => {
      patchPageFinish({ pageDeco: decoId });
      onFeaturedIdChange(0);
      onFeaturedPreviewChange('');
    },
    [onFeaturedIdChange, onFeaturedPreviewChange, patchPageFinish]
  );

  const applyThemeQuickPreset = useCallback(
    (preset: (typeof CERTIFICATE_THEME_QUICK_PRESETS)[number]) => {
      patchPageFinish({
        pageColor: preset.finish.pageColor,
        pagePattern: preset.finish.pagePattern,
        pageDeco: preset.finish.pageDeco,
      });
      if (preset.clearFeaturedImage) {
        onFeaturedIdChange(0);
        onFeaturedPreviewChange('');
      }
    },
    [onFeaturedIdChange, onFeaturedPreviewChange, patchPageFinish]
  );
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [activeDrag, setActiveDrag] = useState<{
    source: 'palette';
    type: CertBlockType;
    preset?: Record<string, unknown>;
  } | null>(null);
  const pageRef = useRef<HTMLDivElement | null>(null);

  const blocksSorted = useMemo(
    () => [...layout.blocks].sort((a, b) => getBlockFrame(a.props, a.type).z - getBlockFrame(b.props, b.type).z),
    [layout.blocks]
  );

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') {
        return;
      }
      const t = e.target as HTMLElement | null;
      if (t?.closest?.('input, textarea, select, [contenteditable="true"]')) {
        return;
      }
      setSelectedId(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const onDragStart = useCallback((e: DragStartEvent) => {
    if (e.active.data.current?.source === 'palette') {
      setActiveDrag({
        source: 'palette',
        type: e.active.data.current?.blockType as CertBlockType,
        preset: (e.active.data.current?.preset as Record<string, unknown> | undefined) ?? undefined,
      });
    } else {
      setActiveDrag(null);
    }
  }, []);

  const onDragEnd = useCallback(
    (e: DragEndEvent) => {
      setActiveDrag(null);
      const { active, over } = e;
      if (!over || active.data.current?.source !== 'palette') {
        return;
      }
      const blockType = active.data.current?.blockType as CertBlockType;
      if (!blockType) {
        return;
      }
      if (over.id !== CANVAS_ID) {
        return;
      }
      const preset = (active.data.current?.preset as Record<string, unknown> | undefined) ?? undefined;
      onLayoutChange((prev) => {
        const f = nextDropFrame(prev.blocks.length);
        const nb = createBlock(blockType, { ...preset, x: f.x, y: f.y, w: f.w, h: f.h, z: f.z });
        setSelectedId(nb.id);
        return {
          ...prev,
          version: Math.max(CERT_LAYOUT_VERSION, prev.version || 1),
          blocks: [...prev.blocks, nb],
        };
      });
    },
    [onLayoutChange]
  );

  const updateBlock = useCallback(
    (id: string, patch: Record<string, unknown>) => {
      onLayoutChange((prev) => ({
        ...prev,
        blocks: prev.blocks.map((b) => (b.id === id ? { ...b, props: { ...b.props, ...patch } } : b)),
      }));
    },
    [onLayoutChange]
  );

  const removeBlock = useCallback(
    (id: string) => {
      onLayoutChange((prev) => ({ ...prev, blocks: prev.blocks.filter((b) => b.id !== id) }));
      setSelectedId((sid) => (sid === id ? null : sid));
    },
    [onLayoutChange]
  );

  const addBlockQuick = useCallback(
    (item: PaletteItemDef) => {
      onLayoutChange((prev) => {
        const f = nextDropFrame(prev.blocks.length);
        const nb = createBlock(item.type, { ...item.preset, x: f.x, y: f.y, w: f.w, h: f.h, z: f.z });
        setSelectedId(nb.id);
        return {
          ...prev,
          version: Math.max(CERT_LAYOUT_VERSION, prev.version || 1),
          blocks: [...prev.blocks, nb],
        };
      });
    },
    [onLayoutChange]
  );

  const selected = blocks.find((b) => b.id === selectedId) ?? null;

  const [leftTab, setLeftTab] = useState<LeftTabId>('elements');
  const [mediaPickUrl, setMediaPickUrl] = useState('');
  const [templateQuery, setTemplateQuery] = useState('');

  const templates = useMemo(() => {
    type Tpl = {
      id: string;
      name: string;
      description: string;
      /** Small swatch rendered in the card thumbnail to hint at the theme. */
      accent: { bg: string; ink: string; line: string };
      build: () => CertLayoutFile;
      page?: Partial<Pick<Props, 'orientation' | 'featuredPreview' | 'pageSize'>>;
      finish?: CertificatePageFinish;
    };

    // Colour tokens for consistent ink pairings across templates.
    const INK = {
      slate900: '#0f172a',
      slate700: '#334155',
      slate500: '#64748b',
      slate300: '#cbd5e1',
      slate200: '#e2e8f0',
      gold: '#b08a3a',
      goldSoft: '#d4b56a',
      navy: '#0b2447',
      navySoft: '#1f3b73',
      sea: '#0e7490',
      emerald: '#065f46',
    } as const;

    // ---------- 1–2. Seeded defaults (mirror PHP Installer / Pro migration) ----------
    const regaliaHeritage = (): CertLayoutFile => cloneRegaliaSeed();
    const vertexModern = (): CertLayoutFile => cloneVertexSeed();

    // ---------- 3. Corporate Navy (landscape, corporate letter deco) ----------
    const corporateNavy = (): CertLayoutFile => {
      const eyebrow = createBlock('text');
      const heading = createBlock('heading');
      const divider = createBlock('divider');
      const intro = createBlock('text');
      const student = createBlock('merge_field');
      const body = createBlock('text');
      const course = createBlock('merge_field');
      const sigInstructorLabel = createBlock('text');
      const sigInstructor = createBlock('merge_field');
      const sigDateLabel = createBlock('text');
      const sigDate = createBlock('merge_field');
      const qr = createBlock('qr');
      const verifyLabel = createBlock('text');
      const verifyCode = createBlock('merge_field');
      return {
        version: CERT_LAYOUT_VERSION,
        blocks: [
          { ...eyebrow, props: { ...eyebrow.props, x: 10, y: 10, w: 80, h: 4, z: 1, text: 'PROFESSIONAL TRAINING', align: 'center', fontSize: 11, color: INK.navySoft } },
          { ...heading, props: { ...heading.props, x: 8, y: 15, w: 84, h: 12, z: 2, text: 'Certificate of Completion', tag: 'h1', align: 'center', fontSize: 36, fontWeight: '700', color: INK.navy } },
          { ...divider, props: { ...divider.props, x: 38, y: 28, w: 24, h: 2, z: 1, color: INK.navy, thickness: 2 } },
          { ...intro, props: { ...intro.props, x: 10, y: 34, w: 80, h: 5, z: 1, text: 'This is to certify that', align: 'center', fontSize: 13, color: INK.slate500 } },
          { ...student, props: { ...student.props, x: 8, y: 40, w: 84, h: 11, z: 2, field: 'student_name', align: 'center', fontSize: 32, color: INK.slate900 } },
          { ...body, props: { ...body.props, x: 12, y: 54, w: 76, h: 6, z: 1, text: 'has successfully completed the corporate training program', align: 'center', fontSize: 13, color: INK.slate500 } },
          { ...course, props: { ...course.props, x: 10, y: 62, w: 80, h: 8, z: 2, field: 'course_name', align: 'center', fontSize: 20, color: INK.navy } },
          { ...qr, props: { ...qr.props, x: 12, y: 80, w: 12, h: 16, z: 3, size: 110 } },
          { ...verifyLabel, props: { ...verifyLabel.props, x: 26, y: 82, w: 30, h: 3.5, z: 2, text: 'Verification ID', align: 'left', fontSize: 10, color: INK.slate500, fontWeight: '600' } },
          {
            ...verifyCode,
            props: { ...verifyCode.props, x: 26, y: 86, w: 30, h: 6, z: 2, field: 'verification_code', align: 'left', fontSize: 12, color: INK.slate900, fontWeight: '500' },
          },
          { ...sigInstructorLabel, props: { ...sigInstructorLabel.props, x: 58, y: 82, w: 16, h: 3.5, z: 1, text: 'INSTRUCTOR', align: 'center', fontSize: 10, color: INK.slate500 } },
          { ...sigInstructor, props: { ...sigInstructor.props, x: 58, y: 86, w: 16, h: 6, z: 2, field: 'instructor_name', align: 'center', fontSize: 14, color: INK.slate900 } },
          { ...sigDateLabel, props: { ...sigDateLabel.props, x: 76, y: 82, w: 16, h: 3.5, z: 1, text: 'DATE', align: 'center', fontSize: 10, color: INK.slate500 } },
          { ...sigDate, props: { ...sigDate.props, x: 76, y: 86, w: 16, h: 6, z: 2, field: 'completion_date', align: 'center', fontSize: 14, color: INK.slate900 } },
        ],
      };
    };

    // ---------- 4. Academic Diploma (landscape, cream + paper folio) ----------
    const academicDiploma = (): CertLayoutFile => {
      const topRule = createBlock('divider');
      const eyebrow = createBlock('text');
      const heading = createBlock('heading');
      const presented = createBlock('text');
      const student = createBlock('merge_field');
      const forLine = createBlock('text');
      const course = createBlock('merge_field');
      const gradeLine = createBlock('text');
      const gradeValue = createBlock('merge_field');
      const bottomRule = createBlock('divider');
      const signature = createBlock('merge_field');
      const sigLabel = createBlock('text');
      const date = createBlock('merge_field');
      const dateLabel = createBlock('text');
      return {
        version: CERT_LAYOUT_VERSION,
        blocks: [
          { ...topRule, props: { ...topRule.props, x: 10, y: 9, w: 80, h: 2, z: 1, color: INK.gold, thickness: 2 } },
          { ...eyebrow, props: { ...eyebrow.props, x: 10, y: 13, w: 80, h: 4, z: 1, text: 'DIPLOMA', align: 'center', fontSize: 12, color: INK.gold } },
          { ...heading, props: { ...heading.props, x: 8, y: 18, w: 84, h: 12, z: 2, text: 'Diploma of Completion', tag: 'h1', align: 'center', fontSize: 38, fontWeight: '700', color: INK.slate900 } },
          { ...presented, props: { ...presented.props, x: 12, y: 33, w: 76, h: 5, z: 1, text: 'This diploma is hereby conferred upon', align: 'center', fontSize: 13, color: INK.slate500 } },
          { ...student, props: { ...student.props, x: 8, y: 39, w: 84, h: 11, z: 2, field: 'student_name', align: 'center', fontSize: 34, color: INK.slate900 } },
          { ...forLine, props: { ...forLine.props, x: 12, y: 53, w: 76, h: 5, z: 1, text: 'in recognition of the successful completion of', align: 'center', fontSize: 13, color: INK.slate500 } },
          { ...course, props: { ...course.props, x: 10, y: 59, w: 80, h: 7, z: 2, field: 'course_name', align: 'center', fontSize: 20, color: INK.slate700 } },
          { ...gradeLine, props: { ...gradeLine.props, x: 30, y: 69, w: 16, h: 5, z: 1, text: 'Final grade:', align: 'right', fontSize: 13, color: INK.slate500 } },
          { ...gradeValue, props: { ...gradeValue.props, x: 48, y: 69, w: 22, h: 5, z: 2, field: 'grade', align: 'left', fontSize: 14, color: INK.slate900 } },
          { ...bottomRule, props: { ...bottomRule.props, x: 10, y: 80, w: 80, h: 2, z: 1, color: INK.slate200, thickness: 1 } },
          { ...sigLabel, props: { ...sigLabel.props, x: 10, y: 83, w: 34, h: 4, z: 1, text: 'INSTRUCTOR', align: 'center', fontSize: 10, color: INK.slate500 } },
          { ...signature, props: { ...signature.props, x: 10, y: 87, w: 34, h: 6, z: 2, field: 'instructor_name', align: 'center', fontSize: 14, color: INK.slate900 } },
          { ...dateLabel, props: { ...dateLabel.props, x: 56, y: 83, w: 34, h: 4, z: 1, text: 'DATE', align: 'center', fontSize: 10, color: INK.slate500 } },
          { ...date, props: { ...date.props, x: 56, y: 87, w: 34, h: 6, z: 2, field: 'completion_date', align: 'center', fontSize: 14, color: INK.slate900 } },
        ],
      };
    };

    // ---------- 5. Portrait Elegant (A4 portrait, formal navy band) ----------
    const portraitElegant = (): CertLayoutFile => {
      const eyebrow = createBlock('text');
      const heading = createBlock('heading');
      const divider = createBlock('divider');
      const presented = createBlock('text');
      const student = createBlock('merge_field');
      const forLine = createBlock('text');
      const course = createBlock('merge_field');
      const body = createBlock('text');
      const bottomRule = createBlock('divider');
      const sigLabel = createBlock('text');
      const instructor = createBlock('merge_field');
      const dateLabel = createBlock('text');
      const date = createBlock('merge_field');
      const qr = createBlock('qr');
      const verifyLabel = createBlock('text');
      const verifyCode = createBlock('merge_field');
      return {
        version: CERT_LAYOUT_VERSION,
        blocks: [
          { ...eyebrow, props: { ...eyebrow.props, x: 10, y: 10, w: 80, h: 3, z: 1, text: 'CERTIFICATE OF ACHIEVEMENT', align: 'center', fontSize: 11, color: INK.navy } },
          { ...heading, props: { ...heading.props, x: 8, y: 14, w: 84, h: 7, z: 2, text: 'Certificate', tag: 'h1', align: 'center', fontSize: 34, fontWeight: '700', color: INK.slate900 } },
          { ...divider, props: { ...divider.props, x: 40, y: 22, w: 20, h: 2, z: 1, color: INK.navy, thickness: 2 } },
          { ...presented, props: { ...presented.props, x: 10, y: 28, w: 80, h: 3, z: 1, text: 'is proudly awarded to', align: 'center', fontSize: 12, color: INK.slate500 } },
          { ...student, props: { ...student.props, x: 8, y: 33, w: 84, h: 7, z: 2, field: 'student_name', align: 'center', fontSize: 28, color: INK.slate900 } },
          { ...forLine, props: { ...forLine.props, x: 12, y: 42, w: 76, h: 3, z: 1, text: 'for the successful completion of', align: 'center', fontSize: 12, color: INK.slate500 } },
          { ...course, props: { ...course.props, x: 10, y: 47, w: 80, h: 6, z: 2, field: 'course_name', align: 'center', fontSize: 18, color: INK.navy } },
          { ...body, props: { ...body.props, x: 12, y: 56, w: 76, h: 5, z: 1, text: 'Awarded in recognition of dedication, effort, and academic excellence.', align: 'center', fontSize: 12, color: INK.slate500 } },
          { ...bottomRule, props: { ...bottomRule.props, x: 16, y: 78, w: 68, h: 2, z: 1, color: INK.slate200, thickness: 1 } },
          { ...qr, props: { ...qr.props, x: 10, y: 79.5, w: 20, h: 12, z: 3, size: 120 } },
          { ...verifyLabel, props: { ...verifyLabel.props, x: 32, y: 80.5, w: 58, h: 3, z: 2, text: 'Verification ID', align: 'left', fontSize: 10, color: INK.slate500, fontWeight: '600' } },
          {
            ...verifyCode,
            props: { ...verifyCode.props, x: 32, y: 84, w: 58, h: 4, z: 2, field: 'verification_code', align: 'left', fontSize: 12, color: INK.slate900, fontWeight: '500' },
          },
          { ...sigLabel, props: { ...sigLabel.props, x: 10, y: 92.5, w: 35, h: 3, z: 1, text: 'INSTRUCTOR', align: 'center', fontSize: 9, color: INK.slate500 } },
          { ...instructor, props: { ...instructor.props, x: 10, y: 95.5, w: 35, h: 4, z: 2, field: 'instructor_name', align: 'center', fontSize: 12, color: INK.slate900 } },
          { ...dateLabel, props: { ...dateLabel.props, x: 55, y: 92.5, w: 35, h: 3, z: 1, text: 'DATE', align: 'center', fontSize: 9, color: INK.slate500 } },
          { ...date, props: { ...date.props, x: 55, y: 95.5, w: 35, h: 4, z: 2, field: 'completion_date', align: 'center', fontSize: 12, color: INK.slate900 } },
        ],
      };
    };

    const list: Tpl[] = [
      {
        id: 'regalia-heritage',
        name: 'Regalia · Heritage',
        description: 'Warm ivory, gold deco, serif honor line · seeded default',
        accent: { bg: '#fffbf0', ink: '#422006', line: '#d97706' },
        build: regaliaHeritage,
        page: { orientation: 'landscape', pageSize: 'a4' },
        finish: { pageColor: '#fffbf0', pagePattern: 'paperGrain', pageDeco: 'diplomaGold' },
      },
      {
        id: 'vertex-modern',
        name: 'Vertex · Modern',
        description: 'Teal accent strip, airy sans layout · seeded default',
        accent: { bg: '#ffffff', ink: '#0f172a', line: '#0d9488' },
        build: vertexModern,
        page: { orientation: 'landscape', pageSize: 'a4' },
        finish: { pageColor: '#ffffff', pagePattern: 'microDots', pageDeco: 'minimalFrame' },
      },
      {
        id: 'corporate-navy',
        name: 'Corporate Navy',
        description: 'Professional · landscape',
        accent: { bg: '#f5f8fd', ink: INK.navy, line: INK.navy },
        build: corporateNavy,
        page: { orientation: 'landscape', pageSize: 'a4' },
        finish: { pageColor: '#ffffff', pagePattern: 'lines', pageDeco: 'corporateLetter' },
      },
      {
        id: 'academic-diploma',
        name: 'Academic Diploma',
        description: 'Formal · landscape',
        accent: { bg: '#faf5e8', ink: INK.slate900, line: INK.gold },
        build: academicDiploma,
        page: { orientation: 'landscape', pageSize: 'a4' },
        finish: { pageColor: '#faf4e3', pagePattern: 'paperGrain', pageDeco: 'paperFolio' },
      },
      {
        id: 'portrait-elegant',
        name: 'Portrait Elegant',
        description: 'Upright layout · portrait',
        accent: { bg: '#ffffff', ink: INK.navy, line: INK.navy },
        build: portraitElegant,
        page: { orientation: 'portrait', pageSize: 'a4' },
        finish: { pageColor: '#ffffff', pagePattern: 'microDots', pageDeco: 'formalBlueBand' },
      },
    ];
    return list;
  }, []);

  const applyTemplate = useCallback(
    (tid: string) => {
      const t = templates.find((x) => x.id === tid);
      if (!t) {
        return;
      }
      onLayoutChange(t.build());
      setSelectedId(null);
      if (t.page?.orientation) {
        onOrientationChange(t.page.orientation);
      }
      if (typeof t.page?.featuredPreview === 'string') {
        onFeaturedPreviewChange(t.page.featuredPreview);
      } else if (t.finish) {
        // Templates with their own page finish look best without the featured photo on top.
        onFeaturedIdChange(0);
        onFeaturedPreviewChange('');
      }
      if (typeof t.page?.pageSize === 'string') {
        const ps = t.page.pageSize;
        onPageSizeChange(ps === 'a4' ? 'a4' : ps === 'a5' ? 'a5' : 'letter');
      }
      if (t.finish) {
        patchPageFinish(t.finish);
      }
      // Keep the user in Templates so they can try multiple options quickly.
    },
    [
      templates,
      onLayoutChange,
      onOrientationChange,
      onFeaturedPreviewChange,
      onFeaturedIdChange,
      onPageSizeChange,
      patchPageFinish,
    ]
  );

  const insertMediaImage = useCallback(() => {
    const url = String(mediaPickUrl || '').trim();
    if (!url) {
      return;
    }
    onLayoutChange((prev) => {
      const f = nextDropFrame(prev.blocks.length);
      const base = createBlock('image', { x: f.x, y: f.y, w: f.w, h: f.h, z: f.z, src: url, width: 160, align: 'center' });
      setSelectedId(base.id);
      return {
        ...prev,
        version: Math.max(CERT_LAYOUT_VERSION, prev.version || 1),
        blocks: [...prev.blocks, base],
      };
    });
    setLeftTab('elements');
  }, [mediaPickUrl, onLayoutChange]);

  const filteredTemplates = useMemo(() => {
    const q = templateQuery.trim().toLowerCase();
    if (!q) {
      return templates;
    }
    return templates.filter((t) => t.name.toLowerCase().includes(q));
  }, [templates, templateQuery]);

  return (
    <DndContext sensors={sensors} collisionDetection={closestCorners} onDragStart={onDragStart} onDragEnd={onDragEnd}>
      <div className="flex h-full min-h-0 w-full flex-col">
        <div className="grid h-full max-h-full min-h-0 w-full flex-1 grid-cols-[minmax(0,22.5rem)_minmax(0,1fr)_minmax(0,19rem)] gap-0 overflow-hidden 2xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)_minmax(0,21rem)]">
        <aside
          className={`flex h-full max-h-full min-h-0 flex-row overflow-hidden border-r border-slate-200/90 dark:border-slate-800 ${PANEL_CARD}`}
        >
          <div
            className="flex h-full min-h-0 w-[4.25rem] shrink-0 flex-col items-stretch gap-1 overflow-y-auto overscroll-contain border-r border-slate-200/90 bg-slate-100 py-2.5 dark:border-slate-800 dark:bg-slate-900/60"
            role="tablist"
            aria-label="Builder panels"
          >
            {(
              [
                { id: 'templates' as const, label: 'Templates', icon: 'plusDocument' },
                { id: 'elements' as const, label: 'Elements', icon: 'plusCircle' },
                { id: 'media' as const, label: 'Media', icon: 'photoImage' },
                { id: 'backgrounds' as const, label: 'Theme', icon: 'swatch' },
                // Keep Layers at the bottom of the rail.
                { id: 'layers' as const, label: 'Layers', icon: 'chapterStack', bottom: true },
              ] as const
            ).map((t) => {
              const active = leftTab === t.id;
              return (
                <button
                  key={t.id}
                  id={`sikshya-cert-tab-${t.id}`}
                  type="button"
                  role="tab"
                  onClick={() => setLeftTab(t.id)}
                  aria-selected={active}
                  aria-controls={CERT_LEFT_TAB_PANEL_ID}
                  className={`mx-1.5 flex flex-col items-center gap-1 rounded-lg px-1 py-2 text-[10px] font-semibold leading-tight transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/55 focus-visible:ring-offset-1 focus-visible:ring-offset-slate-100 dark:focus-visible:ring-offset-slate-900 ${
                    'bottom' in t && t.bottom ? 'mt-auto' : ''
                  } ${
                    active
                      ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/90 dark:bg-slate-800 dark:text-slate-50 dark:ring-slate-600'
                      : 'text-slate-500 hover:bg-white/80 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800/80 dark:hover:text-slate-200'
                  }`}
                >
                  <span className="flex h-9 w-9 items-center justify-center rounded-md bg-slate-200/60 text-slate-600 dark:bg-slate-700/70 dark:text-slate-300">
                    <NavIcon name={t.icon} className="h-5 w-5" />
                  </span>
                  <span className="max-w-[3.5rem] text-center">{t.label}</span>
                </button>
              );
            })}
          </div>
          <div
            id={CERT_LEFT_TAB_PANEL_ID}
            role="tabpanel"
            tabIndex={-1}
            aria-labelledby={`sikshya-cert-tab-${leftTab}`}
            className="min-h-0 min-w-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain px-3.5 py-3.5 outline-none"
          >
            {leftTab === 'templates' ? (
              <div className="space-y-5">
                <div className={PANEL_HEAD_RULE}>
                  <h3 className={PANEL_TITLE}>Templates</h3>
                  <p className={PANEL_LEDE}>Apply a starter layout, then fine-tune on the canvas.</p>
                </div>
                <input
                  type="search"
                  className={FIELD}
                  value={templateQuery}
                  onChange={(e) => setTemplateQuery(e.target.value)}
                  placeholder="Search templates…"
                  aria-label="Search templates"
                />
                <div className="grid gap-2.5">
                  {filteredTemplates.map((t) => {
                    const isPortrait = t.page?.orientation === 'portrait';
                    return (
                      <button
                        key={t.id}
                        type="button"
                        className="group flex w-full items-center gap-3 rounded-lg border border-slate-200/90 bg-white p-3 text-left shadow-sm transition hover:border-slate-300 hover:bg-slate-50/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/45 dark:border-slate-700 dark:bg-slate-800/60 dark:hover:border-slate-600 dark:hover:bg-slate-800"
                        onClick={() => applyTemplate(t.id)}
                      >
                        <span
                          className="relative shrink-0 overflow-hidden rounded-md border border-slate-200/80 shadow-inner dark:border-slate-700"
                          style={{
                            width: isPortrait ? 38 : 58,
                            height: isPortrait ? 54 : 40,
                            backgroundColor: t.accent.bg,
                          }}
                          aria-hidden="true"
                        >
                          {/* Accent bar (top) */}
                          <span
                            className="absolute inset-x-1.5 top-1.5 h-[3px] rounded-sm"
                            style={{ backgroundColor: t.accent.line }}
                          />
                          {/* Heading stub */}
                          <span
                            className="absolute left-1.5 right-1.5 rounded-sm"
                            style={{
                              top: isPortrait ? 14 : 11,
                              height: 4,
                              backgroundColor: t.accent.ink,
                              opacity: 0.88,
                            }}
                          />
                          {/* Name stub (wider) */}
                          <span
                            className="absolute rounded-sm"
                            style={{
                              left: isPortrait ? 6 : 6,
                              right: isPortrait ? 6 : 6,
                              top: isPortrait ? 24 : 20,
                              height: 3,
                              backgroundColor: t.accent.ink,
                              opacity: 0.45,
                            }}
                          />
                          {/* Subtext stub */}
                          <span
                            className="absolute rounded-sm"
                            style={{
                              left: isPortrait ? 10 : 12,
                              right: isPortrait ? 10 : 12,
                              top: isPortrait ? 30 : 26,
                              height: 2,
                              backgroundColor: t.accent.ink,
                              opacity: 0.3,
                            }}
                          />
                          {/* Bottom row (two signature stubs) */}
                          <span
                            className="absolute rounded-sm"
                            style={{
                              left: 4,
                              width: isPortrait ? 10 : 18,
                              bottom: 5,
                              height: 2,
                              backgroundColor: t.accent.ink,
                              opacity: 0.4,
                            }}
                          />
                          <span
                            className="absolute rounded-sm"
                            style={{
                              right: 4,
                              width: isPortrait ? 10 : 18,
                              bottom: 5,
                              height: 2,
                              backgroundColor: t.accent.ink,
                              opacity: 0.4,
                            }}
                          />
                        </span>
                        <span className="min-w-0">
                          <span className="block text-sm font-medium text-slate-900 dark:text-white">{t.name}</span>
                          <span className="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">
                            {t.description}
                          </span>
                        </span>
                      </button>
                    );
                  })}
                  {!filteredTemplates.length ? (
                    <p className="text-center text-xs text-slate-500 dark:text-slate-400">No templates match.</p>
                  ) : null}
                </div>
              </div>
            ) : null}

            {leftTab === 'layers' ? (
              <div className="space-y-5">
                <div className={PANEL_HEAD_RULE}>
                  <h3 className={PANEL_TITLE}>Layers</h3>
                  <p className={PANEL_LEDE}>Top of the list draws in front. Use ↑ ↓ to reorder, click a row to select, or remove with ×.</p>
                </div>
                <LayersPanel blocks={blocks} selectedId={selectedId} onSelect={setSelectedId} onLayoutChange={onLayoutChange} onRemoveBlock={removeBlock} />
              </div>
            ) : null}

            {leftTab === 'elements' ? (
              <div className="space-y-5">
                <div className={PANEL_HEAD_RULE}>
                  <h3 className={PANEL_TITLE}>Elements</h3>
                  <p className={PANEL_LEDE}>Drag a tile to the canvas, or use quick add for the same block.</p>
                </div>
                <div>
                  <p className={`mb-2.5 ${SECTION_LABEL}`}>Library</p>
                  <div className="grid grid-cols-2 gap-2.5">
                    {PALETTE_ITEMS.map((item) => (
                      <PaletteItem key={`${item.type}-${item.label}`} {...item} />
                    ))}
                  </div>
                </div>
                <div className="rounded-lg border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                  <p className={`mb-2.5 ${SECTION_LABEL}`}>Quick add</p>
                  <div className="grid grid-cols-2 gap-2">
                    {PALETTE_ITEMS.map((item) => (
                      <button
                        key={`q-${item.type}-${item.label}`}
                        type="button"
                        className="min-h-[2.75rem] rounded-md border border-slate-200/90 bg-white px-2 py-2 text-center text-xs font-medium leading-snug text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/35 focus-visible:ring-offset-1 focus-visible:ring-offset-white active:bg-slate-100 dark:border-slate-600 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-slate-500 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-900"
                        onClick={() => addBlockQuick(item)}
                        title={`Add ${item.label}`}
                        aria-label={`Quick add ${item.label}`}
                      >
                        {item.label}
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            ) : null}

            {leftTab === 'media' ? (
              <div className="space-y-5">
                <div className={PANEL_HEAD_RULE}>
                  <h3 className={PANEL_TITLE}>Media</h3>
                  <p className={PANEL_LEDE}>Pick an image from the library, then place it on the certificate.</p>
                </div>
                <div className="rounded-lg border border-slate-200/90 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                  <label className={LABEL} htmlFor="cv-media-pick">
                    Image from library
                  </label>
                  <WPMediaPickerField
                    id="cv-media-pick"
                    value={mediaPickUrl}
                    onChange={setMediaPickUrl}
                    placeholder="Pick or paste image URL"
                    imageOnly
                  />
                    <button
                      type="button"
                      className="mt-3 w-full rounded-md border border-slate-800 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-600 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white dark:focus-visible:ring-slate-500/60"
                      disabled={!String(mediaPickUrl || '').trim()}
                      onClick={() => insertMediaImage()}
                    >
                    Add to canvas
                  </button>
                  <p className={HINT}>You can also drag an Image tile from Elements and set the URL in the right panel.</p>
                </div>
              </div>
            ) : null}

            {leftTab === 'backgrounds' ? (
              <div className="space-y-5">
                <div className={PANEL_HEAD_RULE}>
                  <h3 className={PANEL_TITLE}>Theme</h3>
                  <p className={PANEL_LEDE}>Page color, texture, art background, paper size, and accent stripe.</p>
                </div>

                <div className="rounded-lg border border-slate-200/90 bg-slate-50/40 p-3 dark:border-slate-700 dark:bg-slate-800/35">
                  <h4 className={SECTION_LABEL}>Quick themes</h4>
                  <p className="mt-1.5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                    One-tap combinations. Some keep your background photo; others clear it so art shows.
                  </p>
                  <div className="mt-3 grid grid-cols-1 gap-2">
                    {CERTIFICATE_THEME_QUICK_PRESETS.map((preset) => {
                      const active =
                        pageFinish.pageColor.toLowerCase() === preset.finish.pageColor.toLowerCase() &&
                        pageFinish.pagePattern === preset.finish.pagePattern &&
                        pageFinish.pageDeco === preset.finish.pageDeco;
                      return (
                        <button
                          key={preset.id}
                          type="button"
                          onClick={() => applyThemeQuickPreset(preset)}
                          className={`w-full rounded-lg border px-3 py-2.5 text-left shadow-sm transition hover:border-slate-300 hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/40 dark:hover:border-slate-600 dark:hover:bg-slate-900/80 ${
                            active
                              ? 'border-slate-900 bg-white ring-1 ring-slate-900/10 dark:border-slate-200 dark:bg-slate-900 dark:ring-white/10'
                              : 'border-slate-200/90 bg-white dark:border-slate-600 dark:bg-slate-900/50'
                          }`}
                        >
                          <span className="block text-xs font-semibold text-slate-900 dark:text-slate-100">{preset.label}</span>
                          <span className="mt-1 block text-[11px] leading-snug text-slate-600 dark:text-slate-400">
                            {preset.caption}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                </div>

                <div>
                  <h4 className={SECTION_LABEL}>Color</h4>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Base fill; pattern and image go on top.</p>
                  <div className="mt-2 flex flex-wrap items-center gap-1.5">
                    {CERT_PAGE_SWATCHES.map((hex) => (
                      <button
                        key={hex}
                        type="button"
                        title={hex}
                        onClick={() => patchPageFinish({ pageColor: hex })}
                        className={`h-8 w-8 rounded-full border-2 shadow-sm transition ring-offset-1 ring-offset-white focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50 dark:ring-offset-slate-900 ${
                          pageFinish.pageColor.toLowerCase() === hex.toLowerCase()
                            ? 'border-slate-900 ring-2 ring-slate-400/40 dark:border-slate-100'
                            : 'border-slate-200/90 hover:ring-1 hover:ring-slate-300 dark:border-slate-600'
                        }`}
                        style={{ backgroundColor: hex }}
                        aria-label={`Set page color ${hex}`}
                      />
                    ))}
                    {/*
                      The native color picker anchors to the DOM position of the <input type="color">.
                      Overlay the real input (opacity-0) on top of the "+" badge so the picker opens
                      right next to the button instead of off-screen.
                    */}
                    <span className="relative inline-flex h-8 w-8" title="Pick custom color">
                      <span
                        aria-hidden="true"
                        className="pointer-events-none flex h-8 w-8 items-center justify-center rounded-full border border-dashed border-slate-300 bg-slate-50 text-slate-500 shadow-sm transition dark:border-slate-600 dark:bg-slate-800"
                      >
                        <span className="text-lg leading-none">+</span>
                      </span>
                      <input
                        type="color"
                        aria-label="Pick custom page color"
                        className="absolute inset-0 h-8 w-8 cursor-pointer rounded-full border-0 bg-transparent p-0 opacity-0 focus-visible:opacity-10 focus-visible:outline-2 focus-visible:outline-slate-400"
                        value={/^#[0-9A-Fa-f]{6}$/i.test(pageFinish.pageColor) ? pageFinish.pageColor : '#ffffff'}
                        onChange={(e) => patchPageFinish({ pageColor: e.target.value })}
                      />
                    </span>
                  </div>
                </div>

                <div>
                  <div className="flex items-center justify-between gap-2">
                    <h4 className={SECTION_LABEL}>Pattern</h4>
                    <button
                      type="button"
                      className="shrink-0 rounded px-1 py-0.5 text-xs text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/40 dark:text-slate-400 dark:hover:text-slate-200"
                      onClick={() => setShowAllPatterns((s) => !s)}
                    >
                      {showAllPatterns ? 'Less' : 'View all'}
                    </button>
                  </div>
                  <p className="text-xs text-slate-500 dark:text-slate-400">A subtle texture on top of your color (optional).</p>
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {(showAllPatterns
                      ? [...CERT_PAGE_PATTERN_ORDER]
                      : (['none', 'dots', 'lines', 'grid'] as const)
                    ).map((pid) => {
                      const isNone = pid === 'none';
                      const p = isNone ? null : getCertificatePagePatternLayer(pid);
                      const active = (isNone && pageFinish.pagePattern === 'none') || (!isNone && pageFinish.pagePattern === pid);
                      return (
                        <button
                          key={pid}
                          type="button"
                          onClick={() => patchPageFinish({ pagePattern: isNone ? 'none' : pid })}
                          className={`relative h-12 w-12 overflow-hidden rounded-lg border-2 shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/40 ${
                            active
                              ? 'border-slate-900 ring-2 ring-slate-400/35 dark:border-slate-100'
                              : 'border-slate-200/90 hover:border-slate-300 dark:border-slate-600'
                          }`}
                          style={
                            p
                              ? {
                                  backgroundColor: '#f1f5f9',
                                  backgroundImage: p.image,
                                  backgroundSize: p.size,
                                  backgroundRepeat: 'repeat',
                                }
                              : { backgroundColor: '#f8fafc' }
                          }
                          title={isNone ? 'No pattern' : pid}
                          aria-label={isNone ? 'No pattern' : `Pattern ${pid}`}
                        >
                          {isNone ? <span className="text-xs text-slate-400">—</span> : null}
                        </button>
                      );
                    })}
                  </div>
                </div>

                <div>
                  <div className="flex items-center justify-between gap-2">
                    <h4 className={SECTION_LABEL}>Background</h4>
                    <button
                      type="button"
                      className="shrink-0 rounded px-1 py-0.5 text-xs text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/40 dark:text-slate-400 dark:hover:text-slate-200"
                      onClick={() => setShowAllDecos((s) => !s)}
                    >
                      {showAllDecos ? 'Less' : 'View all'}
                    </button>
                  </div>
                  <p className="text-xs text-slate-500 dark:text-slate-400">
                    Art fills the page (your background photo is hidden until you add it again in Page settings below).
                  </p>
                  <div className="mt-2 grid grid-cols-2 gap-1.5">
                    {(showAllDecos ? [...CERT_PAGE_DECO_ORDER] : CERT_PAGE_DECO_ORDER.slice(0, CERT_PAGE_DECO_SHOW_FIRST)).map(
                      (id) => {
                        const g = getCertificatePageDecoGradient(id);
                        const active = pageFinish.pageDeco === id;
                        const label = CERT_PAGE_DECO_LABELS[id] ?? id;
                        return (
                          <button
                            key={id}
                            type="button"
                            onClick={() => applyDecoPreset(id)}
                            className={`relative flex aspect-[4/3] w-full flex-col justify-end overflow-hidden rounded border p-1 text-left shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/40 ${
                              active
                                ? 'border-slate-900 ring-1 ring-slate-400/50 dark:border-slate-100'
                                : 'border-slate-200/90 hover:border-slate-300 dark:border-slate-600'
                            }`}
                            style={g ? { backgroundImage: g, backgroundSize: '100% 100%' } : undefined}
                            title={label}
                            aria-label={`Background ${label}`}
                          >
                            <span className="pointer-events-none truncate rounded bg-white/90 px-1 py-0.5 text-[9px] font-medium text-slate-700 shadow-sm dark:bg-slate-900/90 dark:text-slate-100">
                              {label}
                            </span>
                          </button>
                        );
                      }
                    )}
                  </div>
                  <button
                    type="button"
                    className="mt-1.5 rounded px-0.5 text-xs font-medium text-slate-500 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/40 dark:text-slate-400 dark:hover:text-slate-200"
                    onClick={() => patchPageFinish({ pageDeco: 'none' })}
                  >
                    Use plain color only (no art background)
                  </button>
                </div>

                <div className="border-t border-slate-200/80 pt-4 dark:border-slate-800">
                  <h4 className={SECTION_LABEL}>Page settings</h4>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Size and orientation.</p>
                  <div className="mt-3 space-y-4">
                    <div>
                      <label className={LABEL} htmlFor="cv-size">
                        Certificate size
                      </label>
                      <select
                        id="cv-size"
                        className={FIELD}
                        value={pageSize}
                        onChange={(e) => onPageSizeChange(e.target.value === 'a4' ? 'a4' : e.target.value === 'a5' ? 'a5' : 'letter')}
                      >
                        <option value="letter">Letter (US)</option>
                        <option value="a4">A4</option>
                        <option value="a5">A5</option>
                      </select>
                      <p className={HINT}>
                        The sheet in the center is drawn at real size (A4/A5 in millimetres, Letter in inches). Scroll the
                        canvas area if it is larger than your screen. Export uses the same proportions.
                      </p>
                    </div>
                    <div>
                      <label className={LABEL} htmlFor="cv-orient">
                        Orientation
                      </label>
                      <select
                        id="cv-orient"
                        className={FIELD}
                        value={orientation}
                        onChange={(e) => onOrientationChange(e.target.value === 'portrait' ? 'portrait' : 'landscape')}
                      >
                        <option value="landscape">Landscape</option>
                        <option value="portrait">Portrait</option>
                      </select>
                    </div>
                  </div>
                </div>

                <div>
                  <label className={LABEL} htmlFor="cv-bg">
                    Your background photo
                  </label>
                  <WPMediaPickerField
                    id="cv-bg"
                    value={featuredPreview}
                    onChange={onFeaturedPreviewChange}
                    onAttachmentIdChange={onFeaturedIdChange}
                    placeholder="Optional: use your own full-page image (replaces the art background above)."
                    imageOnly
                  />
                  <p className={HINT}>Add a company banner or stock image. This sits above your color and pattern.</p>
                </div>
              </div>
            ) : null}
          </div>
        </aside>

        <main className="flex h-full max-h-full min-h-0 min-w-0 flex-col overflow-hidden border-r border-slate-200/80 bg-slate-100/80 dark:border-slate-800 dark:bg-slate-950/50">
          <div className="min-h-0 flex-1 overflow-auto overscroll-contain p-3 sm:p-4 xl:p-5">
            <CanvasDropArea
              hasBlocks={blocks.length > 0}
              orientation={orientation}
              pageSize={pageSize}
              featuredPreview={featuredPreview}
              pageFinish={pageFinish}
              pageRef={pageRef}
              onBackgroundClick={() => setSelectedId(null)}
            >
              {blocksSorted.map((b) => (
                <PositionedCanvasBlock
                  key={b.id}
                  block={b}
                  pageRef={pageRef}
                  selected={selectedId === b.id}
                  onSelect={() => setSelectedId(b.id)}
                  onUpdate={updateBlock}
                  onRemove={removeBlock}
                  templatePreviewUrl={templatePreviewUrl}
                />
              ))}
            </CanvasDropArea>
          </div>
        </main>

        <aside className="flex h-full max-h-full min-h-0 flex-col overflow-hidden border-l border-slate-200/90 bg-white dark:border-slate-800 dark:bg-slate-900">
          <div className="shrink-0 border-b border-slate-200/90 px-4 pb-3 pt-4 dark:border-slate-800">
            <h3 className={PANEL_TITLE}>{selected ? 'Properties' : 'Inspector'}</h3>
            <p className={PANEL_LEDE}>
              {selected
                ? 'Adjust the selected block. Changes apply to this certificate only.'
                : 'Select a block on the sheet to edit content and layout.'}
            </p>
          </div>
          <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-4 pt-2">
            <Inspector selected={selected} onUpdateBlock={updateBlock} onRemoveBlock={removeBlock} />
          </div>
        </aside>
        </div>
      </div>

      <DragOverlay dropAnimation={null}>
        {activeDrag && activeDrag.type ? (
          <div className="flex items-center gap-2.5 rounded-lg border border-slate-200/90 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-xl ring-1 ring-slate-900/5 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:ring-black/20">
            <span className="flex h-8 w-8 items-center justify-center rounded-md bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
              <NavIcon name="plusCircle" className="h-4 w-4" />
            </span>
            <span>Add {PALETTE_ITEMS.find((x) => x.type === activeDrag.type)?.label ?? activeDrag.type}</span>
          </div>
        ) : null}
      </DragOverlay>
    </DndContext>
  );
}
