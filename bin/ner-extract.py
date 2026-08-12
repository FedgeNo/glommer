#!/usr/bin/env python3
"""Reads a JSON array of plain-text post bodies from stdin, writes a JSON array
(same length and order) to stdout - each element
{"language": "fi"|null, "entities": [{"type": ..., "value": ...}, ...]}.

The language is detected from the words, not taken from anybody's word for it:
a Fediverse sender fills the declared language from their account setting, so
an account set to English writing in French says English.

Each text goes to the model that reads its language, and to none at all where
there is no such model. One English model over everything read every capitalised
German noun as a proper name - German capitalises all of them - and turned 25
German posts into 124 entities where the German model finds 53.

Models are loaded once per invocation and only for the languages actually
present in the batch, then every text of a language goes through its model in
one nlp.pipe() call. The caller (EntityExtractor::runNER()) passes the whole
trending window in a single call, so loading per post would dominate the runtime
and loading all nine would waste most of it.
"""

import json
import re
import sys

# spacy and langdetect are imported inside main() rather than here, so that
# `ner-extract.py --models` answers on a bare Python before either is
# installed. That is what bin/install.php asks to find out what to download,
# which keeps the list of models in one place instead of two that drift.

# The languages a model is published for, against what the relay actually
# carries. A language absent here has its text read by nothing rather than by
# the English model, which is the choice between no entities and wrong ones.
MODELS = {
    'en': 'en_core_web_sm',
    'de': 'de_core_news_sm',
    'fr': 'fr_core_news_sm',
    'fi': 'fi_core_news_sm',
    'es': 'es_core_news_sm',
    'pt': 'pt_core_news_sm',
    'it': 'it_core_news_sm',
    'nl': 'nl_core_news_sm',
    'pl': 'pl_core_news_sm',
}

# spaCy's purely numeric/temporal labels (CARDINAL, DATE, TIME, MONEY,
# PERCENT, QUANTITY, ORDINAL) are excluded - those are incidental numbers and
# dates, not topics people are "talking about".
ALLOWED_LABELS = {
    'PERSON', 'ORG', 'GPE', 'LOC', 'FAC', 'PRODUCT', 'EVENT',
    'WORK_OF_ART', 'LAW', 'LANGUAGE', 'NORP',
    # What the non-English models call a person. Their label set is a smaller
    # vocabulary for the same things, so it is translated below rather than
    # stored as a second name for one concept.
    'PER',
}

# The non-English models are trained on WikiNER, which names a person PER
# where the English model says PERSON. Same thing, and a topic page is
# addressed by its type, so two names for it would be two pages.
#
# WikiNER's fourth label, MISC, is deliberately not taken: it is the catch-all
# every entity that is none of the other three falls into, so it is both the
# noisiest and the one with nothing to call itself on a page.
LABEL_ALIASES = {'PER': 'PERSON'}

MAX_ENTITY_LENGTH = 100

# Below this there is not enough to read a language from, and langdetect will
# answer confidently anyway. An unread language is the safe answer: the text
# still reaches no model rather than the wrong one.
SHORTEST_DETECTABLE = 20


def readable_words(text):
    """The text with the parts no language can be read from taken out.

    A post that is a headline and a link is mostly the link, and a bot's is
    almost entirely one. Measuring the raw string lets that pass the length
    guard on the strength of characters that say nothing about the language,
    and langdetect then answers confidently off the few words left.
    """
    without_links = re.sub(r'https?://\S+|www\.\S+', ' ', text)
    without_handles = re.sub(r'[@#]\S+', ' ', without_links)

    return re.sub(r'\s+', ' ', without_handles).strip()


def language_of(text, detect):
    text = readable_words(text)

    if len(text) < SHORTEST_DETECTABLE:
        return None

    try:
        return detect(text)
    except Exception:
        # langdetect raises on text it cannot read at all - punctuation, an
        # emoji, a bare URL. That is not an error, it is an answer.
        return None


def entities_in(doc):
    seen = set()
    entities = []

    for ent in doc.ents:
        if ent.label_ not in ALLOWED_LABELS:
            continue

        value = ent.text.strip()

        if not value or len(value) > MAX_ENTITY_LENGTH:
            continue

        label = LABEL_ALIASES.get(ent.label_, ent.label_)
        key = (label, value)

        if key in seen:
            continue

        seen.add(key)
        entities.append({'type': label.lower(), 'value': value})

    return entities


def main():
    # What bin/install.php asks for, so the models to download are named in
    # one place. Answered before importing anything that has to be installed.
    if '--models' in sys.argv[1:]:
        print('\n'.join(sorted(set(MODELS.values()))))

        return

    # Reading a language needs no model - langdetect is the whole of it - so
    # this mode skips importing spacy entirely. It is what fills in the
    # language of every post, including the ones trending never reads: a bot's,
    # a reply, anything that fell past the window before a pass got to it.
    if '--detect' in sys.argv[1:]:
        from langdetect import DetectorFactory, detect

        DetectorFactory.seed = 0

        json.dump([language_of(text, detect) for text in json.load(sys.stdin)], sys.stdout)

        return

    import spacy
    from langdetect import DetectorFactory, detect

    # Same text, same answer, every run: langdetect is randomised by default
    # and would otherwise make a borderline post's entities come and go
    # between passes.
    DetectorFactory.seed = 0

    texts = json.load(sys.stdin)
    languages = [language_of(text, detect) for text in texts]

    results = [{'language': language, 'entities': []} for language in languages]

    # Grouped so each model is loaded once and reads every text of its language
    # in one pass.
    by_language = {}

    for index, language in enumerate(languages):
        if language in MODELS and texts[index].strip():
            by_language.setdefault(language, []).append(index)

    for language, indexes in by_language.items():
        try:
            nlp = spacy.load(MODELS[language])
        except OSError:
            # The model for a language this server sees is not installed. Its
            # posts contribute no entities, which is what they did before any
            # of them were installed.
            continue

        for index, doc in zip(indexes, nlp.pipe([texts[i] for i in indexes])):
            results[index]['entities'] = entities_in(doc)

    json.dump(results, sys.stdout)


if __name__ == '__main__':
    main()
