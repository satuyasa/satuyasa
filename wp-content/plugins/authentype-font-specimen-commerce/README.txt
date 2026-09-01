Authentype Font Specimen Commerce 1-46 — secure.8.3.0 Stability Freeze

Stability-only release. No new commerce model or customer-facing feature is introduced.

- Adds Athtyp > Stability runtime diagnostics and downloadable diagnostic report.
- Adds bounded plugin fatal/error history (60 events maximum).
- Adds schema/version bookkeeping with an atomic upgrade mutex; no automatic product migration.
- Adds safe degraded bootstrap when required plugin modules are missing/unreadable instead of loading a partial operational stack.
- Adds cross-engine guards so Secure Assets build, Woo Sync, catalog repair/hydration/reconciliation, pricing saves, and normal commerce saves do not intentionally run over an already-active long mutation.
- Storefront add-to-cart/checkout is temporarily blocked while a Secure Assets build lock is active for that product.
- Adds explicit cleanup for expired plugin locks; active locks are never removed automatically.
- Adds health checks for WooCommerce, ZipArchive, renderer, uploads, PHP memory/execution limits, Woo download method, plugin files, schema, and lock state.
- Woo products, variations, prices, downloads, orders, and customer permissions are never migrated automatically by this release.


secure.8.2.26 — Hydration Safety Snapshot & Blocked Diagnostics
- Creates an append-only, versioned pre-hydration snapshot of the exact Athtyp `_ath_product_downloads` meta value immediately before every Legacy Delivery Hydration write.
- Hydration is blocked if the safety snapshot cannot be persisted. Existing snapshot records are never overwritten; a separate lightweight latest-snapshot pointer is updated for diagnostics/recovery tooling.
- Preview Missing Delivery now lists every blocked Woo product with its product name/ID and server-provided blocking reason(s), instead of showing only an aggregate blocked count.
- Successful hydration rows are labeled “Legacy Delivery Hydrated — Re-audit Required” and include the safety snapshot ID; they are no longer visually presented as Needs Woo Sync.
- Snapshot creation and diagnostics do not change Woo products, variations, prices, downloads, orders, customer permissions, Pricing Matrix, Build Secure Assets, Checkout, Full Glyph, or storefront behavior.

secure.8.2.25 — Legacy Delivery Hydration
- Adds a two-step, catalog-only Preview Missing Delivery → Hydrate Legacy Delivery workflow for already-adopted products.
- Hydration is allowed only when the live Commerce Readiness result is Missing Delivery, exactly one Athtyp record owns the Woo product, Style/License mapping is exact, and every missing live pair has stable/importable Woo download IDs/files.
- Copies only missing Athtyp delivery rows and preserves the existing Woo download ID, name, file URL, Style, License, and legacy_download flag. Existing Athtyp delivery pairs are never appended to or replaced.
- Processes one Woo product per AJAX request with Pause support and revalidates the full candidate after acquiring the adoption mutex before any Athtyp write.
- Does not write Woo products, variations, prices, downloads, orders, customer permissions, Pricing Matrix, Style/License inventory, Build Secure Assets, Checkout, Full Glyph, or frontend code.

= secure.8.2.21 — Adopted Catalog Live Audit =
* Already Adopted Woo products no longer return before Catalog Adoption analyzes their current Woo variation data.
* The catalog reuses the single Woo dataset already loaded for the request and now shows live detected Style/License mapping, style count, license count, variation count, prices, and importable downloads for linked products.
* Previously saved adoption attribute keys are preferred for linked products so later label changes do not erase the mapping display.
* Already Adopted remains read-only and keeps its existing Open Athtyp / Snapshot / Restore workflow; no Woo prices, variations, downloads, orders, Athtyp pricing, Build Secure Assets, checkout, Full Glyph, or storefront behavior is changed.


= secure.8.2.19 — Single-Style Inventory Guard =
* Full Style package/variation is now created only when the master ZIP contains more than one distinct detected style.
* A one-style master ZIP (for example, Black only) produces only that actual style across its available delivery formats; no duplicate Full Style row, Full Style ZIP, manifest row, or Woo variation is generated.
* Multi-style family behavior is unchanged: 2+ detected styles still receive the shared Full Style package.
* No Pricing, Woo sync algorithm, Build Secure Assets extraction rules, checkout logic, Full Glyph renderer, or frontend layout changes.

Version: 1.0.4-secure.8.2.29-woo-source-file-inspector





SECURE.8.2.15 — PRICING & WOO SYNC INTEGRITY
- Blocks stale normal WordPress Update submissions from overwriting newer builder/style/license/pricing/download metadata changed by AJAX asset builds, pricing saves, imports, or sync-side state changes.
- Adds Price Matrix revision concurrency checks to Save Pricing Only and Woo Sync init so two admin tabs cannot silently overwrite/sync against a newer server-side pricing revision.
- Makes the Woo sync mutex product-scoped instead of Athtyp+product-scoped, preventing two Athtyp records from mutating the same Woo product concurrently.
- Adds Woo product ownership protection with backfill from existing `_ath_linked_product` relationships; a product already linked to another Athtyp is blocked before sync mutation.
- Verifies Woo sync transient persistence and stops safely if resumable progress cannot be stored; unchanged transient writes remain valid through read-back verification.
- Handles Woo attribute-creation errors explicitly instead of coercing WP_Error objects into invalid numeric attribute IDs.
- Blocks Woo Sync when purchasable licenses exist but no Regular price is configured anywhere in the Price Matrix.
- Reuses the compatibility-preflight variation scan as the existing-variation lookup, removing the second full child hydration pass during sync initialization.
- Adds strict server validation for negative/non-numeric prices and contradictory Sale values; malformed pricing is rejected instead of silently rewritten.
- Keeps the post-build reload warning visible when Generate Missing Internal Codes is clicked on a stale builder page.
- Zero storefront visual change, zero pricing model change, zero package format change, and no automatic migration of existing variations/files.


SECURE.8.2.14 — FINAL INTEGRITY HARDENING
- Adds a per-Athtyp product mutex around Secure Asset Build so simultaneous tabs/admins cannot commit or roll back the same package files concurrently. Expired locks self-recover.
- Blocks ambiguous Master ZIP inventory when more than one source resolves to the same Style × Format (for example two Bold OTF files). The build reports both sources instead of silently choosing the first.
- Adds selected-variation runtime mirror verification: Woo regular/sale/active price and downloadable file signatures must still match the current Athtyp Price Matrix + secure delivery mapping. Manual Woo drift is blocked at purchasability, native add-to-cart, Athtyp multi-select add-to-cart, and checkout until Woo Sync repairs it.
- Performs conservative post-build cleanup only: stale staging/backup residue in the current secure family directory and the legacy duplicated `woocommerce_uploads/authentype-previews/<Athtyp ID>` tree are removed after a successful build.
- Dynamic Package Inventory, Price Matrix workflow, Woo variation model, renderer, frontend UI, Catalog Adoption, and normal package outputs are unchanged.


