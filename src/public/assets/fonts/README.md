# Fonts

| File | Family | Licence |
|------|--------|---------|
| `archivo.woff2` | [Archivo](https://fonts.google.com/specimen/Archivo) by Omnibus-Type | [SIL Open Font License 1.1](https://openfontlicense.org) |
| `inter.woff2` | [Inter](https://fonts.google.com/specimen/Inter) by Rasmus Andersson | [SIL Open Font License 1.1](https://openfontlicense.org) |

Both are variable fonts covering weights 400–700 in a single file, subset to
`latin` (U+0000–00FF). That range covers every character in the demo data —
`Ødegaard`, `Rúben Amorim` — so the `latin-ext` subsets were dropped; they
added 118 KB to serve nothing.

**Self-hosted rather than linked**, like Bootstrap in `../css/`. Two reasons:
the container has no guaranteed outbound network, and a `fonts.googleapis.com`
link makes every visitor's browser announce this page to a third party before
it can render text.

Re-fetch with the URLs recorded in `assets/css/style.css` above the
`@font-face` block.
