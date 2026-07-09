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

        $block_button = new Button();
        $block_button -> type = 'button';
        $block_button -> class = 'Btn BlockUserButton';
        $block_button -> attributes['data-user-id'] = (string) $this -> userId;
        $block_button -> contents[] = 'Block';
        $actions -> addContents($block_button);

        $report_button = new Button();
        $report_button -> type = 'button';
        $report_button -> class = 'Btn ReportButton';
        $report_button -> attributes['data-target-type'] = 'user';
        $report_button -> attributes['data-target-id'] = (string) $this -> userId;
        $report_button -> contents[] = 'Report';
        $actions -> addContents($report_button);

        $element -> appendChild($actions -> toDOM());

        return $element;
    }
}
