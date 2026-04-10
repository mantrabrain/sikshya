import { useCallback, useRef, useState } from 'react';

/** Minimal typing for `wp.media()` frame (WordPress admin). */
type WpMediaFrame = {
  open: () => void;
  on: (event: string, handler: () => void) => void;
  state: () => {
    get: (sel: string) => {
      first: () => { toJSON: () => { url?: string } };
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
  /** When the user picks a file, receive the attachment ID (for `featured_media` on REST). */
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

  return (
    <div className="mt-1.5 space-y-3">
      <div className="flex flex-wrap items-center gap-3">
        <button
          type="button"
          disabled={mediaBusy}
          className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
          onClick={() => void openFrame()}
        >
          {mediaBusy ? 'Opening…' : value ? 'Replace image' : 'Add image'}
        </button>
        {value ? (
          <button
            type="button"
            className="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
            onClick={() => {
              onAttachmentIdChange?.(0);
              onChange('');
            }}
          >
            Remove
          </button>
        ) : null}
      </div>
      {value ? (
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50">
          <img src={value} alt="" className="max-h-48 w-full object-contain" />
        </div>
      ) : (
        <p className="text-xs text-slate-500 dark:text-slate-400">
          {placeholder || 'Choose an image from the media library.'}
        </p>
      )}
      {value ? (
        <input
          id={id}
          type="text"
          readOnly
          className={className}
          value={value}
          title="Image URL"
          aria-label="Image URL"
        />
      ) : (
        <span id={id} className="sr-only">
          No image selected
        </span>
      )}
    </div>
  );
}
