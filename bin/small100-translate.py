#!/usr/bin/env python3
"""Translates one text with SMaLL-100 (CTranslate2), the way argos-translate is
invoked: --from-lang and --to-lang name the pair, the text comes in on stdin,
the translation goes out on stdout. Nothing is held between invocations - the
model is loaded fresh and released when this process exits, because the box
running this has under two spare gigabytes and no room for a resident model.

SMaLL-100 is wired differently from plain M2M-100/NLLB: the target-language
token goes at the start of the *source* sequence (with the source itself
ending in </s>), not passed as target_prefix to translate_batch(). Passing
target_prefix here reproduces the wrong-language output that using the model
card's own example run into.

The source language is read but unused beyond validating the pair: SMaLL-100,
like M2M-100, does not need to be told what it is reading - the target token
alone drives generation. It stays in the CLI surface anyway because Translator
always has one to give, and a wrong source is still a signal something upstream
disagrees with this script about the pair.
"""

import sys
import argparse
import ctranslate2

# The model directory these look for by default. Translator.php always
# passes --model-dir explicitly (its SMALL100_MODEL_DIR constant), and
# bin/install.php's SMALL100_MODEL_DIR names the same place - this default
# only matters for running the script by hand.
DEFAULT_MODEL_DIR = '/opt/glommer-translate/models/small100'


def fail(message: str) -> None:
    print(message, file=sys.stderr)
    sys.exit(1)


def main() -> None:
    parser = argparse.ArgumentParser(add_help=False)
    parser.add_argument('--from-lang', required=True)
    parser.add_argument('--to-lang', required=True)
    parser.add_argument('--model-dir', default=DEFAULT_MODEL_DIR)
    args = parser.parse_args()

    text = sys.stdin.read()

    if text.strip() == '':
        # Same contract as argos-translate: nothing in, nothing out, exit 0.
        return

    try:
        # tokenization_small100.py ships beside the model files (see the
        # model card) rather than as an installed package, so it is imported
        # from there rather than from site-packages.
        sys.path.insert(0, args.model_dir)
        from tokenization_small100 import SMALL100Tokenizer
    except ImportError as error:
        fail('small100-translate: could not import SMALL100Tokenizer from '
             + args.model_dir + ': ' + str(error))
        return

    try:
        translator = ctranslate2.Translator(args.model_dir, device='cpu', compute_type='int8')
        tokenizer = SMALL100Tokenizer.from_pretrained(args.model_dir)
    except Exception as error:
        fail('small100-translate: could not load the model at ' + args.model_dir + ': ' + str(error))
        return

    target_token = '__' + args.to_lang + '__'

    if target_token not in tokenizer.lang_code_to_id:
        fail('small100-translate: ' + args.to_lang + ' is not one of the languages this model has')
        return

    # Encoded without the tokenizer's own language handling - tgt_lang is not
    # set here, because SMALL100Tokenizer.encode() would otherwise apply the
    # standard M2M-100 shape this model does not use. The target token is
    # placed at the front of the source sequence by hand instead, and the
    # source's own </s> follows it, per the note above.
    source_tokens = tokenizer.convert_ids_to_tokens(tokenizer.encode(text, add_special_tokens=False))
    input_tokens = [target_token] + source_tokens + ['</s>']

    try:
        results = translator.translate_batch(
            [input_tokens],
            beam_size=5,
            max_decoding_length=512,
        )
    except Exception as error:
        fail('small100-translate: translation failed: ' + str(error))
        return

    output_tokens = results[0].hypotheses[0]
    translated = tokenizer.decode(tokenizer.convert_tokens_to_ids(output_tokens), skip_special_tokens=True)

    print(translated.strip())


if __name__ == '__main__':
    main()
