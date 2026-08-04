import { OtherUser } from '/scripts/OtherUser.js';

/**
 * Client twin of ReceivedFriendRequest.php: an incoming request's card, which
 * is the person's ordinary card with Accept and Deny in front of the actions.
 */
export class ReceivedFriendRequest extends OtherUser {
    beforeActions() {
        const accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'Button FriendRequestAcceptButton';
        accept.dataset.friendshipId = this.friendshipId;
        accept.textContent = 'Accept';

        const deny = document.createElement('button');
        deny.type = 'button';
        deny.className = 'Button FriendRequestDenyButton';
        deny.dataset.friendshipId = this.friendshipId;
        deny.textContent = 'Deny';

        return [accept, deny];
    }

    toElement() {
        const div = super.toElement();
        div.classList.add('ReceivedFriendRequest');
        return div;
    }
}
