// Project Reviews SOP — shared Typst theme (no external packages)

// Brand palette (aligned with plugin app-shell academic tone)
#let pr-primary = rgb("#1e3a5f")
#let pr-accent = rgb("#2563eb")
#let pr-coordinator = rgb("#0f766e")
#let pr-reviewer = rgb("#7c3aed")
#let pr-warning = rgb("#b45309")
#let pr-danger = rgb("#b91c1c")
#let pr-muted = rgb("#64748b")
#let pr-surface = rgb("#f8fafc")
#let pr-border = rgb("#e2e8f0")

#let font-body = "New Computer Modern"
#let font-display = "New Computer Modern"

#let title-page(title, subtitle, version, date) = {
  set page(margin: 2.5cm)
  set text(font: font-body, size: 11pt)
  align(center + horizon)[
    #v(1fr)
    #block(
      fill: pr-primary,
      inset: (x: 2.5em, y: 2em),
      radius: 6pt,
      width: 100%,
    )[
      #text(fill: white, font: font-display, size: 28pt, weight: "bold")[#title]
      #v(0.6em)
      #text(fill: rgb("#cbd5e1"), size: 13pt)[#subtitle]
    ]
    #v(2em)
    #grid(
      columns: (1fr, 1fr),
      gutter: 1em,
      align(left)[
        #text(weight: "bold", fill: pr-muted)[Document version] \
        #version
      ],
      align(right)[
        #text(weight: "bold", fill: pr-muted)[Last updated] \
        #date
      ],
    )
    #v(1fr)
    #text(size: 9pt, fill: pr-muted)[
      WordPress plugin · Coordinator & Reviewer workspaces \
      Compile with Typst 0.12+ · `typst compile project-reviews-sop.typ`
    ]
  ]
  pagebreak()
}

#let role-badge(role) = {
  let (fill, label) = if role == "coordinator" {
    (pr-coordinator.lighten(85%), "Coordinator")
  } else if role == "reviewer" {
    (pr-reviewer.lighten(85%), "Reviewer")
  } else {
    (pr-accent.lighten(88%), "Both roles")
  }
  box(
    fill: fill,
    inset: (x: 8pt, y: 3pt),
    radius: 4pt,
    stroke: 0.5pt + fill.darken(15%),
  )[#text(size: 8.5pt, weight: "bold", fill: fill.darken(55%))[#label]]
}

#let callout(icon, accent, body) = block(
  fill: accent.lighten(92%),
  stroke: (left: 3pt + accent),
  inset: (left: 12pt, rest: 10pt),
  radius: 4pt,
  width: 100%,
  above: 0.8em,
  below: 0.8em,
)[
  #grid(
    columns: (auto, 1fr),
    column-gutter: 8pt,
    align(horizon)[#text(size: 12pt)[#icon]],
    body,
  )
]

#let tip(body) = callout("💡", pr-accent, body)
#let warning(body) = callout("⚠", pr-warning, body)
#let danger(body) = callout("⛔", pr-danger, body)

// ── Screenshot assets (change one line to pin an older capture run) ─────────────
// PNGs live under docs/sop/{sop-screenshots-dir}{id}.png
// After `npm run sop:screenshots`, set this to the printed folder (e.g. screenshots/2026-06-13_230646/)
// Relative to docs/sop/lib/theme.typ → docs/sop/screenshots/…
#let sop-screenshots-dir = "../screenshots/2026-06-14_002624/"

// When true, embed PNGs listed in sop-captured-ids from sop-screenshots-dir; else placeholder.
#let use-live-screenshots = true

// IDs present in screenshots/2026-06-13_234624/ (update after `npm run sop:screenshots`)
#let sop-captured-ids = (
  "01-landing-login",
  "02-workspace-top-nav",
  "03-coordinator-nav",
  "04-dashboard",
  "05-registry",
  "06-csv-import-mapper",
  "07-wizard-nav",
  "08-wizard-students",
  "09-wizard-panels",
  "10-wizard-reviewers",
  "11-wizard-rubric-builder",
  "12-rubric-confirm-dialog",
  "13-wizard-assignments",
  "14-wizard-open-marking",
  "15-progress-accordion",
  "17-unfreeze-requests",
  "18-reports-tabs",
  "20-reports-downloads",
  "22-panel-report-settings",
  "23-audit-log",
  "24-close-project",
  "25-reviewer-assignments",
  "26-marking-grid",
  "27-marking-grid-mobile",
  "28-rubric-form",
  "29-validation-error",
  "31-freeze-scores-dialog",
  "32-unfreeze-request",
  "33-panel-head-card",
  "34-panel-report-page",
  "35-panel-head-unfreeze",
  "36-portal-login",
)

