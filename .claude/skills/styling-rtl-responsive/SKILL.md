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

Use semantic Tailwind tokens, not hardcoded colors:
- `bg-background`, `bg-card`, `bg-muted`, `bg-primary`, `bg-accent`
- `text-foreground`, `text-muted-foreground`, `text-primary-foreground`
- `border-border`, `border-input`
- **NEVER use `text-white` with `bg-primary`** — use `text-primary-foreground` instead.

## Icons

Use `lucide-vue-next` only. Never import from other icon libraries.

## Layout Consistency

- Pages use `max-w-[1300px]` container.
- Cards use `rounded-3xl border bg-card p-6`.
- Header bars use `rounded-xl border bg-card p-4`.
- Buttons: Edit = yellow outline, Delete = red outline, Create = primary.

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
