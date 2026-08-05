<?php

declare(strict_types=1);

/**
 * The nav dropdown and the notifications page render the same kind of list,
 * and the infinite scroller binds to anything advertising a scroll
 * configuration - so a dropdown that inherited the page's ends up paging
 * itself, loading older notifications into a five-item panel nobody scrolled.
 */
class NotificationDropdownTest extends TestCase
{
    private function attributesOf(string $class): array
    {
        $list = (new \ReflectionClass($class)) -> newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($class, 'dataAttributes');

        return $method -> invoke($list);
    }

    public function testTheNotificationsPageListPages(): void
    {
        $this -> assertTrue(array_key_exists('data-infinite-scroll', $this -> attributesOf(NotificationList::class)));
    }

    public function testTheDropdownListDoesNot(): void
    {
        $this -> assertSame([], $this -> attributesOf(RecentNotificationList::class));
    }

    /**
     * The two are told apart by name as well - the dropdown's chain carries
     * its own identity, so a stylesheet or a script can address one without
     * catching the other.
     */
    public function testTheDropdownListIsNamedApartFromThePageList(): void
    {
        $dropdown = (new \ReflectionClass(RecentNotificationList::class)) -> newInstanceWithoutConstructor();
        (new \ReflectionMethod(HTMLObject::class, 'deriveClassName')) -> invoke($dropdown);

        $this -> assertSame('NotificationList RecentNotificationList', $dropdown -> class);
    }
}
