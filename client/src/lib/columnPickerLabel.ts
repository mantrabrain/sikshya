import type { Column } from '../components/shared/DataTable';

/** Stable string label for column picker + skeleton rows when `header` is not plain text. */
export function resolveColumnPickerLabel<T>(col: Column<T>): string {
  if (typeof col.columnPickerLabel === 'string' && col.columnPickerLabel.trim() !== '') {
    return col.columnPickerLabel;
  }
  if (typeof col.header === 'string') {
    return col.header;
  }
  return 'Column';
}
