export type TextSegment =
    | { type: 'text'; text: string }
    | { type: 'link'; text: string; href: string };

// The lookbehind keeps "www." inside another word or address from becoming a link.
const urlPattern = /(?<![\w@./-])(?:https?:\/\/|www\.)[^\s<>"]+/gi;

const trailingPunctuation = new Set(['.', ',', '!', '?', ':', ';', "'"]);

function countOf(text: string, char: string): number {
    return text.split(char).length - 1;
}

/**
 * Cut sentence punctuation off the end of a matched URL.
 *
 * A closing parenthesis stays only while the URL holds a matching opening one,
 * so "(see https://x.test/a)" drops it but ".../Foo_(bar)" keeps it.
 */
function trimUrl(candidate: string): string {
    let url = candidate;

    while (url.length > 0) {
        const last = url[url.length - 1];
        const isUnbalancedParenthesis =
            last === ')' && countOf(url, ')') > countOf(url, '(');

        if (!trailingPunctuation.has(last) && !isUnbalancedParenthesis) {
            break;
        }

        url = url.slice(0, -1);
    }

    return url;
}

/**
 * Split free text into plain-text and link segments.
 *
 * Only http(s):// and www. URLs become links; every other scheme such as
 * javascript: stays text, so the result is safe to bind as an href.
 */
export function linkify(text: string): TextSegment[] {
    const segments: TextSegment[] = [];
    let cursor = 0;

    for (const match of text.matchAll(urlPattern)) {
        const url = trimUrl(match[0]);
        const start = match.index;
        const isBareHost = /^www\.$/i.test(url);

        if (isBareHost) {
            continue;
        }

        if (start > cursor) {
            segments.push({ type: 'text', text: text.slice(cursor, start) });
        }

        segments.push({
            type: 'link',
            text: url,
            href: /^www\./i.test(url) ? `https://${url}` : url,
        });
        cursor = start + url.length;
    }

    if (cursor < text.length) {
        segments.push({ type: 'text', text: text.slice(cursor) });
    }

    return segments;
}
