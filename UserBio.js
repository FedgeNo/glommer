/** Mirrors UserBio.php: a user's plain-text bio, linkified the same way the
 * server renders it (delta.js's shared linkifier), so a saved bio round-trips
 * identically. Newlines are preserved by the .UserBio white-space rule. */
class UserBio {
    constructor(user) {
        this.description = user.description || '';
    }

    toElement() {
        const bio = document.createElement('div');
        bio.className = 'UserBio';

        for (const segment of linkify_tokenize(this.description)) {
            const inner = document.createTextNode(segment.text);

            if (segment.type === 'url') {
                bio.appendChild(linked_node(segment.text, inner));
            } else if (segment.type === 'hashtag') {
                bio.appendChild(hashtag_node(segment.tag, inner));
            } else if (segment.type === 'mention') {
                bio.appendChild(mention_node(segment.username, inner));
            } else {
                bio.appendChild(inner);
            }
        }

        return bio;
    }
}