SECURE.8.2.13 — WOO ACTIVE PRICE MIRROR
- Fixes a commerce regression introduced with the 7.3.6 Price Matrix mirror: Woo regular/sale prices were synced, but the separate active price (`_price`) was not always updated.
- Woo Sync now explicitly mirrors regular, sale, and active price. Active price is Sale when valid, otherwise Regular.
- Woo sync signature schema bumped so products synced by older builds show Needs sync and are repaired before storefront purchase.
- Add-to-cart diagnostics now distinguish incomplete active-price mirror, missing delivery, and unpublished variation without changing normal checkout behavior.
- No Package Builder, Price Matrix authority, download mapping, renderer, Adoption, or storefront visual changes.

SECURE.8.2.12 — PRICING STATE GUARD
- Prevents a stale or absent Price Matrix from being saved as an empty matrix after Secure Asset Build.
- Adds a Style/License schema signature to Pricing Save and Woo Sync init; server rejects stale admin pages with a reload instruction.
- Locks Save Pricing Only and Woo Sync in the current browser page immediately after a successful asset build until reload.
- Pricing status now reports Not configured when the matrix is empty instead of implying pricing is already configured.
- No package, pricing authority, Woo variation architecture, checkout, renderer, adoption, or storefront changes.

SECURE.8.2.11 — PACKAGE COMMIT FIX
- Built directly on secure.8.2.10 Dynamic Package Inventory.
- Fixes the metadata round-trip verification bug introduced with secure.7.3.6 Asset / Pricing Separation: WordPress returns numeric post-meta scalars as strings, so `_ath_asset_built_at` could be written successfully and then falsely fail strict type-sensitive verification.
- Package metadata verification now normalizes only scalar storage semantics (integer/float/boolean/null) while arrays and structured metadata remain strictly compared through serialization.
- Real metadata write mismatches still trigger the existing file + metadata rollback safety path.
- Failed verification now carries the exact meta key in WP_Error data and writes that key to the PHP error log only when WP_DEBUG is enabled.
- Dynamic Package Inventory, nested Master ZIP ingestion, Price Matrix separation, Woo Sync, checkout, renderer, Catalog Adoption, frontend UI, and existing download IDs are unchanged.


SECURE.8.2.10 — DYNAMIC PACKAGE INVENTORY
- Built directly on secure.8.2.9 Upload Ingestion Compatibility.
- Package generation is now driven by the actual styles detected in the Master ZIP; there is no longer a 1-style-versus-family threshold that disables the package bundle.
- A Full Style package is generated for every valid detected inventory, including a one-style product, restoring the package behavior that existed before secure.7.3.6 removed single-style package ZIP creation.
- Any style count is supported by the same path: 1, 2, 3, 5, 9, 18, or more detected styles are treated as inventory rather than product-type assumptions.
- License extension rules act as filters over files that actually exist. Package signatures and ZIP reuse are based on the real selected formats, so an OTF-only master does not create a misleading OTF-TTF package name.
- Individual Style Woo downloads intentionally remain the existing protected shared font assets. They are not converted to new ZIP download IDs, protecting existing order/download history and preserving the proven secure.8.2.4 commerce contract.
- Pricing remains fully separated from asset/package generation. Price Matrix changes never rebuild packages.
- Existing flat/folder/nested-one-level Master ZIP ingestion from secure.8.2.9 is retained unchanged.
- No changes to checkout, Woo batch-sync architecture, Price Matrix authority, renderer, Catalog Adoption, frontend UI, or theme typography.

SECURE.8.2.9 — UPLOAD INGESTION COMPATIBILITY
- Built directly on secure.8.2.8 Stable Recovery / secure.8.2.4 commerce workflow baseline.
- Family ZIP ingestion now supports three compatible layouts: direct font files, fonts inside folders, and one level of nested Single Style ZIP archives.
- Nested ZIPs are scanned in a preflight pass before secure assets are written; invalid archives fail with a clear error instead of silently disappearing from the family.
- Nested archives are materialized only to private system temporary storage, chmod 0600 where supported, and deleted immediately after scanning/build use. No archive paths are blindly extracted.
- ZIP bomb protections also apply to nested archives: archive count/size, child entry count, font entry size, combined expanded font size, and compression-ratio ceilings.
- Deeper ZIP recursion is never followed. A child ZIP that only contains another ZIP is rejected with an explicit one-level-depth error.
- Existing flat/folder Family ZIP behavior remains compatible. Direct fonts and existing Package Builder output/commerce contracts are unchanged.
- Build success reports how many nested Single Style ZIP archives were actually used.
- No changes to Price Matrix, Woo Sync, checkout, renderer, Catalog Adoption, frontend UI, licensing behavior, or the secure.8.2.4 commerce guards.

SECURE.8.2.8 — STABLE RECOVERY
- Recovery branch rebuilt directly from the proven secure.8.2.4 Final Commerce Safety backend/workflow baseline.
- Reverts the Resource & Cache Hygiene backend/state changes introduced in secure.8.2.5.
- Reverts all Step 3 Build Integrity & Preview Sync workflow/data-contract changes introduced in secure.8.2.7.
- Restores the original Build Secure Assets → Save Pricing Only → Woo Sync flow from secure.8.2.4 without build-revision reload gates or strict per-license format completeness blocking.
- Restores the secure.8.2.4 Package Builder, Woo Sync state, cart guards, render backend, metadata persistence, and Catalog Adoption behavior byte-for-byte.
- Keeps only the secure.8.2.6 frontend typography/color inheritance CSS so the specimen UI follows the active theme font and text color.
- Existing secure.8.2.2 button polish and secure.8.2.3 price spacing remain because they were already part of the secure.8.2.4 baseline.
- No secure assets, pricing model, Woo variations, or existing product data are migrated automatically by this recovery release.

SECURE.8.2.4 — FINAL COMMERCE SAFETY
- Zero visual change and zero normal workflow change.
- Public storefront resolution now exposes only Published Athtyp font records; Draft/Private records remain available only to users who can edit them.
- WooCommerce purchasability/cart guards only attach to Published Athtyp records.
- Woo Sync now performs a read-only structural compatibility preflight before any taxonomy, term, attribute, product, price, variation, or download mutation.
- Sync is blocked when an existing Woo variable product has a third variation dimension, wildcard/missing Style or License values, or duplicate Style × License variation pairs.
- Restore Pre-Adoption Woo State now detaches the Athtyp record from the restored Woo product after restoring the snapshot, so Athtyp no longer remains commerce authority over the restored catalog item.
- Price Matrix, Package Builder, secure assets, frontend UI, multi-style checkout, renderer, and normal Build Assets → Save Pricing → Woo Sync workflow are unchanged.


