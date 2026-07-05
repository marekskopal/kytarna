# Handoff: Kytario Design System

## Overview

A new, ground-up visual system for **Kytario** — a multi-tenant, MCP-native Kanban task manager. The system is dense, minimal, light-mode only, Inter-based, indigo-accented, and treats AI agent activity as a first-class surface throughout the UI.

This package contains:

- A **single HTML design canvas** (`Kytario Design System.html`) showing tokens, components, and 7 application screens side-by-side.
- A **production-ready token sheet** (`tokens.css`) — every color, spacing, radius, shadow, and font value as CSS variables.
- React/JSX prototype files (`foundations.jsx`, `components.jsx`, `screens.jsx`) you can read as ground truth for layout, density, copy, and interaction.

## About the Design Files

The files in this bundle are **design references** — they were built as HTML prototypes to specify the intended look, density, and behavior. **They are not production code to copy verbatim.**

Your job is to recreate these designs inside the existing **Kytario Angular 21 codebase** using its established patterns: standalone components + signals, SCSS partials in `frontend/src/styles/`, `ngx-translate` for all copy, attribute-routed reactive forms. The current codebase already has a thin design-token layer in `frontend/src/styles/_variables.scss` — your work is to **replace and expand** those tokens to match this new system, then update every component template/SCSS to the new patterns.

## Fidelity

**High fidelity.** Exact hex values, font sizes, line-heights, border colors, control heights, and spacing are all specified. The prototype is the source of truth — when in doubt, open the relevant artboard in `Kytario Design System.html` and match it pixel-for-pixel.

## Target codebase

```
Stack:    Angular 21 (standalone components + signals), SCSS, ngx-translate
Repo:     github.com/marekskopal/kytario · frontend/
Style:    frontend/src/styles/_variables.scss   ← replace contents
          frontend/src/styles/_mixins.scss      ← keep / extend
          frontend/src/styles.scss              ← rewrite globals to new system
i18n:     frontend/src/i18n/{en,cs}.json        ← all copy goes through translate pipe
Linting:  ng lint --max-warnings=0 (must pass)
```

Component locations that need to be re-skinned:

| File | What changes |
|---|---|
| `app/shared/components/layout/layout.component.{html,scss}` | Topbar redesign (workspace switcher chip, search field, AI nav, ⌘K kbd) |
| `app/board/board.component.{html,scss}` | Kanban columns, headers, dot+count |
| `app/board/task-card.component.{html,scss}` | New task card anatomy (ID/priority/title/meta + agent variant) |
| `app/board/task-detail-drawer.component.{html,scss}` | Full redesign — meta grid, activity feed |
| `app/projects/*` | Projects list table |
| `app/tasks/*` | Workspace tasks grid with filter bar |
| `app/events/*` | Activity log timeline (with agent highlighting) |
| `app/workspaces/*` | Tabbed settings page incl. MCP clients panel |
| `app/authentication/login.component.{html,scss}` | Split-pane login with dark aside |
| Global `styles.scss` | New `.btn`, `.input`, `.field`, etc. classes |

---

## Design tokens

Drop these into `frontend/src/styles/_variables.scss`. Names mirror SCSS conventions but values come straight from `tokens.css`.

### Surfaces
```scss
$color-bg:            #fafafa;
$color-surface:       #ffffff;
$color-surface-muted: #f4f4f5;
$color-surface-deep:  #ebebed;
$color-surface-hover: #f7f7f8;
```

### Borders
```scss
$color-border:        #e7e7ea;
$color-border-strong: #d4d4d8;
$color-border-focus:  #5e6ad2;
```

### Text
```scss
$color-text:         #18181b;
$color-text-muted:   #52525b;
$color-text-subtle:  #8a8a92;
$color-text-faint:   #b4b4ba;
$color-text-inverse: #ffffff;
```

### Accent · Indigo
```scss
$color-accent:        #5e6ad2;
$color-accent-hover:  #4f5bbf;
$color-accent-active: #424ea8;
$color-accent-soft:   #eef0fb;
$color-accent-fg:     #ffffff;
```

