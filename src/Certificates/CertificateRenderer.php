<?php

namespace Sikshya\Certificates;

use Sikshya\Services\PermalinkService;

/**
 * Lightweight public certificate renderer for the free plugin.
 *
 * Pro can override with richer rendering; this exists so public hash links work without Pro.
 */
final class CertificateRenderer
{
    /**
     * @return array{w:string,h:string,ar:string}
     */
    private static function pageDimsForTemplate(int $template_id): array
    {
        $page_size = $template_id > 0 ? sanitize_key((string) get_post_meta($template_id, '_sikshya_certificate_page_size', true)) : '';
        $orientation = $template_id > 0 ? sanitize_key((string) get_post_meta($template_id, '_sikshya_certificate_orientation', true)) : '';

        $portrait = $orientation === 'portrait';
        if ($page_size === 'a5') {
            return $portrait
                ? ['w' => '148mm', 'h' => '210mm', 'ar' => '148 / 210']
                : ['w' => '210mm', 'h' => '148mm', 'ar' => '210 / 148'];
        }
        if ($page_size === 'a4' || $page_size === '') {
            return $portrait
                ? ['w' => '210mm', 'h' => '297mm', 'ar' => '210 / 297']
                : ['w' => '297mm', 'h' => '210mm', 'ar' => '297 / 210'];
        }
        return $portrait
            ? ['w' => '8.5in', 'h' => '11in', 'ar' => '8.5 / 11']
            : ['w' => '11in', 'h' => '8.5in', 'ar' => '11 / 8.5'];
    }

    /**
     * Physical page size in PDF points (1 pt = 1/72 in) for client-side export sizing.
     *
     * @return array{w:float,h:float}
     */
    private static function exportPtsForTemplate(int $template_id): array
    {
        $page_size = $template_id > 0 ? sanitize_key((string) get_post_meta($template_id, '_sikshya_certificate_page_size', true)) : '';
        $orientation = $template_id > 0 ? sanitize_key((string) get_post_meta($template_id, '_sikshya_certificate_orientation', true)) : '';
        $portrait = $orientation === 'portrait';

        $mmToPt = static function (float $mm): float {
            return $mm * 72.0 / 25.4;
        };

        if ($page_size === 'a5') {
            return $portrait
                ? ['w' => $mmToPt(148.0), 'h' => $mmToPt(210.0)]
                : ['w' => $mmToPt(210.0), 'h' => $mmToPt(148.0)];
        }
        if ($page_size === 'a4' || $page_size === '') {
            return $portrait
                ? ['w' => $mmToPt(210.0), 'h' => $mmToPt(297.0)]
                : ['w' => $mmToPt(297.0), 'h' => $mmToPt(210.0)];
        }
        return $portrait
            ? ['w' => 8.5 * 72.0, 'h' => 11.0 * 72.0]
            : ['w' => 11.0 * 72.0, 'h' => 8.5 * 72.0];
    }