SECURE.8.2.3 — PRICE SPACING POLISH
- Price-only presentation patch.
- Adds consistent spacing between current price, struck regular price, and discount badge in Family Packages.
- No typography, button, preview, sticky, checkout, pricing, WooCommerce, adoption, package, or render logic changes.

SECURE.8.2.2 — HIVEGLYPH BUTTON POLISH

- Button-only presentation patch; no layout, commerce, pricing, sticky, renderer, adoption, or package workflow changes.
- Protects HiveGlyph buttons from WordPress/theme generic hover backgrounds, gradients, shadows, transforms, and text decoration.
- All accent button colors continue to inherit the site's global primary color through the existing --ath-blue theme variable chain.
- Text actions use a quiet global-primary tint on hover without changing size or position.
- Final transaction CTAs stay solid global-primary and derive their hover tone from the same global color.
- Utility, modal, glyph, multi-style, Free Download, and keyboard focus states now share one consistent theme-aware button language.

PREVIOUS: SECURE.8.2.1 — STICKY INDIVIDUAL STYLE CONTROLS

- Individual Styles preview controls now stay visible while scrolling long style families.
- The selected-styles purchase bar stays directly below the preview controls on desktop/tablet.
- Sticky offsets automatically account for the WordPress admin bar and expose CSS variables for theme-specific header overrides.
- On mobile, preview controls use a compact sticky layout while the selected-styles purchase bar moves to a compact bottom action bar.
- No changes to pricing, Woo sync, Package Builder, Catalog Adoption, secure assets, Glyph Engine, server rendering, or checkout rules.

PREVIOUS: SECURE.8.2 — MULTI-STYLE CHECKOUT

- Adds optional multi-style purchasing to Individual Styles without removing the existing one-style Choose license flow.
- Each individual style gets a subtle HiveGlyph-style selection checkbox; selected rows surface a compact sticky selection bar with Clear and Choose licenses actions.
- The existing license modal can now price one or many selected styles against one or many licenses. Each license total is the sum of the existing plugin Price Matrix cells; no new pricing model is introduced.
- License availability is evaluated across every selected style. A license with a missing Price Matrix cell is disabled before checkout and identifies the affected style(s).
- Multi-style × multi-license checkout remains atomic: the server validates every requested Woo variation before changing the cart and restores the previous cart state if any add operation fails.
- Default safety limits are 50 selected styles, 10 licenses, and 100 total combinations per request; all three limits are filterable server-side.
- When a Full Style Bundle exists and is cheaper for the currently selected license combination, the modal offers a non-destructive Full Family recommendation with the calculated savings. The customer must explicitly choose it.
- WooCommerce continues to receive normal variation line items, preserving variation IDs, download permissions, order reporting, and existing fulfillment behavior.
- Price Matrix remains the pricing authority. Package Builder, secure assets, Catalog Adoption, Woo sync architecture, server rendering, Glyph Engine, Font Pairs, and Free Downloads are not changed by this feature.

PREVIOUS: SECURE.8.1.1 — ADOPTION SAFETY HARDENING

- Existing Catalog Adoption uses high-confidence mapping only for automatic adoption; ambiguous mappings require review.
- Blocks sparse matrices, wildcard/incomplete variations, extra variation dimensions, scheduled sales, and other unsafe legacy structures from automatic takeover.
- Preserves legacy Woo download IDs, uses logical adoption states, and stores enhanced pre-takeover snapshots for safer restore/migration workflows.

Version: 1.0.4-secure.8.0.1-production-hardening

SECURE.8.0.1 — PRODUCTION HARDENING

- Zero visual change and zero workflow change from secure.8.0 HiveGlyph UI Identity.
- Unifies render and Free Download client-IP resolution; forwarded IP headers are accepted only when explicitly enabled and the direct proxy peer matches a configured exact IP/CIDR.
- Shared-host render rate limiting uses a short MySQL advisory mutex when available to serialize parallel counter updates; unsupported hosts fall back safely to the existing transient path.
- Free Download lead timestamps now distinguish request, token creation, and successful token use/download delivery.
- One-time download tokens are validated before consumption and use an atomic claim so concurrent requests cannot redeem the same token twice.
- Woo batch sync now reserves a database-backed atomic per-product mutex before the mutation phase, while retaining resumable session behavior and legacy transient-lock compatibility.
- Protected download directories also receive IIS web.config denial and a defensive index.php marker; existing Apache/LiteSpeed .htaccess protection is retained. Nginx still requires Force Downloads/X-Accel-Redirect or an equivalent server rule.
- No CSS, frontend JavaScript, shortcode markup, pricing rules, Package Builder flow, Woo sync workflow, or secure-render output changed.

PREVIOUS: SECURE.8.0 — HIVEGLYPH UI IDENTITY

- Frontend identity pass based on HiveGlyph UI DNA v1.0: Typographic, Editorial, Precise, Quiet, Functional.
- Existing commerce, rendering, asset, pricing, WooCommerce, glyph, and admin workflows are unchanged.

PREVIOUS: SECURE.7.3.14 — FREE DOWNLOAD UX

- Free Downloads only: no changes to Font Pairs, tabs, pricing, WooCommerce, license popup, glyphs, Tech Specs, package building, or secure render logic.
- Adds professional responsive Free Download cards with theme-aware badges, thumbnail treatment, compact typography, notes, and stronger download CTA.
- Completes the existing email-gated frontend flow: inline expand form, AJAX request, validation/error state, secure ready state, and 15-minute Download now link.
- Adds Display Order to Free Download Settings; lower numbers appear first while older items without the field remain visible.
- Clarifies in admin that WordPress Excerpt is the short description displayed on the specimen card.
- Existing token security, honeypot, nonce, IP/email rate limits, lead storage, and secure local-file streaming are retained.

Version: 1.0.4-secure.7.3.13-persistent-font-pairs

SECURE.7.3.13 — PERSISTENT FONT PAIRS

- Moves Font Pairs outside the switchable tab panels so it remains visible while visitors move between Glyphs, Family Packages/Individual Styles, Tech Specs, and Licensing.
- Free Downloads Below Font Pairs now renders immediately below the persistent Font Pairs section.
- No popup, pricing, WooCommerce, render security, glyph, Tech Specs, admin workflow, JavaScript, or CSS behavior changed.

Version: 1.0.4-secure.7.3.12-commerce-render-hardening

SECURE.7.3.12 — COMMERCE & RENDER HARDENING

