import { useCallback, useRef, useState } from 'react';

/** Minimal typing for `wp.media()` frame (WordPress admin). */
type WpMediaFrame = {
  open: () => void;
  on: (event: string, handler: () => void) => void;
  state: () => {
    get: (sel: string) => {
      first: () => { toJSON: () => { id?: number | string; url?: string } };
    };
  };
};

type WpMediaFn = (attrs: { library?: { type?: string }; multiple?: boolean }) => WpMediaFrame;

declare global {
  interface Window {
    wp?: {
      media: WpMediaFn;
    };
  }
}

type Props = {
  id: string;
  value: string;
  onChange: (url: string) => void;
  /** When the user picks a file, receive the attachment ID (for featured images). */
  onAttachmentIdChange?: (id: number) => void;
  className?: string;
  placeholder?: string;
  /** Restrict library to image mime types (default true for course featured image). */
  imageOnly?: boolean;
};

/**
 * Featured image / media URL using the WordPress media modal (`wp_enqueue_media` + `wp.media`).
 */
function waitForWpMedia(maxMs: number): Promise<boolean> {
  const start = Date.now();
  return new Promise((resolve) => {
    const tick = () => {
      if (typeof window.wp?.media === 'function') {
        resolve(true);
        return;
      }
      if (Date.now() - start >= maxMs) {
        resolve(false);
        return;
      }
      window.setTimeout(tick, 50);
    };
    tick();
  });
}

export function WPMediaPickerField(props: Props) {
  const { id, value, onChange, onAttachmentIdChange, className, placeholder, imageOnly = true } = props;
  const frameRef = useRef<WpMediaFrame | null>(null);
  const [mediaBusy, setMediaBusy] = useState(false);

  const openFrame = useCallback(async () => {
    setMediaBusy(true);
    try {
      const ok = await waitForWpMedia(4000);
      const wp = window.wp;
      if (!ok || typeof wp?.media !== 'function') {
        console.warn('Sikshya: wp.media is not available. Try reloading the page.');
        return;
      }
      if (!frameRef.current) {
        const frame = wp.media({
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
    } finally {
      setMediaBusy(false);
    }
  }, [imageOnly, onChange, onAttachmentIdChange]);

  const zoneLabel = value ? 'Replace image' : 'Upload or choose image';
  const hint = placeholder || 'Opens the WordPress media library. You can upload a new file and insert it.';

  return (
    <div className="mt-1.5 space-y-3">
      <button
        type="button"
        id={id}
        disabled={mediaBusy}
        onClick={() => void openFrame()}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            void openFrame();
          }
        }}
        className="group relative flex w-full min-h-[11rem] flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center transition hover:border-brand-300 hover:bg-brand-50/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800/40 dark:hover:border-brand-500/50 dark:hover:bg-brand-950/20"
      >
        {value ? (
          <div className="pointer-events-none absolute inset-2 overflow-hidden rounded-xl border border-slate-200/80 bg-white dark:border-slate-700 dark:bg-slate-900">
            <img src={value} alt="" className="h-full w-full object-contain" />
          </div>
        ) : (
          <>
            <span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600">
              {mediaBusy ? 'Opening…' : zoneLabel}
            </span>
            <span className="max-w-sm text-xs leading-relaxed text-slate-500 dark:text-slate-400">{hint}</span>
            <span className="text-[11px] font-medium text-brand-600 group-hover:text-brand-700 dark:text-brand-400 dark:group-hover:text-brand-300">
              Click to open media library
            </span>
          </>
        )}
        {value ? (
          <span className="relative z-[1] mt-auto rounded-lg bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm ring-1 ring-slate-200 backdrop-blur-sm dark:bg-slate-900/90 dark:text-slate-100 dark:ring-slate-600">
            {mediaBusy ? 'Opening…' : 'Click to replace'}
          </span>
        ) : null}
      </button>
      {value ? (
        <div className="flex flex-wrap items-center gap-3">
          <button
            type="button"
            className="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
            onClick={() => {
              onAttachmentIdChange?.(0);
              onChange('');
            }}
          >
            Remove image
          </button>
          <input type="text" readOnly className={className} value={value} title="Image URL" aria-label="Image URL" />
        </div>
      ) : (
        <span className="sr-only">{hint}</span>
      )}
    </div>
  );
}
