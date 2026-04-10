import { useCallback, useMemo, useState, type Dispatch, type SetStateAction } from 'react';
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
import { SortableContext, arrayMove, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { NavIcon } from '../../components/NavIcon';
import { WPMediaPickerField } from '../../components/shared/WPMediaPickerField';
import {
  type CertBlock,
  type CertBlockType,
  type MergeFieldKey,
  PALETTE_ITEMS,
  createBlock,
  mergeFieldLabel,
  mergeFieldToken,
  substituteMergePreview,
  type CertLayoutFile,
} from './certificateLayout';

const FIELD =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LABEL = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const HINT = 'mt-1 text-xs text-slate-500 dark:text-slate-400';

const CANVAS_ID = 'sikshya-cert-canvas-root';

type Props = {
  layout: CertLayoutFile;
  onLayoutChange: Dispatch<SetStateAction<CertLayoutFile>>;
  orientation: 'landscape' | 'portrait';
  onOrientationChange: (v: 'landscape' | 'portrait') => void;
  accentColor: string;
  onAccentColorChange: (v: string) => void;
  featuredPreview: string;
  onFeaturedPreviewChange: (url: string) => void;
  onFeaturedIdChange: (id: number) => void;
};

type LeftTabId = 'templates' | 'elements' | 'media' | 'backgrounds';

function paletteDraggableId(type: CertBlockType): string {
  return `palette-${type}`;
}

function PaletteItem(props: { type: CertBlockType; label: string; description: string }) {
  const { type, label, description } = props;
  const id = paletteDraggableId(type);
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id,
    data: { source: 'palette', blockType: type },
  });

  const style = transform ? { transform: `translate3d(${transform.x}px,${transform.y}px,0)` } : undefined;

  return (
    <div ref={setNodeRef} style={style} className={`${isDragging ? 'opacity-50' : ''}`}>
      <button
        type="button"
        className="flex w-full cursor-grab items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-brand-300 hover:bg-brand-50/40 active:cursor-grabbing dark:border-slate-600 dark:bg-slate-800 dark:hover:border-brand-600 dark:hover:bg-slate-800/80"
        {...listeners}
        {...attributes}
      >
        <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300">
          <NavIcon name="dragHandle" className="h-4 w-4" />
        </span>
        <span className="min-w-0">
          <span className="block text-sm font-semibold text-slate-900 dark:text-white">{label}</span>
          <span className="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{description}</span>
        </span>
      </button>
    </div>
  );
}

function BlockCanvasPreview({ block }: { block: CertBlock }) {
  const p = block.props;
  switch (block.type) {
    case 'heading': {
      const Tag = ['h1', 'h2', 'h3'].includes(String(p.tag)) ? (String(p.tag) as 'h1' | 'h2' | 'h3') : 'h1';
      return (
        <Tag
          style={{
            textAlign: (p.align as string) || 'center',
            fontSize: Number(p.fontSize) || 28,
            color: (p.color as string) || '#0f172a',
            fontWeight: (p.fontWeight as string) || '700',
            margin: '0.25em 0',
          }}
        >
          {String(p.text || '')}
        </Tag>
      );
    }
    case 'text':
      return (
        <p
          style={{
            textAlign: (p.align as string) || 'left',
            fontSize: Number(p.fontSize) || 14,
            color: (p.color as string) || '#334155',
            margin: '0.35em 0',
            lineHeight: 1.5,
            whiteSpace: 'pre-wrap',
          }}
        >
          {String(p.text || '')}
        </p>
      );
    case 'merge_field': {
      const field = (String(p.field) as MergeFieldKey) || 'student_name';
      const token = mergeFieldToken(field);
      const sample = substituteMergePreview(token);
      return (
        <div
          style={{
            textAlign: (p.align as string) || 'center',
            fontSize: Number(p.fontSize) || 22,
            color: (p.color as string) || '#0f172a',
            fontWeight: 600,
            margin: '0.25em 0',
          }}
        >
          {sample}
          <div className="text-[10px] font-normal normal-case text-slate-400">({mergeFieldLabel(field)})</div>
        </div>
      );
    }
    case 'spacer':
      return <div style={{ height: Number(p.height) || 16 }} className="bg-transparent" aria-hidden />;
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
            className="flex items-center justify-center rounded-lg border border-dashed border-slate-300 py-6 text-xs text-slate-400 dark:border-slate-600"
            style={{ justifyContent: jc }}
          >
            Image — set URL in settings
          </div>
        );
      }
      return (
        <div className="flex py-1" style={{ justifyContent: jc }}>
          <img src={src} alt="" style={{ width: w, maxWidth: '100%', height: 'auto' }} />
        </div>
      );
    }
    default:
      return null;
  }
}

