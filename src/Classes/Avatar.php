<?php

declare(strict_types=1);

/**
 * A user's avatar, in one of two concrete forms chosen by whether they've
 * uploaded an image: AvatarImage (an <img>) or the CSS/text fallback
 * AvatarInitial (a hue-from-userId circle holding the first letter of their
 * name, with no image request). Size is not the avatar's concern - it's the
 * same class everywhere, and CSS sizes it by container (e.g. a bigger avatar on
 * a profile, a smaller one in a notification). Build with Avatar::forUser() or,
 * from raw fields, Avatar::create().
 */
abstract class Avatar extends HTMLObject
{
    public ?string $name = null;
    public int $userId = 0;

    public static function create(bool $has_image, ?string $image_url, ?string $name, int $user_id): self
    {
        if ($has_image && $image_url !== null) {
            $avatar = new AvatarImage();
            $avatar -> imageURL = $image_url;
        } else {
            $avatar = new AvatarInitial();
        }

        $avatar -> name = $name;
        $avatar -> userId = $user_id;

        return $avatar;
    }

    public static function forUser(?User $user): self
    {
        if ($user === null) {
            return self::create(false, null, null, 0);
        }

        return self::create(
            (bool) $user -> hasAvatar,
            $user -> avatarURL(),
            $user -> displayName ?? $user -> username,
            (int) $user -> userId
        );
    }
}
