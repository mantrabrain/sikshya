import type { Dispatch, ReactNode, SetStateAction } from 'react';
import type { SettingsField } from '../types/settingsSchema';
import { DynamicFieldsBuilder } from '../components/settings/DynamicFieldsBuilder';

export function fieldToStringValue(v: unknown): string {
  if (v === null || v === undefined) return '';
  return String(v);
}

export function isTruthyCheckboxValue(v: unknown): boolean {
  return v === true || v === 1 || v === '1' || v === 'yes' || v === 'on';
}

function renderDescription(desc: string): ReactNode {
  const raw = String(desc || '').trim();
  if (!raw) return null;

  // Allow only <a href="...">text</a> from server-provided schema descriptions.
  // Everything else is rendered as plain text (with URL linkify).
  if (raw.includes('<a') && typeof window !== 'undefined' && typeof DOMParser !== 'undefined') {
    try {
      const doc = new DOMParser().parseFromString(`<div>${raw}</div>`, 'text/html');
      const root = doc.body.firstElementChild;
      if (!root) return raw;

      const out: ReactNode[] = [];
      const walk = (node: ChildNode) => {
        if (node.nodeType === Node.TEXT_NODE) {
          const t = node.textContent || '';
          if (t) out.push(t);
          return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        const el = node as HTMLElement;
        if (el.tagName.toLowerCase() === 'a') {
          const href = String(el.getAttribute('href') || '').trim();
          const text = String(el.textContent || href || '').trim();
          if (!href || !/^https?:\/\//i.test(href)) {
            if (text) out.push(text);
            return;
          }
          out.push(
            <a
              key={`a-${out.length}-${href}`}
              href={href}
              target="_blank"
              rel="noopener noreferrer"
              className="font-medium text-blue-600 underline decoration-blue-300 underline-offset-2 hover:text-blue-700 hover:decoration-blue-400 dark:text-blue-400 dark:decoration-blue-700 dark:hover:text-blue-300 dark:hover:decoration-blue-600"
            >
              {text || href}
            </a>
          );
          return;
        }
        // Any other element: render its text content only.
        const t = el.textContent || '';
        if (t) out.push(t);
      };

      root.childNodes.forEach((n) => walk(n));
      return <>{out}</>;
    } catch {
      // Fall through to linkify.
    }
  }

  // Linkify plain URLs.
  const parts = raw.split(/(https?:\/\/[^\s)]+)\b/g);
  if (parts.length <= 1) return raw;
  return (
    <>
      {parts.map((p, idx) => {
        const isUrl = /^https?:\/\//i.test(p);
        if (!isUrl) return <span key={`t-${idx}`}>{p}</span>;
        return (
          <a
            key={`u-${idx}-${p}`}
            href={p}
            target="_blank"
            rel="noopener noreferrer"
            className="font-medium text-blue-600 underline decoration-blue-300 underline-offset-2 hover:text-blue-700 hover:decoration-blue-400 dark:text-blue-400 dark:decoration-blue-700 dark:hover:text-blue-300 dark:hover:decoration-blue-600"
          >
            {p}
          </a>
        );
      })}
    </>
  );
}

/**
 * Wrap a locked field in a disabled overlay with a "Pro" pill and a friendly
 * reason.  Shared between the Settings page, the Email page, and any consumer
 * that uses `renderSettingsField`.
 */
function LockedFieldOverlay(props: { f: SettingsField; children: ReactNode; className?: string }) {
  const { f, children, className = '' } = props;
  const reason = f.locked_reason || 'Available on a higher Sikshya Pro plan.';
  const addonLabel = f.required_addon_label || f.required_addon || '';
  const planLabel = f.required_plan_label || '';
  return (
    <div className={`relative rounded-xl border border-dashed border-violet-200 bg-violet-50/40 p-3 dark:border-violet-900/50 dark:bg-violet-950/20 ${className}`}>
      <div className="pointer-events-none select-none opacity-60" aria-hidden>
        {children}
      </div>
      <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
        <span className="inline-flex items-center gap-1 rounded-md bg-violet-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-violet-700 dark:bg-violet-900/50 dark:text-violet-200">
          <span aria-hidden>★</span> Pro{planLabel ? ` • ${planLabel}` : ''}
        </span>
        <span className="text-[11px] leading-snug text-violet-700/90 dark:text-violet-200/90">
          {addonLabel ? (
            <>
              <span className="font-semibold">Addon:</span> {addonLabel} <span className="mx-1 opacity-70">•</span>
            </>
          ) : null}
          {reason}
        </span>
      </div>
    </div>
  );
}