function SortableCanvasBlock(props: {
  block: CertBlock;
  selected: boolean;
  onSelect: () => void;
  onRemove: () => void;
}) {
  const { block, selected, onSelect, onRemove } = props;
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: block.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.45 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`group relative rounded-lg border-2 bg-white/90 px-2 py-1.5 transition-colors dark:bg-slate-900/80 ${
        selected ? 'border-brand-500 ring-2 ring-brand-500/20' : 'border-transparent hover:border-slate-200 dark:hover:border-slate-600'
      }`}
      onClick={(e) => {
        e.stopPropagation();
        onSelect();
      }}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onSelect();
        }
      }}
      role="button"
      tabIndex={0}
    >
      <div className="absolute right-1 top-1 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
        <button
          type="button"
          className="rounded-md bg-slate-100 p-1 text-slate-500 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600"
          aria-label="Drag to reorder"
          {...attributes}
          {...listeners}
        >
          <NavIcon name="dragHandle" className="h-3.5 w-3.5" />
        </button>
        <button
          type="button"
          className="rounded-md bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-700 hover:bg-red-100 dark:bg-red-950/50 dark:text-red-300"
          onClick={(e) => {
            e.stopPropagation();
            onRemove();
          }}
        >
          ×
        </button>
      </div>
      <BlockCanvasPreview block={block} />
    </div>
  );
}

