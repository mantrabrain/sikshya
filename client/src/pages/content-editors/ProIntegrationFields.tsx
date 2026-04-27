import { type ReactNode, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi } from '../../api';
import { SIKSHYA_ENDPOINTS } from '../../api/endpoints';
import { useAddonEnabled } from '../../hooks/useAddons';
import { DateTimePickerField } from '../../components/shared/DateTimePickerField';

const FIELD =
  'block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/25 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';
const LABEL = 'mb-1 block text-sm font-medium text-slate-900 dark:text-slate-100';
const HINT = 'mb-2 text-xs text-slate-500 dark:text-slate-400';

function ProCard(props: { title: string; description?: string; badge?: string; children: ReactNode }) {
  const { title, description, badge, children } = props;
  return (
    <section className="rounded-xl border border-amber-200/60 bg-amber-50/40 p-4 dark:border-amber-400/20 dark:bg-amber-950/10">
      <header className="mb-3 flex flex-wrap items-center gap-2">
        <h3 className="text-sm font-semibold text-amber-900 dark:text-amber-200">{title}</h3>
        {badge ? (
          <span className="rounded-full bg-amber-200/70 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900 dark:bg-amber-500/20 dark:text-amber-200">
            {badge}
          </span>
        ) : null}
      </header>
      {description ? <p className={`${HINT} text-amber-900/80 dark:text-amber-200/70`}>{description}</p> : null}
      <div className="space-y-4">{children}</div>
    </section>
  );
}

// ---------------------------------------------------------------------------
// Lesson — live_classes + scorm_h5p_pro
// ---------------------------------------------------------------------------

/** Per-lesson launch mode override; '' = inherit from course/global policy. */
export type InteractiveLaunchMode = '' | 'inline' | 'fullscreen' | 'new_window';

export type ProLessonValues = {
  liveUrl: string;
  liveProvider: string;
  liveStart: string;
  liveDuration: number;
  liveSessionTitle: string;
  livePasscodeHint: string;
  liveRecordingUrl: string;
  /** Managed SCORM package id from the package library (0 = none). */
  scormPackageId: number;
  /** Optional external SCORM launch URL (used only if no managed package is attached). */
  scormUrl: string;
  /** Selected H5P content id from the picker (0 = none). */
  h5pContentId: number;
  /** Sanitized iframe HTML fallback for environments without the H5P plugin. */
  h5pEmbed: string;
  /** Per-lesson player display override; empty string means inherit. */
  launchMode: InteractiveLaunchMode;
};

export const PRO_LESSON_DEFAULTS: ProLessonValues = {
  liveUrl: '',
  liveProvider: '',
  liveStart: '',
  liveDuration: 60,
  liveSessionTitle: '',
  livePasscodeHint: '',
  liveRecordingUrl: '',
  scormPackageId: 0,
  scormUrl: '',
  h5pContentId: 0,
  h5pEmbed: '',
  launchMode: '',
};

/**
 * ISO strings come back from WP as "2026-04-19T14:00:00+00:00".
 * datetime-local inputs want "2026-04-19T14:00".
 */
function isoToLocal(iso: string): string {
  if (!iso) return '';
  try {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  } catch {
    return '';
  }
}

function localToIso(local: string): string {
  if (!local) return '';
  try {
    const d = new Date(local);
    if (isNaN(d.getTime())) return '';
    return d.toISOString();
  } catch {
    return '';
  }
}

export function readProLessonFromMeta(meta: Record<string, unknown> | undefined): ProLessonValues {
  const m = meta ?? {};
  const launch = String(m['_sikshya_lesson_launch_mode'] ?? '');
  const launchMode: InteractiveLaunchMode = launch === 'inline' || launch === 'fullscreen' || launch === 'new_window' ? launch : '';
  return {
    liveUrl: String(m['_sikshya_live_meeting_url'] ?? ''),
    liveProvider: String(m['_sikshya_live_provider'] ?? ''),
    liveStart: isoToLocal(String(m['_sikshya_live_start_at'] ?? '')),
    liveDuration: Number(m['_sikshya_live_duration_minutes'] ?? 60) || 60,
    liveSessionTitle: String(m['_sikshya_live_session_title'] ?? ''),
    livePasscodeHint: String(m['_sikshya_live_passcode_hint'] ?? ''),
    liveRecordingUrl: String(m['_sikshya_live_recording_url'] ?? ''),
    scormPackageId: Math.max(0, Number(m['_sikshya_scorm_package_id'] ?? 0) || 0),
    scormUrl: String(m['_sikshya_scorm_launch_url'] ?? ''),
    h5pContentId: Math.max(0, Number(m['_sikshya_h5p_content_id'] ?? 0) || 0),
    h5pEmbed: String(m['_sikshya_h5p_embed_html'] ?? ''),
    launchMode,
  };
}

/**
 * Lesson kinds that carry Pro-only field sets. Aligned with `_sikshya_lesson_type`.
 */
export type ProLessonKind = 'live' | 'scorm' | 'h5p';

/**
 * Persist only the meta keys relevant to the active lesson kind. Keys for other
 * kinds are explicitly emptied so switching from "Live class" to "SCORM" does
 * not leave a stale meeting URL behind that the runtime renderer would still
 * pick up.
 */
export function buildProLessonMetaForKind(
  kind: string,
  v: ProLessonValues
): Record<string, unknown> {
  const isLive = kind === 'live';
  const isScorm = kind === 'scorm';
  const isH5p = kind === 'h5p';
  const isInteractive = isScorm || isH5p;
  return {
    _sikshya_live_meeting_url: isLive ? v.liveUrl.trim() : '',
    _sikshya_live_provider: isLive ? v.liveProvider : '',
    _sikshya_live_start_at: isLive && v.liveStart ? localToIso(v.liveStart) : '',
    _sikshya_live_duration_minutes: isLive ? Math.max(0, Math.min(720, Number(v.liveDuration) || 0)) : 0,
    _sikshya_scorm_package_id: isScorm ? Math.max(0, Number(v.scormPackageId) || 0) : 0,
    _sikshya_scorm_launch_url: isScorm ? v.scormUrl.trim() : '',
    _sikshya_h5p_content_id: isH5p ? Math.max(0, Number(v.h5pContentId) || 0) : 0,
    _sikshya_h5p_embed_html: isH5p ? v.h5pEmbed : '',
    _sikshya_lesson_launch_mode: isInteractive ? v.launchMode : '',
  };
}

