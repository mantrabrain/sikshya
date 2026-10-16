import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPanelSkeleton } from '../components/shared/Skeleton';
import { useAsyncData } from '../hooks/useAsyncData';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import type { SikshyaReactConfig } from '../types';
import { __ } from '../lib/i18n';

type Seats = {
  seats_total: number;
  seats_used: number;
  seats_available: number;
  over_capacity: boolean;
};

type Group = {
  id: number;
  name: string;
  slug: string;
  status: string;
  owner_user_id: number;
  seats: Seats;
  course_count: number;
};

type Member = {
  user_id: number;
  role: string;
  status: string;
  consumes_seat: boolean;
  display_name: string;
  email: string;
};

const FIELD =
  'mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LABEL = 'block text-sm font-medium text-slate-700 dark:text-slate-300';

/**
 * Groups & seat licensing.
 *
 * An organisation buys a block of seats; this is where the site administrator
 * sets that up. Seat capacity is deliberately shown on every row, because it is
 * the number the customer is paying for and the one that blocks them when it
 * runs out — the API refuses a learner beyond the purchased count, and a leader
 * seeing "0 of 25 left" is how that refusal stops being a surprise.
 */
export function GroupsPage(props: {
  config: SikshyaReactConfig;
  title: string;
  subtitle?: string;
  embedded?: boolean;
}) {
  const { config, title, subtitle, embedded } = props;
  const toast = useTopRightToast(3000);

  const [creating, setCreating] = useState(false);
  const [name, setName] = useState('');
  const [seats, setSeats] = useState('10');
  const [ownerEmail, setOwnerEmail] = useState('');
  const [busy, setBusy] = useState(false);

  const [openGroup, setOpenGroup] = useState<number | null>(null);
  const [memberEmail, setMemberEmail] = useState('');
  const [memberRole, setMemberRole] = useState<'member' | 'leader'>('member');

  const loader = useCallback(
    () => getSikshyaApi().get<{ ok?: boolean; groups?: Group[] }>(SIKSHYA_ENDPOINTS.admin.groups({ per_page: 100 })),
    [],
  );
  const { loading, data, error, refetch } = useAsyncData(loader, ['groups']);
  const groups = data?.groups ?? [];

  const membersLoader = useCallback(async () => {
    if (openGroup === null) {
      return { members: [] as Member[] };
    }

    return getSikshyaApi().get<{ members?: Member[]; seats?: Seats }>(
      SIKSHYA_ENDPOINTS.admin.groupMembers(openGroup),
    );
  }, [openGroup]);
  const membersState = useAsyncData(membersLoader, [`members-${openGroup ?? 'none'}`]);
  const members = membersState.data?.members ?? [];

  const openGroupRow = useMemo(
    () => groups.find((g) => g.id === openGroup) ?? null,
    [groups, openGroup],
  );

  const create = async () => {
    if (name.trim() === '') {
      toast.error(__('Name required', 'sikshya'), __('Give the group a name.', 'sikshya'));
      return;
    }
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.groups(), {
        name: name.trim(),
        seats_total: Math.max(0, parseInt(seats, 10) || 0),
        owner_email: ownerEmail.trim() || undefined,
      });
      toast.success(__('Group created', 'sikshya'), __('Add learners to start using its seats.', 'sikshya'));
      setName('');
      setSeats('10');
      setOwnerEmail('');
      setCreating(false);
      void refetch();
    } catch (e) {
      toast.error(__('Could not create', 'sikshya'), e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusy(false);
    }
  };

  const addMember = async () => {
    if (openGroup === null) {
      return;
    }
    if (memberEmail.trim() === '') {
      toast.error(__('Email required', 'sikshya'), __('Enter the learner’s email address.', 'sikshya'));
      return;
    }
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.groupMembers(openGroup), {
        email: memberEmail.trim(),
        role: memberRole,
      });
      toast.success(__('Learner added', 'sikshya'), __('They now have access to the group’s courses.', 'sikshya'));
      setMemberEmail('');
      void membersState.refetch();
      void refetch();
    } catch (e) {
      // The commonest failure is a full group, and the API explains it — so the
      // message is shown rather than replaced with something generic.
      toast.error(__('Could not add', 'sikshya'), e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusy(false);
    }
  };

  const removeMember = async (userId: number) => {
    if (openGroup === null) {
      return;
    }
    setBusy(true);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.admin.groupMember(openGroup, userId));
      toast.success(__('Removed', 'sikshya'), __('Their seat is free. Progress is kept.', 'sikshya'));
      void membersState.refetch();
      void refetch();
    } catch (e) {
      toast.error(__('Could not remove', 'sikshya'), e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusy(false);
    }
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={
        subtitle ??
        __(
          'Sell a block of seats to an organisation and let them run their own roster. Seats are enforced: once the purchased number is used, adding another learner is refused.',
          'sikshya',
        )
      }
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

      <ListPanel className="p-4">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <p className="text-xs text-slate-500 dark:text-slate-400">
            {groups.length > 0 ? `${groups.length} ${__('groups', 'sikshya')}` : null}
          </p>
          <span data-sikshya-new-group>
            <ButtonPrimary onClick={() => setCreating((v) => !v)}>
              {creating ? __('Cancel', 'sikshya') : __('New group', 'sikshya')}
            </ButtonPrimary>
          </span>
        </div>

        {creating ? (
          <div className="mb-5 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-950/30">
            <div className="grid gap-3 md:grid-cols-3">
              <label className={LABEL}>
                {__('Organisation name', 'sikshya')}
                <input
                  className={FIELD}
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder={__('Acme Corp', 'sikshya')}
                  data-sikshya-group-name
                />
              </label>
              <label className={LABEL}>
                {__('Seats purchased', 'sikshya')}
                <input
                  className={FIELD}
                  type="number"
                  min={0}
                  value={seats}
                  onChange={(e) => setSeats(e.target.value)}
                  data-sikshya-group-seats
                />
              </label>
              <label className={LABEL}>
                {__('Owner email (optional)', 'sikshya')}
                <input
                  className={FIELD}
                  value={ownerEmail}
                  onChange={(e) => setOwnerEmail(e.target.value)}
                  placeholder={__('buyer@acme.test', 'sikshya')}
                  data-sikshya-group-owner
                />
                <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                  {__('They administer the roster themselves and use no seat.', 'sikshya')}
                </span>
              </label>
            </div>
            <div className="mt-3">
              <span data-sikshya-group-save>
                <ButtonPrimary onClick={() => void create()} disabled={busy}>
                  {busy ? __('Saving…', 'sikshya') : __('Create group', 'sikshya')}
                </ButtonPrimary>
              </span>
            </div>
          </div>
        ) : null}

        {error ? (
          <ApiErrorPanel error={error} onRetry={() => void refetch()} />
        ) : loading ? (
          <ListPanelSkeleton />
        ) : groups.length === 0 ? (
          <ListEmptyState
            title={__('No groups yet', 'sikshya')}
            description={__(
              'Create one when an organisation buys seats. Their leader can then add learners without needing you.',
              'sikshya',
            )}
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm" data-sikshya-groups-table>
              <thead className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                  <th className="py-2 pr-4">{__('Organisation', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Seats', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Courses', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Status', 'sikshya')}</th>
                  <th className="py-2" />
                </tr>
              </thead>
              <tbody>
                {groups.map((g) => (
                  <tr key={g.id} className="border-t border-slate-100 dark:border-slate-800">
                    <td className="py-3 pr-4 font-medium text-slate-800 dark:text-slate-100">{g.name}</td>
                    <td className="py-3 pr-4">
                      <span className={g.seats.over_capacity ? 'font-semibold text-rose-600' : ''}>
                        {g.seats.seats_used} / {g.seats.seats_total}
                      </span>
                      {g.seats.over_capacity ? (
                        <span className="ml-2 text-xs text-rose-600">{__('over capacity', 'sikshya')}</span>
                      ) : (
                        <span className="ml-2 text-xs text-slate-500">
                          {g.seats.seats_available} {__('left', 'sikshya')}
                        </span>
                      )}
                    </td>
                    <td className="py-3 pr-4">{g.course_count}</td>
                    <td className="py-3 pr-4">{g.status}</td>
                    <td className="py-3 text-right">
                      <ButtonSecondary onClick={() => setOpenGroup(openGroup === g.id ? null : g.id)}>
                        {openGroup === g.id ? __('Close', 'sikshya') : __('Manage roster', 'sikshya')}
                      </ButtonSecondary>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </ListPanel>

      {openGroup !== null ? (
        <div data-sikshya-group-roster>
        <ListPanel className="mt-4 p-4">
          <h2 className="mb-1 text-base font-semibold text-slate-800 dark:text-slate-100">
            {openGroupRow ? openGroupRow.name : __('Roster', 'sikshya')}
          </h2>
          {openGroupRow ? (
            <p className="mb-4 text-xs text-slate-500 dark:text-slate-400">
              {openGroupRow.seats.seats_used} {__('of', 'sikshya')} {openGroupRow.seats.seats_total}{' '}
              {__('seats used', 'sikshya')}
              {openGroupRow.seats.seats_available === 0
                ? ` — ${__('adding another learner will be refused until seats are added.', 'sikshya')}`
                : null}
            </p>
          ) : null}

          <div className="mb-4 flex flex-wrap items-end gap-3">
            <label className={LABEL}>
              {__('Learner email', 'sikshya')}
              <input
                className={FIELD}
                value={memberEmail}
                onChange={(e) => setMemberEmail(e.target.value)}
                placeholder={__('learner@acme.test', 'sikshya')}
                data-sikshya-member-email
              />
            </label>
            <label className={LABEL}>
              {__('Role', 'sikshya')}
              <select
                className={FIELD}
                value={memberRole}
                onChange={(e) => setMemberRole(e.target.value === 'leader' ? 'leader' : 'member')}
                data-sikshya-member-role
              >
                <option value="member">{__('Learner (uses a seat)', 'sikshya')}</option>
                <option value="leader">{__('Leader (administers, no seat)', 'sikshya')}</option>
              </select>
            </label>
            <span data-sikshya-member-add>
              <ButtonPrimary onClick={() => void addMember()} disabled={busy}>
                {__('Add to group', 'sikshya')}
              </ButtonPrimary>
            </span>
          </div>

          {membersState.error ? (
            <ApiErrorPanel error={membersState.error} onRetry={() => void membersState.refetch()} />
          ) : membersState.loading ? (
            <ListPanelSkeleton />
          ) : members.length === 0 ? (
            <ListEmptyState
              title={__('Nobody in this group yet', 'sikshya')}
              description={__('Add a learner by email above.', 'sikshya')}
            />
          ) : (
            <table className="w-full text-left text-sm" data-sikshya-members-table>
              <thead className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                  <th className="py-2 pr-4">{__('Learner', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Role', 'sikshya')}</th>
                  <th className="py-2 pr-4">{__('Seat', 'sikshya')}</th>
                  <th className="py-2" />
                </tr>
              </thead>
              <tbody>
                {members.map((m) => (
                  <tr key={m.user_id} className="border-t border-slate-100 dark:border-slate-800">
                    <td className="py-3 pr-4">
                      <span className="font-medium text-slate-800 dark:text-slate-100">
                        {m.display_name || `#${m.user_id}`}
                      </span>
                      <span className="ml-2 text-xs text-slate-500">{m.email}</span>
                    </td>
                    <td className="py-3 pr-4">{m.role}</td>
                    <td className="py-3 pr-4 text-xs text-slate-500">
                      {m.consumes_seat ? __('uses a seat', 'sikshya') : __('no seat', 'sikshya')}
                    </td>
                    <td className="py-3 text-right">
                      <ButtonSecondary onClick={() => void removeMember(m.user_id)} disabled={busy}>
                        {__('Remove', 'sikshya')}
                      </ButtonSecondary>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </ListPanel>
        </div>
      ) : null}
    </EmbeddableShell>
  );
}
