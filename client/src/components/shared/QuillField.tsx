import { useId, useMemo } from 'react';
import ReactQuill from 'react-quill';
import 'react-quill/dist/quill.snow.css';

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

  const formats = useMemo(() => {
    return ['header', 'bold', 'italic', 'underline', 'list', 'bullet', 'link'];
  }, []);

  return (
    <label className="block" aria-label={label}>
      <span className="block text-sm font-medium text-slate-800 dark:text-slate-200">{label}</span>
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
    </label>
  );
}

