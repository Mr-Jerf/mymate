(() => {
  const cfg = window.STATUS_CONFIG;
  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const fmt = (value) => value ? new Date(value).toLocaleString(undefined,{dateStyle:'medium',timeStyle:'short'}) : '';
  const stateLabel = (s) => s === 'outage' ? 'Outage' : s === 'degraded' ? 'Degraded' : s === 'unknown' ? 'Unknown' : 'Operational';
  const incidentStatusLabel = (s) => s === 'monitoring' ? 'Monitoring' : s === 'resolved' ? 'Resolved' : s === 'investigating' ? 'Investigating' : String(s || '');
  const monthLabel = (date) => new Date(`${date}T12:00:00`).toLocaleDateString(undefined,{month:'long',year:'numeric'});
  const barLabel = (date) => new Date(`${date}T12:00:00`).toLocaleDateString(undefined,{month:'short',day:'numeric'});
  const tooltipDate = (date) => new Date(`${date}T12:00:00`).toLocaleDateString('en-US',{month:'long',day:'2-digit',year:'numeric'});
  let snapshot;
  let historyRange = 7;
  let publicPresentation = {show_site_names: true, show_device_counts: true};
  const eventTimestamp = (event) => {
    const updates = Array.isArray(event.updates) ? event.updates : [];
    const latestUpdate = updates.map((update) => update.created_at).filter(Boolean).sort().pop();
    return event.ended_at || event.ends_at || event.latest_update_at || latestUpdate || event.started_at || event.starts_at || '';
  };
  const newestEventsFirst = (events) => [...events].sort((a, b) => {
    const timestampOrder = String(eventTimestamp(b)).localeCompare(String(eventTimestamp(a)));
    return timestampOrder || String(b.started_at || b.starts_at || '').localeCompare(String(a.started_at || a.starts_at || ''));
  });
  const siteLabel = (site, index = 0) => publicPresentation.show_site_names && site.name ? site.name : `Public site ${index + 1}`;
  function renderOverall(overall) {
    const node = $('overall'); node.className = `overall ${overall.status}`;
    node.innerHTML = `<span class="pulse"></span><div><strong>${esc(overall.label)}</strong><small>Last checked just now · Updates automatically</small></div>`;
  }
  function renderStates(sites) {
    if (!sites.length) {
      $('sites').innerHTML = '<p class="empty-state">No public service sites have been configured yet.</p>';
      return;
    }
    $('sites').innerHTML = sites.map((site, index) => {
      const history = (site.history_daily || site.history_7d || []).sort((a, b) => String(a.date).localeCompare(String(b.date))).slice(-historyRange);
      const label = siteLabel(site, index);
      const meta = [site.state_name, publicPresentation.show_device_counts && site.monitored_devices !== undefined ? `${site.monitored_devices} monitored` : '', publicPresentation.show_device_counts && site.down_devices !== undefined ? `${site.down_devices} down` : ''].filter(Boolean).join(' · ');
      return `<article class="state-card" id="site-${esc(site.key)}"><div class="state-head"><span class="state-name">${esc(label)}</span><span class="state-status ${site.status}">${stateLabel(site.status)}</span></div><div class="uptime">${site.uptime_60d == null ? '—' : `${Number(site.uptime_60d).toFixed(2)}%`}</div><div class="meta">${esc(meta)}</div><div class="history"><div class="history-title"><span>Last ${historyRange} days</span><span>Click a day for details</span></div><div class="bars${historyRange > 7 ? ' long-range' : ''}" style="--bar-count:${history.length}">${history.map((day) => { const event = (day.incidents || []).length > 0 || (day.maintenance || []).length > 0; const barStatus = (day.incidents || []).length > 0 ? 'outage' : ((day.maintenance || []).length > 0 ? 'maintenance' : day.status); const statusLabel = event ? ((day.incidents || []).length > 0 ? 'Event' : 'Maintenance') : stateLabel(day.status); return `<a class="bar ${barStatus}" href="#activity" data-site="${esc(site.key)}" data-date="${esc(day.date)}" title="${esc(day.date)}: ${esc(statusLabel)}" aria-label="${esc(label)} ${esc(day.date)} ${esc(statusLabel)}"><span class="bar-tooltip" role="tooltip">${esc(tooltipDate(day.date))}</span></a>`; }).join('')}</div><div class="days${historyRange > 7 ? ' long-range-days' : ''}" style="--bar-count:${history.length}">${history.map((day) => `<span>${esc(barLabel(day.date))}</span>`).join('')}</div></div></article>`;
    }).join('');
    document.querySelectorAll('.bar').forEach((bar) => bar.addEventListener('click', (event) => { event.preventDefault(); showActivity(bar.dataset.site, bar.dataset.date); }));
  }
  function openSubscribe() {
    const form = $('subscribe-form'); const select = $('subscribe-site');
    select.innerHTML = (snapshot.sites || []).map((site, index) => `<option value="${esc(site.key)}">${esc(siteLabel(site, index))}</option>`).join('');
    form.reset(); $('subscribe-message').textContent = ''; $('subscribe-modal').hidden = false; $('subscribe-email').focus();
  }
  async function submitSubscription(event) {
    event.preventDefault();
    const form = event.currentTarget; const f = new FormData(form); const message = $('subscribe-message');
    const preferences = {outage:f.has('outage'), degraded:f.has('degraded'), updates:f.has('updates'), resolved:f.has('resolved'), maintenance:f.has('maintenance')};
    try { const response = await fetch('/api/public/status-subscriptions', {method:'POST', headers:{'Content-Type':'application/json',Accept:'application/json'}, body:JSON.stringify({site_key:f.get('site_key'),email:f.get('email'),preferences})}); if (!response.ok && response.status !== 202) throw new Error(); message.textContent = 'If eligible, a confirmation email will be sent.'; form.reset(); } catch (_) { message.textContent = 'We could not process that request right now.'; }
  }
  function incidentHtml(incident) {
    const severity = ['outage', 'degraded', 'monitoring'].includes(incident.site_status) ? incident.site_status : 'unknown';
    const status = ['investigating', 'monitoring', 'resolved'].includes(incident.status) ? incident.status : 'unknown';
    const cls = `incident-${severity} incident-${status}`;
    const updates = (incident.updates || []).map((u) => `<p>${esc(u.message)} <span class="tag">${esc(fmt(u.created_at))}</span></p>`).join('');
    return `<details class="event ${cls}" open><summary><strong>${esc(incident.site || 'Site')} — ${esc(incident.summary)}</strong><span class="tag">${esc(incidentStatusLabel(incident.status))} · ${esc(fmt(incident.started_at))}</span></summary>${updates || '<p>No updates posted.</p>'}</details>`;
  }
  function historyIncidentHtml(incident) {
    const severity = ['outage', 'degraded', 'monitoring'].includes(incident.site_status) ? incident.site_status : 'unknown';
    const status = ['investigating', 'monitoring', 'resolved'].includes(incident.status) ? incident.status : 'unknown';
    const cls = `history-incident incident-${severity} incident-${status}`;
    const count = Number(incident.update_count || 0);
    const resolved = incident.status === 'resolved' ? '<span class="history-resolved">Resolved</span>' : '';
    const updates = (incident.updates || []).map((u) => `<p>${esc(u.message)} <span class="tag">${esc(fmt(u.created_at))}</span></p>`).join('');
    return `<details class="event ${cls}" open><summary><strong>${esc(incident.site || 'Site')} — ${esc(incident.summary)}</strong>${resolved}<span class="tag">${esc(incidentStatusLabel(incident.status))} · ${esc(fmt(incident.started_at))}</span></summary>${updates || `<p>${count ? `${count} status update${count === 1 ? '' : 's'} recorded.` : 'No status updates recorded.'}</p>`}</details>`;
  }
  function maintenanceHistoryHtml(maintenance) {
    const status = ['scheduled', 'active', 'completed'].includes(maintenance.status) ? maintenance.status : 'scheduled';
    const label = status === 'active' ? 'In progress' : status[0].toUpperCase() + status.slice(1);
    const sites = Array.isArray(maintenance.sites) && maintenance.sites.length > 0 ? maintenance.sites.join(', ') : 'Public sites';
    const resolved = status === 'completed' ? '<span class="history-resolved">Resolved</span>' : '';
    return `<details class="event maintenance-history maintenance-${status}" open><summary><strong>${esc(maintenance.overview)}</strong>${resolved}<span class="history-maintenance-status ${status}">${esc(label)}</span><span class="tag">Maintenance · ${esc(fmt(maintenance.starts_at))}</span></summary><p>Impacted sites: ${esc(sites)}</p><p>${esc(maintenance.description || 'Scheduled maintenance')}</p><p>${esc(fmt(maintenance.starts_at))} – ${esc(fmt(maintenance.ends_at))}</p></details>`;
  }
  function renderMaintenance(windows) {
    const panel = $('maintenance-panel');
    panel.hidden = !Array.isArray(windows) || windows.length === 0;
    $('maintenance-list').innerHTML = (windows || []).map(maintenanceHistoryHtml).join('');
  }
  function showActivity(siteKey, date) {
    const siteIndex = snapshot.sites.findIndex((s) => s.key === siteKey);
    const site = siteIndex >= 0 ? snapshot.sites[siteIndex] : null;
    const day = site?.history_daily?.find((d) => d.date === date) || site?.history_7d?.find((d) => d.date === date);
    if (!site || !day) return;
    $('activity').hidden = false;
    $('activity-title').textContent = `${siteLabel(site, siteIndex)} · Activity for ${barLabel(date)}`;
    const events = newestEventsFirst([
      ...(day.incidents || []).map((event) => ({...event, event_type: 'incident'})),
      ...(day.maintenance || []).map((event) => ({...event, event_type: 'maintenance'})),
    ]);
    $('activity-body').innerHTML = events.map((event) => event.event_type === 'incident' ? historyIncidentHtml(event) : maintenanceHistoryHtml(event)).join('') || '<p>No incidents or maintenance were recorded for this day.</p>';
    $('activity').scrollIntoView({behavior:'smooth',block:'nearest'});
  }

  function applyColors(configuration) {
    const root = document.documentElement;
    const vars = { '--green': configuration.color_operational, '--orange': configuration.color_degraded, '--yellow': configuration.color_monitoring, '--red': configuration.color_outage, '--unknown': configuration.color_unknown, '--purple': configuration.color_maintenance_scheduled, '--blue': configuration.color_maintenance_active, '--maintenance-completed': configuration.color_maintenance_completed };
    Object.entries(vars).forEach(([name, value]) => { if (typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value)) root.style.setProperty(name, value); });
  }
  function renderHistory(events) {
    const groups = events.reduce((map, event) => { const key = event.date?.slice(0, 7) || 'unknown'; (map[key] ||= []).push(event); return map; }, {});
    Object.keys(groups).forEach((key) => { groups[key] = newestEventsFirst(groups[key]); });
    const keys = Object.keys(groups).sort().reverse();
    $('history-archive').hidden = keys.length === 0;
    $('history-months').innerHTML = keys.map((key, index) => `<details class="history-month" ${index === 0 ? 'open' : ''}><summary>${esc(monthLabel(`${key}-01`))}<span>${groups[key].length} event${groups[key].length === 1 ? '' : 's'}</span></summary><div class="history-events">${groups[key].map((event) => event.event_type === 'incident' ? historyIncidentHtml(event) : maintenanceHistoryHtml(event)).join('')}</div></details>`).join('');
  }
  function setHistoryRange(range) {
    historyRange = [7, 30, 60].includes(Number(range)) ? Number(range) : 7;
    document.querySelectorAll('.range-button').forEach((button) => { const active = Number(button.dataset.range) === historyRange; button.classList.toggle('active', active); button.setAttribute('aria-pressed', String(active)); });
    if (snapshot) renderStates(snapshot.sites || []);
  }
  async function refresh() {
    try {
      const response = await fetch(`${cfg.apiBaseUrl.replace(/\/$/,'')}${cfg.apiPath}`, {headers:{Accept:'application/json'}});
      if (!response.ok) throw new Error(`Status API returned ${response.status}`);
      snapshot = (await response.json()).data;
      const presentation = snapshot.configuration || {};
      publicPresentation = presentation;
      applyColors(presentation);
      const logo = $('status-logo'); logo.onerror = () => { logo.onerror = null; logo.src = '/logo.svg'; }; if (presentation.logo_url) logo.src = presentation.logo_url;
      const favicon = $('status-favicon'); favicon.onerror = () => { favicon.onerror = null; favicon.href = '/favicon.svg'; }; if (presentation.favicon_url) favicon.href = presentation.favicon_url;
      $('subscribe-open').hidden = presentation.allow_subscriptions === false || (snapshot.sites || []).length === 0;
      if (presentation.brand_name) { $('brand').textContent = presentation.brand_name; $('footer-brand').textContent = presentation.brand_name; }
      if (presentation.subtitle) $('subtitle').textContent = presentation.subtitle;
      renderOverall(snapshot.overall); renderStates(snapshot.sites || []); renderMaintenance(snapshot.maintenance || []); renderHistory(snapshot.history_60d || []);
      $('last-checked').textContent = `Checked ${new Date().toLocaleTimeString()}`; $('error').hidden = true;
    } catch (error) { $('error').hidden = false; $('error').textContent = `Status data is temporarily unavailable. ${error.message}`; }
  }
  $('brand').textContent = cfg.brandName; $('subtitle').textContent = cfg.subtitle; $('footer-brand').textContent = cfg.brandName;
  $('subscribe-open').addEventListener('click', openSubscribe);
  $('subscribe-close').addEventListener('click', () => { $('subscribe-modal').hidden = true; });
  $('subscribe-form').addEventListener('submit', submitSubscription);
  document.querySelectorAll('.range-button').forEach((button) => button.addEventListener('click', () => setHistoryRange(button.dataset.range)));
  refresh(); setInterval(refresh, Number(cfg.pollMs) || 30000);
})();
