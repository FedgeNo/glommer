import { OtherUser } from '/scripts/OtherUser.js';
import { Strings } from '/scripts/Strings.js';

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
        accept.textContent = Strings.for('FriendRequestAcceptButton', { name: 'Accept' }).name;

        const deny = document.createElement('button');
        deny.type = 'button';
        deny.className = 'Button FriendRequestDenyButton';
        deny.dataset.friendshipId = this.friendshipId;
        deny.textContent = Strings.for('FriendRequestDenyButton', { name: 'Deny' }).name;

        return [accept, deny];
    }

    toElement() {
        const div = super.toElement();
        div.classList.add('ReceivedFriendRequest');
        return div;
    }
}
