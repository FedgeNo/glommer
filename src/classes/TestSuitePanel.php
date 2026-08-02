<?php

declare(strict_types=1);

class TestSuitePanel extends Div
{
    public ?string $class = 'TestSuitePanel';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2', 'align-items-start'];

    public function toDOM(): \DOMElement
    {
        $this -> addContent(new Paragraph(
            'Run the site\'s test suite and see the results. It takes a few seconds, so it opens on its own page.'
        ));

        $link = new Anchor(ServerURL::absolute('/admin/tests'), 'Run tests');
        $link -> class = 'Button';
        $this -> addContent($link);

        // Not here, because the answer is about whoever's browser is asking
        // rather than about the server - so it lives in everyone's own settings,
        // where the person whose calls are failing can reach it.
        $this -> addContent(new Paragraph(
            'Video calling has its own check, in Settings, since it can only be answered by the browser actually attempting a connection - and it answers for that browser rather than for the site.'
        ));

        $call_link = new Anchor(ServerURL::absolute('/settings'), 'Check video calling');
        $call_link -> class = 'Button';
        $this -> addContent($call_link);

        return parent::toDOM();
    }
}