### Semantic
```scss
$color-success: #16794a;  $color-success-soft: #e6f5ee;  $color-success-border: #b6e1c8;
$color-warn:    #a35c00;  $color-warn-soft:    #fbf2dd;  $color-warn-border:    #f1d795;
$color-danger:  #b42318;  $color-danger-soft:  #fdecea;  $color-danger-border:  #f4b8b0;
$color-info:    #1e58b6;  $color-info-soft:    #e6efff;  $color-info-border:    #b6cff7;
$color-ai:      #6f4ed3;  $color-ai-soft:      #f0ebfb;  $color-ai-border:      #d4c5f3;
```

### Workflow status dots
```scss
$color-status-todo:    #94a3a8;
$color-status-doing:   #c98a14;
$color-status-review:  #4a8fd6;
$color-status-done:    #16794a;
$color-status-blocked: #b42318;
```

### Priority chip backgrounds (bg / fg)
```scss
$prio-low:    (#f1f1f3, #52525b);
$prio-med:    (#fbf2dd, #a35c00);
$prio-high:   (#fdecea, #b42318);
$prio-urgent: (#18181b, #ffffff);
```

### Spacing (4 px base)
```scss
$space-1:  4px;   $space-2:  6px;   $space-3:  8px;   $space-4:  10px;
$space-5:  12px;  $space-6:  16px;  $space-7:  20px;  $space-8:  24px;
$space-9:  32px;  $space-10: 40px;  $space-11: 56px;  $space-12: 72px;
```

### Radii
```scss
$radius-xs:   3px;   // chips
$radius-sm:   5px;   // buttons, inputs
$radius-md:   7px;   // cards, alerts
$radius-lg:   10px;  // big panels
$radius-xl:   14px;  // hero / modal
$radius-pill: 999px;
```

### Type
```scss
$font-sans: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
$font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
// Sizes: 10 11 12 13 14 15(base) 16 18 20 24 30 40
// Tracking: tight -0.012em, tighter -0.022em, wide 0.04em
```

Load Inter (400/500/600/700) and JetBrains Mono (400/500) via the existing Google Fonts include or self-host.

### Control heights (density tokens)
```scss
$h-xs: 22px;   $h-sm: 26px;   $h-md: 30px;   $h-lg: 36px;   $h-xl: 44px;
// Default control = md (30 px). The previous codebase used md=14px font — switch to 13 px.
```

### Shadows
Used sparingly. Borders separate static surfaces; shadows only on floating UI (menus, drawers, toasts).
```scss
$shadow-xs: 0 1px 0  rgba(24,24,27,0.04);
$shadow-sm: 0 1px 2  rgba(24,24,27,0.06), 0 1px 1 rgba(24,24,27,0.04);
$shadow-md: 0 6px 16 -4px rgba(24,24,27,0.10), 0 2px 4 rgba(24,24,27,0.05);
$shadow-lg: 0 16px 40 -8px rgba(24,24,27,0.14), 0 4px 8 rgba(24,24,27,0.06);
$ring:      0 0 0 3px rgba(94,106,210,0.20);
```

---

## Typography scale

| Class | Size | Weight | Tracking | Use |
|---|---|---|---|---|
| `.uk-display` | 40 px | 600 | −0.022em | Marketing only (login aside) |
| `.uk-h1`      | 30 px | 600 | −0.022em | Workspace title |
| `.uk-h2`      | 24 px | 600 | −0.012em | **Page title** (default) |
| `.uk-h3`      | 20 px | 600 | −0.012em | Drawer title, section heads |
| `.uk-h4`      | 16 px | 600 | normal   | Card titles |
| `.uk-h5`      | 14 px | 600 | normal   | Column heads |
| `.uk-body`    | 15 px | 400 | 1.55 lh  | Default body |
| `.uk-body-sm` | 13 px | 400 | 1.5 lh   | Tables, cards |
| `.uk-caption` | 12 px | 400 | 1.4 lh   | Captions, metadata |
| `.uk-overline`| 11 px | 600 | 0.08em uppercase | Section labels |
| `.uk-mono`    | 12 px | 400 mono | 0.01em | IDs, code |

