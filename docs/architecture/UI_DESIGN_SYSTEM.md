# JUANET EOS: Enterprise Design System

This document outlines the reusable **Enterprise Design System** of the **JUANET Enterprise SaaS Platform** (JUANET EOS). It serves as a single source of truth for engineering and design teams to build cohesive, high-performance, accessible, and responsive user interfaces.

---

## 🎨 1. Color System (Space Slate Design Language)

The platform utilizes the **Space Slate** palette, prioritizing a refined, high-contrast, professional, and accessible visual theme.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        PRIMARY WORKSPACE COLORS                        │
├─────────────────┬─────────────────┬──────────────────┬─────────────────┤
│ Slate Background│ Panel Slate     │ Accent Teal      │ Accent Cyan     │
│ #090D16         │ #0F172A         │ #14B8A6          │ #06B6D4         │
│ (slate-950)     │ (slate-900)     │ (teal-500)       │ (cyan-500)      │
└─────────────────┴─────────────────┴──────────────────┴─────────────────┘
```

### Color Contrast Ratios (WCAG 2.2 AA Compliance)
*   **Body Text**: Soft Grey (`#E2E8F0` on `#090D16` / `#0F172A`) - Contrast ratio: **13.5:1** (Exceeds AA standard of 4.5:1).
*   **Muted Text**: Grey-blue (`#94A3B8` on `#0F172A`) - Contrast ratio: **6.2:1** (Exceeds AA standard of 4.5:1).
*   **Accent Links**: Teal (`#2DD4BF` on `#0F172A`) - Contrast ratio: **7.1:1** (Exceeds AA standard of 4.5:1).

---

## 🔠 2. Typography System

The design system pairs structural Sans-Serif fonts with Tech-Mono accents to reinforce legibility and a modern, high-tech tone.

### Font Families
1.  **Display Font**: `Space Grotesk` (sans-serif) — Used for primary headings, callouts, hero banners, and title sections.
2.  **UI/Body Font**: `Inter` (sans-serif) — Clean, legible, and optimized for data density and mobile viewports.
3.  **Data/Code Font**: `JetBrains Mono` (monospace) — Used for ledger statistics, prices, timestamps, and tables.

### Typography Hierarchy Scale
*   **H1 (Hero Heading)**: `Space Grotesk`, `text-4xl md:text-5xl font-bold tracking-tight text-white`
*   **H2 (Section Heading)**: `Space Grotesk`, `text-2xl md:text-3xl font-semibold tracking-tight text-white`
*   **H3 (Component Header)**: `Space Grotesk`, `text-lg md:text-xl font-medium text-slate-100`
*   **Body Text**: `Inter`, `text-sm md:text-base text-slate-300 leading-relaxed`
*   **Data Label**: `JetBrains Mono`, `text-xs uppercase tracking-wider text-slate-500`
*   **Data Value**: `JetBrains Mono`, `text-sm font-semibold text-cyan-400`

---

## 🧱 3. Visual Components Reference

### A. Buttons
```html
<!-- Primary Accent Button -->
<button class="px-5 py-2.5 bg-teal-500 hover:bg-teal-400 active:scale-98 text-slate-950 text-sm font-sans font-medium rounded-lg shadow-lg shadow-teal-500/10 transition-all duration-200">
  Get Started
</button>

<!-- Secondary Ghost Button -->
<button class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 active:scale-98 text-slate-300 hover:text-white text-sm font-sans font-medium rounded-lg transition-all duration-200">
  Learn More
</button>
```

### B. Inputs & Form Fields
```html
<!-- Text Input Field -->
<div class="space-y-1.5">
  <label class="block text-xs font-mono uppercase tracking-wider text-slate-400">Company Name</label>
  <input type="text" placeholder="e.g. Acme Corp" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-800 hover:border-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 rounded-lg text-slate-100 text-sm font-sans placeholder:text-slate-600 outline-none transition-all duration-200" />
</div>
```

### C. Information Cards
```html
<!-- Metric Card -->
<div class="p-6 bg-slate-900 border border-slate-800 rounded-xl relative overflow-hidden group">
  <div class="absolute inset-0 bg-gradient-to-r from-teal-500/5 to-cyan-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
  <div class="flex justify-between items-start">
    <span class="text-xs font-mono uppercase tracking-wider text-slate-500">Monthly Revenue</span>
    <span class="text-emerald-400 text-xs font-sans bg-emerald-500/10 px-2 py-0.5 rounded">+12.3%</span>
  </div>
  <div class="mt-4 text-2xl font-mono font-bold text-white">KES 1,245,300</div>
</div>
```

---

## 🛡️ 4. Accessible & Responsive Layout Principles
1.  **Flexible Grid Sizing**: Always use responsive layouts (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3`) to adapt fluidly across devices.
2.  **Minimum Touch Targets**: Keep all interactive buttons, checkboxes, and menu anchors above `44px` in height and width on touchscreens.
3.  **Aria Landmark Compliance**: Structure views using standard landmarks (`<main>`, `<nav>`, `<aside>`, `<footer>`) to maintain accessible screen reader structures.
