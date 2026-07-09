# JUANET EOS: Search Engine Optimization (SEO) & Metadata Architecture

This document outlines the SEO strategy, semantic metadata, structured schemas, and indexing guidelines implemented to optimize the search visibility of **JUANET EOS**.

---

## 📈 1. Metadata Schema Definitions

All public pages include optimized HTML title and meta tags to maximize click-through rates (CTR) on search engine results pages (SERPs):

```html
<!-- Example of standard semantic metadata header -->
<title>JUANET EOS - Enterprise SaaS ERP for Modern Operations</title>
<meta name="description" content="Manage CRM, financial ledgers, workforce planning, and proposals from a single, high-performance workspace." />
<meta name="robots" content="index, follow" />
<link rel="canonical" href="https://juanet.cloud" />
```

### Page Title and Description Specifications
1.  **Homepage**:
    *   *Title*: JUANET EOS — Enterprise SaaS ERP for Modern Operations
    *   *Meta Description*: Manage CRM pipelines, double-entry financial ledgers, workforce assignments, and contracts from a single, high-performance workspace.
2.  **Pricing Page**:
    *   *Title*: Pricing Packages & Subscription Plans — JUANET EOS
    *   *Meta Description*: Find the perfect SaaS plan for your business. Transparent pricing, secure databases, and zero-downtime performance.
3.  **Quote Request Wizard**:
    *   *Title*: Request an Operational Quote — JUANET EOS
    *   *Meta Description*: Use our AI-assisted scoping wizard to generate a detailed project proposal and pricing estimate in minutes.

---

## 🔗 2. OpenGraph & Twitter Card Configurations

To ensure our content is beautifully represented when shared on social channels, all public pages include structured OpenGraph metadata tags:

```html
<!-- Facebook OpenGraph Cards -->
<meta property="og:type" content="website" />
<meta property="og:url" content="https://juanet.cloud" />
<meta property="og:title" content="JUANET EOS — Enterprise SaaS ERP for Modern Operations" />
<meta property="og:description" content="Manage CRM, financial ledgers, workforce planning, and proposals from a single, high-performance workspace." />
<meta property="og:image" content="https://juanet.cloud/images/og-main-banner.png" />

<!-- Twitter Social Cards -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:site" content="@juanetsolutions" />
<meta name="twitter:title" content="JUANET EOS — Enterprise SaaS ERP for Modern Operations" />
<meta name="twitter:description" content="Manage CRM, financial ledgers, workforce planning, and proposals from a single, high-performance workspace." />
<meta name="twitter:image" content="https://juanet.cloud/images/og-main-banner.png" />
```

---

## 🤖 3. Crawling & Indexing Control

### I. Robots.txt Specification (`/robots.txt`)
```txt
# robots.txt for JUANET EOS
User-agent: *
Allow: /
Disallow: /api/
Disallow: /admin/
Disallow: /client-portal/
Disallow: /employee/
Disallow: /storage/framework/

Sitemap: https://juanet.cloud/sitemap.xml
```

### II. Dynamic XML Sitemap Structure (`/sitemap.xml`)
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://juanet.cloud/</loc>
    <lastmod>2026-07-09</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://juanet.cloud/about</loc>
    <lastmod>2026-07-09</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://juanet.cloud/pricing</loc>
    <lastmod>2026-07-09</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
  </url>
</urlset>
```
---

## 📊 4. Structured Data Schema (JSON-LD)

To help search engines index and display our company and software information, we embed structured JSON-LD schemas:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "JUANET EOS",
  "operatingSystem": "All",
  "applicationCategory": "BusinessApplication",
  "offers": {
    "@type": "Offer",
    "price": "50000.00",
    "priceCurrency": "KES"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.9",
    "ratingCount": "148"
  }
}
</script>
```
