import { useMemo, useState } from 'react';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { UserEntityListView } from '../components/shared/list/UserEntityListView';
import { InlineRowActions } from '../components/shared/list/InlineRowActions';
import { ButtonPrimary, LinkButtonPrimary } from '../components/shared/buttons';
import { Modal } from '../components/shared/Modal';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { getWpApi } from '../api';
import type { Column } from '../components/shared/DataTable';
import { formatPostDate } from '../lib/formatPostDate';
import type { SikshyaReactConfig, WpRestUser } from '../types';
import { term, termLower } from '../lib/terminology';

type Variant = 'students' | 'instructors';

export function WpUserListPage(props: {
  config: SikshyaReactConfig;
  title: string;
  subtitle: string;
  variant: Variant;
  embedded?: boolean;
}) {
  const { config, title, subtitle, variant, embedded } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');

  const roleSlug = variant === 'students' ? 'sikshya_student' : 'sikshya_instructor';
  const termStudent = termLower(config, 'student');
  const termInstructor = termLower(config, 'instructor');
  const termStudents = term(config, 'students');
  const termInstructors = term(config, 'instructors');
  const newUrl = `${adminBase}user-new.php`;
  const [createOpen, setCreateOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [createErr, setCreateErr] = useState<unknown>(null);
  const [refreshSeq, setRefreshSeq] = useState(0);

  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [password, setPassword] = useState('');

  const columns: Column<WpRestUser>[] = useMemo(
    () => [
      {
        id: 'id',
        header: 'ID',
        sortKey: 'id',
        alwaysVisible: true,
        cellClassName: 'whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-400',
        render: (u) => u.id,
      },
      {
        id: 'name',
        header: 'Name',
        sortKey: 'name',
        render: (u) => {
          const editUrl = `${adminBase}user-edit.php?user_id=${u.id}`;
          return (
            <div className="max-w-md">
              <a
                href={editUrl}
                className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
              >
                {u.name}
              </a>
              <div className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{u.slug}</div>
              <InlineRowActions
                ariaLabel={`Actions for ${u.name}`}
                items={[{ key: 'edit', label: 'Edit', href: editUrl }]}
              />
            </div>
          );
        },
      },
      {
        id: 'email',
        header: 'Email',
        sortKey: 'email',
        cellClassName: 'text-slate-600 dark:text-slate-400',
        render: (u) => u.email || '—',
      },
      {
        id: 'registered',
        header: 'Registered',
        sortKey: 'registered_date',
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (u) => formatPostDate(u.registered_date),
      },
    ],
    [adminBase]
  );

  async function createUser() {
    setCreateErr(null);
    setCreating(true);
    try {
      const uname = username.trim();
      const em = email.trim();
      const nm = displayName.trim();
      const pw = password.trim();

      // WordPress requires username, email and password for `/wp/v2/users` create.
      if (!uname || !em || !pw) {
        throw new Error('Username, email, and password are required.');
      }

      await getWpApi().post('/users', {
        username: uname,
        email: em,
        password: pw,
        name: nm || undefined,
        roles: [roleSlug],
      });

      setCreateOpen(false);
      setUsername('');
      setEmail('');
      setDisplayName('');
      setPassword('');
      setRefreshSeq((n) => n + 1);
    } catch (e) {
      setCreateErr(e);
    } finally {
      setCreating(false);
    }
  }

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={subtitle}
      pageActions={
        <div className="flex flex-wrap items-center gap-2">
          <ButtonPrimary type="button" onClick={() => setCreateOpen(true)}>
            + Add {variant === 'students' ? termStudent : termInstructor}
          </ButtonPrimary>
          <LinkButtonPrimary href={newUrl} title="WordPress user creation screen">
            Add in WordPress
          </LinkButtonPrimary>
        </div>
      }
    >
      <Modal
        open={createOpen}
        title={`Add ${variant === 'students' ? termStudent : termInstructor}`}
        description="Creates a WordPress user and assigns the appropriate Sikshya role."
        onClose={() => (creating ? null : setCreateOpen(false))}
        size="md"
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
              onClick={() => setCreateOpen(false)}
              disabled={creating}
            >
              Cancel
            </button>
            <ButtonPrimary type="button" onClick={() => void createUser()} disabled={creating}>
              {creating ? 'Creating…' : 'Create'}
            </ButtonPrimary>
          </div>
        }
      >
        {createErr ? <ApiErrorPanel error={createErr} title="Could not create user" /> : null}
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block text-sm text-slate-700 dark:text-slate-300">
            Username
            <input
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              placeholder="e.g. john_doe"
              autoComplete="off"
              disabled={creating}
              required
            />
          </label>
          <label className="block text-sm text-slate-700 dark:text-slate-300">
            Email
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              placeholder="name@example.com"
              autoComplete="off"
              disabled={creating}
              required
            />
          </label>
          <label className="block text-sm text-slate-700 dark:text-slate-300 sm:col-span-2">
            Display name (optional)
            <input
              value={displayName}
              onChange={(e) => setDisplayName(e.target.value)}
              className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              placeholder="Shown publicly as the author/teacher"
              autoComplete="off"
              disabled={creating}
            />
          </label>
          <label className="block text-sm text-slate-700 dark:text-slate-300 sm:col-span-2">
            Password
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              placeholder="Set an initial password"
              autoComplete="new-password"
              disabled={creating}
              required
            />
          </label>
        </div>
        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
          Requires the current admin user to have permission to create WordPress users.
        </p>
      </Modal>

      <UserEntityListView
        roleSlug={roleSlug}
        refreshToken={refreshSeq}
        contextHint={
          variant === 'students'
            ? `Users with the ${termStudents} role.`
            : `Users with the ${termInstructors} role. Review pending sign-ups under People → Applications.`
        }
        searchPlaceholder={`Search ${variant === 'students' ? termStudents.toLowerCase() : termInstructors.toLowerCase()}…`}
        sortFieldOptions={[
          { value: 'name', label: 'Name' },
          { value: 'registered_date', label: 'Registered' },
          { value: 'id', label: 'ID' },
          { value: 'email', label: 'Email' },
        ]}
        defaultSortField="name"
        columnPickerStorageKey={`users_${variant}`}
        columns={columns}
        emptyMessage="No users match your search."
        emptyStateTitle="No users found"
        emptyStateDescription="Try another search or create one here."
        emptyStateAction={
          <ButtonPrimary type="button" onClick={() => setCreateOpen(true)}>
            + Add {variant === 'students' ? termStudent : termInstructor}
          </ButtonPrimary>
        }
        skeletonHeaders={['ID', 'Name', 'Email', 'Registered']}
      />
    </EmbeddableShell>
  );
}
