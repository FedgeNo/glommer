<?php

declare(strict_types=1);

/**
 * The standing note at the top of a conversation with someone on another
 * server.
 *
 * It is here because these messages are genuinely less private than the rest,
 * and the difference is invisible otherwise - the thread looks identical.
 *
 * A message between two members here is stored on one server. A federated one
 * is stored on two, in the clear on both, because ActivityPub has no encryption
 * and no mechanism to add any. Whoever runs the other server can read it. That
 * is not a defect in this implementation; it is what federated messaging is, and
 * the only honest thing to do is say so where the decision is being made.
 */
class FederatedThreadNotice extends Notice
{
    public ?string $class = 'FederatedThreadNotice';

    public function __construct(string $handle)
    {
        parent::__construct(
            $handle . ' is on another server. Messages in this conversation are stored on that server as well as this one, and its administrator can read them. Keep anything sensitive to conversations on this site.'
        );
    }
}