- No frontend UI/layout changes and no admin workflow changes.
- Cart and checkout require WooCommerce to match the current Athtyp pricing/delivery signature, preventing stale Woo prices from being charged.
- Contact Sales is now commerce eligibility, not presentation-only: Woo Sync does not create purchasable Contact Sales variations and retires old plugin-managed ones.
- Removed Style/License combinations are retired safely (private and unpriced) instead of deleted; historical variation downloads are retained for existing order permissions while new purchases are blocked.
- Empty download mappings now clear stale Woo variation downloads.
- Woo batch sync lock is product-wide so two administrators cannot safely resume the same product session at once.
- Preview render rate limits now use global + per-font buckets with separate render/metadata/glyph limits.
- PNG cache cleanup is deterministic and limited by TTL, file count, and total bytes.
- Full Glyph temporary fonts use a private system temp directory outside the WordPress public tree, mode 0600, shutdown cleanup, and orphan cleanup.
- Full Glyph metadata cache schema 4 stores compact GID→Unicode mappings and synthesizes pages on demand for very large fonts.
- Free download lead requests add an IP-wide hourly abuse limit in addition to the existing email/download limit.
- After upgrading from 7.3.11, run Woo Sync once for each active linked product so the new hardened commerce signature is recorded and stale variations/downloads are retired.


Secure.7.3.11 License Icon Manager
- Adds an admin-selectable internal icon library for License Options.
- Auto mode maps common license slugs to a consistent icon.
- Manual icons: Desktop, Web, App, Document, Server, Ads, Social, Broadcast, Merchandise, Corporate, Enterprise, Logo, Custom.
- Uses fixed internal stroke SVGs only; no arbitrary SVG upload or external icon dependency.
- Icon metadata is UI-only: changing it does not rebuild secure assets, alter pricing, or require Woo pricing sync.
- Build/import flows preserve icon choices by Woo license value.


Secure.7.3.10: individual/family/tech default specimen previews use server-measured single-line auto-fit. Moving the size slider disables auto-fit; Reset restores it.

Version: 1.0.4-secure.7.3.9-multi-license-checkout

SECURE.7.3.9 — MULTI-LICENSE CHECKOUT

- License picker uses compact checkbox rows instead of single-choice radio cards.
- Customers can purchase multiple licenses for the same selected style/package in one action.
- Sticky checkout summary calculates total regular price, sale subtotal, and combined discount.
- Woo add-to-cart pre-validates every Style × License variation before changing the cart.
- Multi-license cart mutation is atomic; failures restore the exact cart state from before the request.
- License Options add Checkout Type: Auto, Pay once, Annual, or Contact sales.
- Auto does not assume subscriptions; normal licenses default to Pay once and Custom licenses default to Contact sales.
- Contact-sales licenses stay visible without requiring a Price Matrix value and cannot be added directly to cart.
- License rows include lightweight built-in SVG category icons.
- Picker Group and Recommended remain presentation-only. Pay once/Annual changes stay UI-only; switching a license to/from Contact Sales changes purchase eligibility and therefore requires Woo Sync.
- Package rebuild and Woo import preserve Checkout Type by license slug.

Version: 1.0.4-secure.7.3.8-license-popup-polish
Popup: compact cards, sticky selected-license summary, theme-aware selection/actions, and WooCommerce currency formatting.
Version: 1.0.4-secure.7.3.7-adaptive-license-picker

secure.7.3.7 Adaptive License Picker
- 1–6 licenses: simple full list.
- 7–10 licenses: Common/Recommended first with Show more.
- 11+ licenses: search, group filters, Common/More progressive disclosure.
- License Options now support Picker Group (Auto/Common/Extended/Business/Custom) and Recommended.
- Picker metadata is UI-only: it does not rebuild secure assets and is excluded from Woo pricing/delivery sync signatures.
- Package rebuild and Woo import preserve picker group/recommended metadata by license slug.

SECURE.7.3.6 — ASSET / PRICING SEPARATION

- Package Builder Step 2 contains license delivery/templates only.
- Secure Asset Build never reads or writes the Price Matrix.
- Pricing & Discounts are managed in Step 4 and can be saved independently.
- Discount % is a UI helper that calculates Sale Price; only Regular + Sale are authoritative.
- Save Pricing Only changes metadata/database values only and never extracts fonts, builds ZIPs, or rewrites protected assets.
- WooCommerce remains a mirror and receives pricing only during Woo Sync.
- Asset fingerprint excludes all pricing fields, so price changes cannot mark assets stale.
- Existing secure.7.3.5 package prices migrate to Price Matrix only when no Price Matrix exists.
- Single-style builds no longer create a redundant Full Style family ZIP.

Version: 1.0.4-secure.7.3.6-asset-pricing-separation

Pricing authority: Athtyp Price Matrix is the master source. WooCommerce variation regular/sale prices are synchronized mirrors. Frontend preview prices read the plugin matrix, and manual Woo price edits are overwritten at the next sync.

Version: 1.0.4-secure.7.3.4-adaptive-tabs
Secure.7.3.3: Tech Specs now includes detailed embedded font metadata, metrics, variable axes, script coverage, and server-detected language support from the font cmap. Raw codepoint arrays are no longer sent to the browser metadata endpoint.
Authentype Font Specimen Commerce — 1.0.4-secure.7.3-full-glyph-engine
==========================================================

SECURE.7 ARCHITECTURE
---------------------
1. Full font bytes are no longer sent to the browser for specimen rendering.
   - No frontend @font-face.
   - No OpenType.js dependency.
   - No XOR/Base64 font payload endpoint.
   - No .OTF/.TTF/.WOFF/.WOFF2 URLs emitted in specimen markup.
   - The browser receives only PNG preview pixels and safe font metadata.

2. Server-side preview rendering.
   - Imagick is the recommended renderer and supports the normal secure OTF/TTF workflow.
   - GD + FreeType is a TTF-only fallback.
   - If neither renderer exists, the admin metabox shows an explicit warning instead of silently exposing the font.
   - Dynamic preview results are returned as raw PNG (not Base64-in-JSON), cached in a protected render-cache directory, capped/pruned, and loaded lazily.
   - Preview tokens are signed; style-1/style-2 enumeration is no longer the public API.

3. Persistent font metadata cache.
   - cmap, GSUB/GPOS feature tags, ligatures, name-table data, glyph count, units-per-em and format information are fingerprinted.
   - Metadata is stored as one protected JSON record per style/pair under woocommerce_uploads/authentype-metadata-cache, rather than one large serialized post-meta value.
   - Package Builder prewarms only the first/default style by default; the remaining styles are parsed only on first use in Glyphs/Tech Specs and then persisted.
   - Frontend page generation does not load or parse every style's metadata.

