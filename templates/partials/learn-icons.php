<?php
/**
 * Shared SVG icon library for the learn-shell templates (single-lesson + learn hub).
 *
 * Defines `sikshya_learn_icon($name)` once, guarded by `function_exists` so the
 * partial is idempotent and safe to `require` from multiple shells.
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('sikshya_learn_icon')) {
    /**
     * Return inline SVG markup for a named icon used in the learn shell.
     */
    function sikshya_learn_icon(string $name): string
    {
        switch ($name) {
            case 'menu':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            case 'x':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            case 'exit-learn':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4m7 14l5-5-5-5m5 5H9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'chevron-up':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 15l6-6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'chevron-right':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'chevron-left':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'book':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'clipboard':
                // Quiz — circle silhouette with a "?" inside.
                // KEEP IN SYNC with sikshya_curriculum_outline_row_type_icon_html() quiz + icons.json curriculumQuiz.
                return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M9.4 9.4a2.6 2.6 0 1 1 3.85 2.27c-.78.4-1.25 1.05-1.25 1.93v.4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="17" r="0.9" fill="currentColor"/></svg>';
            case 'doc':
                // Text lesson — page with folded corner + 2 text lines.
                // KEEP IN SYNC with sikshya_curriculum_outline_row_type_icon_html() text-default + icons.json curriculumLessonText.
                return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M14 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8l-5-5z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M14 3v3.5A1.5 1.5 0 0 0 15.5 8H19" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M8.5 13h7M8.5 16.5h4.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
            case 'audio':
                // Audio lesson — headphones arc. KEEP IN SYNC with icons.json curriculumLessonAudio.
                return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M4 13a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><path d="M4 13v4a2 2 0 0 0 2 2h1v-7H5a1 1 0 0 0-1 1zM20 13v4a2 2 0 0 1-2 2h-1v-7h2a1 1 0 0 1 1 1z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/></svg>';
            case 'folder':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
            case 'assignment':
                // Assignment — square page with a bold check inside.
                // KEEP IN SYNC with sikshya_curriculum_outline_row_type_icon_html() assignment + icons.json curriculumAssignment.
                return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M5 4.5A1.5 1.5 0 0 1 6.5 3h11A1.5 1.5 0 0 1 19 4.5v15A1.5 1.5 0 0 1 17.5 21h-11A1.5 1.5 0 0 1 5 19.5v-15z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M8 12l3 3 5-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'chevron-down':
                return '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'play-video':
                // Video lesson — rounded screen with centred filled play triangle.
                // KEEP IN SYNC with sikshya_curriculum_outline_row_type_icon_html() video + icons.json curriculumLessonVideo.
                return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><rect x="3" y="5.5" width="18" height="13" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M10 9.5v5l5-2.5-5-2.5z" fill="currentColor"/></svg>';
            case 'live':
                // Live class — broadcast signal radiating from a central dot.
                // KEEP IN SYNC with sikshya_curriculum_outline_row_type_icon_html() live + icons.json curriculumLessonLive.
                return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="2" fill="currentColor"/><path d="M8 8a5 5 0 0 0 0 8M16 8a5 5 0 0 1 0 8" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><path d="M5 5a9 9 0 0 0 0 14M19 5a9 9 0 0 1 0 14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>';
            case 'layers':
                // SCORM lesson — stacked layers. KEEP IN SYNC with icons.json curriculumLessonScorm.
                return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M12 3l9 5-9 5-9-5 9-5z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M3 13l9 5 9-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 17l9 5 9-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'puzzle':
                // H5P lesson — puzzle piece. KEEP IN SYNC with icons.json curriculumLessonH5p.
                return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M8.5 4a2.5 2.5 0 1 1 5 0v1h2a2 2 0 0 1 2 2v2h-1a2.5 2.5 0 1 0 0 5h1v2a2 2 0 0 1-2 2h-2v-1a2.5 2.5 0 1 0-5 0v1h-2a2 2 0 0 1-2-2v-2h1a2.5 2.5 0 1 0 0-5H4.5V7a2 2 0 0 1 2-2h2V4z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'check':
                return '<svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true" focusable="false"><path d="M5.5 12.5l2.5 2.5 6.5-8" fill="none" stroke="#ffffff" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'lock':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M7 11V8a5 5 0 0110 0v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6.5 11h11A2.5 2.5 0 0120 13.5v6A2.5 2.5 0 0117.5 22h-11A2.5 2.5 0 014 19.5v-6A2.5 2.5 0 016.5 11z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
            case 'focus':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M4 9V5a1 1 0 011-1h4M20 9V5a1 1 0 00-1-1h-4M4 15v4a1 1 0 001 1h4M20 15v4a1 1 0 01-1 1h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'focus-exit':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M9 4H5a1 1 0 00-1 1v4M15 4h4a1 1 0 011 1v4M9 20H5a1 1 0 01-1-1v-4M15 20h4a1 1 0 001-1v-4M9 9l-5-5M15 9l5-5M9 15l-5 5M15 15l5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'more':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="14" y="3" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="3" y="14" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="14" y="14" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            case 'note':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M5 4h10l4 4v12a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1zM15 4v4h4M8 13h8M8 17h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            case 'keyboard':
                return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><rect x="2" y="6" width="20" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            default:
                return '';
        }
    }
}
