/**
 * @vitest-environment jsdom
 */
import { describe, expect, it } from 'vitest';
import { renderCode, renderMarkdown, renderPaste } from './paste-render';

describe('markdown rendering', () => {
    it('turns markdown into HTML', () => {
        const html = renderMarkdown('# Titel\n\nTekst met **nadruk**.');

        expect(html).toContain('<h1>Titel</h1>');
        expect(html).toContain('<strong>nadruk</strong>');
    });

    it('keeps a normal link', () => {
        const html = renderMarkdown('[site](https://pcpatrol.nl)');

        expect(html).toContain('href="https://pcpatrol.nl"');
    });
});

describe('markdown sanitising', () => {
    it('removes a script tag', () => {
        const html = renderMarkdown('Hallo <script>alert(1)</script>');

        expect(html).not.toContain('<script');
        expect(html).not.toContain('alert(1)');
    });

    it('removes an inline event handler', () => {
        const html = renderMarkdown('<img src=x onerror="alert(1)">');

        expect(html).not.toContain('onerror');
    });

    it('removes a javascript: link', () => {
        const html = renderMarkdown('[klik](javascript:alert(1))');

        expect(html).not.toContain('javascript:');
    });

    it('removes an iframe', () => {
        const html = renderMarkdown(
            '<iframe src="https://example.com"></iframe>',
        );

        expect(html).not.toContain('<iframe');
    });

    it('removes a style attribute', () => {
        const html = renderMarkdown('<p style="position:fixed">x</p>');

        expect(html).not.toContain('style=');
    });
});

describe('code rendering', () => {
    it('marks up the code with highlight classes', () => {
        const html = renderCode('function greet() { return "hallo"; }');

        expect(html).toContain('hljs-');
    });

    it('escapes code that looks like HTML instead of running it', () => {
        const html = renderCode('<script>alert(1)</script>');

        expect(html).not.toContain('<script>');
        expect(html).toContain('&lt;');
    });
});

describe('choosing a renderer', () => {
    it('leaves plain text to the caller', () => {
        expect(renderPaste('gewoon tekst', 'plaintext')).toBeNull();
    });

    it('renders markdown and code as HTML', () => {
        expect(renderPaste('**x**', 'markdown')).toContain('<strong>');
        expect(renderPaste('const x = 1;', 'code')).toContain('hljs-');
    });
});

describe('falling back when rendering breaks', () => {
    it('shows the paste as plain text instead of failing', () => {
        // A format the renderer does not know about must not throw.
        expect(
            renderPaste('tekst', 'onbekend' as unknown as 'plaintext'),
        ).toBeNull();
    });
});