4. Large-family Package Builder refactor.
   - Source font files are extracted once into protected WooCommerce storage; the protected family root is initialized once instead of rewriting protection files for every style folder.
   - Individual style/license variations reuse the same protected assets and receive only the extensions required by the license.
   - Desktop: OTF + TTF.
   - Webfont: WOFF + WOFF2.
   - App/ePub: OTF + TTF by default.
   - Other/Extended/Server/Corporate-style license slugs: OTF + TTF + WOFF + WOFF2 by default.
   - Filters can override the extension rule.
   - Only Full Style family bundles are compressed as ZIPs, one ZIP per unique format set (normally at most three), rather than License × every style.
   - License/template documents are extracted once per license and attached separately to matching WooCommerce variations.
   - Old secure.6 per-license package directories inside the active secure token are removed after a successful secure.7 commit.

5. Large-family frontend/cart performance.
   - Frontend prices prefer the saved price matrix and do not call get_available_variations() once per visible style.
   - Custom add-to-cart uses WooCommerce's product data store matching path when available, with the previous full-variation scan retained only as a compatibility fallback.

6. Safer ZIP limits for shared hosting.
   - Source ZIP entries: 600 maximum (sized for very large families such as 100+ styles across several formats).
   - Single expanded entry: 32 MB maximum.
   - Total expanded source ZIP: 512 MB maximum.
   - Compression ratio: 100x maximum.
   These defaults can still be changed through the existing filters.

FRONTEND UI
-----------
The specimen UI follows the supplied marketplace reference pattern:
- Glyphs
- Family Packages (Best Value)
- Individual Styles
- Tech Specs
- Licensing

Individual Styles includes a shared preview-text field, size slider, color control, available-feature inspector, reset control, per-style pricing and Buying Choices buttons. Only canvases near the viewport are re-rendered when the tester changes, which prevents a 100+ style family from issuing 100+ render requests on every keystroke. Optional Font Pairs and Free Downloads from secure.6 remain supported.

The license modal uses selectable license cards with current price, crossed regular price, discount badge, description, license-detail link and a sticky Add to cart footer.

SERVER REQUIREMENTS
-------------------
- WordPress
- WooCommerce for commerce/variation features
- PHP ZipArchive for Package Builder
- PHP Imagick recommended for secure previews
- OR GD compiled with FreeType as a TTF-only fallback

DOWNLOAD SECURITY
-----------------
Protected product assets live under wp-content/uploads/woocommerce_uploads and are denied by the plugin's Apache/LiteSpeed .htaccess rules.

For commercial fonts, WooCommerce "Redirect only" is not recommended. Use Force Downloads, X-Accel-Redirect, X-Sendfile, or an equivalent protected server configuration. The admin screen warns when WooCommerce is configured for Redirect only.

MIGRATION FROM SECURE.6
-----------------------
The plugin can read existing secure.6 style records, but secure.6 preview assets were commonly WOFF files. For the strongest and most compatible secure.7 server renderer, upload the original family ZIP again and run:

1. Build Secure Assets
2. Sync Existing Woo Product
3. Test Glyphs, Individual Styles, Tech Specs and each license
4. Complete a test order and verify downloaded files for Desktop/Webfont/App/etc.

The public source family ZIP is deleted after a successful build when the existing delete-public-source filter remains enabled.

SECURITY BOUNDARY
-----------------
Secure.7 is intentionally different from secure.6: the complete font file is not needed by the browser to draw a preview. A visitor can save screenshots/PNG preview pixels (as with any visible webpage), but the preview endpoint does not deliver the original font bytes or glyph-outline font container.

MANUAL PREVIEW FILES
--------------------
The Advanced/Manual Font Styles editor remains for compatibility. A font manually uploaded to the normal WordPress Media Library may still have a public source URL at the storage level even though secure.7 never emits that URL to specimen visitors. For commercial previews, Build Secure Assets is the recommended path because it places preview sources in protected WooCommerce storage.

Secure.7.1 Woo sync fix
------------------------
- Registers OTF/TTF/WOFF/WOFF2 in WooCommerce's downloadable-file MIME allowlist.
- Prevents Safari's generic JSON parsing error from hiding PHP/WooCommerce sync failures.
- Sync updates each variation with a single save instead of multiple database writes.
- Download validation now returns the exact style/license that failed.


Secure.7.2 Large-family Woo batch sync
---------------------------------------
- Sync Existing Woo Product now runs in resumable batches instead of one long AJAX request.
- Batch size is adaptive: up to 12 variations/request normally, up to 10 above 150 combinations, and up to 8 above 300 combinations. Developers can lower/override the base with the authentype_specimen_woo_sync_batch_size filter (hard-capped at 25).
- Server-side sync state is retained for two hours, so a page refresh, network interruption, or manual Pause can resume without restarting the completed batches.
- Temporary network/408/429/502/503/504 failures retry the same batch up to three attempts.
- Each successfully processed style/license pair advances the stored offset; if a later pair fails, Resume starts at the exact failing combination.
- Existing variations are idempotent: unchanged prices/downloads are skipped and are not written back to the database.
- WooCommerce parent product attributes unrelated to Style/License are preserved instead of being replaced.
- Parent variable-product lookup/transient caches are synchronized only after the final batch.
- Admin UI now shows processed/total progress and includes a safe Pause Sync control.
- A valid unfinished server session is automatically resumed after reloading the Athtyp edit screen and clicking Sync Existing Woo Product again.

Secure.7.3 Full Glyph Engine
-----------------------------
- The Glyphs tab is now Glyph-ID based, not Unicode-cmap based. It enumerates GID 0 through maxp.numGlyphs - 1, so Tech Specs glyph_count and the total Glyphs count are expected to match exactly.
- Unicode-mapped and unencoded glyphs are both rendered as their real outlines. This includes .notdef, stylistic alternates, discretionary/standard ligatures, ornaments, swashes, small-cap glyph slots, and other glyphs that have no Unicode value.
- To render arbitrary GIDs securely, the server builds a temporary in-memory/server-temp SFNT for each glyph page. A new cmap format 12 maps Supplementary Private Use codepoints to the requested GIDs while the original glyf/CFF/CFF2 outline tables remain unchanged.
- The temporary OTF/TTF exists only on the server, is deleted immediately after rasterization, and is never exposed through specimen HTML, AJAX JSON, or a public URL.
- The final browser payload remains PNG raster pixels plus safe labels such as GID 437 or U+00C1. No reusable SVG/path outline is returned.
- Full Glyph pagination defaults to 60 glyphs and supports All / Unicode / Unencoded filters. The backend caps a page at 120 glyphs.
- OTF/CFF and TTF/glyf previews are supported through Imagick/FreeType. WOFF1 sources can be reconstructed to their underlying SFNT flavor. WOFF2 is intentionally not accepted as a Full Glyph preview source; Build Secure Assets already prefers OTF/TTF/WOFF for specimen preview.
- Existing secure.7 metadata cache records without glyph-item data are rebuilt lazily on first Glyphs/Tech Specs access.


