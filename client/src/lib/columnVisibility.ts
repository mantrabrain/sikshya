const PREFIX = 'sikshya_cols_';

export function columnVisibilityStorageKey(listId: string): string {
  return `${PREFIX}${listId}`;
}

export function loadColumnVisibility(
  storageKey: string,
  pickable: { id: string; defaultHidden?: boolean }[]
): Record<string, boolean> {
  const defaults: Record<string, boolean> = {};
  for (const c of pickable) {
    defaults[c.id] = !c.defaultHidden;
  }
  try {
    const raw = localStorage.getItem(storageKey);
    if (!raw) {
      return defaults;
    }
    const parsed = JSON.parse(raw) as Record<string, unknown>;
    const merged = { ...defaults };
    for (const c of pickable) {
      if (typeof parsed[c.id] === 'boolean') {
        merged[c.id] = parsed[c.id] as boolean;
      }
    }
    return merged;
  } catch {
    return defaults;
  }
}

export function saveColumnVisibility(storageKey: string, visibility: Record<string, boolean>) {
  try {
    localStorage.setItem(storageKey, JSON.stringify(visibility));
  } catch {
    /* ignore quota / private mode */
  }
}
