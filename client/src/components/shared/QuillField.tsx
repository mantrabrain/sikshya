import { useEffect, useId, useMemo, useRef } from 'react';
import ReactQuill from 'react-quill';
import 'react-quill/dist/quill.snow.css';
import { __, sprintf } from '../../lib/i18n';

type Props = {
  label: string;
  value: string;
  onChange: (nextHtml: string) => void;
  placeholder?: string;
  disabled?: boolean;
  help?: string;
  minHeightPx?: number;
};

export function QuillField(props: Props) {
  const { label, value, onChange, placeholder, disabled, help, minHeightPx = 160 } = props;
  const id = useId();
  const shellRef = useRef<HTMLDivElement | null>(null);

  const modules = useMemo(() => {
    return {
      toolbar: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link'],
        ['clean'],
      ],
    };
  }, []);

  /*
   * Quill's toolbar is drawn as icon-only buttons with nothing but an `<svg>`
   * inside, so every control reaches a screen reader as an unnamed button and
   * the heading picker as an unnamed `role="button"`. It is third-party markup
   * we never author, which is exactly why it has to be labelled from here —
   * the alternative is a rich-text editor that cannot be operated without
   * sight.
   *
   * Keyed by Quill's own class names. Anything Quill adds that is not in the
   * map keeps whatever name it has; guessing a label would be worse than
   * leaving it for the next scan to find.
   */
  useEffect(() => {
    const shell = shellRef.current;
    if (!shell) return;

    const labels: Array<[string, string]> = [
      ['.ql-header.ql-picker .ql-picker-label', __('Text style', 'sikshya')],
      ['button.ql-bold', __('Bold', 'sikshya')],
      ['button.ql-italic', __('Italic', 'sikshya')],
      ['button.ql-underline', __('Underline', 'sikshya')],
      ['button.ql-list[value="ordered"]', __('Numbered list', 'sikshya')],
      ['button.ql-list[value="bullet"]', __('Bulleted list', 'sikshya')],
      ['button.ql-link', __('Insert link', 'sikshya')],
      ['button.ql-clean', __('Clear formatting', 'sikshya')],
    ];

    const apply = () => {
      labels.forEach(([selector, name]) => {
        shell.querySelectorAll<HTMLElement>(`.ql-toolbar ${selector}`).forEach((el) => {
          if (!el.getAttribute('aria-label')) el.setAttribute('aria-label', name);
        });
      });

      // The editable area is a `contenteditable` div, so a `<label>` cannot be
      // associated with it the way it would with an `<input>`.
      shell.querySelectorAll<HTMLElement>('.ql-editor').forEach((el) => {
        el.setAttribute('role', 'textbox');
        el.setAttribute('aria-multiline', 'true');
        el.setAttribute('aria-labelledby', `${id}-label`);
      });

      shell.querySelectorAll<HTMLElement>('.ql-toolbar .ql-picker-item').forEach((el) => {
        if (el.getAttribute('aria-label')) return;
        const value = el.getAttribute('data-value');
        el.setAttribute('aria-label', value ? sprintf(__('Heading %s', 'sikshya'), value) : __('Normal text', 'sikshya'));
      });
    };

    apply();

    const toolbar = shell.querySelector('.ql-toolbar');
    if (!toolbar) return;

    // Quill re-renders the picker's options as the selection changes, which
    // drops the labels we just set.
    const observer = new MutationObserver(apply);
    observer.observe(toolbar, { childList: true, subtree: true });

    return () => observer.disconnect();
  }, [disabled, id]);

  const formats = useMemo(() => {
    return ['header', 'bold', 'italic', 'underline', 'list', 'bullet', 'link'];
  }, []);

  return (
    <div className="block" ref={shellRef}>
      {/* Not a `<label>`: it wrapped the toolbar and the editable area, so every
          click inside the editor was a click on the label, and the field it was
          supposed to name is a `contenteditable` div that a `<label>` cannot be
          associated with anyway. */}
      <span id={`${id}-label`} className="block text-sm font-medium text-slate-800 dark:text-slate-200">{label}</span>
      {help ? <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">{help}</span> : null}
      <div
        className={`mt-1.5 overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-sm focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white ${
          disabled ? 'opacity-70' : ''
        }`}
      >
        <ReactQuill
          id={id}
          theme="snow"
          readOnly={Boolean(disabled)}
          value={value}
          onChange={(html) => onChange(html)}
          placeholder={placeholder}
          modules={modules}
          formats={formats}
          style={{ minHeight: `${minHeightPx}px` }}
        />
      </div>
      <style>{`
        /* Make Quill match Sikshya inputs a bit better */
        .ql-toolbar.ql-snow {
          border: 0;
          border-bottom: 1px solid rgba(148, 163, 184, 0.35);
        }
        .ql-container.ql-snow {
          border: 0;
          font-family: inherit;
        }
        .ql-editor {
          padding: 12px 14px;
          min-height: ${minHeightPx}px;
        }
        .ql-editor.ql-blank::before {
          color: rgba(100, 116, 139, 0.9);
          font-style: normal;
        }
      `}</style>
    </div>
  );
}