secure.7.3.2 UI
- Theme-aware compact tabs: WordPress Global Styles preset colors are preferred when available.
- Accent priority: accent-1 -> primary -> plugin fallback.
- Text/surface priority: contrast/base -> plugin fallback.
- All --ath-* CSS variables remain overrideable from WordPress Additional CSS.


Secure.7.3.4 Adaptive Tabs
- Single style, no bundle: Glyphs | Preview | Tech Specs | Licensing.
- Multi-style family, no bundle: Glyphs | Individual Styles | Tech Specs | Licensing.
- Family with Full Style bundle: Glyphs | Family Packages | Individual Styles | Tech Specs | Licensing.
- Family Packages is not rendered when no real bundle exists; intro copy adapts to the product structure.

== 1.0.4-secure.8.0-hiveglyph-ui-identity ==
- Introduces HiveGlyph UI DNA v1.0 as a frontend-only identity pass.
- Replaces marketplace-like filled tabs with a quiet editorial underline navigation.
- Individual Styles now use numbered specimen rows, neutral price hierarchy, and lightweight Choose license actions.
- Family Packages, Tech Specs, Licensing, Font Pairs, Free Downloads, and the multi-license modal share one restrained typographic system.
- License modal uses numbered utility rows while preserving all existing multi-license, filtering, subtotal, Contact Sales, and cart behavior.
- Removes the blue-link/red-sale/green-CTA visual pattern in favor of one theme accent and neutral typography.
- No changes to Price Matrix authority, Woo sync, secure assets, server rendering, package building, glyph security, or admin workflow.

secure.8.1 Existing Catalog Adoption
- Adds Athtyp > Catalog Adoption for existing WooCommerce stores with large font catalogs.
- Scan and Dry Run are read-only against WooCommerce.
- Compatible variable products can be adopted one-by-one or in a safe one-product-per-request batch.
- Adoption creates a draft Athtyp record and imports existing Style/License values, regular/sale prices, and Woo download files.
- Stores a pre-takeover snapshot of Woo variation IDs, attributes, statuses, prices, and downloads.
- Does not modify Woo products during adoption; the normal Build Secure Assets -> Save Pricing -> Sync Existing Woo Product workflow remains authoritative.
- Blocks automatic adoption when products use custom (non-global) Style/License attributes, extra variation dimensions, or ambiguous duplicate Style x License pairs.
- Large catalog list is paginated/searchable and no longer depends on the 200-product metabox selector.


secure.8.1.1 Adoption Safety Hardening
- Frontend HiveGlyph presentation files unchanged from secure.8.1.
- Automatic bulk adoption requires high-confidence Style + License detection; no second-dimension guessing.
- Blocks wildcard variations, sparse Style×License matrices, any third variation dimension, duplicates, and scheduled Woo sales.
- Single-pass Woo dataset reused for analysis/import/snapshot to reduce large-family hydration work.
- Existing Woo download IDs are imported and preserved through normal Woo sync; package rebuilds carry legacy IDs to matching dimensions when possible.
- Adoption uses importing/failed/complete state and commits _ath_linked_product last, so interrupted imports are retryable instead of appearing complete.
- Snapshot schema v2 captures parent attributes/defaults/terms plus variation sale dates, virtual/downloadable state, exact download IDs, and Athtyp-managed meta.
- Pre-adoption Woo snapshot can be restored from Catalog Adoption. New Athtyp-created variations are disabled, never deleted.
- Trashed adoption drafts no longer block re-adoption.
- Catalog page reduced to 20 analyzed products per page; status filtering now occurs before pagination.

secure.8.2.16 — Pricing Flow Compatibility
- Keeps secure.8.2.15 concurrency, stale-page, ownership, and Woo product mutex protections.
- Fixes the post-build admin dead-end where Pricing and Woo Sync looked permanently disabled until a manual browser reload.
- After Build Secure Assets or Sync from WooCommerce changes Style/License inventory, the two affected controls become explicit one-click reload actions.
- Reloading rehydrates the generated Font Styles, License Options, Price Matrix schema, pricing revision, downloads, and Woo sync state from the server before further commerce edits.
- No frontend visual change, no package format change, and no pricing model rollback to the legacy Package Builder.

secure.8.2.17 — Full Glyph Renderer Compatibility
- Focused Full Glyph renderer patch built directly on secure.8.2.16.
- Temporary GID mapping now uses BMP Private Use codepoints U+E000..U+E077 with cmap format 4 for GD/FreeType compatibility.
- Full Glyph PNG cache uses a renderer-only revision so broken cached NO GLYPH rasters are bypassed without flushing normal specimen previews.
- GD keeps the same visible middle-dot label separator without UTF-8 mojibake.
- Pricing, Discounts, WooCommerce Sync, Build Secure Assets, package inventory, licensing, admin workflow, and unrelated frontend behavior are unchanged.


secure.8.2.18 — Checkout Mirror Gate Compatibility
- Focused checkout/runtime patch built directly on secure.8.2.17.
- Fixes a false storefront block where the broad `_ath_woo_synced_signature` receipt could be stale even though the selected Woo variation already contained the correct Athtyp price and secure downloads.
- The global receipt remains available for admin sync diagnostics; storefront purchase safety now blocks only during an active Woo batch mutation and validates each requested Style × License variation directly.
- Direct runtime validation still requires exact Regular/Sale/active price parity, exact secure download ID/name/file parity, downloadable state, valid Style/License values, and non-Contact-Sales eligibility before cart mutation or checkout.
- Pricing, Woo Sync write logic, Build Secure Assets, package inventory, Full Glyph renderer, frontend modal layout/CSS, and download package generation are unchanged.

secure.8.2.20 — Inventory Availability & Pricing Reconciliation
- Full Style is generated per license only when that license delivery includes at least one compatible font asset for every detected master style.
- Incomplete license families no longer receive a misleading Full Style ZIP/manifest row.
- Woo Sync queues only Style × License pairs backed by actual protected font delivery; license-document-only rows cannot create empty variations.
- When Style/License inventory dimensions or delivery availability change, orphan Price Matrix dimensions/no-delivery pairs are removed while prices on still-valid delivered pairs are preserved exactly.
- Pricing Needs Review now blocks Woo Sync, including resumed batch sessions, until pricing is explicitly reviewed and saved.
- Default specimen style is resolved from actual detected styles: preserve a still-valid previous default, otherwise Regular, otherwise the first real style.
- No storefront UI, Full Glyph renderer, checkout handler, pricing calculation/sale logic, or secure asset extraction model was changed.

