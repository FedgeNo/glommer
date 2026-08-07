export class Linkifier {
    static MAX_TAG_LENGTH = 50;
    static MAX_MENTION_LENGTH = 255;
    static URL_TRAILING_TRIM = ".,!?;:)";
    // Kept identical to Linkifier.php's TAG_CHARS - see the reasoning there
    // for why a #tag is stated as the ASCII it may not contain rather than as
    // the characters it may.
    static TAG_CHARS = "[^\\x00-\\x2F\\x3A-\\x40\\x5B-\\x5E\\x60\\x7B-\\x7F]";
    static SCAN = "https?://[A-Za-z0-9._~:/?#\\[\\]@!$&'()*+,;=%-]+|(?<!" + Linkifier.TAG_CHARS + ")(?<!#)#" + Linkifier.TAG_CHARS + "+|(?<![A-Za-z0-9_@])@[A-Za-z0-9_]+(?:@[A-Za-z0-9-]+(?:\\.[A-Za-z0-9-]+)+)?";
    static LOOKS_URL = "https?://|www\\.[A-Za-z0-9-]|[A-Za-z0-9-]+\\.[A-Za-z][A-Za-z]+/";
    static AUTHORITY = "^(?:[A-Za-z][A-Za-z0-9+.-]*:)?//([^/?#]*)";

    /** Pass 1's anti-phishing detector: does this text read as a URL to a human? */
    static textLooksURL(text) {
        return new RegExp(Linkifier.LOOKS_URL).test(text);
    }

    static linkHost(url) {
        const stripped = url.replace(/[\u0000-\u0020]+/g, '');
        const match = new RegExp(Linkifier.AUTHORITY).exec(stripped);

        if (match === null) {
            return null;
        }

        let authority = match[1];
        const at = authority.lastIndexOf('@');

        if (at !== -1) {
            authority = authority.slice(at + 1);
        }

        const colon = authority.indexOf(':');

        if (colon !== -1) {
            authority = authority.slice(0, colon);
        }

        return authority.toLowerCase();
    }

    static tokenize(text) {
        const segments = [];
        let cursor = 0;
        const re = new RegExp(Linkifier.SCAN, 'g');
        let match;

        while ((match = re.exec(text)) !== null) {
            const matched = match[0];
            const offset = match.index;
            const classified = Linkifier.#classify(matched);

            if (classified === null) {
                continue;
            }

            if (offset > cursor) {
                segments.push({ type: 'text', text: text.slice(cursor, offset) });
            }

            segments.push(classified.segment);
            cursor = offset + matched.length;

            if (classified.trailing !== '') {
                segments.push({ type: 'text', text: classified.trailing });
            }
        }

        if (cursor < text.length) {
            segments.push({ type: 'text', text: text.slice(cursor) });
        }

        return Linkifier.#mergeText(segments);
    }

    /**
     * The mirror of Linkifier::isTagSlug(). Counted in code points rather
     * than in string length, which counts UTF-16 units and would make the cap
     * mean something different here than it does on the server.
     */
    static isTagSlug(tag) {
        if (tag === '' || [...tag].length > Linkifier.MAX_TAG_LENGTH) {
            return false;
        }

        if (!new RegExp('^' + Linkifier.TAG_CHARS + '+$').test(tag)) {
            return false;
        }

        return [...tag].some((character) => !'0123456789_'.includes(character));
    }

    static #classify(matched) {
        if (matched[0] === '#') {
            const tag = matched.slice(1);

            if (!Linkifier.isTagSlug(tag)) {
                return null;
            }

            return { segment: { type: 'hashtag', text: matched, tag: tag.toLowerCase() }, trailing: '' };
        }

        if (matched[0] === '@') {
            const username = matched.slice(1);

            if (username === '' || username.length > Linkifier.MAX_MENTION_LENGTH) {
                return null;
            }

            // Lowercased for both display and the link - unlike a hashtag, a
            // username is always stored lowercase, so there's no legitimate
            // original casing to keep. Mirrors Linkifier::classify() exactly.
            const lowercased = username.toLowerCase();

            return { segment: { type: 'mention', text: '@' + lowercased, username: lowercased }, trailing: '' };
        }

        let end = matched.length;

        while (end > 0 && Linkifier.URL_TRAILING_TRIM.includes(matched[end - 1])) {
            end--;
        }

        const url = matched.slice(0, end);
        const trailing = matched.slice(end);

        if (!/^https?:\/\/./.test(url)) {
            return null;
        }

        return { segment: { type: 'url', text: url }, trailing };
    }

    static #mergeText(segments) {
        const merged = [];

        segments.forEach((segment) => {
            const last = merged.length - 1;

            if (segment.type === 'text' && last >= 0 && merged[last].type === 'text') {
                merged[last].text += segment.text;
                return;
            }

            merged.push(segment);
        });

        return merged;
    }
}

