(() => {
  "use strict";

  const cfg = window.AthSpecimen || {};
  const renderCache = new Map();
  const metadataCache = new Map();
  const pendingRenders = new WeakMap();
  const timers = new WeakMap();
  const renderQueue = [];
  let activeRenderRequests = 0;
  const maxConcurrentRenderRequests = 3;

  const qs = (root, sel) => root.querySelector(sel);
  const qsa = (root, sel) => Array.from(root.querySelectorAll(sel));

  function canvasNearViewport(canvas, margin = 420) {
    if (!canvas || canvas.closest("[hidden]")) return false;
    const rect = canvas.getBoundingClientRect();
    return rect.bottom >= -margin && rect.top <= (window.innerHeight || document.documentElement.clientHeight) + margin;
  }

  function cacheRender(key, objectUrl) {
    if (renderCache.has(key)) {
      const old = renderCache.get(key);
      if (old && old !== objectUrl && String(old).startsWith("blob:")) URL.revokeObjectURL(old);
      renderCache.delete(key);
    }
    renderCache.set(key, objectUrl);
    const max = 80;
    while (renderCache.size > max) {
      const oldest = renderCache.keys().next().value;
      const value = renderCache.get(oldest);
      if (value && String(value).startsWith("blob:")) URL.revokeObjectURL(value);
      renderCache.delete(oldest);
    }
  }

  function debounceFor(el, fn, delay = 320) {
    if (timers.has(el)) window.clearTimeout(timers.get(el));
    timers.set(el, window.setTimeout(fn, delay));
  }

  function request(action, body) {
    const fd = new FormData();
    fd.append("action", action);
    Object.entries(body || {}).forEach(([key, value]) => {
      if (Array.isArray(value)) value.forEach((item) => fd.append(`${key}[]`, item));
      else fd.append(key, value == null ? "" : String(value));
    });
    return fetch(cfg.ajaxUrl, {
      method: "POST",
      body: fd,
      credentials: "same-origin",
      cache: "no-store"
    }).then(async (res) => {
      let json = null;
      try { json = await res.json(); } catch (_) {}
      if (!res.ok || !json || !json.success) {
        const message = json && json.data && json.data.message ? json.data.message : (cfg.i18n?.failed || "Request failed.");
        throw new Error(message);
      }
      return json.data;
    });
  }

  function scheduleRenderRequest(task) {
    return new Promise((resolve, reject) => {
      renderQueue.push({ task, resolve, reject });
      drainRenderQueue();
    });
  }

  function drainRenderQueue() {
    while (activeRenderRequests < maxConcurrentRenderRequests && renderQueue.length) {
      const job = renderQueue.shift();
      activeRenderRequests += 1;
      Promise.resolve()
        .then(job.task)
        .then(job.resolve, job.reject)
        .finally(() => {
          activeRenderRequests = Math.max(0, activeRenderRequests - 1);
          drainRenderQueue();
        });
    }
  }

  function requestImage(body) {
    return scheduleRenderRequest(async () => {
    const fd = new FormData();
    fd.append("action", "ath_specimen_render_preview");
    Object.entries(body || {}).forEach(([key, value]) => fd.append(key, value == null ? "" : String(value)));
    const res = await fetch(cfg.ajaxUrl, {
      method: "POST",
      body: fd,
      credentials: "same-origin",
      cache: "no-store"
    });
    const type = (res.headers.get("content-type") || "").toLowerCase();
    if (!res.ok || !type.includes("image/png")) {
      let message = cfg.i18n?.renderFailed || "Preview unavailable.";
      try {
        const json = await res.json();
        if (json?.data?.message) message = json.data.message;
      } catch (_) {}
      throw new Error(message);
    }
    const blob = await res.blob();
    return URL.createObjectURL(blob);
    });
  }

  function canvasWidth(canvas) {
    const rect = canvas.getBoundingClientRect();
    const measured = Math.max(280, Math.min(1800, Math.round(rect.width || canvas.parentElement?.clientWidth || 1000)));
    // Reuse server PNGs across tiny viewport/layout differences.
    return Math.max(280, Math.min(1800, Math.round(measured / 40) * 40));
  }

  function drawImageToCanvas(canvas, src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => {
        const cssWidth = canvasWidth(canvas);
        const ratio = window.devicePixelRatio || 1;
        const scale = cssWidth / Math.max(1, img.naturalWidth);
        const cssHeight = Math.max(64, Math.round(img.naturalHeight * scale));
        canvas.style.height = `${cssHeight}px`;
        canvas.width = Math.round(cssWidth * ratio);
        canvas.height = Math.round(cssHeight * ratio);
        const ctx = canvas.getContext("2d");
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, cssWidth, cssHeight);
        ctx.imageSmoothingEnabled = true;
        ctx.drawImage(img, 0, 0, cssWidth, cssHeight);
        canvas.classList.remove("is-loading", "has-error");
        resolve();
      };
      img.onerror = reject;
      img.src = src;
    });
  }

  function drawCanvasMessage(canvas, text) {
    const width = canvasWidth(canvas);
    const ratio = window.devicePixelRatio || 1;
    const height = 84;
    canvas.style.height = `${height}px`;
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    const ctx = canvas.getContext("2d");
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);
    ctx.font = "14px system-ui, sans-serif";
    ctx.fillStyle = "#68707d";
    ctx.fillText(text, 18, 46);
  }

  function renderCanvas(root, canvas, force = false) {
    if (!canvas || canvas.hidden || canvas.closest("[hidden]") || !canvas.dataset.fontToken) return Promise.resolve();
    if (!force && canvas.dataset.rendered === "1" && !canvas.dataset.dirty) return Promise.resolve();
    if (pendingRenders.has(canvas)) {
      if (force || canvas.dataset.dirty) canvas.dataset.rerender = "1";
      return pendingRenders.get(canvas);
    }
    delete canvas.dataset.rerender;

    const text = canvas.dataset.text || "The quick brown fox jumps over the lazy dog";
    const mode = canvas.dataset.mode || "text";
    const glyphItems = canvas.dataset.glyphItems || "";
    const size = Number(canvas.dataset.fontSize || 38);
    const fitSingleLine = canvas.dataset.fitSingleLine === "1";
    const lineHeight = Number(canvas.dataset.lineHeight || 1.18);
    const textColor = root.dataset.textColor || "#111111";
    const bgColor = root.dataset.bgColor || "#ffffff";
    const width = canvasWidth(canvas);
    const cacheKey = ["text-fit-r2", root.dataset.fontPostId, canvas.dataset.fontToken, mode, text, glyphItems, size, fitSingleLine ? 1 : 0, lineHeight, textColor, bgColor, width].join("|");

    const job = (async () => {
      canvas.classList.add("is-loading");
      if (renderCache.has(cacheKey)) {
        await drawImageToCanvas(canvas, renderCache.get(cacheKey));
        canvas.dataset.rendered = "1";
        if (!canvas.dataset.rerender) delete canvas.dataset.dirty;
        return;
      }
      drawCanvasMessage(canvas, cfg.i18n?.loading || "Loading preview…");
      try {
        const imageUrl = await requestImage({
          nonce: cfg.renderNonce || "",
          post_id: root.dataset.fontPostId || "",
          font_token: canvas.dataset.fontToken,
          text,
          mode,
          width,
          font_size: size,
          fit_single_line: fitSingleLine ? "1" : "0",
          line_height: lineHeight,
          text_color: textColor,
          bg_color: bgColor,
          glyph_items: glyphItems
        });
        cacheRender(cacheKey, imageUrl);
        await drawImageToCanvas(canvas, imageUrl);
        canvas.dataset.rendered = "1";
        if (!canvas.dataset.rerender) delete canvas.dataset.dirty;
      } catch (err) {
        canvas.classList.remove("is-loading");
        canvas.classList.add("has-error");
        drawCanvasMessage(canvas, err.message || cfg.i18n?.renderFailed || "Preview unavailable.");
      }
    })().finally(() => {
      pendingRenders.delete(canvas);
      if (canvas.dataset.rerender === "1" && canvasNearViewport(canvas)) {
        delete canvas.dataset.rerender;
        window.setTimeout(() => renderCanvas(root, canvas, true), 0);
      }
    });

    pendingRenders.set(canvas, job);
    return job;
  }

  function markAndRender(root, canvases, delay = 0) {
    canvases.forEach((canvas) => {
      canvas.dataset.dirty = "1";
      if (!canvasNearViewport(canvas)) return;
      if (delay) debounceFor(canvas, () => { if (canvasNearViewport(canvas)) renderCanvas(root, canvas, true); }, delay);
      else renderCanvas(root, canvas, true);
    });
  }

  function renderNearViewport(root, scope) {
    qsa(scope || root, ".ath-server-canvas").forEach((canvas) => {
      if (canvasNearViewport(canvas)) renderCanvas(root, canvas);
    });
  }

  function metadata(root, token) {
    if (!token) return Promise.reject(new Error("Missing font token."));
    const key = `${root.dataset.fontPostId}|${token}`;
    if (metadataCache.has(key)) return metadataCache.get(key);
    const promise = request("ath_specimen_font_metadata", {
      nonce: cfg.renderNonce || "",
      post_id: root.dataset.fontPostId || "",
      font_token: token
    }).catch((err) => {
      metadataCache.delete(key);
      throw err;
    });
    metadataCache.set(key, promise);
    return promise;
  }

  function glyphState(root) {
    if (!root._athGlyphState) root._athGlyphState = { page: 1, pages: 1, perPage: 60, token: "", filter: "all" };
    return root._athGlyphState;
  }

  function glyphPage(root, token, page = 1, perPage = 60, filter = "all") {
    return request("ath_specimen_glyph_page", {
      nonce: cfg.renderNonce || "",
      post_id: root.dataset.fontPostId || "",
      font_token: token,
      page,
      per_page: perPage,
      glyph_filter: filter
    });
  }


  function formatMoney(value) {
    const number = Number(value);
    if (!Number.isFinite(number)) return "—";
    const decimals = Math.max(0, Math.min(6, Number(cfg.priceDecimals ?? 2)));
    const decimal = String(cfg.decimalSeparator ?? ".");
    const thousand = String(cfg.thousandSeparator ?? ",");
    const symbol = String(cfg.currencySymbol || cfg.currency || "$");
    const position = String(cfg.currencyPosition || "left");
    const fixed = Math.abs(number).toFixed(decimals);
    let [whole, fraction = ""] = fixed.split(".");
    whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, thousand);
    let amount = decimals ? `${whole}${decimal}${fraction}` : whole;
    if (number < 0) amount = `-${amount}`;
    if (position === "right") return `${amount}${symbol}`;
    if (position === "right_space") return `${amount} ${symbol}`;
    if (position === "left_space") return `${symbol} ${amount}`;
    return `${symbol}${amount}`;
  }

  function parsePriceMap(root) {
    try { return JSON.parse(root.dataset.priceMap || "{}"); }
    catch (_) { return {}; }
  }

  function initTabs(root) {
    qsa(root, ".ath-tab").forEach((button) => {
      button.addEventListener("click", () => {
        const name = button.dataset.tab;
        qsa(root, ".ath-tab").forEach((tab) => {
          const activeTab = tab === button;
          tab.classList.toggle("is-active", activeTab);
          tab.setAttribute("aria-selected", activeTab ? "true" : "false");
        });
        qsa(root, ".ath-tab-panel").forEach((panel) => {
          const active = panel.dataset.panel === name;
          panel.hidden = !active;
          panel.classList.toggle("is-active", active);
          if (active) renderNearViewport(root, panel);
        });
        if (name === "tech-specs") loadTech(root);
        if (name === "glyphs") loadGlyphs(root);
      });
    });
  }

  function initPreviewToolbar(root) {
    const toolbars = qsa(root, ".ath-preview-toolbar");
    if (!toolbars.length) return;

    // Family Packages and Individual Styles are two views of the same specimen.
    // Keep their controls synchronized while only rendering canvases that are
    // visible/near the viewport. This is presentation state only; pricing,
    // licenses, package inventory, Woo variations, and secure tokens are untouched.
    const synced = () => qsa(root, '.ath-server-canvas[data-sync-master="1"]');
    const allInputs = () => qsa(root, ".ath-preview-toolbar .ath-master-text");
    const allSizes = () => qsa(root, ".ath-preview-toolbar .ath-size");
    const allColors = () => qsa(root, ".ath-preview-toolbar .ath-text-color");
    const defaultText = "The quick brown fox jumps over the lazy dog";

    const setTextControls = (value, source = null) => {
      allInputs().forEach((item) => { if (item !== source) item.value = value; });
    };
    const setSizeControls = (value, source = null) => {
      allSizes().forEach((item) => { if (item !== source) item.value = value; });
    };
    const setColorControls = (value, source = null) => {
      allColors().forEach((item) => { if (item !== source) item.value = value; });
    };

    toolbars.forEach((toolbar) => {
      const input = qs(toolbar, ".ath-master-text");
      const size = qs(toolbar, ".ath-size");
      const color = qs(toolbar, ".ath-text-color");
      const reset = qs(toolbar, ".ath-reset");

      if (input) input.addEventListener("input", () => {
        const value = input.value || " ";
        setTextControls(input.value, input);
        synced().forEach((canvas) => {
          canvas.dataset.text = value;
          // Explicit user copy must respect the selected size. The 8.3.9
          // single-line ink-fit remains reserved for the untouched default.
          canvas.dataset.fitSingleLine = "0";
        });
        markAndRender(root, synced(), 360);
      });

      if (size) size.addEventListener("input", () => {
        setSizeControls(size.value, size);
        synced().forEach((canvas) => {
          canvas.dataset.fontSize = size.value;
          canvas.dataset.fitSingleLine = "0";
        });
        markAndRender(root, synced(), 120);
      });

      if (color) color.addEventListener("input", () => {
        setColorControls(color.value, color);
        root.dataset.textColor = color.value;
        markAndRender(root, synced(), 100);
      });

      if (reset) reset.addEventListener("click", () => {
        setTextControls(defaultText);
        setSizeControls("38");
        setColorControls("#111111");
        root.dataset.textColor = "#111111";
        synced().forEach((canvas) => {
          canvas.dataset.text = defaultText;
          canvas.dataset.fontSize = "38";
          canvas.dataset.fitSingleLine = "1";
        });
        markAndRender(root, synced());
      });

      const featureBtn = qs(toolbar, ".ath-feature-menu-btn");
      const featurePopover = qs(toolbar, ".ath-feature-popover");
      if (featureBtn && featurePopover) {
        featureBtn.addEventListener("click", async () => {
          const opening = featurePopover.hidden;
          qsa(root, ".ath-feature-popover").forEach((popover) => {
            if (popover !== featurePopover) popover.hidden = true;
          });
          qsa(root, ".ath-feature-menu-btn").forEach((button) => {
            if (button !== featureBtn) button.setAttribute("aria-expanded", "false");
          });
          featurePopover.hidden = !opening;
          featureBtn.setAttribute("aria-expanded", opening ? "true" : "false");
          if (!opening) return;
          const first = qs(root, ".ath-tech-style-select option:checked") || qs(root, ".ath-tech-style-select option");
          if (!first) return;
          const target = qs(featurePopover, "[data-feature-list]");
          try {
            const info = await metadata(root, first.dataset.token);
            target.textContent = info.features?.length ? info.features.join(" · ") : "No OpenType feature tags detected.";
          } catch (err) { target.textContent = err.message; }
        });
      }
    });
  }

  async function loadGlyphs(root, targetPage = null) {
    const select = qs(root, ".ath-glyph-style-select");
    const canvas = qs(root, ".ath-glyph-canvas");
    if (!select || !canvas) return;
    const option = select.options[select.selectedIndex];
    if (!option) return;
    const token = option.dataset.token || "";
    const state = glyphState(root);
    const styleChanged = token !== state.token;
    state.token = token;
    const page = Number.isInteger(targetPage) && targetPage > 0 ? targetPage : (styleChanged ? 1 : (state.page || 1));
    canvas.dataset.fontToken = token;
    const title = qs(root, ".ath-glyph-title");
    if (title) title.textContent = option.textContent || "";
    try {
      const info = await metadata(root, token);
      const total = Number(info.glyph_total || info.tech?.glyph_count || 0);
      const unicodeCount = Number(info.glyph_unicode || 0);
      const unencodedCount = Number(info.glyph_unencoded || Math.max(0, total - unicodeCount));
      const count = qs(root, "[data-glyph-count]");
      if (count) count.textContent = total ? `${total.toLocaleString()} glyphs total` : "";
      const coverage = qs(root, "[data-glyph-coverage]");
      if (coverage) coverage.textContent = total ? `${unicodeCount.toLocaleString()} Unicode • ${unencodedCount.toLocaleString()} unencoded` : "";
      const pageData = await glyphPage(root, token, page, state.perPage || 60, state.filter || "all");
      state.page = Number(pageData.page || 1);
      state.pages = Number(pageData.pages || 1);
      state.perPage = Number(pageData.per_page || 60);
      canvas.dataset.glyphItems = JSON.stringify(pageData.items || []);
      canvas.dataset.text = "";
      const range = qs(root, "[data-glyph-range]");
      if (range) {
        const from = Number(pageData.from || 0);
        const to = Number(pageData.to || 0);
        const filteredTotal = Number(pageData.total || 0);
        const filterLabel = (pageData.filter || state.filter || "all") === "all" ? "All glyphs" : ((pageData.filter || state.filter) === "unicode" ? "Unicode" : "Unencoded");
        range.textContent = filteredTotal ? `Showing ${from.toLocaleString()}–${to.toLocaleString()} of ${filteredTotal.toLocaleString()} • ${filterLabel} • exact GID raster` : `No glyphs in ${filterLabel}`;
      }
      const pageLabel = qs(root, "[data-glyph-page]");
      if (pageLabel) pageLabel.textContent = `Page ${state.page} of ${state.pages}`;
      const prev = qs(root, "[data-glyph-prev]");
      const next = qs(root, "[data-glyph-next]");
      if (prev) prev.disabled = state.page <= 1;
      if (next) next.disabled = state.page >= state.pages;
      markAndRender(root, [canvas]);
    } catch (err) {
      drawCanvasMessage(canvas, err.message);
    }
  }

  function initGlyphs(root) {
    const select = qs(root, ".ath-glyph-style-select");
    if (select) select.addEventListener("change", () => loadGlyphs(root, 1));
    qsa(root, "[data-glyph-filter]").forEach((button) => {
      button.addEventListener("click", () => {
        const state = glyphState(root);
        state.filter = button.dataset.glyphFilter || "all";
        state.page = 1;
        qsa(root, "[data-glyph-filter]").forEach((item) => item.classList.toggle("is-active", item === button));
        loadGlyphs(root, 1);
      });
    });
    const prev = qs(root, "[data-glyph-prev]");
    const next = qs(root, "[data-glyph-next]");
    if (prev) prev.addEventListener("click", () => {
      const state = glyphState(root);
      if (state.page > 1) loadGlyphs(root, state.page - 1);
    });
    if (next) next.addEventListener("click", () => {
      const state = glyphState(root);
      if (state.page < state.pages) loadGlyphs(root, state.page + 1);
    });
  }

  function humanFileSize(bytes) {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value <= 0) return "—";
    const units = ["B", "KB", "MB", "GB"];
    let size = value;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit += 1; }
    return `${size >= 100 || unit === 0 ? size.toFixed(0) : size.toFixed(1)} ${units[unit]}`;
  }

  function techValue(key, value) {
    if (key === "file_size") return humanFileSize(value);
    if (["glyph_count", "unicode_characters", "encoded_glyphs", "unencoded_glyphs", "units_per_em", "typo_ascender", "typo_descender", "typo_line_gap", "hhea_ascender", "hhea_descender", "win_ascent", "win_descent", "cap_height", "x_height", "underline_position", "underline_thickness"].includes(key)) {
      const number = Number(value);
      return Number.isFinite(number) ? number.toLocaleString() : "—";
    }
    if (value === 0 || value === "0") return "0";
    if (value == null || value === "" || value === false) return "—";
    return String(value);
  }

  function renderChips(target, values, className = "ath-feature-chip") {
    if (!target) return;
    target.innerHTML = "";
    if (!values || !values.length) { target.textContent = "—"; return; }
    values.forEach((value) => {
      const chip = document.createElement("span");
      chip.className = className;
      chip.textContent = value;
      target.appendChild(chip);
    });
  }

  function renderLanguages(root, info) {
    const languages = Array.isArray(info.languages) ? info.languages : [];
    const count = qs(root, "[data-tech-language-count]");
    if (count) count.textContent = languages.length ? `${languages.length.toLocaleString()} languages` : "No catalog match";
    const target = qs(root, "[data-tech-languages]");
    if (target) {
      target.innerHTML = "";
      if (!languages.length) {
        target.textContent = "No complete language repertoire from the built-in detection catalog was found.";
      } else {
        const groups = new Map();
        languages.forEach((language) => {
          const script = language.script || "Other";
          if (!groups.has(script)) groups.set(script, []);
          groups.get(script).push(language);
        });
        groups.forEach((items, script) => {
          const group = document.createElement("section");
          group.className = "ath-language-group";
          const heading = document.createElement("h5");
          heading.textContent = `${script} · ${items.length}`;
          group.appendChild(heading);
          const list = document.createElement("div");
          list.className = "ath-language-list";
          items.forEach((language) => {
            const chip = document.createElement("span");
            chip.className = "ath-language-chip";
            chip.textContent = language.name || language.code || "";
            if (language.code) chip.title = language.code;
            list.appendChild(chip);
          });
          group.appendChild(list);
          target.appendChild(group);
        });
      }
    }
    const scripts = Array.isArray(info.scripts) ? info.scripts : [];
    renderChips(qs(root, "[data-tech-scripts]"), scripts.map((item) => `${item.name} · ${Number(item.characters || 0).toLocaleString()}`), "ath-script-chip");
  }

  function renderVariableAxes(root, axes) {
    const card = qs(root, "[data-tech-axes-card]");
    const target = qs(root, "[data-tech-axes]");
    axes = Array.isArray(axes) ? axes : [];
    if (card) card.hidden = !axes.length;
    if (!target) return;
    target.innerHTML = "";
    axes.forEach((axis) => {
      const row = document.createElement("div");
      row.className = "ath-axis-row";
      const label = document.createElement("strong");
      label.textContent = `${axis.name || axis.tag} (${axis.tag})`;
      const range = document.createElement("span");
      range.textContent = `${axis.min} → ${axis.default} → ${axis.max}`;
      row.append(label, range);
      target.appendChild(row);
    });
  }

  function renderFontNotes(root, tech) {
    const card = qs(root, "[data-tech-notes-card]");
    const desc = qs(root, "[data-tech-description]");
    const license = qs(root, "[data-tech-license-description]");
    const description = String(tech?.description || "").trim();
    const licenseText = String(tech?.license_description || "").trim();
    if (desc) {
      desc.hidden = !description;
      const p = qs(desc, "p");
      if (p) p.textContent = description;
    }
    if (license) {
      license.hidden = !licenseText;
      const p = qs(license, "p");
      if (p) p.textContent = licenseText;
    }
    if (card) card.hidden = !(description || licenseText);
  }

  async function loadTech(root) {
    const select = qs(root, ".ath-tech-style-select");
    const canvas = qs(root, ".ath-tech-preview");
    if (!select || !canvas) return;
    const option = select.options[select.selectedIndex];
    if (!option) return;
    canvas.dataset.fontToken = option.dataset.token || "";
    markAndRender(root, [canvas]);
    try {
      const info = await metadata(root, canvas.dataset.fontToken);
      const tech = info.tech || {};
      qsa(root, "[data-tech]").forEach((node) => {
        const key = node.dataset.tech;
        node.textContent = techValue(key, tech[key]);
      });
      renderChips(qs(root, "[data-tech-features]"), info.features || []);
      renderChips(qs(root, "[data-tech-tables]"), Array.isArray(tech.table_tags) ? tech.table_tags : [], "ath-table-chip");
      renderLanguages(root, info);
      renderVariableAxes(root, tech.variable_axes || []);
      renderFontNotes(root, tech);
    } catch (err) {
      qsa(root, "[data-tech]").forEach((node) => { node.textContent = "—"; });
      renderChips(qs(root, "[data-tech-features]"), []);
      renderChips(qs(root, "[data-tech-tables]"), []);
      const languageTarget = qs(root, "[data-tech-languages]");
      if (languageTarget) languageTarget.textContent = err.message || "Metadata unavailable.";
    }
  }

  function initTech(root) {
    const select = qs(root, ".ath-tech-style-select");
    if (select) select.addEventListener("change", () => loadTech(root));
  }

  function initModal(root) {
    const modal = qs(root, ".ath-license-modal");
    if (!modal) return;
    const priceMap = parsePriceMap(root);
    const cards = qsa(modal, ".ath-license-option");
    const adaptive = cards.length > 6;
    const searchInput = qs(modal, ".ath-license-search");
    const moreButton = qs(modal, "[data-more-licenses]");
    const heading = qs(modal, "[data-license-picker-heading]");
    const visibleCount = qs(modal, "[data-license-visible-count]");
    const noResults = qs(modal, "[data-license-no-results]");
    const styleRows = qsa(root, ".ath-individual-row[data-style-value]");
    const styleBoxes = qsa(root, ".ath-style-select");
    const multiBar = qs(root, "[data-multi-style-bar]");
    const bundleStyle = String(root.dataset.bundleStyle || "");
    const maxStyles = Math.max(1, Number(cfg.multiStyleMaxStyles || 50));
    const maxLicenses = Math.max(1, Number(cfg.multiStyleMaxLicenses || 10));
    const maxCombinations = Math.max(1, Number(cfg.multiStyleMaxCombinations || 100));
    const styleNames = new Map();
    styleRows.forEach((row) => styleNames.set(String(row.dataset.styleValue || ""), String(row.dataset.styleName || row.dataset.styleValue || "")));

    let selectedStyles = [];
    let packageLabel = "";
    let showMore = false;
    let groupFilter = "all";

    function i18n(key, fallback) {
      return cfg.i18n?.[key] || fallback;
    }

    function isContact(card) {
      return card?.dataset.licenseCheckoutType === "contact";
    }

    function checkboxFor(card) {
      return qs(card, 'input[type="checkbox"]');
    }

    function selectedCards() {
      return cards.filter((card) => checkboxFor(card)?.checked && !isContact(card) && !card.classList.contains("is-unavailable"));
    }

    function isCommon(card) {
      return card.dataset.licenseGroup === "common" || card.dataset.licenseFeatured === "1";
    }

    function availableCards() {
      return cards.filter((card) => !isContact(card) && !card.classList.contains("is-unavailable"));
    }

    function syncSelectedClasses() {
      cards.forEach((card) => card.classList.toggle("is-selected", Boolean(checkboxFor(card)?.checked)));
    }

    function selectedStyleNameList() {
      return selectedStyles.map((style) => styleNames.get(style) || style).filter(Boolean);
    }

    function selectionLabel() {
      if (selectedStyles.length === 1) return packageLabel || selectedStyleNameList()[0] || selectedStyles[0];
      const names = selectedStyleNameList();
      const visible = names.slice(0, 3).join(" · ");
      return names.length > 3 ? `${names.length} styles — ${visible} +${names.length - 3}` : `${names.length} styles — ${visible}`;
    }

    function setCartMessage(text, error = false) {
      const message = qs(modal, ".ath-cart-message");
      if (!message) return;
      message.textContent = text || "";
      message.hidden = !text;
      message.classList.toggle("is-error", Boolean(error));
    }

    function updateMultiStyleBar() {
      if (!multiBar || !styleBoxes.length) return;
      const selected = styleBoxes.filter((box) => box.checked);
      const countNode = qs(multiBar, "[data-style-selection-count]");
      const namesNode = qs(multiBar, "[data-style-selection-names]");
      multiBar.hidden = selected.length === 0;
      styleRows.forEach((row) => {
        const box = qs(row, ".ath-style-select");
        row.classList.toggle("is-multi-selected", Boolean(box?.checked));
      });
      if (!selected.length) return;
      if (countNode) countNode.textContent = `${selected.length} ${selected.length === 1 ? i18n("styleSelected", "style selected") : i18n("stylesSelected", "styles selected")}`;
      if (namesNode) {
        const names = selected.map((box) => styleNames.get(box.value) || box.value);
        namesNode.textContent = names.length <= 4 ? names.join(" · ") : `${names.slice(0, 4).join(" · ")} +${names.length - 4}`;
      }
    }

    function applyAdaptiveVisibility() {
      if (!adaptive) {
        cards.forEach((card) => { card.hidden = false; });
        if (noResults) noResults.hidden = true;
        return;
      }
      const query = (searchInput?.value || "").trim().toLowerCase();
      const searching = query.length > 0;
      let visible = 0;
      let hiddenMore = 0;
      cards.forEach((card) => {
        const searchable = card.dataset.licenseSearch || "";
        const group = card.dataset.licenseGroup || "extended";
        const matchesSearch = !searching || searchable.includes(query);
        const matchesGroup = groupFilter === "all" || group === groupFilter;
        const primary = isCommon(card);
        const selected = Boolean(checkboxFor(card)?.checked);
        let show = matchesSearch && matchesGroup;
        if (!searching && groupFilter === "all" && !showMore && !primary && !selected) {
          show = false;
          hiddenMore += 1;
        }
        card.hidden = !show;
        if (show) visible += 1;
      });

      if (!searching && groupFilter === "all" && !showMore && visible === 0 && cards.length) {
        cards.slice(0, Math.min(4, cards.length)).forEach((card) => { card.hidden = false; });
        visible = Math.min(4, cards.length);
        hiddenMore = Math.max(0, cards.length - visible);
      }

      if (heading) {
        if (searching) heading.textContent = "Search results";
        else if (groupFilter !== "all") heading.textContent = `${groupFilter.charAt(0).toUpperCase()}${groupFilter.slice(1)} licenses`;
        else heading.textContent = showMore ? "All licenses" : "Most common licenses";
      }
      if (visibleCount) visibleCount.textContent = visible ? `${visible} shown` : "";
      if (noResults) noResults.hidden = visible > 0;
      if (moreButton) {
        const canToggle = !searching && groupFilter === "all" && (hiddenMore > 0 || showMore);
        moreButton.closest(".ath-more-licenses-wrap").hidden = !canToggle;
        moreButton.setAttribute("aria-expanded", showMore ? "true" : "false");
        const label = qs(moreButton, "[data-more-label]");
        if (label) label.textContent = showMore ? "Show common licenses" : (hiddenMore ? `Show more licenses (${hiddenMore})` : "Show more licenses");
      }
    }

    function updateFamilyRecommendation() {
      const recommendation = qs(modal, "[data-family-saving-recommendation]");
      if (!recommendation) return;
      recommendation.hidden = true;
      if (!bundleStyle || selectedStyles.length < 2 || selectedStyles.includes(bundleStyle)) return;
      const chosen = selectedCards();
      if (!chosen.length) return;

      let individualTotal = 0;
      let familyTotal = 0;
      for (const card of chosen) {
        const license = card.dataset.licenseValue;
        const current = Number(card.dataset.price || 0);
        const family = priceMap?.[bundleStyle]?.[license];
        const familyPrice = family ? Number(family.price) : 0;
        if (!(current > 0) || !(familyPrice > 0)) return;
        individualTotal += current;
        familyTotal += familyPrice;
      }
      if (!(familyTotal < individualTotal)) return;

      const saving = individualTotal - familyTotal;
      const extra = Math.max(0, styleRows.length - selectedStyles.length);
      const detail = qs(recommendation, "[data-family-saving-detail]");
      if (detail) {
        detail.textContent = extra > 0
          ? `${formatMoney(familyTotal)} · Save ${formatMoney(saving)} and get ${extra} more ${extra === 1 ? "style" : "styles"}.`
          : `${formatMoney(familyTotal)} · Save ${formatMoney(saving)}.`;
      }
      recommendation.hidden = false;
    }

    function updateCheckoutSummary() {
      const selected = selectedCards();
      const nameNode = qs(modal, "[data-summary-license]");
      const priceNode = qs(modal, "[data-summary-price]");
      const regularNode = qs(modal, "[data-summary-regular]");
      const discountNode = qs(modal, "[data-summary-discount]");
      const add = qs(modal, ".ath-add-to-cart");
      const selectedTotalBox = qs(modal, "[data-selected-styles-total-box]");
      const selectedTotalNode = qs(modal, "[data-selected-styles-total]");
      const selectedRegularNode = qs(modal, "[data-selected-styles-regular]");
      const selectedDiscountNode = qs(modal, "[data-selected-styles-discount]");
      const selectedMetaNode = qs(modal, "[data-selected-styles-total-meta]");

      if (selectedTotalBox) selectedTotalBox.hidden = selectedStyles.length < 2 || (bundleStyle && selectedStyles.includes(bundleStyle));

      let price = 0;
      let regular = 0;
      selected.forEach((card) => {
        price += Number(card.dataset.price || 0);
        regular += Number(card.dataset.regular || card.dataset.price || 0);
      });

      if (!selected.length) {
        if (nameNode) nameNode.textContent = "Choose one or more licenses";
        if (priceNode) priceNode.textContent = "—";
        if (selectedTotalNode) selectedTotalNode.textContent = "—";
        if (selectedMetaNode) selectedMetaNode.textContent = `${selectedStyles.length} ${selectedStyles.length === 1 ? i18n("styleSelected", "style selected") : i18n("stylesSelected", "styles selected")}`;
        if (selectedRegularNode) { selectedRegularNode.hidden = true; selectedRegularNode.textContent = ""; }
        if (selectedDiscountNode) { selectedDiscountNode.hidden = true; selectedDiscountNode.textContent = ""; }
        if (regularNode) { regularNode.hidden = true; regularNode.textContent = ""; }
        if (discountNode) { discountNode.hidden = true; discountNode.textContent = ""; }
        if (add) add.disabled = true;
        updateFamilyRecommendation();
        return;
      }

      if (nameNode) {
        const labels = selected.map((card) => card.dataset.licenseLabel || card.dataset.licenseValue).filter(Boolean);
        const licenseLabel = labels.length <= 2 ? labels.join(" + ") : `${labels.length} licenses selected`;
        nameNode.textContent = selectedStyles.length > 1 ? `${selectedStyles.length} styles × ${licenseLabel}` : licenseLabel;
      }
      if (priceNode) priceNode.textContent = formatMoney(price);
      if (selectedTotalNode) selectedTotalNode.textContent = formatMoney(price);
      if (selectedMetaNode) {
        const styleCount = selectedStyles.length;
        const licenseCount = selected.length;
        selectedMetaNode.textContent = `${styleCount} ${styleCount === 1 ? "style" : "styles"} × ${licenseCount} ${licenseCount === 1 ? "license" : "licenses"}`;
      }
      if (selectedRegularNode) {
        selectedRegularNode.hidden = !(regular > price);
        selectedRegularNode.textContent = regular > price ? formatMoney(regular) : "";
      }
      if (selectedDiscountNode) {
        const selectedDiscount = regular > price && regular > 0 ? Math.round(((regular - price) / regular) * 100) : 0;
        selectedDiscountNode.hidden = !selectedDiscount;
        selectedDiscountNode.textContent = selectedDiscount ? `${selectedDiscount}% off` : "";
      }
      if (regularNode) {
        regularNode.hidden = !(regular > price);
        regularNode.textContent = regular > price ? formatMoney(regular) : "";
      }
      if (discountNode) {
        const discount = regular > price && regular > 0 ? Math.round(((regular - price) / regular) * 100) : 0;
        discountNode.hidden = !discount;
        discountNode.textContent = discount ? `${discount}% off` : "";
      }
      const combinations = selectedStyles.length * selected.length;
      if (add) add.disabled = selected.length > maxLicenses || selectedStyles.length > maxStyles || combinations > maxCombinations;
      updateFamilyRecommendation();
    }

    function updatePrices() {
      cards.forEach((card) => {
        const box = checkboxFor(card);
        const scopeNode = qs(card, "[data-license-scope]");
        if (isContact(card)) {
          card.classList.remove("is-unavailable");
          card.dataset.price = "";
          card.dataset.regular = "";
          if (scopeNode) scopeNode.textContent = selectedStyles.length > 1 ? "Custom quote for selected styles" : "";
          if (box) { box.checked = false; box.disabled = true; }
          return;
        }

        const license = card.dataset.licenseValue;
        let price = 0;
        let regular = 0;
        let valid = selectedStyles.length > 0;
        const missing = [];
        selectedStyles.forEach((style) => {
          const item = priceMap?.[style]?.[license];
          if (!item || !Number.isFinite(Number(item.price)) || Number(item.price) <= 0) {
            valid = false;
            missing.push(styleNames.get(style) || style);
            return;
          }
          price += Number(item.price);
          regular += Number.isFinite(Number(item.regular)) ? Number(item.regular) : Number(item.price);
        });
        const priceNode = qs(card, "[data-license-price]");
        const regularNode = qs(card, "[data-license-regular]");
        const discountNode = qs(card, "[data-license-discount]");
        if (!valid) {
          if (priceNode) priceNode.textContent = "Not available";
          if (regularNode) { regularNode.hidden = true; regularNode.textContent = ""; }
          if (discountNode) { discountNode.hidden = true; discountNode.textContent = ""; }
          if (scopeNode) {
            const names = missing.slice(0, 2).join(" · ");
            scopeNode.textContent = missing.length ? `${i18n("notAvailableFor", "Not available for")} ${names}${missing.length > 2 ? ` +${missing.length - 2}` : ""}` : "";
          }
          card.classList.add("is-unavailable");
          card.dataset.price = "";
          card.dataset.regular = "";
          if (box) { box.checked = false; box.disabled = true; }
          return;
        }
        card.classList.remove("is-unavailable");
        card.dataset.price = String(price);
        card.dataset.regular = String(regular);
        if (scopeNode) scopeNode.textContent = selectedStyles.length > 1 ? `${i18n("availableAllStyles", "Available for all selected styles")} (${selectedStyles.length})` : "";
        if (box) box.disabled = false;
        if (priceNode) priceNode.textContent = formatMoney(price);
        if (regularNode) {
          regularNode.hidden = !(regular > price);
          regularNode.textContent = regular > price ? formatMoney(regular) : "";
        }
        if (discountNode) {
          const discount = regular > price && regular > 0 ? Math.round(((regular - price) / regular) * 100) : 0;
          discountNode.hidden = !discount;
          discountNode.textContent = discount ? `${discount}% off` : "";
        }
      });
      syncSelectedClasses();
      applyAdaptiveVisibility();
      updateCheckoutSummary();
    }

    function selectDefaultLicense() {
      let preferred = cards.find((card) => card.dataset.licenseFeatured === "1" && !isContact(card) && !card.classList.contains("is-unavailable"));
      if (!preferred) preferred = availableCards()[0];
      const box = preferred ? checkboxFor(preferred) : null;
      if (box) box.checked = true;
      syncSelectedClasses();
      updateCheckoutSummary();
      return box;
    }

    function setSelectedStyles(styles, label = "") {
      selectedStyles = Array.from(new Set((styles || []).map((style) => String(style || "")).filter(Boolean))).slice(0, maxStyles);
      packageLabel = label || "";
    }

    function openSelection(styles, label = "") {
      setSelectedStyles(styles, label);
      if (!selectedStyles.length) return;
      showMore = false;
      groupFilter = "all";
      if (searchInput) searchInput.value = "";
      qsa(modal, "[data-license-group-filter]").forEach((button) => button.classList.toggle("is-active", button.dataset.licenseGroupFilter === "all"));
      const selected = qs(modal, "[data-selected-package]");
      if (selected) selected.textContent = selectionLabel();
      cards.forEach((card) => {
        const box = checkboxFor(card);
        if (box) box.checked = false;
        card.classList.remove("is-selected");
      });
      setCartMessage("");
      const actions = qs(modal, ".ath-cart-actions");
      if (actions) actions.hidden = true;
      updatePrices();
      const defaultBox = selectDefaultLicense();
      modal.hidden = false;
      document.documentElement.classList.add("ath-modal-open");
      defaultBox?.focus();
    }

    function open(style, label) {
      openSelection([style], label || style);
    }

    function close() {
      modal.hidden = true;
      document.documentElement.classList.remove("ath-modal-open");
    }

    if (moreButton) moreButton.addEventListener("click", () => {
      showMore = !showMore;
      applyAdaptiveVisibility();
    });

    searchInput?.addEventListener("input", () => {
      if (searchInput.value.trim()) showMore = true;
      applyAdaptiveVisibility();
    });

    qsa(modal, "[data-license-group-filter]").forEach((button) => {
      button.addEventListener("click", () => {
        groupFilter = button.dataset.licenseGroupFilter || "all";
        showMore = groupFilter !== "all";
        qsa(modal, "[data-license-group-filter]").forEach((item) => item.classList.toggle("is-active", item === button));
        applyAdaptiveVisibility();
      });
    });

    qsa(root, ".ath-buy-choice").forEach((button) => button.addEventListener("click", () => open(button.dataset.styleValue, button.dataset.packageLabel)));

    styleBoxes.forEach((box) => box.addEventListener("change", () => {
      const selected = styleBoxes.filter((item) => item.checked);
      if (selected.length > maxStyles) {
        box.checked = false;
        const row = box.closest(".ath-individual-row");
        row?.classList.add("ath-selection-limit-pulse");
        window.setTimeout(() => row?.classList.remove("ath-selection-limit-pulse"), 500);
      }
      updateMultiStyleBar();
    }));

    qs(root, "[data-clear-style-selection]")?.addEventListener("click", () => {
      styleBoxes.forEach((box) => { box.checked = false; });
      updateMultiStyleBar();
    });

    qs(root, "[data-choose-selected-styles]")?.addEventListener("click", () => {
      const selected = styleBoxes.filter((box) => box.checked);
      if (!selected.length) return;
      const styles = selected.map((box) => box.value);
      const names = styles.map((style) => styleNames.get(style) || style);
      openSelection(styles, names.join(" · "));
    });

    qs(modal, "[data-switch-to-family]")?.addEventListener("click", () => {
      if (!bundleStyle) return;
      setSelectedStyles([bundleStyle], i18n("fullFamily", "Full Style Bundle"));
      const selected = qs(modal, "[data-selected-package]");
      if (selected) selected.textContent = packageLabel;
      updatePrices();
    });

    qsa(modal, "[data-close-modal]").forEach((button) => button.addEventListener("click", close));
    document.addEventListener("keydown", (event) => { if (!modal.hidden && event.key === "Escape") close(); });

    qsa(modal, 'input[type="checkbox"]').forEach((box) => box.addEventListener("change", () => {
      if (box.checked && selectedCards().length > maxLicenses) {
        box.checked = false;
        setCartMessage(`You can select up to ${maxLicenses} licenses at once.`, true);
      } else {
        setCartMessage("");
      }
      syncSelectedClasses();
      applyAdaptiveVisibility();
      updateCheckoutSummary();
    }));
    qsa(modal, ".ath-license-option a").forEach((link) => link.addEventListener("click", (event) => event.stopPropagation()));

    const add = qs(modal, ".ath-add-to-cart");
    if (add) add.addEventListener("click", async () => {
      const chosen = selectedCards();
      const licenses = chosen.map((card) => card.dataset.licenseValue).filter(Boolean);
      if (!licenses.length || !selectedStyles.length) return;
      const combinations = licenses.length * selectedStyles.length;
      if (licenses.length > maxLicenses || selectedStyles.length > maxStyles || combinations > maxCombinations) {
        setCartMessage(`Choose no more than ${maxStyles} styles, ${maxLicenses} licenses, or ${maxCombinations} total combinations at once.`, true);
        return;
      }
      add.disabled = true;
      const old = add.textContent;
      add.textContent = combinations > 1 ? `Adding ${combinations} items…` : "Adding…";
      try {
        const data = await request("ath_specimen_add_to_cart", {
          nonce: cfg.nonce || "",
          product_id: root.dataset.productId || "",
          licenses,
          styles: selectedStyles
        });
        setCartMessage(data.message || cfg.i18n?.added || "Added to cart.", false);
        const actions = qs(modal, ".ath-cart-actions");
        if (actions) actions.hidden = false;
      } catch (err) {
        setCartMessage(err.message, true);
      } finally {
        add.disabled = false;
        updateCheckoutSummary();
        add.textContent = old;
      }
    });

    updateMultiStyleBar();
  }

  function initLazyRender(root) {
    const canvases = qsa(root, ".ath-server-canvas");
    if (!("IntersectionObserver" in window)) {
      canvases.forEach((canvas) => renderCanvas(root, canvas));
      return;
    }
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting && !entry.target.closest("[hidden]")) {
          renderCanvas(root, entry.target);
        }
      });
    }, { rootMargin: "350px 0px" });
    canvases.forEach((canvas) => observer.observe(canvas));

    // Refit when the real preview column changes width. Theme builders,
    // Elementor containers, sidebars, tabs, and responsive grids can resize a
    // specimen independently of the browser window. Width-only tracking avoids
    // observer loops when a new raster changes only the canvas height.
    if ("ResizeObserver" in window) {
      const resizeObserver = new ResizeObserver((entries) => {
        entries.forEach((entry) => {
          const canvas = entry.target;
          const width = Math.round(entry.contentRect?.width || canvas.getBoundingClientRect().width || 0);
          const previous = Number(canvas.dataset.observedWidth || 0);
          if (width <= 0 || (previous && Math.abs(width - previous) < 2)) return;
          canvas.dataset.observedWidth = String(width);
          if (canvas.dataset.rendered !== "1") return;
          canvas.dataset.dirty = "1";
          if (canvasNearViewport(canvas)) debounceFor(canvas, () => renderCanvas(root, canvas, true), 180);
        });
      });
      canvases.forEach((canvas) => resizeObserver.observe(canvas));
      root._athPreviewResizeObserver = resizeObserver;
    } else {
      let resizeTimer = 0;
      window.addEventListener("resize", () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => {
          qsa(root, ".ath-server-canvas[data-rendered='1']").forEach((canvas) => {
            canvas.dataset.dirty = "1";
            if (canvasNearViewport(canvas)) renderCanvas(root, canvas, true);
          });
        }, 300);
      }, { passive: true });
    }
  }

  function initFreeDownloads(scope) {
    const freeCfg = window.AthFreeDownloads || {};
    qsa(scope || document, ".ath-free-download-card").forEach((card) => {
      if (card.dataset.freeDownloadInit === "1") return;
      card.dataset.freeDownloadInit = "1";

      const open = qs(card, ".ath-free-download-open");
      const form = qs(card, ".ath-free-download-form");
      if (!open || !form) return;

      const cancel = qs(form, ".ath-free-download-cancel");
      const submit = qs(form, ".ath-free-download-submit");
      const message = qs(form, ".ath-free-download-message");
      const ready = qs(form, ".ath-free-download-ready");
      const readyLink = qs(form, "[data-free-download-ready-link]");

      const setOpen = (state) => {
        form.hidden = !state;
        open.setAttribute("aria-expanded", state ? "true" : "false");
        open.hidden = state;
        card.classList.toggle("is-form-open", state);
        if (state) {
          const email = qs(form, 'input[name="email"]');
          window.setTimeout(() => { if (email) email.focus(); }, 30);
        }
      };

      open.addEventListener("click", () => setOpen(true));
      if (cancel) cancel.addEventListener("click", () => setOpen(false));

      form.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!freeCfg.ajaxUrl || !freeCfg.nonce || !submit) return;
        if (typeof form.reportValidity === "function" && !form.reportValidity()) return;

        const oldLabel = submit.textContent;
        submit.disabled = true;
        submit.textContent = freeCfg.i18n?.preparing || "Preparing…";
        if (message) {
          message.hidden = true;
          message.textContent = "";
          message.classList.remove("is-error", "is-success");
        }

        try {
          const fd = new FormData(form);
          fd.append("action", "ath_free_download_request");
          fd.append("nonce", freeCfg.nonce);
          const res = await fetch(freeCfg.ajaxUrl, {
            method: "POST",
            body: fd,
            credentials: "same-origin",
            cache: "no-store"
          });
          let json = null;
          try { json = await res.json(); } catch (_) {}
          if (!res.ok || !json || !json.success || !json.data?.download_url) {
            const errorText = json?.data?.message || freeCfg.i18n?.failed || "Could not prepare the download.";
            throw new Error(errorText);
          }

          if (readyLink) readyLink.href = json.data.download_url;
          form.classList.add("is-ready");
          if (ready) ready.hidden = false;
          if (message) {
            message.textContent = json.data.message || freeCfg.i18n?.ready || "Thanks. Your download is ready.";
            message.classList.add("is-success");
            message.hidden = false;
          }
        } catch (err) {
          if (message) {
            message.textContent = err.message || freeCfg.i18n?.failed || "Could not prepare the download.";
            message.classList.add("is-error");
            message.hidden = false;
          }
        } finally {
          submit.disabled = false;
          submit.textContent = oldLabel;
        }
      });
    });
  }

  function initIndividualStyleSticky(root) {
    const panel = qs(root, '[data-panel="individual-styles"]');
    const toolbar = panel ? qs(panel, ".ath-preview-toolbar") : null;
    if (!panel || !toolbar) return;

    const syncStickyMetrics = () => {
      const rect = toolbar.getBoundingClientRect();
      if (rect.height > 0) {
        root.style.setProperty("--ath-individual-toolbar-height", `${Math.ceil(rect.height)}px`);
      }

      // WordPress' admin bar is fixed above the page for logged-in users.
      // Keep the specimen controls below it without guessing theme header sizes.
      let offset = 12;
      const adminBar = document.getElementById("wpadminbar");
      if (adminBar) {
        const adminRect = adminBar.getBoundingClientRect();
        const adminStyle = window.getComputedStyle(adminBar);
        if ((adminStyle.position === "fixed" || adminStyle.position === "sticky") && adminRect.bottom > 0 && adminRect.top <= 1) {
          offset = Math.max(offset, Math.ceil(adminRect.bottom) + 12);
        }
      }
      root.style.setProperty("--ath-individual-sticky-offset", `${offset}px`);
    };

    if ("ResizeObserver" in window) {
      const observer = new ResizeObserver(() => syncStickyMetrics());
      observer.observe(toolbar);
    }

    qsa(root, ".ath-tab").forEach((tab) => tab.addEventListener("click", () => {
      window.requestAnimationFrame(syncStickyMetrics);
    }));
    window.addEventListener("resize", () => debounceFor(toolbar, syncStickyMetrics, 120), { passive: true });
    window.requestAnimationFrame(syncStickyMetrics);
  }

  function initRoot(root) {
    root.dataset.textColor = "#111111";
    root.dataset.bgColor = "#ffffff";
    initTabs(root);
    initPreviewToolbar(root);
    initIndividualStyleSticky(root);
    initGlyphs(root);
    initTech(root);
    initModal(root);
    initLazyRender(root);
    const active = qs(root, ".ath-tab-panel.is-active:not([hidden])");
    if (active) renderNearViewport(root, active);
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".ath-specimen-v7").forEach(initRoot);
    initFreeDownloads(document);
  });
})();
