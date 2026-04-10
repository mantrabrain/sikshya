import { useMemo } from 'react';
import { AppShell } from '../components/AppShell';
import { UserEntityListView } from '../components/shared/list/UserEntityListView';
import { InlineRowActions } from '../components/shared/list/InlineRowActions';
import { LinkButtonPrimary } from '../components/shared/buttons';
import type { Column } from '../components/shared/DataTable';
import { formatPostDate } from '../lib/formatPostDate';
import type { NavItem, SikshyaReactConfig, WpRestUser } from '../types';

type Variant = 'students' | 'instructors';

export function WpUserListPage(props: {
  config: SikshyaReactConfig;
  title: string;
  subtitle: string;
  variant: Variant;
}) {
  const { config, title, subtitle, variant } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');

  const roleSlug = variant === 'students' ? 'sikshya_student' : 'sikshya_instructor';
  const newUrl = `${adminBase}user-new.php`;

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

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle={subtitle}
      pageActions={<LinkButtonPrimary href={newUrl}>+ Add user</LinkButtonPrimary>}
    >
      <UserEntityListView
        roleSlug={roleSlug}
        contextHint={
          variant === 'students'
            ? 'Users with the Sikshya student role.'
            : 'Users with the Sikshya instructor role.'
        }
        searchPlaceholder={`Search ${variant === 'students' ? 'students' : 'instructors'}…`}
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
        emptyStateDescription="Try another search or add a user from WordPress."
        emptyStateAction={<LinkButtonPrimary href={newUrl}>+ Add user</LinkButtonPrimary>}
        skeletonHeaders={['ID', 'Name', 'Email', 'Registered']}
      />
    </AppShell>
  );
}
