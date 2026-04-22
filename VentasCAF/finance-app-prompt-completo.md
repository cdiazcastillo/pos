# FINANCE APP — PROMPT DE DISEÑO COMPLETO
> Versión final unificada — Todos los estándares incluidos

---

## 🎨 ESTILO VISUAL GENERAL

Design a modern mobile finance app with a clean, soft UI style.
The visual language must be friendly, approachable and consistent
across every single screen — no exceptions.

---

## COLOR PALETTE

- Primary: Purple/Violet (#7C3AED) for headers, top bars and CTAs
- Background: Pure white (#FFFFFF) for all cards and content areas
- App background: Soft lavender (#F3F0FF) behind all cards
- Accent colors: Coral/red, teal/green, blue and pink — used ONLY
  for charts, progress bars and category color indicators
- Text primary: Dark navy (#1E1B4B) for all headings and key figures
- Text secondary: Medium gray (#6B7280) for subtitles and labels
- Negative amounts: Orange/red with coin icon
- Positive amounts: Black or dark navy

---

## TYPOGRAPHY

- Font family: Poppins (preferred) or SF Pro — ONE font, used everywhere
- Large bold numbers for key financial figures ($450, $2.570)
- Small uppercase or medium-weight labels for categories and sections
- Never mix font families across screens
- Font scale must be consistent: same H1, H2, body, label sizes on all pages

---

## CARDS & LAYOUT

- All cards: rounded corners with border-radius strictly between 20–24px
- All cards: soft drop shadow — no hard borders
- White card surfaces floating over the lavender background
- Generous padding inside every card: 16–20px minimum
- Breathable spacing between sections: never cramped, never too loose
- Consistent grid/column system across all screens

---

## UI COMPONENTS

- Donut/pie chart: multi-color segments, total amount centered inside
- Line chart: smooth curve with soft gradient fill underneath
- Progress bars: rounded, thin (6–8px height), color-coded per category
- Toggle pill buttons: soft rounded switcher (e.g. Categories / Nature)
- Floating action button (+): dark navy circle, centered in bottom nav,
  identical size and position on every screen
- Bottom navigation: icon + label, minimal clean style, same on all pages
- Avatar/profile photo: circular frame, top left of header
- Month navigator: arrow chevrons (< Aug 2023 >) — consistent placement

---

## ICONS (STRICT RULE)

- ZERO emojis anywhere in the app — not in lists, not in categories
- ZERO filled icon sets
- Use ONLY clean minimal line icons (stroke style, 1.5px weight)
- Consistent icon library: Lucide Icons, Feather Icons or Heroicons — pick ONE
- Icons must be monochromatic or use a single soft color matching the card accent
- Icons must be small and subtle — never the focal point of a list item

---

## TRANSACTION LIST ITEMS

- Clean line icon on the left (no emojis)
- Title + subtitle in two lines
- Amount right-aligned: negative in orange/red with icon, positive in dark navy
- Consistent row height and padding across all transaction lists

---

## VISUAL MOOD

- Friendly, approachable — not corporate or cold
- Pastel base with vibrant accents on data elements
- No hard borders — rely on shadows and spacing for separation
- iOS-inspired aesthetic with smooth curves everywhere
- Avoid generic, AI-default aesthetics — the design must feel intentional

---

## RESPONSIVE DESIGN (MANDATORY — DO NOT SKIP)

Every component and every screen MUST be fully responsive.
This is not optional. Responsive behavior is part of the design.

- Breakpoints to support: 320px / 375px / 768px / 1280px+
- Use relative units ONLY: rem, %, vw/vh — never fixed px for layout
- All layouts built with Flexbox or CSS Grid — no absolute positioning
  for structural elements
- Cards must reflow and stack vertically on small screens
- Charts must resize fluidly without cutting off labels or data
- Bottom navigation must adapt gracefully on larger screens
- Touch targets minimum 44x44px on all interactive elements
- Zero horizontal scroll at any breakpoint
- Test EVERY screen at EVERY breakpoint before marking it done

---

## PRE-DELIVERY TECHNICAL VALIDATION (REQUIRED)

Before delivering anything, run and pass ALL of the following:

- Lighthouse audit — minimum scores required:
    Performance:    90+
    Accessibility:  90+
    Best Practices: 90+
- W3C HTML Validator — zero critical errors allowed
- Chrome DevTools responsive test at 320px, 375px, 768px and 1280px
- Color contrast ratio: WCAG AA minimum (4.5:1) on all text
- Confirm zero layout overflow at any breakpoint
- Confirm zero horizontal scroll at any breakpoint

Do NOT deliver any screen until all validations pass.

---

## STYLE CONSISTENCY AUDIT (ZERO TOLERANCE — MANDATORY ON EVERY PAGE)

BEFORE writing code for a new page: re-read the style rules above.
AFTER finishing each page: run the full checklist below.
This checklist is not optional. It is not skippable under any circumstance.

### CHECKLIST PER PAGE (must be 100% before moving on)

[ ] Color palette matches EXACTLY: purple primary, white cards,
    lavender background — no improvised colors
[ ] Every card has border-radius between 20–24px and soft shadow
[ ] ONE font family used consistently — zero exceptions
[ ] ZERO emojis, ZERO filled icons — only line icons (Lucide/Feather)
[ ] Spacing and padding follow the same scale on every element
[ ] Bottom navigation is pixel-identical across all screens
[ ] Floating action button (+) is identical in size, color and position
[ ] All text contrast passes WCAG AA (4.5:1 minimum)
[ ] Page is fully responsive at 320px, 375px, 768px and 1280px
[ ] Zero horizontal scroll at any breakpoint

### STRICT ENFORCEMENT RULES

→ If even ONE checkbox is unchecked: STOP. Fix it immediately.
   Re-run the full checklist from the beginning for that page.
→ Do NOT move to the next page until current page is 100% PASS.
→ Do NOT group fixes at the end — fix page by page, immediately.
→ Do NOT assume a page is correct because it "looks similar" —
   run the checklist every time, no exceptions, no shortcuts.
→ Inconsistency between screens = delivery REJECTED.

---

## FINAL DELIVERY REQUIREMENT

You MUST include a Style Audit Report in this exact format:

═══════════════════════════════════
PAGE AUDIT REPORT
═══════════════════════════════════

Page 1 — Dashboard
  Checklist items passed: 10/10
  Issues found: None
  Responsive validated: ✅
  Lighthouse passed: ✅
  Status: APPROVED ✅

Page 2 — Transactions
  Checklist items passed: 10/10
  Issues found: None
  Responsive validated: ✅
  Lighthouse passed: ✅
  Status: APPROVED ✅

Page 3 — Spending Trends
  Checklist items passed: 10/10
  Issues found: None
  Responsive validated: ✅
  Lighthouse passed: ✅
  Status: APPROVED ✅

(repeat for every page in the app)

───────────────────────────────────
GLOBAL RESULT: ALL PAGES APPROVED ✅
═══════════════════════════════════

If GLOBAL RESULT is not "ALL PAGES APPROVED ✅"
→ The delivery is REJECTED. Fix all failing pages and resubmit.
→ Do not deliver partial results.
→ Do not ask the user to accept incomplete work.

---
*End of prompt — No section may be ignored or partially applied*