/**
 * @deprecated Prefer {@link buildProLessonMetaForKind} so unrelated meta is cleared.
 *             Kept for back-compat with classic admin tooling that always saves the full set.
 */
export function buildProLessonMeta(v: ProLessonValues): Record<string, unknown> {
  return {
    _sikshya_live_meeting_url: v.liveUrl.trim(),
    _sikshya_live_provider: v.liveProvider,
    _sikshya_live_start_at: v.liveStart ? localToIso(v.liveStart) : '',
    _sikshya_live_duration_minutes: Math.max(5, Math.min(720, Number(v.liveDuration) || 0)),
    _sikshya_live_session_title: v.liveSessionTitle.trim(),
    _sikshya_live_passcode_hint: v.livePasscodeHint.trim(),
    _sikshya_live_recording_url: v.liveRecordingUrl.trim(),
    _sikshya_scorm_package_id: Math.max(0, Number(v.scormPackageId) || 0),
    _sikshya_scorm_launch_url: v.scormUrl.trim(),
    _sikshya_h5p_content_id: Math.max(0, Number(v.h5pContentId) || 0),
    _sikshya_h5p_embed_html: v.h5pEmbed,
    _sikshya_lesson_launch_mode: v.launchMode,
  };
}

/**
 * Live class field block — rendered when the active lesson kind is "live"
 * AND the `live_classes` Pro addon is licensed + enabled.
 *
 * Emits a non-null upsell card when the kind is selected but the addon is off,
 * so admins always understand why the fields are missing.
 */
