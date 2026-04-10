import type { SikshyaReactConfig } from '../types';

/** Enable sample rows when the API returns none (Vite dev or `initialData.useEntityListMock` / legacy `useCourseListMock`). */
export function useEntityListMockEnabled(config: SikshyaReactConfig): boolean {
  const d = config.initialData as { useEntityListMock?: boolean; useCourseListMock?: boolean };
  return import.meta.env.DEV || Boolean(d.useEntityListMock || d.useCourseListMock);
}
