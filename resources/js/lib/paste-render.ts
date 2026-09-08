/**
 * Turn a decrypted paste into HTML for display.
 *
 * The text comes from whoever wrote the paste, so anything that ends up as
 * HTML is sanitised first. Plain text is never turned into HTML at all; the
 * caller renders it as text so the browser escapes it.
 */

import DOMPurify from 'dompurify';
import hljs from 'highlight.js/lib/common';
import { marked } from 'marked';
import type { PasteFormat } from './paste-crypto';

/** Tags that carry meaning in a paste. Everything else is stripped. */
const ALLOWED_TAGS = [
    'a',
    'blockquote',
    'br',
    'code',
    'del',
    'em',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'hr',
    'li',
    'ol',
    'p',
    'pre',
    'span',
    'strong',
    'table',
    'tbody',
    'td',
    'th',
    'thead',
    'tr',
    'ul',
];

const ALLOWED_ATTR = ['href', 'title', 'class'];

function sanitize(html: string): string {
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS,
        ALLOWED_ATTR,
        ALLOW_DATA_ATTR: false,
        // Only ever link out over http(s) or mail, never javascript: or data:.
        ALLOWED_URI_REGEXP: /^(?:https?:|mailto:)/i,
    });
}

/** Render Markdown to sanitised HTML. */
export function renderMarkdown(text: string): string {
    const html = marked.parse(text, { async: false, gfm: true, breaks: true });

    return sanitize(html);
}

/** Render source code to sanitised, syntax highlighted HTML. */
export function renderCode(text: string): string {
    return sanitize(hljs.highlightAuto(text).value);
}

/**
 * Render a paste for display, or return null when it should be shown as plain
 * text rather than HTML.
 */
export function renderPaste(text: string, format: PasteFormat): string | null {
    try {
        switch (format) {
            case 'markdown':
                return renderMarkdown(text);
            case 'code':
                return renderCode(text);
            default:
                return null;
        }
    } catch (failure) {
        // Never let a rendering problem hide the paste: fall back to showing it
        // as plain text, and leave a trace so the cause can be found.
        console.error(`Kon de paste niet als ${format} weergeven:`, failure);

        return null;
    }
}