function CanvasDropArea(props: {
  children: React.ReactNode;
  hasBlocks: boolean;
  orientation: 'landscape' | 'portrait';
  accentColor: string;
  featuredPreview: string;
  onBackgroundClick: () => void;
}) {
  const { children, hasBlocks, orientation, accentColor, featuredPreview, onBackgroundClick } = props;
  const { setNodeRef, isOver } = useDroppable({ id: CANVAS_ID });

  const border =
    accentColor && /^#[0-9A-Fa-f]{6}$/.test(accentColor) ? accentColor : '#6366f1';
  const aspect = orientation === 'portrait' ? 'aspect-[8.5/11]' : 'aspect-[11/8.5]';

  return (
    <div
      ref={setNodeRef}
      className={`mx-auto w-full max-w-4xl overflow-hidden rounded-2xl border-2 border-dashed shadow-inner transition-colors ${aspect} max-h-[min(72vh,820px)] ${
        isOver ? 'border-brand-400 bg-brand-50/30 dark:border-brand-500 dark:bg-brand-950/20' : 'border-slate-300 dark:border-slate-600'
      }`}
      style={{ borderStyle: isOver ? 'solid' : 'dashed' }}
    >
      <button
        type="button"
        className="relative h-full w-full overflow-auto text-left"
        onClick={onBackgroundClick}
        aria-label="Certificate canvas — click to edit page settings"
      >
        <div
          className="relative min-h-full w-full bg-slate-50 dark:bg-slate-900"
          style={{
            backgroundImage: featuredPreview ? `url(${featuredPreview})` : undefined,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
          }}
        >
          <div
            className="absolute inset-0 bg-white/88 dark:bg-slate-950/85"
            style={{ backdropFilter: 'blur(1px)' }}
            aria-hidden
          />
          <div
            className="relative z-[1] flex min-h-full flex-col p-6 sm:p-10"
            style={{ borderTop: `4px solid ${border}` }}
          >
            {!hasBlocks ? (
              <div className="flex flex-1 flex-col items-center justify-center gap-2 text-center text-sm text-slate-500 dark:text-slate-400">
                <NavIcon name="plusCircle" className="h-10 w-10 opacity-40" />
                <p className="font-medium text-slate-700 dark:text-slate-200">Drop elements here</p>
                <p className="max-w-xs text-xs">
                  Drag from Elements, add images from Library, or use quick add. Page background lives under Backgrounds.
                </p>
              </div>
            ) : (
              <div className="flex flex-col gap-1">{children}</div>
            )}
          </div>
        </div>
      </button>
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
      <div className="space-y-3">
        <div>
          <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Select an element</h3>
          <p className={HINT}>Click an element on the canvas to edit its settings.</p>
        </div>
      </div>
    );
  }

  const p = selected.props;
  const patch = (o: Record<string, unknown>) => onUpdateBlock(selected.id, o);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          {PALETTE_ITEMS.find((x) => x.type === selected.type)?.label ?? selected.type}
        </h3>
        <button
          type="button"
          className="text-xs font-medium text-red-600 hover:underline dark:text-red-400"
          onClick={() => onRemoveBlock(selected.id)}
        >
          Remove
        </button>
      </div>

      {selected.type === 'heading' ? (
        <>
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
              value={Number(p.fontSize) || 28}
              onChange={(e) => patch({ fontSize: Number(e.target.value) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-h-color">
              Color
            </label>
            <input
              id="insp-h-color"
              type="color"
              className="mt-1 h-9 w-full max-w-[5rem] cursor-pointer rounded border border-slate-200 dark:border-slate-600"
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
        </>
      ) : null}

      {selected.type === 'text' ? (
        <>
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
              value={Number(p.fontSize) || 14}
              onChange={(e) => patch({ fontSize: Number(e.target.value) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-t-color">
              Color
            </label>
            <input
              id="insp-t-color"
              type="color"
              className="mt-1 h-9 w-full max-w-[5rem] cursor-pointer rounded border border-slate-200 dark:border-slate-600"
              value={/^#[0-9A-Fa-f]{6}$/.test(String(p.color)) ? String(p.color) : '#334155'}
              onChange={(e) => patch({ color: e.target.value })}
            />
          </div>
        </>
      ) : null}

      {selected.type === 'merge_field' ? (
        <>
          <div>
            <label className={LABEL} htmlFor="insp-m-field">
              Field
            </label>
            <select
              id="insp-m-field"
              className={FIELD}
              value={String(p.field ?? 'student_name')}
              onChange={(e) => patch({ field: e.target.value as MergeFieldKey })}
            >
              {(Object.keys({
                student_name: 1,
                course_name: 1,
                completion_date: 1,
                certificate_id: 1,
                site_name: 1,
              }) as MergeFieldKey[]).map((k) => (
                <option key={k} value={k}>
                  {mergeFieldLabel(k)}
                </option>
              ))}
            </select>
            <p className={HINT}>Stored as {mergeFieldToken(String(p.field ?? 'student_name') as MergeFieldKey)} in HTML export.</p>
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
              value={Number(p.fontSize) || 22}
              onChange={(e) => patch({ fontSize: Number(e.target.value) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-m-color">
              Color
            </label>
            <input
              id="insp-m-color"
              type="color"
              className="mt-1 h-9 w-full max-w-[5rem] cursor-pointer rounded border border-slate-200 dark:border-slate-600"
              value={/^#[0-9A-Fa-f]{6}$/.test(String(p.color)) ? String(p.color) : '#0f172a'}
              onChange={(e) => patch({ color: e.target.value })}
            />
          </div>
        </>
      ) : null}

      {selected.type === 'spacer' ? (
        <div>
          <label className={LABEL} htmlFor="insp-s-h">
            Height (px)
          </label>
          <input
            id="insp-s-h"
            type="range"
            min={0}
            max={200}
            className="mt-2 w-full"
            value={Number(p.height) || 24}
            onChange={(e) => patch({ height: Number(e.target.value) })}
          />
          <p className="mt-1 text-xs text-slate-500">{Number(p.height) || 24}px</p>
        </div>
      ) : null}

      {selected.type === 'divider' ? (
        <>
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
              value={Number(p.thickness) || 2}
              onChange={(e) => patch({ thickness: Number(e.target.value) })}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="insp-d-color">
              Color
            </label>
            <input
              id="insp-d-color"
              type="color"
              className="mt-1 h-9 w-full max-w-[5rem] cursor-pointer rounded border border-slate-200 dark:border-slate-600"
              value={/^#[0-9A-Fa-f]{6}$/.test(String(p.color)) ? String(p.color) : '#cbd5e1'}
              onChange={(e) => patch({ color: e.target.value })}
            />
          </div>
        </>
      ) : null}

      {selected.type === 'image' ? (
        <>
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
              value={Number(p.width) || 120}
              onChange={(e) => patch({ width: Number(e.target.value) })}
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
        </>
      ) : null}
    </div>
  );
}

export function CertificateVisualBuilder(props: Props) {
  const {
    layout,
    onLayoutChange,
    orientation,
    onOrientationChange,
    accentColor,
    onAccentColorChange,
    featuredPreview,
    onFeaturedPreviewChange,
    onFeaturedIdChange,
  } = props;

  const blocks = layout.blocks;
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [activeDrag, setActiveDrag] = useState<{ source: 'palette' | 'sort'; type?: CertBlockType; block?: CertBlock } | null>(
    null
  );

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  const onDragStart = useCallback((e: DragStartEvent) => {
    const src = e.active.data.current?.source;
    if (src === 'palette') {
      setActiveDrag({ source: 'palette', type: e.active.data.current?.blockType as CertBlockType });
    } else {
      const b = layout.blocks.find((x) => x.id === e.active.id);
      if (b) {
        setActiveDrag({ source: 'sort', block: b });
      }
    }
  }, [layout.blocks]);

  const onDragEnd = useCallback(
    (e: DragEndEvent) => {
      setActiveDrag(null);
      const { active, over } = e;
      if (!over) {
        return;
      }

      if (active.data.current?.source === 'palette') {
        const blockType = active.data.current?.blockType as CertBlockType;
        if (!blockType) {
          return;
        }
        const nb = createBlock(blockType);
        onLayoutChange((prev) => {
          const list = prev.blocks;
          if (over.id === CANVAS_ID) {
            return { ...prev, blocks: [...list, nb] };
          }
          const idx = list.findIndex((b) => b.id === over.id);
          if (idx < 0) {
            return prev;
          }
          return { ...prev, blocks: [...list.slice(0, idx), nb, ...list.slice(idx)] };
        });
        setSelectedId(nb.id);
        return;
      }

      if (active.id !== over.id) {
        onLayoutChange((prev) => {
          const list = prev.blocks;
          const oldIndex = list.findIndex((b) => b.id === active.id);
          const newIndex = list.findIndex((b) => b.id === over.id);
          if (oldIndex < 0 || newIndex < 0) {
            return prev;
          }
          return { ...prev, blocks: arrayMove(list, oldIndex, newIndex) };
        });
      }
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
    (type: CertBlockType) => {
      const nb = createBlock(type);
      onLayoutChange((prev) => ({ ...prev, blocks: [...prev.blocks, nb] }));
      setSelectedId(nb.id);
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
      build: () => CertLayoutFile;
      page?: Partial<Pick<Props, 'orientation' | 'accentColor' | 'featuredPreview'>>;
    };
    const classic = (): CertLayoutFile => {
      const h = createBlock('heading');
      const s1 = createBlock('spacer');
      const t1 = createBlock('text');
      const mStudent = createBlock('merge_field');
      const t2 = createBlock('text');
      const mCourse = createBlock('merge_field');
      const s2 = createBlock('spacer');
      const mDate = createBlock('merge_field');
      return {
        version: 1,
        blocks: [
          { ...h, props: { ...h.props, text: 'Certificate of Completion', fontSize: 34 } },
          { ...s1, props: { height: 10 } },
          { ...t1, props: { ...t1.props, text: 'This certifies that', align: 'center' } },
          { ...mStudent, props: { ...mStudent.props, field: 'student_name', fontSize: 30, align: 'center' } },
          { ...t2, props: { ...t2.props, text: 'has successfully completed', align: 'center' } },
          { ...mCourse, props: { ...mCourse.props, field: 'course_name', fontSize: 22, align: 'center' } },
          { ...s2, props: { height: 18 } },
          { ...mDate, props: { ...mDate.props, field: 'completion_date', fontSize: 14, align: 'center' } },
        ],
      };
    };
    const minimal = (): CertLayoutFile => {
      const h = createBlock('heading');
      const d = createBlock('divider');
      const mStudent = createBlock('merge_field');
      const t1 = createBlock('text');
      const mCourse = createBlock('merge_field');
      const s1 = createBlock('spacer');
      const mDate = createBlock('merge_field');
      return {
        version: 1,
        blocks: [
          { ...h, props: { ...h.props, text: 'Completion', tag: 'h2', fontSize: 26, align: 'left' } },
          { ...d, props: { color: '#e2e8f0', thickness: 2 } },
          { ...mStudent, props: { ...mStudent.props, field: 'student_name', fontSize: 28, align: 'left' } },
          { ...t1, props: { ...t1.props, text: 'completed', align: 'left' } },
          { ...mCourse, props: { ...mCourse.props, field: 'course_name', fontSize: 18, align: 'left' } },
          { ...s1, props: { height: 16 } },
          { ...mDate, props: { ...mDate.props, field: 'completion_date', fontSize: 12, align: 'left' } },
        ],
      };
    };
    const list: Tpl[] = [
      { id: 'classic', name: 'Classic', build: classic, page: { accentColor: '#6366f1', orientation: 'landscape' } },
      { id: 'minimal', name: 'Minimal', build: minimal, page: { accentColor: '#0ea5e9', orientation: 'landscape' } },
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
      if (typeof t.page?.accentColor === 'string') {
        onAccentColorChange(t.page.accentColor);
      }
      if (typeof t.page?.featuredPreview === 'string') {
        onFeaturedPreviewChange(t.page.featuredPreview);
      }
      setLeftTab('elements');
    },
    [templates, onLayoutChange, onOrientationChange, onAccentColorChange, onFeaturedPreviewChange]
  );

  const insertMediaImage = useCallback(() => {
    const url = String(mediaPickUrl || '').trim();
    if (!url) {
      return;
    }
    const base = createBlock('image');
    const nb: CertBlock = { ...base, props: { ...base.props, src: url, width: 160, align: 'center' } };
    onLayoutChange((prev) => ({ ...prev, blocks: [...prev.blocks, nb] }));
    setSelectedId(nb.id);
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
      <div className="flex min-h-[560px] flex-col gap-4 xl:flex-row xl:items-stretch">
        <aside className="w-full shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50 xl:w-[22rem]">
          <div className="border-b border-slate-200/80 p-2 dark:border-slate-700">
            <div className="grid grid-cols-4 gap-1 rounded-xl bg-white p-1 shadow-sm dark:bg-slate-900">
              {(
                [
                  { id: 'templates' as const, label: 'Templates', icon: 'plusDocument' },
                  { id: 'elements' as const, label: 'Elements', icon: 'plusCircle' },
                  { id: 'media' as const, label: 'Library', icon: 'photoImage' },
                  { id: 'backgrounds' as const, label: 'Backgrounds', icon: 'layers' },
                ] as const
              ).map((t) => (
                <button
                  key={t.id}
                  type="button"
                  className={`flex flex-col items-center justify-center gap-0.5 rounded-lg px-1.5 py-2 text-[10px] font-semibold leading-tight transition sm:text-[11px] ${
                    leftTab === t.id
                      ? 'bg-brand-600 text-white'
                      : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800'
                  }`}
                  onClick={() => setLeftTab(t.id)}
                >
                  <NavIcon name={t.icon} className="h-4 w-4" />
                  <span className="text-center">{t.label}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="max-h-[min(74vh,760px)] overflow-y-auto p-4">
            {leftTab === 'templates' ? (
              <div className="space-y-3">
                <div>
                  <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Templates</h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Starter layouts you can edit freely.</p>
                </div>
                <input
                  type="search"
                  className={FIELD}
                  value={templateQuery}
                  onChange={(e) => setTemplateQuery(e.target.value)}
                  placeholder="Search templates…"
                  aria-label="Search templates"
                />
                <div className="grid gap-3">
                  {filteredTemplates.map((t) => (
                    <button
                      key={t.id}
                      type="button"
                      className="group flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-brand-300 hover:bg-brand-50/40 dark:border-slate-600 dark:bg-slate-800 dark:hover:border-brand-600 dark:hover:bg-slate-800/80"
                      onClick={() => applyTemplate(t.id)}
                    >
                      <span className="relative h-12 w-16 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                        <span
                          className="absolute inset-x-0 top-0 h-1.5"
                          style={{
                            backgroundColor:
                              t.page?.accentColor && /^#[0-9A-Fa-f]{6}$/.test(String(t.page.accentColor))
                                ? String(t.page.accentColor)
                                : '#6366f1',
                          }}
                        />
                        <span className="absolute left-2 top-4 h-1 w-10 rounded bg-slate-200 dark:bg-slate-700" />
                        <span className="absolute left-2 top-7 h-1 w-8 rounded bg-slate-200 dark:bg-slate-700" />
                      </span>
                      <span className="min-w-0">
                        <span className="block text-sm font-semibold text-slate-900 dark:text-white">{t.name}</span>
                        <span className="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                          Layout + accent and orientation.
                        </span>
                      </span>
                      <span className="ml-auto text-xs font-semibold text-brand-700 opacity-0 transition group-hover:opacity-100 dark:text-brand-300">
                        Use →
                      </span>
                    </button>
                  ))}
                  {!filteredTemplates.length ? (
                    <p className="text-center text-xs text-slate-500 dark:text-slate-400">No templates match.</p>
                  ) : null}
                </div>
              </div>
            ) : null}

            {leftTab === 'elements' ? (
              <div className="space-y-3">
                <div>
                  <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Elements</h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Drag onto the canvas or use quick add.</p>
                </div>
                <div className="space-y-2">
                  {PALETTE_ITEMS.map((item) => (
                    <PaletteItem key={item.type} type={item.type} label={item.label} description={item.description} />
                  ))}
                </div>
                <div className="border-t border-slate-200 pt-3 dark:border-slate-700">
                  <p className="mb-2 text-xs font-medium text-slate-600 dark:text-slate-300">Quick add</p>
                  <div className="flex flex-wrap gap-1.5">
                    {PALETTE_ITEMS.map((item) => (
                      <button
                        key={`q-${item.type}`}
                        type="button"
                        className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        onClick={() => addBlockQuick(item.type)}
                      >
                        + {item.label}
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            ) : null}

            {leftTab === 'media' ? (
              <div className="space-y-4">
                <div>
                  <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Media library</h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Choose an image, then add it to the canvas.</p>
                </div>
                <div>
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
                    className="mt-3 w-full rounded-xl bg-brand-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                    disabled={!String(mediaPickUrl || '').trim()}
                    onClick={() => insertMediaImage()}
                  >
                    Add image to canvas
                  </button>
                  <p className={HINT}>You can also drag the Image element from Elements and set the source in settings.</p>
                </div>
              </div>
            ) : null}

            {leftTab === 'backgrounds' ? (
              <div className="space-y-5">
                <div>
                  <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Backgrounds</h3>
                  <p className="text-xs text-slate-500 dark:text-slate-400">Page-wide look: orientation, accent, and backdrop.</p>
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
                <div>
                  <label className={LABEL} htmlFor="cv-accent">
                    Accent color
                  </label>
                  <div className="mt-1.5 flex flex-wrap items-center gap-3">
                    <input
                      id="cv-accent"
                      type="color"
                      className="h-10 w-14 cursor-pointer rounded border border-slate-200 bg-white p-1 dark:border-slate-600"
                      value={accentColor && /^#[0-9A-Fa-f]{6}$/.test(accentColor) ? accentColor : '#6366f1'}
                      onChange={(e) => onAccentColorChange(e.target.value)}
                    />
                    <input
                      type="text"
                      className={`${FIELD} max-w-[9rem]`}
                      value={accentColor}
                      onChange={(e) => onAccentColorChange(e.target.value)}
                      placeholder="#6366f1"
                      aria-label="Accent hex"
                    />
                  </div>
                </div>
                <div>
                  <label className={LABEL} htmlFor="cv-bg">
                    Background image
                  </label>
                  <WPMediaPickerField
                    id="cv-bg"
                    value={featuredPreview}
                    onChange={onFeaturedPreviewChange}
                    onAttachmentIdChange={onFeaturedIdChange}
                    placeholder="Optional full-page background."
                    imageOnly
                  />
                </div>
              </div>
            ) : null}
          </div>
        </aside>

        <main className="min-w-0 flex-1 space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Canvas</h3>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Use <span className="font-medium text-slate-600 dark:text-slate-300">Backgrounds</span> for page options.
            </p>
          </div>
          <SortableContext items={blocks.map((b) => b.id)} strategy={verticalListSortingStrategy}>
            <CanvasDropArea
              hasBlocks={blocks.length > 0}
              orientation={orientation}
              accentColor={accentColor}
              featuredPreview={featuredPreview}
              onBackgroundClick={() => setSelectedId(null)}
            >
              {blocks.map((b) => (
                <SortableCanvasBlock
                  key={b.id}
                  block={b}
                  selected={selectedId === b.id}
                  onSelect={() => setSelectedId(b.id)}
                  onRemove={() => removeBlock(b.id)}
                />
              ))}
            </CanvasDropArea>
          </SortableContext>
        </main>

        <aside className="w-full shrink-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 xl:w-80">
          <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {selected ? 'Element settings' : 'Settings'}
          </h3>
          <div className="mt-4 max-h-[min(70vh,640px)] overflow-y-auto pr-1">
            <Inspector selected={selected} onUpdateBlock={updateBlock} onRemoveBlock={removeBlock} />
          </div>
        </aside>
      </div>

      <DragOverlay dropAnimation={null}>
        {activeDrag?.source === 'palette' && activeDrag.type ? (
          <div className="rounded-xl border border-brand-300 bg-white px-4 py-3 text-sm font-medium shadow-lg dark:bg-slate-800">
            Add {PALETTE_ITEMS.find((x) => x.type === activeDrag.type)?.label ?? activeDrag.type}
          </div>
        ) : null}
        {activeDrag?.source === 'sort' && activeDrag.block ? (
          <div className="rounded-lg border border-slate-200 bg-white/95 p-3 shadow-lg dark:border-slate-600 dark:bg-slate-900">
            <BlockCanvasPreview block={activeDrag.block} />
          </div>
        ) : null}
      </DragOverlay>
    </DndContext>
  );
}
