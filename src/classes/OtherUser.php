<?php

declare(strict_types=1);

class OtherUser extends User
{
    public function toDOM(): \DOMElement
    {
        $element = parent::toDOM();

        if (!Auth::check() || $this -> userId === null || Auth::id() === $this -> userId) {
            return $element;
        }

        $viewer_id = (int) Auth::id();

        if (Block::blockedBy($viewer_id, $this -> userId)) {
            $element -> appendChild(new UserUnblockButton((int) $this -> userId) -> toDOM());

            return $element;
        }

        if (Block::blockedBy($this -> userId, $viewer_id)) {
            return $element;
        }

        $friendship = Friendship::statusBetween($viewer_id, $this -> userId);

        $sent_by_viewer = $friendship !== null
            && $friendship -> status === 'pending'
            && (int) $friendship -> requesterId === $viewer_id;

        $actions = new Div();
        $actions -> mixins = ['d-flex', 'flex-column', 'gap-2', 'ms-auto'];

        foreach ($this -> beforeActions() as $item) {
            $actions -> addContent($item);
        }

        // A Fediverse account can't hold up its end of a friendship or read a
        // message here - there's no person on this side of it - so the mutual
        // actions are replaced by the one-way relationship that does mean
        // something: following.
        if ($this -> remoteActorURI !== null) {
            $following = Friendship::follows($viewer_id, (int) $this -> userId);

            $actions -> addContent(new UserFollowButton((int) $this -> userId, $following));

            // A Fediverse account can be messaged, so it needs the way in to
            // the thread the same as anyone else. Friendship stays absent -
            // that is mutual, and there is nobody on this side of it.
            $remote_message_link = new Anchor(ServerURL::absolute('/messages/' . $this -> slug), 'Message');
            $remote_message_link -> class = 'Button';
            $actions -> addContent($remote_message_link);
        } else {
            if ($friendship === null || $sent_by_viewer) {
                $actions -> addContent(new FriendRequestButton((int) $this -> userId, $sent_by_viewer));
            }

            $message_link = new Anchor(ServerURL::absolute('/messages/' . $this -> slug), 'Message');
            $message_link -> class = 'Button';
            $actions -> addContent($message_link);

            // Friendship is a thing that happens here, between two people who
            // both signed up. A Fediverse account has no friends on this site
            // to look at, so it is offered no way to look at them.
            $friends_link = new Anchor(ServerURL::absolute('/users/' . $this -> slug . '/friends'), $this -> friendsButtonLabel());
            $friends_link -> class = 'Button';
            $actions -> addContent($friends_link);
        }

        if ($friendship !== null && $friendship -> status === 'accepted') {
            $actions -> addContent(new FriendRemoveButton($this -> userId));
        }

        foreach ($this -> afterMessageActions() as $item) {
            $actions -> addContent($item);
        }

        // Only the primary admin can promote/demote moderators - not mods
        // themselves, to avoid a mod-promotes-mod escalation chain. And only
        // members: moderating happens by signing in here, which nobody on
        // another server can do.
        if ($viewer_id === 1 && $this -> remoteActorURI === null) {
            $actions -> addContent(new UserModButton($this -> userId, (bool) $this -> isMod));
        }

        $actions -> addContent(new UserBlockButton((int) $this -> userId));

        // The admin (userId 1) can be neither banned (api/ban.php rejects it)
        // nor reported (api/report.php rejects it - nobody could act on the
        // report anyway), so their card gets neither button.
        if ($this -> userId !== 1) {
            $actions -> addContent(
                Auth::canModerate() ? new UserBanButton($this -> userId, 'Ban') : new ReportButton('user', $this -> userId)
            );
        }

        $element -> appendChild($actions -> toDOM());

        return $element;
    }

    /**
     * @return HTMLObject[] extra actions a subclass wants shown grouped in
     *                       with the message/block/report trio (before it,
     *                       in the same right-aligned $actions column) -
     *                       not as separate items in the row alongside the
     *                       user header, which is where they'd otherwise
     *                       land as flex siblings of $actions
     */
    protected function beforeActions(): array
    {
        return [];
    }

    /**
     * @return HTMLObject[] extra actions a subclass wants shown grouped in
     *                       with the block/report trio, right after Message
     */
    protected function afterMessageActions(): array
    {
        return [];
    }

    /**
     * The viewer-relative JSON an OtherUser card is built from client-side
     * (OtherUser.js OtherUser.fromData). Everything that decides which action
     * buttons show - the block state each way and the friendship status - is
     * computed against $viewer, so the same person renders differently
     * depending on who's looking. $viewer is null for a logged-out visitor
     * (public friends pages), in which case there's no relationship to report.
     *
     * @return array{userId: int, username: ?string, displayName: ?string, image: ?string, createdAt: ?string, blockedByViewer: bool, blockedByOther: bool, friendshipStatus: ?string, friendshipSentByViewer: ?bool, isMod: bool}
     */
    public static function payloadFor(User $user, ?User $viewer): array
    {
        $user_id = (int) $user -> userId;

        if ($viewer === null) {
            $blocked_by_viewer = false;
            $blocked_by_other = false;
            $friendship = null;
        } else {
            $viewer_id = (int) $viewer -> userId;
            $blocked_by_viewer = Block::blockedBy($viewer_id, $user_id);
            $blocked_by_other = Block::blockedBy($user_id, $viewer_id);
            $friendship = ($blocked_by_viewer || $blocked_by_other) ? null : Friendship::statusBetween($viewer_id, $user_id);
        }

        return [
            'userId' => $user_id,
            'slug' => $user -> slug,
            'title' => $user -> title,
            'description' => $user -> description,
            'image' => $user -> avatarURL(),
            'createdAt' => $user -> createdAt,
            // Whether this is a Fediverse account, which decides the actions
            // the card offers - following rather than friendship. Without it
            // the client rebuilt every remote account as a local one and
            // offered to send a friend request no server would answer.
            'remote' => $user -> remoteActorURI !== null,
            'following' => $viewer !== null && $user -> remoteActorURI !== null
                ? Friendship::follows((int) $viewer -> userId, $user_id)
                : false,
            'blockedByViewer' => $blocked_by_viewer,
            'blockedByOther' => $blocked_by_other,
            'friendshipStatus' => $friendship ?-> status,
            'friendshipSentByViewer' => $friendship !== null ? ((int) $friendship -> requesterId === (int) $viewer -> userId) : null,
            'isMod' => (bool) $user -> isMod,
        ];
    }
}
