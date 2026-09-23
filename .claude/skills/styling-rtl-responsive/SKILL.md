---
name: styling-rtl-responsive
description: "Use whenever writing or reviewing Tailwind classes in this starter's Vue components — RTL/Arabic logical-property rules, semantic theme color tokens, icon library choice, page/card/button layout consistency, and mobile-first responsive breakpoints. Trigger on: any ml-*/mr-*/pl-*/pr-*/left-*/right-* class (these are BANNED, must be logical ms-*/me-*/ps-*/pe-*/start-*/end-*), hardcoded colors instead of bg-card/text-foreground/etc., icon imports, new page/card/modal layout, and 'make this responsive' / mobile-breakpoint work. Do not use for the Vue component patterns themselves (Teleport modals, forms, tables) — see vue-admin-ui-patterns for that."
metadata:
  author: project
---

# Styling, RTL & Responsive Rules

## RTL Support (MANDATORY)

This project supports Arabic (RTL). Use logical properties ONLY:
- `ms-*` / `me-*` instead of `ml-*` / `mr-*`
- `ps-*` / `pe-*` instead of `pl-*` / `pr-*`
- `start-*` / `end-*` instead of `left-*` / `right-*`
- `ltr:` / `rtl:` prefixes when directional behavior differs.

**NEVER use** `ml-*`, `mr-*`, `pl-*`, `pr-*`, `left-*`, `right-*`.

## Theme Colors

Use semantic Tailwind tokens, not hardcoded colors. Every token below is defined for BOTH
light and dark in `resources/css/app.css`; a raw palette class like `bg-red-50` or
`text-emerald-600` has no dark-mode counterpart and renders as a light block on a near-black
card, which is how most of this codebase's dark-mode bugs were born.

- Surfaces: `bg-background`, `bg-card`, `bg-muted`, `bg-popover`, `bg-accent`, `bg-sidebar`
- Text: `text-foreground`, `text-muted-foreground`, `text-primary-foreground`, `text-card-foreground`
- Lines: `border-border`, `border-input`, `ring-ring`
- Intent: `primary`, `destructive`, **`success`**, **`warning`**, **`info`** — each with a matching
  `-foreground`. Use the tone at 10% for a tinted background: `bg-success/10 text-success`.
- **NEVER use `text-white` with `bg-primary`** — use `text-primary-foreground` instead.

Do not hand-roll a status pill out of these; use `<Badge variant="success|warning|destructive|info">`
(see the vue-admin-ui-patterns skill).

## Icons

Use `lucide-vue-next` only. Never import from other icon libraries.

## Layout Consistency

- Page shell: `<div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-6 md:py-8 text-start">`.
  The layout owns the background and page height — do NOT wrap a page in `min-h-[100dvh] bg-background`,
  it overshoots by the height of the top bar.
- Every page opens with `<PageHeader :title="…">` — the `<h1>` does not belong in `*Filters.vue`.
- Cards use `rounded-xl border bg-card p-4 md:p-6`. Modals are `rounded-xl` with `shadow-lg`.
  Retire `rounded-2xl`/`rounded-3xl`; five nesting levels of different radii read as unsystematic.
- Buttons: Create = `<Button>` (primary), Edit = `<Button variant="outline" size="sm">`,
  Delete = `<Button variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10">`.
  Never hand-roll `border-yellow-500 text-yellow-500` — yellow-500 on white is ~1.9:1 and fails
  contrast in the one place it is used most (the Edit button).
- Icon sizes: `size-4` inside buttons, table cells and inputs; `size-5` in nav and section headers;
  `size-6` on stat cards. Prefer `size-4` over `h-4 w-4`.

## Accessibility floor

Non-negotiable, because these were all missing and are cheap:
- Every icon-only button needs an `aria-label`.
- Every `<img>` needs `alt` (`alt=""` when decorative).
- Every form control needs a real label — use `FormField`, which wires `for`/`id` for you.
- Custom toggles need `role="switch"` + `:aria-checked` + an accessible name; disabled state via
  `disabled:` classes, never an inline `style="opacity:0.5"`.
- Any raw `<button>` needs a visible `focus-visible:ring-2 ring-ring ring-offset-2`.
- Interactive rows must be `<button>`/`<Link>`, not `<div @click>`.
- Wrap a `<Link>` in a `<Button>` with `as-child` (`<Button as-child><Link …/></Button>`) —
  `<Link><Button>` renders `<a><button>`, which is invalid and a double tab stop.

## Scroll Cues

A scrolling area whose edges sit under fixed furniture (the navbar's list above its pinned
footer, for example) gets the `scroll-shadows` utility. The shadow shows only at an edge with
content past it and adapts to the theme on its own. No JS, no scroll listener — add the class
and nothing else.

## Scrollbars

Thin, themed scrollbars are global (`resources/css/app.css`) — every scroll container gets
them, so never style a scrollbar per component and never re-add a chunky default. The colour
comes from `--color-primary` and follows the theme, including dark mode, on its own.

- Nothing to add for a normal scrolling area (modal body, `overflow-x-auto` table, dropdown
  panel) — it is already thin.
- `scrollbar-none` (utility) hides the indicator entirely, for a strip the user drags or
  swipes such as a chip row. It only hides the bar; never use it where scrolling is the only
  way to reach the rest of the content.

## Responsive Design (MANDATORY)

All Vue components MUST be responsive and work on all screen sizes.

**Mobile-First Approach:**
- Start with mobile styles, then add larger breakpoint overrides.
- Use Tailwind breakpoints: `sm:` (640px), `md:` (768px), `lg:` (1024px), `xl:` (1280px).

**Grid Layouts:**
```vue
<!-- Single column on mobile, multi-column on larger screens -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
```

**Flex Layouts:**
```vue
<!-- Stack on mobile, row on larger screens -->
<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
```

**Responsive Spacing:**
- Use `p-4 md:p-6` for padding that grows on larger screens.
- Use `gap-3 md:gap-4 lg:gap-6` for responsive gaps.

**Responsive Text:**
- Use `text-sm md:text-base` for body text.
- Use `text-lg md:text-xl lg:text-2xl` for headings.

**Hidden/Visible Elements:**
- Use `hidden md:block` to show elements only on medium+ screens.
- Use `md:hidden` to show elements only on mobile.

**Tables:**
- Tables MUST have horizontal scroll on mobile: `<div class="overflow-x-auto">`.
- Consider card-based layouts for mobile as alternative to tables.

**Forms:**
- Form fields stack vertically on mobile, can be side-by-side on larger screens.
- Buttons should be full-width on mobile: `w-full md:w-auto`.

**Modals:**
- Use `w-full max-w-lg` for responsive modal width.
- Reduce padding on mobile: `p-4 md:p-6`.

**Navigation:**
- Navbar must have mobile menu (hamburger) for small screens.
- Sidebar collapses to icons or hidden on mobile.

**Testing:**
- Always test at 320px (small mobile), 768px (tablet), 1024px (desktop).
- Use browser DevTools responsive mode during development.