Base body is **15 px** (up from 14 px). Apply `font-feature-settings: 'cv11', 'ss01', 'ss03'` on `<body>` for Inter's cleaner numerals + `i`/`l` shapes.

---

## Components

### Buttons (`.btn`, variants + sizes)

**Variants**: `--primary`, `--secondary`, `--ghost`, `--danger`, `--danger-ghost`
**Sizes**: `--xs` (22 px), `--sm` (26 px), `--md` (30 px default), `--lg` (36 px), `--xl` (44 px)
**Modifiers**: `--icon` (square, no text)

Anatomy:
- 5 px border-radius, 1 px transparent border on all variants (avoids 1 px jumps between primary/secondary)
- gap: 6 px between icon + label
- font-size scales with size token (xs=11 / sm=12 / md=13 / lg=14 / xl=15)
- focus: `box-shadow: var(--ring)`, no outline
- Primary: `bg = --uk-accent`, `color = white`; hover → `--uk-accent-hover`; active → `--uk-accent-active`
- Secondary: `bg = white`, `border = --uk-border-strong`; hover → `bg = --uk-surface-2`
- Ghost: transparent, `color = --uk-fg-muted`; hover → `bg = --uk-surface-2`, `color = --uk-fg`
- Danger: solid red `--uk-danger`
- Danger-ghost: transparent with red text; hover → `bg = --uk-danger-soft`

Implementation: rewrite `.btn-*` rules in `frontend/src/styles.scss`. Keep `<button class="btn btn-primary">` markup pattern — just modernize the rules.

### Form fields

- All controls **30 px tall** by default (was inconsistent before).
- Input/textarea/select share `.input`, `.textarea`, `.select` classes.
- Border: 1 px `--uk-border-strong`; hover → `#b8b8be`; focus → `--uk-border-focus` + 3 px indigo ring.
- Label: 12 px / 500 weight, 5 px gap from control.
- Hint: 11 px `--uk-fg-subtle`.
- Error: red border + red 18% ring; hint goes `--uk-danger`.
- Custom checkbox (15×15 px, 3 px radius) and radio — hide native input, render `.uk-check-box` / `.uk-radio-box` via CSS. See `tokens.css` for exact selectors.
- `<select>` uses CSS background-image chevron (already in tokens.css, copy verbatim).
- Input-group composes prefix/suffix slots (used for `mskopal/` prefix, search icon, calendar icon).
- Toggle (28×16 px pill) for boolean prefs; not a replacement for checkboxes.

### Badges & status pills

| Class | Use |
|---|---|
| `.uk-badge` | Default neutral count chip (18 px tall, 11 px text) |
| `.uk-badge--outline` | Subtle pill on white |
| `.uk-badge--accent` | Indigo soft fill |
| `.uk-badge--success/--warn/--danger/--info` | Semantic |
| `.uk-badge--ai` | **MCP/agent purple** — always pairs with sparkle icon |
| `.uk-badge--solid` | Near-black solid (rare; "private", "Urgent") |

Workflow status uses **dot + label** instead of a chip — 8 px circle + plain text. Dot color comes from `--uk-status-*` tokens. (The current `status.color` field on the entity should map to these tokens; if a custom color is set, render it directly.)

Priority uses **filled chips**: low (gray), medium (amber), high (red), urgent (near-black solid).

### Cards & surfaces

`.uk-card` = white, 1 px `--uk-border`, 7 px radius, no shadow. Composes with `.uk-list` (children separated by 1 px hairlines) and tables.

### Lists & rows

`.uk-row` is the universal interactive list row: 8 px vert / 12 px horiz padding, 36 px min height, hover `bg = --uk-surface-2`, optional `--selected` (indigo soft bg).

### Tables (`.uk-table`)

