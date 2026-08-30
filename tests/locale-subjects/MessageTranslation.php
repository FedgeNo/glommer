<?php

declare(strict_types=1);

/**
 * @return array<string, callable(): HTMLObject>
 *
 * MessageTranslationNotice has no class of its own - it is a dialog the
 * browser raises, so its words are read by scripts/Controllers.js
 * and there is nothing here to render.
 */

return [
    MessageTranslateButton::class => static fn (): HTMLObject => new MessageTranslateButton(1),
];
