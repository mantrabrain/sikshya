import { useCallback, useEffect, useRef, useState } from 'react';
import { ButtonSecondary } from './buttons';
import { useSikshyaDialog } from './SikshyaDialogContext';
import { __ } from '../../lib/i18n';
import { getWpApi } from '../../api';

/**
 * The document behind a PDF lesson: one media library file, or a URL.
 *
 * Single-select and PDF-only, so it is neither {@link WPMediaPickerField} (the
 * image-biased featured-image control) nor {@link LessonAttachmentsField} (an
 * ordered multi-select for downloads). The media frame is constrained by MIME
 * rather than by extension, because the server resolves the stored id the same
 * way and would silently ignore anything that is not a PDF.
 *
 * The URL field is only offered while no file is chosen. Both can technically
 * be stored, but the id always wins server-side, and a form that lets you fill
 * in a field that will then be ignored is a form that lies.
 */
type WpMediaItem = { id?: number | string; title?: string; url?: string; filename?: string };

type WpMediaFrame = {
  open: () => void;
  on: (event: string, handler: () => void) => void;
  state: () => { get: (sel: string) => { first: () => { toJSON: () => WpMediaItem } | undefined } };
};

type WpWithMedia = {
  media?: (attrs: {
    title?: string;
    button?: { text?: string };
    library?: { type?: string | string[] };
    multiple?: boolean;
  }) => WpMediaFrame;
};

type Props = {
  id: string;
  attachmentId: number;
  url: string;
  hideDownload: boolean;
  onAttachmentIdChange: (id: number) => void;
  onUrlChange: (url: string) => void;
  onHideDownloadChange: (hide: boolean) => void;
};

export function LessonDocumentField(props: Props) {
  const { id, attachmentId, url, hideDownload } = props;
  const { onAttachmentIdChange, onUrlChange, onHideDownloadChange } = props;
  const dialog = useSikshyaDialog();
  const frameRef = useRef<WpMediaFrame | null>(null);
  const [name, setName] = useState('');

  // Resolve the file name for an id that came back from the server; the picker
  // supplies its own, so this only runs on load.
  useEffect(() => {
    if (attachmentId <= 0) {
      setName('');
      return;
    }
    let cancelled = false;
    (async () => {
      try {
        const row = await getWpApi().get<{ id: number; title?: { rendered?: string }; source_url?: string }>(
          `/media/${attachmentId}?_fields=id,title,source_url`,
        );
        if (cancelled) {
          return;
        }
        const title = row?.title?.rendered?.trim();
        setName(title && title !== '' ? title : String(row?.source_url ?? `#${attachmentId}`));
      } catch {
        // Show the id rather than nothing: the file may be fine and only the
        // lookup failed, and blanking the row would look like data loss.
        if (!cancelled) {
          setName(`#${attachmentId}`);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [attachmentId]);

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
        title: __('Choose a PDF', 'sikshya'),
        button: { text: __('Use this PDF', 'sikshya') },
        library: { type: 'application/pdf' },
        multiple: false,
      });
      frame.on('select', () => {
        const picked = frame.state().get('selection').first()?.toJSON();
        if (!picked) {
          return;
        }
        const pid = typeof picked.id === 'number' ? picked.id : Number(picked.id);
        if (!Number.isFinite(pid) || pid <= 0) {
          return;
        }
        setName(String(picked.title || picked.filename || picked.url || pid));
        onAttachmentIdChange(pid);
      });
      frameRef.current = frame;
    }

    frameRef.current.open();
  }, [dialog, onAttachmentIdChange]);

  return (
    <div className="mt-1.5 space-y-3">
      {attachmentId > 0 ? (
        <div className="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-200">
          <span className="truncate" title={name || `#${attachmentId}`}>
            {name || `#${attachmentId}`}
          </span>
          <button
            type="button"
            className="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40"
            onClick={() => {
              onAttachmentIdChange(0);
              setName('');
            }}
          >
            {__('Remove', 'sikshya')}
          </button>
        </div>
      ) : (
        <>
          <span id={id}>
            <ButtonSecondary type="button" onClick={openFrame}>
              {__('Choose a PDF', 'sikshya')}
            </ButtonSecondary>
          </span>
          <div>
            <label
              className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
              htmlFor="sik-lesson-pdf-url"
            >
              {__('…or paste a document URL', 'sikshya')}
            </label>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
              {__(
                'A file in your media library is served through a signed, expiring link that only the signed-in learner can use. A document on another host is served by that host, under whatever rules it applies.',
                'sikshya',
              )}
            </p>
            <input
              id="sik-lesson-pdf-url"
              className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              value={url}
              onChange={(e) => onUrlChange(e.target.value)}
              placeholder="https://example.com/handbook.pdf"
            />
          </div>
        </>
      )}
      <label className="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200" htmlFor="sik-lesson-pdf-hide-download">
        <input
          id="sik-lesson-pdf-hide-download"
          type="checkbox"
          className="mt-0.5"
          checked={hideDownload}
          onChange={(e) => onHideDownloadChange(e.target.checked)}
        />
        <span>
          {__('Hide the download link', 'sikshya')}
          <span className="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
            {__(
              'Removes the Download link under the viewer. It does not stop a determined learner saving the file — the browser’s own PDF viewer has a save button, and no web page can take it away.',
              'sikshya',
            )}
          </span>
        </span>
      </label>
    </div>
  );
}
