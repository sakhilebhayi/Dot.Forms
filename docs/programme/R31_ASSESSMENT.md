# R-31 — Design transformation: honest assessment and plan

Brief: `requirements/dot-forms-design-transformation.md` (verbatim, 2026-10-31). Assessed against
the product as deployed the same day (R-30 phases 1–4, PRs #59–#62 live).

## What the brief asks that R-30 already delivered (same day)

The brief's §3 identity and anti-slop list describes the pre-R-30 product. Already live: the
Carbon Book system (DESIGN.md + carbon.css — no gradients, no card grids, no emoji chrome),
drag-and-drop reorder + duplicate + autosave with a visible saved state, 23 collecting field
types incl. signature, page breaks, logic v2, 15 SA templates + team templates, four document
themes + accent + width, the response desk. Those items are not redone here.

## What the brief asks that the product genuinely lacks

1. **The press edits configuration, not the form.** Field cards are stacks of Label/Type/
   Placeholder inputs — the creator never sees the form take shape (§4B: "the form itself
   should remain the visual centre of attention"). This is the largest honest gap.
2. **No media or text elements** (§5): nothing between questions — no headings, prose, images,
   video, dividers. Forms are questions only.
3. **No insert-between**: new fields append at the end and must be moved up.
4. **No in-editor device preview** (§4A) beyond opening the public page.
5. **Element library gaps** (§9): address, matrix, ranking, image selection, calculations,
   repeating sections.
6. **Motion** (§7): the document has one stamp animation; the press has none of the gentle
   feedback the brief wants.

## R-31 phases (one PR each, owner approval per merge)

1. **The living master** (this PR): the press's centre column becomes the document itself —
   fields render exactly as the published form (the same `field-input` partial, inputs disabled),
   click a field to select it, its settings appear in the properties column, ✎/✕/⧉/grip on the
   selected box, an insert control between every pair of elements, and canvas width toggles
   (desk/tablet/phone) so the creator sees the form at every size while editing. Plus the first
   **display elements** in the registry: section heading, text block, image (uploaded, stored
   like the logo), video embed (YouTube/Vimeo only, URL parsed server-side, embedded as
   youtube-nocookie/player iframes), divider — rendered on the published document and the canvas,
   collecting nothing.
2. Element library completion: address, matrix, ranking, image selection; calculations.
3. Landing-page presentation mode + completion-screen design + richer progress — BUILT
   (Phase 3): a "Cover" presentation (optional full-bleed cover image stored like the logo,
   masthead kicker, display-scale title, Begin, an honest question count) fronting the unchanged
   document; a receipt-style completion screen (RECEIVED stamp, owner heading, dashed tear line,
   `FRM-xxx · lodged <date> · your copy`, optional "Submit another response" and an owner onward
   link rendered as a button — respondents are never auto-redirected, and only http(s) URLs are
   accepted at save or rendered); a 3px accent progress rule for conversational and paged
   documents (260ms width transition, disabled under reduced motion).
4. Motion pass (press transitions, document question transitions, reduced-motion audit) +
   mobile editing ergonomics.

Out of honest scope for now (stated, not faked): video uploads (host storage and transcoding),
multi-column layouts, free image repositioning/cropping inside the document, editor-level
undo/redo (version restore exists), importing foreign forms.

## Finding from the Phase 1 live round (for Phase 2+)
"Save Draft" on a **published** form silently unpublishes it — an owner tweaking a live form
takes it offline without warning. The press needs publish-state-preserving save semantics
(Save keeps the state; Publish/Unpublish is its own decision).
