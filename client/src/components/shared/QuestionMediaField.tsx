import { useCallback, useEffect, useRef, useState } from 'react';
import { ButtonSecondary } from './buttons';
import { useSikshyaDialog } from './SikshyaDialogContext';
import { __ } from '../../lib/i18n';
import { getWpApi } from '../../api';

/**
 * One image or audio clip for a question's stem.
 *
 * The media library frame is opened for images *and* audio in one list,
 * because the author is picking "the thing this question is about" and does
 * not think of it as two decisions. The kind is then derived from what they
 * picked, never asked — except for an external URL whose path carries no
 * usable extension, where there is nothing to derive it from.
 *
 * Alt text is a required field for an image, and the parent refuses to save
 * without it. That is not pedantry: the image *is* the question, so a learner
 * using a screen reader has nothing to answer from.
 */
type WpMediaItem = {
  id?: number | string;
  title?: string;
  url?: string;
  filename?: string;
  type?: string;
  mime?: string;
  alt?: string;
};

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

export type QuestionMediaKind = '' | 'image' | 'audio';

type Props = {
  id: string;
  attachmentId: number;
  url: string;
  kind: QuestionMediaKind;
  alt: string;
  onAttachmentIdChange: (id: number) => void;
  onUrlChange: (url: string) => void;
  onKindChange: (kind: QuestionMediaKind) => void;
  onAltChange: (alt: string) => void;
  /** Shown under the alt field when the parent blocked a save. */
  altError?: string | null;
};

const FIELD =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
const SUBLABEL = 'block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
const HINT = 'mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400';

/** Extension → kind, mirroring `Sikshya\Services\QuestionMedia::URL_EXTENSIONS`. */
const URL_EXTENSIONS: Record<string, QuestionMediaKind> = {
  jpg: 'image',
  jpeg: 'image',
  png: 'image',
  gif: 'image',
  webp: 'image',
  avif: 'image',
  mp3: 'audio',
  m4a: 'audio',
  aac: 'audio',
  oga: 'audio',
  ogg: 'audio',
  wav: 'audio',
  weba: 'audio',
  flac: 'audio',
};

export function kindFromUrl(url: string): QuestionMediaKind {
  const path = url.split('?')[0].split('#')[0];
  const dot = path.lastIndexOf('.');
  if (dot < 0) {
    return '';
  }
  return URL_EXTENSIONS[path.slice(dot + 1).toLowerCase()] ?? '';
}

