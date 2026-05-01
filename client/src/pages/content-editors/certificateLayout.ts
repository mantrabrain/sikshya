/**
 * Certificate visual builder: block model, defaults, and HTML export for post_content sync.
 * Blocks use a freeform frame: x, y, w, h, z in percent of the page (0–100).
 */

export const CERT_LAYOUT_VERSION = 2;

export type CertBlockType = 'heading' | 'text' | 'merge_field' | 'spacer' | 'divider' | 'image' | 'qr';

export type MergeFieldKey =
  | 'student_name'
  | 'course_name'
  | 'instructor_name'
  | 'completion_date'
  | 'completion_time'
  | 'duration'
  | 'points'
  | 'grade'
  | 'certificate_number'
  | 'verification_code'
  | 'site_name';

export type CertBlock = {
  id: string;
  type: CertBlockType;
  props: Record<string, unknown>;
};

export type CertLayoutFile = {
  version: number;
  blocks: CertBlock[];
};

export type PaletteItem = {
  type: CertBlockType;
  label: string;
  description: string;
  preset?: Record<string, unknown>;
};

export const MERGE_FIELD_KEYS: MergeFieldKey[] = [
  'student_name',
  'course_name',
  'instructor_name',
  'completion_date',
  'completion_time',
  'duration',
  'points',
  'grade',
  'certificate_number',
  'verification_code',
  'site_name',
];

const MERGE: Record<MergeFieldKey, { token: string; example: string }> = {
  student_name: { token: '{{student_name}}', example: 'Alex Student' },
  course_name: { token: '{{course_name}}', example: 'Intro to WordPress' },
  instructor_name: { token: '{{instructor_name}}', example: 'Taylor Instructor' },
  completion_date: { token: '{{completion_date}}', example: 'April 9, 2026' },
  completion_time: { token: '{{completion_time}}', example: '10:42 AM' },
  duration: { token: '{{duration}}', example: '3h 20m' },
  points: { token: '{{points}}', example: '92' },
  grade: { token: '{{grade}}', example: 'A' },
  certificate_number: { token: '{{certificate_number}}', example: 'SK-12-55-20260423' },
  verification_code: { token: '{{verification_code}}', example: 'ab12cd34ef56gh78' },
  site_name: { token: '{{site_name}}', example: 'My LMS' },
};

export const PALETTE_ITEMS: PaletteItem[] = [
  { type: 'text', label: 'Text', description: 'Paragraph or custom copy' },
  { type: 'merge_field', label: 'Course', description: 'Dynamic: course title', preset: { field: 'course_name' } },
  { type: 'merge_field', label: 'Student Name', description: 'Dynamic: learner name', preset: { field: 'student_name' } },
  { type: 'merge_field', label: 'Instructor', description: 'Dynamic: instructor name', preset: { field: 'instructor_name' } },
  { type: 'image', label: 'Signature', description: 'Upload a signature image', preset: { width: 180, align: 'center' } },
  { type: 'merge_field', label: 'Verification ID', description: 'Dynamic: verification code', preset: { field: 'verification_code', fontSize: 12, align: 'center' } },
  { type: 'qr', label: 'QR', description: 'Dynamic: QR to verification link', preset: { size: 140 } },
  { type: 'merge_field', label: 'Time', description: 'Dynamic: completion time', preset: { field: 'completion_time', fontSize: 12, align: 'center' } },
  { type: 'merge_field', label: 'Duration', description: 'Dynamic: learning duration', preset: { field: 'duration', fontSize: 12, align: 'center' } },
  { type: 'merge_field', label: 'Point', description: 'Dynamic: points earned', preset: { field: 'points', fontSize: 12, align: 'center' } },
  { type: 'merge_field', label: 'Grade', description: 'Dynamic: grade letter/value', preset: { field: 'grade', fontSize: 12, align: 'center' } },
  { type: 'heading', label: 'Heading', description: 'Large title text' },
  { type: 'divider', label: 'Divider', description: 'Horizontal line' },
  { type: 'spacer', label: 'Spacer', description: 'Vertical space' },
];

export type BlockFrame = { x: number; y: number; w: number; h: number; z: number };

