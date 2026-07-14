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
            $unblock_button = new Button();
            $unblock_button -> type = 'button';
            $unblock_button -> class = 'Btn UnblockUserButton ms-auto';
            $unblock_button -> attributes['data-user-id'] = (string) $this -> userId;
            $unblock_button -> contents[] = 'Unblock';
            $element -> appendChild($unblock_button -> toDOM());

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
        $actions -> class = 'd-flex flex-column gap-2 ms-auto';

        foreach ($this -> beforeActions() as $item) {
            $actions -> addContent($item);
        }

        if ($friendship === null || $sent_by_viewer) {
            $friend_button = new Button();
            $friend_button -> type = 'button';
            $friend_button -> class = 'Btn FriendRequestButton';
            $friend_button -> attributes['data-user-id'] = (string) $this -> userId;
            $friend_button -> attributes['data-sent'] = $sent_by_viewer ? '1' : '0';
            $friend_button -> contents[] = $sent_by_viewer ? 'Cancel' : 'Add Friend';
            $actions -> addContent($friend_button);
        }

        $message_link = new Anchor(ServerURL::absolute('/messages/' . $this -> username), 'Message');
        $message_link -> class = 'Btn';
        $actions -> addContent($message_link);

        $friends_link = new Anchor(ServerURL::absolute('/users/' . $this -> username . '/friends'), 'Friends');
        $friends_link -> class = 'Btn';
        $actions -> addContent($friends_link);

        if ($friendship !== null && $friendship -> status === 'accepted') {
            $actions -> addContent(new RemoveFriendButton($this -> userId));
        }

        foreach ($this -> afterMessageActions() as $item) {
            $actions -> addContent($item);
        }

        // Only the primary admin can promote/demote moderators - not mods
        // themselves, to avoid a mod-promotes-mod escalation chain.
        if ($viewer_id === 1) {
            $actions -> addContent(new ModButton($this -> userId, (bool) $this -> isMod));
        }

        $block_button = new Button();
        $block_button -> type = 'button';
        $block_button -> class = 'Btn BlockUserButton';
        $block_button -> attributes['data-user-id'] = (string) $this -> userId;
        $block_button -> contents[] = 'Block';
        $actions -> addContent($block_button);

        // The admin (userId 1) can be neither banned (api/ban.php rejects it)
        // nor reported (api/report.php rejects it - nobody could act on the
        // report anyway), so their card gets neither button.
        if ($this -> userId !== 1) {
            $actions -> addContent(
                Auth::canModerate() ? new BanButton($this -> userId, 'Ban') : new ReportButton('user', $this -> userId)
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
     * (other-user.js OtherUser.fromData). Everything that decides which action
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
            'username' => $user -> username,
            'displayName' => $user -> displayName,
            'image' => $user -> avatarURL(),
            'createdAt' => $user -> createdAt,
            'blockedByViewer' => $blocked_by_viewer,
            'blockedByOther' => $blocked_by_other,
            'friendshipStatus' => $friendship ?-> status,
            'friendshipSentByViewer' => $friendship !== null ? ((int) $friendship -> requesterId === (int) $viewer -> userId) : null,
            'isMod' => (bool) $user -> isMod,
        ];
    }
}