secure.8.2.22 — Global License URL Routing
- Added a site-wide Athtyp > License URLs setting for the license-details destination.
- Added an optional per-product License URL Override without changing frontend layout.
- Supports {license} in path or fragment templates; URLs without the token append the current license slug as a fragment when appropriate.
- Preserves the existing /licenses/#license-slug behavior when no setting is configured.
- Single-product Licensing tab, Choose Licenses popup, and Contact sales links continue to use the existing centralized URL helper/filter.
- No changes to Pricing, Woo Sync, Checkout, Build Secure Assets, Package Builder, Full Glyph, download permissions, or frontend CSS/JS.


secure.8.2.23 — Bulk Legacy Woo Matcher & Adoption
- Adds a read-only Bulk Legacy Woo Matcher to Athtyp > Catalog Adoption for migrating dozens or hundreds of existing Woo font products without recreating them one by one.
- Catalog IDs are fetched in lightweight pages and each Woo product is analyzed in its own sequential AJAX request, avoiding one large variation-hydration spike.
- Successful adoptions and pre-existing linked Athtyp records teach site-local Style/License mapping profiles; one confirmed legacy schema can therefore unlock other products using the same global Woo attributes.
- Saved mapping profiles are exact attribute-key pairs only. Multiple matching profiles, equal-score heuristic ambiguity, custom product attributes, wildcard variations, extra dimensions, duplicate Style x License pairs, non-published variations, and scheduled sales remain blocked from automatic bulk adoption.
- Sparse legacy Style x License matrices are now adoptable without inventing missing combinations. Adoption imports only the exact existing pairs; secure.8.2.20 availability-aware Woo Sync remains responsible for queuing only pairs backed by actual delivery.
- Bulk Ready is stricter than structural compatibility: every existing pair must have a current price, at least one importable download, no unsafe download path, reusable high/profile mapping, and no exact-slug unlinked Athtyp candidate.
- Exact-slug unlinked Athtyp candidates are routed to review instead of creating duplicate Athtyp records.
- "Scan Woo Catalog" classifies the searched catalog as Ready / Review / Already Adopted. "Adopt All Ready" then performs one verified adoption request per product with a fresh server-side Bulk Ready recheck before commit.
- Adoption remains read-only against WooCommerce: no Woo variation, price, download, status, order, or customer permission is changed during catalog adoption.
- Only Catalog Adoption PHP/JS/CSS plus plugin version/localized strings were changed. Checkout, Pricing engine, Woo Sync writer, Build Secure Assets, Package Builder, Full Glyph, storefront specimen, and download-permission logic are unchanged.

secure.8.2.24 — Bulk Commerce Readiness Audit
- Adds a read-only audit for already-linked Athtyp/Woo products in Catalog Adoption.
- Audits one linked Woo product per AJAX request to avoid a large single-request CPU/RAM spike.
- Classifies products as Shop Ready, Needs Woo Sync, Needs Pricing, Missing Delivery, or Review.
- Shop Ready requires a published Athtyp link, published variable Woo product, valid Style/License mapping, and exact live Woo price/download mirror for every Athtyp delivery-backed purchasable pair.
- Uses the same exact variation mirror validator used by checkout; a stale broad sync receipt alone does not fail an otherwise exact live mirror.
- Audit is read-only: it does not publish Athtyp posts, save pricing, build assets, alter Woo variations/downloads, sync products, or modify customer/order permissions.
\n\nsecure.8.2.27 — Legacy Pricing Hydration
- Adds a two-step Preview Missing Pricing -> Hydrate Legacy Pricing migration for already-linked products currently classified Needs Pricing.
- Reads exact live Woo Style x License Regular/Sale prices one product per AJAX request and writes only completely empty Athtyp Price Matrix cells; existing Athtyp price cells always win and are never overwritten.
- Blocks scheduled sales, unpublished/stale variations, ambiguous variation structures, invalid Woo price relationships, duplicate ownership, invalid existing Athtyp price rows, and any product not currently classified Needs Pricing.
- Creates an append-only versioned pre-hydration pricing snapshot before every Price Matrix write and rolls back the Price Matrix/hash/saved/review metadata if post-write verification fails.
- Clears Pricing Needs Review only when every current delivery-backed purchasable pair has an active Athtyp price after the merge; it does not mark Woo as synced, so the normal readiness audit still detects genuine live mirror drift.
- Woo products, variations, prices, downloads, delivery metadata, orders, customer permissions, Checkout, Build Secure Assets, Package Builder, Full Glyph, and storefront files are unchanged by pricing hydration.



secure.8.2.30 — Buyer Delivery Download
- Adds a clear “What the Buyer Receives” view to Woo Source Files, grouped by the exact Woo product/variation that grants each download set.
- One-file deliveries stream the exact current Woo file; multi-file local deliveries can be downloaded in one temporary admin ZIP containing those same files unchanged.
- Exact per-file download buttons remain available for every Woo download ID/name/file, including mixed or remote deliveries.
- Every request re-reads the live Woo product/variation and requires the existing admin capability + nonce; a variation must still belong to the requested parent product.
- Multi-file bundling never fetches remote URLs and refuses missing/non-local files; use the exact individual file actions for those cases.
- This is admin-only recovery/inspection. It does not alter Woo products, variation IDs, download IDs/files, prices, orders, customer permissions, Athtyp delivery, Checkout, Build, Pricing, Full Glyph, or storefront behavior.


secure.8.2.29 — Woo Source File Inspector & Simplified Catalog
- Adds a read-only Source Files inspector for every Woo catalog product.
- Shows existing Woo variation/download IDs, local/remote/missing file state, file size/type, and font candidates inside local ZIPs without extracting them.
- Authorized admins can securely download local Woo source files through a nonce/capability-checked stream endpoint; external Woo URLs are only opened after the exact Woo download reference is revalidated.
- Catalog Adoption UI is simplified around Connect Old Woo Products, Check Storefront, and per-product Source Files. Legacy Delivery/Pricing/Reconciliation engines remain available under one Advanced repair tools disclosure and are not removed.
- No Woo product, variation, price, download, order, customer permission, checkout, package, Full Glyph, or frontend specimen behavior is changed by the inspector.

secure.8.2.28 — Legacy Woo Variation Reconciliation
- Adds a two-step preview/reconcile path for already-adopted products classified Needs Woo Sync.
- Reuses existing Woo variation IDs; only stale Style/License attributes and Athtyp-authoritative regular/sale/active prices may change.
- Requires exact existing Woo download signature to match the target Athtyp delivery before a stale pair can be remapped.
- Existing Woo download IDs, names, files, downloadable state, variation status, orders, and customer download permissions are not written by this path.
- Uses append-only pre-change snapshots, adoption/Woo-sync mutexes, revalidation after locks, post-write verification, and automatic rollback on verification failure.
- Does not create/delete variations and does not invoke normal Woo Sync.