export function newBlockId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `cb_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;
}

function clampP(n: unknown, min: number, max: number, fallback: number): number {
  const v = typeof n === 'number' ? n : Number(n);
  if (!Number.isFinite(v)) {
    return fallback;
  }
  return Math.min(max, Math.max(min, v));
}

export function defaultFrameForType(type: CertBlockType): BlockFrame {
  // Centered columns — not full-width — so new blocks read like a layout builder.
  switch (type) {
    case 'heading':
      // Headings are typically 1 line; keep the box tight so the border matches content.
      return { x: 12, y: 6, w: 76, h: 8, z: 1 };
    case 'text':
      // Default paragraph is short; avoid a tall box on drop.
      return { x: 14, y: 20, w: 72, h: 9, z: 1 };
    case 'merge_field':
      // Merge fields are usually a single line (name/course/date).
      return { x: 16, y: 34, w: 68, h: 7, z: 1 };
    case 'image':
      return { x: 38, y: 58, w: 24, h: 16, z: 2 };
    case 'qr':
      return { x: 76, y: 78, w: 16, h: 16, z: 2 };
    case 'divider':
      return { x: 18, y: 48, w: 64, h: 2.5, z: 1 };
    case 'spacer':
      return { x: 18, y: 42, w: 64, h: 3, z: 1 };
    default:
      return { x: 14, y: 8, w: 56, h: 10, z: 1 };
  }
}

/** Staggered drop position — two columns of compact blocks (not full width). */
export function nextDropFrame(blockIndex: number): BlockFrame {
  const col = blockIndex % 2;
  const row = Math.floor(blockIndex / 2);
  const x = col === 0 ? 10 : 52;
  const y = 8 + row * 14;
  return {
    x: clampP(x, 0, 92, 10),
    y: clampP(y, 0, 85, 8),
    w: 38,
    // Keep new blocks compact; users can resize taller when needed.
    h: 8,
    z: 1 + blockIndex,
  };
}

export function getBlockFrame(p: Record<string, unknown>, type: CertBlockType): BlockFrame {
  const d = defaultFrameForType(type);
  return {
    x: clampP(p.x, 0, 100, d.x),
    y: clampP(p.y, 0, 100, d.y),
    w: clampP(p.w, 5, 100, d.w),
    h: clampP(p.h, 2, 100, d.h),
    z: Math.floor(clampP(p.z, 0, 200, d.z)),
  };
}

/**
 * Real-world certificate page size in CSS (ISO 216 for A4/A5; US Letter 8.5×11 in).
 * Use this in the visual builder so the on-screen “sheet” matches print dimensions.
 * Percent-based block layout is still relative to this box.
 */
export function getCertificatePagePhysicalSize(
  orientation: 'landscape' | 'portrait',
  pageSize: 'letter' | 'a4' | 'a5'
): { width: string; height: string; aspectRatio: string; label: string } {
  if (pageSize === 'a4') {
    if (orientation === 'portrait') {
      return {
        width: '210mm',
        height: '297mm',
        aspectRatio: '210 / 297',
        label: 'A4 portrait (210×297mm)',
      };
    }
    return { width: '297mm', height: '210mm', aspectRatio: '297 / 210', label: 'A4 landscape (297×210mm)' };
  }
  if (pageSize === 'a5') {
    if (orientation === 'portrait') {
      return {
        width: '148mm',
        height: '210mm',
        aspectRatio: '148 / 210',
        label: 'A5 portrait (148×210mm)',
      };
    }
    return { width: '210mm', height: '148mm', aspectRatio: '210 / 148', label: 'A5 landscape (210×148mm)' };
  }
  if (orientation === 'portrait') {
    return {
      width: '8.5in',
      height: '11in',
      aspectRatio: '8.5 / 11',
      label: 'US Letter portrait (8.5×11in)',
    };
  }
  return { width: '11in', height: '8.5in', aspectRatio: '11 / 8.5', label: 'US Letter landscape (11×8.5in)' };
}

/**
 * Page aspect (CSS) for a given size + orientation — used for layout HTML and responsive preview.
 * Derived from the same paper dimensions as {@link getCertificatePagePhysicalSize}.
 */
export function getPageAspectCss(orientation: 'landscape' | 'portrait', pageSize: 'letter' | 'a4' | 'a5'): string {
  return getCertificatePagePhysicalSize(orientation, pageSize).aspectRatio;
}

/** Solid fills + pattern + art gradients for the certificate “page” (backdrops), editor + layout HTML. */
export type CertificatePageFinish = {
  pageColor: string;
  pagePattern: string;
  pageDeco: string;
};

export const DEFAULT_CERTIFICATE_PAGE_FINISH: CertificatePageFinish = {
  pageColor: '#ffffff',
  pagePattern: 'none',
  pageDeco: 'none',
};

export const CERT_PAGE_SWATCHES: readonly string[] = [
  '#ffffff',
  '#f8fafc',
  '#f1f5f9',
  '#e2e8f0',
  '#cbd5e1',
  '#fef3c7',
  '#fde68a',
  '#fbbf24',
  '#fecdd3',
  '#fda4af',
  '#e9d5ff',
  '#c4b5fd',
  '#7dd3fc',
  '#6ee7b7',
  '#94a3b8',
];

export const CERT_PAGE_PATTERN_ORDER = ['none', 'dots', 'lines', 'grid', 'diagonals', 'microDots', 'paperGrain'] as const;
export type CertPagePatternId = (typeof CERT_PAGE_PATTERN_ORDER)[number];

/**
 * Decorative full-page gradients (shown when no background photo).
 * Curated: certificate‑appropriate first, expressive options after.
 */
export const CERT_PAGE_DECO_ORDER = [
  'slate',
  'cream',
  'paperFolio',
  'corporateLetter',
  'formalBlueBand',
  'diplomaGold',
  'educationMint',
  'minimalFrame',
  'dawn',
  'sky',
  'rose',
  'forest',
  'sand',
  'gold',
  'mint',
  'coral',
  'sea',
  'plum',
  'aurora',
  'night',
  'dusk',
] as const;
export type CertPageDecoId = (typeof CERT_PAGE_DECO_ORDER)[number] | 'none';

/** Short labels for the Theme grid (avoid raw ids in the UI). */
export const CERT_PAGE_DECO_LABELS: Record<string, string> = {
  slate: 'Slate',
  cream: 'Cream',
  paperFolio: 'Ledger',
  corporateLetter: 'Corporate',
  formalBlueBand: 'Navy band',
  diplomaGold: 'Diploma gold',
  educationMint: 'Education',
  minimalFrame: 'Soft frame',
  dawn: 'Dawn',
  sky: 'Sky',
  rose: 'Rose',
  forest: 'Forest',
  sand: 'Sand',
  gold: 'Gold',
  mint: 'Mint',
  coral: 'Coral',
  sea: 'Sea',
  plum: 'Plum',
  aurora: 'Aurora',
  night: 'Night',
  dusk: 'Dusk',
};

/** First row in Theme → Background before “View all” (most useful for certificates). */
export const CERT_PAGE_DECO_SHOW_FIRST = 10;

/**
 * One-tap combinations of color + pattern + art (or plain) for real certificate workflows.
 * `clearFeaturedImage`: when true, clears the full-bleed photo so art/gradient is visible.
 */
export const CERTIFICATE_THEME_QUICK_PRESETS: readonly {
  id: string;
  label: string;
  caption: string;
  finish: CertificatePageFinish;
  clearFeaturedImage: boolean;
}[] = [
  {
    id: 'classic-print',
    label: 'Classic print',
    caption: 'White — signatures & seals stay sharp',
    finish: { pageColor: '#ffffff', pagePattern: 'none', pageDeco: 'none' },
    clearFeaturedImage: false,
  },
  {
    id: 'soft-paper',
    label: 'Soft paper',
    caption: 'Ivory + light dots — works with a banner photo',
    finish: { pageColor: '#fffdf7', pagePattern: 'dots', pageDeco: 'none' },
    clearFeaturedImage: false,
  },
  {
    id: 'lined-letter',
    label: 'Lined letter',
    caption: 'Cool gray lines — corporate certificates',
    finish: { pageColor: '#f8fafc', pagePattern: 'lines', pageDeco: 'none' },
    clearFeaturedImage: false,
  },
  {
    id: 'photo-hero',
    label: 'Photo hero',
    caption: 'Plain fill so your background image is the star',
    finish: { pageColor: '#f1f5f9', pagePattern: 'none', pageDeco: 'none' },
    clearFeaturedImage: false,
  },
  {
    id: 'formal-ledger',
    label: 'Formal ledger',
    caption: 'Warm ledger tone, subtle texture',
    finish: { pageColor: '#fffef8', pagePattern: 'grid', pageDeco: 'paperFolio' },
    clearFeaturedImage: true,
  },
  {
    id: 'corporate-pack',
    label: 'Corporate pack',
    caption: 'Letterhead-style wash (no photo)',
    finish: { pageColor: '#f1f5f9', pagePattern: 'none', pageDeco: 'corporateLetter' },
    clearFeaturedImage: true,
  },
  {
    id: 'navy-letterhead',
    label: 'Navy letterhead',
    caption: 'Top band — title + merge fields read well',
    finish: { pageColor: '#ffffff', pagePattern: 'none', pageDeco: 'formalBlueBand' },
    clearFeaturedImage: true,
  },
  {
    id: 'achievement',
    label: 'Achievement',
    caption: 'Gold cream — diplomas & awards',
    finish: { pageColor: '#fffbeb', pagePattern: 'none', pageDeco: 'diplomaGold' },
    clearFeaturedImage: true,
  },
  {
    id: 'education',
    label: 'Education',
    caption: 'Fresh mint wash — courses & training',
    finish: { pageColor: '#ffffff', pagePattern: 'dots', pageDeco: 'educationMint' },
    clearFeaturedImage: true,
  },
  {
    id: 'minimal-pro',
    label: 'Minimal',
    caption: 'Neutral frame — modern certificates',
    finish: { pageColor: '#ffffff', pagePattern: 'none', pageDeco: 'minimalFrame' },
    clearFeaturedImage: true,
  },
] as const;

function certSafeHex6(raw: string, fallback: string): string {
  const s = typeof raw === 'string' ? raw.trim() : '';
  return /^#[0-9A-Fa-f]{6}$/.test(s) ? s : fallback;
}

/** Public for builder (pattern swatch cells). */
export function getCertificatePagePatternLayer(patternId: string): { image: string; size: string } | null {
  if (!patternId || patternId === 'none') {
    return null;
  }
  if (patternId === 'dots') {
    return {
      image: 'radial-gradient(rgba(15,23,42,0.1) 1.2px, transparent 1.2px)',
      size: '14px 14px',
    };
  }
  if (patternId === 'microDots') {
    return {
      image: 'radial-gradient(rgba(15,23,42,0.075) 0.9px, transparent 0.9px)',
      size: '10px 10px',
    };
  }
  if (patternId === 'lines') {
    return {
      image: 'repeating-linear-gradient(0deg, transparent, transparent 8px, rgba(15,23,42,0.05) 8px, rgba(15,23,42,0.05) 9px)',
      size: '100% 100%',
    };
  }
  if (patternId === 'grid') {
    return {
      image: 'linear-gradient(rgba(15,23,42,0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(15,23,42,0.06) 1px, transparent 1px)',
      size: '20px 20px',
    };
  }
  if (patternId === 'diagonals') {
    return {
      image: 'repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(15,23,42,0.04) 5px, rgba(15,23,42,0.04) 6px)',
      size: 'auto',
    };
  }
  if (patternId === 'paperGrain') {
    // Deterministic “grain” using layered gradients (no external image, prints well).
    return {
      image:
        'radial-gradient(circle at 10% 20%, rgba(15,23,42,0.035) 0, transparent 45%),' +
        'radial-gradient(circle at 80% 0%, rgba(15,23,42,0.03) 0, transparent 40%),' +
        'radial-gradient(circle at 40% 90%, rgba(15,23,42,0.03) 0, transparent 42%)',
      size: '180px 180px',
    };
  }
  return null;
}

/** Public for builder thumbnails (grid of “Background” art presets). */
export function getCertificatePageDecoGradient(decoId: string): string | null {
  if (!decoId || decoId === 'none') {
    return null;
  }
  const g: Record<string, string> = {
    paperFolio:
      'radial-gradient(ellipse 110% 85% at 50% 0%, #fffef8 0%, #fffdf5 35%, #faf8f3 70%, #ffffff 100%)',
    corporateLetter:
      'linear-gradient(180deg, #e2e8f0 0%, #f8fafc 14%, #ffffff 52%, #f1f5f9 100%)',
    formalBlueBand:
      'linear-gradient(180deg, #1e3a5f 0%, #1e3a5f 9%, #f8fafc 9%, #ffffff 42%, #f8fafc 100%)',
    diplomaGold:
      'linear-gradient(165deg, #fffbeb 0%, #ffffff 38%, #fef3c7 78%, #fffbeb 100%)',
    educationMint:
      'linear-gradient(185deg, #ecfdf5 0%, #ffffff 50%, #f0fdf4 100%)',
    minimalFrame:
      'linear-gradient(90deg, #eef2f6 0%, #ffffff 10%, #ffffff 90%, #eef2f6 100%)',
    dawn: 'linear-gradient(160deg, #fff1f2 0%, #ffffff 50%, #e0e7ff 100%)',
    sky: 'linear-gradient(180deg, #bfdbfe 0%, #f0f9ff 45%, #f8fafc 100%)',
    cream: 'linear-gradient(180deg, #fff7ed 0%, #fffbeb 40%, #ffffff 100%)',
    rose: 'linear-gradient(135deg, #fce7f3 0%, #ffffff 55%, #fae8ff 100%)',
    forest: 'linear-gradient(160deg, #d1fae5 0%, #f0fdf4 100%)',
    sand: 'linear-gradient(200deg, #ffedd5 0%, #fff7ed 50%, #fffbeb 100%)',
    night: 'linear-gradient(210deg, #1e293b 0%, #334155 55%, #0f172a 100%)',
    gold: 'linear-gradient(135deg, #fffbeb 0%, #fef3c7 50%, #ffffff 100%)',
    mint: 'linear-gradient(180deg, #ccfbf1 0%, #f0fdfa 100%)',
    aurora: 'linear-gradient(200deg, #c7d2fe 0%, #e0e7ff 30%, #fce7f3 70%, #f0f9ff 100%)',
    plum: 'linear-gradient(165deg, #e9d5ff 0%, #faf5ff 50%, #f5f3ff 100%)',
    slate: 'linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%)',
    coral: 'linear-gradient(160deg, #ffe4e6 0%, #fff1f2 100%)',
    sea: 'linear-gradient(200deg, #a5f3fc 0%, #e0f2fe 50%, #f0f9ff 100%)',
    dusk: 'linear-gradient(195deg, #4c1d95 0%, #7c3aed 35%, #312e81 100%)',
  };
  return g[decoId] || null;
}

/**
 * Composes the certificate page background for the live canvas (React) and for saved HTML.
 * Layer order: subtle pattern (top) → full‑bleed image or art gradient → solid page color.
 */
export function getCertificatePageBackgroundStyle(opts: {
  pageColor: string;
  pagePattern: string;
  pageDeco: string;
  /** Featured image (full-bleed); when set, decorative gradient is not drawn. */
  featuredImageUrl: string;
}): { backgroundColor: string; backgroundImage: string; backgroundSize: string; backgroundRepeat: string; backgroundPosition: string } {
  const bgColor = certSafeHex6(opts.pageColor, '#ffffff');
  const p = getCertificatePagePatternLayer(String(opts.pagePattern));
  const img = String(opts.featuredImageUrl || '').trim();
  const deco = getCertificatePageDecoGradient(String(opts.pageDeco));
  const hasImg = img.length > 0;

  const images: string[] = [];
  const sizes: string[] = [];
  const reps: string[] = [];
  const pos: string[] = [];

  if (p) {
    images.push(p.image);
    sizes.push(p.size);
    reps.push('repeat');
    pos.push('0 0');
  }

  if (hasImg) {
    const safe = String(img)
      .replace(/\\/g, '\\\\')
      .replace(/'/g, "\\'");
    images.push(`url('${safe}')`);
    sizes.push('cover');
    reps.push('no-repeat');
    pos.push('center center');
  } else if (deco) {
    images.push(deco);
    sizes.push('100% 100%');
    reps.push('no-repeat');
    pos.push('center center');
  }

  return {
    backgroundColor: bgColor,
    backgroundImage: images.length ? images.join(', ') : 'none',
    backgroundSize: images.length ? sizes.join(', ') : 'auto',
    backgroundRepeat: images.length ? reps.join(', ') : 'no-repeat',
    backgroundPosition: images.length ? pos.join(', ') : '0 0',
  };
}

/**
 * Value persisted in post meta after {@see PostTypeManager} sanitize_callback
 * (sanitize_key + whitelist). Used when comparing REST save responses to avoid false
 * "could not save" toasts — UI uses camelCase ids; DB stores lowercase slugs.
 */
export function certificatePagePatternStoredValue(uiOrStored: string): string {
  const raw = String(uiOrStored ?? 'none').trim();
  // Mirrors WP `sanitize_key()` for ASCII ids: lowercase, strip non [a-z0-9_-].
  const s = raw.toLowerCase().replace(/[^a-z0-9_-]/g, '');
  const allowed = ['none', 'dots', 'microdots', 'lines', 'grid', 'diagonals', 'papergrain'] as const;
  return (allowed as readonly string[]).includes(s) ? s : 'none';
}

function normalizePagePattern(id: string): string {
  const s = String(id || 'none');
  // Back-compat: PHP sanitizer uses sanitize_key() which lowercases pattern ids.
  // Normalize to the canonical ids used by the UI.
  const canonical =
    s === 'microdots' ? 'microDots' : s === 'papergrain' ? 'paperGrain' : s;
  if (CERT_PAGE_PATTERN_ORDER.includes(canonical as CertPagePatternId)) {
    return canonical;
  }
  return 'none';
}

function normalizePageDeco(id: string): string {
  const s = String(id || 'none');
  if (s === 'none') {
    return 'none';
  }
  if (CERT_PAGE_DECO_ORDER.includes(s as CertPageDecoId)) {
    return s;
  }
  return 'none';
}

/** Whitelist and defaults for page finish loaded from post meta. */
export function parseCertificatePageFinish(
  colorRaw: unknown,
  patternRaw: unknown,
  decoRaw: unknown
): CertificatePageFinish {
  const c = String(colorRaw || '').trim();
  const col = c && /^#[0-9A-Fa-f]{6}$/i.test(c) ? c.toLowerCase() : DEFAULT_CERTIFICATE_PAGE_FINISH.pageColor;
  return {
    pageColor: col,
    pagePattern: normalizePagePattern(String(patternRaw)),
    pageDeco: normalizePageDeco(String(decoRaw)),
  };
}

export function migrateLayoutBlocks(blocks: CertBlock[]): CertBlock[] {
  return blocks.map((b, i) => {
    const p = b.props;
    if (typeof p.x === 'number' && typeof p.y === 'number' && typeof p.w === 'number' && typeof p.h === 'number') {
      return b;
    }
    const hExtra = b.type === 'heading' ? 4 : b.type === 'image' || b.type === 'text' || b.type === 'merge_field' ? 2 : 0;
    return {
      ...b,
      props: {
        ...p,
        x: 10 + (i % 2) * 42,
        y: Math.min(78, 6 + Math.floor(i / 2) * 14),
        w: 38,
        h: Math.max(4, 8 + hExtra + (b.type === 'spacer' ? 1 : 0) + (b.type === 'divider' ? 0.5 : 0)),
        z: 1 + i,
      },
    };
  });
}

export function normalizeLayoutFile(file: CertLayoutFile): CertLayoutFile {
  const v = file.version;
  const blocks = migrateLayoutBlocks(Array.isArray(file.blocks) ? file.blocks : []);
  return { version: v >= CERT_LAYOUT_VERSION ? v : CERT_LAYOUT_VERSION, blocks };
}

export function createBlock(type: CertBlockType, preset?: Record<string, unknown>): CertBlock {
  const id = newBlockId();
  const f = defaultFrameForType(type);
  const baseFrame = { x: f.x, y: f.y, w: f.w, h: f.h, z: f.z };
  switch (type) {
    case 'heading':
      return {
        id,
        type,
        props: {
          ...baseFrame,
          text: 'Certificate of Completion',
          tag: 'h1',
          align: 'center',
          fontSize: 28,
          color: '#0f172a',
          fontWeight: '700',
          ...(preset || {}),
        },
      };
    case 'text':
      return {
        id,
        type,
        props: {
          ...baseFrame,
          text: 'This certifies that the named learner has completed the course requirements.',
          align: 'center',
          fontSize: 14,
          color: '#334155',
          ...(preset || {}),
        },
      };
    case 'merge_field':
      return {
        id,
        type,
        props: {
          ...baseFrame,
          field: 'student_name' as MergeFieldKey,
          fontSize: 22,
          align: 'center',
          color: '#0f172a',
          ...(preset || {}),
        },
      };
    case 'spacer':
      return {
        id,
        type,
        props: { ...defaultFrameForType('spacer'), height: 24, ...(preset || {}) },
      };
    case 'divider':
      return { id, type, props: { ...defaultFrameForType('divider'), color: '#cbd5e1', thickness: 2, ...(preset || {}) } };
    case 'image':
      return { id, type, props: { ...defaultFrameForType('image'), src: '', width: 120, align: 'center', ...(preset || {}) } };
    case 'qr': {
      const size = Math.min(260, Math.max(80, Number((preset || {}).size) || 140));
      return {
        id,
        type,
        props: { ...defaultFrameForType('qr'), size, ...(preset || {}) },
      };
    }
    default:
      return { id, type: 'text', props: { ...baseFrame, text: '', align: 'left', fontSize: 14, color: '#334155' } };
  }
}

export function defaultCertificateLayout(): CertLayoutFile {
  // Polished starter template (works well with default Theme values).
  const eyebrow = createBlock('text', { text: 'CERTIFICATE OF ACHIEVEMENT', fontSize: 11, align: 'center', color: '#64748b', fontWeight: '600' });
  const heading = createBlock('heading', { text: 'Certificate of Excellence', tag: 'h1', align: 'center', fontSize: 34, color: '#0f172a', fontWeight: '700' });
  const presented = createBlock('text', { text: 'is proudly awarded to', fontSize: 12, align: 'center', color: '#64748b' });
  const student = createBlock('merge_field', { field: 'student_name', fontSize: 28, align: 'center', color: '#0f172a', fontWeight: '600', fontFamily: 'serif' });
  const forLine = createBlock('text', { text: 'for the successful completion of', fontSize: 12, align: 'center', color: '#64748b' });
  const course = createBlock('merge_field', { field: 'course_name', fontSize: 18, align: 'center', color: '#1e3a5f', fontWeight: '600' });
  const body = createBlock('text', { text: 'Awarded in recognition of dedication, effort, and academic excellence.', fontSize: 12, align: 'center', color: '#64748b' });
  const divider = createBlock('divider', { thickness: 2, color: '#e2e8f0' });
  const qr = createBlock('qr', { size: 120 });
  const verifyLabel = createBlock('text', { text: 'Verification ID', fontSize: 10, align: 'left', color: '#64748b', fontWeight: '600' });
  const verifyCode = createBlock('merge_field', { field: 'verification_code', fontSize: 12, align: 'left', color: '#0f172a', fontWeight: '500', fontFamily: 'mono' });
  const dateLabel = createBlock('text', { text: 'DATE', fontSize: 9, align: 'center', color: '#64748b', fontWeight: '600' });
  const date = createBlock('merge_field', { field: 'completion_date', fontSize: 12, align: 'center', color: '#0f172a', fontWeight: '500' });
  const instLabel = createBlock('text', { text: 'INSTRUCTOR', fontSize: 9, align: 'center', color: '#64748b', fontWeight: '600' });
  const instructor = createBlock('merge_field', { field: 'instructor_name', fontSize: 12, align: 'center', color: '#0f172a', fontWeight: '500' });

  return {
    version: CERT_LAYOUT_VERSION,
    blocks: [
      { ...eyebrow, props: { ...eyebrow.props, x: 10, y: 10, w: 80, h: 3, z: 1 } },
      { ...heading, props: { ...heading.props, x: 8, y: 14, w: 84, h: 7, z: 2 } },
      { ...divider, props: { ...divider.props, x: 40, y: 22, w: 20, h: 2, z: 1 } },
      { ...presented, props: { ...presented.props, x: 10, y: 28, w: 80, h: 3, z: 1 } },
      { ...student, props: { ...student.props, x: 8, y: 33, w: 84, h: 7, z: 2 } },
      { ...forLine, props: { ...forLine.props, x: 12, y: 42, w: 76, h: 3, z: 1 } },
      { ...course, props: { ...course.props, x: 10, y: 47, w: 80, h: 6, z: 2 } },
      { ...body, props: { ...body.props, x: 12, y: 56, w: 76, h: 5, z: 1 } },
      { ...qr, props: { ...qr.props, x: 10, y: 79.5, w: 20, h: 12, z: 3 } },
      { ...verifyLabel, props: { ...verifyLabel.props, x: 32, y: 80.5, w: 58, h: 3, z: 2 } },
      { ...verifyCode, props: { ...verifyCode.props, x: 32, y: 84, w: 58, h: 4, z: 2 } },
      { ...instLabel, props: { ...instLabel.props, x: 10, y: 92.5, w: 35, h: 3, z: 1 } },
      { ...instructor, props: { ...instructor.props, x: 10, y: 95.5, w: 35, h: 4, z: 2 } },
      { ...dateLabel, props: { ...dateLabel.props, x: 55, y: 92.5, w: 35, h: 3, z: 1 } },
      { ...date, props: { ...date.props, x: 55, y: 95.5, w: 35, h: 4, z: 2 } },
    ],
  };
}

export function parseLayoutFromMeta(raw: unknown): CertLayoutFile {
  if (raw === null || raw === undefined || raw === '') {
    return defaultCertificateLayout();
  }
  let parsed: unknown = raw;
  if (typeof raw === 'string') {
    try {
      parsed = JSON.parse(raw) as unknown;
    } catch {
      return defaultCertificateLayout();
    }
  }
  if (!parsed || typeof parsed !== 'object') {
    return defaultCertificateLayout();
  }
  const o = parsed as Record<string, unknown>;
  const version = typeof o.version === 'number' ? o.version : 1;
  const blocksRaw = o.blocks;
  if (!Array.isArray(blocksRaw)) {
    return defaultCertificateLayout();
  }
  const blocks: CertBlock[] = [];
  for (const b of blocksRaw) {
    if (!b || typeof b !== 'object') {
      continue;
    }
    const row = b as Record<string, unknown>;
    const type = row.type as CertBlockType;
    const id = typeof row.id === 'string' ? row.id : newBlockId();
    const props = row.props && typeof row.props === 'object' ? { ...(row.props as Record<string, unknown>) } : {};
    if (!type || !['heading', 'text', 'merge_field', 'spacer', 'divider', 'image', 'qr'].includes(type)) {
      continue;
    }
    blocks.push({ id, type, props });
  }
  if (blocks.length > 250) {
    blocks.splice(250);
  }
  if (blocks.length === 0) {
    return defaultCertificateLayout();
  }
  return normalizeLayoutFile({ version, blocks });
}

export function layoutToStorage(layout: CertLayoutFile): string {
  const safeBlocks = Array.isArray(layout.blocks) ? layout.blocks.slice(0, 250) : [];
  return JSON.stringify({ version: Math.max(CERT_LAYOUT_VERSION, layout.version || 1), blocks: safeBlocks });
}

function escAttr(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function buildCertificateRootBackgroundForHtml(opts: {
  pageColor: string;
  pagePattern: string;
  pageDeco: string;
  featuredImageUrl: string;
}): string {
  const s = getCertificatePageBackgroundStyle(opts);
  const frags: string[] = [
    `background-color:${escAttr(s.backgroundColor)}`,
    `background-image:${escAttr(s.backgroundImage)}`,
    `background-size:${escAttr(s.backgroundSize)}`,
    `background-repeat:${escAttr(s.backgroundRepeat)}`,
    `background-position:${escAttr(s.backgroundPosition)}`,
  ];
  return frags.join(';');
}

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/** Allow only safe hex colors in inline CSS (blocks `url()`, `expression()`, etc.). */
function sanitizeCssColor(input: unknown, fallback: string): string {
  const s = String(input ?? '').trim();
  return /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/.test(s) ? s : fallback;
}

function sanitizeCssFontWeight(input: unknown): string {
  const s = String(input ?? '600');
  return ['400', '500', '600', '700', 'normal', 'bold'].includes(s) ? s : '600';
}

type CertFontFamilyId = 'sans' | 'serif' | 'mono';

function resolveFontFamilyStack(id: unknown): string {
  const s = String(id ?? 'sans');
  if (s === 'serif') {
    return 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif';
  }
  if (s === 'mono') {
    return 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace';
  }
  return 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif';
}

function sanitizeFontFamilyId(input: unknown, fallback: CertFontFamilyId): CertFontFamilyId {
  const s = String(input ?? '').trim();
  if (s === 'serif' || s === 'mono' || s === 'sans') return s;
  return fallback;
}

function sanitizeCssLineHeight(input: unknown, fallback: number): number {
  const n = typeof input === 'number' ? input : Number(input);
  if (!Number.isFinite(n)) return fallback;
  return Math.min(2.4, Math.max(1, n));
}

function sanitizeCssLetterSpacing(input: unknown, fallback: number): number {
  const n = typeof input === 'number' ? input : Number(input);
  if (!Number.isFinite(n)) return fallback;
  return Math.min(0.5, Math.max(-0.05, n));
}

/** Aspect ratio value for CSS `aspect-ratio` — digits, `.`, `/`, and spaces only. */
function sanitizeAspectRatioCss(input: string): string {
  const s = String(input || '')
    .trim()
    .replace(/[^\d./\s]/g, '')
    .trim();
  return s || '11 / 8.5';
}

/** Merge tokens → example text for preview only. */
export function substituteMergePreview(html: string): string {
  let out = html;
  for (const row of Object.values(MERGE)) {
    out = out.split(row.token).join(row.example);
  }
  return out;
}

export function mergeFieldToken(field: MergeFieldKey): string {
  return MERGE[field]?.token ?? '';
}

export function mergeFieldLabel(field: MergeFieldKey): string {
  const labels: Record<MergeFieldKey, string> = {
    student_name: 'Student name',
    course_name: 'Course name',
    instructor_name: 'Instructor',
    completion_date: 'Completion date',
    completion_time: 'Completion time',
    duration: 'Duration',
    points: 'Point',
    grade: 'Grade',
    certificate_number: 'Certificate #',
    verification_code: 'Verification ID',
    site_name: 'Site name',
  };
  return labels[field] ?? field;
}

export type LayoutToHtmlOptions = {
  /** e.g. `11 / 8.5` for letter landscape. */
  pageAspect?: string;
  pageColor?: string;
  pagePattern?: string;
  pageDeco?: string;
  /** WordPress featured (full-bleed) page background image URL, if any. */
  pageFeaturedImageUrl?: string;
};

function blockInnerToHtml(b: CertBlock): string {
  const p = b.props;
  switch (b.type) {
    case 'heading': {
      const tag = ['h1', 'h2', 'h3'].includes(String(p.tag)) ? String(p.tag) : 'h1';
      const text = typeof p.text === 'string' ? escapeHtml(p.text) : '';
      const align = ['left', 'center', 'right'].includes(String(p.align)) ? String(p.align) : 'center';
      const fs = Math.min(96, Math.max(10, Number(p.fontSize) || 24));
      const color = sanitizeCssColor(p.color, '#0f172a');
      const fw = sanitizeCssFontWeight(p.fontWeight === 'bold' || p.fontWeight === '700' ? '700' : p.fontWeight);
      const ff = resolveFontFamilyStack(sanitizeFontFamilyId(p.fontFamily, 'serif'));
      const lh = sanitizeCssLineHeight(p.lineHeight, 1.12);
      const ls = sanitizeCssLetterSpacing(p.letterSpacing, 0);
      return `<${tag} style="margin:0;max-height:100%;overflow:hidden;text-align:${escAttr(
        align
      )};font-size:${fs}px;color:${escAttr(color)};font-weight:${escAttr(fw)};font-family:${escAttr(
        ff
      )};line-height:${lh};letter-spacing:${ls}em;">${text}</${tag}>`;
    }
    case 'text': {
      const raw = typeof p.text === 'string' ? p.text : '';
      const text = raw
        .split('\n')
        .map((line) => escapeHtml(line))
        .join('<br />');
      const align = ['left', 'center', 'right'].includes(String(p.align)) ? String(p.align) : 'left';
      const fs = Math.min(48, Math.max(10, Number(p.fontSize) || 14));
      const color = sanitizeCssColor(p.color, '#334155');
      const fw = sanitizeCssFontWeight(p.fontWeight ?? '400');
      const ff = resolveFontFamilyStack(sanitizeFontFamilyId(p.fontFamily, 'sans'));
      const lh = sanitizeCssLineHeight(p.lineHeight, 1.5);
      const ls = sanitizeCssLetterSpacing(p.letterSpacing, 0);
      return `<p style="margin:0;max-height:100%;overflow:hidden;text-align:${escAttr(
        align
      )};font-size:${fs}px;color:${escAttr(color)};font-weight:${escAttr(fw)};font-family:${escAttr(
        ff
      )};line-height:${lh};letter-spacing:${ls}em;">${text}</p>`;
    }
    case 'merge_field': {
      const field = (String(p.field) as MergeFieldKey) in MERGE ? (String(p.field) as MergeFieldKey) : 'student_name';
      const token = mergeFieldToken(field);
      const tokenHtml = escapeHtml(token);
      const align = ['left', 'center', 'right'].includes(String(p.align)) ? String(p.align) : 'center';
      const fs = Math.min(72, Math.max(10, Number(p.fontSize) || 18));
      const color = sanitizeCssColor(p.color, '#0f172a');
      const fw = sanitizeCssFontWeight(p.fontWeight ?? '600');
      const ff = resolveFontFamilyStack(sanitizeFontFamilyId(p.fontFamily, 'sans'));
      const lh = sanitizeCssLineHeight(p.lineHeight, 1.2);
      const ls = sanitizeCssLetterSpacing(p.letterSpacing, 0);
      return `<div class="sikshya-cert-merge" data-field="${escAttr(
        field
      )}" style="max-height:100%;overflow:hidden;text-align:${escAttr(
        align
      )};font-size:${fs}px;color:${escAttr(color)};font-weight:${escAttr(fw)};font-family:${escAttr(
        ff
      )};line-height:${lh};letter-spacing:${ls}em;">${tokenHtml}</div>`;
    }
    case 'spacer': {
      return '<div style="height:100%;" aria-hidden="true"></div>';
    }
    case 'divider': {
      const color = sanitizeCssColor(p.color, '#cbd5e1');
      const t = Math.min(20, Math.max(1, Number(p.thickness) || 2));
      return `<div style="display:flex;align-items:center;height:100%;width:100%;"><hr style="border:none;border-top:${t}px solid ${escAttr(
        color
      )};margin:0;width:100%;" /></div>`;
    }
    case 'image': {
      const src = typeof p.src === 'string' ? p.src.trim() : '';
      if (!src) {
        return '';
      }
      return `<div style="display:flex;align-items:center;justify-content:center;height:100%;width:100%;"><img src="${escAttr(
        src
      )}" alt="" style="max-width:100%;max-height:100%;object-fit:contain;" /></div>`;
    }
    case 'qr': {
      // This token is replaced server-side (Pro) with the actual QR <img> based on the verification URL.
      // Keep it deterministic (no user input), so it's safe to embed without HTML escaping.
      return `<div class="sikshya-cert-qr" style="display:flex;align-items:center;justify-content:center;height:100%;width:100%;">{{qr_image}}</div>`;
    }
    default:
      return '';
  }
}

