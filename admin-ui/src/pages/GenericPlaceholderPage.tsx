import { AppShell } from '../components/AppShell';
import type { NavItem, SikshyaReactConfig } from '../types';

export function GenericPlaceholderPage(props: {
  config: SikshyaReactConfig;
  title: string;
  description: string;
}) {
  const { config, title, description } = props;
  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle={description}
    >
      <div className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <p className="text-slate-600">{description}</p>
        <p className="mt-4 text-sm text-slate-400">
          Data for this screen can be wired to REST or WP list APIs in a follow-up.
        </p>
      </div>
    </AppShell>
  );
}
