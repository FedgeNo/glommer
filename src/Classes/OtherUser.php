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
            $actions -> addContents($item);
        }

        if ($friendship === null || $sent_by_viewer) {
            $friend_button = new Button();
            $friend_button -> type = 'button';
            $friend_button -> class = 'Btn FriendRequestButton';
            $friend_button -> attributes['data-user-id'] = (string) $this -> userId;
            $friend_button -> attributes['data-sent'] = $sent_by_viewer ? '1' : '0';
            $friend_button -> contents[] = $sent_by_viewer ? 'Cancel' : 'Add Friend';
            $actions -> addContents($friend_button);
        }

        $message_link = new Anchor(URL::absolute('/messages/' . $this -> username), 'Message');
        $message_link -> class = 'Btn';
        $actions -> addContents($message_link);

        foreach ($this -> afterMessageActions() as $item) {
            $actions -> addContents($item);
        }

        // Only the primary admin can promote/demote moderators - not mods
        // themselves, to avoid a mod-promotes-mod escalation chain.
        if ($viewer_id === 1) {
            $actions -> addContents(new ModButton($this -> userId, (bool) $this -> isMod));
        }

        $block_button = new Button();
        $block_button -> type = 'button';
        $block_button -> class = 'Btn BlockUserButton';
        $block_button -> attributes['data-user-id'] = (string) $this -> userId;
        $block_button -> contents[] = 'Block';
        $actions -> addContents($block_button);

        $actions -> addContents(
            Auth::canModerate() ? new BanButton($this -> userId, 'Ban') : new ReportButton('user', $this -> userId)
        );

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
}
