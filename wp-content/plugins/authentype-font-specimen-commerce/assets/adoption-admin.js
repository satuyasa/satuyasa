(function () {
  'use strict';
  if (typeof AthSpecimenAdoption === 'undefined') return;

  var root = document.querySelector('.ath-adoption-wrap');
  if (!root) return;
  var ajaxUrl = AthSpecimenAdoption.ajaxUrl;
  var nonce = AthSpecimenAdoption.nonce;

  function requestAdoption(productId, styleAttr, licenseAttr, requireBulkReady) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adopt_product');
    body.set('nonce', nonce);
    body.set('product_id', productId);
    body.set('style_attr', styleAttr || '');
    body.set('license_attr', licenseAttr || '');
    if (requireBulkReady) body.set('require_bulk_ready', '1');
    return fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.failed);
        }
        return json.data;
      });
    });
  }

  root.addEventListener('click', function (event) {
    var one = event.target.closest('.ath-adopt-one');
    if (one) {
      event.preventDefault();
      if (one.disabled) return;
      var result = one.parentNode.querySelector('.ath-adopt-result');
      var spinner = one.parentNode.querySelector('.spinner');
      one.disabled = true;
      if (spinner) spinner.classList.add('is-active');
      if (result) result.textContent = AthSpecimenAdoption.i18n.adopting;
      requestAdoption(one.dataset.productId, one.dataset.styleAttr, one.dataset.licenseAttr).then(function (data) {
        if (result) result.innerHTML = data.edit_url ? '<a href="' + data.edit_url + '">' + AthSpecimenAdoption.i18n.openFont + '</a> — ' + data.message : data.message;
        one.hidden = true;
      }).catch(function (error) {
        one.disabled = false;
        if (result) result.textContent = error.message;
      }).finally(function () {
        if (spinner) spinner.classList.remove('is-active');
      });
      return;
    }
  });


  root.addEventListener('click', function (event) {
    var restore = event.target.closest('.ath-restore-snapshot');
    if (!restore) return;
    event.preventDefault();
    if (restore.disabled) return;
    if (!window.confirm(AthSpecimenAdoption.i18n.restoreConfirm)) return;
    var wrap = restore.parentNode;
    var spinner = wrap ? wrap.querySelector('.spinner') : null;
    var result = wrap ? wrap.querySelector('.ath-restore-result') : null;
    restore.disabled = true;
    if (spinner) spinner.classList.add('is-active');
    if (result) result.textContent = AthSpecimenAdoption.i18n.restoring;
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_restore_adoption_snapshot');
    body.set('nonce', nonce);
    body.set('font_id', restore.dataset.fontId || '0');
    fetch(ajaxUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:body.toString()})
      .then(function(res){ return res.json().then(function(json){ if(!res.ok || !json || !json.success) throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.failed); return json.data; }); })
      .then(function(data){ if(result) result.textContent = data.message; })
      .catch(function(error){ restore.disabled = false; if(result) result.textContent = error.message; })
      .finally(function(){ if(spinner) spinner.classList.remove('is-active'); });
  });


  function requestCatalogIds(page, search) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_catalog_ids');
    body.set('nonce', nonce);
    body.set('paged', String(page || 1));
    body.set('search', search || '');
    return fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.scanFailed);
        }
        return json.data;
      });
    });
  }

  function requestScanProduct(productId) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_scan_product');
    body.set('nonce', nonce);
    body.set('product_id', String(productId));
    return fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.scanFailed);
        }
        return json.data;
      });
    });
  }


  function requestReadinessIds(page) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_readiness_ids');
    body.set('nonce', nonce);
    body.set('paged', String(page || 1));
    return fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.readinessFailed);
        }
        return json.data;
      });
    });
  }

  function requestReadinessProduct(productId) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_readiness_product');
    body.set('nonce', nonce);
    body.set('product_id', String(productId));
    return fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.readinessFailed);
        }
        return json.data;
      });
    });
  }

  function requestLegacyDeliveryPlan(productId) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_legacy_delivery_plan');
    body.set('nonce', nonce);
    body.set('product_id', String(productId));
    return fetch(ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.hydrationFailed);
        }
        return json.data;
      });
    });
  }

  function requestLegacyDeliveryHydrate(productId) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_legacy_delivery_hydrate');
    body.set('nonce', nonce);
    body.set('product_id', String(productId));
    return fetch(ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.hydrationFailed);
        }
        return json.data;
      });
    });
  }

  function requestLegacyPricingPlan(productId) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_legacy_pricing_plan');
    body.set('nonce', nonce);
    body.set('product_id', String(productId));
    return fetch(ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.pricingHydrationFailed);
        }
        return json.data;
      });
    });
  }

  function requestLegacyPricingHydrate(productId) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_legacy_pricing_hydrate');
    body.set('nonce', nonce);
    body.set('product_id', String(productId));
    return fetch(ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.pricingHydrationFailed);
        }
        return json.data;
      });
    });
  }


  function requestLegacyWooReconcilePlan(productId) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_legacy_woo_reconcile_plan');
    body.set('nonce', nonce);
    body.set('product_id', String(productId));
    return fetch(ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.wooReconcileFailed);
        }
        return json.data;
      });
    });
  }

  function requestLegacyWooReconcile(productId) {
    var body = new URLSearchParams();
    body.set('action', 'ath_specimen_adoption_legacy_woo_reconcile');
    body.set('nonce', nonce);
    body.set('product_id', String(productId));
    return fetch(ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : AthSpecimenAdoption.i18n.wooReconcileFailed);
        }
        return json.data;
      });
    });
  }

  function fetchAllReadinessIds() {
    var ids = [];
    var seen = {};
    function page(n) {
      return requestReadinessIds(n).then(function (data) {
        (data.ids || []).forEach(function (id) {
          id = Number(id || 0);
          if (id && !seen[id]) { seen[id] = true; ids.push(id); }
        });
        if (n < Number(data.total_pages || 1)) return page(n + 1);
        return ids;
      });
    }
    return page(1);
  }

  var matcher = root.querySelector('.ath-legacy-matcher');
  var scanButton = matcher ? matcher.querySelector('.ath-scan-legacy-catalog') : null;
  var adoptReadyButton = matcher ? matcher.querySelector('.ath-adopt-all-ready') : null;
  var matcherPause = matcher ? matcher.querySelector('.ath-pause-legacy-matcher') : null;
  var matcherStatus = matcher ? matcher.querySelector('.ath-legacy-matcher-status') : null;
  var matcherCounts = matcher ? matcher.querySelectorAll('[data-ath-count]') : [];
  var matcherPaused = false;
  var matcherMode = '';
  var matcherReady = [];
  var matcherSummary = {scanned: 0, ready: 0, review: 0, adopted: 0};

  function setMatcherCount(key, value) {
    matcherSummary[key] = value;
    Array.prototype.forEach.call(matcherCounts, function (el) {
      if (el.getAttribute('data-ath-count') === key) el.textContent = String(value);
    });
  }

  function resetMatcher() {
    matcherReady = [];
    ['scanned', 'ready', 'review', 'adopted'].forEach(function (key) { setMatcherCount(key, 0); });
    if (adoptReadyButton) adoptReadyButton.disabled = true;
    if (matcherStatus) matcherStatus.textContent = '';
  }

  function matcherRow(productId) {
    return root.querySelector('tr[data-product-id="' + String(productId).replace(/"/g, '') + '"]');
  }

  function updateVisibleRowFromScan(data) {
    var row = matcherRow(data.product_id);
    if (!row) return;
    var status = row.querySelector('.ath-adoption-status');
    var small = status && status.parentNode ? status.parentNode.querySelector('small') : null;
    var mapping = row.children && row.children.length > 3 ? row.children[3] : null;
    if (status) {
      status.textContent = data.status_label || data.status;
      var warningStatus = ['compatible', 'needs_mapping', 'needs_global_attributes', 'needs_existing_match', 'simple'].indexOf(data.status) !== -1;
      status.className = 'ath-adoption-status ' + (data.bulk_ready || data.status === 'adopted' ? 'is-good' : (warningStatus ? 'is-warning' : 'is-bad'));
    }
    if (small) small.textContent = data.message || '';
    if (mapping && data.style_attr && data.license_attr) {
      mapping.innerHTML = '<code>' + escapeHtml(data.style_attr) + '</code> × <code>' + escapeHtml(data.license_attr) + '</code>';
    }
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function fetchAllCatalogIds(search) {
    var ids = [];
    function page(n) {
      return requestCatalogIds(n, search).then(function (data) {
        ids = ids.concat(data.ids || []);
        if (n < Number(data.total_pages || 1)) return page(n + 1);
        return ids;
      });
    }
    return page(1);
  }

  function finishMatcherScan() {
    matcherMode = '';
    if (matcherPause) matcherPause.hidden = true;
    if (scanButton) scanButton.disabled = false;
    if (adoptReadyButton) adoptReadyButton.disabled = matcherReady.length === 0;
    if (readinessButton) readinessButton.disabled = false;
    if (matcherStatus) {
      matcherStatus.textContent = AthSpecimenAdoption.i18n.scanComplete
        .replace('%r', String(matcherSummary.ready))
        .replace('%v', String(matcherSummary.review))
        .replace('%a', String(matcherSummary.adopted));
    }
  }

  function runMatcherScan(ids) {
    matcherMode = 'scan';
    matcherPaused = false;
    if (matcherPause) matcherPause.hidden = false;
    if (scanButton) scanButton.disabled = true;
    if (adoptReadyButton) adoptReadyButton.disabled = true;
    if (readinessButton) readinessButton.disabled = true;
    var queue = ids.slice();

    function next() {
      if (matcherPaused) {
        matcherMode = '';
        if (matcherPause) matcherPause.hidden = true;
        if (scanButton) scanButton.disabled = false;
        if (adoptReadyButton) adoptReadyButton.disabled = matcherReady.length === 0;
        if (readinessButton) readinessButton.disabled = false;
        if (matcherStatus) matcherStatus.textContent = AthSpecimenAdoption.i18n.scanPaused;
        return;
      }
      if (!queue.length) {
        finishMatcherScan();
        return;
      }
      var productId = queue.shift();
      if (matcherStatus) {
        matcherStatus.textContent = AthSpecimenAdoption.i18n.scanProgress
          .replace('%c', String(matcherSummary.scanned + 1))
          .replace('%t', String(ids.length));
      }
      requestScanProduct(productId).then(function (data) {
        setMatcherCount('scanned', matcherSummary.scanned + 1);
        updateVisibleRowFromScan(data);
        if (data.status === 'adopted') {
          setMatcherCount('adopted', matcherSummary.adopted + 1);
        } else if (data.bulk_ready) {
          matcherReady.push({
            productId: data.product_id,
            styleAttr: data.style_attr || '',
            licenseAttr: data.license_attr || ''
          });
          setMatcherCount('ready', matcherSummary.ready + 1);
        } else {
          setMatcherCount('review', matcherSummary.review + 1);
        }
      }).catch(function () {
        setMatcherCount('scanned', matcherSummary.scanned + 1);
        setMatcherCount('review', matcherSummary.review + 1);
      }).finally(next);
    }
    next();
  }

  function runMatcherAdoption() {
    if (!matcherReady.length || matcherMode || readinessRunning || hydrationRunning || pricingHydrationRunning || wooReconcileRunning) return;
    matcherMode = 'adopt';
    matcherPaused = false;
    if (matcherPause) matcherPause.hidden = false;
    if (scanButton) scanButton.disabled = true;
    if (adoptReadyButton) adoptReadyButton.disabled = true;
    if (readinessButton) readinessButton.disabled = true;
    var queue = matcherReady.slice();
    var total = queue.length;
    var done = 0;
    var failed = 0;

    function next() {
      if (matcherPaused) {
        matcherMode = '';
        matcherReady = queue.slice();
        if (matcherPause) matcherPause.hidden = true;
        if (scanButton) scanButton.disabled = false;
        if (adoptReadyButton) adoptReadyButton.disabled = queue.length === 0;
        if (readinessButton) readinessButton.disabled = false;
        if (matcherStatus) matcherStatus.textContent = AthSpecimenAdoption.i18n.paused;
        return;
      }
      if (!queue.length) {
        matcherMode = '';
        matcherReady = [];
        if (matcherPause) matcherPause.hidden = true;
        if (scanButton) scanButton.disabled = false;
        if (adoptReadyButton) adoptReadyButton.disabled = true;
        if (readinessButton) readinessButton.disabled = false;
        if (matcherStatus) matcherStatus.textContent = AthSpecimenAdoption.i18n.bulkReadyComplete.replace('%d', String(done)).replace('%f', String(failed));
        return;
      }
      var item = queue.shift();
      if (matcherStatus) matcherStatus.textContent = AthSpecimenAdoption.i18n.progress.replace('%c', String(done + failed + 1)).replace('%t', String(total));
      requestAdoption(item.productId, item.styleAttr, item.licenseAttr, true).then(function (data) {
        done++;
        setMatcherCount('ready', Math.max(0, matcherSummary.ready - 1));
        setMatcherCount('adopted', matcherSummary.adopted + 1);
        var row = matcherRow(item.productId);
        if (row) {
          var status = row.querySelector('.ath-adoption-status');
          if (status) {
            status.textContent = AthSpecimenAdoption.i18n.adopted;
            status.className = 'ath-adoption-status is-good';
          }
          var action = row.lastElementChild;
          if (action && data.edit_url) action.innerHTML = '<a class="button" href="' + data.edit_url + '">' + AthSpecimenAdoption.i18n.openFont + '</a>';
          var checkbox = row.querySelector('.ath-adopt-checkbox');
          if (checkbox) {
            checkbox.checked = false;
            checkbox.disabled = true;
          }
        }
      }).catch(function () {
        failed++;
        setMatcherCount('ready', Math.max(0, matcherSummary.ready - 1));
        setMatcherCount('review', matcherSummary.review + 1);
      }).finally(next);
    }
    next();
  }

  if (scanButton) scanButton.addEventListener('click', function () {
    if (matcherMode || readinessRunning || hydrationRunning || pricingHydrationRunning || wooReconcileRunning) return;
    resetMatcher();
    matcherPaused = false;
    matcherMode = 'loading';
    scanButton.disabled = true;
    if (readinessButton) readinessButton.disabled = true;
    if (matcherStatus) matcherStatus.textContent = AthSpecimenAdoption.i18n.loadingCatalog;
    var search = matcher ? (matcher.getAttribute('data-search') || '') : '';
    fetchAllCatalogIds(search).then(function (ids) {
      if (!ids.length) {
        matcherMode = '';
        if (matcherStatus) matcherStatus.textContent = AthSpecimenAdoption.i18n.noCatalogProducts;
        if (scanButton) scanButton.disabled = false;
        if (readinessButton) readinessButton.disabled = false;
        return;
      }
      runMatcherScan(ids);
    }).catch(function (error) {
      matcherMode = '';
      if (scanButton) scanButton.disabled = false;
      if (readinessButton) readinessButton.disabled = false;
      if (matcherPause) matcherPause.hidden = true;
      if (matcherStatus) matcherStatus.textContent = error.message;
    });
  });

  if (adoptReadyButton) adoptReadyButton.addEventListener('click', function () {
    runMatcherAdoption();
  });

  if (matcherPause) matcherPause.addEventListener('click', function () {
    matcherPaused = true;
  });


  var readiness = root.querySelector('.ath-commerce-readiness');
  var readinessButton = readiness ? readiness.querySelector('.ath-audit-commerce-readiness') : null;
  var readinessPause = readiness ? readiness.querySelector('.ath-pause-commerce-readiness') : null;
  var readinessStatus = readiness ? readiness.querySelector('.ath-commerce-readiness-status') : null;
  var readinessCountNodes = readiness ? readiness.querySelectorAll('[data-ath-readiness-count]') : [];
  var readinessPaused = false;
  var readinessRunning = false;
  var readinessSummary = {audited: 0, shop_ready: 0, needs_sync: 0, needs_pricing: 0, missing_delivery: 0, review: 0};

  function setReadinessCount(key, value) {
    readinessSummary[key] = value;
    Array.prototype.forEach.call(readinessCountNodes, function (el) {
      if (el.getAttribute('data-ath-readiness-count') === key) el.textContent = String(value);
    });
  }

  function resetReadiness() {
    Object.keys(readinessSummary).forEach(function (key) { setReadinessCount(key, 0); });
    Array.prototype.forEach.call(root.querySelectorAll('.ath-commerce-readiness-result'), function (el) {
      el.hidden = true;
      el.textContent = '';
      el.className = 'ath-commerce-readiness-result';
    });
    if (readinessStatus) readinessStatus.textContent = '';
  }

  function updateVisibleReadiness(data) {
    var row = matcherRow(data.product_id);
    if (!row) return;
    var target = row.querySelector('.ath-commerce-readiness-result');
    if (!target) return;
    target.hidden = false;
    target.textContent = (data.status_label || data.status) + (data.message ? ' — ' + data.message : '');
    target.className = 'ath-commerce-readiness-result is-' + String(data.status || 'review').replace(/[^a-z0-9_-]/g, '');
  }

  function finishReadinessAudit() {
    readinessRunning = false;
    if (readinessPause) readinessPause.hidden = true;
    if (readinessButton) readinessButton.disabled = false;
    if (scanButton) scanButton.disabled = false;
    if (adoptReadyButton) adoptReadyButton.disabled = matcherReady.length === 0;
    if (readinessStatus) {
      readinessStatus.textContent = AthSpecimenAdoption.i18n.readinessComplete
        .replace('%s', String(readinessSummary.shop_ready))
        .replace('%y', String(readinessSummary.needs_sync))
        .replace('%p', String(readinessSummary.needs_pricing))
        .replace('%m', String(readinessSummary.missing_delivery))
        .replace('%r', String(readinessSummary.review));
    }
  }

  function runReadinessAudit(ids) {
    readinessRunning = true;
    readinessPaused = false;
    if (readinessPause) readinessPause.hidden = false;
    if (readinessButton) readinessButton.disabled = true;
    if (scanButton) scanButton.disabled = true;
    if (adoptReadyButton) adoptReadyButton.disabled = true;
    var queue = ids.slice();
    var total = ids.length;

    function next() {
      if (readinessPaused) {
        readinessRunning = false;
        if (readinessPause) readinessPause.hidden = true;
        if (readinessButton) readinessButton.disabled = false;
        if (scanButton) scanButton.disabled = false;
        if (adoptReadyButton) adoptReadyButton.disabled = matcherReady.length === 0;
        if (readinessStatus) readinessStatus.textContent = AthSpecimenAdoption.i18n.readinessPaused;
        return;
      }
      if (!queue.length) {
        finishReadinessAudit();
        return;
      }
      var productId = queue.shift();
      if (readinessStatus) {
        readinessStatus.textContent = AthSpecimenAdoption.i18n.readinessProgress
          .replace('%c', String(readinessSummary.audited + 1))
          .replace('%t', String(total));
      }
      requestReadinessProduct(productId).then(function (data) {
        setReadinessCount('audited', readinessSummary.audited + 1);
        var key = Object.prototype.hasOwnProperty.call(readinessSummary, data.status) ? data.status : 'review';
        if (key === 'audited') key = 'review';
        setReadinessCount(key, readinessSummary[key] + 1);
        updateVisibleReadiness(data);
      }).catch(function (error) {
        setReadinessCount('audited', readinessSummary.audited + 1);
        setReadinessCount('review', readinessSummary.review + 1);
        var row = matcherRow(productId);
        var target = row ? row.querySelector('.ath-commerce-readiness-result') : null;
        if (target) {
          target.hidden = false;
          target.textContent = 'Review — ' + error.message;
          target.className = 'ath-commerce-readiness-result is-review';
        }
      }).finally(next);
    }
    next();
  }

  if (readinessButton) readinessButton.addEventListener('click', function () {
    if (readinessRunning || matcherMode || hydrationRunning || pricingHydrationRunning || wooReconcileRunning) return;
    resetReadiness();
    readinessPaused = false;
    readinessRunning = true;
    readinessButton.disabled = true;
    if (scanButton) scanButton.disabled = true;
    if (adoptReadyButton) adoptReadyButton.disabled = true;
    if (readinessStatus) readinessStatus.textContent = AthSpecimenAdoption.i18n.readinessLoading;
    fetchAllReadinessIds().then(function (ids) {
      if (!ids.length) {
        readinessRunning = false;
        readinessButton.disabled = false;
        if (scanButton) scanButton.disabled = false;
        if (adoptReadyButton) adoptReadyButton.disabled = matcherReady.length === 0;
        if (readinessStatus) readinessStatus.textContent = AthSpecimenAdoption.i18n.noAdoptedProducts;
        return;
      }
      runReadinessAudit(ids);
    }).catch(function (error) {
      readinessRunning = false;
      readinessButton.disabled = false;
      if (scanButton) scanButton.disabled = false;
      if (adoptReadyButton) adoptReadyButton.disabled = matcherReady.length === 0;
      if (readinessPause) readinessPause.hidden = true;
      if (readinessStatus) readinessStatus.textContent = error.message;
    });
  });

  if (readinessPause) readinessPause.addEventListener('click', function () {
    readinessPaused = true;
  });

  var hydration = root.querySelector('.ath-legacy-delivery-hydration');
  var hydrationPreviewButton = hydration ? hydration.querySelector('.ath-preview-legacy-delivery') : null;
  var hydrationRunButton = hydration ? hydration.querySelector('.ath-hydrate-legacy-delivery') : null;
  var hydrationPause = hydration ? hydration.querySelector('.ath-pause-legacy-delivery') : null;
  var hydrationStatus = hydration ? hydration.querySelector('.ath-legacy-delivery-hydration-status') : null;
  var hydrationBlockedPanel = hydration ? hydration.querySelector('.ath-legacy-delivery-blocked') : null;
  var hydrationBlockedList = hydration ? hydration.querySelector('.ath-legacy-delivery-blocked-list') : null;
  var hydrationCountNodes = hydration ? hydration.querySelectorAll('[data-ath-hydration-count]') : [];
  var hydrationReady = [];
  var hydrationBlocked = [];
  var hydrationPaused = false;
  var hydrationRunning = false;
  var hydrationMode = '';
  var hydrationSummary = {checked: 0, eligible: 0, pairs: 0, files: 0, blocked: 0, skipped: 0, hydrated: 0};

  function setHydrationCount(key, value) {
    hydrationSummary[key] = value;
    Array.prototype.forEach.call(hydrationCountNodes, function (el) {
      if (el.getAttribute('data-ath-hydration-count') === key) el.textContent = String(value);
    });
  }

  function renderHydrationBlocked() {
    if (!hydrationBlockedPanel || !hydrationBlockedList) return;
    while (hydrationBlockedList.firstChild) hydrationBlockedList.removeChild(hydrationBlockedList.firstChild);
    hydrationBlocked.forEach(function (item) {
      var li = document.createElement('li');
      var title = document.createElement('strong');
      title.textContent = (item.name || ('Woo #' + item.productId)) + ' (Woo #' + item.productId + ')';
      var reason = document.createElement('span');
      reason.textContent = (item.reasons && item.reasons.length ? item.reasons : ['Blocked without a detailed reason.']).join('; ');
      li.appendChild(title);
      li.appendChild(reason);
      hydrationBlockedList.appendChild(li);
    });
    hydrationBlockedPanel.hidden = hydrationBlocked.length === 0;
  }

  function addHydrationBlocked(data, fallbackMessage) {
    data = data || {};
    var productId = Number(data.product_id || 0);
    var reasons = Array.isArray(data.blocked_reasons) ? data.blocked_reasons.filter(Boolean) : [];
    if (!reasons.length && data.message) reasons.push(String(data.message));
    if (!reasons.length && fallbackMessage) reasons.push(String(fallbackMessage));
    hydrationBlocked.push({
      productId: productId,
      name: String(data.product_name || ''),
      reasons: reasons
    });
    renderHydrationBlocked();
  }

  function resetHydrationPreview() {
    hydrationReady = [];
    hydrationBlocked = [];
    Object.keys(hydrationSummary).forEach(function (key) { setHydrationCount(key, 0); });
    if (hydrationRunButton) hydrationRunButton.disabled = true;
    if (hydrationStatus) hydrationStatus.textContent = '';
    renderHydrationBlocked();
  }

  function setCatalogControlsForHydration(disabled) {
    if (hydrationPreviewButton) hydrationPreviewButton.disabled = disabled;
    if (hydrationRunButton) hydrationRunButton.disabled = disabled || hydrationReady.length === 0;
    if (scanButton) scanButton.disabled = disabled;
    if (adoptReadyButton) adoptReadyButton.disabled = disabled || matcherReady.length === 0;
    if (readinessButton) readinessButton.disabled = disabled;
  }

  function finishHydrationPreview() {
    hydrationRunning = false;
    hydrationMode = '';
    if (hydrationPause) hydrationPause.hidden = true;
    setCatalogControlsForHydration(false);
    if (hydrationStatus) {
      hydrationStatus.textContent = AthSpecimenAdoption.i18n.hydrationPreviewComplete
        .replace('%e', String(hydrationSummary.eligible))
        .replace('%p', String(hydrationSummary.pairs))
        .replace('%f', String(hydrationSummary.files))
        .replace('%b', String(hydrationSummary.blocked))
        .replace('%s', String(hydrationSummary.skipped));
    }
  }

  function runHydrationPreview(ids) {
    hydrationRunning = true;
    hydrationMode = 'preview';
    hydrationPaused = false;
    if (hydrationPause) hydrationPause.hidden = false;
    setCatalogControlsForHydration(true);
    var queue = ids.slice();
    var total = ids.length;

    function next() {
      if (hydrationPaused) {
        hydrationRunning = false;
        hydrationMode = '';
        if (hydrationPause) hydrationPause.hidden = true;
        setCatalogControlsForHydration(false);
        if (hydrationStatus) hydrationStatus.textContent = AthSpecimenAdoption.i18n.hydrationPaused;
        return;
      }
      if (!queue.length) { finishHydrationPreview(); return; }
      var productId = queue.shift();
      if (hydrationStatus) hydrationStatus.textContent = AthSpecimenAdoption.i18n.hydrationPreviewProgress
        .replace('%c', String(hydrationSummary.checked + 1)).replace('%t', String(total));
      requestLegacyDeliveryPlan(productId).then(function (data) {
        setHydrationCount('checked', hydrationSummary.checked + 1);
        if (data.eligible) {
          hydrationReady.push(Number(data.product_id || 0));
          setHydrationCount('eligible', hydrationSummary.eligible + 1);
          setHydrationCount('pairs', hydrationSummary.pairs + Number(data.counts && data.counts.pairs || 0));
          setHydrationCount('files', hydrationSummary.files + Number(data.counts && data.counts.downloads || 0));
        } else if (data.readiness_status === 'missing_delivery' || data.status === 'blocked') {
          setHydrationCount('blocked', hydrationSummary.blocked + 1);
          addHydrationBlocked(data);
        } else {
          setHydrationCount('skipped', hydrationSummary.skipped + 1);
        }
      }).catch(function (error) {
        setHydrationCount('checked', hydrationSummary.checked + 1);
        setHydrationCount('blocked', hydrationSummary.blocked + 1);
        addHydrationBlocked({product_id: productId}, AthSpecimenAdoption.i18n.hydrationPreviewRequestFailed.replace('%s', error.message));
      }).finally(next);
    }
    next();
  }

  function finishHydrationRun(done, failed) {
    hydrationRunning = false;
    hydrationMode = '';
    hydrationReady = [];
    if (hydrationPause) hydrationPause.hidden = true;
    setCatalogControlsForHydration(false);
    if (hydrationRunButton) hydrationRunButton.disabled = true;
    if (hydrationStatus) hydrationStatus.textContent = AthSpecimenAdoption.i18n.hydrationComplete
      .replace('%d', String(done)).replace('%f', String(failed));
  }

  function runHydration() {
    if (!hydrationReady.length || hydrationRunning || matcherMode || readinessRunning || pricingHydrationRunning || wooReconcileRunning) return;
    hydrationRunning = true;
    hydrationMode = 'hydrate';
    hydrationPaused = false;
    if (hydrationPause) hydrationPause.hidden = false;
    setCatalogControlsForHydration(true);
    var queue = hydrationReady.slice();
    var total = queue.length;
    var done = 0;
    var failed = 0;

    function next() {
      if (hydrationPaused) {
        hydrationRunning = false;
        hydrationMode = '';
        hydrationReady = queue.slice();
        if (hydrationPause) hydrationPause.hidden = true;
        setCatalogControlsForHydration(false);
        if (hydrationStatus) hydrationStatus.textContent = AthSpecimenAdoption.i18n.hydrationPaused;
        return;
      }
      if (!queue.length) { finishHydrationRun(done, failed); return; }
      var productId = queue.shift();
      if (hydrationStatus) hydrationStatus.textContent = AthSpecimenAdoption.i18n.hydrationProgress
        .replace('%c', String(done + failed + 1)).replace('%t', String(total));
      requestLegacyDeliveryHydrate(productId).then(function (data) {
        done++;
        setHydrationCount('hydrated', hydrationSummary.hydrated + 1);
        var row = matcherRow(productId);
        var target = row ? row.querySelector('.ath-commerce-readiness-result') : null;
        if (target) {
          target.hidden = false;
          target.textContent = AthSpecimenAdoption.i18n.hydratedRow
            .replace('%f', String(data.downloads || 0))
            .replace('%p', String(data.pairs || 0))
            .replace('%s', String(data.snapshot_id || 'saved'));
          target.className = 'ath-commerce-readiness-result is-hydrated';
        }
      }).catch(function (error) {
        failed++;
        var row = matcherRow(productId);
        var target = row ? row.querySelector('.ath-commerce-readiness-result') : null;
        if (target) {
          target.hidden = false;
          target.textContent = 'Review — ' + error.message;
          target.className = 'ath-commerce-readiness-result is-review';
        }
      }).finally(next);
    }
    next();
  }

  if (hydrationPreviewButton) hydrationPreviewButton.addEventListener('click', function () {
    if (hydrationRunning || matcherMode || readinessRunning || pricingHydrationRunning || wooReconcileRunning) return;
    resetHydrationPreview();
    hydrationRunning = true;
    hydrationMode = 'loading';
    setCatalogControlsForHydration(true);
    if (hydrationStatus) hydrationStatus.textContent = AthSpecimenAdoption.i18n.hydrationLoading;
    fetchAllReadinessIds().then(function (ids) {
      if (!ids.length) {
        hydrationRunning = false;
        hydrationMode = '';
        setCatalogControlsForHydration(false);
        if (hydrationStatus) hydrationStatus.textContent = AthSpecimenAdoption.i18n.noAdoptedProducts;
        return;
      }
      runHydrationPreview(ids);
    }).catch(function (error) {
      hydrationRunning = false;
      hydrationMode = '';
      setCatalogControlsForHydration(false);
      if (hydrationPause) hydrationPause.hidden = true;
      if (hydrationStatus) hydrationStatus.textContent = error.message;
    });
  });

  if (hydrationRunButton) hydrationRunButton.addEventListener('click', runHydration);
  if (hydrationPause) hydrationPause.addEventListener('click', function () { hydrationPaused = true; });

  var pricingHydration = root.querySelector('.ath-legacy-pricing-hydration');
  var pricingHydrationPreviewButton = pricingHydration ? pricingHydration.querySelector('.ath-preview-legacy-pricing') : null;
  var pricingHydrationRunButton = pricingHydration ? pricingHydration.querySelector('.ath-hydrate-legacy-pricing') : null;
  var pricingHydrationPause = pricingHydration ? pricingHydration.querySelector('.ath-pause-legacy-pricing') : null;
  var pricingHydrationStatus = pricingHydration ? pricingHydration.querySelector('.ath-legacy-pricing-hydration-status') : null;
  var pricingHydrationBlockedPanel = pricingHydration ? pricingHydration.querySelector('.ath-legacy-pricing-blocked') : null;
  var pricingHydrationBlockedList = pricingHydration ? pricingHydration.querySelector('.ath-legacy-pricing-blocked-list') : null;
  var pricingHydrationCountNodes = pricingHydration ? pricingHydration.querySelectorAll('[data-ath-pricing-hydration-count]') : [];
  var pricingHydrationReady = [];
  var pricingHydrationBlocked = [];
  var pricingHydrationPaused = false;
  var pricingHydrationRunning = false;
  var pricingHydrationMode = '';
  var pricingHydrationSummary = {checked: 0, eligible: 0, pairs: 0, sales: 0, blocked: 0, skipped: 0, hydrated: 0};

  function setPricingHydrationCount(key, value) {
    pricingHydrationSummary[key] = value;
    Array.prototype.forEach.call(pricingHydrationCountNodes, function (el) {
      if (el.getAttribute('data-ath-pricing-hydration-count') === key) el.textContent = String(value);
    });
  }

  function renderPricingHydrationBlocked() {
    if (!pricingHydrationBlockedPanel || !pricingHydrationBlockedList) return;
    while (pricingHydrationBlockedList.firstChild) pricingHydrationBlockedList.removeChild(pricingHydrationBlockedList.firstChild);
    pricingHydrationBlocked.forEach(function (item) {
      var li = document.createElement('li');
      var title = document.createElement('strong');
      title.textContent = (item.name || ('Woo #' + item.productId)) + ' (Woo #' + item.productId + ')';
      var reason = document.createElement('span');
      reason.textContent = (item.reasons && item.reasons.length ? item.reasons : ['Blocked without a detailed reason.']).join('; ');
      li.appendChild(title);
      li.appendChild(reason);
      pricingHydrationBlockedList.appendChild(li);
    });
    pricingHydrationBlockedPanel.hidden = pricingHydrationBlocked.length === 0;
  }

  function addPricingHydrationBlocked(data, fallbackMessage) {
    data = data || {};
    var productId = Number(data.product_id || 0);
    var reasons = Array.isArray(data.blocked_reasons) ? data.blocked_reasons.filter(Boolean) : [];
    if (!reasons.length && data.message) reasons.push(String(data.message));
    if (!reasons.length && fallbackMessage) reasons.push(String(fallbackMessage));
    pricingHydrationBlocked.push({
      productId: productId,
      name: String(data.product_name || ''),
      reasons: reasons
    });
    renderPricingHydrationBlocked();
  }

  function resetPricingHydrationPreview() {
    pricingHydrationReady = [];
    pricingHydrationBlocked = [];
    Object.keys(pricingHydrationSummary).forEach(function (key) { setPricingHydrationCount(key, 0); });
    if (pricingHydrationRunButton) pricingHydrationRunButton.disabled = true;
    if (pricingHydrationStatus) pricingHydrationStatus.textContent = '';
    renderPricingHydrationBlocked();
  }

  function setCatalogControlsForPricingHydration(disabled) {
    if (pricingHydrationPreviewButton) pricingHydrationPreviewButton.disabled = disabled;
    if (pricingHydrationRunButton) pricingHydrationRunButton.disabled = disabled || pricingHydrationReady.length === 0;
    if (scanButton) scanButton.disabled = disabled;
    if (adoptReadyButton) adoptReadyButton.disabled = disabled || matcherReady.length === 0;
    if (readinessButton) readinessButton.disabled = disabled;
    if (hydrationPreviewButton) hydrationPreviewButton.disabled = disabled;
    if (hydrationRunButton) hydrationRunButton.disabled = disabled || hydrationReady.length === 0;
  }

  function finishPricingHydrationPreview() {
    pricingHydrationRunning = false;
    pricingHydrationMode = '';
    if (pricingHydrationPause) pricingHydrationPause.hidden = true;
    setCatalogControlsForPricingHydration(false);
    if (pricingHydrationStatus) {
      pricingHydrationStatus.textContent = AthSpecimenAdoption.i18n.pricingHydrationPreviewComplete
        .replace('%e', String(pricingHydrationSummary.eligible))
        .replace('%p', String(pricingHydrationSummary.pairs))
        .replace('%l', String(pricingHydrationSummary.sales))
        .replace('%b', String(pricingHydrationSummary.blocked))
        .replace('%s', String(pricingHydrationSummary.skipped));
    }
  }

  function runPricingHydrationPreview(ids) {
    pricingHydrationRunning = true;
    pricingHydrationMode = 'preview';
    pricingHydrationPaused = false;
    if (pricingHydrationPause) pricingHydrationPause.hidden = false;
    setCatalogControlsForPricingHydration(true);
    var queue = ids.slice();
    var total = ids.length;

    function next() {
      if (pricingHydrationPaused) {
        pricingHydrationRunning = false;
        pricingHydrationMode = '';
        if (pricingHydrationPause) pricingHydrationPause.hidden = true;
        setCatalogControlsForPricingHydration(false);
        if (pricingHydrationStatus) pricingHydrationStatus.textContent = AthSpecimenAdoption.i18n.pricingHydrationPaused;
        return;
      }
      if (!queue.length) { finishPricingHydrationPreview(); return; }
      var productId = queue.shift();
      if (pricingHydrationStatus) pricingHydrationStatus.textContent = AthSpecimenAdoption.i18n.pricingHydrationPreviewProgress
        .replace('%c', String(pricingHydrationSummary.checked + 1)).replace('%t', String(total));
      requestLegacyPricingPlan(productId).then(function (data) {
        setPricingHydrationCount('checked', pricingHydrationSummary.checked + 1);
        if (data.eligible) {
          pricingHydrationReady.push(Number(data.product_id || 0));
          setPricingHydrationCount('eligible', pricingHydrationSummary.eligible + 1);
          setPricingHydrationCount('pairs', pricingHydrationSummary.pairs + Number(data.counts && data.counts.pairs || 0));
          setPricingHydrationCount('sales', pricingHydrationSummary.sales + Number(data.counts && data.counts.sales || 0));
        } else if (data.readiness_status === 'needs_pricing' || data.status === 'blocked') {
          setPricingHydrationCount('blocked', pricingHydrationSummary.blocked + 1);
          addPricingHydrationBlocked(data);
        } else {
          setPricingHydrationCount('skipped', pricingHydrationSummary.skipped + 1);
        }
      }).catch(function (error) {
        setPricingHydrationCount('checked', pricingHydrationSummary.checked + 1);
        setPricingHydrationCount('blocked', pricingHydrationSummary.blocked + 1);
        addPricingHydrationBlocked({product_id: productId}, AthSpecimenAdoption.i18n.pricingHydrationPreviewRequestFailed.replace('%s', error.message));
      }).finally(next);
    }
    next();
  }

  function finishPricingHydrationRun(done, failed) {
    pricingHydrationRunning = false;
    pricingHydrationMode = '';
    pricingHydrationReady = [];
    if (pricingHydrationPause) pricingHydrationPause.hidden = true;
    setCatalogControlsForPricingHydration(false);
    if (pricingHydrationRunButton) pricingHydrationRunButton.disabled = true;
    if (pricingHydrationStatus) pricingHydrationStatus.textContent = AthSpecimenAdoption.i18n.pricingHydrationComplete
      .replace('%d', String(done)).replace('%f', String(failed));
  }

  function runPricingHydration() {
    if (!pricingHydrationReady.length || pricingHydrationRunning || hydrationRunning || matcherMode || readinessRunning || wooReconcileRunning) return;
    pricingHydrationRunning = true;
    pricingHydrationMode = 'hydrate';
    pricingHydrationPaused = false;
    if (pricingHydrationPause) pricingHydrationPause.hidden = false;
    setCatalogControlsForPricingHydration(true);
    var queue = pricingHydrationReady.slice();
    var total = queue.length;
    var done = 0;
    var failed = 0;

    function next() {
      if (pricingHydrationPaused) {
        pricingHydrationRunning = false;
        pricingHydrationMode = '';
        pricingHydrationReady = queue.slice();
        if (pricingHydrationPause) pricingHydrationPause.hidden = true;
        setCatalogControlsForPricingHydration(false);
        if (pricingHydrationStatus) pricingHydrationStatus.textContent = AthSpecimenAdoption.i18n.pricingHydrationPaused;
        return;
      }
      if (!queue.length) { finishPricingHydrationRun(done, failed); return; }
      var productId = queue.shift();
      if (pricingHydrationStatus) pricingHydrationStatus.textContent = AthSpecimenAdoption.i18n.pricingHydrationProgress
        .replace('%c', String(done + failed + 1)).replace('%t', String(total));
      requestLegacyPricingHydrate(productId).then(function (data) {
        done++;
        setPricingHydrationCount('hydrated', pricingHydrationSummary.hydrated + 1);
        var row = matcherRow(productId);
        var target = row ? row.querySelector('.ath-commerce-readiness-result') : null;
        if (target) {
          target.hidden = false;
          target.textContent = AthSpecimenAdoption.i18n.pricingHydratedRow
            .replace('%p', String(data.pairs || 0))
            .replace('%s', String(data.snapshot_id || 'saved'));
          target.className = 'ath-commerce-readiness-result is-pricing-hydrated';
        }
      }).catch(function (error) {
        failed++;
        var row = matcherRow(productId);
        var target = row ? row.querySelector('.ath-commerce-readiness-result') : null;
        if (target) {
          target.hidden = false;
          target.textContent = 'Review — ' + error.message;
          target.className = 'ath-commerce-readiness-result is-review';
        }
      }).finally(next);
    }
    next();
  }

  if (pricingHydrationPreviewButton) pricingHydrationPreviewButton.addEventListener('click', function () {
    if (pricingHydrationRunning || hydrationRunning || matcherMode || readinessRunning || wooReconcileRunning) return;
    resetPricingHydrationPreview();
    pricingHydrationRunning = true;
    pricingHydrationMode = 'loading';
    setCatalogControlsForPricingHydration(true);
    if (pricingHydrationStatus) pricingHydrationStatus.textContent = AthSpecimenAdoption.i18n.pricingHydrationLoading;
    fetchAllReadinessIds().then(function (ids) {
      if (!ids.length) {
        pricingHydrationRunning = false;
        pricingHydrationMode = '';
        setCatalogControlsForPricingHydration(false);
        if (pricingHydrationStatus) pricingHydrationStatus.textContent = AthSpecimenAdoption.i18n.noAdoptedProducts;
        return;
      }
      runPricingHydrationPreview(ids);
    }).catch(function (error) {
      pricingHydrationRunning = false;
      pricingHydrationMode = '';
      setCatalogControlsForPricingHydration(false);
      if (pricingHydrationPause) pricingHydrationPause.hidden = true;
      if (pricingHydrationStatus) pricingHydrationStatus.textContent = error.message;
    });
  });

  if (pricingHydrationRunButton) pricingHydrationRunButton.addEventListener('click', runPricingHydration);
  if (pricingHydrationPause) pricingHydrationPause.addEventListener('click', function () { pricingHydrationPaused = true; });


  var wooReconcile = root.querySelector('.ath-legacy-woo-reconciliation');
  var wooReconcilePreviewButton = wooReconcile ? wooReconcile.querySelector('.ath-preview-legacy-woo-reconciliation') : null;
  var wooReconcileRunButton = wooReconcile ? wooReconcile.querySelector('.ath-run-legacy-woo-reconciliation') : null;
  var wooReconcilePause = wooReconcile ? wooReconcile.querySelector('.ath-pause-legacy-woo-reconciliation') : null;
  var wooReconcileStatus = wooReconcile ? wooReconcile.querySelector('.ath-legacy-woo-reconciliation-status') : null;
  var wooReconcileBlockedPanel = wooReconcile ? wooReconcile.querySelector('.ath-legacy-woo-reconciliation-blocked') : null;
  var wooReconcileBlockedList = wooReconcile ? wooReconcile.querySelector('.ath-legacy-woo-reconciliation-blocked-list') : null;
  var wooReconcileCountNodes = wooReconcile ? wooReconcile.querySelectorAll('[data-ath-woo-reconcile-count]') : [];
  var wooReconcileReady = [];
  var wooReconcileBlocked = [];
  var wooReconcilePaused = false;
  var wooReconcileRunning = false;
  var wooReconcileMode = '';
  var wooReconcileSummary = {checked:0, eligible:0, pairs:0, remaps:0, prices:0, blocked:0, skipped:0, reconciled:0};

  function setWooReconcileCount(key, value) {
    wooReconcileSummary[key] = value;
    Array.prototype.forEach.call(wooReconcileCountNodes, function (el) {
      if (el.getAttribute('data-ath-woo-reconcile-count') === key) el.textContent = String(value);
    });
  }

  function renderWooReconcileBlocked() {
    if (!wooReconcileBlockedPanel || !wooReconcileBlockedList) return;
    while (wooReconcileBlockedList.firstChild) wooReconcileBlockedList.removeChild(wooReconcileBlockedList.firstChild);
    wooReconcileBlocked.forEach(function (item) {
      var li = document.createElement('li');
      var title = document.createElement('strong');
      title.textContent = (item.name || ('Woo #' + item.productId)) + ' (Woo #' + item.productId + ')';
      var reason = document.createElement('span');
      reason.textContent = (item.reasons && item.reasons.length ? item.reasons : ['Blocked without a detailed reason.']).join('; ');
      li.appendChild(title); li.appendChild(reason); wooReconcileBlockedList.appendChild(li);
    });
    wooReconcileBlockedPanel.hidden = wooReconcileBlocked.length === 0;
  }

  function addWooReconcileBlocked(data, fallbackMessage) {
    data = data || {};
    var reasons = Array.isArray(data.blocked_reasons) ? data.blocked_reasons.filter(Boolean) : [];
    if (!reasons.length && data.message) reasons.push(String(data.message));
    if (!reasons.length && fallbackMessage) reasons.push(String(fallbackMessage));
    wooReconcileBlocked.push({productId:Number(data.product_id || 0), name:String(data.product_name || ''), reasons:reasons});
    renderWooReconcileBlocked();
  }

  function resetWooReconcilePreview() {
    wooReconcileReady = [];
    wooReconcileBlocked = [];
    Object.keys(wooReconcileSummary).forEach(function (key) { setWooReconcileCount(key, 0); });
    if (wooReconcileRunButton) wooReconcileRunButton.disabled = true;
    if (wooReconcileStatus) wooReconcileStatus.textContent = '';
    renderWooReconcileBlocked();
  }

  function setCatalogControlsForWooReconcile(disabled) {
    if (wooReconcilePreviewButton) wooReconcilePreviewButton.disabled = disabled;
    if (wooReconcileRunButton) wooReconcileRunButton.disabled = disabled || wooReconcileReady.length === 0;
    if (scanButton) scanButton.disabled = disabled;
    if (adoptReadyButton) adoptReadyButton.disabled = disabled || matcherReady.length === 0;
    if (readinessButton) readinessButton.disabled = disabled;
    if (hydrationPreviewButton) hydrationPreviewButton.disabled = disabled;
    if (hydrationRunButton) hydrationRunButton.disabled = disabled || hydrationReady.length === 0;
    if (pricingHydrationPreviewButton) pricingHydrationPreviewButton.disabled = disabled;
    if (pricingHydrationRunButton) pricingHydrationRunButton.disabled = disabled || pricingHydrationReady.length === 0;
  }

  function finishWooReconcilePreview() {
    wooReconcileRunning = false;
    wooReconcileMode = '';
    if (wooReconcilePause) wooReconcilePause.hidden = true;
    setCatalogControlsForWooReconcile(false);
    if (wooReconcileStatus) wooReconcileStatus.textContent = AthSpecimenAdoption.i18n.wooReconcilePreviewComplete
      .replace('%e', String(wooReconcileSummary.eligible))
      .replace('%p', String(wooReconcileSummary.pairs))
      .replace('%r', String(wooReconcileSummary.remaps))
      .replace('%u', String(wooReconcileSummary.prices))
      .replace('%b', String(wooReconcileSummary.blocked))
      .replace('%s', String(wooReconcileSummary.skipped));
  }

  function runWooReconcilePreview(ids) {
    wooReconcileRunning = true;
    wooReconcileMode = 'preview';
    wooReconcilePaused = false;
    if (wooReconcilePause) wooReconcilePause.hidden = false;
    setCatalogControlsForWooReconcile(true);
    var queue = ids.slice();
    var total = ids.length;
    function next() {
      if (wooReconcilePaused) {
        wooReconcileRunning = false; wooReconcileMode = '';
        if (wooReconcilePause) wooReconcilePause.hidden = true;
        setCatalogControlsForWooReconcile(false);
        if (wooReconcileStatus) wooReconcileStatus.textContent = AthSpecimenAdoption.i18n.wooReconcilePaused;
        return;
      }
      if (!queue.length) { finishWooReconcilePreview(); return; }
      var productId = queue.shift();
      if (wooReconcileStatus) wooReconcileStatus.textContent = AthSpecimenAdoption.i18n.wooReconcilePreviewProgress
        .replace('%c', String(wooReconcileSummary.checked + 1)).replace('%t', String(total));
      requestLegacyWooReconcilePlan(productId).then(function (data) {
        setWooReconcileCount('checked', wooReconcileSummary.checked + 1);
        if (data.eligible) {
          wooReconcileReady.push(Number(data.product_id || 0));
          setWooReconcileCount('eligible', wooReconcileSummary.eligible + 1);
          setWooReconcileCount('pairs', wooReconcileSummary.pairs + Number(data.counts && data.counts.pairs || 0));
          setWooReconcileCount('remaps', wooReconcileSummary.remaps + Number(data.counts && data.counts.remaps || 0));
          setWooReconcileCount('prices', wooReconcileSummary.prices + Number(data.counts && data.counts.price_updates || 0));
        } else if (data.readiness_status === 'needs_sync' || data.status === 'blocked') {
          setWooReconcileCount('blocked', wooReconcileSummary.blocked + 1);
          addWooReconcileBlocked(data);
        } else {
          setWooReconcileCount('skipped', wooReconcileSummary.skipped + 1);
        }
      }).catch(function (error) {
        setWooReconcileCount('checked', wooReconcileSummary.checked + 1);
        setWooReconcileCount('blocked', wooReconcileSummary.blocked + 1);
        addWooReconcileBlocked({product_id:productId}, AthSpecimenAdoption.i18n.wooReconcilePreviewRequestFailed.replace('%s', error.message));
      }).finally(next);
    }
    next();
  }

  function finishWooReconcileRun(done, failed) {
    wooReconcileRunning = false; wooReconcileMode = ''; wooReconcileReady = [];
    if (wooReconcilePause) wooReconcilePause.hidden = true;
    setCatalogControlsForWooReconcile(false);
    if (wooReconcileRunButton) wooReconcileRunButton.disabled = true;
    if (wooReconcileStatus) wooReconcileStatus.textContent = AthSpecimenAdoption.i18n.wooReconcileComplete.replace('%d', String(done)).replace('%f', String(failed));
  }

  function runWooReconcile() {
    if (!wooReconcileReady.length || wooReconcileRunning || pricingHydrationRunning || hydrationRunning || matcherMode || readinessRunning) return;
    if (!window.confirm(AthSpecimenAdoption.i18n.wooReconcileConfirm)) return;
    wooReconcileRunning = true; wooReconcileMode = 'run'; wooReconcilePaused = false;
    if (wooReconcilePause) wooReconcilePause.hidden = false;
    setCatalogControlsForWooReconcile(true);
    var queue = wooReconcileReady.slice();
    var total = queue.length, done = 0, failed = 0;
    function next() {
      if (wooReconcilePaused) {
        wooReconcileRunning = false; wooReconcileMode = ''; wooReconcileReady = queue.slice();
        if (wooReconcilePause) wooReconcilePause.hidden = true;
        setCatalogControlsForWooReconcile(false);
        if (wooReconcileStatus) wooReconcileStatus.textContent = AthSpecimenAdoption.i18n.wooReconcilePaused;
        return;
      }
      if (!queue.length) { finishWooReconcileRun(done, failed); return; }
      var productId = queue.shift();
      if (wooReconcileStatus) wooReconcileStatus.textContent = AthSpecimenAdoption.i18n.wooReconcileProgress
        .replace('%c', String(done + failed + 1)).replace('%t', String(total));
      requestLegacyWooReconcile(productId).then(function (data) {
        done++; setWooReconcileCount('reconciled', wooReconcileSummary.reconciled + 1);
        var row = matcherRow(productId); var target = row ? row.querySelector('.ath-commerce-readiness-result') : null;
        if (target) {
          target.hidden = false;
          target.textContent = AthSpecimenAdoption.i18n.wooReconciledRow
            .replace('%p', String(data.pairs || 0)).replace('%r', String(data.remaps || 0))
            .replace('%u', String(data.price_updates || 0)).replace('%s', String(data.snapshot_id || 'saved'));
          target.className = 'ath-commerce-readiness-result is-woo-reconciled';
        }
      }).catch(function (error) {
        failed++;
        var row = matcherRow(productId); var target = row ? row.querySelector('.ath-commerce-readiness-result') : null;
        if (target) { target.hidden = false; target.textContent = 'Review — ' + error.message; target.className = 'ath-commerce-readiness-result is-review'; }
      }).finally(next);
    }
    next();
  }

  if (wooReconcilePreviewButton) wooReconcilePreviewButton.addEventListener('click', function () {
    if (wooReconcileRunning || pricingHydrationRunning || hydrationRunning || matcherMode || readinessRunning) return;
    resetWooReconcilePreview(); wooReconcileRunning = true; wooReconcileMode = 'loading';
    setCatalogControlsForWooReconcile(true);
    if (wooReconcileStatus) wooReconcileStatus.textContent = AthSpecimenAdoption.i18n.wooReconcileLoading;
    fetchAllReadinessIds().then(function (ids) {
      if (!ids.length) {
        wooReconcileRunning = false; wooReconcileMode = ''; setCatalogControlsForWooReconcile(false);
        if (wooReconcileStatus) wooReconcileStatus.textContent = AthSpecimenAdoption.i18n.noAdoptedProducts;
        return;
      }
      runWooReconcilePreview(ids);
    }).catch(function (error) {
      wooReconcileRunning = false; wooReconcileMode = ''; setCatalogControlsForWooReconcile(false);
      if (wooReconcilePause) wooReconcilePause.hidden = true;
      if (wooReconcileStatus) wooReconcileStatus.textContent = error.message;
    });
  });
  if (wooReconcileRunButton) wooReconcileRunButton.addEventListener('click', runWooReconcile);
  if (wooReconcilePause) wooReconcilePause.addEventListener('click', function () { wooReconcilePaused = true; });

  var all = root.querySelector('.ath-select-all-compatible');
  var bulk = root.querySelector('.ath-adopt-selected');
  var pause = root.querySelector('.ath-pause-adoption');
  var bulkStatus = root.querySelector('.ath-adoption-bulk-status');
  var paused = false;

  function checks() { return Array.prototype.slice.call(root.querySelectorAll('.ath-adopt-checkbox')); }
  function updateBulk() {
    if (!bulk) return;
    bulk.disabled = !checks().some(function (c) { return c.checked && !c.disabled; });
  }
  if (all) all.addEventListener('change', function () {
    checks().forEach(function (c) { if (!c.disabled) c.checked = all.checked; });
    updateBulk();
  });
  root.addEventListener('change', function (event) {
    if (event.target.classList.contains('ath-adopt-checkbox')) updateBulk();
  });
  if (pause) pause.addEventListener('click', function () {
    paused = true;
    pause.hidden = true;
    if (bulkStatus) bulkStatus.textContent = AthSpecimenAdoption.i18n.paused;
  });

  if (bulk) bulk.addEventListener('click', function () {
    if (hydrationRunning || matcherMode || readinessRunning || pricingHydrationRunning || wooReconcileRunning) return;
    var queue = checks().filter(function (c) { return c.checked && !c.disabled; });
    if (!queue.length) return;
    bulk.disabled = true;
    paused = false;
    if (pause) pause.hidden = false;
    var done = 0;
    var failed = 0;

    function next() {
      if (paused || !queue.length) {
        if (pause) pause.hidden = true;
        updateBulk();
        if (!paused && bulkStatus) bulkStatus.textContent = AthSpecimenAdoption.i18n.complete.replace('%d', done).replace('%f', failed);
        return;
      }
      var checkbox = queue.shift();
      var row = checkbox.closest('tr');
      if (bulkStatus) bulkStatus.textContent = AthSpecimenAdoption.i18n.progress.replace('%c', done + 1).replace('%t', done + queue.length + 1);
      requestAdoption(checkbox.value, checkbox.dataset.styleAttr, checkbox.dataset.licenseAttr, true).then(function (data) {
        done++;
        checkbox.checked = false;
        checkbox.disabled = true;
        if (row) {
          var status = row.querySelector('.ath-adoption-status');
          if (status) { status.textContent = AthSpecimenAdoption.i18n.adopted; status.className = 'ath-adoption-status is-good'; }
          var action = row.lastElementChild;
          if (action && data.edit_url) action.innerHTML = '<a class="button" href="' + data.edit_url + '">' + AthSpecimenAdoption.i18n.openFont + '</a>';
        }
      }).catch(function (error) {
        failed++;
        if (row) {
          var small = row.querySelector('td:nth-last-child(2) small');
          if (small) small.textContent = error.message;
        }
      }).finally(next);
    }
    next();
  });
  updateBulk();
})();
