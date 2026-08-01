<?php

declare(strict_types=1);

/**
 * A class an object gives itself while rendering has to survive the identity
 * chain being derived.
 *
 * deriveClassName() runs inside toDOM(), after the object has already decided
 * things about itself - Message marks its own sender's messages Own, Post marks
 * a permalink PostStandalone - so assigning the chain over the property drops
 * exactly the state the render just set. Silently: the markup is still valid,
 * the styling simply never applies.
 */
class RuntimeClassTest extends TestCase
{
    private static function derived(HTMLObject $object): string
    {
        $method = new \ReflectionMethod(HTMLObject::class, 'deriveClassName');
        $method -> setAccessible(true);
        $method -> invoke($object);

        return (string) $object -> class;
    }

    public function testAStateClassSetDuringRenderSurvives(): void
    {
        // Message's own-message styling - what pulls your own messages to the
        // right of the thread.
        $message = (new \ReflectionClass(Message::class)) -> newInstanceWithoutConstructor();
        $message -> class .= ' Own';

        $this -> assertSame('Message Own', self::derived($message));
    }

    public function testAPermalinkPostKeepsItsStandaloneClass(): void
    {
        $post = (new \ReflectionClass(Post::class)) -> newInstanceWithoutConstructor();
        $post -> class .= ' PostStandalone';

        $this -> assertSame('Post PostStandalone', self::derived($post));
    }

    public function testASecondIdentityComposedAtTheCallSiteSurvives(): void
    {
        // The delete-account form composes an extra identity onto its submit
        // button so the danger styling finds it.
        $button = (new \ReflectionClass(SubmitButton::class)) -> newInstanceWithoutConstructor();
        $button -> class .= ' AccountDeleteButton';

        $this -> assertSame('Button SubmitButton AccountDeleteButton', self::derived($button));
    }

    public function testAnUntouchedObjectStillGetsExactlyItsChain(): void
    {
        $post = (new \ReflectionClass(Post::class)) -> newInstanceWithoutConstructor();
        $this -> assertSame('Post', self::derived($post));

        $button = (new \ReflectionClass(SubmitButton::class)) -> newInstanceWithoutConstructor();
        $this -> assertSame('Button SubmitButton', self::derived($button));
    }

    public function testTheChainIsNotDuplicatedWhenDerivedTwice(): void
    {
        // Not a normal path - rendering twice throws - but the added-class diff
        // must not mistake the chain it just wrote for a runtime addition.
        $button = (new \ReflectionClass(SubmitButton::class)) -> newInstanceWithoutConstructor();

        self::derived($button);

        $this -> assertSame('Button SubmitButton', self::derived($button));
    }
}
