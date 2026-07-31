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

        return parent::toDOM();
    }
}