secure.8.3.1 — Purchase CTA Clarity
- Storefront purchase entry points now use explicit Buy wording instead of presenting the purchase gateway as a lightweight “Choose license” text link.
- Individual style rows show “Buy {Style}”; the family package shows “Buy Full Family”; and the sticky multi-style selection bar shows “Buy Selected Styles”.
- Purchase entry buttons are solid global-primary CTAs with stable geometry, theme-derived hover/focus treatment, and mobile-safe wrapping.
- The license modal remains the license-selection step, now titled “Choose Your Licenses” with a short instruction that the buyer selects coverage before adding to cart.
- Pricing, license availability, cart requests, checkout, Woo Sync, Build Secure Assets, Full Glyph, Catalog tools, and download permissions are unchanged.

secure.8.3.2 — Free Download License Authority
- Free Downloads now use reusable site-wide license presets managed from Athtyp > Free Licenses. Each preset has a frontend label, explicit version, short scope/summary, and public license-document URL/PDF.
- The supplied Authentype Free Commercial Limited and Free Commercial license documents are bundled as the default documents for those two presets; their short summaries mirror the supplied license scope without expanding the granted rights.
- Existing Tester, Demo, Free Personal, and Free Commercial keys remain compatible. Free Commercial Limited is added as a separate license class, and Custom License is available per Free Download item.
- Each Free Download item selects a global preset or a custom override. A site-wide default applies only to items that have never saved a license selection, so existing products are not silently reclassified.
- Storefront cards display the actual license name/version, a concise scope summary, and a Read full license terms link before the download action. Email-gated forms name the exact accepted license and link the document directly beside the required agreement checkbox.
- Gated requests carry a server-verified license fingerprint. If a preset changes after a page was loaded, submission is rejected until the visitor refreshes and reviews the new terms.
- Free Download Leads snapshot the accepted license key, label, version, summary, document URL, source, acceptance timestamp, and fingerprint so later preset edits do not rewrite historical consent records.
- Only Free Downloads, Free Download Leads display, their dedicated frontend presentation, plugin version, and bundled free-license PDFs are changed. Paid licenses, Pricing, WooCommerce, Checkout, Build Secure Assets, Catalog Adoption, Full Glyph, package delivery, and customer permissions are unchanged.

secure.8.3.3 — Free Downloads UI Redesign
- Redesigns only the Free Downloads storefront presentation using a quieter premium-foundry layout inspired by the supplied custom-license reference: stronger whitespace, clear hierarchy, restrained surfaces, and a dedicated license panel before the download action.
- Adds an explicit Free Download kicker, clearer license name/version hierarchy, concise usage-rights presentation, and a stronger primary Download CTA while keeping the existing versioned license authority and agreement flow unchanged.
- Adds one site-aware custom-license help panel beneath the Free Downloads grid. It uses the existing WordPress administration email as a presentation-only contact target and can be overridden with the authentype_free_download_support_email filter.
- Email-gated forms, secure tokens, lead snapshots, license fingerprints, files, and download behavior are unchanged. Existing JavaScript hooks are preserved.
- Only Free Downloads PHP presentation, Free Downloads CSS presentation, plugin version, and README are changed. Pricing, WooCommerce, Checkout, Build Secure Assets, Catalog Adoption, Full Glyph, Package Builder, paid licenses, delivery, and customer permissions are untouched.


secure.8.3.4 — Free Download Layout Refinement
- Removed the reference-only “Need broader usage rights / Need a custom license?” promotional panel from Free Downloads.
- Free Download cards now use the full available specimen/template width instead of collapsing into a narrow auto-fit card.
- Kept Free Download license authority, consent, secure download, lead snapshots, and all commerce/build/catalog/full-glyph behavior unchanged.


secure.8.3.5 — Free Download Centered Full-Width Card
- Free Download cards now occupy the full usable width of the current specimen/template container and are explicitly centered with automatic inline margins.
- The embedded Free Downloads section and standalone shortcode both use a single full-width grid column so cards no longer sit visually left-aligned or collapse to a partial column.
- This is presentation-only. Free Download license authority, secure download flow, Pricing, WooCommerce, Checkout, Build Secure Assets, Catalog Adoption, Full Glyph, Package Builder, paid licenses, delivery, and customer permissions are unchanged.


secure.8.4.1 — Family Packages Proof Sheet (base secure.8.3.9)
- Rebuilds only the ★ BEST VALUE Family Packages storefront tab on top of secure.8.3.9 Preview Cache + Ink Safe Fit.
- The complete-family package now shows every included individual style as a compact protected server-rendered proof-sheet row, with preview on the left and the style label on the right.
- Adds a Family Packages preview controller for text, size, color, OpenType inspection, and reset. Its values stay synchronized with the existing Individual Styles controller.
- The untouched default pangram keeps secure.8.3.9 single-line ink-safe fitting. User-entered text or explicit size changes keep the chosen size and disable automatic single-line shrinking, matching existing 8.3.9 behavior.
- Family package rows do not expose individual prices or individual purchase actions; the package footer remains the single commerce entry point and opens the existing license picker via Choose License.
- Style labels are shortened presentation-only when an adopted style name repeats the family prefix; variation values, package inventory, prices, and delivery mappings are unchanged.
- No changes to server-render.php, WooCommerce sync/delivery, Package Builder, Pricing authority, paid license matrix, Catalog Adoption, Free Downloads, Full Glyph, customer permissions, or data schema.


secure.8.4.3 — Render Workload Hardening (base secure.8.4.2)
- Zero visual, pricing, licensing, WooCommerce, package, and product-schema changes.
- Caps browser-side preview traffic at three concurrent render requests.
- Adds an atomic per-cache-key render mutex to prevent cold-cache stampedes.
- Adds server-wide render, metadata, and Full Glyph request ceilings in addition to the existing per-visitor limits.
- Keeps Redis/external-object-cache atomic counters when available and a safe transient fallback otherwise.
- Adds a bounded global PNG cache ceiling, scanned at most once per 30 minutes.

secure.8.4.2 — Selected Styles Total (base secure.8.3.9)
- Adds a clear Selected total summary inside the existing Buy Selected Styles license picker.
- The total is calculated from the existing Athtyp Price Matrix for every selected Style × selected License combination; no new pricing source or multiplier is introduced.
- The summary shows the selected style/license count, current combined total, and existing regular/discount values when a sale price applies.
- License rows continue to show the total price for all selected styles for that license, while the footer subtotal remains unchanged for checkout verification.
- This is storefront presentation/JavaScript only. WooCommerce sync/delivery, Package Builder, Pricing authority, license availability, secure.8.3.9 server renderer, Catalog Adoption, Free Downloads, Full Glyph, and data schema are unchanged.
