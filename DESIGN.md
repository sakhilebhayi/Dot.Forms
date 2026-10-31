# Design: The Carbon Book

Source of truth: `resources/css/carbon.css` (`cb-` classes, tokens on `:root`). Chosen by the
owner 2026-10-31 from three R-30 boards (framed sheet, amber ladder, carbon book).

## Concept
The South African triplicate carbon-copy book, made digital. A form is a **master** that the
company sets; every response is a numbered **copy**; decisions land as **stamps**. The product
surfaces are the parts of the book: the spine (navigation), the register (the list of forms),
the press (the builder), the copies (responses), the tallies (analytics). Everything carries a
serial in mono — forms `FRM-012`, copies `FRM-012/148` — because numbered paper is how business
trusts paper.

## Tokens
- Desk `--paper #f3f1ea` (light) / near-black desk in dark; sheets `--sheet #fdfcf8` stay paper
  in both schemes **except** the chrome, which darkens. A published form prints light: the
  public document keeps the paper sheet in dark mode too, on a dark desk.
- Ink `#191a1c`; muted `#6e6a60`; rules `#d9d4c6`; box lines `#b9b3a2`.
- **Carbon blue `#2b4a9e`** is the one working hue: serials, the selected field, links, focus,
  the copy tint `--carbon-wash #eef1f9`. It is the carbon paper itself — data, never decoration.
- **Stamp red `#b3362a`** and **stamp green `#2f6a4a`** exist only inside a stamp or a verdict.
  Never as button or chrome colours.
- Radius 3px (paper corners, not pills). Depth = offset block shadows (`3px 3px 0` ink at 8%),
  never blur.

## Type
- **Archivo** 400/500/600/700: everything read.
- **IBM Plex Mono** 500/600: serials, field numbers, stamps, keys, counts in tables. Never body
  text. Stamps are mono, letter-spaced, 1.5–2px bordered, rotated −1° to −3°.

## Components (`carbon.css`)
- `cb-spine` — top navigation: brand, section tabs on a 2px ink baseline, company + book code in
  mono on the right. No sidebar.
- `cb-register` / `cb-bookrow` — the list of forms: serial, name + metadata line, tallies,
  state plate (`cb-state`, mono, bordered, −1°: IN USE / UNSET / CLOSED).
- `cb-master` — the builder canvas: a sheet with the double-rule head (`cb-master__head`,
  title + `MASTER · FRM-xxx · vN`), fields as `cb-fieldbox` (1px box, floating mono field
  number on the top edge, dotted answer line, hint row). Selected = carbon border + wash shadow.
- `cb-tray` — the field palette ("type tray"): bordered buttons with a mono type mark.
- `cb-props` — the settings column: mono section heads, boxed inputs, `cb-tick` checkboxes.
- `cb-copy` — one response row: carbon wash + carbon border, serial, summary, time, stamp slot.
- `cb-stamp` — APPROVED / AWAITING / REJECTED etc.: mono caps, 2px border, rotated.
- `cb-btn` (sheet) / `cb-btn--primary` (ink) / `cb-btn--quiet`; `cb-input`, `cb-select`,
  `cb-label`; `cb-table` with tabular numerals; `cb-note` (status line), `cb-empty`.
- Public document: `cb-doc` — the sheet with `cb-doc__head` (title + serial on a 2px rule),
  numbered `cb-q` boxes whose inputs are **underlines, not boxes-in-boxes** (ink baseline,
  carbon focus ring), progress as `n of m` in mono rather than a bar, and the submit button
  naming the copy it will create when the count is public.

## Page layouts
Each page is the matching part of the book, never one card grid:
- Desk (dashboard): the open book — a short register of recent masters beside the latest copies
  and the tallies that matter (copies this week, awaiting stamp, completion).
- The book (forms index): the register, full width; search above as a ruled line.
- The press (builder): tray · master sheet · settings, 240/1fr/280; on a phone the tray becomes
  a bottom row and settings stack under the selected field.
- Copies (submissions): copy rows; opening one shows the copy as a filled document, not a modal
  of key-values dumped in a grid.
- Tallies (analytics): counts as stencil-free plain figures with mono units; one chart answers
  one question.

## Behaviour
- Motion: almost none. A stamp lands once (scale 1.06→1, 160ms, reduced-motion: none). Nothing
  idles, floats or pulses.
- Focus: 2px carbon outline, offset 2, instant.
- Dark scheme: desk and chrome darken, sheets hold paper-dark (`#232420`) with light ink; the
  published document stays paper-light on the dark desk.

## Don'ts
Pills, gradients, glassmorphism, blur shadows, emoji in chrome, icon buttons without names,
tinted icon squares, pastel category colours, decorative illustrations, stamps used as badges
for non-decisions, carbon blue on large areas, more than one hue per screen outside stamps.
