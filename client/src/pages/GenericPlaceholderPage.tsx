import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { __ } from '../lib/i18n';
import type { SikshyaReactConfig } from '../types';

export function GenericPlaceholderPage(props: {
  embedded?: boolean;
  config: SikshyaReactConfig;
  title: string;
  description: string;
}) {
  const { config, title, description } = props;
  const localizedDescription = __(description, 'sikshya');
  return (
    <EmbeddableShell embedded={props.embedded} config={config} title={title} subtitle={localizedDescription}>
      <div className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <p className="text-slate-600">{localizedDescription}</p>
        <p className="mt-4 text-sm text-slate-400">
          {__('Data for this screen can be wired to REST or WP list APIs in a follow-up.', 'sikshya')}
        </p>
      </div>
    </EmbeddableShell>
  );
}
