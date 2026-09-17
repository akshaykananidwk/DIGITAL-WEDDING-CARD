# The PDF engine

The application ships its own PDF writer (`app/Core/Pdf/`), because the brief is a
plain-PHP host: no Composer, no shell, no headless browser. `PdfService` will use mPDF
or Dompdf if either happens to be installed, and otherwise composes the document
itself.

This file explains what the built-in writer does, and — more usefully — what it does
not.

## What it produces

- **PDF 1.4**, valid enough for `qpdf --check`, `pdftotext`, `pdftoppm` and every
  viewer tested. One indirect object per page, a real cross-reference table, a
  `/Catalog`, `/Pages` and `/Info` dictionary.
- **Embedded TrueType fonts** as `CIDFontType2` with `Identity-H` encoding, subsetted
  to the glyphs actually used. A subset carries its own `loca`, `glyf`, `hmtx`, `cmap`,
  `head`, `hhea` and `maxp` tables, with composite glyphs resolved so an accented or
  composed glyph does not lose its components.
- **A `ToUnicode` CMap**, so the text is selectable and searchable in a viewer and
  extractable by `pdftotext` — including the Gujarati and Devanagari text.
- **Flate compression** for content streams and font programs.
- Gradients, clipping paths, transparency groups, rounded rectangles, and JPEG
  pass-through for photos (a JPEG is embedded as-is rather than re-encoded).
- Paper sizes A4, A5, Letter, a mobile-shaped page and a Kankotri page
  (5.5 × 8 inches).

## Indic text without a shaping engine

Correct Gujarati or Devanagari typesetting needs OpenType shaping: `GSUB` for
conjuncts and `GPOS` for mark positioning. HarfBuzz does this. PHP does not have it,
and implementing it was out of scope, so the writer does two targeted things instead:

**Pre-base matra reordering.** A vowel sign such as િ or િ is stored *after* its
consonant and drawn *before* it. `IndicText::toVisualOrder()` moves those code points
ahead of the consonant they attach to, which is what makes કિ render as કિ rather than
ક followed by a stray matra.

**Zero-advance mark positioning.** A combining mark has no advance width, so a naive
writer stacks every mark at the same x position. The writer reads the real left side
bearing and ink bounding box from the font's `glyf` table and emits a `TJ` array that
shifts each mark to sit over the centre of its base glyph, then shifts back.

Getting the second one right needed a fix to the subsetter as well: it was writing a
left side bearing of zero for every glyph, and because Noto's zero-advance marks have
*negative* ink extents (ે spans roughly −390 to −102 font units), renderers were
pushing them to the right of the following character.

## What it does not do

**Conjuncts are not ligated.** ગ્ન renders as ગ + a visible virama + ન rather than the
single conjunct form. The text is correct, selectable, searchable and legible — it is
how a plain-text renderer without `GSUB` shows a cluster. A reader will recognise the
word; a typographer will see that the ligature is missing.

**A pre-base matra sits before the whole cluster.** Where a cluster contains a reph or
another consonant, the matra is placed before all of it rather than before the base
consonant specifically.

**No bidirectional text**, no vertical writing, no complex Arabic joining.

The browser view is unaffected: the invitation page uses real web fonts and the
browser's own shaper, so the card a guest opens — and the screenshot they share — is
typographically correct. Only the generated PDF simplifies joining.

## If you need typographically perfect PDFs

Install mPDF (which bundles a shaper):

```bash
composer require mpdf/mpdf
```

Then set **Admin → Settings → PDF → Engine** to `mPDF`, or leave it on `Automatic`
and it will be preferred when present. `Dompdf` is detected the same way. Neither is
required, neither is bundled, and the application works identically without them —
that is the point of writing the fallback.

## Verifying a change

```bash
php bin/console pdf:test          # self test: registers fonts, writes a page, checks the bytes
php tests/run.php PDF             # 53 checks over the writer, the QR encoder and the exports
```

Useful external tools, if you have them:

```bash
qpdf --check invitation.pdf       # structural validity
pdftotext invitation.pdf -        # is the text extractable and correct?
pdftoppm -r 300 -png invitation.pdf out   # look at the glyphs at 300 dpi
mutool info invitation.pdf        # fonts and page objects
```

The Gujarati lines worth checking after any change to the writer, because each one
exercised a different bug during development:

```
શુભ લગ્ન પ્રસંગ                    matra over the base, conjunct with virama
આપ સૌને હાર્દિક આમંત્રણ            multiple marks, anusvara
સ્થળ: દ્વારકાધીશ મંદિર, દ્વારકા     long conjunct cluster, punctuation
```
