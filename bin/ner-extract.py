#!/usr/bin/env python3
"""Reads a JSON array of plain-text post bodies from stdin, writes a JSON
array (same length and order) to stdout - each element the input text's
named entities as a list of {"type": ..., "value": ...} objects.

One spaCy model load per invocation, batched across every text via
nlp.pipe() - the caller (EntityExtractor::runNER()) passes every post in the
current trending window in a single call, not one post per process, since
loading the model per-post would dominate the runtime.
"""

import json
import sys

import spacy

# spaCy's purely numeric/temporal labels (CARDINAL, DATE, TIME, MONEY,
# PERCENT, QUANTITY, ORDINAL) are excluded - those are incidental numbers and
# dates, not topics people are "talking about".
ALLOWED_LABELS = {
    'PERSON', 'ORG', 'GPE', 'LOC', 'FAC', 'PRODUCT', 'EVENT',
    'WORK_OF_ART', 'LAW', 'LANGUAGE', 'NORP',
}

MAX_ENTITY_LENGTH = 100


def main():
    texts = json.load(sys.stdin)
    nlp = spacy.load('en_core_web_sm')

    results = []

    for doc in nlp.pipe(texts):
        seen = set()
        entities = []

        for ent in doc.ents:
            if ent.label_ not in ALLOWED_LABELS:
                continue

            value = ent.text.strip()

            if not value or len(value) > MAX_ENTITY_LENGTH:
                continue

            key = (ent.label_, value)

            if key in seen:
                continue

            seen.add(key)
            entities.append({'type': ent.label_.lower(), 'value': value})

        results.append(entities)

    json.dump(results, sys.stdout)


if __name__ == '__main__':
    main()
