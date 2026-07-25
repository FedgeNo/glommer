/** Mirrors Avatar.php: an <img> when the user has one, otherwise a pure
 * CSS/text fallback circle in a color derived from their userId, showing the
 * first letter of their name. */
class Avatar {
    static forUser(user) {
        if (!user) {
            return Avatar.create(false, null, null, 0);
        }

        return Avatar.create(Boolean(user.image), user.image, user.title || user.slug, user.userId);
    }

    static create(has_image, image_url, name, user_id) {
        if (has_image && image_url) {
            const image = document.createElement('img');
            image.className = 'Avatar';
            image.src = image_url;
            image.alt = (name || '') + '\'s avatar';
            return image;
        }

        const fallback = document.createElement('div');
        fallback.className = 'Avatar AvatarInitial';
        fallback.setAttribute('aria-hidden', 'true');
        fallback.style.setProperty('--avatar-hue', ((Number(user_id) * 137) % 360) + 'deg');

        // Array.from splits on code points, not UTF-16 units - .charAt(0) on a
        // name starting with an emoji or other astral character would produce a
        // lone surrogate half instead of the character.
        const first_char = Array.from(name || '')[0];
        fallback.textContent = first_char ? first_char.toUpperCase() : '?';

        return fallback;
    }
}
