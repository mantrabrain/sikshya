import { type ReactNode } from 'react';
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

export type ProLessonValues = {
  liveUrl: string;
  liveProvider: string;
  liveStart: string;
  liveDuration: number;
  scormUrl: string;
  h5pEmbed: string;
};

export const PRO_LESSON_DEFAULTS: ProLessonValues = {
  liveUrl: '',
  liveProvider: '',
  liveStart: '',
  liveDuration: 60,
  scormUrl: '',
  h5pEmbed: '',
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
  return {
    liveUrl: String(m['_sikshya_live_meeting_url'] ?? ''),
    liveProvider: String(m['_sikshya_live_provider'] ?? ''),
    liveStart: isoToLocal(String(m['_sikshya_live_start_at'] ?? '')),
    liveDuration: Number(m['_sikshya_live_duration_minutes'] ?? 60) || 60,
    scormUrl: String(m['_sikshya_scorm_launch_url'] ?? ''),
    h5pEmbed: String(m['_sikshya_h5p_embed_html'] ?? ''),
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
  return {
    _sikshya_live_meeting_url: isLive ? v.liveUrl.trim() : '',
    _sikshya_live_provider: isLive ? v.liveProvider : '',
    _sikshya_live_start_at: isLive && v.liveStart ? localToIso(v.liveStart) : '',
    _sikshya_live_duration_minutes: isLive ? Math.max(0, Math.min(720, Number(v.liveDuration) || 0)) : 0,
    _sikshya_scorm_launch_url: isScorm ? v.scormUrl.trim() : '',
    _sikshya_h5p_embed_html: isH5p ? v.h5pEmbed : '',
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
    _sikshya_live_duration_minutes: Math.max(0, Math.min(720, Number(v.liveDuration) || 0)),
    _sikshya_scorm_launch_url: v.scormUrl.trim(),
    _sikshya_h5p_embed_html: v.h5pEmbed,
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
    <ProCard title="Live class" badge="Pro" description="Schedule a Zoom / Meet / Teams / Jitsi session that learners join from this lesson.">
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
            <option value="zoom">Zoom</option>
            <option value="google_meet">Google Meet</option>
            <option value="teams">Microsoft Teams</option>
            <option value="jitsi">Jitsi</option>
            <option value="other">Other</option>
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
          <p className={HINT}>Shown in the learner calendar and the "Upcoming sessions" block on the course page.</p>
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
      </div>
    </ProCard>
  );
}

/**
 * SCORM launch field — rendered when the active lesson kind is "scorm".
 * Gated by the `scorm_h5p_pro` addon.
 */
export function ProLessonScormBlock(props: {
  values: ProLessonValues;
  onChange: (v: ProLessonValues) => void;
}) {
  const { values, onChange } = props;
  const addon = useAddonEnabled('scorm_h5p_pro');
  const ready = Boolean(addon.enabled && addon.licenseOk);
  const set = <K extends keyof ProLessonValues>(k: K, val: ProLessonValues[K]) => onChange({ ...values, [k]: val });

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
      />
    );
  }

  return (
    <ProCard title="SCORM package" badge="Pro" description="Render an unzipped SCORM package as the lesson body.">
      <div>
        <label className={LABEL} htmlFor="sik-pro-scorm-url">SCORM launch URL</label>
        <input
          id="sik-pro-scorm-url"
          type="url"
          className={FIELD}
          value={values.scormUrl}
          onChange={(e) => set('scormUrl', e.target.value)}
          placeholder="https://example.com/scorm/imsmanifest.html"
        />
        <p className={HINT}>Direct link to the unzipped SCORM entry HTML (typically the file referenced by <code>imsmanifest.xml</code>).</p>
      </div>
    </ProCard>
  );
}

/**
 * H5P embed field — rendered when the active lesson kind is "h5p".
 * Gated by the same `scorm_h5p_pro` addon as SCORM.
 */