- Header: 11 px uppercase 0.06em tracking, `--uk-fg-subtle`, `bg = --uk-surface-2`, 1 px bottom border.
- Cell: 8/12 px padding, 13 px text, 1 px bottom hairline.
- Row hover: cells get `bg = --uk-surface-2` (whole row tints).
- Used for both **Projects list** and **Workspace tasks grid**.

### Toasts & alerts

- `.uk-alert` — bordered tinted panel; variants `info / success / warn / danger / ai`. Icon-left, title + body two-line layout.
- `.uk-toast` — solid near-black floating notification, 280 px min width, large shadow. Used for transient confirmations.

### Task card (Kanban) — anatomy

```
┌──────────────────────────────────┐
│ UKO-318  [agent?]      [High]    │ ← ID (mono 11), optional AI badge, priority chip
│ Migrate sessions to Redis        │ ← title 13/500/-0.005em
│ Optional description, 2-line     │ ← 12 px muted, 2-line clamp
│ 📅 May 22  ·  (MS)               │ ← meta row 11 px subtle + 18 px avatar
└──────────────────────────────────┘
```

- 10 px / 11 px padding, 5 px radius, 1 px border.
- **Agent-created tasks** get a 2 px left border in `--uk-ai` and an `.uk-badge--ai` "agent" pill — this is the single most important new affordance.
- **Overdue tasks** have due-date row in `--uk-danger` with `· overdue` suffix.
- Hover: border → `--uk-border-strong`. No shadow.
- Dragging: 1.2° rotation, `--shadow-lg`. (Wire into `cdk-drag-preview`.)

---

## Application screens

Each screen is implemented as a full prototype artboard in `Kytario Design System.html`. Open the file and zoom into the matching artboard to read exact spacing/copy.

### 1. Login

Split layout, 56% form / 44% dark aside.

- **Form side** (white, `--uk-bg`): centered 320 px column. Brand mark + wordmark at top. `h2` "Sign in" + caption "to your workspace". Reactive form: email + password (with "Forgot?" link as right-aligned label slot). Primary `xl` button "Continue". OR divider. Secondary `xl` "Continue with SSO".
- **Aside** (`#18181b` solid): 40 px padding, overline "MCP-native", display-size headline, body copy at 14/1.6 muted. Bottom: a config snippet card in `#23232a` with JetBrains Mono code showing the MCP URL — sells the product as agent-first the moment you see the login.

### 2. Topbar shell (every authenticated page)

48 px tall, white, 1 px bottom border. Left → right:
- Brand mark (22 px) + "kytario" wordmark
- 1 px divider
- Workspace switcher — small `--ghost` button: 14 px colored square w/ workspace initial + "mskopal" + chevron
- Nav links: Projects · Tasks · **Agents** · Workspaces (Agents is new — this is where the activity log lives)
- Spacer
- Search field: 220 px, 26 px tall, "Search or jump to…" placeholder + `⌘K` kbd
- AI sparkle icon-button (opens command palette in agent mode)
- 24 px user avatar

### 3. Projects list

Table-based, not a card grid. Columns: Project (dot + name + description) · Open count · Done · 7d (number + tiny progress bar) · Members (overlapping avatars) · Target date · row menu.

Above the table: **agent-activity strip** (`.uk-alert--ai`) summarizing what agents did in the last 5 minutes with a "View activity" CTA — sets the tone that this is an agent-driven product.

### 4. Kanban board

- Project header bar (white, 1 px bottom border): breadcrumb "Projects → Backend rewrite", h2 title, version badge, "N agents active" purple badge, view-switcher segmented control (Board · List · Workflow · Events), Filter + New task buttons.
- Body: horizontal scroll of 280 px columns, 12 px gap.
- Column header: 8 px dot, name 13/600, count 11 px, "+" icon-button. No background fill on columns — let cards float on the page bg. (Departure from current design where columns have `surface-muted` fill — this looks cleaner.)
- Cards stack with 8 px gap. Bottom of each column: ghost "+ Add task" button.

