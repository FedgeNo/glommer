<?php

declare(strict_types=1);

/**
 * The shared form identity. Form itself is an HTML primitive and never names
 * itself, so FormForm sits between it and the real forms to inject ".Form" into
 * the identity chain - the same job ButtonButton does for buttons. The look that
 * every form shares hangs off that one class in CSS, rather than each form
 * composing it into its own class attribute.
 */
class FormFormTest extends TestCase
{
    /** The chain a class would render, without constructing it. */
    private function renderedClassFor(string $class): ?string
    {
        $instance = (new \ReflectionClass($class)) -> newInstanceWithoutConstructor();
        (new \ReflectionMethod(HTMLObject::class, 'deriveClassName')) -> invoke($instance);

        return $instance -> class;
    }

    public function testAFormIsNamedAfterBothTheSharedIdentityAndItself(): void
    {
        $this -> assertSame('Form LoginFormFake', $this -> renderedClassFor(LoginFormFake::class));
    }

    public function testAFormReachesTheSharedIdentityThroughAnAbstractMiddleClass(): void
    {
        // An abstract middle class must not name itself either, or the chain
        // stops there and the shared identity never reaches the concrete form.
        $this -> assertSame('Form MiddleFormFake ConcreteFormFake', $this -> renderedClassFor(ConcreteFormFake::class));
    }

    public function testAPlainFormPrimitiveNamesNothing(): void
    {
        $this -> assertNull($this -> renderedClassFor(Form::class));
    }

    /**
     * The point of the shared identity is that no form has to compose the card
     * look itself - one that starts doing so again would be styled twice, and by
     * two different rules.
     */
    public function testNoSharedFormComposesTheCardLookItself(): void
    {
        $offenders = [];

        foreach (glob(__DIR__ . '/../src/classes/*.php') ?: [] as $file) {
            $class = basename($file, '.php');

            if (!class_exists($class) || !is_subclass_of($class, FormForm::class)) {
                continue;
            }

            $mixins = (new \ReflectionClass($class)) -> getDefaultProperties()['mixins'] ?? [];

            if (in_array('Card', $mixins, true)) {
                $offenders[] = $class;
            }
        }

        $this -> assertSame([], $offenders, 'these forms compose Card on top of the shared .Form look');
    }
}

class LoginFormFake extends FormForm
{
}

abstract class MiddleFormFake extends FormForm
{
}

class ConcreteFormFake extends MiddleFormFake
{
}
