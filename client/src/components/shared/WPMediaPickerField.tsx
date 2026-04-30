import { useCallback, useRef } from 'react';
import { NavIcon } from '../NavIcon';
import { ButtonSecondary } from './buttons';
import { useSikshyaDialog } from './SikshyaDialogContext';

/** Compact URL preview; merge with caller `FIELD_INPUT` without double margins. */
const URL_PREVIEW_BASE =
  'mt-2 w-full max-w-lg truncate rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-snug text-slate-600 shadow-sm dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-400';

function mergedUrlPreviewClass(passed?: string): string {
  if (!passed || passed.trim() === '') {
    return URL_PREVIEW_BASE;
  }
  const cleaned = passed
    .replace(/\bmt-1\.5\b/g, '')
    .replace(/\s+/g, ' ')
    .trim();

  return `${URL_PREVIEW_BASE} ${cleaned}`.trim();
}

/** Minimal typing for the `wp.media()` frame returned by WordPress. */
type WpMediaFrame = {
  open: () => void;
  on: (event: string, handler: () => void) => void;
  state: () => {
    get: (sel: string) => {
      first: () => { toJSON: () => { id?: number | string; url?: string } };
    };
  };
};

declare global {
  interface Window {
    wp?: {
      media?: (attrs: { library?: { type?: string }; multiple?: boolean }) => WpMediaFrame;
    };
  }
}

type Props = {
  id: string;
  value: string;
  onChange: (url: string) => void;
  onAttachmentIdChange?: (id: number) => void;
  className?: string;
  placeholder?: string;
  imageOnly?: boolean;
};

const THUMB_BTN =
  'group relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35';

/**
 * Opens the native WordPress media modal (`wp.media`).
 * PHP side must call `wp_enqueue_media()` on the admin screen.
 *
 * Compact layout: fixed thumbnail target + browse / replace actions (no full‑width drop zone).
 */
export function WPMediaPickerField(props: Props) {
  const { id, value, onChange, onAttachmentIdChange, className, placeholder, imageOnly = true } = props;
  const dialog = useSikshyaDialog();
  const frameRef = useRef<WpMediaFrame | null>(null);

  const openFrame = useCallback(() => {
    if (typeof window.wp?.media !== 'function') {
      void dialog.alert({
        title: 'Media library unavailable',
        message: 'WordPress media library is not loaded. Please reload the page.',
      });
      return;
    }

    if (!frameRef.current) {
      const frame = window.wp.media({
        library: imageOnly ? { type: 'image' } : {},
        multiple: false,
      });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        const url = attachment.url;
        const aid = typeof attachment.id === 'number' ? attachment.id : Number(attachment.id);
        if (Number.isFinite(aid) && aid > 0) {
          onAttachmentIdChange?.(aid);
        }
        if (typeof url === 'string' && url.length > 0) {
          onChange(url);
        }
      });
      frameRef.current = frame;
    }

    frameRef.current.open();
  }, [dialog, imageOnly, onChange, onAttachmentIdChange]);

  const thumbLabel = imageOnly ? 'Featured image thumbnail' : 'Selected file';

  const hint =
    placeholder || 'Opens the WordPress media library — upload something new or pick an existing file.';

  const chooseCtaLabel = imageOnly ? 'Choose image' : 'Choose file';

  const thumbStateClass = value
    ? 'border-slate-200 bg-white hover:border-brand-300 dark:border-slate-600 dark:bg-slate-900 dark:hover:border-brand-500/60'
    : 'border-dashed border-slate-200 bg-slate-50/90 hover:border-brand-300 hover:bg-brand-50/50 dark:border-slate-600 dark:bg-slate-800/70 dark:hover:border-brand-500/50 dark:hover:bg-brand-950/20';

  const inputClass = mergedUrlPreviewClass(className);

  const remove = () => {
    onAttachmentIdChange?.(0);
    onChange('');
  };

  return (
    <div className="mt-1.5 inline-block w-full max-w-xl align-top">
      <div className="flex flex-wrap items-start gap-3">
        <button
          type="button"
          id={id}
          title={thumbLabel}
          aria-label={value ? (imageOnly ? 'Replace featured image' : 'Replace file') : chooseCtaLabel}
          onClick={openFrame}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              openFrame();
            }
          }}
          className={`${THUMB_BTN} ${thumbStateClass}`}
        >
          {value ? (
            imageOnly ? (
              <img src={value} alt="" className="h-full w-full object-cover" loading="lazy" />
            ) : (
              <div className="flex h-full w-full flex-col items-center justify-center gap-0.5 bg-slate-50 px-1 dark:bg-slate-800/80">
                <NavIcon name="clipboard" className="h-5 w-5 text-slate-400 dark:text-slate-500" />
                <span className="max-w-full truncate px-0.5 text-[9px] font-medium text-slate-500 dark:text-slate-400">
                  File
                </span>
              </div>
            )
          ) : (
            <NavIcon
              name={imageOnly ? 'photoImage' : 'clipboard'}
              className="h-6 w-6 text-slate-400 transition group-hover:text-brand-500 dark:text-slate-500 dark:group-hover:text-brand-400"
            />
          )}
        </button>

        <div className="min-w-0 flex-1">
          {!value ? (
            <>
              <ButtonSecondary type="button" onClick={openFrame} className="px-3 py-1.5 text-xs font-semibold shadow-sm">
                {chooseCtaLabel}
              </ButtonSecondary>
              <p className="mt-1.5 max-w-lg text-xs leading-relaxed text-slate-500 dark:text-slate-400">{hint}</p>
              <span className="sr-only">{hint}</span>
            </>
          ) : (
            <>
              <div className="flex flex-wrap items-center gap-2">
                <ButtonSecondary
                  type="button"
                  onClick={openFrame}
                  className="px-3 py-1.5 text-xs font-semibold shadow-sm"
                  aria-label={imageOnly ? 'Replace image from media library' : 'Replace file from media library'}
                >
                  {imageOnly ? 'Replace' : 'Replace file'}
                </ButtonSecondary>
                <button
                  type="button"
                  className="text-xs font-semibold text-red-600 underline-offset-2 hover:text-red-800 hover:underline dark:text-red-400 dark:hover:text-red-300"
                  onClick={remove}
                >
                  {imageOnly ? 'Remove' : 'Remove file'}
                </button>
              </div>
              <input type="text" readOnly tabIndex={-1} className={inputClass} value={value} title="URL" aria-label="File URL" />
            </>
          )}
        </div>
      </div>
    </div>
  );
}
