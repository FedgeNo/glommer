<?php

declare(strict_types=1);

/**
 * Converted search and authentication furniture that can be rendered without
 * a database, merged into NoEnglishInClassesTest::subjects().
 */

return [
    AuthDivider::class => static fn (): HTMLObject => new AuthDivider(),
    UserSearchBox::class => static fn (): HTMLObject => new UserSearchBox(),
    PostSearchBox::class => static fn (): HTMLObject => new PostSearchBox(),
    FriendSearchBox::class => static fn (): HTMLObject => new FriendSearchBox(),
];
