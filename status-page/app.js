(() => {
  const cfg = window.STATUS_CONFIG;
  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const fmt = (value) => value ? new Date(value).toLocaleString(undefined,{dateStyle:'medium',timeStyle:'short'}) : '';
  const stateLabel = (s) => s === 'outage' ? 'Outage' : s === 'degraded' ? 'Degraded' : s === 'unknown' ? 'Unknown' : 'Operational';
  const incidentStatusLabel = (s) => s === 'monitoring' ? 'Monitoring' : s === 'resolved' ? 'Resolved' : s === 'investigating' ? 'Investigating' : String(s || '');
  const monthLabel = (date) => new Date(`${date}T12:00:00`).toLocaleDateString(undefined,{month:'long',year:'numeric'});
  const barLabel = (date) => new Date(`${date}T12:00:00`).toLocaleDateString(undefined,{month:'short',day:'numeric'});
  let snapshot;
  let publicPresentation = {show_site_names: true, show_device_counts: true};
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
      const history = site.history_7d || [];
      const label = siteLabel(site, index);
      const meta = [site.state_name, publicPresentation.show_device_counts && site.monitored_devices !== undefined ? `${site.monitored_devices} monitored` : '', publicPresentation.show_device_counts && site.down_devices !== undefined ? `${site.down_devices} down` : ''].filter(Boolean).join(' · ');
      return `<article class="state-card" id="site-${esc(site.key)}"><div class="state-head"><span class="state-name">${esc(label)}</span><span class="state-status ${site.status}">${stateLabel(site.status)}</span></div><div class="uptime">${site.uptime_60d == null ? '—' : `${Number(site.uptime_60d).toFixed(2)}%`}</div><div class="meta">${esc(meta)}</div><div class="history"><div class="history-title"><span>Last 7 days</span><span>Click a day for details</span></div><div class="bars">${history.map((day) => `<a class="bar ${day.status}" href="#activity" data-site="${esc(site.key)}" data-date="${esc(day.date)}" title="${esc(day.date)}: ${stateLabel(day.status)}" aria-label="${esc(label)} ${esc(day.date)} ${esc(stateLabel(day.status))}"></a>`).join('')}</div><div class="days">${history.map((day) => `<span>${esc(barLabel(day.date))}</span>`).join('')}</div></div></article>`;
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
    const cls = `incident-${incident.severity} incident-${incident.status}`;
    const updates = (incident.updates || []).map((u) => `<p>${esc(u.message)} <span class="tag">${esc(fmt(u.created_at))}</span></p>`).join('');
    return `<details class="event ${cls}" open><summary><strong>${esc(incident.site || 'Site')} — ${esc(incident.summary)}</strong><span class="tag">${esc(incidentStatusLabel(incident.status))} · ${esc(fmt(incident.started_at))}</span></summary>${updates || '<p>No updates posted.</p>'}</details>`;
  }
  function historyIncidentHtml(incident) {
    const cls = `incident-${incident.site_status} incident-${incident.status}`;
    const count = Number(incident.update_count || 0);
    return `<details class="event ${cls}" open><summary><strong>${esc(incident.site || 'Site')} — ${esc(incident.summary)}</strong><span class="tag">${esc(incidentStatusLabel(incident.status))} · ${esc(fmt(incident.started_at))}</span></summary><p>${count ? `${count} status update${count === 1 ? '' : 's'} recorded.` : 'No status updates recorded.'}</p></details>`;
  }
  function showActivity(siteKey, date) {
    const siteIndex = snapshot.sites.findIndex((s) => s.key === siteKey);
    const site = siteIndex >= 0 ? snapshot.sites[siteIndex] : null;
    const day = site?.history_7d?.find((d) => d.date === date);
    if (!site || !day) return;
    $('activity').hidden = false;
    $('activity-title').textContent = `${siteLabel(site, siteIndex)} · Activity for ${barLabel(date)}`;
    const incidents = (day.incidents || []).map(incidentHtml).join('');
    const maintenance = (day.maintenance || []).map((m) => `<details class="event ${esc(m.status || '')}"><summary><strong>${esc(m.overview)}</strong><span class="tag">Maintenance · ${esc(fmt(m.starts_at))}</span></summary><p>${esc(m.description || 'Scheduled maintenance')}</p><p>${esc(fmt(m.starts_at))} – ${esc(fmt(m.ends_at))}</p></details>`).join('');
    $('activity-body').innerHTML = incidents + maintenance || '<p>No incidents or maintenance were recorded for this day.</p>';
    $('activity').scrollIntoView({behavior:'smooth',block:'nearest'});
  }
  function renderFeeds(data) {
    const incidents = data.status_feed || [];
    $('incidents').hidden = incidents.length === 0;
    $('incident-list').innerHTML = incidents.map(incidentHtml).join('');
    const maintenance = data.maintenance || [];
    $('maintenance').hidden = maintenance.length === 0;
    $('maintenance-list').innerHTML = maintenance.map((m) => `<details class="event ${m.status}"><summary><strong>${esc(m.overview)}</strong><span class="tag">${esc(m.status)} · ${esc(fmt(m.starts_at))}</span></summary><p>${esc(m.description || '')}</p><p>${esc(fmt(m.starts_at))} – ${esc(fmt(m.ends_at))}</p></details>`).join('');
  }
  function applyColors(configuration) {
    const root = document.documentElement;
    const vars = { '--green': configuration.color_operational, '--orange': configuration.color_degraded, '--yellow': configuration.color_monitoring, '--red': configuration.color_outage, '--unknown': configuration.color_unknown, '--purple': configuration.color_maintenance_scheduled, '--blue': configuration.color_maintenance_active, '--maintenance-completed': configuration.color_maintenance_completed };
    Object.entries(vars).forEach(([name, value]) => { if (typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value)) root.style.setProperty(name, value); });
  }
  function renderHistory(events) {
    const groups = events.reduce((map, event) => { const key = event.date?.slice(0, 7) || 'unknown'; (map[key] ||= []).push(event); return map; }, {});
    const keys = Object.keys(groups).sort().reverse();
    $('history-archive').hidden = keys.length === 0;
    $('history-months').innerHTML = keys.map((key, index) => `<details class="history-month" ${index === 0 ? 'open' : ''}><summary>${esc(monthLabel(`${key}-01`))}<span>${groups[key].length} event${groups[key].length === 1 ? '' : 's'}</span></summary><div class="history-events">${groups[key].map((event) => event.event_type === 'incident' ? historyIncidentHtml(event) : `<details class="event maintenance-history ${esc(event.status || '')}" open><summary><strong>${esc(event.site || 'All sites')} — ${esc(event.overview)}</strong><span class="tag">Maintenance · ${esc(fmt(event.starts_at))}</span></summary><p>${esc(event.description || 'Scheduled maintenance')}</p><p>${esc(fmt(event.starts_at))} – ${esc(fmt(event.ends_at))}</p></details>`).join('')}</div></details>`).join('');
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
      renderOverall(snapshot.overall); renderStates(snapshot.sites || []); renderFeeds(snapshot); renderHistory(snapshot.history_60d || []);
      $('last-checked').textContent = `Checked ${new Date().toLocaleTimeString()}`; $('error').hidden = true;
    } catch (error) { $('error').hidden = false; $('error').textContent = `Status data is temporarily unavailable. ${error.message}`; }
  }
  $('brand').textContent = cfg.brandName; $('subtitle').textContent = cfg.subtitle; $('footer-brand').textContent = cfg.brandName;
  $('subscribe-open').addEventListener('click', openSubscribe);
  $('subscribe-close').addEventListener('click', () => { $('subscribe-modal').hidden = true; });
  $('subscribe-form').addEventListener('submit', submitSubscription);
  refresh(); setInterval(refresh, Number(cfg.pollMs) || 30000);
})();
