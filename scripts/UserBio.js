import { linkify_tokenize, linked_node, hashtag_node, mention_node } from '/scripts/delta.js';

/** Mirrors UserBio.php: a user's plain-text bio, linkified the same way the
 * server renders it (delta.js's shared linkifier), so a saved bio round-trips
 * identically. Newlines are preserved by the .UserBio white-space rule. */
export class UserBio {
    constructor(user) {
        this.description = user.description || '';
    }

    toElement() {
        const bio = document.createElement('div');
        bio.className = 'UserBio';

        for (const segment of linkify_tokenize(this.description)) {
            const inner = document.createTextNode(segment.text);

            if (segment.type === 'url') {
                bio.appendWithSpace(linked_node(segment.text, inner));
            } else if (segment.type === 'hashtag') {
                bio.appendWithSpace(hashtag_node(segment.tag, inner));
            } else if (segment.type === 'mention') {
                bio.appendWithSpace(mention_node(segment.username, inner));
            } else {
                bio.appendWithSpace(inner);
            }
        }

        return bio;
    }
}
