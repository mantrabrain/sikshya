import { useMemo, useState } from 'react';
import { __, sprintf } from '../../lib/i18n';

export type DynamicCheckoutFieldType =
  | 'text'
  | 'textarea'
  | 'select'
  | 'country'
  | 'checkbox'
  | 'radio'
  | 'number'
  | 'email'
  | 'tel';

export type DynamicCheckoutField = {
  id: string;
  label: string;
  type: DynamicCheckoutFieldType;
  placeholder?: string;
  enabled?: boolean;
  width?: 'full' | 'half';
  required?: boolean;
  help?: string;
  options?: Array<{ value: string; label: string }>;
  visibility?: {
    depends_on?: string;
    depends_value?: string;
    depends_in?: string[];
  };
  persist_to_user?: boolean;
  system?: boolean;
  locked?: boolean;
};

function safeJsonParseArray(raw: unknown): DynamicCheckoutField[] {
  if (typeof raw !== 'string') return [];
  try {
    const v = JSON.parse(raw);
    return Array.isArray(v) ? (v as DynamicCheckoutField[]) : [];
  } catch {
    return [];
  }
}

function toSchemaString(schema: DynamicCheckoutField[]): string {
  return JSON.stringify(schema, null, 2);
}

function slugifyId(input: string): string {
  return input
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9_]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 64);
}

function fieldTypeLabel(type: DynamicCheckoutFieldType): string {
  switch (type) {
    case 'text':
      return __('Text', 'sikshya');
    case 'textarea':
      return __('Textarea', 'sikshya');
    case 'email':
      return __('Email', 'sikshya');
    case 'tel':
      return __('Phone', 'sikshya');
    case 'number':
      return __('Number', 'sikshya');
    case 'select':
      return __('Select', 'sikshya');
    case 'country':
      return __('Country', 'sikshya');
    case 'radio':
      return __('Radio', 'sikshya');
    case 'checkbox':
      return __('Checkbox', 'sikshya');
    default:
      return type;
  }
}