export function QuestionMediaField(props: Props) {
  const { id, attachmentId, url, kind, alt, altError } = props;
  // Ids derive from the one the caller gave us: the full-page editor and the
  // in-course modal render this same field, and a duplicated `id` would point
  // two labels at one input.
  const urlId = `${id}-url`;
  const kindId = `${id}-kind`;
  const altId = `${id}-alt`;
  const altErrorId = `${id}-alt-error`;
  const { onAttachmentIdChange, onUrlChange, onKindChange, onAltChange } = props;
  const dialog = useSikshyaDialog();
  const frameRef = useRef<WpMediaFrame | null>(null);
  const [name, setName] = useState('');
  const [preview, setPreview] = useState('');

  // Resolve the file's name, kind and a preview for an id that came back from
  // the server. The picker supplies all three itself, so this is load-only.
  useEffect(() => {
    if (attachmentId <= 0) {
      setName('');
      setPreview('');
      return;
    }
    let cancelled = false;
    (async () => {
      try {
        const row = await getWpApi().get<{
          id: number;
          title?: { rendered?: string };
          source_url?: string;
          media_type?: string;
          alt_text?: string;
        }>(`/media/${attachmentId}?_fields=id,title,source_url,media_type,alt_text`);
        if (cancelled) {
          return;
        }
        const title = row?.title?.rendered?.trim();
        setName(title && title !== '' ? title : String(row?.source_url ?? `#${attachmentId}`));
        const mt = String(row?.media_type ?? '');
        if (mt === 'image' || mt === 'audio') {
          onKindChange(mt);
          setPreview(mt === 'image' ? String(row?.source_url ?? '') : '');
        }
      } catch {
        if (!cancelled) {
          setName(`#${attachmentId}`);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
    // `onKindChange` is intentionally omitted: it is a setState wrapper and
    // including it re-runs the lookup on every parent render.
    // eslint-disable-next-line react-hooks/exhaustive-deps
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
        title: __('Choose an image or audio clip', 'sikshya'),
        button: { text: __('Use this file', 'sikshya') },
        library: { type: ['image', 'audio'] },
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
        const type = String(picked.type ?? '');
        setName(String(picked.title || picked.filename || picked.url || pid));
        setPreview(type === 'image' ? String(picked.url ?? '') : '');
        onKindChange(type === 'audio' ? 'audio' : 'image');
        onAttachmentIdChange(pid);
        // The media library's own alt text is where a careful site already
        // keeps it; carry it over rather than making the author retype it.
        const pickedAlt = String(picked.alt ?? '').trim();
        if (pickedAlt !== '' && alt.trim() === '') {
          onAltChange(pickedAlt);
        }
      });
      frameRef.current = frame;
    }

    frameRef.current.open();
  }, [alt, dialog, onAltChange, onAttachmentIdChange, onKindChange]);

  const clear = () => {
    onAttachmentIdChange(0);
    onUrlChange('');
    onKindChange('');
    setName('');
    setPreview('');
  };

  const hasMedia = attachmentId > 0 || url.trim() !== '';
  const urlKind = kindFromUrl(url);
  const needsKindChoice = attachmentId <= 0 && url.trim() !== '' && urlKind === '';

  return (
    <div className="mt-1.5 space-y-3">
      {attachmentId > 0 ? (
        <div className="space-y-2">
          <div className="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-200">
            <span className="truncate" title={name || `#${attachmentId}`}>
              {name || `#${attachmentId}`}
              {kind !== '' ? (
                <span className="ml-2 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{kind}</span>
              ) : null}
            </span>
            <button
              type="button"
              className="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40"
              onClick={clear}
            >
              {__('Remove', 'sikshya')}
            </button>
          </div>
          {preview !== '' ? (
            <img
              src={preview}
              alt=""
              className="max-h-40 rounded-xl border border-slate-200 dark:border-slate-700"
            />
          ) : null}
        </div>
      ) : (
        <>
          <span id={id}>
            <ButtonSecondary type="button" onClick={openFrame}>
              {__('Choose an image or audio clip', 'sikshya')}
            </ButtonSecondary>
          </span>
          <div>
            <label className={SUBLABEL} htmlFor={urlId}>
              {__('…or paste a media URL', 'sikshya')}
            </label>
            <p className={HINT}>
              {__(
                'A file from your media library is served through a signed, expiring link that only the signed-in learner can use. A file on another host is served by that host.',
                'sikshya',
              )}
            </p>
            <input
              id={urlId}
              className={FIELD}
              value={url}
              onChange={(e) => {
                onUrlChange(e.target.value);
                const k = kindFromUrl(e.target.value);
                if (k !== '') {
                  onKindChange(k);
                }
              }}
              placeholder="https://example.com/waveform.png"
            />
          </div>
          {needsKindChoice ? (
            <div>
              <label className={SUBLABEL} htmlFor={kindId}>
                {__('What kind of file is it?', 'sikshya')}
              </label>
              <p className={HINT}>
                {__(
                  'That URL has no file extension to go by, and guessing would mean showing an audio file as a picture.',
                  'sikshya',
                )}
              </p>
              <select
                id={kindId}
                className={FIELD}
                value={kind}
                onChange={(e) => onKindChange(e.target.value as QuestionMediaKind)}
              >
                <option value="">{__('Choose…', 'sikshya')}</option>
                <option value="image">{__('Image', 'sikshya')}</option>
                <option value="audio">{__('Audio', 'sikshya')}</option>
              </select>
            </div>
          ) : null}
        </>
      )}

      {hasMedia && kind === 'image' ? (
        <div>
          <label className={SUBLABEL} htmlFor={altId}>
            {__('Describe the image (required)', 'sikshya')}
          </label>
          <p className={HINT}>
            {__(
              'The image is part of the question, so a learner using a screen reader has nothing to answer from without this. Describe what has to be read off it — not "diagram".',
              'sikshya',
            )}
          </p>
          <input
            id={altId}
            className={FIELD}
            value={alt}
            onChange={(e) => onAltChange(e.target.value)}
            aria-invalid={altError ? true : undefined}
            aria-describedby={altError ? altErrorId : undefined}
            placeholder={__('e.g. A supply curve crossing demand at 40 units, £12', 'sikshya')}
          />
          {altError ? (
            <p id={altErrorId} className="mt-1.5 text-xs font-semibold text-rose-600" role="alert">
              {altError}
            </p>
          ) : null}
        </div>
      ) : null}

      {hasMedia && kind === 'audio' ? (
        <div>
          <label className={SUBLABEL} htmlFor={altId}>
            {__('Describe the audio (optional)', 'sikshya')}
          </label>
          <p className={HINT}>
            {__(
              'Names the player for screen readers. Leave it empty and it is announced as "Audio for question N".',
              'sikshya',
            )}
          </p>
          <input
            id={altId}
            className={FIELD}
            value={alt}
            onChange={(e) => onAltChange(e.target.value)}
            placeholder={__('e.g. A 20-second clip of a heart murmur', 'sikshya')}
          />
        </div>
      ) : null}
    </div>
  );
}