/**
 * Serialize layout to safe HTML (absolute, percentage-based) for post_content and preview.
 */
export function layoutToHtml(layout: CertLayoutFile, options?: LayoutToHtmlOptions): string {
  const aspect = sanitizeAspectRatioCss(String(options?.pageAspect || '11 / 8.5'));
  const bg = buildCertificateRootBackgroundForHtml({
    pageColor: sanitizeCssColor(
      (options?.pageColor || DEFAULT_CERTIFICATE_PAGE_FINISH.pageColor).trim(),
      DEFAULT_CERTIFICATE_PAGE_FINISH.pageColor
    ),
    pagePattern: String(options?.pagePattern || 'none') || 'none',
    pageDeco: String(options?.pageDeco || 'none') || 'none',
    featuredImageUrl: String(options?.pageFeaturedImageUrl || '').trim(),
  });
  const parts: string[] = [];
  for (const b of layout.blocks) {
    const f = getBlockFrame(b.props, b.type);
    const inner = blockInnerToHtml(b);
    if (inner === '') {
      continue;
    }
    parts.push(
      `<div class="sikshya-cb" data-type="${escAttr(
        b.type
      )}" style="position:absolute;left:${f.x}%;top:${f.y}%;width:${f.w}%;height:${
        f.h
      }%;z-index:${f.z};overflow:hidden;box-sizing:border-box;">${inner}</div>`
    );
  }
  return `<div class="sikshya-certificate-layout" data-version="2" style="position:relative;width:100%;max-width:100%;margin:0 auto;aspect-ratio:${escAttr(
    aspect
  )};${bg}">${parts.join('\n')}</div>`;
}
