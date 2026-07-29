import { Linkifier } from '/scripts/Linkifier.js';
import { DeltaRenderer } from '/scripts/DeltaRenderer.js';

/** Mirrors UserBio.php: a user's plain-text bio, linkified the same way the
 * server renders it (the shared Linkifier), so a saved bio round-trips
 * identically. Newlines are preserved by the .UserBio white-space rule. */
export class UserBio {
    constructor(user) {
        this.description = user.description || '';
    }

    toElement() {
        const bio = document.createElement('div');
        bio.className = 'UserBio';

        for (const segment of Linkifier.tokenize(this.description)) {
            const inner = document.createTextNode(segment.text);

            if (segment.type === 'url') {
                bio.appendWithSpace(DeltaRenderer.linkedNode(segment.text, inner));
            } else if (segment.type === 'hashtag') {
                bio.appendWithSpace(DeltaRenderer.hashtagNode(segment.tag, inner));
            } else if (segment.type === 'mention') {
                bio.appendWithSpace(DeltaRenderer.mentionNode(segment.username, inner));
            } else {
                bio.appendWithSpace(inner);
            }
        }

        return bio;
    }
}