### 5. Task detail drawer

560 px wide, slides in from right, dimmed backdrop (`rgba(24,24,27,0.32)`).

- **Header** (48 px): `UKO-318` mono ID · 1 px divider · status pill button (dot + name + chevron) · spacer · "..." menu · close (X).
- **Body**:
  - Inline-editable title (h3 20/600/-0.012em). No border, no padding — just type to edit.
  - Meta grid: 2 cols, 90 px label / 1 fr value. Rows: Assignee, Priority, Due date, Workflow, Version (any custom fields). Values are ghost buttons → open inline popovers.
  - Description: overline + Edit/Preview tabs. Bordered panel.
  - Activity feed: timeline of avatars + actions. **Agent actions show purple sparkle avatar + client name (e.g., "Claude (claude-sonnet-4-5)" or "Cursor")**. Comments render in surface-muted bubbles.
  - Comment composer at the bottom of the feed.
- **Footer** (48 px, 1 px top border): Danger-ghost "Delete" left · Cancel + primary "Save changes" right.

### 6. Workspace tasks grid

Standard data table from the system, plus a **dedicated filter bar** above:
- Search input (240 px / 26 px)
- 1 px divider
- "+ Status" ghost button with dashed border (the "add filter" affordance)
- Active filter chips with X buttons (Status, Priority, Created-by-agent — purple chip)
- Group-by dropdown on the far right

Columns: checkbox · ID (mono, with optional sparkle prefix for agent-created) · Task · Project · Status (dot + label) · Priority (chip) · Assignee (20 px avatar) · Due (red if overdue) · row menu.

Pagination row below the card: "11 of 128" + Previous/Next.

### 7. Agent activity log (`/agents`)

The hero feature. New top-level route.

- Page title "Agent activity", caption with date.
- **4-up KPI strip**: Events 24h · Active agents · Tasks created · Tasks closed. Each card: overline label, 22 px bold number, colored sub-line.
- Pill-shaped filter chips (24 px tall, pill radius): All · Humans · Agents · Comments · Status changes. Selected = solid near-black.
- Card with timeline rows: `HH:MM` mono timestamp · avatar · actor name · action verb · target (mono ID chip) · second-line project + "via MCP" purple badge for agents. Comments render in muted bubbles inline.

### 8. Workspace management

Tabbed (General · **Members** · MCP & agents · Custom fields · Billing), Members shown by default.

Two-column grid:
- **Left, wider**: Members card. Filter input + Invite button in header. List of avatar + name/email/last-active + role dropdown + row menu.
- **Right**: **MCP clients** card. Each row: AI sparkle avatar + client name + model/created/last-used + row menu. Footer with active count and monthly call count.
- **Full-width bottom card**: Transfer ownership. 3-col layout: description / form / actions (with red "Transfer ownership" danger button).

---

## Interactions & behavior

| Interaction | Spec |
|---|---|
| Hover (rows, cards, buttons) | 80 ms ease on `background`, `border-color`, `color`, `box-shadow` |
| Focus | Indigo 3 px ring (`var(--ring)`); never outline. Visible on all interactive elements. |
| Kanban drag | Card rotates −1.2°, gets `--shadow-lg`. Placeholder is 40% opacity. CDK transition: `200ms cubic-bezier(0,0,0.2,1)`. |
| Drawer open/close | Slide-from-right transform 200 ms ease-out. Backdrop fades. ESC closes; click backdrop closes. |
| Toast lifetime | 4 s, then fade-out 150 ms. Click body to dismiss; click "Undo" to revert action. |
| Inline title edit (drawer) | Click title → contenteditable; save on blur or ⌘+Enter; revert on ESC. |
| Filter chips | Click X removes filter immediately; saves to URL query params for shareable filtered views. |
| Status dropdown (drawer header) | Opens popover with the project's workflow statuses; each row = dot + name. Click changes status, emits event, closes popover. |
| Workspace switcher | Click pill → dropdown with all workspaces + "Create workspace" + link to /workspaces. Active workspace gets a checkmark. |
| Agent badge tooltip | Hover "agent" badge → "Created by Claude (claude-sonnet-4-5) · 4h ago" tooltip. |