/**
 * Shared field renderer for Settings and the dedicated Email admin page.
 */
export function renderSettingsField(
  draft: Record<string, unknown>,
  setDraft: Dispatch<SetStateAction<Record<string, unknown>>>,
  f: SettingsField
) {
  const k = f.key;
  const type = f.type || 'text';
  const cur = draft[k];

  const label = f.label || k;
  const desc = f.description || '';
  const locked = !!f.locked;
  // When a field is Pro-locked we render read-only controls so the user can
  // still see the (default) shape of the field but cannot modify it.
  const readOnly = locked;
  const onChangeGuard = <T,>(cb: (v: T) => void) => (v: T) => {
    if (readOnly) return;
    cb(v);
  };

  let body: ReactNode;

  if (type === 'dynamic_fields_builder') {
    body = (
      <div>
        <label className="block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor={k}>
          {label}
        </label>
        {desc ? (
          <p className="mt-1.5 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{renderDescription(desc)}</p>
        ) : null}
        <div className="mt-2">
          <DynamicFieldsBuilder
            value={cur ?? f.default ?? '[]'}
            readOnly={readOnly}
            onChange={onChangeGuard<string>((v) => setDraft((p) => ({ ...p, [k]: v })))}
          />
        </div>
      </div>
    );
  } else if (type === 'checkbox') {
    const checked = isTruthyCheckboxValue(cur);
    body = (
      <div>
        <label
          className={`flex items-start gap-3 rounded-xl border border-slate-200/70 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 ${
            readOnly ? 'cursor-not-allowed' : 'cursor-pointer'
          }`}
        >
          <input
            type="checkbox"
            className="mt-1 h-4 w-4"
            checked={checked}
            disabled={readOnly}
            onChange={(e) => onChangeGuard<boolean>((v) => setDraft((p) => ({ ...p, [k]: v ? '1' : '0' })))(e.target.checked)}
          />
          <span className="min-w-0">
            <span className="block text-sm font-semibold text-slate-900 dark:text-white">{label}</span>
            {desc ? (
              <span className="mt-1 block text-xs text-slate-400/90 dark:text-slate-500/80">{renderDescription(desc)}</span>
            ) : null}
          </span>
        </label>
      </div>
    );
  } else if (type === 'select') {
    const opts = f.options || {};
    const optKeys = Object.keys(opts).map((x) => String(x));
    const ph = f.select_placeholder;
    const raw = fieldToStringValue(cur ?? f.default ?? '');
    const selectValue = ph && (raw === '' || !optKeys.includes(raw)) ? '' : raw;
    body = (
      <div>
        <label className="block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor={k}>
          {label}
        </label>
        {desc ? (
          <p className="mt-1.5 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{renderDescription(desc)}</p>
        ) : null}
        <select
          id={k}
          className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/25 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          value={selectValue}
          disabled={readOnly}
          onChange={(e) => onChangeGuard<string>((v) => setDraft((p) => ({ ...p, [k]: v })))(e.target.value)}
        >
          {ph ? <option value="">{ph}</option> : null}
          {Object.entries(opts).map(([ov, ol]) => (
            <option key={String(ov)} value={ov}>
              {ol}
            </option>
          ))}
        </select>
      </div>
    );
  } else if (type === 'multi_select') {
    const opts = f.options || {};
    const allowed = Object.keys(opts).map((x) => String(x));
    const raw = fieldToStringValue(cur ?? f.default ?? '');
    const selected = raw
      .split(',')
      .map((x) => String(x || '').trim())
      .filter((x) => x !== '')
      .filter((x, i, a) => a.indexOf(x) === i)
      .filter((x) => allowed.includes(x));

    const setSelected = (vals: string[]) => {
      const normalized = vals
        .map((x) => String(x || '').trim())
        .filter((x) => x !== '' && allowed.includes(x))
        .filter((x, i, a) => a.indexOf(x) === i);
      setDraft((p) => ({ ...p, [k]: normalized.join(',') }));
    };

    body = (
      <div>
        <label className="block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor={k}>
          {label}
        </label>
        {desc ? (
          <p className="mt-1.5 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{renderDescription(desc)}</p>
        ) : null}

        <select
          id={k}
          multiple
          className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/25 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          value={selected}
          disabled={readOnly}
          onChange={(e) =>
            onChangeGuard<string[]>((v) => setSelected(v))(
              Array.from(e.target.selectedOptions || []).map((o) => String(o.value))
            )
          }
          size={Math.min(6, Math.max(3, allowed.length))}
        >
          {Object.entries(opts).map(([ov, ol]) => (
            <option key={String(ov)} value={ov}>
              {ol}
            </option>
          ))}
        </select>

        {selected.length ? (
          <div className="mt-2 flex flex-wrap gap-2">
            {selected.map((v) => (
              <span
                key={v}
                className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[12px] font-semibold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
              >
                {opts[v] || v}
                {!readOnly ? (
                  <button
                    type="button"
                    className="rounded-full px-1 text-slate-400 hover:text-slate-700 dark:text-slate-500 dark:hover:text-slate-200"
                    onClick={() => setSelected(selected.filter((x) => x !== v))}
                    aria-label={`Remove ${opts[v] || v}`}
                  >
                    ×
                  </button>
                ) : null}
              </span>
            ))}
          </div>
        ) : (
          <p className="mt-2 text-xs text-slate-400/90 dark:text-slate-500/80">
            Select one or more methods. The value is stored as a comma-separated list.
          </p>
        )}
      </div>
    );
  } else if (type === 'color') {
    const hex = (() => {
      const s = fieldToStringValue(cur ?? f.default ?? '#000000');
      return /^#[0-9A-Fa-f]{6}$/.test(s) ? s : '#000000';
    })();
    body = (
      <div>
        <label className="block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor={k}>
          {label}
        </label>
        {desc ? (
          <p className="mt-1.5 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{renderDescription(desc)}</p>
        ) : null}
        <div className="mt-1.5 flex flex-wrap items-center gap-3">
          <input
            id={k}
            type="color"
            className="h-10 w-14 cursor-pointer rounded border border-slate-200 bg-white p-1 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-600"
            value={hex}
            disabled={readOnly}
            onChange={(e) => onChangeGuard<string>((v) => setDraft((p) => ({ ...p, [k]: v })))(e.target.value)}
            aria-label={label}
          />
          <span className="font-mono text-xs text-slate-500 dark:text-slate-400">{hex}</span>
        </div>
      </div>
    );
  } else if (type === 'textarea') {
    body = (
      <div>
        <label className="block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor={k}>
          {label}
        </label>
        {desc ? (
          <p className="mt-1.5 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{renderDescription(desc)}</p>
        ) : null}
        <textarea
          id={k}
          rows={4}
          className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/25 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          value={fieldToStringValue(cur ?? f.default ?? '')}
          disabled={readOnly}
          onChange={(e) => onChangeGuard<string>((v) => setDraft((p) => ({ ...p, [k]: v })))(e.target.value)}
          placeholder={f.placeholder || ''}
        />
      </div>
    );
  } else {
    const inputType =
      type === 'number'
        ? 'number'
        : type === 'email'
          ? 'email'
          : type === 'password'
            ? 'password'
            : type === 'url'
              ? 'url'
              : type === 'datetime-local'
                ? 'datetime-local'
                : 'text';
    body = (
      <div>
        <label className="block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor={k}>
          {label}
        </label>
        {desc ? (
          <p className="mt-1.5 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{renderDescription(desc)}</p>
        ) : null}
        <input
          id={k}
          type={inputType}
          className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/25 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          value={fieldToStringValue(cur ?? f.default ?? '')}
          disabled={readOnly}
          onChange={(e) => onChangeGuard<string>((v) => setDraft((p) => ({ ...p, [k]: v })))(e.target.value)}
          placeholder={f.placeholder || ''}
          min={typeof f.min === 'number' ? f.min : undefined}
          max={typeof f.max === 'number' ? f.max : undefined}
          step={typeof f.step === 'number' ? f.step : type === 'number' ? 1 : undefined}
          autoComplete={type === 'password' ? 'new-password' : type === 'email' ? 'email' : undefined}
        />
      </div>
    );
  }

  // Checkbox and textarea span two columns; keep that behavior on the wrapper
  // so the lock-overlay doesn't break the grid layout.
  const spanClass = type === 'checkbox' || type === 'textarea' || type === 'dynamic_fields_builder' ? 'lg:col-span-2' : '';

  if (locked) {
    return (
      <div key={k} className={spanClass}>
        <LockedFieldOverlay f={f}>{body}</LockedFieldOverlay>
      </div>
    );
  }

  return (
    <div key={k} className={spanClass}>
      {body}
    </div>
  );
}
