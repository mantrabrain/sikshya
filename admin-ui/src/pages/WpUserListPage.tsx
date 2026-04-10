import { useMemo } from 'react';
import { AppShell } from '../components/AppShell';
import { UserEntityListView } from '../components/shared/list/UserEntityListView';
import { RowActionsMenu } from '../components/shared/list/RowActionsMenu';
import { LinkButtonPrimary } from '../components/shared/buttons';
import type { Column } from '../components/shared/DataTable';
import { useEntityListMockEnabled } from '../lib/entityListMock';
import { MOCK_INSTRUCTORS, MOCK_STUDENTS } from '../lib/mockWpUsers';
import { formatPostDate } from '../lib/formatPostDate';
import type { NavItem, SikshyaReactConfig, WpRestUser } from '../types';

function stripTags(html: string): string {
  return html.replace(/<[^>]*>/g, '').trim();
}

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
  const mockRows = variant === 'students' ? MOCK_STUDENTS : MOCK_INSTRUCTORS;
  const useMock = useEntityListMockEnabled(config);
  const newUrl = `${adminBase}user-new.php`;

  const columns: Column<WpRestUser>[] = useMemo(
    () => [
      {
        id: 'name',
        header: 'Name',
        render: (u) => (
          <div className="max-w-md">
            <a
              href={`${adminBase}user-edit.php?user_id=${u.id}`}
              className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
            >
              {u.name}
            </a>
            <div className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{u.slug}</div>
          </div>
        ),
      },
      {
        id: 'email',
        header: 'Email',
        cellClassName: 'text-slate-600 dark:text-slate-400',
        render: (u) => u.email || '—',
      },
      {
        id: 'registered',
        header: 'Registered',
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (u) => formatPostDate(u.registered_date),
      },
      {
        id: 'actions',
        header: '',
        alwaysVisible: true,
        headerClassName: 'w-14 text-right',
        cellClassName: 'text-right',
        render: (u) => {
          const label = stripTags(u.name) || 'User';
          const editUrl = `${adminBase}user-edit.php?user_id=${u.id}`;
          return (
            <RowActionsMenu
              ariaLabel={`Actions for ${label}`}
              items={[{ key: 'edit', label: 'Edit user', href: editUrl }]}
            />
          );
        },
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
        ]}
        defaultSortField="name"
        columnPickerStorageKey={`users_${variant}`}
        columns={columns}
        emptyMessage="No users match your search."
        emptyStateTitle="No users found"
        emptyStateDescription="Try another search or add a user from WordPress."
        emptyStateAction={<LinkButtonPrimary href={newUrl}>+ Add user</LinkButtonPrimary>}
        skeletonHeaders={['Name', 'Email', 'Registered', '']}
        useMockPlaceholder={useMock}
        mockPlaceholderRows={mockRows}
      />
    </AppShell>
  );
}
