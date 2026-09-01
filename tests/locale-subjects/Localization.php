<?php

declare(strict_types=1);

/**
 * How to build each converted language-choice class - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * LanguagePrompt is not here: what it offers depends on Strings::preferred(),
 * which reads the Accept-Language header of the request that is not
 * happening here, and on Strings::hasChosen(), which reads a session that is
 * not open here either - there is no request to shape into "asks for a
 * language this site speaks but hasn't chosen one yet" from a plain test.
 *
 * @return array<string, callable(): HTMLObject>
 */
return [
    LanguageSelector::class => static fn (): HTMLObject => new LanguageSelector(),
];
