import { useCallback, useEffect, useRef, useState } from 'react';
import { ButtonSecondary } from './buttons';
import { useSikshyaDialog } from './SikshyaDialogContext';
import { __ } from '../../lib/i18n';
import { getWpApi } from '../../api';

/**
 * Multi-select attachment picker for a lesson's downloadable materials.
 *
 * The existing {@link WPMediaPickerField} is deliberately single-select and
 * image-biased (it is the featured-image control), so attachments get their own
 * component rather than growing that one a second mode.
 *
 * Only the ids are persisted. Titles are resolved from the media library for
 * display, which keeps a renamed file from showing a stale name here and avoids
 * duplicating library metadata into post meta where it would drift.
 */
type WpMediaSelection = {
  map: <T>(cb: (item: { toJSON: () => { id?: number | string; title?: string; url?: string } }) => T) => T[];
};

type WpMediaFrameMulti = {
  open: () => void;
  on: (event: string, handler: () => void) => void;
  state: () => { get: (sel: string) => WpMediaSelection };
};

type WpWithMedia = {
  media?: (attrs: { title?: string; library?: { type?: string }; multiple?: boolean }) => WpMediaFrameMulti;
};

export type LessonAttachment = {
  id: number;
  title: string;
};

type Props = {
  id: string;
  value: number[];
  onChange: (ids: number[]) => void;
};

export function LessonAttachmentsField(props: Props) {
  const { id, value, onChange } = props;
  const dialog = useSikshyaDialog();
  const frameRef = useRef<WpMediaFrameMulti | null>(null);
  const [titles, setTitles] = useState<Record<number, string>>({});

  // Resolve names for ids that arrived from the server (the picker supplies its
  // own titles, so only unknown ids are fetched).
  useEffect(() => {
    const unknown = value.filter((aid) => aid > 0 && !titles[aid]);
    if (unknown.length === 0) {
      return;
    }
    let cancelled = false;
    (async () => {
      try {
        const rows = await getWpApi().get<Array<{ id: number; title?: { rendered?: string }; source_url?: string }>>(
          `/media?include=${unknown.join(',')}&per_page=${unknown.length}&_fields=id,title,source_url`,
        );
        if (cancelled || !Array.isArray(rows)) {
          return;
        }
        setTitles((prev) => {
          const next = { ...prev };
          for (const row of rows) {
            const name = row?.title?.rendered?.trim();
            next[row.id] = name && name !== '' ? name : String(row.source_url ?? row.id);
          }
          return next;
        });
      } catch {
        // A failed lookup must not blank the list; the id is shown instead.
        if (!cancelled) {
          setTitles((prev) => {
            const next = { ...prev };
            for (const aid of unknown) {
              if (!next[aid]) {
                next[aid] = `#${aid}`;
              }
            }
            return next;
          });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [value, titles]);

  const openFrame = useCallback(() => {
    const wp = (window as unknown as { wp?: WpWithMedia }).wp;
    if (typeof wp?.media !== 'function') {
      void dialog.alert({
        title: __('Media library unavailable', 'sikshya'),
        message: __('WordPress media library is not loaded. Please reload the page.', 'sikshya'),
      });
      return;
    }

    if (!frameRef.current) {
      const frame = wp.media({
        title: __('Add lesson attachments', 'sikshya'),
        multiple: true,
      });
      frame.on('select', () => {
        const picked = frame
          .state()
          .get('selection')
          .map((item) => item.toJSON());

        const additions: LessonAttachment[] = [];
        for (const item of picked) {
          const aid = typeof item.id === 'number' ? item.id : Number(item.id);
          if (!Number.isFinite(aid) || aid <= 0) {
            continue;
          }
          additions.push({ id: aid, title: String(item.title ?? item.url ?? aid) });
        }
        if (additions.length === 0) {
          return;
        }

        setTitles((prev) => {
          const next = { ...prev };
          for (const a of additions) {
            next[a.id] = a.title;
          }
          return next;
        });

        // Append, skipping anything already attached, so re-opening the modal
        // does not duplicate a file.
        onChange(
          Array.from(new Set([...value, ...additions.map((a) => a.id)])).filter((n) => n > 0),
        );
      });
      frameRef.current = frame;
    }

    frameRef.current.open();
  }, [dialog, onChange, value]);

  const remove = (aid: number) => {
    onChange(value.filter((n) => n !== aid));
  };

  const move = (index: number, delta: number) => {
    const target = index + delta;
    if (target < 0 || target >= value.length) {
      return;
    }
    const next = [...value];
    const [item] = next.splice(index, 1);
    next.splice(target, 0, item);
    onChange(next);
  };

  return (
    <div className="mt-1.5">
      {value.length > 0 && (
        <ul className="mb-3 space-y-2" data-sikshya-lesson-attachments>
          {value.map((aid, index) => (
            <li
              key={aid}
              className="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-200"
            >
              <span className="truncate" title={titles[aid] ?? `#${aid}`}>
                {titles[aid] ?? `#${aid}`}
              </span>
              <span className="flex shrink-0 items-center gap-1">
                <button
                  type="button"
                  className="rounded-lg px-2 py-1 text-xs text-slate-500 hover:bg-slate-200 disabled:opacity-40 dark:hover:bg-slate-700"
                  onClick={() => move(index, -1)}
                  disabled={index === 0}
                  aria-label={__('Move up', 'sikshya')}
                >
                  ↑
                </button>
                <button
                  type="button"
                  className="rounded-lg px-2 py-1 text-xs text-slate-500 hover:bg-slate-200 disabled:opacity-40 dark:hover:bg-slate-700"
                  onClick={() => move(index, 1)}
                  disabled={index === value.length - 1}
                  aria-label={__('Move down', 'sikshya')}
                >
                  ↓
                </button>
                <button
                  type="button"
                  className="rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                  onClick={() => remove(aid)}
                >
                  {__('Remove', 'sikshya')}
                </button>
              </span>
            </li>
          ))}
        </ul>
      )}
      <span id={id}>
        <ButtonSecondary type="button" onClick={openFrame}>
          {value.length > 0 ? __('Add more files', 'sikshya') : __('Add files', 'sikshya')}
        </ButtonSecondary>
      </span>
    </div>
  );
}