export function ProLessonLiveBlock(props: {
  values: ProLessonValues;
  onChange: (v: ProLessonValues) => void;
}) {
  const { values, onChange } = props;
  const addon = useAddonEnabled('live_classes');
  const ready = Boolean(addon.enabled && addon.licenseOk);
  const set = <K extends keyof ProLessonValues>(k: K, val: ProLessonValues[K]) => onChange({ ...values, [k]: val });

  if (!ready) {
    return (
      <ProCard
        title="Live class"
        badge={addon.licenseOk ? 'Addon off' : 'Pro'}
        description={
          addon.licenseOk
            ? 'Turn on the “Live classes” addon to schedule a Zoom / Meet / Teams / Jitsi session for this lesson.'
            : 'Live classes require Sikshya Pro. Upgrade to schedule live sessions inside lessons.'
        }
      >
        <p className={HINT}>The lesson is set to “Live class” but the addon is unavailable right now — fields will appear here once it is enabled.</p>
      </ProCard>
    );
  }

  return (
    <ProCard
      title="Live class"
      badge="Pro"
      description="Paste the join link from Zoom, Meet, Teams, Classroom, or Webex. Learners see a polished join panel with schedule-aware hints."
    >
      {settingsHint ? <p className={`${HINT} mb-3 text-slate-600 dark:text-slate-300`}>{settingsHint}</p> : null}
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <label className={LABEL} htmlFor="sik-pro-live-url">Meeting URL</label>
          <input
            id="sik-pro-live-url"
            type="url"
            className={FIELD}
            value={values.liveUrl}
            onChange={(e) => set('liveUrl', e.target.value)}
            placeholder="https://zoom.us/j/..."
          />
          <p className={HINT}>Must be an https:// link. Learners only see this after they can access the lesson.</p>
        </div>
        <div>
          <label className={LABEL} htmlFor="sik-pro-live-provider">Provider</label>
          <select
            id="sik-pro-live-provider"
            className={FIELD}
            value={values.liveProvider}
            onChange={(e) => set('liveProvider', e.target.value)}
          >
            <option value="">— pick one —</option>
            {LIVE_PROVIDER_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className={LABEL} htmlFor="sik-pro-live-start">Scheduled start</label>
          <DateTimePickerField
            kind="datetime"
            value={values.liveStart}
            onChange={(v) => set('liveStart', v)}
            className=""
          />
          <p className={HINT}>Drives the course schedule strip, learn banner, catalog “Live” badge window, and calendar export.</p>
        </div>
        <div>
          <label className={LABEL} htmlFor="sik-pro-live-duration">Duration (minutes)</label>
          <input
            id="sik-pro-live-duration"
            type="number"
            min={5}
            max={720}
            className={FIELD}
            value={String(values.liveDuration || '')}
            onChange={(e) => set('liveDuration', Number(e.target.value) || 0)}
          />
        </div>
        <div className="sm:col-span-2">
          <label className={LABEL} htmlFor="sik-pro-live-session-title">Session title (optional)</label>
          <input
            id="sik-pro-live-session-title"
            type="text"
            className={FIELD}
            value={values.liveSessionTitle}
            onChange={(e) => set('liveSessionTitle', e.target.value)}
            placeholder="Week 2 · Design critique"
          />
          <p className={HINT}>Shown in schedules and calendar instead of the lesson title when set.</p>
        </div>
        <div>
          <label className={LABEL} htmlFor="sik-pro-live-pass">Meeting ID / passcode hint</label>
          <input
            id="sik-pro-live-pass"
            type="text"
            className={FIELD}
            value={values.livePasscodeHint}
            onChange={(e) => set('livePasscodeHint', e.target.value)}
            placeholder="ID: 123 456 7890"
          />
          <p className={HINT}>Plain text only. Never store secrets you would not email to students.</p>
        </div>
        <div>
          <label className={LABEL} htmlFor="sik-pro-live-recording">Recording URL (optional)</label>
          <input
            id="sik-pro-live-recording"
            type="url"
            className={FIELD}
            value={values.liveRecordingUrl}
            onChange={(e) => set('liveRecordingUrl', e.target.value)}
            placeholder="https://…"
          />
          <p className={HINT}>Cloud replay link appears under the join panel when populated.</p>
        </div>
      </div>
    </ProCard>
  );
}

// ---------------------------------------------------------------------------
// SCORM/H5P attach wizard helpers
// ---------------------------------------------------------------------------

type ScormPackageRow = {
  id: number;
  title: string;
  scorm_version?: string;
  status: string;
  file_size_bytes?: number;
  lesson_reference_count?: number;
  manifest_identifier?: string;
  launch_path?: string;
  updated_at: string;
};

type ScormPackagesResponse = {
  rows?: ScormPackageRow[];
  total?: number;
  ok?: boolean;
};

type H5pContentRow = {
  id: number;
  title: string;
  library: string;
  updated_at: string;
};

type H5pContentsResponse = {
  ok?: boolean;
  plugin_available?: boolean;
  rows?: H5pContentRow[];
};

const LAUNCH_MODE_OPTIONS: ReadonlyArray<{ value: InteractiveLaunchMode; label: string; help: string }> = [
  { value: '', label: 'Use course / global default', help: 'Inherits launch mode from the course or addon settings.' },
  { value: 'inline', label: 'Inline iframe', help: 'Embeds the player inside the lesson page.' },
  { value: 'fullscreen', label: 'Inline + fullscreen toggle', help: 'Inline by default with a fullscreen control in the player toolbar.' },
  { value: 'new_window', label: 'Open in new window', help: 'Launches the player in a popup; useful when the package blocks iframing.' },
];

function formatSize(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return '—';
  const u = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  let n = bytes;
  while (n >= 1024 && i < u.length - 1) {
    n /= 1024;
    i++;
  }
  return `${n.toFixed(n >= 10 || i === 0 ? 0 : 1)} ${u[i]}`;
}

/**
 * SCORM lesson attach wizard — rendered when the active lesson kind is "scorm".
 * Gated by the `scorm_h5p_pro` addon. Lets the instructor pick a managed package
 * from the library, or fall back to an external launch URL.
 */
export function ProLessonScormBlock(props: {
  values: ProLessonValues;
  onChange: (v: ProLessonValues) => void;
}) {
  const { values, onChange } = props;
  const addon = useAddonEnabled('scorm_h5p_pro');
  const ready = Boolean(addon.enabled && addon.licenseOk);
  const set = <K extends keyof ProLessonValues>(k: K, val: ProLessonValues[K]) => onChange({ ...values, [k]: val });

  const [search, setSearch] = useState('');
  const [packages, setPackages] = useState<ScormPackageRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!ready) return;
    let cancelled = false;
    setLoading(true);
    setError(null);
    const t = setTimeout(() => {
      const api = getSikshyaApi();
      api
        .get<ScormPackagesResponse>(SIKSHYA_ENDPOINTS.pro.scormPackages({ per_page: 25, search }))
        .then((res) => {
          if (cancelled) return;
          setPackages(Array.isArray(res?.rows) ? res.rows : []);
        })
        .catch((err: unknown) => {
          if (cancelled) return;
          setPackages([]);
          setError(err instanceof Error ? err.message : 'Could not load packages.');
        })
        .finally(() => {
          if (!cancelled) setLoading(false);
        });
    }, 250);
    return () => {
      cancelled = true;
      clearTimeout(t);
    };
  }, [ready, search]);

  const selected = useMemo(
    () => packages.find((p) => p.id === values.scormPackageId) || null,
    [packages, values.scormPackageId]
  );

  if (!ready) {
    return (
      <ProCard
        title="SCORM package"
        badge={addon.licenseOk ? 'Addon off' : 'Pro'}
        description={
          addon.licenseOk
            ? 'Turn on the “SCORM / H5P” addon to launch packaged content from this lesson.'
            : 'SCORM playback requires Sikshya Pro. Upgrade to embed packaged training inside lessons.'
        }
      >
        <p className={HINT}>The lesson is set to “SCORM” but the addon is unavailable right now — fields will appear here once it is enabled.</p>
      </ProCard>
    );
  }

  const hasSelection = values.scormPackageId > 0;

  return (
    <ProCard
      title="SCORM package"
      badge="Pro"
      description="Pick a SCORM 1.2 / 2004 package from the library, or attach an external launch URL. Learner attempts and resume bookmarks are tracked automatically."
    >
      <div>
        <label className={LABEL}>Managed package</label>
        <p className={HINT}>
          Upload zipped packages on the SCORM / H5P workspace, then attach them here. Manifest is parsed for the entry file; attempts are recorded against the package.
        </p>
        <div className="space-y-2">
          <input
            type="search"
            className={FIELD}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search packages by title…"
            aria-label="Search SCORM packages"
          />
          <div className="max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
            {loading ? (
              <p className="p-3 text-slate-500 dark:text-slate-400">Loading library…</p>
            ) : error ? (
              <p className="p-3 text-rose-600 dark:text-rose-300">{error}</p>
            ) : packages.length === 0 ? (
              <p className="p-3 text-slate-500 dark:text-slate-400">
                No packages found. Upload one on the <em>SCORM / H5P</em> workspace, then refresh this picker.
              </p>
            ) : (
              <ul role="listbox" aria-label="SCORM packages">
                <li>
                  <button
                    type="button"
                    onClick={() => set('scormPackageId', 0)}
                    className={`block w-full px-3 py-2 text-left text-sm transition ${values.scormPackageId === 0 ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'}`}
                  >
                    <span className="font-medium">No managed package</span>
                    <span className="ml-2 text-xs text-slate-500 dark:text-slate-400">(use the URL fallback below)</span>
                  </button>
                </li>
                {packages.map((p) => {
                  const isActive = p.id === values.scormPackageId;
                  const disabled = p.status !== 'ready';
                  const refCount = p.lesson_reference_count ?? 0;
                  return (
                    <li key={p.id}>
                      <button
                        type="button"
                        disabled={disabled}
                        onClick={() => set('scormPackageId', p.id)}
                        className={`block w-full border-t border-slate-100 px-3 py-2 text-left text-sm transition first:border-t-0 dark:border-slate-800 ${isActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'} ${disabled ? 'cursor-not-allowed opacity-60' : ''}`}
                      >
                        <span className="block font-medium">{p.title || `Package #${p.id}`}</span>
                        <span className="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                          {(p.scorm_version || 'unknown').toUpperCase()} · {formatSize(p.file_size_bytes ?? 0)} · attached to {refCount} lesson{refCount === 1 ? '' : 's'}
                          {disabled ? ` · ${p.status}` : ''}
                        </span>
                      </button>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
          {selected ? (
            <p className={HINT}>
              Selected: <strong>{selected.title || `Package #${selected.id}`}</strong> · entry <code className="rounded bg-slate-100 px-1 py-0.5 text-[11px] dark:bg-slate-800">{selected.launch_path || '—'}</code>
            </p>
          ) : null}
        </div>
      </div>

      <div>
        <label className={LABEL} htmlFor="sik-pro-scorm-url">Fallback launch URL (optional)</label>
        <input
          id="sik-pro-scorm-url"
          type="url"
          className={FIELD}
          value={values.scormUrl}
          onChange={(e) => set('scormUrl', e.target.value)}
          placeholder="https://example.com/scorm/index_lms.html"
          disabled={hasSelection}
        />
        <p className={HINT}>
          {hasSelection
            ? 'Disabled while a managed package is attached — clear the selection above to use an external URL.'
            : 'Direct link to the unzipped SCORM entry HTML (the file referenced by imsmanifest.xml). Used only when no managed package is selected.'}
        </p>
      </div>

      <ProInteractiveLaunchModeField value={values.launchMode} onChange={(v) => set('launchMode', v)} />
    </ProCard>
  );
}

/**
 * H5P lesson attach wizard — rendered when the active lesson kind is "h5p".
 * Gated by the `scorm_h5p_pro` addon. Surfaces the H5P content picker when the
 * H5P plugin is installed; otherwise falls back to a sanitized iframe embed.
 */
export function ProLessonH5pBlock(props: {
  values: ProLessonValues;
  onChange: (v: ProLessonValues) => void;
}) {
  const { values, onChange } = props;
  const addon = useAddonEnabled('scorm_h5p_pro');
  const ready = Boolean(addon.enabled && addon.licenseOk);
  const set = <K extends keyof ProLessonValues>(k: K, val: ProLessonValues[K]) => onChange({ ...values, [k]: val });

  const [search, setSearch] = useState('');
  const [contents, setContents] = useState<H5pContentRow[]>([]);
  const [available, setAvailable] = useState(true);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!ready) return;
    let cancelled = false;
    setLoading(true);
    setError(null);
    const t = setTimeout(() => {
      const api = getSikshyaApi();
      api
        .get<H5pContentsResponse>(SIKSHYA_ENDPOINTS.pro.h5pContents({ per_page: 25, search }))
        .then((res) => {
          if (cancelled) return;
          setAvailable(Boolean(res?.plugin_available));
          setContents(Array.isArray(res?.rows) ? res.rows : []);
        })
        .catch((err: unknown) => {
          if (cancelled) return;
          setAvailable(false);
          setContents([]);
          setError(err instanceof Error ? err.message : 'Could not load H5P content.');
        })
        .finally(() => {
          if (!cancelled) setLoading(false);
        });
    }, 250);
    return () => {
      cancelled = true;
      clearTimeout(t);
    };
  }, [ready, search]);

  const selected = useMemo(
    () => contents.find((c) => c.id === values.h5pContentId) || null,
    [contents, values.h5pContentId]
  );
  const hasSelection = values.h5pContentId > 0;

  if (!ready) {
    return (
      <ProCard
        title="H5P interactive"
        badge={addon.licenseOk ? 'Addon off' : 'Pro'}
        description={
          addon.licenseOk
            ? 'Turn on the “SCORM / H5P” addon to embed H5P interactives in this lesson.'
            : 'H5P embedding requires Sikshya Pro. Upgrade to embed interactive activities.'
        }
      >
        <p className={HINT}>The lesson is set to “H5P” but the addon is unavailable right now — fields will appear here once it is enabled.</p>
      </ProCard>
    );
  }

  return (
    <ProCard
      title="H5P interactive"
      badge="Pro"
      description={
        available
          ? 'Pick H5P content from the active plugin or paste a sanitized iframe fallback for external H5P hosts.'
          : 'Install and activate the H5P plugin to enable the picker, or paste an iframe fallback below.'
      }
    >
      {available ? (
        <div>
          <label className={LABEL}>H5P content</label>
          <p className={HINT}>Selecting content auto-renders it via the H5P shortcode and tracks results in reports.</p>
          <div className="space-y-2">
            <input
              type="search"
              className={FIELD}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search H5P content by title…"
              aria-label="Search H5P content"
            />
            <div className="max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
              {loading ? (
                <p className="p-3 text-slate-500 dark:text-slate-400">Loading content…</p>
              ) : error ? (
                <p className="p-3 text-rose-600 dark:text-rose-300">{error}</p>
              ) : contents.length === 0 ? (
                <p className="p-3 text-slate-500 dark:text-slate-400">
                  No H5P content yet. Create some inside the H5P plugin, then refresh this picker.
                </p>
              ) : (
                <ul role="listbox" aria-label="H5P content">
                  <li>
                    <button
                      type="button"
                      onClick={() => set('h5pContentId', 0)}
                      className={`block w-full px-3 py-2 text-left text-sm transition ${values.h5pContentId === 0 ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'}`}
                    >
                      <span className="font-medium">No H5P content</span>
                      <span className="ml-2 text-xs text-slate-500 dark:text-slate-400">(use the iframe fallback below)</span>
                    </button>
                  </li>
                  {contents.map((c) => {
                    const isActive = c.id === values.h5pContentId;
                    return (
                      <li key={c.id}>
                        <button
                          type="button"
                          onClick={() => set('h5pContentId', c.id)}
                          className={`block w-full border-t border-slate-100 px-3 py-2 text-left text-sm transition first:border-t-0 dark:border-slate-800 ${isActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'}`}
                        >
                          <span className="block font-medium">{c.title || `Content #${c.id}`}</span>
                          <span className="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                            {c.library || 'unknown library'}
                          </span>
                        </button>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
            {selected ? (
              <p className={HINT}>
                Selected: <strong>{selected.title || `Content #${selected.id}`}</strong>
                {selected.library ? <> · <code className="rounded bg-slate-100 px-1 py-0.5 text-[11px] dark:bg-slate-800">{selected.library}</code></> : null}
              </p>
            ) : null}
          </div>
        </div>
      ) : null}

      <div>
        <label className={LABEL} htmlFor="sik-pro-h5p-embed">Iframe fallback (optional)</label>
        <textarea
          id="sik-pro-h5p-embed"
          rows={5}
          className={`${FIELD} font-mono text-[12px]`}
          value={values.h5pEmbed}
          onChange={(e) => set('h5pEmbed', e.target.value)}
          placeholder="<iframe src=... />"
          disabled={hasSelection}
        />
        <p className={HINT}>
          {hasSelection
            ? 'Disabled while H5P content is selected — clear the picker to paste a fallback iframe.'
            : 'Sanitized at save time: only iframe + structural tags are kept (no scripts).'}
        </p>
      </div>

      <ProInteractiveLaunchModeField value={values.launchMode} onChange={(v) => set('launchMode', v)} />
    </ProCard>
  );
}

/**
 * Shared launch-mode select used by the SCORM and H5P attach wizards.
 */
function ProInteractiveLaunchModeField(props: {
  value: InteractiveLaunchMode;
  onChange: (v: InteractiveLaunchMode) => void;
}) {
  const { value, onChange } = props;
  const helpText = LAUNCH_MODE_OPTIONS.find((o) => o.value === value)?.help ?? '';
  return (
    <div>
      <label className={LABEL} htmlFor="sik-pro-launch-mode">Player display</label>
      <select
        id="sik-pro-launch-mode"
        className={FIELD}
        value={value}
        onChange={(e) => onChange(e.target.value as InteractiveLaunchMode)}
      >
        {LAUNCH_MODE_OPTIONS.map((o) => (
          <option key={o.value || 'inherit'} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
      {helpText ? <p className={HINT}>{helpText}</p> : null}
    </div>
  );
}

/**
 * @deprecated Pre-split combined renderer. The lesson editor now renders the
 *             kind-specific blocks ({@link ProLessonLiveBlock},
 *             {@link ProLessonScormBlock}, {@link ProLessonH5pBlock}) based on
 *             the selected `_sikshya_lesson_type`. Kept temporarily for any
 *             external consumer that imported it.
 */
export function ProLessonFields(props: { values: ProLessonValues; onChange: (v: ProLessonValues) => void }) {
  const { values, onChange } = props;
  const live = useAddonEnabled('live_classes');
  const scorm = useAddonEnabled('scorm_h5p_pro');
  const liveOn = Boolean(live.enabled && live.licenseOk);
  const scormOn = Boolean(scorm.enabled && scorm.licenseOk);
  if (!liveOn && !scormOn) return null;
  return (
    <div className="space-y-4">
      {liveOn ? <ProLessonLiveBlock values={values} onChange={onChange} /> : null}
      {scormOn ? <ProLessonScormBlock values={values} onChange={onChange} /> : null}
      {scormOn ? <ProLessonH5pBlock values={values} onChange={onChange} /> : null}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Quiz — quiz_advanced
// ---------------------------------------------------------------------------

/** Upper bound from `QuizAdvancedAddonSettings` PHP schema (`max` on `max_random_draw_per_quiz`). */
export const QUIZ_ADVANCED_BANK_DRAW_HARD_MAX = 200;

/**
 * Caps stored draw count: 0 = unlimited matching pool; otherwise 1..min(hard max, global addon cap).
 */
export function clampQuizBankCount(raw: number, maxRandomDrawPerQuiz: number): number {
  const cap = Math.max(1, Math.min(QUIZ_ADVANCED_BANK_DRAW_HARD_MAX, Math.floor(maxRandomDrawPerQuiz)));
  const n = Number(raw) || 0;
  if (n <= 0) {
    return 0;
  }
  return Math.min(cap, n);
}

function parseGradebookItemWeight(raw: unknown): number {
  const n = typeof raw === 'number' ? raw : Number(raw);
  if (!Number.isFinite(n) || n <= 0) {
    return 1;
  }
  return Math.min(1000, Math.max(0.01, n));
}

export type ProQuizValues = {
  shuffle: boolean;
  onePerPage: boolean;
  bankTag: string;
  bankCount: number;
  /** Relative weight within quiz component for gradebook averages (`_sikshya_grade_weight`). */
  gradeWeight: number;
};

export const PRO_QUIZ_DEFAULTS: ProQuizValues = {
  shuffle: false,
  onePerPage: false,
  bankTag: '',
  bankCount: 0,
  gradeWeight: 1,
};

export function readProQuizFromMeta(meta: Record<string, unknown> | undefined): ProQuizValues {
  const m = meta ?? {};
  return {
    shuffle: String(m['_sikshya_quiz_shuffle'] ?? '') === '1',
    onePerPage: String(m['_sikshya_quiz_one_per_page'] ?? '') === '1',
    bankTag: String(m['_sikshya_quiz_bank_tag'] ?? ''),
    bankCount: Number(m['_sikshya_quiz_bank_count'] ?? 0) || 0,
    gradeWeight: parseGradebookItemWeight(m['_sikshya_grade_weight']),
  };
}

export function buildProQuizMeta(v: ProQuizValues, maxRandomDrawPerQuiz = QUIZ_ADVANCED_BANK_DRAW_HARD_MAX): Record<string, unknown> {
  return {
    _sikshya_quiz_shuffle: v.shuffle ? '1' : '',
    _sikshya_quiz_one_per_page: v.onePerPage ? '1' : '',
    _sikshya_quiz_bank_tag: v.bankTag.trim(),
    _sikshya_quiz_bank_count: clampQuizBankCount(Number(v.bankCount) || 0, maxRandomDrawPerQuiz),
    _sikshya_grade_weight: parseGradebookItemWeight(v.gradeWeight),
  };
}

/** Gradebook per-item weight; shown when Gradebook addon is on (independent of quiz advanced). */
export function ProGradebookQuizWeightFields(props: {
  gradeWeight: number;
  onGradeWeightChange: (v: number) => void;
}) {
  const gb = useAddonEnabled('gradebook');
  if (!gb.enabled || !gb.licenseOk) return null;
  const { gradeWeight, onGradeWeightChange } = props;
  return (
    <ProCard
      title="Gradebook weight"
      badge="Pro"
      description="How much this quiz counts relative to other quizzes in the same course when computing the quiz portion of the overall grade."
    >
      <div>
        <label className={LABEL} htmlFor="sik-pro-quiz-grade-weight">
          Weight
        </label>
        <input
          id="sik-pro-quiz-grade-weight"
          type="number"
          min={0.01}
          max={1000}
          step="any"
          className={FIELD}
          value={String(gradeWeight)}
          onChange={(e) => onGradeWeightChange(parseGradebookItemWeight(e.target.value))}
        />
        <p className={HINT}>Default 1. Higher values increase this quiz’s influence within the quiz average.</p>
      </div>
    </ProCard>
  );
}

/** Gradebook per-item weight for assignments. */
export function ProGradebookAssignmentWeightFields(props: {
  gradeWeight: number;
  onGradeWeightChange: (v: number) => void;
}) {
  const gb = useAddonEnabled('gradebook');
  if (!gb.enabled || !gb.licenseOk) return null;
  const { gradeWeight, onGradeWeightChange } = props;
  return (
    <ProCard
      title="Gradebook weight"
      badge="Pro"
      description="How much this assignment counts relative to other graded assignments in the same course."
    >
      <div>
        <label className={LABEL} htmlFor="sik-pro-asg-grade-weight">
          Weight
        </label>
        <input
          id="sik-pro-asg-grade-weight"
          type="number"
          min={0.01}
          max={1000}
          step="any"
          className={FIELD}
          value={String(gradeWeight)}
          onChange={(e) => onGradeWeightChange(parseGradebookItemWeight(e.target.value))}
        />
        <p className={HINT}>Default 1. Higher values increase this assignment’s influence within the assignment average.</p>
      </div>
    </ProCard>
  );
}

export function ProQuizFields(props: { values: ProQuizValues; onChange: (v: ProQuizValues) => void }) {
  const { values, onChange } = props;
  const adv = useAddonEnabled('quiz_advanced');
  if (!adv.enabled || !adv.licenseOk) return null;

  const set = <K extends keyof ProQuizValues>(k: K, val: ProQuizValues[K]) => onChange({ ...values, [k]: val });

  return (
    <ProCard title="Advanced quiz behaviour" badge="Pro" description="Shuffle, paginate, or draw random questions from a pool.">
      <div className="space-y-3">
        <label className="flex items-start gap-2 text-sm text-slate-800 dark:text-slate-100">
          <input
            type="checkbox"
            className="mt-1 h-4 w-4 rounded border-slate-300"
            checked={values.shuffle}
            onChange={(e) => set('shuffle', e.target.checked)}
          />
          <span>
            <span className="font-medium">Shuffle questions</span>
            <span className="block text-xs text-slate-500 dark:text-slate-400">Random order on each attempt.</span>
          </span>
        </label>
        <label className="flex items-start gap-2 text-sm text-slate-800 dark:text-slate-100">
          <input
            type="checkbox"
            className="mt-1 h-4 w-4 rounded border-slate-300"
            checked={values.onePerPage}
            onChange={(e) => set('onePerPage', e.target.checked)}
          />
          <span>
            <span className="font-medium">One question per page</span>
            <span className="block text-xs text-slate-500 dark:text-slate-400">Show Next / Previous controls between questions.</span>
          </span>
        </label>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className={LABEL} htmlFor="sik-pro-quiz-bank-tag">Question pool tag</label>
          <input
            id="sik-pro-quiz-bank-tag"
            className={FIELD}
            value={values.bankTag}
            onChange={(e) => set('bankTag', e.target.value)}
            placeholder="unit-1"
          />
          <p className={HINT}>When set, draws random questions whose pool tag matches, instead of attached questions.</p>
        </div>
        <div>
          <label className={LABEL} htmlFor="sik-pro-quiz-bank-count">Random questions to draw</label>
          <input
            id="sik-pro-quiz-bank-count"
            type="number"
            min={0}
            max={drawCap}
            className={FIELD}
            value={String(values.bankCount || '')}
            onChange={(e) => set('bankCount', Number(e.target.value) || 0)}
          />
          <p className={HINT}>
            0 means use all matching questions. Per-quiz draw is capped at {drawCap} (global setting under Advanced quiz).
          </p>
        </div>
      </div>
    </ProCard>
  );
}

// ---------------------------------------------------------------------------
// Question — quiz_advanced (pool tag)
// ---------------------------------------------------------------------------

export type ProQuestionValues = { poolTag: string };
export const PRO_QUESTION_DEFAULTS: ProQuestionValues = { poolTag: '' };

export function readProQuestionFromMeta(meta: Record<string, unknown> | undefined): ProQuestionValues {
  return { poolTag: String((meta ?? {})['_sikshya_question_pool_tag'] ?? '') };
}
export function buildProQuestionMeta(v: ProQuestionValues): Record<string, unknown> {
  return { _sikshya_question_pool_tag: v.poolTag.trim() };
}

export function ProQuestionFields(props: { values: ProQuestionValues; onChange: (v: ProQuestionValues) => void }) {
  const { values, onChange } = props;
  const adv = useAddonEnabled('quiz_advanced');
  if (!adv.enabled || !adv.licenseOk) return null;

  return (
    <ProCard title="Question pool" badge="Pro" description="Tag this question so quizzes drawing from this pool can pick it up at random.">
      <div>
        <label className={LABEL} htmlFor="sik-pro-question-pool-tag">Pool tag</label>
        <input
          id="sik-pro-question-pool-tag"
          className={FIELD}
          value={values.poolTag}
          onChange={(e) => onChange({ poolTag: e.target.value })}
          placeholder="e.g. unit-1"
        />
      </div>
    </ProCard>
  );
}

// ---------------------------------------------------------------------------
// Assignment — assignments_advanced
// ---------------------------------------------------------------------------

export type ProAssignmentRubricRow = {
  id: string;
  title: string;
  weight: number;
  description: string;
  /** One checklist line per row (shown to learners under the criterion). */
  checks: string;
};

export type ProAssignmentValues = {
  rubricRows: ProAssignmentRubricRow[];
  allowedExts: string;
  allowLate: boolean;
  requireText: boolean;
  /** When true, learners may submit again after the submission is marked graded. */
  allowResubmit: boolean;
  minFiles: number;
  maxFiles: number;
  /** Relative weight within assignment component (`_sikshya_grade_weight`). */
  gradeWeight: number;
};

export const PRO_ASSIGNMENT_DEFAULTS: ProAssignmentValues = {
  rubricRows: [],
  allowedExts: '',
  allowLate: false,
  requireText: false,
  allowResubmit: false,
  minFiles: 0,
  maxFiles: 0,
  gradeWeight: 1,
};

function newRubricRow(): ProAssignmentRubricRow {
  return {
    id: `r_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
    title: '',
    weight: 0,
    description: '',
    checks: '',
  };
}

function parseRubricMeta(raw: string): ProAssignmentRubricRow[] {
  const t = raw.trim();
  if (!t) return [];
  try {
    const v = JSON.parse(t) as unknown;
    const rows: ProAssignmentRubricRow[] = [];
    const pushObj = (obj: Record<string, unknown>, i: number) => {
      const title = String(obj.title ?? obj.label ?? obj.name ?? '').trim();
      if (!title) return;
      const weight = Number(obj.weight ?? obj.points ?? 0);
      const description = String(obj.description ?? obj.desc ?? '');
      const checksArr = Array.isArray(obj.checks)
        ? obj.checks
        : Array.isArray(obj.checklist)
          ? obj.checklist
          : [];
      const checks = checksArr
        .filter((x): x is string => typeof x === 'string')
        .map((s) => s.trim())
        .filter(Boolean)
        .join('\n');
      rows.push({
        id: `r${i}_${title.slice(0, 12)}`,
        title,
        weight: Number.isFinite(weight) ? weight : 0,
        description,
        checks,
      });
    };
    if (Array.isArray(v)) {
      v.forEach((row, i) => {
        if (row && typeof row === 'object') pushObj(row as Record<string, unknown>, i);
      });
      return rows;
    }
    if (v && typeof v === 'object' && Array.isArray((v as { criteria?: unknown }).criteria)) {
      ((v as { criteria: unknown[] }).criteria || []).forEach((row, i) => {
        if (row && typeof row === 'object') pushObj(row as Record<string, unknown>, i);
      });
      return rows;
    }
  } catch {
    return [];
  }
  return [];
}

function buildRubricJson(rows: ProAssignmentRubricRow[]): string {
  const criteria = rows
    .filter((r) => r.title.trim())
    .map((r) => {
      const checks = r.checks
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean);
      const o: Record<string, unknown> = {
        title: r.title.trim(),
        weight: Math.max(0, Math.round(r.weight)),
      };
      if (r.description.trim()) o.description = r.description.trim();
      if (checks.length) o.checks = checks;
      return o;
    });
  if (criteria.length === 0) return '';
  return JSON.stringify({ version: 1, criteria });
}

export function readProAssignmentFromMeta(meta: Record<string, unknown> | undefined): ProAssignmentValues {
  const m = meta ?? {};
  const rubricRows = parseRubricMeta(String(m['_sikshya_rubric_json'] ?? ''));
  return {
    rubricRows,
    allowedExts: String(m['_sikshya_allowed_file_extensions'] ?? ''),
    allowLate: String(m['_sikshya_allow_late'] ?? '') === '1',
    requireText: String(m['_sikshya_require_text'] ?? '') === '1',
    allowResubmit: String(m['_sikshya_assignment_allow_resubmit'] ?? '') === '1',
    minFiles: Math.max(0, Math.min(50, Number(m['_sikshya_assignment_min_files'] ?? 0) || 0)),
    maxFiles: Math.max(0, Math.min(50, Number(m['_sikshya_assignment_max_files'] ?? 0) || 0)),
    gradeWeight: parseGradebookItemWeight(m['_sikshya_grade_weight']),
  };
}

export function buildProAssignmentMeta(v: ProAssignmentValues): Record<string, unknown> {
  const rubricClean = buildRubricJson(v.rubricRows);
  const exts = v.allowedExts
    .split(',')
    .map((x) => x.trim().toLowerCase())
    .filter(Boolean)
    .join(',');
  return {
    _sikshya_rubric_json: rubricClean,
    _sikshya_allowed_file_extensions: exts,
    _sikshya_allow_late: v.allowLate ? '1' : '',
    _sikshya_require_text: v.requireText ? '1' : '',
    _sikshya_assignment_allow_resubmit: v.allowResubmit ? '1' : '',
    _sikshya_assignment_min_files: Math.max(0, Math.min(50, Math.floor(v.minFiles))),
    _sikshya_assignment_max_files: Math.max(0, Math.min(50, Math.floor(v.maxFiles))),
    _sikshya_grade_weight: parseGradebookItemWeight(v.gradeWeight),
  };
}

export function ProAssignmentFields(props: {
  values: ProAssignmentValues;
  onChange: (v: ProAssignmentValues) => void;
}) {
  const { values, onChange } = props;
  const adv = useAddonEnabled('assignments_advanced');
  if (!adv.enabled || !adv.licenseOk) return null;

  const set = <K extends keyof ProAssignmentValues>(k: K, val: ProAssignmentValues[K]) =>
    onChange({ ...values, [k]: val });

  const updateRow = (id: string, patch: Partial<ProAssignmentRubricRow>) => {
    onChange({
      ...values,
      rubricRows: values.rubricRows.map((r) => (r.id === id ? { ...r, ...patch } : r)),
    });
  };

  const removeRow = (id: string) => {
    onChange({ ...values, rubricRows: values.rubricRows.filter((r) => r.id !== id) });
  };

  const addRow = () => {
    onChange({ ...values, rubricRows: [...values.rubricRows, newRubricRow()] });
  };

  return (
    <ProCard
      title="Advanced assignment options"
      badge="Pro"
      description="Rubric criteria (with optional learner checklist lines), file rules, and submission behaviour."
    >
      <div>
        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
          <span className={LABEL}>Rubric criteria</span>
          <button type="button" className="text-xs font-semibold text-brand-600 hover:underline" onClick={addRow}>
            + Add criterion
          </button>
        </div>
        <p className={HINT}>
          Each row is one grading dimension. Weight is shown to learners as points or share of the rubric. Checklist lines
          appear as a short bullet list under that row.
        </p>
        {values.rubricRows.length === 0 ? (
          <p className="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
            No rubric rows yet. Add a row for each criterion (e.g. Structure, Accuracy) or leave empty for instructions-only
            assignments.
          </p>
        ) : (
          <ul className="space-y-3">
            {values.rubricRows.map((row) => (
              <li
                key={row.id}
                className="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/50"
              >
                <div className="mb-2 flex flex-wrap items-center justify-end gap-2">
                  <button
                    type="button"
                    className="text-xs font-medium text-red-600 hover:underline"
                    onClick={() => removeRow(row.id)}
                  >
                    Remove
                  </button>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div>
                    <label className={LABEL} htmlFor={`sik-pro-r-${row.id}-t`}>
                      Criterion title
                    </label>
                    <input
                      id={`sik-pro-r-${row.id}-t`}
                      className={FIELD}
                      value={row.title}
                      onChange={(e) => updateRow(row.id, { title: e.target.value })}
                      placeholder="e.g. Clear thesis"
                    />
                  </div>
                  <div>
                    <label className={LABEL} htmlFor={`sik-pro-r-${row.id}-w`}>
                      Weight / points
                    </label>
                    <input
                      id={`sik-pro-r-${row.id}-w`}
                      type="number"
                      min={0}
                      className={FIELD}
                      value={row.weight}
                      onChange={(e) => updateRow(row.id, { weight: Number(e.target.value) })}
                    />
                  </div>
                </div>
                <div className="mt-2">
                  <label className={LABEL} htmlFor={`sik-pro-r-${row.id}-d`}>
                    Learner description (optional)
                  </label>
                  <textarea
                    id={`sik-pro-r-${row.id}-d`}
                    rows={2}
                    className={FIELD}
                    value={row.description}
                    onChange={(e) => updateRow(row.id, { description: e.target.value })}
                    placeholder="What you expect in this area…"
                  />
                </div>
                <div className="mt-2">
                  <label className={LABEL} htmlFor={`sik-pro-r-${row.id}-c`}>
                    Checklist lines (optional)
                  </label>
                  <textarea
                    id={`sik-pro-r-${row.id}-c`}
                    rows={3}
                    className={`${FIELD} font-mono text-[12px]`}
                    value={row.checks}
                    onChange={(e) => updateRow(row.id, { checks: e.target.value })}
                    placeholder={'One line per checklist item\ne.g. Uses at least two sources'}
                  />
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
      <div>
        <label className={LABEL} htmlFor="sik-pro-exts">
          Allowed file extensions
        </label>
        <input
          id="sik-pro-exts"
          className={FIELD}
          value={values.allowedExts}
          onChange={(e) => set('allowedExts', e.target.value)}
          placeholder="pdf,docx,zip"
        />
        <p className={HINT}>Comma separated, lower-case, no dots. Leave empty to use the global default from add-on settings.</p>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className={LABEL} htmlFor="sik-pro-minf">
            Minimum files
          </label>
          <input
            id="sik-pro-minf"
            type="number"
            min={0}
            max={50}
            className={FIELD}
            value={values.minFiles}
            onChange={(e) => set('minFiles', Number(e.target.value))}
          />
          <p className={HINT}>0 = no minimum.</p>
        </div>
        <div>
          <label className={LABEL} htmlFor="sik-pro-maxf">
            Maximum files (0 = global cap only)
          </label>
          <input
            id="sik-pro-maxf"
            type="number"
            min={0}
            max={50}
            className={FIELD}
            value={values.maxFiles}
            onChange={(e) => set('maxFiles', Number(e.target.value))}
          />
        </div>
      </div>
      <div className="space-y-2">
        <label className="flex items-start gap-2 text-sm text-slate-800 dark:text-slate-100">
          <input
            type="checkbox"
            className="mt-1 h-4 w-4 rounded border-slate-300"
            checked={values.allowLate}
            onChange={(e) => set('allowLate', e.target.checked)}
          />
          <span>Allow late submissions</span>
        </label>
        <label className="flex items-start gap-2 text-sm text-slate-800 dark:text-slate-100">
          <input
            type="checkbox"
            className="mt-1 h-4 w-4 rounded border-slate-300"
            checked={values.requireText}
            onChange={(e) => set('requireText', e.target.checked)}
          />
          <span>Require written response in addition to file uploads</span>
        </label>
        <label className="flex items-start gap-2 text-sm text-slate-800 dark:text-slate-100">
          <input
            type="checkbox"
            className="mt-1 h-4 w-4 rounded border-slate-300"
            checked={values.allowResubmit}
            onChange={(e) => set('allowResubmit', e.target.checked)}
          />
          <span>Allow resubmission after grading</span>
        </label>
      </div>
    </ProCard>
  );
}
