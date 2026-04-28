import { useCallback, useRef } from 'react';

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

/**
 * Opens the native WordPress media modal (`wp.media`).
 * PHP side must call `wp_enqueue_media()` on the admin screen.
 */
export function WPMediaPickerField(props: Props) {
  const { id, value, onChange, onAttachmentIdChange, className, placeholder, imageOnly = true } = props;
  const frameRef = useRef<WpMediaFrame | null>(null);

  const openFrame = useCallback(() => {
    if (typeof window.wp?.media !== 'function') {
      // eslint-disable-next-line no-alert
      window.alert('WordPress media library is not loaded. Please reload the page.');
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
  }, [imageOnly, onChange, onAttachmentIdChange]);

  const zoneLabel = value ? (imageOnly ? 'Replace image' : 'Replace file') : imageOnly ? 'Upload or choose image' : 'Upload or choose file';
  const hint = placeholder || 'Opens the WordPress media library. You can upload a new file and insert it.';

  const zoneClass = value
    ? 'h-32 sm:h-36'
    : 'min-h-[5.5rem] py-4';

  return (
    <div className="mt-1.5 space-y-2">
      <button
        type="button"
        id={id}
        onClick={openFrame}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openFrame();
          }
        }}
        className={`group relative flex w-full flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/80 px-3 text-center transition hover:border-brand-300 hover:bg-brand-50/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-800/40 dark:hover:border-brand-500/50 dark:hover:bg-brand-950/20 ${zoneClass}`}
      >
        {value ? (
          imageOnly ? (
            <div className="pointer-events-none absolute inset-1.5 overflow-hidden rounded-lg border border-slate-200/80 bg-white dark:border-slate-700 dark:bg-slate-900">
              <img src={value} alt="" className="h-full w-full object-contain object-center" />
            </div>
          ) : (
            <div className="pointer-events-none absolute inset-1.5 flex items-center justify-center overflow-hidden rounded-lg border border-slate-200/80 bg-white px-3 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
              <span className="truncate">{value}</span>
            </div>
          )
        ) : (
          <>
            <span className="rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600">
              {zoneLabel}
            </span>
            <span className="max-w-[18rem] text-[11px] leading-snug text-slate-500 dark:text-slate-400">{hint}</span>
            <span className="text-[10px] font-medium text-brand-600 group-hover:text-brand-700 dark:text-brand-400 dark:group-hover:text-brand-300">
              Click to open media library
            </span>
          </>
        )}
        {value ? (
          <span className="relative z-[1] mt-auto mb-1 rounded-md bg-white/90 px-2 py-1 text-[10px] font-semibold text-slate-800 shadow-sm ring-1 ring-slate-200 backdrop-blur-sm dark:bg-slate-900/90 dark:text-slate-100 dark:ring-slate-600">
            Click to replace
          </span>
        ) : null}
      </button>
      {value ? (
        <div className="flex flex-col gap-1.5 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-3">
          <button
            type="button"
            className="shrink-0 text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
            onClick={() => {
              onAttachmentIdChange?.(0);
              onChange('');
            }}
          >
            {imageOnly ? 'Remove image' : 'Remove file'}
          </button>
          <input
            type="text"
            readOnly
            className={className ? `${className} max-w-full text-xs` : 'max-w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800/50 dark:text-slate-300'}
            value={value}
            title={imageOnly ? 'Image URL' : 'File URL'}
            aria-label={imageOnly ? 'Image URL' : 'File URL'}
          />
        </div>
      ) : (
        <span className="sr-only">{hint}</span>
      )}
    </div>
  );
}