## Responsive behavior

The current app is desktop-first; keep it that way. Minimum supported width: **1024 px**. The kanban board can scroll horizontally below 1280 px. Drawer reduces to 480 px below 900 px, becomes full-screen below 640 px.

## i18n

**Every string** must go through the `translate` pipe. Add keys under `app.designsystem.*` only where new (filter chip labels, agent log copy). Most existing keys (`app.actions.save`, `app.priority.High`, etc.) already exist and should be reused. Add Czech translations for any new keys.

Names of people in mocks (Marek Skopal, Jakub Kostka, Eva Pokorna) are placeholders — render real workspace members.

## Linting & quality bar

- `pnpm run lint` must pass with zero warnings.
- No raw hex colors in SCSS — everything via `$color-*` tokens.
- No `:host ::ng-deep` except for CDK drag visuals (existing pattern).
- All form fields: reactive forms with explicit `formControlName`.
- Accessibility: visible focus ring on every focusable element; `aria-label` on icon-only buttons; semantic HTML (`<table>`, `<th>`, `<tbody>` — already correct in the prototype).

## Files in this bundle

| File | What it is |
|---|---|
| `Kytario Design System.html` | **The canvas.** Open this in a browser to see every artboard. |
| `tokens.css` | All CSS variables (`--uk-*`). Use as the source of truth for hex values. Port to `_variables.scss`. |
| `foundations.jsx` | React components rendering the Brand, Color, Typography, Spacing/Radius/Shadow artboards. |
| `components.jsx` | React components rendering Buttons, Forms, Badges/Alerts, Lists/Tables, Task cards. |
| `screens.jsx` | React components rendering all 7 application screens (Login, Projects, Kanban, Drawer, Tasks grid, Activity log, Workspace mgmt). |
| `design-canvas.jsx` | The starter canvas chrome (pan/zoom/focus). Not relevant to the implementation — it's just the presentation shell. |

## Suggested implementation order

1. **Tokens first.** Rewrite `frontend/src/styles/_variables.scss` from the token table above. Load Inter + JetBrains Mono. Update `styles.scss` globals (h1–h5, .btn, .input, .field, .card, .uk-table, .uk-row, .uk-badge, .uk-alert, .uk-check, .uk-toggle, .uk-avatar, .uk-mono, .uk-kbd). Reference `tokens.css` cell-by-cell.
2. **Layout shell** — re-skin `layout.component`. Adds Agents nav link (new route).
3. **Task card** — small surface area, huge perception bump. Implement agent-variant + overdue states.
4. **Kanban board** — remove column fill, add view-switcher + agent count badge.
5. **Task drawer** — rebuild from scratch; existing markup is the smallest fit.
6. **Tasks grid + filter bar** — add filter chip system (new component, likely `<uk-filter-chip>`).
7. **Projects list** — table; reuse `.uk-table` styles.
8. **Activity log** — new route `/agents`. New component.
9. **Workspace settings** — tab into the existing workspaces module, add MCP clients panel (this is a new backend area — coordinate).
10. **Login** — split layout, dark aside. Last because lowest traffic.

## Open questions for the dev

- The new system removes column background fills on the kanban — confirm with the team that this is wanted before shipping.
- "Agents" as a top-level nav vs. a tab inside each project — current design has it both ways (project-level Events tab + workspace-level Agents page). Decide which is primary.
- The MCP clients panel implies new backend endpoints (`/api/workspaces/:id/mcp-clients`). Confirm with backend before wiring up.
- The 2px left bar on agent-created tasks needs a stable rendering rule — recommend a `data-agent="true"` attribute on the task element and a `[data-agent="true"]` SCSS selector rather than `*ngIf`-ed class strings.

---

Questions, gaps, or "the prototype contradicts the README"? The HTML canvas is the source of truth.
