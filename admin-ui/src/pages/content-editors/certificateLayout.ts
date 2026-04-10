/**
 * Certificate visual builder: block model, defaults, and HTML export for post_content sync.
 */

export type CertBlockType = 'heading' | 'text' | 'merge_field' | 'spacer' | 'divider' | 'image';

export type MergeFieldKey = 'student_name' | 'course_name' | 'completion_date' | 'certificate_id' | 'site_name';

export type CertBlock = {
  id: string;
  type: CertBlockType;
  props: Record<string, unknown>;
};

export type CertLayoutFile = {
  version: number;
  blocks: CertBlock[];
};

const MERGE: Record<MergeFieldKey, { token: string; example: string }> = {
  student_name: { token: '{{student_name}}', example: 'Alex Student' },
  course_name: { token: '{{course_name}}', example: 'Intro to WordPress' },
  completion_date: { token: '{{completion_date}}', example: 'April 9, 2026' },
  certificate_id: { token: '{{certificate_id}}', example: 'CERT-1024' },
  site_name: { token: '{{site_name}}', example: 'My LMS' },
};

export const PALETTE_ITEMS: { type: CertBlockType; label: string; description: string }[] = [
  { type: 'heading', label: 'Heading', description: 'Large title text' },
  { type: 'text', label: 'Text', description: 'Paragraph or custom copy' },
  { type: 'merge_field', label: 'Dynamic field', description: 'Student, course, date…' },
  { type: 'image', label: 'Image', description: 'Logo or badge URL' },
  { type: 'spacer', label: 'Spacer', description: 'Vertical space' },
  { type: 'divider', label: 'Divider', description: 'Horizontal line' },
];

export function newBlockId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `cb_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;
}

export function createBlock(type: CertBlockType): CertBlock {
  const id = newBlockId();
  switch (type) {
    case 'heading':
      return {
        id,
        type,
        props: { text: 'Certificate of Completion', tag: 'h1', align: 'center', fontSize: 28, color: '#0f172a', fontWeight: '700' },
      };
    case 'text':
      return {
        id,
        type,
        props: {
          text: 'This certifies that the named learner has completed the course requirements.',
          align: 'center',
          fontSize: 14,
          color: '#334155',
        },
      };
    case 'merge_field':
      return { id, type, props: { field: 'student_name' as MergeFieldKey, fontSize: 22, align: 'center', color: '#0f172a' } };
    case 'spacer':
      return { id, type, props: { height: 24 } };
    case 'divider':
      return { id, type, props: { color: '#cbd5e1', thickness: 2 } };
    case 'image':
      return { id, type, props: { src: '', width: 120, align: 'center' } };
    default:
      return { id, type: 'text', props: { text: '', align: 'left', fontSize: 14, color: '#334155' } };
  }
}

export function defaultCertificateLayout(): CertLayoutFile {
  return {
    version: 1,
    blocks: [
      createBlock('heading'),
      createBlock('spacer'),
      createBlock('merge_field'),
      createBlock('spacer'),
      createBlock('text'),
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
    if (!type || !['heading', 'text', 'merge_field', 'spacer', 'divider', 'image'].includes(type)) {
      continue;
    }
    blocks.push({ id, type, props });
  }
  if (blocks.length === 0) {
    return defaultCertificateLayout();
  }
  return { version: 1, blocks };
}

export function layoutToStorage(layout: CertLayoutFile): string {
  return JSON.stringify({ version: layout.version, blocks: layout.blocks });
}

function escAttr(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
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
    completion_date: 'Completion date',
    certificate_id: 'Certificate ID',
    site_name: 'Site name',
  };
  return labels[field] ?? field;
}

/** Serialize layout to safe HTML for post_content (issuance / PDF pipelines). */
export function layoutToHtml(layout: CertLayoutFile): string {
  const parts: string[] = [];
  for (const b of layout.blocks) {
    const p = b.props;
    switch (b.type) {
      case 'heading': {
        const tag = ['h1', 'h2', 'h3'].includes(String(p.tag)) ? String(p.tag) : 'h1';
        const text = typeof p.text === 'string' ? escapeHtml(p.text) : '';
        const align = ['left', 'center', 'right'].includes(String(p.align)) ? String(p.align) : 'center';
        const fs = Math.min(96, Math.max(10, Number(p.fontSize) || 24));
        const color = typeof p.color === 'string' ? p.color : '#0f172a';
        const fw = p.fontWeight === 'bold' || p.fontWeight === '700' ? '700' : String(p.fontWeight || '600');
        parts.push(
          `<${tag} style="text-align:${escAttr(align)};font-size:${fs}px;color:${escAttr(color)};font-weight:${escAttr(fw)};margin:0.35em 0;">${text}</${tag}>`
        );
        break;
      }
      case 'text': {
        const raw = typeof p.text === 'string' ? p.text : '';
        const text = raw
          .split('\n')
          .map((line) => escapeHtml(line))
          .join('<br />');
        const align = ['left', 'center', 'right'].includes(String(p.align)) ? String(p.align) : 'left';
        const fs = Math.min(48, Math.max(10, Number(p.fontSize) || 14));
        const color = typeof p.color === 'string' ? p.color : '#334155';
        parts.push(
          `<p style="text-align:${escAttr(align)};font-size:${fs}px;color:${escAttr(color)};margin:0.5em 0;line-height:1.5;">${text}</p>`
        );
        break;
      }
      case 'merge_field': {
        const field = (String(p.field) as MergeFieldKey) in MERGE ? (String(p.field) as MergeFieldKey) : 'student_name';
        const token = mergeFieldToken(field);
        const align = ['left', 'center', 'right'].includes(String(p.align)) ? String(p.align) : 'center';
        const fs = Math.min(72, Math.max(10, Number(p.fontSize) || 18));
        const color = typeof p.color === 'string' ? p.color : '#0f172a';
        parts.push(
          `<div class="sikshya-cert-merge" data-field="${escAttr(field)}" style="text-align:${escAttr(align)};font-size:${fs}px;color:${escAttr(color)};font-weight:600;margin:0.35em 0;">${token}</div>`
        );
        break;
      }
      case 'spacer': {
        const h = Math.min(400, Math.max(0, Number(p.height) || 16));
        parts.push(`<div style="height:${h}px" aria-hidden="true"></div>`);
        break;
      }
      case 'divider': {
        const color = typeof p.color === 'string' ? p.color : '#cbd5e1';
        const t = Math.min(20, Math.max(1, Number(p.thickness) || 2));
        parts.push(
          `<hr style="border:none;border-top:${t}px solid ${escAttr(color)};margin:1em 0;width:100%;" />`
        );
        break;
      }
      case 'image': {
        const src = typeof p.src === 'string' ? p.src.trim() : '';
        if (!src) {
          break;
        }
        const w = Math.min(800, Math.max(20, Number(p.width) || 120));
        const align = ['left', 'center', 'right'].includes(String(p.align)) ? String(p.align) : 'center';
        const jc = align === 'left' ? 'flex-start' : align === 'right' ? 'flex-end' : 'center';
        parts.push(
          `<div style="display:flex;justify-content:${jc};margin:0.5em 0;"><img src="${escAttr(src)}" alt="" style="max-width:100%;width:${w}px;height:auto;" /></div>`
        );
        break;
      }
      default:
        break;
    }
  }
  return `<div class="sikshya-certificate-layout" data-version="1">${parts.join('\n')}</div>`;
}
