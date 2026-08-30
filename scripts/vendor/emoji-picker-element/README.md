# Vendored emoji picker

This directory contains the browser files used from:

- `emoji-picker-element` 1.22.8 (`index.js`, `picker.js`, `database.js`)
- `emoji-picker-element-data` 1.8.0 (`data.json`, English Emojibase data)

They are vendored because an ES-module import graph cannot apply Subresource
Integrity to its transitive dynamic imports. The downloaded npm archives were
verified against these registry integrity values before the files were copied:

- `sha512-EFgRjrlIcdA1ilyMH/f9KjB0Pi/vynrojNgMDZfU1Jv2YLrhdLJWx6xCehizPyxm4/NUuB8DfFvIT4v+1njjPQ==`
- `sha512-VfRuRJNEDLS1JKlNS4olaqhjX5S1nnZ+ZHG73b/dV8QeZyi0yPruTPEE72EmF6XO3k/9hj3lybMIYMOYXb/57A==`

`DEFAULT_DATA_SOURCE` in `database.js` and `picker.js` is the only upstream
code change; it points at this directory's same-origin `data.json`.
