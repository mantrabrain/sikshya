import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { appViewHref } from '../lib/appUrl';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { NavItem, SikshyaReactConfig } from '../types';

type InstructorRow = {
  id: number;
  course_id: number;
  user_id: number;
  role: string;
  revenue_share: number;
  display_name?: string;
  user_email?: string;
  avatar_url?: string;
  course_title?: string;
};

type Resp = {
  ok?: boolean;
  instructors?: InstructorRow[];
  share_total?: number;
  warnings?: string[];
};
type AllStaffResp = { ok?: boolean; rows?: InstructorRow[] };
type EarningsRow = { id: number; order_item_id: number; amount: number; status: string; created_at: string };
type EarningsResp = { ok?: boolean; rows?: EarningsRow[]; total?: number };
type UserOpt = { id: number; name: string; email?: string };
type CourseOpt = { id: number; title: string };

/** Matches course builder instructor picker (WP roles that can be assigned as staff). */
const STAFF_SEARCH_ROLES = ['administrator', 'editor', 'author', 'sikshya_instructor'] as const;

export function CourseTeamPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const featureOk = isFeatureEnabled(config, 'multi_instructor');
  const addon = useAddonEnabled('multi_instructor');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';
  const qCourse = config.query?.course_id;
  const [courseId, setCourseId] = useState(qCourse || '');
  const [courseQuery, setCourseQuery] = useState('');
  const [courseDropdownOpen, setCourseDropdownOpen] = useState(false);
  const [newUserId, setNewUserId] = useState('');
  const [userQuery, setUserQuery] = useState('');
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);
  const [share, setShare] = useState('0');
  const [newMemberRole, setNewMemberRole] = useState<'co_instructor' | 'lead'>('co_instructor');
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [earningsUserId, setEarningsUserId] = useState('');
  const [ledgerUserQuery, setLedgerUserQuery] = useState('');
  const [ledgerDropdownOpen, setLedgerDropdownOpen] = useState(false);
  const [ledgerPickedLabel, setLedgerPickedLabel] = useState('');
  const debouncedLedgerQuery = useDebouncedValue(ledgerUserQuery, 240);
  const [courseAuthorId, setCourseAuthorId] = useState<number | null>(null);
  const [rowShareDraft, setRowShareDraft] = useState<Record<number, string>>({});
  const [rowRoleDraft, setRowRoleDraft] = useState<Record<number, string>>({});
  const [ledgerBusyId, setLedgerBusyId] = useState<number | null>(null);
  const [staffFilter, setStaffFilter] = useState('');
  const debouncedStaffFilter = useDebouncedValue(staffFilter, 200);

  const canManageLedger = Boolean(config.multiInstructor?.canManageLedger);

  const debouncedUserQuery = useDebouncedValue(userQuery, 240);
  const debouncedCourseQuery = useDebouncedValue(courseQuery, 240);

  const courseSearch = useAsyncData(
    async () => {
      if (!enabled) return { data: [] as CourseOpt[] };
      if (!courseDropdownOpen) return { data: [] as CourseOpt[] };
      const q = debouncedCourseQuery.trim();
      const params = new URLSearchParams({
        per_page: '15',
        page: '1',
        status: 'any',
        _fields: 'id,title',
      });
      if (q) params.set('search', q);
      const r = await getWpApi().getWithTotal<Array<{ id: number; title?: { rendered?: string } | string }>>(
        `/sik_course?${params.toString()}`
      );
      const raw = Array.isArray(r.data) ? r.data : [];
      const out: CourseOpt[] = raw.map((p) => {
        const titleRendered =
          typeof p.title === 'object' && p.title && 'rendered' in p.title
            ? String(p.title.rendered || '')
            : String(p.title || '');
        return { id: p.id, title: titleRendered.replace(/<[^>]+>/g, '').trim() || `Course #${p.id}` };
      });
      return { data: out };
    },
    [enabled, debouncedCourseQuery, courseDropdownOpen]
  );

  const userSearch = useAsyncData(
    async () => {
      if (!enabled) return { data: [] as UserOpt[] };
      if (!userDropdownOpen) return { data: [] as UserOpt[] };
      const q = debouncedUserQuery.trim();
      const base = new URLSearchParams({
        per_page: '12',
        page: '1',
        context: 'edit',
        orderby: 'name',
        order: 'asc',
      });
      if (q) base.set('search', q);
      const lists = await Promise.all(
        STAFF_SEARCH_ROLES.map(async (role) => {
          const p = new URLSearchParams(base);
          p.set('role', role);
          try {
            return await getWpApi().get<Array<{ id: number; name: string; email?: string }>>(`/users?${p.toString()}`);
          } catch {
            return [];
          }
        })
      );
      const byId = new Map<number, UserOpt>();
      for (const list of lists) {
        const arr = Array.isArray(list) ? list : [];
        for (const u of arr) {
          if (u && typeof u.id === 'number' && u.id > 0 && !byId.has(u.id)) {
            byId.set(u.id, {
              id: u.id,
              name: u.name || `User #${u.id}`,
              email: typeof u.email === 'string' && u.email ? u.email : undefined,
            });
          }
        }
      }
      const merged = Array.from(byId.values()).sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));
      return { data: merged.slice(0, 28) };
    },
    [enabled, debouncedUserQuery, userDropdownOpen]
  );

  const pickedUserLabel = useMemo(() => {
    const uid = parseInt(newUserId, 10);
    if (!Number.isFinite(uid) || uid <= 0) return null;
    const hit = (userSearch.data?.data || []).find((u) => u.id === uid);
    if (hit) {
      return hit.email ? `${hit.name} · ${hit.email}` : hit.name;
    }
    return `User #${uid}`;
  }, [newUserId, userSearch.data?.data]);

  const ledgerUserSearch = useAsyncData(
    async () => {
      if (!enabled) return { data: [] as UserOpt[] };
      if (!ledgerDropdownOpen) return { data: [] as UserOpt[] };
      const q = debouncedLedgerQuery.trim();
      const base = new URLSearchParams({
        per_page: '15',
        page: '1',
        context: 'edit',
        orderby: 'name',
        order: 'asc',
      });
      if (q) base.set('search', q);
      const lists = await Promise.all(
        STAFF_SEARCH_ROLES.map(async (role) => {
          const p = new URLSearchParams(base);
          p.set('role', role);
          try {
            return await getWpApi().get<Array<{ id: number; name: string; email?: string }>>(`/users?${p.toString()}`);
          } catch {
            return [];
          }
        })
      );
      const byId = new Map<number, UserOpt>();
      for (const list of lists) {
        const arr = Array.isArray(list) ? list : [];
        for (const u of arr) {
          if (u && typeof u.id === 'number' && u.id > 0 && !byId.has(u.id)) {
            byId.set(u.id, {
              id: u.id,
              name: u.name || `User #${u.id}`,
              email: typeof u.email === 'string' && u.email ? u.email : undefined,
            });
          }
        }
      }
      const merged = Array.from(byId.values()).sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));
      return { data: merged.slice(0, 32) };
    },
    [enabled, debouncedLedgerQuery, ledgerDropdownOpen]
  );

  useEffect(() => {
    if (!ledgerDropdownOpen) return;
    const onDoc = (e: MouseEvent) => {
      const t = e.target as HTMLElement | null;
      if (!t) return;
      if (!t.closest('[data-ledger-instructor-picker="1"]')) {
        setLedgerDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [ledgerDropdownOpen]);

  useEffect(() => {
    if (!userDropdownOpen) return;
    const onDoc = (e: MouseEvent) => {
      const t = e.target as HTMLElement | null;
      if (!t) return;
      if (!t.closest('[data-instructor-picker="1"]')) {
        setUserDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [userDropdownOpen]);

  useEffect(() => {
    if (!courseDropdownOpen) return;
    const onDoc = (e: MouseEvent) => {
      const t = e.target as HTMLElement | null;
      if (!t) return;
      if (!t.closest('[data-course-picker="1"]')) {
        setCourseDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [courseDropdownOpen]);

  const earningsLoader = useCallback(async () => {
    if (!enabled) return { ok: true, rows: [] as EarningsRow[], total: 0 };
    const uid = parseInt(earningsUserId, 10);
    if (!Number.isFinite(uid) || uid <= 0) return { ok: true, rows: [] as EarningsRow[], total: 0 };
    return getSikshyaApi().get<EarningsResp>(SIKSHYA_ENDPOINTS.pro.multiInstructorEarnings(uid));
  }, [earningsUserId, enabled]);

  const {
    loading: earningsLoading,
    data: earningsData,
    error: earningsError,
    refetch: refetchEarnings,
  } = useAsyncData(earningsLoader, [earningsUserId, enabled]);

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, instructors: [] as InstructorRow[], share_total: 0, warnings: [] as string[] };
    }
    const cid = parseInt(courseId, 10);
    if (!Number.isFinite(cid) || cid <= 0) {
      const r = await getSikshyaApi().get<AllStaffResp>(SIKSHYA_ENDPOINTS.pro.multiInstructorCourseStaffAll({ per_page: 750, page: 1 }));
      return { ok: true, instructors: r?.rows || [], share_total: 0, warnings: [] };
    }
    return getSikshyaApi().get<Resp>(SIKSHYA_ENDPOINTS.pro.multiInstructorCourseStaff(cid));
  }, [courseId, enabled]);

  const { loading, data, error, refetch } = useAsyncData(loader, [courseId, enabled]);
  const rowsBase = data?.instructors ?? [];
  const shareTotal = data?.share_total ?? 0;
  const warnings = data?.warnings ?? [];
  const isGlobal = !courseId || parseInt(courseId, 10) <= 0;

  const rows = useMemo(() => {
    const q = debouncedStaffFilter.trim().toLowerCase();
    if (!q) return rowsBase;
    return rowsBase.filter((r) => {
      const name = String(r.display_name || '').toLowerCase();
      const email = String(r.user_email || '').toLowerCase();
      const courseTitle = String(r.course_title || '').toLowerCase();
      return name.includes(q) || email.includes(q) || courseTitle.includes(q);
    });
  }, [rowsBase, debouncedStaffFilter]);

  useEffect(() => {
    const nextShare: Record<number, string> = {};
    const nextRole: Record<number, string> = {};
    for (const r of rowsBase) {
      nextShare[r.user_id] = String(Number(r.revenue_share).toFixed(2));
      nextRole[r.user_id] = r.role === 'lead' ? 'lead' : 'co_instructor';
    }
    setRowShareDraft(nextShare);
    setRowRoleDraft(nextRole);
  }, [rowsBase]);

  const loadCourseAuthor = useCallback(async (cid: number) => {
    if (!enabled || cid <= 0) {
      setCourseAuthorId(null);
      return;
    }
    try {
      const p = await getWpApi().get<{ author?: number }>(
        `/sik_course/${encodeURIComponent(String(cid))}?context=edit&_fields=author`
      );
      const a = typeof p?.author === 'number' ? p.author : null;
      setCourseAuthorId(a && a > 0 ? a : null);
    } catch {
      setCourseAuthorId(null);
    }
  }, [enabled]);

  useEffect(() => {
    const cid = parseInt(courseId, 10);
    if (!Number.isFinite(cid) || cid <= 0) {
      setCourseAuthorId(null);
      return;
    }
    void loadCourseAuthor(cid);
  }, [courseId, loadCourseAuthor]);

  /** When course is chosen (including deep link `?course_id=`), load its title into the search field. */
  useEffect(() => {
    const cid = parseInt(courseId, 10);
    if (!enabled || !Number.isFinite(cid) || cid <= 0) {
      return;
    }
    let cancelled = false;
    void (async () => {
      try {
        const p = await getWpApi().get<{ id?: number; title?: { rendered?: string } | string }>(
          `/sik_course/${encodeURIComponent(String(cid))}?_fields=id,title`
        );
        if (cancelled || !p) return;
        const titleRendered =
          typeof p.title === 'object' && p.title && 'rendered' in p.title
            ? String(p.title.rendered || '')
            : String((p as { title?: string }).title || '');
        const t = titleRendered.replace(/<[^>]+>/g, '').trim() || `Course #${cid}`;
        setCourseQuery(t);
      } catch {
        /* keep typed query */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [courseId, enabled]);

  const addInstructor = async (e: FormEvent) => {
    e.preventDefault();
    setMsg(null);
    const cid = parseInt(courseId, 10);
    const uid = parseInt(newUserId, 10);
    if (!Number.isFinite(cid) || cid <= 0 || !Number.isFinite(uid) || uid <= 0) {
      setMsg('Pick a course and an instructor.');
      return;
    }
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.multiInstructorCourseStaffWrite, {
        course_id: cid,
        user_id: uid,
        revenue_share: parseFloat(share) || 0,
        role: newMemberRole,
      });
      setMsg('Instructor saved.');
      setNewUserId('');
      setUserQuery('');
      setNewMemberRole('co_instructor');
      refetch();
    } catch (err) {
      setMsg(err instanceof Error ? err.message : 'Request failed');
    } finally {
      setSaving(false);
    }
  };

  const saveStaffRow = async (userId: number) => {
    const cid = parseInt(courseId, 10);
    if (!Number.isFinite(cid) || cid <= 0) return;
    const raw = rowShareDraft[userId] ?? '0';
    const revenueShare = parseFloat(raw);
    if (!Number.isFinite(revenueShare)) {
      setMsg('Enter a valid percentage.');
      return;
    }
    const roleRaw = rowRoleDraft[userId] ?? 'co_instructor';
    const role = roleRaw === 'lead' ? 'lead' : 'co_instructor';
    setSaving(true);
    setMsg(null);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.multiInstructorCourseStaffWrite, {
        course_id: cid,
        user_id: userId,
        revenue_share: revenueShare,
        role,
      });
      setMsg('Staff row updated.');
      refetch();
    } catch (err) {
      setMsg(err instanceof Error ? err.message : 'Request failed');
    } finally {
      setSaving(false);
    }
  };

  const setLedgerStatus = async (rowId: number, status: 'paid' | 'pending') => {
    setLedgerBusyId(rowId);
    setMsg(null);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.multiInstructorEarningsSetStatus, { id: rowId, status });
      setMsg(status === 'paid' ? 'Marked as paid.' : 'Marked as pending.');
      await refetchEarnings();
    } catch (err) {
      setMsg(err instanceof Error ? err.message : 'Request failed');
    } finally {
      setLedgerBusyId(null);
    }
  };

  const deleteMember = async (userId: number) => {
    const cid = parseInt(courseId, 10);
    if (!Number.isFinite(cid) || cid <= 0) return;
    if (!window.confirm('Remove this person from the course staff list?')) return;
    setSaving(true);
    setMsg(null);
    try {
      const path = `${SIKSHYA_ENDPOINTS.pro.multiInstructorCourseStaffWrite}?course_id=${encodeURIComponent(String(cid))}&user_id=${encodeURIComponent(String(userId))}`;
      await getSikshyaApi().delete(path);
      setMsg('Removed.');
      refetch();
    } catch (err) {
      setMsg(err instanceof Error ? err.message : 'Request failed');
    } finally {
      setSaving(false);
    }
  };

  const pickedCourseSummary = useMemo(() => {
    const cid = parseInt(courseId, 10);
    if (!Number.isFinite(cid) || cid <= 0) return null;
    const hit = (courseSearch.data?.data || []).find((c) => c.id === cid);
    const title = hit?.title || (courseQuery.trim() ? courseQuery.trim() : null) || `Course #${cid}`;
    return { cid, title };
  }, [courseId, courseSearch.data?.data, courseQuery]);

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle="Assign co-instructors and revenue weights per course (Pro Multi-instructor). Open from Course → Course staff, or use Manage staff on any course row. Global visibility: Add-ons → Multi-instructor."
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="multi_instructor"
        config={config}
        featureTitle="Course staff"
        featureDescription="Link co-instructors to a course, set optional revenue weights, and record ledger rows when orders complete."
        previewVariant="table"
        addonEnableTitle="Course staff is not enabled"
        addonEnableDescription="Enable the Multi-instructor add-on to register REST routes and unlock co-instructor management."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        <>
          {error ? <ApiErrorPanel error={error} title="Could not load team" onRetry={() => refetch()} /> : null}

          {warnings.length > 0 ? (
            <div
              className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
              role="status"
            >
              <p className="font-semibold">Heads up</p>
              <ul className="mt-2 list-disc space-y-1 pl-5">
                {warnings.map((w) => (
                  <li key={w}>{w}</li>
                ))}
              </ul>
            </div>
          ) : null}

          <form
            onSubmit={addInstructor}
            className="mb-8 overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/80 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:to-slate-950/80"
          >
            <div className="border-b border-slate-100 bg-slate-50/90 px-6 py-4 dark:border-slate-800 dark:bg-slate-900/80">
              <h2 className="text-base font-semibold tracking-tight text-slate-900 dark:text-white">Build course team</h2>
              <p className="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                Pick a course, search staff by name (Administrator, Editor, Author, or Sikshya instructor), set role and
                revenue weight. Weights apply to paid line items and are normalized per sale.
              </p>
              {pickedCourseSummary ? (
                <div className="mt-3 inline-flex max-w-full items-center gap-2 rounded-full border border-brand-200 bg-brand-50/90 px-3 py-1.5 text-xs font-medium text-brand-900 dark:border-brand-900/50 dark:bg-brand-950/40 dark:text-brand-100">
                  <span className="truncate">{pickedCourseSummary.title}</span>
                  <span className="shrink-0 rounded-md bg-white/80 px-1.5 py-0.5 font-mono text-[10px] text-brand-800 dark:bg-slate-900/60 dark:text-brand-200">
                    #{pickedCourseSummary.cid}
                  </span>
                  <a
                    className="shrink-0 text-brand-700 underline-offset-2 hover:underline dark:text-brand-300"
                    href={appViewHref(config, 'add-course', { course_id: String(pickedCourseSummary.cid) })}
                  >
                    Open in builder
                  </a>
                </div>
              ) : null}
            </div>
            <div className="grid gap-6 p-6 lg:grid-cols-12">
              <div className="lg:col-span-5" data-course-picker="1">
                <label className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Course
                </label>
                <input
                  type="text"
                  value={courseQuery}
                  onChange={(e) => setCourseQuery(e.target.value)}
                  onFocus={() => setCourseDropdownOpen(true)}
                  className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-inner dark:border-slate-600 dark:bg-slate-950"
                  placeholder="Type to search your catalog…"
                  aria-label="Search courses"
                  autoComplete="off"
                />
                {courseSearch.loading ? (
                  <div className="mt-2 text-xs text-slate-500">Searching courses…</div>
                ) : courseSearch.error ? (
                  <div className="mt-2 text-xs text-red-600 dark:text-red-400">Could not search courses.</div>
                ) : courseDropdownOpen ? (
                  <div className="mt-2 max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white text-sm shadow-md dark:border-slate-600 dark:bg-slate-950">
                    {(courseSearch.data?.data || []).length ? (
                      (courseSearch.data?.data || []).map((c) => (
                        <button
                          key={c.id}
                          type="button"
                          className="flex w-full items-center justify-between gap-2 border-b border-slate-50 px-3 py-2.5 text-left last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900"
                          onClick={() => {
                            setCourseId(String(c.id));
                            setCourseQuery(c.title);
                            setCourseDropdownOpen(false);
                          }}
                        >
                          <span className="min-w-0 font-medium text-slate-800 dark:text-slate-100">{c.title}</span>
                          <span className="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {c.id}
                          </span>
                        </button>
                      ))
                    ) : (
                      <div className="px-3 py-3 text-xs text-slate-500">
                        {debouncedCourseQuery.trim() ? 'No courses match.' : 'Start typing to search.'}
                      </div>
                    )}
                  </div>
                ) : null}
              </div>
              <div className="lg:col-span-4" data-instructor-picker="1">
                <label className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Instructor
                </label>
                <input
                  type="text"
                  value={userQuery}
                  onChange={(e) => setUserQuery(e.target.value)}
                  onFocus={() => setUserDropdownOpen(true)}
                  className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-inner dark:border-slate-600 dark:bg-slate-950"
                  placeholder="Search by display name…"
                  autoComplete="off"
                />
                {pickedUserLabel ? (
                  <div className="mt-2 flex items-center justify-between gap-2 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-3 py-2 text-xs text-emerald-950 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-100">
                    <span className="min-w-0 truncate font-medium">{pickedUserLabel}</span>
                    <button
                      type="button"
                      className="shrink-0 rounded-lg px-2 py-1 text-emerald-800 hover:bg-emerald-100/80 dark:text-emerald-200 dark:hover:bg-emerald-900/40"
                      onClick={() => {
                        setNewUserId('');
                        setUserQuery('');
                      }}
                    >
                      Clear
                    </button>
                  </div>
                ) : null}
                {userSearch.loading ? (
                  <div className="mt-2 text-xs text-slate-500">Searching people…</div>
                ) : userSearch.error ? (
                  <div className="mt-2 text-xs text-red-600 dark:text-red-400">Could not search users.</div>
                ) : userDropdownOpen ? (
                  <div className="mt-2 max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white text-sm shadow-md dark:border-slate-600 dark:bg-slate-950">
                    {(userSearch.data?.data || []).length ? (
                      (userSearch.data?.data || []).map((u) => (
                        <button
                          key={u.id}
                          type="button"
                          className="flex w-full items-start justify-between gap-2 border-b border-slate-50 px-3 py-2.5 text-left last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900"
                          onClick={() => {
                            setNewUserId(String(u.id));
                            setUserQuery(u.name);
                            setUserDropdownOpen(false);
                          }}
                        >
                          <span className="min-w-0">
                            <span className="block font-medium text-slate-800 dark:text-slate-100">{u.name}</span>
                            {u.email ? (
                              <span className="mt-0.5 block truncate text-[11px] text-slate-500 dark:text-slate-400">
                                {u.email}
                              </span>
                            ) : null}
                          </span>
                          <span className="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {u.id}
                          </span>
                        </button>
                      ))
                    ) : (
                      <div className="px-3 py-3 text-xs text-slate-500">
                        {debouncedUserQuery.trim() ? 'No people match.' : 'Type to search staff accounts.'}
                      </div>
                    )}
                  </div>
                ) : null}
              </div>
              <div className="grid gap-4 sm:grid-cols-2 lg:col-span-3 lg:grid-cols-1">
                <label className="text-sm text-slate-600 dark:text-slate-300">
                  <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Role
                  </span>
                  <select
                    value={newMemberRole}
                    onChange={(e) => setNewMemberRole(e.target.value === 'lead' ? 'lead' : 'co_instructor')}
                    className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-950"
                    aria-label="Staff role for new member"
                  >
                    <option value="co_instructor">Co-instructor</option>
                    <option value="lead">Lead</option>
                  </select>
                </label>
                <label className="text-sm text-slate-600 dark:text-slate-300">
                  <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Revenue weight %
                  </span>
                  <input
                    type="number"
                    step="0.01"
                    min={0}
                    max={100}
                    value={share}
                    onChange={(e) => setShare(e.target.value)}
                    className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm tabular-nums dark:border-slate-600 dark:bg-slate-950"
                  />
                </label>
                <div className="flex items-end sm:col-span-2 lg:col-span-1">
                  <ButtonPrimary type="submit" disabled={saving} className="w-full sm:w-auto">
                    {saving ? 'Saving…' : 'Add to team'}
                  </ButtonPrimary>
                </div>
              </div>
            </div>
            {msg ? <p className="border-t border-slate-100 px-6 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400">{msg}</p> : null}
          </form>

          <ListPanel className="overflow-hidden rounded-2xl border border-slate-200/80 dark:border-slate-700/80">
            {loading ? (
              <div className="p-10 text-center text-sm text-slate-500">Loading team…</div>
            ) : rowsBase.length === 0 ? (
              <ListEmptyState
                title={isGlobal ? 'No staff rows yet' : 'No staff yet'}
                description={
                  isGlobal
                    ? 'Once you link instructors to courses, they’ll show up here. Pick a course above to add staff.'
                    : 'Add co-instructors with the form above or from the course builder. The course author does not have to appear here for revenue splits.'
                }
              />
            ) : (
              <div className="overflow-x-auto">
                <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 bg-slate-50/50 px-5 py-3 dark:border-slate-800 dark:bg-slate-900/40">
                  <div className="min-w-[min(100%,340px)] flex-1">
                    <label className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      Filter staff
                    </label>
                    <input
                      type="text"
                      value={staffFilter}
                      onChange={(e) => setStaffFilter(e.target.value)}
                      placeholder="Search by name, email, or course…"
                      className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-inner dark:border-slate-600 dark:bg-slate-950"
                    />
                  </div>
                  <div className="text-xs text-slate-600 dark:text-slate-400">
                    {isGlobal ? (
                      <span>
                        Showing <span className="font-semibold tabular-nums text-slate-900 dark:text-white">{rows.length}</span> staff row
                        {rows.length === 1 ? '' : 's'}
                      </span>
                    ) : (
                      <span>
                        Total weight (before normalization at checkout):{' '}
                        <span className="font-semibold tabular-nums text-slate-900 dark:text-white">
                          {Number(shareTotal).toFixed(2)}%
                        </span>
                      </span>
                    )}
                  </div>
                </div>
                <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                  <thead className="bg-slate-50/90 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/90 dark:text-slate-400">
                    <tr>
                      {isGlobal ? <th className="px-5 py-3.5">Course</th> : null}
                      <th className="px-5 py-3.5">Instructor</th>
                      <th className="px-5 py-3.5">Role</th>
                      <th className="px-5 py-3.5">Weight %</th>
                      <th className="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {rows.map((r) => {
                      const isAuthor = courseAuthorId !== null && r.user_id === courseAuthorId;
                      return (
                        <tr key={r.id} className="bg-white dark:bg-slate-900">
                          {isGlobal ? (
                            <td className="px-5 py-3.5 align-top">
                              <div className="min-w-0">
                                <div className="truncate font-medium text-slate-900 dark:text-white">
                                  {r.course_title || `Course #${r.course_id}`}
                                </div>
                                <div className="mt-0.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">#{r.course_id}</div>
                              </div>
                            </td>
                          ) : null}
                          <td className="px-5 py-3.5">
                            <div className="flex items-center gap-3">
                              {r.avatar_url ? (
                                <img
                                  src={r.avatar_url}
                                  alt=""
                                  className="h-9 w-9 rounded-full border border-slate-200 dark:border-slate-700"
                                />
                              ) : null}
                              <div className="min-w-0">
                                <div className="truncate font-medium text-slate-900 dark:text-white">
                                  {r.display_name || `User #${r.user_id}`}
                                  {isAuthor ? (
                                    <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                      Author
                                    </span>
                                  ) : null}
                                </div>
                                {r.user_email ? (
                                  <div className="truncate text-xs text-slate-500 dark:text-slate-400">{r.user_email}</div>
                                ) : null}
                              </div>
                            </div>
                          </td>
                          <td className="px-5 py-3.5">
                            {isGlobal ? (
                              <span className="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {r.role === 'lead' ? 'Lead' : 'Co-instructor'}
                              </span>
                            ) : (
                              <select
                                value={rowRoleDraft[r.user_id] ?? 'co_instructor'}
                                onChange={(e) =>
                                  setRowRoleDraft((prev) => ({
                                    ...prev,
                                    [r.user_id]: e.target.value === 'lead' ? 'lead' : 'co_instructor',
                                  }))
                                }
                                className="w-full max-w-[11rem] rounded-lg border border-slate-200 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                                aria-label={`Role for user ${r.user_id}`}
                              >
                                <option value="co_instructor">Co-instructor</option>
                                <option value="lead">Lead</option>
                              </select>
                            )}
                          </td>
                          <td className="px-5 py-3.5">
                            <div className="flex flex-wrap items-center gap-2">
                              {isGlobal ? (
                                <span className="tabular-nums text-slate-800 dark:text-slate-100">
                                  {Number(r.revenue_share || 0).toFixed(2)}%
                                </span>
                              ) : (
                                <>
                                  <input
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    max={100}
                                    className="w-28 rounded-lg border border-slate-200 px-2 py-1.5 tabular-nums dark:border-slate-700 dark:bg-slate-950"
                                    value={rowShareDraft[r.user_id] ?? ''}
                                    onChange={(e) =>
                                      setRowShareDraft((prev) => ({ ...prev, [r.user_id]: e.target.value }))
                                    }
                                    aria-label={`Revenue weight for user ${r.user_id}`}
                                  />
                                  <button
                                    type="button"
                                    className="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                                    disabled={saving}
                                    onClick={() => void saveStaffRow(r.user_id)}
                                  >
                                    Save
                                  </button>
                                </>
                              )}
                            </div>
                          </td>
                          <td className="px-5 py-3.5 text-right">
                            {isGlobal ? (
                              <button
                                type="button"
                                className="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400"
                                onClick={() => {
                                  setCourseId(String(r.course_id));
                                  setCourseQuery(r.course_title || `Course #${r.course_id}`);
                                }}
                              >
                                Manage
                              </button>
                            ) : (
                              <button
                                type="button"
                                className="text-xs font-medium text-red-600 hover:underline disabled:opacity-40 dark:text-red-400"
                                disabled={saving || isAuthor}
                                title={
                                  isAuthor
                                    ? 'Remove the author from the course in WordPress if you need to change ownership.'
                                    : 'Remove from staff'
                                }
                                onClick={() => void deleteMember(r.user_id)}
                              >
                                Remove
                              </button>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </ListPanel>

          <div className="mt-8 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">Instructor earnings ledger</h2>
            <p className="mt-1 max-w-2xl text-xs text-slate-600 dark:text-slate-400">
              Ledger rows are created when a paid order completes. Search an instructor by name, then load their
              history. Admins with payout permissions can mark rows paid.
            </p>
            <div className="mt-5 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
              <div className="min-w-[min(100%,280px)] flex-1" data-ledger-instructor-picker="1">
                <label className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Instructor
                </label>
                <input
                  type="text"
                  value={ledgerUserQuery}
                  onChange={(e) => {
                    setLedgerUserQuery(e.target.value);
                    setLedgerPickedLabel('');
                  }}
                  onFocus={() => setLedgerDropdownOpen(true)}
                  placeholder="Search by name…"
                  className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-950"
                  autoComplete="off"
                />
                {earningsUserId && ledgerPickedLabel ? (
                  <div className="mt-2 text-xs text-slate-600 dark:text-slate-400">
                    Viewing: <span className="font-semibold text-slate-800 dark:text-slate-200">{ledgerPickedLabel}</span>
                    <span className="ml-2 font-mono text-slate-500">#{earningsUserId}</span>
                    <button
                      type="button"
                      className="ml-2 text-brand-600 hover:underline dark:text-brand-400"
                      onClick={() => {
                        setEarningsUserId('');
                        setLedgerUserQuery('');
                        setLedgerPickedLabel('');
                      }}
                    >
                      Clear
                    </button>
                  </div>
                ) : null}
                {ledgerUserSearch.loading ? (
                  <div className="mt-2 text-xs text-slate-500">Searching…</div>
                ) : ledgerUserSearch.error ? (
                  <div className="mt-2 text-xs text-red-600">Could not search users.</div>
                ) : ledgerDropdownOpen ? (
                  <div className="mt-2 max-h-52 overflow-auto rounded-xl border border-slate-200 bg-white text-sm shadow-md dark:border-slate-600 dark:bg-slate-950">
                    {(ledgerUserSearch.data?.data || []).length ? (
                      (ledgerUserSearch.data?.data || []).map((u) => (
                        <button
                          key={u.id}
                          type="button"
                          className="flex w-full items-start justify-between gap-2 border-b border-slate-50 px-3 py-2.5 text-left last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900"
                          onClick={() => {
                            setEarningsUserId(String(u.id));
                            setLedgerUserQuery(u.name);
                            setLedgerPickedLabel(u.email ? `${u.name} · ${u.email}` : u.name);
                            setLedgerDropdownOpen(false);
                          }}
                        >
                          <span className="min-w-0">
                            <span className="block font-medium text-slate-800 dark:text-slate-100">{u.name}</span>
                            {u.email ? (
                              <span className="mt-0.5 block truncate text-[11px] text-slate-500">{u.email}</span>
                            ) : null}
                          </span>
                          <span className="shrink-0 font-mono text-[11px] text-slate-500">{u.id}</span>
                        </button>
                      ))
                    ) : (
                      <div className="px-3 py-3 text-xs text-slate-500">
                        {debouncedLedgerQuery.trim() ? 'No matches.' : 'Type to search instructors.'}
                      </div>
                    )}
                  </div>
                ) : null}
              </div>
              <ButtonPrimary type="button" disabled={earningsLoading || !earningsUserId} onClick={() => refetchEarnings()}>
                {earningsLoading ? 'Loading…' : 'Refresh ledger'}
              </ButtonPrimary>
              <div className="text-sm text-slate-600 dark:text-slate-400">
                Total: <span className="font-semibold tabular-nums">{Number(earningsData?.total || 0).toFixed(2)}</span>
              </div>
            </div>

            {earningsError ? (
              <ApiErrorPanel error={earningsError} title="Could not load earnings" onRetry={() => refetchEarnings()} />
            ) : null}
            {(earningsData?.rows?.length || 0) > 0 ? (
              <div className="mt-4 overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                  <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                    <tr>
                      <th className="px-5 py-3.5">ID</th>
                      <th className="px-5 py-3.5">Order item</th>
                      <th className="px-5 py-3.5">Amount</th>
                      <th className="px-5 py-3.5">Status</th>
                      <th className="px-5 py-3.5">Created</th>
                      {canManageLedger ? <th className="px-5 py-3.5 text-right">Admin</th> : null}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {(earningsData?.rows || []).map((r) => (
                      <tr key={r.id} className="bg-white dark:bg-slate-900">
                        <td className="px-5 py-3.5">{r.id}</td>
                        <td className="px-5 py-3.5">{r.order_item_id}</td>
                        <td className="px-5 py-3.5 tabular-nums">{Number(r.amount).toFixed(2)}</td>
                        <td className="px-5 py-3.5 capitalize">{r.status}</td>
                        <td className="px-5 py-3.5 text-slate-600 dark:text-slate-400">{r.created_at || '—'}</td>
                        {canManageLedger ? (
                          <td className="px-5 py-3.5 text-right">
                            {r.status === 'pending' ? (
                              <button
                                type="button"
                                className="text-xs font-medium text-teal-700 hover:underline disabled:opacity-40 dark:text-teal-300"
                                disabled={ledgerBusyId === r.id}
                                onClick={() => void setLedgerStatus(r.id, 'paid')}
                              >
                                {ledgerBusyId === r.id ? '…' : 'Mark paid'}
                              </button>
                            ) : r.status === 'paid' ? (
                              <button
                                type="button"
                                className="text-xs font-medium text-slate-600 hover:underline disabled:opacity-40 dark:text-slate-400"
                                disabled={ledgerBusyId === r.id}
                                onClick={() => void setLedgerStatus(r.id, 'pending')}
                              >
                                {ledgerBusyId === r.id ? '…' : 'Set pending'}
                              </button>
                            ) : (
                              <span className="text-xs text-slate-400">—</span>
                            )}
                          </td>
                        ) : null}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="mt-4 text-sm text-slate-600 dark:text-slate-400">No earnings rows for this query yet.</p>
            )}
          </div>
        </>
      </GatedFeatureWorkspace>
    </AppShell>
  );
}
