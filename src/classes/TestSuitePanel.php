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

        // Its own page for the same reason: it negotiates for real and each step
        // has to be allowed to time out.
        $this -> addContent(new Paragraph(
            'Video calling has its own check, since it can only be answered by the browser actually attempting a connection.'
        ));

        $call_link = new Anchor(ServerURL::absolute('/admin/call-test'), 'Check video calling');
        $call_link -> class = 'Button';
        $this -> addContent($call_link);

        return parent::toDOM();
    }
}
