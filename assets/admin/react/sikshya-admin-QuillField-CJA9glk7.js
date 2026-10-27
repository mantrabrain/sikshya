import{r as a,j as r}from"./sikshya-admin-sikshya-react-E_TY_un4.js";import{R as w}from"./sikshya-admin-sikshya-editor-BMfQXWSC.js";import{ad as e,ae as A}from"./sikshya-admin-sikshya-vendor-BVAk_Gwu.js";function $(f){const{label:x,value:k,onChange:q,placeholder:p,disabled:o,help:n,minHeightPx:b=160}=f,s=a.useId(),d=a.useRef(null),y=a.useMemo(()=>({toolbar:[[{header:[2,3,!1]}],["bold","italic","underline"],[{list:"ordered"},{list:"bullet"}],["link"],["clean"]]}),[]);a.useEffect(()=>{const l=d.current;if(!l)return;const v=[[".ql-header.ql-picker .ql-picker-label",e("Text style","sikshya")],["button.ql-bold",e("Bold","sikshya")],["button.ql-italic",e("Italic","sikshya")],["button.ql-underline",e("Underline","sikshya")],['button.ql-list[value="ordered"]',e("Numbered list","sikshya")],['button.ql-list[value="bullet"]',e("Bulleted list","sikshya")],["button.ql-link",e("Insert link","sikshya")],["button.ql-clean",e("Clear formatting","sikshya")]],u=()=>{v.forEach(([t,i])=>{l.querySelectorAll(`.ql-toolbar ${t}`).forEach(m=>{m.getAttribute("aria-label")||m.setAttribute("aria-label",i)})}),l.querySelectorAll(".ql-editor").forEach(t=>{t.setAttribute("role","textbox"),t.setAttribute("aria-multiline","true"),t.setAttribute("aria-labelledby",`${s}-label`)}),l.querySelectorAll(".ql-toolbar .ql-picker-item").forEach(t=>{if(t.getAttribute("aria-label"))return;const i=t.getAttribute("data-value");t.setAttribute("aria-label",i?A(e("Heading %s","sikshya"),i):e("Normal text","sikshya"))})};u();const c=l.querySelector(".ql-toolbar");if(!c)return;const h=new MutationObserver(u);return h.observe(c,{childList:!0,subtree:!0}),()=>h.disconnect()},[o,s]);const g=a.useMemo(()=>["header","bold","italic","underline","list","bullet","link"],[]);return r.jsxs("div",{className:"block",ref:d,children:[r.jsx("span",{id:`${s}-label`,className:"block text-sm font-medium text-slate-800 dark:text-slate-200",children:x}),n?r.jsx("span",{className:"mt-1 block text-xs text-slate-500 dark:text-slate-400",children:n}):null,r.jsx("div",{className:`mt-1.5 overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-sm focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white ${o?"opacity-70":""}`,children:r.jsx(w,{id:s,theme:"snow",readOnly:!!o,value:k,onChange:l=>q(l),placeholder:p,modules:y,formats:g,style:{minHeight:`${b}px`}})}),r.jsx("style",{children:`
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
          min-height: ${b}px;
        }
        .ql-editor.ql-blank::before {
          color: rgba(100, 116, 139, 0.9);
          font-style: normal;
        }
      `})]})}export{$ as Q};
