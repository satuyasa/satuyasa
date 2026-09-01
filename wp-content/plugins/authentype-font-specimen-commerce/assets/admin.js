document.addEventListener("DOMContentLoaded", () => {
  async function postAdminAjax(body) {
    const response = await fetch(AthSpecimenAdmin.ajaxUrl, {
      method: "POST",
      body,
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    });
    const raw = await response.text();
    const clean = raw.replace(/^\uFEFF/, "").trim();
    let result;
    try {
      result = JSON.parse(clean);
    } catch (error) {
      const summary = clean
        .replace(/<style[\s\S]*?<\/style>/gi, " ")
        .replace(/<script[\s\S]*?<\/script>/gi, " ")
        .replace(/<[^>]+>/g, " ")
        .replace(/&nbsp;/g, " ")
        .replace(/\s+/g, " ")
        .trim()
        .slice(0, 700);
      throw new Error(
        `Admin request returned a non-JSON response (HTTP ${response.status}). ${summary || "Check the PHP/WordPress error log."}`
      );
    }
    if (!response.ok && !result?.success) {
      const requestError = new Error(result?.data?.message || `Admin request failed with HTTP ${response.status}.`);
      requestError.data = result?.data || {};
      requestError.status = response.status;
      throw requestError;
    }
    return result;
  }

  const wait = (ms) => new Promise(resolve => window.setTimeout(resolve, ms));
  function nextIndex(tbody) {
    const table = tbody.closest(".ath-repeat-table");
    const index = Number(table?.dataset.nextIndex || 0);
    if (table) table.dataset.nextIndex = String(index + 1);
    return index;
  }

  function insertTemplate(tbody, template, values = {}) {
    const index = nextIndex(tbody);
    tbody.insertAdjacentHTML("beforeend", template.innerHTML.replaceAll("__INDEX__", index));
    const row = tbody.lastElementChild;
    Object.entries(values).forEach(([name, value]) => {
      const input = row.querySelector(`[name$="[${name}]"]`);
      if (!input) return;
      if (input.type === "checkbox") input.checked = Boolean(Number(value) || value === true || value === "true" || value === "yes");
      else input.value = value;
    });
    updateMoveButtons(tbody);
    return row;
  }

  function addRow(buttonSelector, tbodySelector, templateSelector) {
    const button = document.querySelector(buttonSelector);
    const tbody = document.querySelector(tbodySelector);
    const template = document.querySelector(templateSelector);
    if (!button || !tbody || !template) return;

    button.addEventListener("click", () => {
      insertTemplate(tbody, template);
      refreshPairCardSelects();
    });
  }

  addRow(".ath-add-style", "#ath-font-style-rows", "#tmpl-ath-style-row");
  addRow(".ath-add-license", "#ath-license-rows", "#tmpl-ath-license-row");
  addRow(".ath-add-product-download", "#ath-product-download-rows", "#tmpl-ath-product-download-row");
  addRow(".ath-add-package-license", "#ath-package-license-rows", "#tmpl-ath-package-license-row");
  addRow(".ath-add-pairing-font", "#ath-pairing-font-rows", "#tmpl-ath-pairing-font-row");
  addRow(".ath-add-pair-card", "#ath-pair-card-rows", "#tmpl-ath-pair-card-row");


  function slugify(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/&/g, " and ")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");
  }

  function randomCode(prefix = "ath") {
    const alphabet = "23456789abcdefghjkmnpqrstuvwxyz";
    let code = "";
    for (let index = 0; index < 6; index += 1) {
      code += alphabet[Math.floor(Math.random() * alphabet.length)];
    }
    return `${prefix}-${code}`;
  }

  const styleWeights = [
    { re: /\b(hairline|thin)\b/i, name: "Thin", weight: "100" },
    { re: /\b(extra|ultra)[-\s_]*light\b/i, name: "Extra Light", weight: "200" },
    { re: /\b(light)\b/i, name: "Light", weight: "300" },
    { re: /\b(book|regular|normal|roman)\b/i, name: "Regular", weight: "400" },
    { re: /\b(medium)\b/i, name: "Medium", weight: "500" },
    { re: /\b(semi|demi)[-\s_]*bold\b/i, name: "Semi Bold", weight: "600" },
    { re: /\b(bold)\b/i, name: "Bold", weight: "700" },
    { re: /\b(extra|ultra)[-\s_]*bold\b/i, name: "Extra Bold", weight: "800" },
    { re: /\b(black|heavy)\b/i, name: "Black", weight: "900" }
  ];

  function fileNameFromUrl(url) {
    try {
      const parsed = new URL(url, window.location.href);
      return decodeURIComponent(parsed.pathname.split("/").pop() || "");
    } catch (error) {
      return decodeURIComponent(String(url || "").split("?")[0].split("#")[0].split("/").pop() || "");
    }
  }

  function titleCaseStyle(value) {
    return String(value || "")
      .replace(/([a-z])([A-Z])/g, "$1 $2")
      .replace(/[-_+.]+/g, " ")
      .replace(/\s+/g, " ")
      .trim()
      .toLowerCase()
      .replace(/\b\w/g, character => character.toUpperCase())
      .replace(/\bSemi Bold\b/g, "Semi Bold")
      .replace(/\bExtra Light\b/g, "Extra Light")
      .replace(/\bExtra Bold\b/g, "Extra Bold");
  }

  function detectStyleFromFontUrl(url) {
    const fileName = fileNameFromUrl(url);
    const baseName = fileName
      .replace(/\.(woff2?|otf|ttf|ttc)$/i, "")
      .replace(/%20/g, " ")
      .replace(/([a-z])([A-Z])/g, "$1 $2");
    const searchable = baseName.replace(/[-_+.]+/g, " ");
    const isItalic = /\b(italic|ita)\b/i.test(searchable);
    const isOblique = /\b(oblique|obl)\b/i.test(searchable);
    const isRange = /\b(thin|hairline)\b.*\b(black|heavy)\b|\b(black|heavy)\b.*\b(thin|hairline)\b/i.test(searchable);
    const isFull = /\b(variable|var|vf|full|complete|all styles?)\b/i.test(searchable) || isRange;

    if (isFull) {
      return {
        styleName: "Full Style",
        weight: "1",
        fontStyle: isItalic ? "italic" : isOblique ? "oblique" : "normal",
        wooValue: "full-style",
        isPackage: true
      };
    }

    let match = styleWeights.find(item => item.re.test(searchable));
    if (match?.name === "Bold") {
      const stronger = styleWeights.find(item => item.name === "Extra Bold" && item.re.test(searchable));
      if (stronger) match = stronger;
    }

    const styleNameParts = [];
    if (match) styleNameParts.push(match.name);
    if (isItalic) styleNameParts.push("Italic");
    if (!isItalic && isOblique) styleNameParts.push("Oblique");

    let styleName = styleNameParts.join(" ");
    if (!styleName) {
      const lastChunk = baseName.split(/[-_]/).map(part => part.trim()).filter(Boolean).pop();
      styleName = titleCaseStyle(lastChunk || "Regular");
    }

    return {
      styleName: styleName || "Regular",
      weight: match?.weight || "400",
      fontStyle: isItalic ? "italic" : isOblique ? "oblique" : "normal",
      wooValue: slugify(styleName || "regular"),
      isPackage: false
    };
  }

  function applyDetectedStyle(row, force = false) {
    const url = row?.querySelector(".ath-font-url")?.value || "";
    if (!url.trim()) return false;

    const detected = detectStyleFromFontUrl(url);
    const name = row.querySelector('[name$="[style_name]"]');
    const weight = row.querySelector('[name$="[font_weight]"]');
    const style = row.querySelector('[name$="[font_style]"]');
    const woo = row.querySelector('[name$="[style_variation_value]"]');
    const isPackage = row.querySelector('[name$="[is_package]"]');

    if (name && (force || !name.value.trim())) name.value = detected.styleName;
    if (weight && (force || !weight.value.trim() || weight.value === "400")) weight.value = detected.weight;
    if (style && (force || !style.value || style.value === "normal")) style.value = detected.fontStyle;
    if (woo && (force || !woo.value.trim())) woo.value = detected.wooValue;
    if (isPackage && detected.isPackage && (force || !isPackage.checked)) isPackage.checked = true;

    return true;
  }

  function isEmptyStyleRow(row) {
    if (!row) return false;
    const fields = [
      row.querySelector('[name$="[style_name]"]'),
      row.querySelector(".ath-font-url"),
      row.querySelector('[name$="[style_variation_value]"]')
    ];
    return fields.every(field => !field || !field.value.trim());
  }

  function insertStyleFromFontFile(file) {
    const tbody = document.querySelector("#ath-font-style-rows");
    const template = document.querySelector("#tmpl-ath-style-row");
    if (!tbody || !template || !file?.url) return null;

    let row = Array.from(tbody.querySelectorAll(".ath-admin-row")).find(isEmptyStyleRow);
    if (!row) {
      row = insertTemplate(tbody, template);
    }

    const input = row.querySelector(".ath-font-url");
    if (input) input.value = file.url;
    applyDetectedStyle(row, true);

    const selected = row.querySelector('[name$="[default_selected]"]');
    const hasSelected = Boolean(tbody.querySelector('[name$="[default_selected]"]:checked'));
    if (selected && !hasSelected) selected.checked = true;

    updateMoveButtons(tbody);
    return row;
  }

  function insertProductDownloadFromFile(file) {
    const tbody = document.querySelector("#ath-product-download-rows");
    const template = document.querySelector("#tmpl-ath-product-download-row");
    if (!tbody || !template || !file?.url) return null;

    const row = insertTemplate(tbody, template, {
      download_name: file.title || file.filename || file.name || fileNameFromUrl(file.url),
      download_file: file.url
    });
    updateMoveButtons(tbody);
    return row;
  }

  function fillMissingInternalCodes() {
    let created = 0;

    document.querySelectorAll("#ath-font-style-rows .ath-admin-row").forEach(row => {
      const value = row.querySelector('[name$="[style_variation_value]"]');
      if (value && !value.value.trim()) {
        value.value = randomCode("aths");
        created += 1;
      }
    });

    document.querySelectorAll("#ath-license-rows .ath-admin-row").forEach(row => {
      const value = row.querySelector('[name$="[license_variation_value]"]');
      if (value && !value.value.trim()) {
        value.value = randomCode("athl");
        created += 1;
      }
    });

    return created;
  }

  function ensurePairKeys() {
    document.querySelectorAll("#ath-pairing-font-rows .ath-admin-row").forEach(row => {
      const name = row.querySelector(".ath-pair-name");
      const key = row.querySelector(".ath-pair-key");
      if (name && key && !key.value) key.value = slugify(name.value);
    });
  }

  function currentPairingFontOptions() {
    ensurePairKeys();
    return Array.from(document.querySelectorAll("#ath-pairing-font-rows .ath-admin-row"))
      .map(row => {
        const name = row.querySelector(".ath-pair-name")?.value?.trim() || "";
        const key = row.querySelector(".ath-pair-key")?.value?.trim() || slugify(name);
        return name && key ? { key, name } : null;
      })
      .filter(Boolean);
  }

  function refreshPairCardSelects() {
    const fonts = currentPairingFontOptions();
    document.querySelectorAll('#ath-pair-card-rows select[name$="[title_font]"], #ath-pair-card-rows select[name$="[body_font]"]').forEach(select => {
      const selected = select.value;
      select.innerHTML = '<option value="">Select font</option>';
      fonts.forEach(font => {
        const option = document.createElement("option");
        option.value = font.key;
        option.textContent = font.name;
        option.selected = selected === font.key;
        select.appendChild(option);
      });
    });
  }

  function parseLicenseLine(line) {
    const clean = line.trim();
    if (!clean || clean.startsWith("#")) return null;

    const separator = clean.includes("|") ? "|" : clean.includes("\t") ? "\t" : clean.includes(",") ? "," : null;
    const parts = separator ? clean.split(separator).map(part => part.trim()) : [clean];
    const label = parts[0] || "";
    if (!label) return null;

    const groupRaw = (parts[3] || "").toLowerCase();
    const allowedGroups = ["common", "extended", "business", "custom"];
    const featuredRaw = (parts[4] || "").toLowerCase();
    const checkoutRaw = (parts[5] || "").toLowerCase().replace(/[- ]+/g, "_");
    const allowedCheckout = ["pay_once", "annual", "contact"];
    const iconRaw = (parts[6] || "").toLowerCase().replace(/[- ]+/g, "_");
    const allowedIcons = ["desktop", "web", "app", "document", "server", "ads", "social", "broadcast", "merchandise", "corporate", "enterprise", "logo", "custom"];
    return {
      license_label: label,
      license_variation_value: parts[1] || slugify(label),
      license_description: parts[2] || "",
      license_group: allowedGroups.includes(groupRaw) ? groupRaw : "",
      license_featured: ["1", "yes", "true", "recommended", "featured"].includes(featuredRaw) ? 1 : 0,
      license_checkout_type: allowedCheckout.includes(checkoutRaw) ? checkoutRaw : "",
      license_icon: allowedIcons.includes(iconRaw) ? iconRaw : ""
    };
  }

  const licensePreset = document.querySelector(".ath-license-preset");
  const licensePaste = document.querySelector(".ath-license-smart-paste");
  const licenseApply = document.querySelector(".ath-apply-license-paste");
  const licenseStatus = document.querySelector(".ath-license-smart-status");
  const generateCodes = document.querySelector(".ath-generate-internal-codes");

  generateCodes?.addEventListener("click", () => {
    const created = fillMissingInternalCodes();
    const status = document.querySelector(".ath-sync-status");
    if (status) {
      const staleSuffix = document.body.dataset.athBuilderStale === "1"
        ? " Secure assets changed; reload this Athtyp edit page before Pricing/Woo Sync or a normal Update of builder fields."
        : "";
      status.textContent = (created
        ? `${created} missing internal codes generated. Save/update this Athtyp post to keep them.`
        : "No empty Woo Style/License values found.") + staleSuffix;
    }
  });


  function updateMatrixDiscountCell(cell, syncHelper = true) {
    if (!cell) return;
    const regularInput = cell.querySelector('input[name*="[regular]"]');
    const saleInput = cell.querySelector('input[name*="[sale]"]');
    const helperInput = cell.querySelector('.ath-matrix-discount-input');
    const badge = cell.querySelector('[data-ath-matrix-discount]');
    if (!badge) return;
    const regular = Number.parseFloat(regularInput?.value || "");
    const sale = Number.parseFloat(saleInput?.value || "");
    if (!Number.isFinite(regular) || regular <= 0) {
      badge.textContent = Number.isFinite(sale) && sale > 0 ? "Add Regular price first" : "No price";
      badge.classList.remove("has-discount");
      badge.classList.toggle("has-warning", Number.isFinite(sale) && sale > 0);
      if (syncHelper && helperInput) helperInput.value = "";
      return;
    }
    if (!Number.isFinite(sale) || sale <= 0) {
      badge.textContent = "Regular price";
      badge.classList.remove("has-discount", "has-warning");
      if (syncHelper && helperInput) helperInput.value = "";
      return;
    }
    if (sale >= regular) {
      badge.textContent = "Sale must be lower than Regular";
      badge.classList.remove("has-discount");
      badge.classList.add("has-warning");
      if (syncHelper && helperInput) helperInput.value = "";
      return;
    }
    const discount = ((regular - sale) / regular) * 100;
    badge.textContent = `${Math.round(discount)}% off`;
    badge.classList.add("has-discount");
    badge.classList.remove("has-warning");
    if (syncHelper && helperInput) helperInput.value = String(Math.round(discount * 100) / 100);
  }

  function applyMatrixDiscountHelper(cell) {
    if (!cell) return;
    const regularInput = cell.querySelector('input[name*="[regular]"]');
    const saleInput = cell.querySelector('input[name*="[sale]"]');
    const helperInput = cell.querySelector('.ath-matrix-discount-input');
    const regular = Number.parseFloat(regularInput?.value || "");
    const discount = Number.parseFloat(helperInput?.value || "");
    if (!saleInput) return;
    if (!Number.isFinite(discount) || discount <= 0) {
      saleInput.value = "";
      updateMatrixDiscountCell(cell, false);
      return;
    }
    if (!Number.isFinite(regular) || regular <= 0) {
      updateMatrixDiscountCell(cell, false);
      const badge = cell.querySelector('[data-ath-matrix-discount]');
      if (badge) { badge.textContent = "Add Regular price first"; badge.classList.add("has-warning"); }
      return;
    }
    const bounded = Math.max(0, Math.min(95, discount));
    helperInput.value = String(bounded);
    const sale = regular * ((100 - bounded) / 100);
    saleInput.value = String(Math.round(sale * 100) / 100);
    updateMatrixDiscountCell(cell, false);
  }

  function updateAllMatrixDiscounts() {
    document.querySelectorAll(".ath-price-matrix td").forEach(cell => updateMatrixDiscountCell(cell, true));
  }
  updateAllMatrixDiscounts();

  document.addEventListener("input", event => {
    const discountHelper = event.target.closest('.ath-matrix-discount-input');
    if (discountHelper) {
      applyMatrixDiscountHelper(discountHelper.closest("td"));
      const pricingStatus = document.querySelector(".ath-pricing-status");
      if (pricingStatus) pricingStatus.textContent = "Unsaved pricing changes. Save Pricing Only to apply them without rebuilding files.";
      document.body.dataset.athPricingDirty = "1";
      return;
    }
    const matrixPrice = event.target.closest('.ath-price-matrix input[name*="[regular]"], .ath-price-matrix input[name*="[sale]"]');
    if (matrixPrice) {
      updateMatrixDiscountCell(matrixPrice.closest("td"), true);
      const pricingStatus = document.querySelector(".ath-pricing-status");
      if (pricingStatus) pricingStatus.textContent = "Unsaved pricing changes. Save Pricing Only to apply them without rebuilding files.";
      document.body.dataset.athPricingDirty = "1";
      return;
    }

    const pairName = event.target.closest(".ath-pair-name");
    if (pairName) {
      const row = pairName.closest(".ath-admin-row");
      const key = row?.querySelector(".ath-pair-key");
      if (key && !key.value) key.value = slugify(pairName.value);
      refreshPairCardSelects();
      return;
    }

  });

  document.addEventListener("change", event => {
    const fontUrl = event.target.closest("#ath-font-style-rows .ath-font-url");
    if (fontUrl) {
      applyDetectedStyle(fontUrl.closest(".ath-admin-row"));
      return;
    }

  });

  licensePreset?.addEventListener("click", () => {
    if (!licensePaste) return;
    licensePaste.value = [
      "Desktop | desktop | For local desktop use. | common | recommended | pay_once | desktop",
      "Webfont | webfont | For website embedding. | common | | pay_once | web",
      "App | app | For mobile or desktop apps. | common | | pay_once | app",
      "ePub | epub | For ebooks and digital publications. | common | | pay_once | document",
      "Server | server | For server-side rendering or automated output. | extended | | pay_once | server",
      "Extended | extended | For larger commercial usage. | extended | | pay_once | custom"
    ].join("\n");
    licensePaste.focus();
  });

  licenseApply?.addEventListener("click", () => {
    const tbody = document.querySelector("#ath-license-rows");
    const template = document.querySelector("#tmpl-ath-license-row");
    const replace = document.querySelector(".ath-license-replace")?.checked;
    if (!tbody || !template || !licensePaste) return;

    const licenses = licensePaste.value
      .split(/\r?\n/)
      .map(parseLicenseLine)
      .filter(Boolean);

    if (!licenses.length) {
      if (licenseStatus) licenseStatus.textContent = "No license lines found.";
      return;
    }

    if (replace) {
      tbody.innerHTML = "";
      tbody.closest(".ath-repeat-table").dataset.nextIndex = "0";
    }

    licenses.forEach(license => insertTemplate(tbody, template, license));
    if (licenseStatus) licenseStatus.textContent = `${licenses.length} license rows ready.`;
  });

  function updateMoveButtons(tbody) {
    if (!tbody) return;
    Array.from(tbody.querySelectorAll(".ath-admin-row")).forEach((row, index, rows) => {
      const up = row.querySelector(".ath-move-up");
      const down = row.querySelector(".ath-move-down");
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === rows.length - 1;
    });
  }

  document.querySelectorAll(".ath-repeat-table tbody").forEach(updateMoveButtons);

  const syncButton = document.querySelector(".ath-sync-woo");
  const buildWooButton = document.querySelector(".ath-build-woo");
  const wooProgressWrap = document.querySelector(".ath-woo-sync-progress");
  const wooProgress = document.querySelector(".ath-woo-progress");
  const wooProgressLabel = document.querySelector(".ath-woo-progress-label");
  const stopWooButton = document.querySelector(".ath-stop-woo-sync");
  let wooSyncResume = null;
  let wooSyncStopRequested = false;

  function updateWooProgress(data = {}) {
    const total = Number(data.total || 0);
    const processed = Number(data.processed || 0);
    const percent = Number.isFinite(Number(data.percent))
      ? Number(data.percent)
      : (total > 0 ? Math.floor((processed / total) * 100) : 0);
    if (wooProgressWrap) wooProgressWrap.hidden = false;
    if (wooProgress) {
      wooProgress.max = Math.max(1, total || 100);
      wooProgress.value = total > 0 ? Math.min(total, processed) : Math.min(100, percent);
    }
    if (wooProgressLabel) {
      wooProgressLabel.textContent = total > 0
        ? `${processed} / ${total} (${Math.min(100, percent)}%)`
        : `${Math.min(100, percent)}%`;
    }
  }

  async function requestWooBatch(postId, token, status, attempt = 0) {
    const batchBody = new FormData();
    batchBody.append("action", "ath_specimen_build_woo");
    batchBody.append("nonce", AthSpecimenAdmin.nonce);
    batchBody.append("phase", "batch");
    batchBody.append("post_id", postId);
    batchBody.append("token", token);

    try {
      return await postAdminAjax(batchBody);
    } catch (error) {
      const retryableHttp = [408, 429, 502, 503, 504].includes(Number(error?.status || 0));
      const networkFailure = !error?.data && !error?.status;
      if (attempt < 2 && (retryableHttp || networkFailure)) {
        if (status) status.textContent = `Temporary sync interruption. Retrying batch ${attempt + 2}/3...`;
        await wait(900 * (attempt + 1));
        return requestWooBatch(postId, token, status, attempt + 1);
      }
      throw error;
    }
  }

  function applyWorkflowSignature(data) {
    if (!data?.workflow_signature) return;
    const input = document.querySelector('input[name="ath_admin_workflow_signature"]');
    if (input) input.value = data.workflow_signature;
  }

  function requireCommerceReload(message = "Commerce inventory changed on the server.") {
    document.body.dataset.athBuilderStale = "1";

    const pricingButton = document.querySelector(".ath-save-pricing");
    const wooButton = document.querySelector(".ath-build-woo");
    const pricingStatus = document.querySelector(".ath-pricing-status");
    const syncStatus = document.querySelector(".ath-sync-status");

    if (pricingButton) {
      if (!pricingButton.dataset.normalLabel) pricingButton.dataset.normalLabel = pricingButton.textContent || "Save Pricing Only";
      pricingButton.dataset.reloadRequired = "1";
      pricingButton.disabled = false;
      pricingButton.textContent = "Reload to Load Pricing";
    }
    if (wooButton) {
      if (!wooButton.dataset.normalLabel) wooButton.dataset.normalLabel = wooButton.textContent || "Sync Existing Woo Product";
      wooButton.dataset.reloadRequired = "1";
      wooButton.disabled = false;
      wooButton.textContent = "Reload to Continue Woo Sync";
    }

    const detail = `${message} Reload this Athtyp edit page to load the current Style × License inventory safely.`;
    if (pricingStatus) pricingStatus.textContent = detail;
    if (syncStatus) syncStatus.textContent = detail;
  }

  if (stopWooButton) {
    stopWooButton.addEventListener("click", () => {
      wooSyncStopRequested = true;
      stopWooButton.disabled = true;
      const status = document.querySelector(".ath-sync-status");
      if (status) status.textContent = "Woo sync will pause after the current batch. Click Sync Existing Woo Product to resume.";
    });
  }

  if (buildWooButton) {
    const defaultButtonLabel = buildWooButton.textContent;
    buildWooButton.addEventListener("click", async () => {
      if (buildWooButton.dataset.reloadRequired === "1") {
        window.location.reload();
        return;
      }
      const postId = document.querySelector("#post_ID")?.value;
      const productSelect = document.querySelector('[name="ath_linked_product"]');
      const productId = productSelect?.value || "0";
      const styleAttribute = document.querySelector('[name="ath_style_attribute"]')?.value || "pa_style";
      const licenseAttribute = document.querySelector('[name="ath_license_attribute"]')?.value || "pa_license";
      const status = document.querySelector(".ath-sync-status");

      if (!postId) return;
      if (document.body.dataset.athBuilderStale === "1") {
        window.alert("Secure assets changed. Reload this Athtyp edit page before starting Woo Sync.");
        return;
      }
      if (document.body.dataset.athPricingDirty === "1") {
        window.alert("Pricing has unsaved changes. Click Save Pricing Only first; this saves prices without rebuilding any files.");
        return;
      }
      if (!productId || productId === "0") {
        window.alert(AthSpecimenAdmin?.i18n?.selectProduct || "Select a linked product first.");
        return;
      }

      if (!wooSyncResume && !window.confirm("Sync the selected existing WooCommerce variable product in safe batches? Save this Athtyp post first if you changed rows.")) {
        return;
      }

      buildWooButton.disabled = true;
      buildWooButton.textContent = wooSyncResume ? "Resuming Woo Sync..." : "Starting Woo Sync...";
      wooSyncStopRequested = false;
      if (stopWooButton) {
        stopWooButton.hidden = false;
        stopWooButton.disabled = false;
      }
      if (status) status.textContent = wooSyncResume ? "Resuming Woo product sync..." : "Preparing Woo batch sync...";

      try {
        let syncData = wooSyncResume;
        if (syncData?.token && syncData?.product_id && String(syncData.product_id) !== String(productId)) {
          wooSyncResume = null;
          syncData = null;
        }
        if (!syncData?.token) {
          const initBody = new FormData();
          initBody.append("action", "ath_specimen_build_woo");
          initBody.append("nonce", AthSpecimenAdmin.nonce);
          initBody.append("phase", "init");
          initBody.append("post_id", postId);
          initBody.append("product_id", productId);
          initBody.append("style_attribute", styleAttribute);
          initBody.append("license_attribute", licenseAttribute);
          initBody.append("pricing_schema", buildWooButton.dataset.pricingSchema || "");
          initBody.append("pricing_hash", buildWooButton.dataset.pricingHash || "");

          const initResult = await postAdminAjax(initBody);
          if (!initResult.success) throw new Error(initResult?.data?.message || "Could not initialize Woo sync.");
          syncData = initResult.data || {};
          wooSyncResume = syncData;
          applyWorkflowSignature(syncData);
          updateWooProgress(syncData);
          if (status) status.textContent = syncData.message || "Woo batch sync ready.";
        }

        while (!syncData.complete && !wooSyncStopRequested) {
          const batchResult = await requestWooBatch(postId, syncData.token, status);
          if (!batchResult.success) throw new Error(batchResult?.data?.message || "Could not sync Woo batch.");
          syncData = batchResult.data || {};
          wooSyncResume = syncData.complete ? null : syncData;
          applyWorkflowSignature(syncData);
          updateWooProgress(syncData);
          if (status) status.textContent = syncData.message || "Syncing Woo product...";
          if (!syncData.complete) await wait(60);
        }

        if (wooSyncStopRequested && !syncData.complete) {
          wooSyncResume = syncData;
          buildWooButton.textContent = "Resume Woo Sync";
          if (status) status.textContent = `Woo sync paused safely at ${syncData.processed || 0} of ${syncData.total || 0}. Click Resume Woo Sync to continue.`;
          return;
        }

        if (productSelect && syncData?.product_id) {
          const syncedProductId = String(syncData.product_id);
          let option = Array.from(productSelect.options).find(item => item.value === syncedProductId);
          if (!option) {
            option = document.createElement("option");
            option.value = syncedProductId;
            option.textContent = `Woo Product #${syncedProductId}`;
            productSelect.appendChild(option);
          }
          productSelect.value = syncedProductId;
        }

        wooSyncResume = null;
        if (status) status.textContent = syncData?.message || "Woo product synced.";
        document.querySelectorAll(".ath-admin-state").forEach((node) => {
          if (node.textContent.includes("WooCommerce:")) {
            node.classList.remove("is-warning"); node.classList.add("is-good");
            node.innerHTML = "<strong>WooCommerce:</strong> Synced";
          }
        });
      } catch (error) {
        if (error?.data?.restart) {
          wooSyncResume = null;
          buildWooButton.textContent = defaultButtonLabel;
        } else if (wooSyncResume?.token) {
          buildWooButton.textContent = "Resume Woo Sync";
        }
        if (status) {
          const progress = error?.data?.progress;
          if (progress?.total) updateWooProgress(progress);
          const suffix = progress?.total ? ` (${progress.processed || 0}/${progress.total})` : "";
          status.textContent = `${error.message || "Could not sync Woo product."}${suffix}`;
        }
      } finally {
        buildWooButton.disabled = false;
        if (!wooSyncResume) buildWooButton.textContent = defaultButtonLabel;
        if (stopWooButton) {
          stopWooButton.disabled = false;
          stopWooButton.hidden = !wooSyncResume;
        }
      }
    });
  }

  if (syncButton) {
    syncButton.addEventListener("click", async () => {
      const product = document.querySelector('[name="ath_linked_product"]')?.value;
      const styleAttribute = document.querySelector('[name="ath_style_attribute"]')?.value || "pa_style";
      const licenseAttribute = document.querySelector('[name="ath_license_attribute"]')?.value || "pa_license";
      const status = document.querySelector(".ath-sync-status");
      const styleRows = document.querySelector("#ath-font-style-rows");
      const licenseRows = document.querySelector("#ath-license-rows");
      const styleTemplate = document.querySelector("#tmpl-ath-style-row");
      const licenseTemplate = document.querySelector("#tmpl-ath-license-row");

      if (!product || product === "0") {
        window.alert(AthSpecimenAdmin?.i18n?.selectProduct || "Select a linked product first.");
        return;
      }

      const body = new FormData();
      body.append("action", "ath_specimen_sync_woo");
      body.append("nonce", AthSpecimenAdmin.nonce);
      body.append("post_id", document.querySelector("#post_ID")?.value || "");
      body.append("product_id", product);
      body.append("style_attribute", styleAttribute);
      body.append("license_attribute", licenseAttribute);

      syncButton.disabled = true;
      if (status) status.textContent = "Syncing...";

      try {
        const result = await postAdminAjax(body);
        if (!result.success) throw new Error(result?.data?.message || "Sync failed");

        styleRows.innerHTML = "";
        licenseRows.innerHTML = "";
        styleRows.closest(".ath-repeat-table").dataset.nextIndex = "0";
        licenseRows.closest(".ath-repeat-table").dataset.nextIndex = "0";

        result.data.styles.forEach((style, index) => {
          const row = insertTemplate(styleRows, styleTemplate, {
            style_name: style.label,
            style_variation_value: style.value,
            font_file: style.font_file || "",
            font_weight: style.font_weight || "400",
            font_style: style.font_style || "normal"
          });
          const selected = row.querySelector('[name$="[default_selected]"]');
          if (selected) selected.checked = Boolean(style.default_selected) || index === 0;
          const isPackage = row.querySelector('[name$="[is_package]"]');
          if (isPackage) isPackage.checked = Boolean(style.is_package);
        });

        result.data.licenses.forEach(license => {
          insertTemplate(licenseRows, licenseTemplate, {
            license_label: license.label,
            license_variation_value: license.value,
            license_description: license.description || "",
            license_group: license.group || "",
            license_featured: license.featured ? 1 : 0,
            license_checkout_type: license.checkout_type || "",
            license_icon: license.icon || ""
          });
        });

        if (status) status.textContent = result?.data?.message || AthSpecimenAdmin?.i18n?.synced || "Synced from WooCommerce variations.";
        requireCommerceReload("WooCommerce Style/License values were imported and saved to Athtyp.");
      } catch (error) {
        if (status) status.textContent = error.message || AthSpecimenAdmin?.i18n?.failed || "Could not sync variations.";
      } finally {
        syncButton.disabled = false;
      }
    });
  }

  document.addEventListener("click", event => {
    const remove = event.target.closest(".ath-remove-row");
    if (remove) {
      const tbody = remove.closest("tbody");
      remove.closest("tr")?.remove();
      if (tbody) updateMoveButtons(tbody);
      return;
    }

    const moveUp = event.target.closest(".ath-move-up");
    if (moveUp) {
      const row = moveUp.closest("tr");
      const previous = row?.previousElementSibling;
      if (row && previous) {
        previous.before(row);
        updateMoveButtons(row.closest("tbody"));
      }
      return;
    }

    const moveDown = event.target.closest(".ath-move-down");
    if (moveDown) {
      const row = moveDown.closest("tr");
      const next = row?.nextElementSibling;
      if (row && next) {
        next.after(row);
        updateMoveButtons(row.closest("tbody"));
      }
      return;
    }

    const detect = event.target.closest(".ath-detect-style");
    if (detect) {
      const row = detect.closest(".ath-admin-row");
      if (!applyDetectedStyle(row, true)) {
        const status = document.querySelector(".ath-sync-status");
        if (status) status.textContent = "Add a font file first, then run Detect.";
      }
      return;
    }

    const bulkUpload = event.target.closest(".ath-bulk-upload-styles");
    if (bulkUpload) {
      if (!window.wp?.media) return;

      const status = document.querySelector(".ath-sync-status");
      const frame = wp.media({
        title: "Select font files",
        button: { text: "Use these fonts" },
        multiple: true
      });

      frame.on("select", () => {
        const files = frame.state().get("selection").toJSON().filter(file => file?.url);
        files.forEach(file => insertStyleFromFontFile(file));
        if (status) {
          status.textContent = files.length
            ? `${files.length} font files added. Review detected styles, then save/update this Athtyp post.`
            : "No font files selected.";
        }
      });

      frame.open();
      return;
    }

    const bulkDownloads = event.target.closest(".ath-bulk-upload-downloads");
    if (bulkDownloads) {
      if (!window.wp?.media) return;

      const status = document.querySelector(".ath-sync-status");
      const frame = wp.media({
        title: "Select product files",
        button: { text: "Use these files" },
        multiple: true
      });

      frame.on("select", () => {
        const files = frame.state().get("selection").toJSON().filter(file => file?.url);
        files.forEach(file => insertProductDownloadFromFile(file));
        if (status) {
          status.textContent = files.length
            ? `${files.length} product files added. Fill Style/License values if a file should only attach to specific variations, then save/update this Athtyp post.`
            : "No product files selected.";
        }
      });

      frame.open();
      return;
    }

    const csvUpload = event.target.closest(".ath-upload-package-csv");
    if (csvUpload) {
      if (!window.wp?.media) return;

      const input = document.querySelector(".ath-package-csv-url");
      const frame = wp.media({
        title: "Select package CSV",
        button: { text: "Use this CSV" },
        multiple: false
      });

      frame.on("select", () => {
        const file = frame.state().get("selection").first().toJSON();
        if (input) input.value = file.url || "";
      });

      frame.open();
      return;
    }

    const csvImport = event.target.closest(".ath-import-package-csv");
    if (csvImport) {
      const postId = document.querySelector("#post_ID")?.value;
      const csvUrl = document.querySelector(".ath-package-csv-url")?.value || "";
      const replace = document.querySelector(".ath-package-csv-replace")?.checked;
      const status = document.querySelector(".ath-sync-status");

      if (!postId || !csvUrl.trim()) {
        if (status) status.textContent = "Choose a package CSV first.";
        return;
      }

      if (!window.confirm("Import package CSV into Product Download Files, License Options, and Price Matrix? Existing imported rows may be replaced.")) {
        return;
      }

      const body = new FormData();
      body.append("action", "ath_specimen_import_package_csv");
      body.append("nonce", AthSpecimenAdmin.nonce);
      body.append("post_id", postId);
      body.append("csv_url", csvUrl);
      body.append("replace", replace ? "1" : "");

      csvImport.disabled = true;
      if (status) status.textContent = "Importing package CSV...";

      fetch(AthSpecimenAdmin.ajaxUrl, { method: "POST", body })
        .then(response => response.json())
        .then(result => {
          if (!result.success) throw new Error(result?.data?.message || "CSV import failed.");
          if (status) status.textContent = `${result.data.message} Reload this Athtyp edit page to review imported rows.`;
        })
        .catch(error => {
          if (status) status.textContent = error.message || "CSV import failed.";
        })
        .finally(() => {
          csvImport.disabled = false;
        });
      return;
    }

    const packageZipUpload = event.target.closest(".ath-upload-package-font-zip");
    if (packageZipUpload) {
      if (!window.wp?.media) return;

      const input = document.querySelector(".ath-package-font-zip");
      const family = document.querySelector(".ath-package-family-name");
      const frame = wp.media({
        title: "Select font family ZIP",
        button: { text: "Use this ZIP" },
        multiple: false
      });

      frame.on("select", () => {
        const file = frame.state().get("selection").first().toJSON();
        if (input) input.value = file.url || "";
        if (family && !family.value.trim()) {
          family.value = titleCaseStyle(fileNameFromUrl(file.url).replace(/\.zip$/i, ""));
        }
      });

      frame.open();
      return;
    }

    const freeDownloadUpload = event.target.closest(".ath-upload-free-download");
    if (freeDownloadUpload) {
      if (!window.wp?.media) return;

      const input = document.querySelector(".ath-free-download-file");
      const frame = wp.media({
        title: "Select free download file",
        button: { text: "Use this file" },
        multiple: false
      });

      frame.on("select", () => {
        const file = frame.state().get("selection").first().toJSON();
        if (input) input.value = file.url || "";
      });

      frame.open();
      return;
    }

    const packageTemplateUpload = event.target.closest(".ath-upload-package-template");
    if (packageTemplateUpload) {
      if (!window.wp?.media) return;

      const input = packageTemplateUpload.closest("tr, label")?.querySelector(".ath-package-template-zip");
      const frame = wp.media({
        title: "Select license/documentation template ZIP",
        button: { text: "Use this template" },
        multiple: false
      });

      frame.on("select", () => {
        const file = frame.state().get("selection").first().toJSON();
        if (input) input.value = file.url || "";
      });

      frame.open();
      return;
    }

    const savePricing = event.target.closest(".ath-save-pricing");
    if (savePricing) {
      if (savePricing.dataset.reloadRequired === "1") {
        window.location.reload();
        return;
      }
      const postId = document.querySelector("#post_ID")?.value;
      const status = document.querySelector(".ath-pricing-status");
      const matrix = document.querySelector(".ath-price-matrix");
      const pricingInputs = Array.from(document.querySelectorAll('.ath-price-matrix input[name^="ath_price_matrix"]'));
      if (!postId) return;
      if (document.body.dataset.athBuilderStale === "1") {
        if (status) status.textContent = "Secure assets changed. Reload this Athtyp edit page before saving pricing.";
        return;
      }
      if (!matrix || pricingInputs.length === 0) {
        if (status) status.textContent = "Price Matrix is not loaded. Reload this Athtyp edit page before saving pricing.";
        return;
      }
      const body = new FormData();
      body.append("action", "ath_specimen_save_pricing");
      body.append("nonce", AthSpecimenAdmin.nonce);
      body.append("post_id", postId);
      body.append("pricing_schema", savePricing.dataset.pricingSchema || "");
      body.append("pricing_hash", savePricing.dataset.pricingHash || "");
      pricingInputs.forEach((input) => {
        body.append(input.name, input.value || "");
      });
      savePricing.disabled = true;
      if (status) status.textContent = "Saving pricing metadata only…";
      postAdminAjax(body)
        .then((result) => {
          if (!result?.success) throw new Error(result?.data?.message || "Pricing save failed.");
          if (status) status.textContent = result.data.message || "Pricing saved. No files rebuilt.";
          if (result.data.pricing_hash) {
            savePricing.dataset.pricingHash = result.data.pricing_hash;
            const wooButton = document.querySelector(".ath-build-woo");
            if (wooButton) wooButton.dataset.pricingHash = result.data.pricing_hash;
          }
          applyWorkflowSignature(result.data);
          delete document.body.dataset.athPricingDirty;
          document.querySelectorAll(".ath-admin-state").forEach((node) => {
            if (node.textContent.includes("Pricing:")) {
              node.classList.remove("is-warning", "is-good");
              node.classList.add(result.data.pricing_configured ? "is-good" : "is-warning");
              node.innerHTML = result.data.pricing_configured
                ? "<strong>Pricing:</strong> Saved independently"
                : "<strong>Pricing:</strong> Not configured";
            }
            if (node.textContent.includes("WooCommerce:") && !result.data.woo_synced) {
              node.classList.remove("is-good"); node.classList.add("is-warning");
              node.innerHTML = "<strong>WooCommerce:</strong> Needs sync";
            }
          });
        })
        .catch((error) => { if (status) status.textContent = error.message || "Pricing save failed."; })
        .finally(() => { savePricing.disabled = false; });
      return;
    }

    const packageBuild = event.target.closest(".ath-build-packages");
    if (packageBuild) {
      const postId = document.querySelector("#post_ID")?.value;
      const status = document.querySelector(".ath-package-status") || document.querySelector(".ath-sync-status");
      const fontZip = document.querySelector(".ath-package-font-zip")?.value || "";
      const familyName = document.querySelector(".ath-package-family-name")?.value || "";
      const previewFormat = document.querySelector(".ath-package-preview-format")?.value || "woff";
      const secureToken = document.querySelector(".ath-package-secure-token")?.value || "";

      if (!postId || !fontZip.trim()) {
        if (status) status.textContent = "Choose a font family ZIP first.";
        return;
      }

      if (!window.confirm("Build secure font assets from this family ZIP? This updates Font Styles, License Options, Product Download Files, and protected packages. Pricing is preserved and is never rebuilt here.")) {
        return;
      }

      const body = new FormData();
      body.append("action", "ath_specimen_build_packages");
      body.append("nonce", AthSpecimenAdmin.nonce);
      body.append("post_id", postId);
      body.append("font_zip", fontZip);
      body.append("family_name", familyName);
      body.append("preview_format", previewFormat);
      body.append("secure_token", secureToken);
      document.querySelectorAll("#ath-package-license-rows .ath-admin-row").forEach((row, index) => {
        body.append(`package_licenses[${index}][license_label]`, row.querySelector('[name$="[license_label]"]')?.value || "");
        body.append(`package_licenses[${index}][license_variation_value]`, row.querySelector('[name$="[license_variation_value]"]')?.value || "");
        body.append(`package_licenses[${index}][template_zip]`, row.querySelector('[name$="[template_zip]"]')?.value || "");
      });

      packageBuild.disabled = true;
      if (status) status.textContent = "Building secure assets... Pricing will not be changed.";

      fetch(AthSpecimenAdmin.ajaxUrl, { method: "POST", body })
        .then(response => response.json())
        .then(result => {
          if (!result.success) throw new Error(result?.data?.message || "Package build failed.");
          requireCommerceReload("Secure assets were rebuilt and the generated Style/License inventory changed on the server.");
          if (status) status.textContent = `${result.data.message} Click Reload to Load Pricing (or Reload to Continue Woo Sync) to refresh this page safely.`;
        })
        .catch(error => {
          if (status) status.textContent = error.message || "Package build failed.";
        })
        .finally(() => {
          packageBuild.disabled = false;
        });
      return;
    }

    const upload = event.target.closest(".ath-upload-font, .ath-upload-download");
    if (!upload || !window.wp?.media) return;

    const input = upload.closest("td").querySelector(".ath-font-url, .ath-download-url");
    const frame = wp.media({
      title: upload.matches(".ath-upload-download") ? "Select product file" : "Select font file",
      button: { text: upload.matches(".ath-upload-download") ? "Use this file" : "Use this font" },
      multiple: false
    });

    frame.on("select", () => {
      const file = frame.state().get("selection").first().toJSON();
      input.value = file.url || "";
      if (upload.matches(".ath-upload-font")) {
        applyDetectedStyle(upload.closest(".ath-admin-row"));
      }
    });

    frame.open();
  });
});