export function DynamicFieldsBuilder(props: {
  value: unknown;
  onChange: (next: string) => void;
  readOnly?: boolean;
}) {
  const { value, onChange, readOnly } = props;
  const schema = useMemo(() => safeJsonParseArray(value), [value]);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [rawError, setRawError] = useState<string>('');
  const [showAdd, setShowAdd] = useState(false);
  const [draggedId, setDraggedId] = useState<string | null>(null);
  const [dragOverId, setDragOverId] = useState<string | null>(null);

  const [newField, setNewField] = useState<DynamicCheckoutField>({
    id: '',
    label: '',
    type: 'text',
    placeholder: '',
    enabled: true,
    width: 'full',
    required: false,
    help: '',
    options: [],
    persist_to_user: true,
  });
  const [idTouched, setIdTouched] = useState(false);

  const update = (nextSchema: DynamicCheckoutField[]) => {
    setRawError('');
    onChange(toSchemaString(nextSchema));
  };

  const ids = new Set<string>();
  for (const f of schema) {
    if (f && typeof f.id === 'string' && f.id) ids.add(f.id);
  }

  const validate = (nextSchema: DynamicCheckoutField[]) => {
    const nextIds = new Set<string>();
    for (const f of nextSchema) {
      const id = slugifyId(f?.id || '');
      if (!id) return __('Each field must have an id.', 'sikshya');
      if (nextIds.has(id)) return sprintf(__('Duplicate field id "%s".', 'sikshya'), id);
      nextIds.add(id);
      if ((f.type === 'select' || f.type === 'radio') && (!f.options || f.options.length < 1)) {
        return sprintf(__('Field "%s" needs at least one option.', 'sikshya'), id);
      }
    }
    return '';
  };

  const addField = () => {
    const rawId = (newField.id || '').trim() !== '' ? newField.id : newField.label;
    const id = slugifyId(rawId);
    if (!id || !newField.label) return;
    if (ids.has(id)) {
      setRawError(sprintf(__('Duplicate field id "%s".', 'sikshya'), id));
      return;
    }

    const nextField: DynamicCheckoutField = {
      id,
      label: newField.label,
      type: newField.type,
      placeholder: newField.placeholder || '',
      enabled: newField.enabled !== false,
      width: newField.width || 'full',
      required: !!newField.required,
      help: newField.help || '',
      options:
        newField.type === 'select' || newField.type === 'radio'
          ? (newField.options || []).filter((o) => o.value && o.label)
          : [],
      visibility: newField.visibility,
      persist_to_user: newField.persist_to_user !== false,
    };

    const next: DynamicCheckoutField[] = [...schema, nextField];
    const err = validate(next);
    if (err) {
      setRawError(err);
      return;
    }
    update(next);
    setActiveId(id);
    setShowAdd(false);
    setNewField({
      id: '',
      label: '',
      type: 'text',
      placeholder: '',
      enabled: true,
      width: 'full',
      required: false,
      help: '',
      options: [],
      persist_to_user: true,
    });
    setIdTouched(false);
  };

  const removeField = (id: string) => {
    const next = schema.filter((f) => slugifyId(f.id) !== slugifyId(id));
    update(next);
    if (activeId === id) setActiveId(null);
  };

  const moveByDrag = (dragId: string, dropId: string) => {
    const a = schema.findIndex((f) => slugifyId(f.id) === slugifyId(dragId));
    const b = schema.findIndex((f) => slugifyId(f.id) === slugifyId(dropId));
    if (a < 0 || b < 0 || a === b) return;
    const next = [...schema];
    const [it] = next.splice(a, 1);
    next.splice(b, 0, it);
    update(next);
  };

  const move = (id: string, dir: -1 | 1) => {
    const idx = schema.findIndex((f) => slugifyId(f.id) === slugifyId(id));
    if (idx < 0) return;
    const j = idx + dir;
    if (j < 0 || j >= schema.length) return;
    const next = [...schema];
    const t = next[idx];
    next[idx] = next[j];
    next[j] = t;
    update(next);
  };

  const updateField = (id: string, patch: Partial<DynamicCheckoutField>) => {
    const next = schema.map((f) => (slugifyId(f.id) === slugifyId(id) ? { ...f, ...patch } : f));
    const err = validate(next.map((f) => ({ ...f, id: slugifyId(f.id) })));
    if (err) {
      setRawError(err);
      return;
    }
    update(next.map((f) => ({ ...f, id: slugifyId(f.id) })));
  };

  const active = activeId ? schema.find((f) => slugifyId(f.id) === slugifyId(activeId)) : null;

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <div className="text-sm font-semibold text-slate-900 dark:text-white">{__('Field builder', 'sikshya')}</div>
          <div className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            {__('Drag to reorder, enable/disable, set width, options and visibility.', 'sikshya')}
          </div>
        </div>
        <button
          type="button"
          className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800/60"
          onClick={() => setShowAdd((p) => !p)}
          disabled={!!readOnly}
        >
          {showAdd ? __('Close', 'sikshya') : __('Add field', 'sikshya')}
        </button>
      </div>

      {rawError ? (
        <div className="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-200">
          {rawError}
        </div>
      ) : null}

      {showAdd ? (
        <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950/30">
          <div className="grid gap-3 lg:grid-cols-2">
            <div>
              <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Label', 'sikshya')}</label>
              <input
                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                value={newField.label || ''}
                disabled={!!readOnly}
                onChange={(e) =>
                  setNewField((p) => {
                    const label = e.target.value;
                    const next: DynamicCheckoutField = { ...p, label };
                    if (!idTouched) {
                      next.id = slugifyId(label);
                    }
                    return next;
                  })
                }
                placeholder={__('e.g. Company name', 'sikshya')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{__('ID', 'sikshya')}</label>
              <input
                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                value={newField.id || ''}
                disabled={!!readOnly}
                onChange={(e) => {
                  setIdTouched(true);
                  setNewField((p) => ({ ...p, id: e.target.value }));
                }}
                onBlur={() => {
                  setNewField((p) => ({ ...p, id: slugifyId(p.id || p.label) }));
                }}
                placeholder={__('e.g. company_name', 'sikshya')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Type', 'sikshya')}</label>
              <select
                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                value={newField.type}
                disabled={!!readOnly}
                onChange={(e) => setNewField((p) => ({ ...p, type: e.target.value as DynamicCheckoutFieldType }))}
              >
                {(['text', 'textarea', 'email', 'tel', 'number', 'select', 'country', 'radio', 'checkbox'] as DynamicCheckoutFieldType[]).map(
                  (t) => (
                    <option key={t} value={t}>
                      {fieldTypeLabel(t)}
                    </option>
                  )
                )}
              </select>
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Placeholder', 'sikshya')}</label>
              <input
                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                value={newField.placeholder || ''}
                disabled={!!readOnly}
                onChange={(e) => setNewField((p) => ({ ...p, placeholder: e.target.value }))}
                placeholder={__('Optional', 'sikshya')}
              />
            </div>
          </div>

          {(newField.type === 'select' || newField.type === 'radio') && (
            <div className="mt-3 space-y-2">
              <div className="text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Options', 'sikshya')}</div>
              {(newField.options || []).map((opt, idx) => (
                <div key={idx} className="grid gap-2 lg:grid-cols-2">
                  <input
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    value={opt.value}
                    disabled={!!readOnly}
                    onChange={(e) => {
                      const next = [...(newField.options || [])];
                      next[idx] = { ...next[idx], value: e.target.value };
                      setNewField((p) => ({ ...p, options: next }));
                    }}
                    placeholder={__('value', 'sikshya')}
                  />
                  <input
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    value={opt.label}
                    disabled={!!readOnly}
                    onChange={(e) => {
                      const next = [...(newField.options || [])];
                      next[idx] = { ...next[idx], label: e.target.value };
                      setNewField((p) => ({ ...p, options: next }));
                    }}
                    placeholder={__('label', 'sikshya')}
                  />
                </div>
              ))}
              <button
                type="button"
                className="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800/60"
                onClick={() => setNewField((p) => ({ ...p, options: [...(p.options || []), { value: '', label: '' }] }))}
                disabled={!!readOnly}
              >
                {__('Add option', 'sikshya')}
              </button>
            </div>
          )}

          <div className="mt-3 flex flex-wrap items-center gap-4">
            <label className="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
              <input
                type="checkbox"
                className="h-4 w-4"
                checked={!!newField.required}
                disabled={!!readOnly}
                onChange={(e) => setNewField((p) => ({ ...p, required: e.target.checked }))}
              />
              {__('Required', 'sikshya')}
            </label>
            <label className="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
              <input
                type="checkbox"
                className="h-4 w-4"
                checked={newField.enabled !== false}
                disabled={!!readOnly}
                onChange={(e) => setNewField((p) => ({ ...p, enabled: e.target.checked }))}
              />
              {__('Enabled', 'sikshya')}
            </label>
            <label className="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
              <input
                type="checkbox"
                className="h-4 w-4"
                checked={newField.persist_to_user !== false}
                disabled={!!readOnly}
                onChange={(e) => setNewField((p) => ({ ...p, persist_to_user: e.target.checked }))}
              />
              {__('Save to user', 'sikshya')}
            </label>
            <div className="flex items-center gap-2">
              <span className="text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Width', 'sikshya')}</span>
              <select
                className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                value={newField.width || 'full'}
                disabled={!!readOnly}
                onChange={(e) => setNewField((p) => ({ ...p, width: e.target.value as 'full' | 'half' }))}
              >
                <option value="full">{__('Full', 'sikshya')}</option>
                <option value="half">{__('Half', 'sikshya')}</option>
              </select>
            </div>
            <div className="flex-1" />
            <button
              type="button"
              className="rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 disabled:opacity-60"
              onClick={addField}
              disabled={!!readOnly || !newField.label}
            >
              {__('Add', 'sikshya')}
            </button>
          </div>
        </div>
      ) : null}

      <div className="mt-4 grid gap-3 lg:grid-cols-2">
        <div className="space-y-2">
          {schema.length === 0 ? (
            <div className="rounded-lg border border-dashed border-slate-200 p-3 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
              {__('No fields yet. Click “Add field”.', 'sikshya')}
            </div>
          ) : null}

          {schema.map((f, i) => {
            const id = slugifyId(f.id);
            const isActive = !!activeId && slugifyId(activeId) === id;
            const enabled = f.enabled !== false;
            const locked = !!f.locked || !!f.system;
            return (
              <button
                key={`${id}-${i}`}
                type="button"
                draggable={!readOnly}
                onDragStart={() => setDraggedId(id)}
                onDragOver={(e) => {
                  e.preventDefault();
                  if (draggedId && draggedId !== id) setDragOverId(id);
                }}
                onDragLeave={() => setDragOverId(null)}
                onDrop={(e) => {
                  e.preventDefault();
                  if (draggedId && draggedId !== id) moveByDrag(draggedId, id);
                  setDraggedId(null);
                  setDragOverId(null);
                }}
                onDragEnd={() => {
                  setDraggedId(null);
                  setDragOverId(null);
                }}
                className={`w-full rounded-lg border px-3 py-2 text-left text-sm shadow-sm transition-all ${
                  isActive
                    ? 'border-brand-300 bg-brand-50 dark:border-brand-800/60 dark:bg-brand-950/20'
                    : 'border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800/60'
                } ${dragOverId === id ? 'ring-2 ring-brand-500/30' : ''} ${enabled ? '' : 'opacity-60'}`}
                onClick={() => setActiveId(id)}
              >
                <div className="flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <div className="truncate font-semibold text-slate-900 dark:text-white">
                      {f.label || __('(Untitled)', 'sikshya')}{' '}
                      {f.required ? <span className="text-rose-600 dark:text-rose-300">*</span> : null}
                    </div>
                    <div className="mt-0.5 truncate text-[11px] text-slate-500 dark:text-slate-400">
                      <span className="font-mono">{id || __('missing_id', 'sikshya')}</span> • {fieldTypeLabel(f.type)} •{' '}
                      {f.system ? __('System', 'sikshya') : enabled ? __('Enabled', 'sikshya') : __('Disabled', 'sikshya')}
                    </div>
                  </div>
                  <div className="flex shrink-0 items-center gap-1">
                    <button
                      type="button"
                      className="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800/60"
                      onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        move(id, -1);
                      }}
                      disabled={!!readOnly || i === 0}
                      aria-label={__('Move up', 'sikshya')}
                    >
                      ↑
                    </button>
                    <button
                      type="button"
                      className="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800/60"
                      onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        move(id, 1);
                      }}
                      disabled={!!readOnly || i === schema.length - 1}
                      aria-label={__('Move down', 'sikshya')}
                    >
                      ↓
                    </button>
                    <button
                      type="button"
                      className="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800/60"
                      onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        updateField(id, { enabled: !(f.enabled !== false) });
                      }}
                      disabled={!!readOnly || locked}
                      aria-label={__('Toggle enabled', 'sikshya')}
                    >
                      {enabled ? __('On', 'sikshya') : __('Off', 'sikshya')}
                    </button>
                  </div>
                </div>
              </button>
            );
          })}
        </div>

        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950/30">
          {active ? (
            <div className="space-y-3">
              <div className="flex items-center justify-between gap-2">
                <div className="text-sm font-semibold text-slate-900 dark:text-white">{__('Edit field', 'sikshya')}</div>
                <button
                  type="button"
                  className="rounded-md border border-rose-200 bg-white px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-60 dark:border-rose-900/50 dark:bg-slate-900 dark:text-rose-200 dark:hover:bg-rose-950/30"
                  onClick={() => removeField(active.id)}
                  disabled={!!readOnly || !!active.locked || !!active.system}
                >
                  {__('Remove', 'sikshya')}
                </button>
              </div>

              <div className="grid gap-3 lg:grid-cols-2">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{__('ID', 'sikshya')}</label>
                  <input
                    className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    value={active.id}
                    disabled={!!readOnly}
                    onChange={(e) => updateField(active.id, { id: e.target.value })}
                    placeholder={__('e.g. company_name', 'sikshya')}
                  />
                  <div className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                    {__('Used as key in orders/user meta. Lowercase + underscores recommended.', 'sikshya')}
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Label', 'sikshya')}</label>
                  <input
                    className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    value={active.label || ''}
                    disabled={!!readOnly}
                    onChange={(e) => updateField(active.id, { label: e.target.value })}
                    placeholder={__('e.g. Company name', 'sikshya')}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Placeholder', 'sikshya')}</label>
                  <input
                    className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    value={active.placeholder || ''}
                    disabled={
                      !!readOnly ||
                      active.type === 'select' ||
                      active.type === 'radio' ||
                      active.type === 'checkbox' ||
                      active.type === 'country'
                    }
                    onChange={(e) => updateField(active.id, { placeholder: e.target.value })}
                    placeholder={__('Optional', 'sikshya')}
                  />
                  {active.type === 'select' || active.type === 'radio' || active.type === 'checkbox' || active.type === 'country' ? (
                    <div className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                      {__('Not used for this field type.', 'sikshya')}
                    </div>
                  ) : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Type', 'sikshya')}</label>
                  <select
                    className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    value={active.type}
                    disabled={!!readOnly}
                    onChange={(e) => updateField(active.id, { type: e.target.value as DynamicCheckoutFieldType })}
                  >
                    {(['text', 'textarea', 'email', 'tel', 'number', 'select', 'country', 'radio', 'checkbox'] as DynamicCheckoutFieldType[]).map(
                      (t) => (
                        <option key={t} value={t}>
                          {fieldTypeLabel(t)}
                        </option>
                      )
                    )}
                  </select>
                </div>

                <div className="flex items-center gap-3 pt-6">
                  <label className="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
                    <input
                      type="checkbox"
                      className="h-4 w-4"
                      checked={!!active.required}
                      disabled={!!readOnly}
                      onChange={(e) => updateField(active.id, { required: e.target.checked })}
                    />
                    {__('Required', 'sikshya')}
                  </label>
                  <label className="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
                    <input
                      type="checkbox"
                      className="h-4 w-4"
                      checked={active.persist_to_user !== false}
                      disabled={!!readOnly}
                      onChange={(e) => updateField(active.id, { persist_to_user: e.target.checked })}
                    />
                    {__('Save to user (prefill next time)', 'sikshya')}
                  </label>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Help text', 'sikshya')}</label>
                <textarea
                  className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                  rows={2}
                  value={active.help || ''}
                  disabled={!!readOnly}
                  onChange={(e) => updateField(active.id, { help: e.target.value })}
                />
              </div>

              {active.type === 'select' || active.type === 'radio' ? (
                <div className="space-y-2">
                  <div className="text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Options', 'sikshya')}</div>
                  {(active.options || []).map((opt, idx) => (
                    <div key={idx} className="grid gap-2 lg:grid-cols-2">
                      <input
                        className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        value={opt.value}
                        disabled={!!readOnly}
                        onChange={(e) => {
                          const next = [...(active.options || [])];
                          next[idx] = { ...next[idx], value: e.target.value };
                          updateField(active.id, { options: next });
                        }}
                        placeholder={__('value', 'sikshya')}
                      />
                      <input
                        className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        value={opt.label}
                        disabled={!!readOnly}
                        onChange={(e) => {
                          const next = [...(active.options || [])];
                          next[idx] = { ...next[idx], label: e.target.value };
                          updateField(active.id, { options: next });
                        }}
                        placeholder={__('label', 'sikshya')}
                      />
                    </div>
                  ))}
                  <button
                    type="button"
                    className="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800/60"
                    onClick={() => updateField(active.id, { options: [...(active.options || []), { value: '', label: '' }] })}
                    disabled={!!readOnly}
                  >
                    {__('Add option', 'sikshya')}
                  </button>
                </div>
              ) : null}

              <div className="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                <div className="text-xs font-semibold text-slate-700 dark:text-slate-200">{__('Visibility (optional)', 'sikshya')}</div>
                <div className="mt-2 grid gap-3 lg:grid-cols-2">
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                      {__('Depends on field ID', 'sikshya')}
                    </label>
                    <input
                      className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                      value={active.visibility?.depends_on || ''}
                      disabled={!!readOnly}
                      onChange={(e) =>
                        updateField(active.id, {
                          visibility: { ...(active.visibility || {}), depends_on: slugifyId(e.target.value) },
                        })
                      }
                      placeholder={__('e.g. plan', 'sikshya')}
                    />
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                      {__('Show when value equals', 'sikshya')}
                    </label>
                    <input
                      className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                      value={active.visibility?.depends_value || ''}
                      disabled={!!readOnly}
                      onChange={(e) =>
                        updateField(active.id, {
                          visibility: { ...(active.visibility || {}), depends_value: e.target.value },
                        })
                      }
                      placeholder={__('e.g. business', 'sikshya')}
                    />
                  </div>
                </div>
                <div className="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                  {__('For multi-value rules (depends_in), we’ll expand in v2.', 'sikshya')}
                </div>
              </div>
            </div>
          ) : (
            <div className="text-sm text-slate-600 dark:text-slate-300">{__('Select a field to edit.', 'sikshya')}</div>
          )}
        </div>
      </div>
    </div>
  );
}

