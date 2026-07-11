<?php

declare(strict_types=1);

class Friend extends OtherUser
{
    protected function afterMessageActions(): array
    {
        return [new RemoveFriendButton($this -> userId)];
    }
}
