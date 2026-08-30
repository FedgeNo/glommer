import { TestCase } from './TestCase.js';
import {
    Article,
    Button,
    Div,
    HTMLObject,
    Image,
    Span,
} from '../../scripts/HTMLObjects.js';

class Family extends Div {
    static className = 'Family';
    static properties = {
        subject: 'default subject',
        choices: [],
    };
}

class FamilyChild extends Family {
    static properties = {
        subject: 'child default subject',
        detail: null,
    };
}

class StructurallyConfusedFamily extends Family {
    static properties = {
        attributes: 'not attributes',
        contents: 'not contents',
    };
}

export default {
    suite: 'HTMLObject',
    tests: {
        'descendants build nested DOM from objects strings attributes and nodes'() {
            const parent = new FamilyChild({ subject: 'kept as declared data', ignored: 'discarded' });
            parent.id = 'subject';
            parent.attributes.role = 'group';
            parent.addContent('Before');

            const child = new Div();
            child.addContent('Inside');
            parent.addContent(child);

            const node = document.createElement('span');
            node.textContent = 'After';
            parent.addContent(node);

            const element = parent.toDOM();

            TestCase.assertEquals('DIV', element.tagName);
            TestCase.assertEquals('Family FamilyChild', element.getAttribute('class'));
            TestCase.assertEquals('subject', element.getAttribute('id'));
            TestCase.assertEquals('group', element.getAttribute('role'));
            TestCase.assertEquals('BeforeInsideAfter', element.textContent);
            TestCase.assertEquals('kept as declared data', parent.subject);
            TestCase.assertEquals(undefined, parent.ignored);
        },

        'declared defaults inherit and input overlays only known data properties'() {
            const defaults = new FamilyChild();
            const other = new FamilyChild();
            const hydrated = new FamilyChild({ subject: 'changed', detail: 'known', unknown: 'ignored' });

            defaults.choices.push('one object only');

            TestCase.assertEquals('child default subject', defaults.subject);
            TestCase.assertNull(defaults.detail);
            TestCase.assertEquals(0, other.choices.length);
            TestCase.assertEquals('changed', hydrated.subject);
            TestCase.assertEquals('known', hydrated.detail);
            TestCase.assertEquals(undefined, hydrated.unknown);
        },

        'a descendant cannot declare structural properties as hydratable data'() {
            const object = new StructurallyConfusedFamily({ attributes: 'changed', contents: 'changed' });

            TestCase.assertEquals('{}', JSON.stringify(object.attributes));
            TestCase.assertEquals('[]', JSON.stringify(object.contents));
        },

        'rendering one object twice is refused'() {
            const object = new Family();
            object.toDOM();

            TestCase.assertThrows(() => object.toDOM());
        },

        'image objects render their own attributes'() {
            const image = new Image({ src: '/image.jpg', alt: 'Subject' });
            image.attributes.loading = 'lazy';
            const element = image.toDOM();

            TestCase.assertEquals('IMG', element.tagName);
            TestCase.assertEquals('/image.jpg', element.getAttribute('src'));
            TestCase.assertEquals('Subject', element.getAttribute('alt'));
            TestCase.assertEquals('lazy', element.getAttribute('loading'));
        },

        'generic primitives carry no application class'() {
            for (const Primitive of [Article, Button, Div, Image, Span]) {
                TestCase.assertEquals('', Primitive.compoundedClassName());
                TestCase.assertTrue(new Primitive() instanceof HTMLObject);
                TestCase.assertNull(new Primitive().toDOM().getAttribute('class'));
            }
        },

        'runtime state follows the compounded class identity'() {
            const object = new FamilyChild();
            object.class = 'Selected Family';

            TestCase.assertEquals('Family FamilyChild Selected', object.toDOM().getAttribute('class'));
        },
    },
};