    public static function publicUrlForHash(string $hash): string
    {
        $clean = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $hash) ?? '');
        $p = PermalinkService::get();
        $base = isset($p['rewrite_base_certificate']) ? PermalinkService::sanitizeSlug((string) $p['rewrite_base_certificate']) : 'certificates';
        if (PermalinkService::isPlainPermalinks()) {
            return home_url('/' . $base . '/?hash=' . rawurlencode($clean));
        }
        return user_trailingslashit(home_url('/' . $base . '/' . $clean));
    }

    public static function qrImgTag(string $target_url): string
    {
        $src = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . rawurlencode($target_url);
        return '<img class="sikshya-cert-qr" src="' . esc_url($src)
            . '" width="140" height="140" alt="" loading="lazy" crossorigin="anonymous" referrerpolicy="no-referrer" style="width:100%;height:100%;max-width:140px;max-height:140px;object-fit:contain;" />';
    }

    public static function wrap(string $inner, string $title, int $template_id = 0, string $share_url = '', string $hash = '', bool $show_controls = true, string $meta_line = ''): string
    {
        $t = $title !== '' ? esc_html($title) : esc_html__('Certificate', 'sikshya');
        $dims = self::pageDimsForTemplate($template_id);
        $w = esc_attr($dims['w']);
        $h = esc_attr($dims['h']);
        $ar = esc_attr($dims['ar']);
        $e = self::exportPtsForTemplate($template_id);
        $ew = esc_attr((string) round($e['w'], 2));
        $eh = esc_attr((string) round($e['h'], 2));
        $share_url = $share_url !== '' ? esc_url($share_url) : '';
        $hash_clean = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $hash) ?? '');
        $hash_clean = strlen($hash_clean) >= 16 ? $hash_clean : '';
        $show_controls = $show_controls && is_user_logged_in();
        $controls_class = $show_controls ? '' : ' no-controls';

        $toolbar_block = '';
        $meta_block = '';
        $controls_script = '';
        if ($show_controls) {
            $toolbar_block = '<div class="toolbar">'
                . '<div class="left">'
                . '<button class="btn" type="button" data-action="copy-hash">' . esc_html__('Copy verification ID', 'sikshya') . '</button>'
                . '<button class="btn" type="button" data-action="copy-link">' . esc_html__('Copy link', 'sikshya') . '</button>'
                . '<button class="btn" type="button" data-action="share-x">' . esc_html__('Share on X', 'sikshya') . '</button>'
                . '<button class="btn" type="button" data-action="share-fb">' . esc_html__('Share on Facebook', 'sikshya') . '</button>'
                . '<button class="btn" type="button" data-action="share-ln">' . esc_html__('Share on LinkedIn', 'sikshya') . '</button>'
                . '</div>'
                . '<div class="right">'
                . '<button class="btn" type="button" data-action="png">' . esc_html__('Download PNG', 'sikshya') . '</button>'
                . '<button class="btn" type="button" data-action="pdf">' . esc_html__('Download PDF', 'sikshya') . '</button>'
                . '<button class="btn primary" type="button" data-action="print">' . esc_html__('Print', 'sikshya') . '</button>'
                . '</div>'
                . '</div>';

            $meta_block = '<div class="meta" id="sikshya-cert-meta">'
                . '<div class="row"><div><code>' . esc_html($meta_line !== '' ? $meta_line : '') . '</code></div></div>'
                . '<div class="small">' . esc_html__('Tip: PNG/PDF are generated in your browser.', 'sikshya') . '</div>'
                . '</div>';

            $controls_script = '<script>(function(){'
            . 'var stage=document.querySelector(".stage");if(!stage){return;}'
            . 'var shareUrl=stage.getAttribute("data-share-url")||window.location.href;'
            . 'var hash=stage.getAttribute("data-hash")||"";'
            . 'var exportW=parseFloat(stage.getAttribute("data-export-pt-w")||"0");'
            . 'var exportH=parseFloat(stage.getAttribute("data-export-pt-h")||"0");'
            . 'function toast(msg){try{var m=document.getElementById("sikshya-cert-meta");if(m){m.querySelector(".small").textContent=msg;}}catch(e){}}'
            . 'function flashButton(action,label,ok){'
            . 'try{var btn=document.querySelector("button[data-action=\\""+action+"\\"]");if(!btn){return;}'
            . 'var prev=btn.getAttribute("data-prev-label")||btn.textContent||"";'
            . 'if(!btn.getAttribute("data-prev-label")){btn.setAttribute("data-prev-label",prev);} '
            . 'btn.textContent=label;'
            . 'if(ok){btn.classList.add("success");}else{btn.classList.remove("success");}'
            . 'try{window.clearTimeout(btn._sikshyaT||0);}catch(e){}'
            . 'btn._sikshyaT=window.setTimeout(function(){btn.textContent=prev;btn.classList.remove("success");},1200);'
            . '}catch(e){}'
            . '}'
            . 'async function copyText(t){try{if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(t);return true;}}catch(e){}'
            . 'try{var ta=document.createElement("textarea");ta.value=t;ta.style.position="fixed";ta.style.left="-9999px";document.body.appendChild(ta);ta.focus();ta.select();var ok=document.execCommand("copy");document.body.removeChild(ta);return ok;}catch(e){return false;}}'
            . 'function shareLinks(url){var u=encodeURIComponent(url);return{'
            . 'x:"https://twitter.com/intent/tweet?url="+u,'
            . 'fb:"https://www.facebook.com/sharer/sharer.php?u="+u,'
            . 'ln:"https://www.linkedin.com/sharing/share-offsite/?url="+u'
            . '};}'
            . 'function openShare(url,which){var l=shareLinks(url);var target=(which==="x"?l.x:(which==="fb"?l.fb:l.ln));'
            . 'var w=window.open(target,"_blank","noopener,noreferrer");if(!w){window.location.href=target;}}'
            . 'async function ensureHtml2Canvas(){if(window.html2canvas){return window.html2canvas;}'
            . 'return await new Promise(function(resolve,reject){var s=document.createElement("script");'
            . 's.src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js";s.async=true;'
            . 's.onload=function(){resolve(window.html2canvas);};s.onerror=function(){reject(new Error("html2canvas failed to load"));};document.head.appendChild(s);});}'
            . 'async function ensureHtmlToImage(){if(window.htmlToImage&&window.htmlToImage.toPng){return window.htmlToImage;}'
            . 'return await new Promise(function(resolve,reject){var s=document.createElement("script");'
            . 's.src="https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.min.js";s.async=true;'
            . 's.onload=function(){resolve(window.htmlToImage);};s.onerror=function(){reject(new Error("html-to-image failed to load"));};document.head.appendChild(s);});}'
            . 'async function ensureJsPdf(){if(window.jspdf&&window.jspdf.jsPDF){return window.jspdf.jsPDF;}'
            . 'return await new Promise(function(resolve,reject){var s=document.createElement("script");'
            . 's.src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js";s.async=true;'
            . 's.onload=function(){resolve(window.jspdf&&window.jspdf.jsPDF?window.jspdf.jsPDF:null);};s.onerror=function(){reject(new Error("jsPDF failed to load"));};document.head.appendChild(s);});}'
            . 'function capturePixelRatio(el){var rect=el.getBoundingClientRect();var tw=Math.max(1,Math.round(rect.width));var th=Math.max(1,Math.round(rect.height));var pr=2;'
            . 'if(exportW>0&&exportH>0&&tw>0&&th>0){var targetW=Math.round(exportW*96/72);var targetH=Math.round(exportH*96/72);pr=Math.max(2,Math.min(3,Math.min(targetW/tw,targetH/th)));}'
            . 'return {tw:tw,th:th,pr:pr};}'
            . 'async function rasterToJpegDataUrl(pngDataUrl,q){var img=await new Promise(function(resolve,reject){var i=new Image();i.onload=function(){resolve(i);};i.onerror=function(){reject(new Error("image load failed"));};i.src=pngDataUrl;});'
            . 'var c=document.createElement("canvas");c.width=img.naturalWidth;c.height=img.naturalHeight;var ctx=c.getContext("2d");ctx.fillStyle="#ffffff";ctx.fillRect(0,0,c.width,c.height);ctx.drawImage(img,0,0);return c.toDataURL("image/jpeg",q);}'
            . 'async function captureRasterPngDataUrl(){var el=document.getElementById("sikshya-cert-sheet");if(!el){throw new Error("missing sheet");}var m=capturePixelRatio(el);'
            . 'function shouldKeepNode(node){try{if(!node||node.nodeType!==1){return true;}'
            . 'if(node.classList&&node.classList.contains("toolbar")){return false;}'
            . 'if(node.classList&&node.classList.contains("sikshya-cert-qr")){return false;}'
            . 'if(node.tagName==="IMG"){var src=(node.getAttribute("src")||"").trim();'
            . 'if(src&&src.indexOf("data:")!==0){'
            . 'try{var u=new URL(src,window.location.href);if(u.origin!==window.location.origin){return false;}}catch(e){return false;}}}'
            . '}catch(e){}return true;}'
            . 'try{var hti=await ensureHtmlToImage();return await hti.toPng(el,{pixelRatio:m.pr,cacheBust:true,backgroundColor:"#ffffff",width:m.tw,height:m.th,style:{transform:"none"},'
            . 'filter:function(node){return shouldKeepNode(node);}});}catch(e){}'
            . 'var h2c=await ensureHtml2Canvas();var canvas=await h2c(el,{backgroundColor:"#ffffff",scale:m.pr,useCORS:true,allowTaint:false,foreignObjectRendering:true,logging:false,width:m.tw,height:m.th,'
            . 'ignoreElements:function(node){return !shouldKeepNode(node);}});'
            . 'return canvas.toDataURL("image/png");}'
            . 'async function downloadPng(){try{toast("Preparing PNG…");var dataUrl=await captureRasterPngDataUrl();'
            . 'var a=document.createElement("a");a.download=("certificate"+(hash?("-"+hash.slice(0,10)):"")+".png");a.href=dataUrl;'
            . 'document.body.appendChild(a);a.click();document.body.removeChild(a);toast("PNG downloaded.");'
            . '}catch(e){toast("Could not generate PNG. Try Print → Save as PDF instead.");}}'
            . 'async function downloadPdf(){try{toast("Preparing PDF…");var J=await ensureJsPdf();if(!J){throw new Error("jsPDF missing");}'
            . 'var png=await captureRasterPngDataUrl();'
            . 'var jpeg=await rasterToJpegDataUrl(png,0.93);'
            . 'var img=await new Promise(function(resolve,reject){var i=new Image();i.onload=function(){resolve(i);};i.onerror=function(){reject(new Error("image load failed"));};i.src=jpeg;});'
            . 'var w=exportW>0?exportW:img.naturalWidth*72/96,h=exportH>0?exportH:img.naturalHeight*72/96;var orientation=w>=h?"l":"p";'
            . 'var pdf=new J({orientation:orientation,unit:"pt",format:[w,h]});pdf.addImage(jpeg,"JPEG",0,0,w,h);'
            . 'pdf.save("certificate"+(hash?("-"+hash.slice(0,10)):"")+".pdf");toast("PDF downloaded.");'
            . '}catch(e){toast("Could not generate PDF. Use Print instead.");}}'
            . 'document.addEventListener("click",function(ev){var t=ev.target;if(!(t&&t.getAttribute)){return;}'
            . 'var a=t.getAttribute("data-action");if(!a){return;}ev.preventDefault();'
            . 'if(a==="print"){window.print();return;}'
            . 'if(a==="pdf"){downloadPdf();return;}'
            . 'if(a==="png"){downloadPng();return;}'
            . 'if(a==="copy-hash"){if(!hash){toast("No verification ID found.");flashButton("copy-hash","No ID",false);return;}copyText(hash).then(function(ok){toast(ok?"Copied verification ID.":"Could not copy verification ID.");flashButton("copy-hash",ok?"Copied":"Copy failed",ok);});return;}'
            . 'if(a==="copy-link"){copyText(shareUrl).then(function(ok){toast(ok?"Copied link.":"Could not copy link.");flashButton("copy-link",ok?"Copied":"Copy failed",ok);});return;}'
            . 'if(a==="share-x"){openShare(shareUrl,"x");return;}'
            . 'if(a==="share-fb"){openShare(shareUrl,"fb");return;}'
            . 'if(a==="share-ln"){openShare(shareUrl,"ln");return;}'
            . '});})();</script>';
        }

        return '<!DOCTYPE html><html lang="'
            . esc_attr(substr(get_locale(), 0, 2))
            . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $t . '</title>'
            . '<style>'
            . '@page{size:' . $w . ' ' . $h . ';margin:0}'
            . 'html,body{height:100%}'
            . 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:18px;background:#fff;color:#0f172a}'
            . '.stage{min-height:100%;display:flex;flex-direction:column;gap:12px;align-items:center;justify-content:flex-start}'
            . '.sheet{width:min(1200px,calc(100vw - 36px));max-height:calc(100vh - 120px);aspect-ratio:' . $ar . ';background:transparent;border:none;border-radius:0;padding:0;box-shadow:none;overflow:hidden;position:relative}'
            . '.sheet:before{content:"";position:absolute;inset:0;border:1px solid #e2e8f0;pointer-events:none}'
            . '.toolbar{width:min(1200px,calc(100vw - 64px));display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between}'
            . '.toolbar .left,.toolbar .right{display:flex;flex-wrap:wrap;gap:8px;align-items:center}'
            . '.btn{appearance:none;border:1px solid #e2e8f0;background:#fff;color:#0f172a;border-radius:10px;padding:8px 10px;font-size:13px;line-height:1;cursor:pointer}'
            . '.btn:hover{background:#f8fafc}'
            . '.btn:active{transform:translateY(1px)}'
            . '.btn.success{border-color:#22c55e;background:#dcfce7;color:#166534}'
            . '.btn.primary{background:#0f172a;color:#fff;border-color:#0f172a}'
            . '.btn.primary:hover{background:#111827}'
            . '.meta{width:min(1200px,calc(100vw - 64px));background:rgba(255,255,255,.9);border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;font-size:13px;color:#334155}'
            . '.meta code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px}'
            . '.meta .row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between}'
            . '.meta .small{font-size:12px;color:#64748b;margin-top:8px}'
            . '.no-controls .toolbar,.no-controls .meta{display:none}'
            . '@media print{body{padding:0;background:#fff}.toolbar,.meta{display:none}.stage{min-height:auto}.sheet{width:' . $w . ';height:' . $h . ';aspect-ratio:auto;border:none;border-radius:0;box-shadow:none;padding:0}}'
            . '</style>'
            . '</head><body>'
            . '<div class="stage' . $controls_class . '" data-share-url="' . esc_attr($share_url) . '" data-hash="' . esc_attr($hash_clean) . '" data-export-pt-w="' . $ew . '" data-export-pt-h="' . $eh . '">'
            . $toolbar_block
            . '<div class="sheet" id="sikshya-cert-sheet">' . $inner . '</div>'
            . $meta_block
            . '</div>'
            . $controls_script
            . '</body></html>';
    }
}