export function ProLessonH5pBlock(props: {
  values: ProLessonValues;
  onChange: (v: ProLessonValues) => void;
}) {
  const { values, onChange } = props;
  const addon = useAddonEnabled('scorm_h5p_pro');
  const ready = Boolean(addon.enabled && addon.licenseOk);
  const set = <K extends keyof ProLessonValues>(k: K, val: ProLessonValues[K]) => onChange({ ...values, [k]: val });

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
      />
    );
  }

  return (
    <ProCard title="H5P interactive" badge="Pro" description="Render an H5P interactive as the lesson body.">
      <div>
        <label className={LABEL} htmlFor="sik-pro-h5p-embed">H5P embed HTML</label>
        <textarea
          id="sik-pro-h5p-embed"
          rows={5}
          className={`${FIELD} font-mono text-[12px]`}
          value={values.h5pEmbed}
          onChange={(e) => set('h5pEmbed', e.target.value)}
          placeholder="<iframe src=... />"
        />
        <p className={HINT}>Paste the &lt;iframe&gt; snippet from your H5P plugin or h5p.org export.</p>
      </div>
    </ProCard>
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

export function buildProQuizMeta(v: ProQuizValues): Record<string, unknown> {
  return {
    _sikshya_quiz_shuffle: v.shuffle ? '1' : '',
    _sikshya_quiz_one_per_page: v.onePerPage ? '1' : '',
    _sikshya_quiz_bank_tag: v.bankTag.trim(),
    _sikshya_quiz_bank_count: Math.max(0, Math.min(200, Number(v.bankCount) || 0)),
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
            max={200}
            className={FIELD}
            value={String(values.bankCount || '')}
            onChange={(e) => set('bankCount', Number(e.target.value) || 0)}
          />
          <p className={HINT}>0 means use all matching questions.</p>
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

export type ProAssignmentValues = {
  rubricJson: string;
  allowedExts: string;
  allowLate: boolean;
  requireText: boolean;
  /** Relative weight within assignment component (`_sikshya_grade_weight`). */
  gradeWeight: number;
};

export const PRO_ASSIGNMENT_DEFAULTS: ProAssignmentValues = {
  rubricJson: '',
  allowedExts: '',
  allowLate: false,
  requireText: false,
  gradeWeight: 1,
};

export function readProAssignmentFromMeta(meta: Record<string, unknown> | undefined): ProAssignmentValues {
  const m = meta ?? {};
  return {
    rubricJson: String(m['_sikshya_rubric_json'] ?? ''),
    allowedExts: String(m['_sikshya_allowed_file_extensions'] ?? ''),
    allowLate: String(m['_sikshya_allow_late'] ?? '') === '1',
    requireText: String(m['_sikshya_require_text'] ?? '') === '1',
    gradeWeight: parseGradebookItemWeight(m['_sikshya_grade_weight']),
  };
}

export function buildProAssignmentMeta(v: ProAssignmentValues): Record<string, unknown> {
  let rubricClean = '';
  if (v.rubricJson.trim() !== '') {
    try {
      const parsed = JSON.parse(v.rubricJson);
      if (Array.isArray(parsed)) rubricClean = JSON.stringify(parsed);
    } catch {
      rubricClean = '';
    }
  }
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

  return (
    <ProCard
      title="Advanced assignment options"
      badge="Pro"
      description="Grading rubric, allowed file types, and submission rules for this assignment."
    >
      <div>
        <label className={LABEL} htmlFor="sik-pro-rubric">Rubric (JSON)</label>
        <textarea
          id="sik-pro-rubric"
          rows={6}
          className={`${FIELD} font-mono text-[12px]`}
          value={values.rubricJson}
          onChange={(e) => set('rubricJson', e.target.value)}
          placeholder='[{"name":"Clarity","weight":30},{"name":"Correctness","weight":50},{"name":"Presentation","weight":20}]'
        />
        <p className={HINT}>Array of &#123;name, weight&#125; objects. Weights should sum to 100.</p>
      </div>
      <div>
        <label className={LABEL} htmlFor="sik-pro-exts">Allowed file extensions</label>
        <input
          id="sik-pro-exts"
          className={FIELD}
          value={values.allowedExts}
          onChange={(e) => set('allowedExts', e.target.value)}
          placeholder="pdf,docx,zip"
        />
        <p className={HINT}>Comma separated, lower-case, no dots. Leave empty for no restriction.</p>
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
          <span>Require submission text in addition to file uploads</span>
        </label>
      </div>
    </ProCard>
  );
}
