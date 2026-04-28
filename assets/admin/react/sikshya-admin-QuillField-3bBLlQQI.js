import{r as l,j as e}from"./sikshya-admin-sikshya-react-E_TY_un4.js";import{R as x}from"./sikshya-admin-sikshya-editor-CKbNYVBT.js";function f(s){const{label:t,value:i,onChange:n,placeholder:d,disabled:r,help:a,minHeightPx:o=160}=s,b=l.useId(),c=l.useMemo(()=>({toolbar:[[{header:[2,3,!1]}],["bold","italic","underline"],[{list:"ordered"},{list:"bullet"}],["link"],["clean"]]}),[]),m=l.useMemo(()=>["header","bold","italic","underline","list","bullet","link"],[]);return e.jsxs("label",{className:"block","aria-label":t,children:[e.jsx("span",{className:"block text-sm font-medium text-slate-800 dark:text-slate-200",children:t}),a?e.jsx("span",{className:"mt-1 block text-xs text-slate-500 dark:text-slate-400",children:a}):null,e.jsx("div",{className:`mt-1.5 overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-sm focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white ${r?"opacity-70":""}`,children:e.jsx(x,{id:b,theme:"snow",readOnly:!!r,value:i,onChange:h=>n(h),placeholder:d,modules:c,formats:m,style:{minHeight:`${o}px`}})}),e.jsx("style",{children:`
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
          min-height: ${o}px;
        }
        .ql-editor.ql-blank::before {
          color: rgba(100, 116, 139, 0.9);
          font-style: normal;
        }
      `})]})}export{f as Q};