#let screenshot-path(id) = sop-screenshots-dir + id + ".png"

#let screenshot-on-disk(id) = use-live-screenshots and sop-captured-ids.contains(id)

#let screenshot-placeholder(id, path, width) = block(
  fill: pr-surface,
  stroke: 1.5pt + pr-border,
  radius: 6pt,
  inset: 0pt,
  width: width,
)[
  #block(
    fill: pr-primary.lighten(92%),
    inset: (x: 1.2em, y: 0.9em),
    width: 100%,
  )[
    #align(left)[
      #text(size: 8pt, weight: "bold", fill: pr-primary)[PLACEHOLDER · #upper(id)]
    ]
  ]
  #block(inset: 2em, width: 100%)[
    #align(center)[
      #text(size: 36pt, fill: pr-border)[⬚]
      #v(0.4em)
      #text(size: 10pt, fill: pr-muted)[
        Run `npm run sop:screenshots` or save `#path`
      ]
    ]
  ]
]

#let screenshot(id, caption, capture-hint, width: 100%) = {
  let path = screenshot-path(id)
  let live = screenshot-on-disk(id)
  figure(
    block(width: width, breakable: false)[
      #if live {
        image(path, width: width)
      } else {
        screenshot-placeholder(id, path, width)
      }
    ],
    caption: {
      caption
      if not live [
        \ 
        #text(size: 8.5pt, style: "italic", fill: pr-accent.darken(10%))[
          📷 Screenshot to capture: #capture-hint
        ]
      ]
    },
  )
}

#let url-box(label, url) = {
  block(
    fill: pr-surface,
    stroke: 0.75pt + pr-border,
    radius: 4pt,
    inset: 10pt,
    width: 100%,
  )[
    #text(weight: "bold", size: 9pt, fill: pr-muted)[#label] \
    #raw(url, lang: "")
  ]
}

#let part-page(title, subtitle, color) = {
  pagebreak()
  block(
    fill: color,
    inset: (x: 2em, y: 1.6em),
    radius: 0pt,
    width: 100%,
  )[
    #text(fill: white, font: font-display, size: 22pt, weight: "bold")[#title]
    #v(0.3em)
    #text(fill: white.transparentize(20%), size: 12pt)[#subtitle]
  ]
  v(1.2em)
}

#let procedure(title, role, ..steps) = {
  block(
    stroke: 0.75pt + pr-border,
    radius: 6pt,
    inset: 0pt,
    width: 100%,
    breakable: true,
    above: 1em,
    below: 1em,
  )[
    #block(
      fill: pr-surface,
      inset: (x: 12pt, y: 8pt),
      width: 100%,
    )[
      #role-badge(role)
      #h(6pt)
      #text(weight: "bold", fill: pr-primary)[#title]
    ]
    #block(inset: 12pt)[
      #set enum(numbering: "1.")
      #for s in steps.pos() { s }
    ]
  ]
}

// ── One-page flow diagram helpers ─────────────────────────────────────────────

#let flow-arrow(down: true) = {
  align(center)[
    #text(size: 11pt, fill: pr-muted)[#if down { "↓" } else { "→" }]
  ]
}

#let flow-node(label, detail: none, fill: pr-surface, accent: pr-primary, width: 100%) = {
  block(
    fill: fill,
    stroke: (left: 3pt + accent, rest: 0.75pt + pr-border),
    radius: 4pt,
    inset: (left: 10pt, rest: 8pt),
    width: width,
  )[
    #text(size: 9pt, weight: "bold", fill: accent)[#label]
    #if detail != none {
      v(2pt)
      text(size: 8pt, fill: pr-muted)[#detail]
    }
  ]
}

#let flow-gate(label, detail) = flow-node(
  label,
  detail: detail,
  fill: pr-warning.lighten(90%),
  accent: pr-warning,
)

#let flow-handoff(label) = {
  align(center)[
    #box(
      fill: pr-accent.lighten(88%),
      stroke: 0.75pt + pr-accent,
      radius: 20pt,
      inset: (x: 10pt, y: 4pt),
    )[
      #text(size: 7.5pt, weight: "bold", fill: pr-accent.darken(15%))[#label]
    ]
  ]
}
