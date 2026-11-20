import { describe, expect, it } from 'vitest';
import { linkify } from './linkify';

describe('linkify', () => {
    it('links a plain URL', () => {
        expect(linkify('See https://drive.google.com/x/1 now')).toEqual([
            { type: 'text', text: 'See ' },
            {
                type: 'link',
                text: 'https://drive.google.com/x/1',
                href: 'https://drive.google.com/x/1',
            },
            { type: 'text', text: ' now' },
        ]);
    });

    it('keeps trailing punctuation outside the link', () => {
        const segments = linkify('Go to https://example.com/a?b=1!');

        expect(segments[1]).toMatchObject({
            href: 'https://example.com/a?b=1',
        });
        expect(segments[2]).toEqual({ type: 'text', text: '!' });
    });

    it('drops an unbalanced closing parenthesis but keeps balanced ones', () => {
        expect(linkify('(https://example.com/a)')[1]).toMatchObject({
            href: 'https://example.com/a',
        });
        expect(
            linkify('https://en.wikipedia.org/wiki/Foo_(bar).')[0],
        ).toMatchObject({
            href: 'https://en.wikipedia.org/wiki/Foo_(bar)',
        });
    });

    it('links www. hosts as https', () => {
        expect(linkify('www.example.com')).toEqual([
            {
                type: 'link',
                text: 'www.example.com',
                href: 'https://www.example.com',
            },
        ]);
    });

    it('leaves javascript: and other schemes as text', () => {
        expect(linkify('javascript:alert(1)')).toEqual([
            { type: 'text', text: 'javascript:alert(1)' },
        ]);
    });

    it('returns a single text segment without URLs', () => {
        expect(linkify('Bring cake\nand plates')).toEqual([
            { type: 'text', text: 'Bring cake\nand plates' },
        ]);
    });
});
