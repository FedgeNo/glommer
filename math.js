import { ClientConfig } from '/ClientConfig.js';

const MATH_COALESCE_SKIP = 'pre, code, .PostFormula, .katex';

function coalesce_display_math(post_body) {
    display_math_block_groups(post_body).forEach((group) => {
        const segments = math_text_segments(group);
        let logical = '';

        segments.forEach((segment) => {
            segment.start = logical.length;
            logical += segment.text;
        });

        const matches = [...logical.matchAll(/\$\$[^\u0000]*?\$\$|\\\[[^\u0000]*?\\\]/g)];

        for (let i = matches.length - 1; i >= 0; i--) {
            coalesce_run(segments, logical, matches[i].index, matches[i].index + matches[i][0].length);
        }
    });
}

function display_math_block_groups(post_body) {
    const groups = [];
    let open_p_group = null;

    Array.from(post_body.children).forEach((child) => {
        if (child.tagName === 'P') {
            if (open_p_group === null) {
                open_p_group = [];
                groups.push(open_p_group);
            }
            open_p_group.push(child);
            return;
        }

        open_p_group = null;

        if (child.tagName === 'PRE') {
            return;
        }

        if (child.tagName === 'OL' || child.tagName === 'UL') {
            Array.from(child.children).forEach((li) => groups.push([li]));
            return;
        }

        groups.push([child]);
    });

    return groups;
}

function math_text_segments(blocks) {
    const segments = [];

    blocks.forEach((block, index) => {
        if (index > 0) {
            segments.push({ text: '\n' });
        }

        collect_math_segments(block, block, segments);
    });

    return segments;
}

function collect_math_segments(node, block, segments) {
    node.childNodes.forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) {
            segments.push({ text: child.data, node: child, block: block });
        } else if (child.nodeType === Node.ELEMENT_NODE) {
            if (child.tagName === 'BR') {
                segments.push({ text: '\n', node: child, block: block });
            } else if (child.matches(MATH_COALESCE_SKIP)) {
                segments.push({ text: '\u0000' });
            } else {
                collect_math_segments(child, block, segments);
            }
        }
    });
}

function coalesce_run(segments, logical, start, end) {
    const covered = segments.filter((segment) =>
        segment.node !== undefined
        && segment.start < end
        && segment.start + segment.text.length > start
    );
    const first = covered[0];
    const last = covered[covered.length - 1];

    if (first === last) {
        return;
    }

    let start_node = first.node;

    if (start > first.start) {
        start_node = start_node.splitText(start - first.start);
    }

    if (end - last.start < last.node.data.length) {
        last.node.splitText(end - last.start);
    }

    start_node.parentNode.insertBeforeWithSpace(document.createTextNode(logical.slice(start, end)), start_node);

    start_node.remove();
    covered.slice(1).forEach((segment) => segment.node.remove());

    let block = first.block.nextElementSibling;

    while (block !== null && block !== last.block) {
        const next = block.nextElementSibling;
        block.remove();
        block = next;
    }

    if (last.block !== first.block && !last.block.hasChildNodes()) {
        last.block.remove();
    }
}

export function render_math(element) {
    render_formulas(element);

    if (typeof renderMathInElement !== 'function') {
        return;
    }
    element.querySelectorAll('.PostBody').forEach((post_body) => {
        const text = post_body.textContent;

        if (text.includes('$$') || text.includes('\\[')) {
            coalesce_display_math(post_body);
        }
    });

    renderMathInElement(element, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '\\[', right: '\\]', display: true },
            { left: '\\(', right: '\\)', display: false },
            { left: '$', right: '$', display: false },
        ],
        throwOnError: false,
    });
}

export function render_formulas(element) {
    if (typeof katex === 'undefined' || typeof katex.render !== 'function') {
        return;
    }

    element.querySelectorAll('.PostFormula[data-formula]').forEach((span) => {
        if (span.dataset.rendered === '1') {
            return;
        }

        katex.render(span.dataset.formula, span, { throwOnError: false });
        span.dataset.rendered = '1';
    });
}

export class MathRenderer {
    static init() {
        if (ClientConfig.get('needsMath')) {
            render_math(document.body);
        }
    }
}
