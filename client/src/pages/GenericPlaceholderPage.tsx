import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import type { SikshyaReactConfig } from '../types';

export function GenericPlaceholderPage(props: {
  embedded?: boolean;
  config: SikshyaReactConfig;
  title: string;
  description: string;
}) {
  const { config, title, description } = props;
  return (
    <EmbeddableShell embedded={props.embedded} config={config} title={title} subtitle={description}>
      <div className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <p className="text-slate-600">{description}</p>
        <p className="mt-4 text-sm text-slate-400">
          Data for this screen can be wired to REST or WP list APIs in a follow-up.
        </p>
      </div>
    </EmbeddableShell>
  );
}
