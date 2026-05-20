/**
 * Module view registry. Each view renders real UI for one RBAC module key.
 */

const Views = {
  registry: {},

  register(moduleKey, view) {
    this.registry[moduleKey] = view;
  },

  get(moduleKey) {
    return this.registry[moduleKey] || this.generic(moduleKey);
  },

  generic(moduleKey) {
    const config = Navigation.getConfig(moduleKey);
    return {
      async render() {
        const data = await Api.get(config.route, defaultParams(moduleKey));
        return renderListPage(config.label, config.route, data, moduleKey);
      }
    };
  }
};

function defaultParams(moduleKey) {
  if (moduleKey === 'reports.monthly') return { month: UI.currentMonth() };
  return {};
}

function normalizeItems(data) {
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.items)) return data.items;
  if (Array.isArray(data?.belts)) return data.belts;
  if (Array.isArray(data?.uploads)) return data.uploads;
  if (Array.isArray(data?.records)) return data.records;
  return [];
}

function humanColumn(key) {
  return UI.titleize(key).replace(/\bId\b/g, 'ID');
}

function valueForDisplay(value) {
  if (Array.isArray(value)) return value.length ? value.join(', ') : '-';
  if (value === null || value === undefined || value === '') return '-';
  if (typeof value === 'object') return JSON.stringify(value);
  return value;
}

function isReviewableWorkUpload(upload) {
  return upload?.upload_type === 'WORK' && upload?.authority_visibility === 'HIDDEN';
}

function inferColumns(rows, preferred = []) {
  const first = rows[0] || {};
  const keys = preferred.length ? preferred.filter((key) => key in first) : Object.keys(first).slice(0, 8);
  return keys.map((key) => ({
    key,
    label: humanColumn(key),
    html: key.includes('status') || key.includes('visibility') || key === 'priority',
    render: (row) => {
      const value = row[key];
      if (key.includes('status') || key.includes('visibility') || key === 'priority') return UI.status(value);
      return valueForDisplay(value);
    }
  }));
}

function renderListPage(title, route, data, moduleKey, options = {}) {
  const rows = normalizeItems(data);
  const columns = inferColumns(rows, options.columns || []);
  const actions = [
    UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: `data-refresh` }),
    options.createButton || ''
  ].filter(Boolean).join('');

  return UI.page(title, options.subtitle || route, actions)
    + UI.panel(options.panelTitle || 'Records', UI.table(columns, rows, {
      empty: options.empty || 'No records available',
      rowAttr: options.rowAttr
    }) + renderPagination(data.pagination, moduleKey, options.params || {}));
}

function attachRefresh() {
  document.querySelectorAll('[data-refresh]').forEach((button) => {
    button.addEventListener('click', () => App.refresh());
  });
}

function wireFilters(onSubmit) {
  document.querySelector('.js-filter-form')?.addEventListener('submit', (event) => {
    event.preventDefault();
    onSubmit(UI.formData(event.currentTarget));
  });
}

async function simpleAction(route, payload, successMessage) {
  await Api.post(route, payload);
  UI.closeModal();
  UI.toast(successMessage, 'good');
  App.refresh();
}

function confirmAction(title, message, onConfirm) {
  UI.showModal(title, `
    <div style="margin-bottom: 24px; color: var(--ink-700);">${UI.escape(message)}</div>
    <div class="modal-actions">
      <button class="btn btn-ghost" data-modal-close>Cancel</button>
      <button class="btn btn-primary js-confirm-btn">Confirm Action</button>
    </div>
  `);
  document.querySelector('.js-confirm-btn')?.addEventListener('click', async () => {
    // Note: Modal is closed inside simpleAction if it's called there, 
    // or we can close it here if onConfirm is a simple function.
    await onConfirm();
  });
}

function renderPagination(pagination, moduleKey, currentParams) {
  if (!pagination || pagination.total <= pagination.limit) return '';
  const totalPages = Math.ceil(pagination.total / pagination.limit);
  const current = pagination.page || 1;
  const paramsJson = JSON.stringify(currentParams).replace(/'/g, "&#39;");
  return `
    <div class="inline-actions" style="justify-content:center;padding:12px;gap:8px;">
      ${current > 1 ? `<button class="btn" data-page="${current - 1}" data-module="${moduleKey}" data-params='${paramsJson}'>← Prev</button>` : ''}
      <span style="padding:0 8px;color:var(--ink-500);">Page ${current} of ${totalPages} (${pagination.total} total)</span>
      ${current < totalPages ? `<button class="btn" data-page="${current + 1}" data-module="${moduleKey}" data-params='${paramsJson}'>Next →</button>` : ''}
    </div>`;
}

function attachPagination(container = document) {
  container.querySelectorAll('[data-page]').forEach(btn => {
    btn.addEventListener('click', () => {
      const page = parseInt(btn.dataset.page);
      const moduleKey = btn.dataset.module;
      const params = JSON.parse(btn.dataset.params || '{}');
      App.navigate(moduleKey, { ...params, page });
    });
  });
}

async function loadSupervisors() {
  try {
    const data = await Api.get('user/list', { role_key: 'GREEN_BELT_SUPERVISOR' });
    const users = normalizeItems(data);
    return users.map(u => ({ value: u.id, label: u.full_name }));
  } catch (e) {
    console.error('Failed to load supervisors', e);
    return null;
  }
}

function openSimpleForm(title, fields, submitLabel, handler, extraHTML = '') {
  UI.showModal(title, UI.form(fields, submitLabel, extraHTML));
  const form = document.querySelector('.js-action-form');
  if (!form) return;
  bindVisibleValidation(form);

  form.onsubmit = async (event) => {
    event.preventDefault();
    try {
      console.log(`[Form:${title}] Handler type:`, typeof handler);
      if (typeof handler !== 'function') {
        throw new Error(`Technical Error: Handler for [${title}] is not a function`);
      }
      await handler(UI.formData(event.currentTarget));
    } catch (error) {
      console.error(`[Form:${title}] Error:`, error);
      UI.toast(error.message, 'bad');
    }
  };
}

function bindVisibleValidation(form) {
  const errorBox = form.querySelector('.js-form-error');
  if (!errorBox) return;

  form.querySelectorAll('[required]').forEach((field) => {
    field.addEventListener('invalid', () => {
      const label = field.closest('.field')?.querySelector('span')?.textContent || field.name || 'This field';
      errorBox.textContent = `${label} is required.`;
    });
    field.addEventListener('input', () => {
      if (field.checkValidity()) errorBox.textContent = '';
    });
    field.addEventListener('change', () => {
      if (field.checkValidity()) errorBox.textContent = '';
    });
  });
}

function dashboardView(route, title, subtitle, actionsHTML) {
  return {
    async render() {
      const data = await Api.get(route);
      const cards = Object.entries(data || {}).map(([key, value]) => ({
        label: humanColumn(key),
        value
      }));
      return UI.page(title, subtitle, UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }))
        + UI.cards(cards)
        + UI.panel('Next Actions', `<div class="inline-actions">${actionsHTML}</div>`);
    },
    async afterRender() {
      attachRefresh();
      document.querySelectorAll('[data-nav]').forEach((button) => {
        button.addEventListener('click', () => App.navigate(button.dataset.nav));
      });
    }
  };
}

Views.register('dashboard.master_ops', {
  async render() {
    const data = await Api.get('dashboard/master');
    const cards = [
      { label: 'Operational Belts', value: data.total_active_belts },
      { label: 'Monitoring Due Today', value: data.monitoring_due_today_count, attr: 'data-nav="monitoring.plan"' },
      { label: 'Open Tasks', value: data.open_task_count, attr: 'data-nav="task.management"' },
      { label: 'Open Issues', value: data.open_issue_count, attr: 'data-nav="green_belt.issue_management"' },
      { label: 'Pending Upload Review', value: data.pending_uploads_for_review, attr: 'data-nav="green_belt.upload_review"' },
      { label: 'Campaigns Ending Soon', value: data.campaign_ending_soon_count },
      { label: 'Free Media Available', value: data.free_media_active_count }
    ];

    const actions = `
      ${UI.button('Green Belts', { icon: 'ph-tree', attr: 'data-nav="green_belt.master"' })}
      ${UI.button('Tasks', { icon: 'ph-list-checks', attr: 'data-nav="task.management"' })}
      ${UI.button('Upload Review', { icon: 'ph-image', attr: 'data-nav="green_belt.upload_review"' })}
      ${UI.button('Reports', { icon: 'ph-file-csv', attr: 'data-nav="reports.monthly"' })}
    `;

    return UI.page('Master Operations Dashboard', 'High-level control across all domains', UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }))
      + UI.cards(cards)
      + UI.panel('Next Actions', `<div class="inline-actions">${actions}</div>`);
  },
  async afterRender() {
    attachRefresh();
    document.querySelectorAll('[data-nav]').forEach((el) => {
      el.addEventListener('click', () => App.navigate(el.dataset.nav));
    });
  }
});

Views.register('dashboard.green_belt', {
  async render({ params = {} }) {
    const summary = await Api.get('dashboard/green-belt');
    
    // Fetch belts for attention table
    const attentionData = await Api.get('belt/list', { 
      maintenance_mode: 'MAINTAINED', 
      hidden: 0,
      zone: params.zone || '',
      ...params
    });
    const attentionBelts = UI.getItems(attentionData);

    const cards = [
      { label: 'Active Cycles', value: summary.active_cycle_count },
      { label: 'Same-Day Watering Pending', value: summary.same_day_watering_pending_count },
      { label: 'Open Belt Issues', value: summary.open_issues, attr: 'data-nav="green_belt.issue_management"' },
      { label: 'Pending Review', value: summary.pending_authority_review_count, attr: 'data-nav="green_belt.upload_review"' }
    ];

    const actions = `
      ${UI.button('Green Belts', { icon: 'ph-tree', attr: 'data-nav="green_belt.master"' })}
      ${UI.button('Watering & Attendance', { icon: 'ph-drop', attr: 'data-nav="green_belt.watering_oversight"' })}
      ${UI.button('Issues', { icon: 'ph-warning-circle', attr: 'data-nav="green_belt.issue_management"' })}
      ${UI.button('Upload Review', { icon: 'ph-image', attr: 'data-nav="green_belt.upload_review"' })}
    `;

    const columns = [
      { key: 'belt_code', label: 'Belt Code' },
      { key: 'common_name', label: 'Name' },
      { key: 'zone', label: 'Zone' },
      { 
        key: 'status', 
        label: 'Attention Needed', 
        render: (row) => {
          const issues = row.open_issue_count > 0 ? `<span class="status-pill status-bad">Issues: ${row.open_issue_count}</span>` : '';
          const noCycle = !row.active_cycle_id ? '<span class="status-pill status-warn">No Active Cycle</span>' : '';
          return `<div style="display: flex; gap: 8px;">${issues} ${noCycle}</div>` || 'OK';
        },
        html: true
      }
    ];

    return UI.page('Green Belt Dashboard', 'Daily belt health and exceptions', UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }))
      + UI.cards(cards)
      + UI.panel('Filters', UI.filters([
          { name: 'zone', label: 'Zone', value: params.zone || '' },
          { name: 'maintenance_mode', label: 'Maintenance', type: 'select', value: params.maintenance_mode || 'MAINTAINED', options: ['MAINTAINED', 'OUTSOURCED'] }
      ], 'Filter Attention List'))
      + UI.panel('Belts Needing Attention', UI.table(columns, attentionBelts, {
          empty: 'No belts require immediate attention in this view',
          rowAttr: (row) => `data-nav="green_belt.detail" data-belt-id="${row.id}"`
      }))
      + UI.panel('Quick Actions', `<div class="inline-actions">${actions}</div>`);
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('dashboard.green_belt', payload));
    document.querySelectorAll('[data-nav]').forEach((el) => {
      el.addEventListener('click', () => {
        const params = el.dataset.beltId ? { belt_id: el.dataset.beltId } : {};
        App.navigate(el.dataset.nav, params);
      });
    });
  }
});

Views.register('dashboard.advertisement', dashboardView('dashboard/advertisement', 'Advertisement Dashboard', 'Campaigns, sites, and media operations', `
  ${UI.button('Site Master', { icon: 'ph-map-pin', attr: `data-nav="advertisement.site_master"` })}
  ${UI.button('Campaigns', { icon: 'ph-megaphone', attr: `data-nav="advertisement.campaign_management"` })}
  ${UI.button('Free Media', { icon: 'ph-gift', attr: `data-nav="media.free_media_inventory"` })}
`));

Views.register('dashboard.monitoring', {
  async render() {
    const data = await Api.get('dashboard/monitoring');
    const cards = [
      { label: 'Due Today', value: data.due_today_count, attr: `data-nav="monitoring.plan" data-params='{"month":"${UI.currentMonth()}"}'` },
      { label: 'Completed Today', value: data.completed_today_count },
      { label: 'Overdue', value: data.overdue_due_date_count, attr: 'data-nav="monitoring.history"' },
      { label: 'Discovery Activity', value: data.discovery_mode_count }
    ];

    const actions = `
      ${UI.button('Monitoring Plan', { icon: 'ph-calendar-check', attr: 'data-nav="monitoring.plan"' })}
      ${UI.button('Monitoring History', { icon: 'ph-clock-counter-clockwise', attr: 'data-nav="monitoring.history"' })}
    `;

    return UI.page('Monitoring Dashboard', 'Due monitoring, coverage, and discovery', UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }))
      + UI.cards(cards)
      + UI.panel('Quick Actions', `<div class="inline-actions">${actions}</div>`);
  },
  async afterRender() {
    attachRefresh();
    document.querySelectorAll('[data-nav]').forEach((el) => {
      el.addEventListener('click', () => {
        const params = el.dataset.params ? JSON.parse(el.dataset.params) : {};
        App.navigate(el.dataset.nav, params);
      });
    });
  }
});

Views.register('dashboard.management', dashboardView('dashboard/management', 'Management Dashboard', 'Read-only business overview', `
  ${UI.button('Reports', { icon: 'ph-file-csv', attr: `data-nav="reports.monthly"` })}
  ${UI.button('Authority View', { icon: 'ph-eye', attr: `data-nav="green_belt.authority_view"` })}
`));

Views.register('reports.monthly', {
  async render({ params = {} }) {
    const month = params.month || UI.currentMonth();
    
    // We fetch data sequentially or concurrently. Doing concurrently for performance.
    const [beltHealth, workerActivity, supervisorActivity, adOps] = await Promise.all([
      Api.get('report/belt-health', { month }),
      Api.get('report/worker-activity', { month }),
      Api.get('report/supervisor-activity', { month }),
      Api.get('report/advertisement-operations', { month })
    ]);

    const filterUI = UI.panel('Reporting Period', UI.filters([
      { name: 'month', label: 'Month', type: 'month', value: month, required: true }
    ], 'Load Reports'));

    const renderReportPanel = (title, route, data) => {
      const items = normalizeItems(data);
      const csvUrl = Api.url(route, { month, format: 'csv' });
      const actions = `<a href="${csvUrl}" class="btn btn-ghost" target="_blank" download><i class="ph ph-download-simple"></i><span>Download CSV</span></a>`;
      return UI.panel(title, UI.table(inferColumns(items), items, { empty: 'No data for this period.' }), actions);
    };

    return UI.page('Monthly Analytics', 'Aggregated performance and activity reports for ' + month)
      + filterUI
      + renderReportPanel('Belt Health Summary', 'report/belt-health', beltHealth)
      + renderReportPanel('Worker Activity', 'report/worker-activity', workerActivity)
      + renderReportPanel('Supervisor Activity', 'report/supervisor-activity', supervisorActivity)
      + renderReportPanel('Advertisement Operations', 'report/advertisement-operations', adOps);
  },
  async afterRender() {
    wireFilters((payload) => App.navigate('reports.monthly', payload));
  }
});

Views.register('green_belt.master', {
  async render({ params = {} }) {
    const data = await Api.get('belt/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'belt_code', label: 'Belt Code' },
      { key: 'common_name', label: 'Common Name' },
      { key: 'authority_name', label: 'Authority Name' },
      { key: 'zone', label: 'Zone' },
      { key: 'permission_status', label: 'Permission', html: true, render: (row) => UI.status(row.permission_status) },
      { key: 'maintenance_mode', label: 'Mode', html: true, render: (row) => UI.status(row.maintenance_mode) },
      { key: 'is_hidden', label: 'Hidden', html: true, render: (row) => UI.status(row.is_hidden ? 'HIDDEN' : 'VISIBLE') }
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'zone', label: 'Zone', value: params.zone || '' },
      { name: 'permission_status', label: 'Permission', type: 'select', value: params.permission_status || '', options: [
        { value: '', label: 'All' }, 'APPLIED', 'AGREEMENT_SIGNED', 'EXPIRED'
      ]},
      { name: 'maintenance_mode', label: 'Mode', type: 'select', value: params.maintenance_mode || '', options: [
        { value: '', label: 'All' }, 'MAINTAINED', 'OUTSOURCED'
      ]},
      { name: 'hidden', label: 'Hidden', type: 'select', value: params.hidden || '', options: [
        { value: '', label: 'All' }, { value: '0', label: 'Visible' }, { value: '1', label: 'Hidden' }
      ]},
      { name: 'supervisor_user_id', label: 'Supervisor ID', type: 'number', value: params.supervisor_user_id || '' }
    ], 'Apply Filter'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
                    UI.button('New Belt', { icon: 'ph-plus', kind: 'btn-primary', attr: 'data-create-belt' });

    return UI.page('Green Belts', 'Manage belts, permissions, and oversight', actions)
      + filterUI
      + UI.panel('Records', UI.table(columns, rows, { empty: 'No belts found', rowAttr: (row) => `data-open-belt="${row.id}"` }) + renderPagination(data.pagination, 'green_belt.master', params));
  },
  async afterRender() {
    attachRefresh();
    attachPagination();
    wireFilters((payload) => App.navigate('green_belt.master', payload));
    document.querySelectorAll('[data-open-belt]').forEach((row) => {
      row.addEventListener('click', () => App.navigate('green_belt.detail', { belt_id: row.dataset.openBelt }));
    });
    document.querySelector('[data-create-belt]')?.addEventListener('click', () => {
      openSimpleForm('Create Green Belt', [
        { name: 'belt_code', label: 'Belt Code', required: true },
        { name: 'common_name', label: 'Common Name', required: true },
        { name: 'authority_name', label: 'Authority Name', required: true },
        { name: 'zone', label: 'Zone' },
        { name: 'location_text', label: 'Location' },
        { name: 'latitude', label: 'Latitude', type: 'number' },
        { name: 'longitude', label: 'Longitude', type: 'number' },
        { name: 'permission_start_date', label: 'Permission Start', type: 'date' },
        { name: 'permission_end_date', label: 'Permission End', type: 'date' },
        { name: 'permission_status', label: 'Permission Status', type: 'select', value: 'AGREEMENT_SIGNED', options: ['APPLIED', 'AGREEMENT_SIGNED', 'EXPIRED'] },
        { name: 'maintenance_mode', label: 'Maintenance Mode', type: 'select', value: 'MAINTAINED', options: ['MAINTAINED', 'OUTSOURCED'] },
        { name: 'watering_frequency', label: 'Watering Frequency', type: 'select', value: 'DAILY', options: ['DAILY', 'ALTERNATE_DAY', 'WEEKLY'] },
        { name: 'is_hidden', label: 'Hidden', type: 'select', value: '0', options: [{value: '0', label: 'No'}, {value: '1', label: 'Yes'}] }
      ], 'Create Belt', (payload) => simpleAction('belt/create', payload, 'Green belt created'));
    });
  }
});

Views.register('green_belt.detail', {
  async render({ params }) {
    if (!params.belt_id) return UI.page('Belt Detail', 'Open a belt from Green Belts') + UI.empty('No belt selected');
    const data = await Api.get('belt/get', { belt_id: params.belt_id });
    const belt = data.belt || data;
    
    const isOps = Auth.getUser()?.role_key === 'OPS_MANAGER';
    const actions = UI.button('Back', { icon: 'ph-arrow-left', attr: 'data-back-belts' }) + 
                    (isOps ? UI.button('Edit Belt', { icon: 'ph-pencil', attr: 'data-edit-belt' }) : '');

    return UI.page(belt.common_name || 'Belt Detail', belt.belt_code || `Belt #${params.belt_id}`, actions)
      + UI.cards([
        { label: 'Permission', value: UI.titleize(belt.permission_status || '-') },
        { label: 'Maintenance', value: UI.titleize(belt.maintenance_mode || '-') },
        { label: 'Watering', value: UI.titleize(belt.watering_frequency || '-') },
        { label: 'Status', value: belt.is_hidden ? 'HIDDEN' : 'ACTIVE' }
      ])
      + UI.panel('Assignments', `
        <div class="inline-actions" style="margin-bottom: 12px;">
          ${isOps ? UI.button('Assign Supervisor', { attr: 'data-assign="supervisor"' }) : ''}
          ${isOps ? UI.button('Assign Authority', { attr: 'data-assign="authority"' }) : ''}
          ${isOps ? UI.button('Assign Outsourced', { attr: 'data-assign="outsourced"' }) : ''}
        </div>
        <h4>Supervisors</h4>
        ${UI.table([
          { key: 'full_name', label: 'Supervisor' },
          { key: 'start_date', label: 'Start Date' },
          { key: 'end_date', label: 'End Date', render: (v) => v || 'Active' },
          { key: 'actions', label: '', html: true, render: (row) => row.end_date ? '<span class="status-pill status-muted">Ended</span>' : `<button class="btn btn-sm" data-end-assign="${row.id}" data-assign-type="supervisor">End</button>` }
        ], data.supervisor_assignments || [], { empty: 'No supervisor assignments' })}
        
        <h4>Authorities</h4>
        ${UI.table([
          { key: 'full_name', label: 'Authority' },
          { key: 'start_date', label: 'Start Date' },
          { key: 'end_date', label: 'End Date', render: (v) => v || 'Active' },
          { key: 'actions', label: '', html: true, render: (row) => row.end_date ? '<span class="status-pill status-muted">Ended</span>' : `<button class="btn btn-sm" data-end-assign="${row.id}" data-assign-type="authority">End</button>` }
        ], data.authority_assignments || [], { empty: 'No authority assignments' })}
        
        <h4>Outsourced</h4>
        ${UI.table([
          { key: 'full_name', label: 'Outsourced' },
          { key: 'start_date', label: 'Start Date' },
          { key: 'end_date', label: 'End Date', render: (v) => v || 'Active' },
          { key: 'actions', label: '', html: true, render: (row) => row.end_date ? '<span class="status-pill status-muted">Ended</span>' : `<button class="btn btn-sm" data-end-assign="${row.id}" data-assign-type="outsourced">End</button>` }
        ], data.outsourced_assignments || [], { empty: 'No outsourced assignments' })}
      `)
      + UI.panel('Maintenance Cycles', `
        <div class="inline-actions" style="margin-bottom: 12px;">
          ${belt.maintenance_mode === 'MAINTAINED' ? UI.button('Start Cycle', { icon: 'ph-play', attr: 'data-start-cycle' }) : ''}
          ${belt.maintenance_mode === 'MAINTAINED' && data.recent_cycle_summary?.active_cycle ? UI.button('Close Cycle', { icon: 'ph-stop', attr: 'data-close-cycle' }) : (belt.maintenance_mode === 'MAINTAINED' ? '<span class="status-pill status-muted">No Active Cycle</span>' : '')}
        </div>
        ${UI.table(inferColumns(data.cycle_history || []), data.cycle_history || [], { empty: 'No cycle history' })}
      `)
      + UI.panel('Watering Summary', UI.table(inferColumns([data.recent_watering_summary || {}]), [data.recent_watering_summary || {}], { empty: 'No watering summary' }))
      + UI.panel('Recent Uploads', UI.table(inferColumns(data.uploads || []), data.uploads || [], { empty: 'No uploads' }))
      + UI.panel('Issues', `
        <div class="inline-actions" style="margin-bottom: 12px;">
          ${UI.button('Log Issue', { icon: 'ph-warning', attr: 'data-log-issue' })}
        </div>
        ${UI.table(inferColumns(data.issues || []), data.issues || [], { empty: 'No issues' })}
      `);
  },
  async afterRender({ params }) {
    document.querySelector('[data-back-belts]')?.addEventListener('click', () => App.navigate('green_belt.master'));
    document.querySelector('[data-edit-belt]')?.addEventListener('click', async () => {
      const data = await Api.get('belt/get', { belt_id: params.belt_id });
      const b = data.belt;
      openSimpleForm('Edit Green Belt', [
        { name: 'belt_id', type: 'hidden', value: b.id },
        { name: 'common_name', label: 'Common Name', required: true, value: b.common_name },
        { name: 'authority_name', label: 'Authority Name', required: true, value: b.authority_name },
        { name: 'zone', label: 'Zone', value: b.zone },
        { name: 'location_text', label: 'Location', value: b.location_text },
        { name: 'latitude', label: 'Latitude', type: 'number', value: b.latitude },
        { name: 'longitude', label: 'Longitude', type: 'number', value: b.longitude },
        { name: 'permission_start_date', label: 'Permission Start', type: 'date', value: b.permission_start_date },
        { name: 'permission_end_date', label: 'Permission End', type: 'date', value: b.permission_end_date },
        { name: 'permission_status', label: 'Permission Status', type: 'select', value: b.permission_status, options: ['APPLIED', 'AGREEMENT_SIGNED', 'EXPIRED'] },
        { name: 'maintenance_mode', label: 'Maintenance Mode', type: 'select', value: b.maintenance_mode, options: ['MAINTAINED', 'OUTSOURCED'] },
        { name: 'watering_frequency', label: 'Watering Frequency', type: 'select', value: b.watering_frequency, options: ['DAILY', 'ALTERNATE_DAY', 'WEEKLY'] },
        { name: 'is_hidden', label: 'Hidden', type: 'select', value: b.is_hidden, options: [{value: '0', label: 'No'}, {value: '1', label: 'Yes'}] }
      ], 'Save Changes', (payload) => simpleAction('belt/update', payload, 'Green belt updated'));
    });
    
    document.querySelector('[data-start-cycle]')?.addEventListener('click', () => {
      openSimpleForm('Start Maintenance Cycle', [
        { name: 'belt_id', type: 'hidden', value: params.belt_id },
        { name: 'start_date', label: 'Start Date', type: 'date', required: true, value: UI.currentDate() }
      ], 'Start Cycle', (payload) => simpleAction('cycle/start', payload, 'Cycle started'));
    });

    document.querySelector('[data-close-cycle]')?.addEventListener('click', async () => {
      const data = await Api.get('belt/get', { belt_id: params.belt_id });
      const activeCycle = data.recent_cycle_summary?.active_cycle;
      if (!activeCycle) return UI.toast('No active cycle found', 'bad');

      openSimpleForm('Close Maintenance Cycle', [
        { name: 'cycle_id', type: 'hidden', value: activeCycle.id },
        { name: 'end_date', label: 'End Date', type: 'date', required: true, value: UI.currentDate() },
        { name: 'close_reason', label: 'Reason', type: 'textarea' }
      ], 'Close Cycle', (payload) => simpleAction('cycle/close', payload, 'Cycle closed'));
    });

    document.querySelectorAll('[data-end-assign]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.endAssign;
        const type = btn.dataset.assignType;
        if (confirm(`End this ${type} assignment?`)) {
          await simpleAction(`${type}assignment/close`, { assignment_id: id, end_date: UI.currentDate() }, 'Assignment ended');
        }
      });
    });

    document.querySelectorAll('[data-assign]').forEach(btn => {
      btn.addEventListener('click', () => {
        const type = btn.dataset.assign;
        openSimpleForm(`Assign ${UI.titleize(type)}`, [
          { name: 'belt_id', type: 'hidden', value: params.belt_id },
          { name: `${type}_user_id`, label: 'User ID', type: 'number', required: true },
          { name: 'start_date', label: 'Start Date', type: 'date', required: true, value: UI.currentDate() }
        ], 'Assign', (payload) => simpleAction(`${type}assignment/create`, payload, 'Assigned'));
      });
    });

    document.querySelector('[data-log-issue]')?.addEventListener('click', () => {
      openSimpleForm('Log Issue', [
        { name: 'belt_id', type: 'hidden', value: params.belt_id },
        { name: 'site_category', type: 'hidden', value: 'GREEN_BELT' },
        { name: 'title', label: 'Title', required: true },
        { name: 'issue_type', label: 'Type', type: 'select', value: 'DAMAGE', options: ['DAMAGE', 'THEFT', 'WIRING', 'AUTHORITY_OBJECTION', 'OTHER'] },
        { name: 'priority', label: 'Priority', type: 'select', value: 'MEDIUM', options: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'] },
        { name: 'description', label: 'Description', type: 'textarea', required: true }
      ], 'Submit Issue', (payload) => simpleAction('issue/create', payload, 'Issue logged'));
    });
  }
});

Views.register('green_belt.maintenance_cycles', {
  async render({ params = {} }) {
    const data = await Api.get('cycle/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'belt_code', label: 'Belt Code' },
      { key: 'belt_name', label: 'Belt Name' },
      { key: 'start_date', label: 'Start Date' },
      { key: 'end_date', label: 'End Date', render: (v) => v || 'Active' },
      { key: 'started_by_user_name', label: 'Started By' },
      { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.end_date ? 'CLOSED' : 'ACTIVE') },
      {
        key: 'actions', label: '', html: true,
        render: (row) => row.end_date
          ? '<span class="status-pill status-muted">Closed</span>'
          : `<button class="btn btn-sm btn-bad" data-close-cycle-id="${row.id}" data-close-belt="${row.belt_id}">Close</button>`
      }
    ];

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
                    UI.button('Start Cycle', { icon: 'ph-play', kind: 'btn-primary', attr: 'data-start-cycle' });

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'status', label: 'Status', type: 'select', value: params.status || '', options: [{value: '', label: 'All'}, {value: 'ACTIVE', label: 'Active'}, {value: 'CLOSED', label: 'Closed'}] },
      { name: 'maintenance_mode', label: 'Mode', type: 'select', value: params.maintenance_mode || '', options: ['', 'MAINTAINED', 'OUTSOURCED'] }
    ], 'Load'));

    return UI.page('Maintenance Cycles', 'Global cycle management', actions)
      + filterUI
      + UI.panel('Cycles', UI.table(columns, rows, {
          empty: 'No maintenance cycles found',
          rowAttr: (row) => `data-belt-id="${row.belt_id}" data-cycle-id="${row.id}"`
      }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('green_belt.maintenance_cycles', payload));

    document.querySelector('[data-start-cycle]')?.addEventListener('click', () => {
      openSimpleForm('Start Cycle', [
        { name: 'belt_id', label: 'Belt ID', type: 'number', required: true },
        { name: 'start_date', label: 'Start Date', type: 'date', required: true, value: UI.currentDate() }
      ], 'Start', (payload) => simpleAction('cycle/start', payload, 'Cycle started'));
    });

    // Inline close buttons on active cycle rows — cycle_id is auto-populated from the row
    document.querySelectorAll('[data-close-cycle-id]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const cycleId = btn.dataset.closeCycleId;
        openSimpleForm('Close Maintenance Cycle', [
          { name: 'cycle_id', type: 'hidden', value: cycleId },
          { name: 'end_date', label: 'End Date', type: 'date', required: true, value: UI.currentDate() },
          { name: 'close_reason', label: 'Reason', type: 'textarea' }
        ], 'Close Cycle', (payload) => simpleAction('cycle/close', payload, 'Cycle closed'));
      });
    });

    document.querySelectorAll('[data-belt-id]').forEach(row => {
      row.addEventListener('click', (e) => {
        if (e.target.closest('button')) return;
        App.navigate('green_belt.detail', { belt_id: row.dataset.beltId });
      });
    });
  }
});

Views.register('green_belt.supervisor_attendance', {
  async render({ params = {} }) {
    const data = await Api.get('attendance/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'supervisor_name', label: 'Supervisor' },
      { key: 'attendance_status', label: 'Status', html: true, render: (row) => UI.status(row.attendance_status) },
      { key: 'reason_text', label: 'Reason' },
      { key: 'marked_by_name', label: 'Marked By' },
      { key: 'marked_at', label: 'Marked At' }
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'date', label: 'Date', type: 'date', value: params.date || UI.currentDate() },
      { name: 'supervisor_user_id', label: 'Supervisor', type: 'select', options: [{ value: '', label: 'Loading...' }], value: params.supervisor_user_id || '' }
    ], 'Load'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
                    UI.button('Mark Attendance', { icon: 'ph-user-check', kind: 'btn-primary', attr: 'data-mark-attendance' });

    return UI.page('Supervisor Attendance', 'Same-day supervisor attendance grid', actions)
      + filterUI
      + UI.panel('Records', UI.table(columns, rows, { empty: 'No attendance records found for this date' }));
  },
  async afterRender({ params = {} }) {
    attachRefresh();
    
    // Load supervisors dropdown
    const sups = await loadSupervisors();
    if (sups) {
      const select = document.querySelector('.js-filter-form [name="supervisor_user_id"]');
      if (select) {
        select.innerHTML = '<option value="">All Supervisors</option>' + sups.map(s => `<option value="${s.value}" ${String(s.value) === String(params.supervisor_user_id) ? 'selected' : ''}>${UI.escape(s.label)}</option>`).join('');
      }
    }

    wireFilters((payload) => App.navigate('green_belt.supervisor_attendance', payload));
    document.querySelector('[data-mark-attendance]')?.addEventListener('click', () => {
      openSimpleForm('Mark Attendance', [
        { name: 'supervisor_user_id', label: 'Supervisor ID', type: 'number', required: true },
        { name: 'attendance_date', label: 'Date', type: 'date', required: true, value: UI.currentDate() },
        { name: 'status', label: 'Status', type: 'select', value: 'PRESENT', options: ['PRESENT', 'ABSENT', 'LEAVE'] },
        { name: 'reason_text', label: 'Reason (Ops Override)', type: 'textarea' }
      ], 'Save', (payload) => simpleAction('attendance/mark', payload, 'Attendance marked'));
    });
  }
});

Views.register('green_belt.labour_entries', {
  async render({ params = {} }) {
    const data = await Api.get('labour/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'belt_code', label: 'Belt Code' },
      { key: 'belt_name', label: 'Belt Name' },
      { key: 'entry_date', label: 'Date' },
      { key: 'labour_count', label: 'Labour' },
      { key: 'gardener_count', label: 'Gardeners' },
      { key: 'night_guard_count', label: 'Night Guards' },
      { key: 'reason_text', label: 'Reason' },
      { key: 'marked_by_name', label: 'Marked By' },
      { key: 'marked_at', label: 'Marked At' }
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'date', label: 'Date', type: 'date', value: params.date || UI.currentDate() },
      { name: 'belt_id', label: 'Belt ID', type: 'number', value: params.belt_id || '' }
    ], 'Load'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
                    UI.button('Enter Labour Counts', { icon: 'ph-users', kind: 'btn-primary', attr: 'data-mark-labour' });

    return UI.page('Labour Entries', 'Daily labour entry panel', actions)
      + filterUI
      + UI.panel('Records', UI.table(columns, rows, { empty: 'No labour records found for this date' }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('green_belt.labour_entries', payload));
    document.querySelector('[data-mark-labour]')?.addEventListener('click', () => {
      openSimpleForm('Enter Labour Counts', [
        { name: 'belt_id', label: 'Belt ID', type: 'number', required: true },
        { name: 'entry_date', label: 'Date', type: 'date', required: true, value: UI.currentDate() },
        { name: 'labour_count', label: 'Labour Count', type: 'number', value: '0' },
        { name: 'gardener_count', label: 'Gardener Count', type: 'number', value: '0' },
        { name: 'night_guard_count', label: 'Night Guard Count', type: 'number', value: '0' },
        { name: 'reason_text', label: 'Reason (Ops Override)', type: 'textarea' }
      ], 'Save', (payload) => simpleAction('labour/mark', payload, 'Labour marked'));
    });
  }
});

Views.register('advertisement.site_master', {
  async render({ params = {} }) {
    const data = await Api.get('site/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'site_code', label: 'Site Code' },
      { key: 'location_text', label: 'Location' },
      { key: 'site_category', label: 'Category' },
      { key: 'lighting_type', label: 'Lighting' },
      { key: 'route_or_group', label: 'Route/Group' },
      { key: 'green_belt_reference', label: 'Belt Reference' },
      { key: 'is_active', label: 'Active', html: true, render: (row) => UI.status(row.is_active ? 'ACTIVE' : 'INACTIVE') }
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'site_category', label: 'Category', type: 'select', value: params.site_category, options: ['', 'GREEN_BELT', 'CITY', 'HIGHWAY'] },
      { name: 'lighting_type', label: 'Lighting', type: 'select', value: params.lighting_type, options: ['', 'NON_LIT', 'LIT'] },
      { name: 'is_active', label: 'Active Status', type: 'select', value: params.is_active, options: [{ value: '', label: 'All' }, { value: '1', label: 'Active' }, { value: '0', label: 'Inactive' }] }
    ], 'Load'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
                    UI.button('New Site', { icon: 'ph-plus', kind: 'btn-primary', attr: 'data-create-site' });

    return UI.page('Site Master', 'Manage advertising sites and assets', actions)
      + filterUI
      + UI.panel('Records', UI.table(columns, rows, { 
          empty: 'No sites found matching criteria',
          rowAttr: (row) => `data-edit-site="${row.site_id}" data-site='${JSON.stringify(row).replace(/'/g, "&#39;")}'`
      }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('advertisement.site_master', payload));
    
    document.querySelector('[data-create-site]')?.addEventListener('click', () => {
      openSimpleForm('Create Site', [
        { name: 'site_code', label: 'Site Code', required: true },
        { name: 'location_text', label: 'Location' },
        { name: 'site_category', label: 'Category', type: 'select', value: 'CITY', options: ['GREEN_BELT', 'CITY', 'HIGHWAY'] },
        { name: 'green_belt_id', label: 'Linked Belt ID (if Green Belt)', type: 'number' },
        { name: 'route_or_group', label: 'Route/Group' },
        { name: 'ownership_name', label: 'Ownership' },
        { name: 'board_type', label: 'Board Type' },
        { name: 'lighting_type', label: 'Lighting', type: 'select', value: 'NON_LIT', options: ['NON_LIT', 'LIT'] },
        { name: 'latitude', label: 'Latitude', type: 'number' },
        { name: 'longitude', label: 'Longitude', type: 'number' },
        { name: 'is_active', label: 'Is Active', type: 'select', value: '1', options: [{ value: '1', label: 'Yes' }, { value: '0', label: 'No' }] }
      ], 'Create', (payload) => {
        payload.is_active = payload.is_active === '1' ? 1 : 0;
        return simpleAction('site/create', payload, 'Site created');
      });
    });

    document.querySelectorAll('[data-edit-site]').forEach(row => {
      row.addEventListener('click', () => {
        const site = JSON.parse(row.dataset.site);
        openSimpleForm('Edit Site', [
          { name: 'site_id', type: 'hidden', value: site.site_id },
          { name: 'site_code', label: 'Site Code', value: site.site_code, required: true },
          { name: 'location_text', label: 'Location', value: site.location_text },
          { name: 'site_category', label: 'Category', type: 'select', value: site.site_category, options: ['GREEN_BELT', 'CITY', 'HIGHWAY'] },
          { name: 'green_belt_id', label: 'Linked Belt ID', type: 'number', value: site.green_belt_id || '' },
          { name: 'route_or_group', label: 'Route/Group', value: site.route_or_group },
          { name: 'ownership_name', label: 'Ownership', value: site.ownership_name },
          { name: 'board_type', label: 'Board Type', value: site.board_type },
          { name: 'lighting_type', label: 'Lighting', type: 'select', value: site.lighting_type, options: ['NON_LIT', 'LIT'] },
          { name: 'latitude', label: 'Latitude', type: 'number', value: site.latitude || '' },
          { name: 'longitude', label: 'Longitude', type: 'number', value: site.longitude || '' },
          { name: 'is_active', label: 'Is Active', type: 'select', value: site.is_active ? '1' : '0', options: [{ value: '1', label: 'Yes' }, { value: '0', label: 'No' }] }
        ], 'Update', (payload) => {
          payload.is_active = payload.is_active === '1' ? 1 : 0;
          return simpleAction('site/update', payload, 'Site updated');
        });
      });
    });
  }
});

Views.register('advertisement.campaign_management', {
  async render({ params = {} }) {
    const data = await Api.get('campaign/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'campaign_code', label: 'Campaign Code' },
      { key: 'client_name', label: 'Client' },
      { key: 'campaign_name', label: 'Campaign Name' },
      { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.status) },
      { key: 'start_date', label: 'Start Date' },
      { key: 'expected_end_date', label: 'Exp. End' },
      { key: 'active_sites_count', label: 'Linked Sites' }
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'status', label: 'Status', type: 'select', value: params.status, options: ['', 'UPCOMING', 'ACTIVE', 'ENDED'] },
      { name: 'client_name', label: 'Client', value: params.client_name },
      { name: 'site_category', label: 'Site Category', type: 'select', value: params.site_category, options: ['', 'GREEN_BELT', 'CITY', 'HIGHWAY'] }
    ], 'Load'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
                    UI.button('New Campaign', { icon: 'ph-plus', kind: 'btn-primary', attr: 'data-create-campaign' });

    return UI.page('Campaign Management', 'Manage ad campaigns and site allocations', actions)
      + filterUI
      + UI.panel('Records', UI.table(columns, rows, { 
          empty: 'No campaigns found',
          rowAttr: (row) => `data-edit-campaign="${row.campaign_id}" data-campaign='${JSON.stringify(row).replace(/'/g, "&#39;")}'`
      }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('advertisement.campaign_management', payload));
    
    document.querySelector('[data-create-campaign]')?.addEventListener('click', () => {
      openSimpleForm('Create Campaign', [
        { name: 'campaign_code', label: 'Campaign Code', required: true },
        { name: 'client_name', label: 'Client Name', required: true },
        { name: 'campaign_name', label: 'Campaign Name', required: true },
        { name: 'start_date', label: 'Start Date', type: 'date', required: true, value: UI.currentDate() },
        { name: 'expected_end_date', label: 'Expected End Date', type: 'date', required: true },
        { name: 'site_ids_text', label: 'Linked Site IDs (comma separated)', type: 'textarea' }
      ], 'Create', (payload) => {
        if (payload.site_ids_text) {
          payload.site_ids = payload.site_ids_text.split(',').map(s => s.trim()).filter(Boolean);
        }
        delete payload.site_ids_text;
        return simpleAction('campaign/create', payload, 'Campaign created');
      });
    });

    document.querySelectorAll('[data-edit-campaign]').forEach(row => {
      row.addEventListener('click', () => {
        const campaign = JSON.parse(row.dataset.campaign);
        
        let extraHTML = '';
        if (campaign.status === 'ACTIVE' || campaign.status === 'UPCOMING') {
          extraHTML = `
            <div class="field full">
              <button type="button" class="btn btn-danger" data-end-campaign="${campaign.campaign_id}">End Campaign</button>
            </div>
          `;
        } else if (campaign.status === 'ENDED') {
          extraHTML = `
            <div class="field full">
              <button type="button" class="btn btn-primary" data-free-media="${campaign.campaign_id}">Confirm Free Media</button>
            </div>
          `;
        }

        openSimpleForm('Edit Campaign', [
          { name: 'campaign_id', type: 'hidden', value: campaign.campaign_id },
          { name: 'campaign_code', label: 'Campaign Code', value: campaign.campaign_code, required: true },
          { name: 'client_name', label: 'Client Name', value: campaign.client_name, required: true },
          { name: 'campaign_name', label: 'Campaign Name', value: campaign.campaign_name, required: true },
          { name: 'expected_end_date', label: 'Expected End Date', type: 'date', value: campaign.expected_end_date ? campaign.expected_end_date.split(' ')[0] : '', required: true },
          { name: 'site_ids_text', label: 'Replace Linked Site IDs (comma separated, empty to keep current)', type: 'textarea' }
        ], 'Update', (payload) => {
          if (payload.site_ids_text) {
            payload.site_ids = payload.site_ids_text.split(',').map(s => s.trim()).filter(Boolean);
          }
          delete payload.site_ids_text;
          return simpleAction('campaign/update', payload, 'Campaign updated');
        }, extraHTML);

        // Bind the extra buttons inside the modal
        const modalRoot = document.getElementById('modal-root');
        
        const btnEnd = modalRoot.querySelector('[data-end-campaign]');
        if (btnEnd) {
          btnEnd.addEventListener('click', (e) => {
            e.preventDefault();
            UI.closeModal();
            openSimpleForm('End Campaign', [
              { name: 'campaign_id', type: 'hidden', value: campaign.campaign_id },
              { name: 'actual_end_date', label: 'Actual End Date', type: 'date', required: true, value: UI.currentDate() }
            ], 'End Now', (payload) => simpleAction('campaign/end', payload, 'Campaign ended'));
          });
        }

        const btnFreeMedia = modalRoot.querySelector('[data-free-media]');
        if (btnFreeMedia) {
          btnFreeMedia.addEventListener('click', (e) => {
            e.preventDefault();
            UI.closeModal();
            openSimpleForm('Confirm Free Media', [
              { name: 'campaign_id', type: 'hidden', value: campaign.campaign_id },
              { name: 'site_id', label: 'Site ID (to confirm)', type: 'number', required: true },
              { name: 'expiry_date', label: 'Free Media Expiry Date', type: 'date', required: true, value: UI.currentDate() }
            ], 'Confirm', (payload) => simpleAction('campaign/confirm-free-media', payload, 'Free media confirmed'));
          });
        }
      });
    });
  }
});

Views.register('media.free_media_inventory', {
  async render({ params = {} }) {
    const data = await Api.get('freemedia/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'site_code', label: 'Site Code' },
      { key: 'location_text', label: 'Location' },
      { key: 'source_type', label: 'Source' },
      { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.status) },
      { key: 'discovered_date', label: 'Discovered' },
      { key: 'confirmed_date', label: 'Confirmed' },
      { key: 'expiry_date', label: 'Expiry' },
      { key: 'actions', label: 'Actions', html: true, render: (row) => `<button class="btn btn-sm btn-ghost" data-raise-request="${row.site_id}">Raise Request</button>` }
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'status', label: 'Status', type: 'select', value: params.status, options: ['', 'DISCOVERED', 'CONFIRMED_ACTIVE', 'EXPIRED', 'CONSUMED'] },
      { name: 'site_category', label: 'Category', type: 'select', value: params.site_category, options: ['', 'GREEN_BELT', 'CITY', 'HIGHWAY'] },
      { name: 'route_or_group', label: 'Route/Group', value: params.route_or_group }
    ], 'Apply'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' });

    return UI.page('Free Media Inventory', 'Manage available advertising inventory', actions)
      + filterUI
      + UI.panel('Records', UI.table(columns, rows, {
        empty: 'No free media found matching criteria',
        rowAttr: (row) => `data-record='${JSON.stringify(row).replace(/'/g, "&#39;")}'`
      }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('media.free_media_inventory', payload));

    document.querySelectorAll('[data-raise-request]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        App.navigate('task.request_intake', { site_id: btn.dataset.raiseRequest });
      });
    });

    document.querySelectorAll('[data-record]').forEach(row => {
      row.addEventListener('click', () => {
        const record = JSON.parse(row.dataset.record);
        
        let extraHTML = '';
        if (record.status === 'DISCOVERED') {
          extraHTML = `
            <div class="field full">
              <button type="button" class="btn btn-primary" data-confirm-record="${record.record_id}">Confirm Active</button>
            </div>
          `;
        } else if (record.status === 'CONFIRMED_ACTIVE') {
          extraHTML = `
            <div class="modal-actions" style="margin-top: 1rem; border-top: 1px solid var(--line); padding-top: 1rem;">
              <button type="button" class="btn btn-danger" data-expire-record="${record.record_id}">Mark Expired</button>
              <button type="button" class="btn btn-ghost" data-consume-record="${record.record_id}">Mark Consumed</button>
            </div>
          `;
        }

        UI.showModal('Free Media Details', `
          <div class="stack-form">
            <div class="form-grid">
              <div class="field"><span>Site Code</span><input type="text" value="${record.site_code}" readonly></div>
              <div class="field"><span>Location</span><input type="text" value="${record.location_text}" readonly></div>
              <div class="field"><span>Source</span><input type="text" value="${record.source_type}" readonly></div>
              <div class="field"><span>Status</span><input type="text" value="${record.status}" readonly></div>
            </div>
            ${extraHTML}
            <div class="modal-actions">
              <button type="button" class="btn btn-ghost" data-modal-close>Close</button>
              <button type="button" class="btn btn-primary" data-nav-site="${record.site_id}">View Site Master</button>
            </div>
          </div>
        `);

        // Bind buttons
        const modalRoot = document.getElementById('modal-root');
        
        modalRoot.querySelector('[data-nav-site]')?.addEventListener('click', () => {
          UI.closeModal();
          App.navigate('advertisement.site_master', { site_code: record.site_code });
        });

        modalRoot.querySelector('[data-confirm-record]')?.addEventListener('click', () => {
          UI.closeModal();
          openSimpleForm('Confirm Free Media', [
            { name: 'record_id', type: 'hidden', value: record.record_id },
            { name: 'confirmed_date', label: 'Confirmed Date', type: 'date', required: true, value: UI.currentDate() },
            { name: 'expiry_date', label: 'Expiry Date', type: 'date' }
          ], 'Confirm Now', (payload) => simpleAction('freemedia/confirm', payload, 'Record confirmed active'));
        });

        modalRoot.querySelector('[data-expire-record]')?.addEventListener('click', async () => {
          if (confirm('Are you sure you want to mark this record as EXPIRED?')) {
            await simpleAction('freemedia/expire', { record_id: record.record_id }, 'Record expired');
            UI.closeModal();
          }
        });

        modalRoot.querySelector('[data-consume-record]')?.addEventListener('click', async () => {
          if (confirm('Are you sure you want to mark this record as CONSUMED?')) {
            await simpleAction('freemedia/consume', { record_id: record.record_id }, 'Record consumed');
            UI.closeModal();
          }
        });
      });
    });
  }
});


const UPLOAD_WORK_TYPES = [
  { value: 'ROUTINE_MAINTENANCE', label: 'Routine',  icon: 'ph-broom'    },
  { value: 'REPAIR',              label: 'Repair',   icon: 'ph-wrench'   },
  { value: 'PLANTING',            label: 'Planting', icon: 'ph-plant'    },
  { value: 'WATERING',            label: 'Watering', icon: 'ph-drop'     },
  { value: 'CLEANING',            label: 'Cleaning', icon: 'ph-sparkle'  },
];

/**
 * Upload files with real XHR progress events.
 * Returns a Promise resolving to the server response data.
 * @param {FormData} formData
 * @param {function(percent: number)} onProgress  — called with 0-100
 */
function uploadWithProgress(formData, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../index.php?route=upload/create');
    xhr.setRequestHeader('Accept', 'application/json');
    const csrf = Auth.getCsrfToken();
    if (csrf) xhr.setRequestHeader('X-CSRF-Token', csrf);
    xhr.withCredentials = true;

    xhr.upload.addEventListener('progress', (e) => {
      if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
    });

    xhr.addEventListener('load', () => {
      let parsed;
      try { parsed = JSON.parse(xhr.responseText); } catch (_) {
        reject(new Error('Invalid server response')); return;
      }
      if (xhr.status === 401) { Auth.clearSession(); App.showLogin(); reject(new Error('Unauthorized')); return; }
      if (xhr.status < 200 || xhr.status >= 300 || !parsed.success) {
        reject(new Error(parsed?.error || `HTTP ${xhr.status}`)); return;
      }
      resolve(parsed.data);
    });

    xhr.addEventListener('error', () => reject(new Error('Network error — check connection')));
    xhr.send(formData);
  });
}

function uploadView(surface, parentType, title) {
  return {
    async render() {
      // Belt / parent selector
      let parentField = UI.field({ name: 'parent_id', label: `${UI.titleize(parentType)} ID`, type: 'number', required: true });
      if (parentType === 'GREEN_BELT') {
        try {
          const targets = normalizeItems(await Api.get('upload/targets'));
          if (targets.length) {
            parentField = UI.field({
              name: 'parent_id',
              label: 'Assigned Green Belt',
              type: 'select',
              required: true,
              options: targets.map((t) => ({ value: t.id, label: t.label || `Belt #${t.id}` }))
            });
          }
        } catch (_) {}
      }

      // Upload type chips (WORK / ISSUE)
      const uploadTypeChips = parentType !== 'TASK' ? `
        <div class="upload-section">
          <div class="upload-section-label">Upload type</div>
          <div class="upload-type-chips">
            <button type="button" class="upload-type-chip active js-upload-type-chip" data-type="WORK">
              <i class="ph ph-image"></i><span>Work proof</span>
            </button>
            <button type="button" class="upload-type-chip js-upload-type-chip" data-type="ISSUE">
              <i class="ph ph-warning-circle"></i><span>Issue report</span>
            </button>
          </div>
          <input type="hidden" name="upload_type" value="WORK">
        </div>
      ` : `<input type="hidden" name="upload_type" value="WORK">`;

      // Work type chips (only shown for GREEN_BELT WORK uploads)
      const workTypeSection = parentType === 'GREEN_BELT' ? `
        <div class="upload-section js-work-type-section">
          <div class="upload-section-label">Work type <span class="upload-required">Required</span></div>
          <div class="upload-work-type-chips">
            ${UPLOAD_WORK_TYPES.map((wt) => `
              <button type="button" class="upload-work-chip js-work-type-chip" data-value="${wt.value}" aria-pressed="false">
                <i class="ph ${wt.icon}"></i>
                <span>${wt.label}</span>
              </button>
            `).join('')}
          </div>
          <input type="hidden" name="work_type" class="js-work-type-hidden">
        </div>
      ` : '';

      // Extra fields
      const extraFields = parentType === 'TASK'
        ? UI.field({ name: 'photo_label', label: 'Photo Label', type: 'select', value: 'AFTER_WORK', options: ['BEFORE_WORK', 'AFTER_WORK', 'GENERAL'] })
        : parentType === 'SITE'
          ? UI.field({ name: 'discovery_mode', label: 'Discovery Mode', type: 'select', value: '0', options: [{ value: '0', label: 'No' }, { value: '1', label: 'Yes' }] })
          : '';

      return UI.page(title, 'Submit field proof with photos')
        + UI.panel('Upload proof', `
          <form class="upload-mobile-form js-upload-form" autocomplete="off" novalidate>
            <input type="hidden" name="gps_latitude"  id="gps_latitude"  value="">
            <input type="hidden" name="gps_longitude" id="gps_longitude" value="">

            <div class="upload-section">${parentField}</div>

            ${uploadTypeChips}
            ${workTypeSection}

            ${extraFields ? `<div class="upload-section">${extraFields}</div>` : ''}

            <div class="upload-section">
              ${UI.field({ name: 'comment_text', label: 'Comment (optional)', type: 'textarea', full: true })}
            </div>

            <div class="upload-section">
              <div class="upload-section-label">Photos</div>
              <label class="upload-file-btn js-upload-file-btn">
                <i class="ph ph-camera-plus"></i>
                <span class="js-upload-file-label">Take a photo or choose from gallery</span>
                <input type="file" name="files[]" multiple accept="image/*"
                       class="upload-file-input js-file-input" aria-label="Select photos">
              </label>
            </div>

            <div class="upload-preview js-upload-preview" hidden>
              <div class="upload-preview-header">
                <span class="js-preview-count"></span>
                <button type="button" class="btn btn-ghost btn-sm js-clear-files">Clear all</button>
              </div>
              <div class="upload-preview-grid js-preview-grid"></div>
            </div>

            <div class="upload-progress js-upload-progress" hidden>
              <div class="upload-progress-track"><div class="upload-progress-fill js-progress-fill"></div></div>
              <p class="js-progress-text upload-progress-text">Uploading…</p>
            </div>

            <button type="submit" class="btn btn-primary btn-block upload-submit-btn js-upload-submit" disabled>
              <i class="ph ph-upload-simple"></i>
              <span class="js-upload-submit-label">Select photos to upload</span>
            </button>
          </form>

          <div class="upload-success js-upload-success" hidden>
            <i class="ph ph-check-circle upload-success-icon"></i>
            <h3 class="js-success-headline">Photos uploaded</h3>
            <p class="upload-success-sub">Submitted for review.</p>
            <div class="upload-success-actions">
              <button type="button" class="btn btn-ghost js-upload-more">Upload more</button>
              <button type="button" class="btn btn-primary" data-nav="green_belt.my_uploads">
                <i class="ph ph-images"></i><span>View My Uploads</span>
              </button>
            </div>
          </div>
        `);
    },

    async afterRender() {
      // Silent GPS
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          (pos) => {
            const lat = document.getElementById('gps_latitude');
            const lng = document.getElementById('gps_longitude');
            if (lat) lat.value = pos.coords.latitude;
            if (lng) lng.value = pos.coords.longitude;
          },
          () => {}
        );
      }

      // Selected files (maintained as an array since FileList is immutable)
      let selectedFiles = [];

      const form       = document.querySelector('.js-upload-form');
      const fileInput  = form?.querySelector('.js-file-input');
      const previewBox = form?.querySelector('.js-upload-preview');
      const previewGrid= form?.querySelector('.js-preview-grid');
      const previewCount = form?.querySelector('.js-preview-count');
      const progressBox  = form?.querySelector('.js-upload-progress');
      const progressFill = form?.querySelector('.js-progress-fill');
      const progressText = form?.querySelector('.js-progress-text');
      const submitBtn    = form?.querySelector('.js-upload-submit');
      const submitLabel  = form?.querySelector('.js-upload-submit-label');
      const successBox   = document.querySelector('.js-upload-success');

      const refreshSubmitState = () => {
        if (!submitBtn) return;
        const hasFiles = selectedFiles.length > 0;
        submitBtn.disabled = !hasFiles;
        if (submitLabel) {
          submitLabel.textContent = hasFiles
            ? `Upload ${selectedFiles.length} photo${selectedFiles.length > 1 ? 's' : ''}`
            : 'Select photos to upload';
        }
      };

      const renderPreviews = () => {
        if (!previewGrid) return;
        previewGrid.innerHTML = selectedFiles.map((file, i) => {
          const url = URL.createObjectURL(file);
          return `
            <div class="upload-thumb-wrap">
              <img src="${url}" class="upload-thumb" alt="Preview ${i + 1}">
              <button type="button" class="upload-thumb-remove js-remove-file" data-index="${i}" aria-label="Remove photo ${i + 1}">
                <i class="ph ph-x"></i>
              </button>
            </div>
          `;
        }).join('');
        if (previewCount) {
          previewCount.textContent = `${selectedFiles.length} photo${selectedFiles.length > 1 ? 's' : ''} selected`;
        }
        if (previewBox) previewBox.hidden = selectedFiles.length === 0;
        refreshSubmitState();
      };

      fileInput?.addEventListener('change', () => {
        Array.from(fileInput.files).forEach((f) => selectedFiles.push(f));
        fileInput.value = '';
        renderPreviews();
      });

      previewGrid?.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-remove-file');
        if (!btn) return;
        const idx = parseInt(btn.dataset.index, 10);
        selectedFiles.splice(idx, 1);
        renderPreviews();
      });

      form?.querySelector('.js-clear-files')?.addEventListener('click', () => {
        selectedFiles = [];
        renderPreviews();
      });

      // Upload type toggle (WORK / ISSUE)
      form?.querySelectorAll('.js-upload-type-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
          form.querySelectorAll('.js-upload-type-chip').forEach((c) => c.classList.remove('active'));
          chip.classList.add('active');
          const typeInput = form.querySelector('[name="upload_type"]');
          if (typeInput) typeInput.value = chip.dataset.type;
          const workSection = form.querySelector('.js-work-type-section');
          if (workSection) workSection.hidden = chip.dataset.type !== 'WORK';
          if (chip.dataset.type !== 'WORK') {
            form.querySelectorAll('.js-work-type-chip').forEach((c) => c.classList.remove('active'));
            form.querySelectorAll('.js-work-type-hidden').forEach((inp) => { inp.value = ''; });
          }
        });
      });

      // Work type chip selection
      form?.querySelectorAll('.js-work-type-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
          form.querySelectorAll('.js-work-type-chip').forEach((c) => {
            c.classList.remove('active');
            c.setAttribute('aria-pressed', 'false');
          });
          chip.classList.add('active');
          chip.setAttribute('aria-pressed', 'true');
          const hidden = form.querySelector('.js-work-type-hidden');
          if (hidden) hidden.value = chip.dataset.value;
        });
      });

      // My Uploads nav button in success card
      successBox?.querySelector('[data-nav]')?.addEventListener('click', (e) => {
        App.navigate(e.currentTarget.dataset.nav);
      });

      form?.querySelector('.js-upload-more')?.addEventListener('click', () => {
        successBox.hidden = true;
        form.hidden = false;
        form.reset();
        selectedFiles = [];
        renderPreviews();
        form.querySelectorAll('.js-work-type-chip').forEach((c) => { c.classList.remove('active'); c.setAttribute('aria-pressed','false'); });
        form.querySelectorAll('.js-upload-type-chip').forEach((c, i) => c.classList.toggle('active', i === 0));
        const typeInput = form.querySelector('[name="upload_type"]'); if (typeInput) typeInput.value = 'WORK';
        const workSection = form.querySelector('.js-work-type-section'); if (workSection) workSection.hidden = false;
        refreshSubmitState();
      });

      // Form submit
      form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        // Validate work type for GREEN_BELT WORK uploads
        const uploadType = form.querySelector('[name="upload_type"]')?.value || 'WORK';
        if (parentType === 'GREEN_BELT' && uploadType === 'WORK') {
          const workType = form.querySelector('.js-work-type-hidden')?.value;
          if (!workType) { UI.toast('Please select a work type', 'bad'); return; }
        }
        if (!selectedFiles.length) { UI.toast('Please select at least one photo', 'bad'); return; }

        // Build FormData
        const formData = new FormData(form);
        formData.set('parent_type', parentType);
        formData.delete('files[]');
        selectedFiles.forEach((file, i) => formData.append(`files[${i}]`, file));

        // Show progress, hide form controls
        if (submitBtn) submitBtn.disabled = true;
        if (progressBox) progressBox.hidden = false;
        if (progressFill) progressFill.style.width = '0%';
        if (progressText) progressText.textContent = `Uploading ${selectedFiles.length} photo${selectedFiles.length > 1 ? 's' : ''}…`;

        try {
          await uploadWithProgress(formData, (pct) => {
            if (progressFill) progressFill.style.width = `${pct}%`;
            if (progressText) progressText.textContent = `Uploading… ${pct}%`;
          });

          // Success
          const count = selectedFiles.length;
          form.hidden = true;
          if (successBox) {
            successBox.hidden = false;
            const headline = successBox.querySelector('.js-success-headline');
            if (headline) headline.textContent = `${count} photo${count > 1 ? 's' : ''} uploaded successfully`;
          }
        } catch (err) {
          UI.toast(err.message, 'bad');
          if (progressBox) progressBox.hidden = true;
          if (submitBtn) { submitBtn.disabled = false; refreshSubmitState(); }
        }
      });

      refreshSubmitState();
    }
  };
}

Views.register('green_belt.supervisor_upload', uploadView('SUPERVISOR', 'GREEN_BELT', 'Supervisor Upload'));
Views.register('green_belt.outsourced_upload', uploadView('OUTSOURCED', 'GREEN_BELT', 'Outsourced Upload'));
Views.register('monitoring.upload', {
  async render() {
    return UI.page('Monitoring Upload', 'Submit monitoring proof with discovery options')
      + UI.panel('Upload Proof', `
        <form class="stack-form js-monitoring-upload-form">
          <input type="hidden" name="parent_type" value="SITE">
          <input type="hidden" name="surface" value="MONITORING">
          <input type="hidden" name="upload_type" value="WORK">
          <input type="hidden" name="gps_latitude" value="">
          <input type="hidden" name="gps_longitude" value="">
          
          <div class="form-grid">
            ${UI.field({ name: 'parent_id', label: 'Site ID', type: 'number', required: true })}
            <div class="field full">
              <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="discovery_mode" value="1">
                <span style="font-weight: bold;">Mark as Free Media Discovery</span>
              </label>
              <p style="font-size: 0.8rem; color: var(--ink-500); margin-top: 4px; margin-left: 24px;">
                Creates a free media record for this site.
              </p>
            </div>
            ${UI.field({ name: 'comment_text', label: 'Comment', type: 'textarea', full: true })}
            <label class="field full">
              <span>Photos</span>
              <input type="file" name="files[]" multiple accept="image/*" required>
            </label>
          </div>
          <button type="submit" class="btn btn-primary"><i class="ph ph-upload-simple"></i><span>Upload Monitoring Proof</span></button>
        </form>
      `);
  },
  async afterRender() {
    // Silent GPS capture
    if ('geolocation' in navigator) {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          document.querySelector('[name="gps_latitude"]').value = pos.coords.latitude;
          document.querySelector('[name="gps_longitude"]').value = pos.coords.longitude;
        },
        (err) => console.log('GPS capture skipped or failed:', err.message),
        { timeout: 5000 }
      );
    }

    document.querySelector('.js-monitoring-upload-form')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const formData = new FormData(form);
      
      // Ensure discovery_mode is 0 if not checked
      if (!form.elements.discovery_mode.checked) {
        formData.set('discovery_mode', '0');
      }

      try {
        await Api.upload('upload/create', formData);
        UI.toast('Monitoring proof uploaded', 'good');
        form.reset();
      } catch (error) {
        UI.toast(error.message, 'bad');
      }
    });
  }
});

Views.register('green_belt.my_uploads', {
  async render() {
    const data = await Api.get('upload/my-list');
    return renderListPage('My Uploads', 'upload/my-list', data, 'green_belt.my_uploads', {
      columns: ['id', 'parent_type', 'parent_name', 'upload_type', 'work_type', 'created_at']
    });
  },
  async afterRender() {
    attachRefresh();
  }
});

Views.register('green_belt.watering_oversight', {
  async render({ params = {} }) {
    const date = params.date || UI.currentDate();
    const [attendanceResp, wateringResp, labourResp, issuesResp] = await Promise.all([
      Api.get('attendance/list', { date }),
      Api.get('oversight/watering', { date }),
      Api.get('labour/list', { date }),
      Api.get('issue/list', { status: 'OPEN', site_category: 'GREEN_BELT' })
    ]);

    const attendance = normalizeItems(attendanceResp);
    const watering = normalizeItems(wateringResp);
    const labour = normalizeItems(labourResp);
    const issues = normalizeItems(issuesResp).slice(0, 5);

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' });

    return UI.page("Today's Operations", date, actions)
      + UI.panel('Filters', UI.filters([
          { name: 'date', label: 'Date', type: 'date', value: date },
          { name: 'supervisor_user_id', label: 'Supervisor', type: 'select', options: [{ value: '', label: 'Loading...' }], value: params.supervisor_user_id || '' }
        ], 'Load'))
      + UI.panel('Section 1: Supervisor Attendance', 
          UI.table([
            { key: 'supervisor_name', label: 'Supervisor' },
            { key: 'attendance_status', label: 'Status', html: true, render: (row) => UI.status(row.attendance_status) },
            { key: 'marked_by_name', label: 'Marked By' },
            { key: 'marked_at', label: 'Marked At' }
          ], attendance, { empty: 'No attendance records' }),
          UI.button('Mark Attendance', { icon: 'ph-user-check', attr: 'data-mark-attendance' })
      )
      + UI.panel('Section 2: Watering Status', 
          UI.table([
            { key: 'belt_code', label: 'Belt Code' },
            { key: 'common_name', label: 'Name' },
            { key: 'supervisor_name', label: 'Supervisor' },
            { key: 'watering_status', label: 'Status', html: true, render: (row) => UI.status(row.watering_status) },
            { key: 'reason_text', label: 'Reason' },
            { 
              key: 'actions', 
              label: '', 
              html: true, 
              render: (row) => `
                <div style="display: flex; gap: 4px;">
                  <button class="btn btn-sm btn-ghost" data-quick-watering="${row.belt_id}" data-status="DONE">Done</button>
                  <button class="btn btn-sm btn-ghost" data-quick-watering="${row.belt_id}" data-status="NOT_REQUIRED">Skip</button>
                </div>
              `
            }
          ], watering, { empty: 'No watering records' }),
          UI.button('Mark Watering (Custom)', { icon: 'ph-drop', attr: 'data-mark-watering' })
      )
      + UI.panel('Section 3: Labour Entry', 
          UI.table(inferColumns(labour), labour, { empty: 'No labour entries' }),
          UI.button('Enter Labour Counts', { icon: 'ph-users', attr: 'data-mark-labour' })
      )
      + UI.panel('Section 4: Quick Exceptions — Open Issues', 
          UI.table([
            { key: 'title', label: 'Title' },
            { key: 'priority', label: 'Priority', html: true, render: (row) => UI.status(row.priority) },
            { key: 'belt_name', label: 'Belt' },
            { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.status) }
          ], issues, { empty: 'No open issues' }),
          UI.button('View All Issues', { icon: 'ph-list', attr: 'data-nav="green_belt.issue_management"' })
      );
  },
  async afterRender({ params = {} }) {
    attachRefresh();

    // Load supervisors dropdown
    const sups = await loadSupervisors();
    if (sups) {
      const select = document.querySelector('.js-filter-form [name="supervisor_user_id"]');
      if (select) {
        select.innerHTML = '<option value="">All Supervisors</option>' + sups.map(s => `<option value="${s.value}" ${String(s.value) === String(params.supervisor_user_id) ? 'selected' : ''}>${UI.escape(s.label)}</option>`).join('');
      }
    }

    wireFilters((payload) => App.navigate('green_belt.watering_oversight', payload));
    
    document.querySelector('[data-mark-attendance]')?.addEventListener('click', () => {
      openSimpleForm('Mark Attendance', [
        { name: 'supervisor_user_id', label: 'Supervisor ID', type: 'number', required: true },
        { name: 'attendance_date', label: 'Date', type: 'date', required: true, value: UI.currentDate() },
        { name: 'status', label: 'Status', type: 'select', value: 'PRESENT', options: ['PRESENT', 'ABSENT', 'LEAVE'] },
        { name: 'reason_text', label: 'Reason', type: 'textarea' }
      ], 'Save', (payload) => simpleAction('attendance/mark', payload, 'Attendance marked'));
    });

    document.querySelector('[data-mark-watering]')?.addEventListener('click', () => {
      openSimpleForm('Mark Watering', [
        { name: 'belt_id', label: 'Belt ID', type: 'number', required: true },
        { name: 'watering_date', label: 'Date', type: 'date', required: true, value: date },
        { name: 'status', label: 'Status', type: 'select', value: 'DONE', options: ['DONE', 'NOT_REQUIRED'] },
        { name: 'reason_text', label: 'Reason', type: 'textarea' }
      ], 'Save', (payload) => simpleAction('watering/mark', payload, 'Watering marked'));
    });

    document.querySelectorAll('[data-quick-watering]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const payload = {
          belt_id: btn.dataset.quickWatering,
          status: btn.dataset.status,
          watering_date: date
        };
        await simpleAction('watering/mark', payload, `Watering marked ${payload.status}`);
      });
    });

    document.querySelector('[data-mark-labour]')?.addEventListener('click', () => {
      openSimpleForm('Enter Labour Counts', [
        { name: 'belt_id', label: 'Belt ID', type: 'number', required: true },
        { name: 'entry_date', label: 'Date', type: 'date', required: true, value: UI.currentDate() },
        { name: 'labour_count', label: 'Labour', type: 'number', value: '0' },
        { name: 'gardener_count', label: 'Gardeners', type: 'number', value: '0' },
        { name: 'night_guard_count', label: 'Night Guards', type: 'number', value: '0' }
      ], 'Save', (payload) => simpleAction('labour/mark', payload, 'Labour counts saved'));
    });

    document.querySelectorAll('[data-nav]').forEach(el => {
      el.addEventListener('click', () => App.navigate(el.dataset.nav));
    });
  }
});


Views.register('monitoring.plan', {
  async render({ params = {} }) {
    const month = params.month || UI.currentMonth();
    const filters = { month };
    if (params.site_category) filters.site_category = params.site_category;
    if (params.lighting_type) filters.lighting_type = params.lighting_type;
    if (params.route_or_group) filters.route_or_group = params.route_or_group;

    const data = await Api.get('monitoringplan/list', filters);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'site_code', label: 'Site Code' },
      { key: 'location_text', label: 'Location' },
      { key: 'site_category', label: 'Category', html: true, render: (row) => UI.status(row.site_category) },
      { key: 'lighting_type', label: 'Lighting', html: true, render: (row) => UI.status(row.lighting_type) },
      { key: 'route_or_group', label: 'Route / Group' },
      { key: 'selected_due_dates_count', label: 'Due Dates' },
      { key: 'due_dates', label: 'Dates', render: (row) => row.due_dates.map(d => d.split('-')[2]).join(', ') }
    ];

    const actions = 
      UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
      UI.button('Bulk Copy Pattern', { icon: 'ph-copy', attr: 'data-bulk-copy' });

    return UI.page('Monitoring Plan', 'Manage monthly monitoring schedules', actions)
      + UI.panel('Filters', UI.filters([
        { name: 'month', label: 'Month', type: 'month', value: month },
        { name: 'site_category', label: 'Category', type: 'select', value: params.site_category || '', options: ['', 'GREEN_BELT', 'CITY', 'HIGHWAY'] },
        { name: 'lighting_type', label: 'Lighting', type: 'select', value: params.lighting_type || '', options: ['', 'LIT', 'NON_LIT'] },
        { name: 'route_or_group', label: 'Route / Group', value: params.route_or_group || '' }
      ], 'Load'))
      + UI.panel('Plan Records', UI.table(columns, rows, { 
          empty: 'No sites found for this month',
          rowAttr: (row) => `data-site='${JSON.stringify(row).replace(/'/g, "&#39;")}' data-month="${month}"`
      }))
      + UI.panel('Bulk Copy Tool', `
        <form class="stack-form js-bulk-copy-form">
          <div class="form-grid">
            ${UI.field({ name: 'source_month', label: 'Source Month', type: 'month', required: true, value: month })}
            ${UI.field({ name: 'target_month', label: 'Target Month', type: 'month', required: true, value: UI.nextMonth(month) })}
            
            <div class="field full" style="border: 1px solid var(--line); padding: 1rem; border-radius: 8px;">
              <p style="margin-bottom: 0.5rem; font-weight: bold;">Copy Mode:</p>
              <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                <input type="radio" name="copy_mode" value="route" checked>
                <span>By Route/Group</span>
              </label>
              <div style="margin-left: 24px; margin-bottom: 12px;">
                <input type="text" name="route_or_group" placeholder="Enter route or group name..." class="input">
              </div>
              
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="radio" name="copy_mode" value="ids">
                <span>By Site IDs</span>
              </label>
              <div style="margin-left: 24px;">
                <input type="text" name="site_ids_text" placeholder="101, 102, 105..." class="input">
              </div>
            </div>

            <div class="field full">
              <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="replace_existing" value="1">
                <span>Replace existing plans if target month already has dates</span>
              </label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="ph ph-copy"></i><span>Execute Bulk Copy</span></button>
        </form>
      `);
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('monitoring.plan', payload));

    // Old modal-based bulk copy removed in favor of in-page panel per plan
    
    document.querySelector('.js-bulk-copy-form')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const data = UI.formData(form);
      const payload = {
        source_month: data.source_month,
        target_month: data.target_month,
        replace_existing: !!data.replace_existing
      };

      if (data.copy_mode === 'route') {
        payload.route_or_group = data.route_or_group;
      } else {
        payload.site_ids = (data.site_ids_text || '').split(',').map(s => s.trim()).filter(Boolean);
      }

      await simpleAction('monitoringplan/bulk-copy', payload, 'Bulk copy process initiated');
    });

    document.querySelectorAll('[data-site]').forEach(row => {
      // ... site detail modal logic stays same ...
      row.addEventListener('click', () => {
        const site = JSON.parse(row.dataset.site);
        const month = row.dataset.month;
        
        const daysInMonth = new Date(month.split('-')[0], month.split('-')[1], 0).getDate();
        let html = `<div class="days-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; margin-bottom: 1rem;">`;
        for (let d = 1; d <= daysInMonth; d++) {
          const dateStr = `${month}-${String(d).padStart(2, '0')}`;
          const checked = site.due_dates.includes(dateStr) ? 'checked' : '';
          html += `
            <label style="border: 1px solid var(--line); padding: 0.5rem; text-align: center; cursor: pointer; border-radius: 4px;">
              <input type="checkbox" name="due_dates" value="${dateStr}" ${checked} style="display: block; margin: 0 auto 0.2rem;">
              <span style="font-size: 0.75rem;">${d}</span>
            </label>
          `;
        }
        html += `</div>`;

        UI.showModal(`Plan: ${site.site_code}`, `
          <form id="due-dates-form" class="stack-form">
            <p style="margin-bottom: 1rem;">Select monitoring dates for <strong>${month}</strong></p>
            ${html}
            <div class="modal-actions">
              <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
              <button type="button" class="btn" data-copy-next>Copy to Next Month</button>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        `);

        const modal = document.getElementById('modal-root');
        
        modal.querySelector('#due-dates-form').addEventListener('submit', async (e) => {
          e.preventDefault();
          const selected = Array.from(new FormData(e.target).getAll('due_dates'));
          await simpleAction('monitoringplan/save', {
            site_id: site.site_id,
            plan_month: month,
            due_dates: selected
          }, 'Plan updated');
          UI.closeModal();
        });

        modal.querySelector('[data-copy-next]').addEventListener('click', () => {
          const nextMonth = new Date(month + '-01');
          nextMonth.setMonth(nextMonth.getMonth() + 1);
          const targetMonth = nextMonth.toISOString().substring(0, 7);
          
          if (confirm(`Copy this month's pattern to ${targetMonth}?`)) {
            simpleAction('monitoringplan/copy-next-month', {
              site_id: site.site_id,
              source_month: month,
              target_month: targetMonth
            }, 'Copied to next month');
            UI.closeModal();
          }
        });
      });
    });
  }
});

Views.register('monitoring.history', {
  async render({ params = {} }) {
    const data = await Api.get('monitoring/history', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'created_at', label: 'Date/Time' },
      { key: 'site_code', label: 'Site Code' },
      { key: 'location_text', label: 'Location' },
      { key: 'is_discovery_mode', label: 'Mode', render: (row) => row.is_discovery_mode ? 'DISCOVERY' : 'PLANNED' },
      { key: 'comment_text', label: 'Comment' },
      { key: 'upload_count', label: 'Photos', render: (row) => row.upload_count || 1 }
    ];

    return UI.page('Monitoring History', 'Review submitted monitoring proof')
      + UI.panel('Filters', UI.filters([
        { name: 'date_from', label: 'From', type: 'date', value: params.date_from },
        { name: 'date_to', label: 'To', type: 'date', value: params.date_to },
        { name: 'site_category', label: 'Category', type: 'select', value: params.site_category || '', options: ['', 'GREEN_BELT', 'CITY', 'HIGHWAY'] },
        { name: 'discovery_mode', label: 'Discovery Mode', type: 'select', value: params.discovery_mode || '', options: [{ value: '', label: 'All' }, { value: '1', label: 'Discovery Only' }, { value: '0', label: 'Normal Only' }] }
      ], 'Search'))
      + UI.panel('History Records', UI.table(columns, rows, {
        empty: 'No monitoring history found',
        rowAttr: (row) => `data-history-id="${row.upload_id}"`
      }) + renderPagination(data.pagination, 'monitoring.history', params));
  },
  async afterRender() {
    attachRefresh();
    attachPagination();
    wireFilters((payload) => App.navigate('monitoring.history', payload));
  }
});

Views.register('task.request_intake', {
  async render({ params = {} }) {
    const isOps = Auth.getUser()?.role_key === 'OPS_MANAGER';
    const data = await Api.get('request/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'request_code', label: 'Request ID', render: (row) => row.request_code || `RQ-${String(row.id).padStart(5, '0')}` },
      { key: 'requester_name', label: 'Requester' },
      { key: 'request_type', label: 'Type' },
      { key: 'client_name', label: 'Client' },
      { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.status) },
      { key: 'priority', label: 'Priority', html: true, render: (row) => UI.status(row.priority) },
      { key: 'created_at', label: 'Requested' }
    ];

    if (isOps) {
      const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' });
      return UI.page('Task Requests', 'Review and approve operational requests', actions)
        + UI.panel('Filters', UI.filters([
          { name: 'status', label: 'Status', type: 'select', value: params.status, options: ['', 'PENDING', 'APPROVED', 'REJECTED'] }
        ], 'Apply'))
        + UI.panel('Pending Approvals', UI.table(columns, rows, {
          empty: 'No requests found',
          rowAttr: (row) => `data-request='${JSON.stringify(row).replace(/'/g, "&#39;")}'`
        }));
    }

    // Sales/Planning Role View
    return UI.page('Task Requests', 'Submit and track your operational requests')
      + UI.panel('Submit a New Request', `
        <form class="stack-form js-request-form">
          <div class="form-grid">
            ${UI.field({ name: 'request_type', label: 'Request Type', type: 'select', required: true, options: ['FABRICATION', 'PRINTING', 'MOUNTING', 'MAINTENANCE', 'OTHER'] })}
            ${UI.field({ name: 'priority', label: 'Priority', type: 'select', required: true, value: 'MEDIUM', options: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'] })}
            ${UI.field({ name: 'client_name', label: 'Client Name' })}
            ${UI.field({ name: 'campaign_id', label: 'Campaign ID (optional)', type: 'number' })}
            ${UI.field({ name: 'site_id', label: 'Site ID (optional)', type: 'number', value: params.site_id || '' })}
            ${UI.field({ name: 'belt_id', label: 'Belt ID (optional)', type: 'number' })}
            ${UI.field({ name: 'description', label: 'Detailed Description', type: 'textarea', full: true, required: true })}
            <div class="field full">
              <span>Reference Photo <small style="color:var(--ink-500)">(optional — requires Site ID above)</small></span>
              <input type="file" name="reference_photo" accept="image/*" style="padding:4px 0;">
            </div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="ph ph-paper-plane-tilt"></i><span>Submit Request</span></button>
        </form>
      `)
      + UI.panel('My Submitted Requests', UI.table(columns, rows, { 
          empty: 'You haven\'t submitted any requests yet.',
          rowAttr: (row) => `data-request='${JSON.stringify(row).replace(/'/g, "&#39;")}'`
      }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('task.request_intake', payload));

    const isOps = Auth.getUser()?.role_key === 'OPS_MANAGER';

    if (!isOps) {
      document.querySelector('.js-request-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.currentTarget;
        const payload = UI.formData(form);
        const fileInput = form.querySelector('[name="reference_photo"]');
        const file = fileInput?.files?.[0];

        await simpleAction('request/create', payload, 'Request submitted successfully');

        // Upload reference photo after request is created (requires site_id)
        if (file && payload.site_id) {
          try {
            const fd = new FormData();
            fd.append('parent_type', 'SITE');
            fd.append('parent_id', payload.site_id);
            fd.append('upload_type', 'WORK');
            fd.append('comment_text', `Reference photo for request: ${payload.description?.substring(0, 80) || ''}`);
            fd.append('files[]', file);
            await Api.upload('upload/create', fd);
            UI.toast('Reference photo attached', 'good');
          } catch (err) {
            UI.toast('Request submitted but photo upload failed: ' + err.message, 'bad');
          }
        }
      });
    }

    document.querySelectorAll('[data-request]').forEach(row => {
      row.addEventListener('click', () => {
        const request = JSON.parse(row.dataset.request);
        
        let extraHTML = '';
        if (isOps && request.status === 'SUBMITTED') {
          extraHTML = `
            <div class="modal-actions" style="margin-top: 1rem; border-top: 1px solid var(--line); padding-top: 1rem;">
              <button type="button" class="btn btn-primary" data-approve="${request.id}">Approve & Create Task</button>
              <button type="button" class="btn btn-danger" data-reject="${request.id}">Reject</button>
            </div>
          `;
        }

        UI.showModal('Request Details', `
          <div class="stack-form">
            <div class="form-grid">
              <div class="field"><span>Request ID</span><input type="text" value="${UI.escape(request.request_code || `RQ-${String(request.id).padStart(5, '0')}`)}" readonly></div>
              <div class="field"><span>Type</span><input type="text" value="${request.request_type}" readonly></div>
              <div class="field"><span>Requester</span><input type="text" value="${request.requester_name}" readonly></div>
              <div class="field"><span>Client</span><input type="text" value="${request.client_name || 'N/A'}" readonly></div>
              <div class="field"><span>Status</span><input type="text" value="${request.status}" readonly></div>
              ${request.rejection_reason ? `<div class="field full"><span style="color:var(--bad)">Rejection Reason</span><textarea readonly>${request.rejection_reason}</textarea></div>` : ''}
              <div class="field full"><span>Description</span><textarea readonly>${request.description}</textarea></div>
            </div>
            ${extraHTML}
          </div>
        `);

        if (isOps) {
          const modal = document.getElementById('modal-root');
          modal.querySelector('[data-approve]')?.addEventListener('click', async () => {
            // Step 1: approve the request
            await Api.post('request/approve', { request_id: request.id });
            UI.closeModal();
            UI.toast('Request approved — create the task below', 'good');
            // Step 2: open task creation form pre-filled from request, with traceability
            openSimpleForm('Create Task from Request', [
              { name: 'request_id', type: 'hidden', value: request.id },
              { name: 'task_source_type', type: 'hidden', value: 'REQUEST' },
              { name: 'task_category', label: 'Category', type: 'select', options: ['GENERAL', 'CLIENT_CAMPAIGN', 'SITE_REPAIR'], value: request.request_type || 'GENERAL', required: true },
              { name: 'vertical_type', label: 'Vertical', type: 'select', options: ['GREEN_BELT', 'ADVERTISEMENT', 'MONITORING'], required: true },
              { name: 'priority', label: 'Priority', type: 'select', options: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], value: request.priority || 'MEDIUM', required: true },
              { name: 'work_description', label: 'Work Description', type: 'textarea', value: request.description || '', required: true },
              { name: 'location_text', label: 'Location', type: 'text', required: true },
              { name: 'assigned_lead_user_id', label: 'Assigned Lead User ID', type: 'number' },
              { name: 'start_date', label: 'Start Date', type: 'date', value: UI.currentDate() },
              { name: 'expected_close_date', label: 'Expected Close', type: 'date' }
            ], 'Create Task', (payload) => simpleAction('task/create', payload, 'Task created from request'));
          });

          modal.querySelector('[data-reject]')?.addEventListener('click', () => {
            UI.closeModal();
            openSimpleForm('Reject Request', [
              { name: 'request_id', type: 'hidden', value: request.id },
              { name: 'rejection_reason', label: 'Reason for Rejection', type: 'textarea', required: true }
            ], 'Confirm Rejection', (payload) => simpleAction('request/reject', payload, 'Request rejected'));
          });
        }
      });
    });
  }
});

Views.register('task.management', {
  async render({ params = {} }) {
    const data = await Api.get('task/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'id', label: 'ID' },
      { key: 'work_description', label: 'Task' },
      { key: 'vertical_type', label: 'Vertical' },
      { key: 'assigned_lead_name', label: 'Lead' },
      { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.status) },
      { key: 'priority', label: 'Priority', html: true, render: (row) => UI.status(row.priority) },
      { key: 'progress_percent', label: 'Progress', render: (row) => `${row.progress_percent}%` }
    ];

    const actions = UI.button('New Task', { icon: 'ph-plus', kind: 'btn-primary', attr: 'data-create-task' });

    return UI.page('Task Management', 'Monitor and assign fabrication tasks', actions)
      + UI.panel('Filters', UI.filters([
        { name: 'status', label: 'Status', type: 'select', value: params.status, options: ['', 'OPEN', 'RUNNING', 'COMPLETED', 'CANCELLED', 'ARCHIVED'] },
        { name: 'priority', label: 'Priority', type: 'select', value: params.priority, options: ['', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'] },
        { name: 'vertical_type', label: 'Vertical', type: 'select', value: params.vertical_type, options: ['', 'GREEN_BELT', 'ADVERTISEMENT', 'MONITORING'] }
      ], 'Apply'))
      + UI.panel('Tasks', UI.table(columns, rows, {
        empty: 'No tasks found',
        rowAttr: (row) => `data-task-id="${row.id}"`
      }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('task.management', payload));

    document.querySelector('[data-create-task]')?.addEventListener('click', () => {
      openSimpleForm('Create Task', [
        { name: 'task_category', label: 'Category', type: 'select', options: ['GENERAL', 'CLIENT_CAMPAIGN', 'SITE_REPAIR'], required: true },
        { name: 'vertical_type', label: 'Vertical', type: 'select', options: ['GREEN_BELT', 'ADVERTISEMENT', 'MONITORING'], required: true },
        { name: 'priority', label: 'Priority', type: 'select', options: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], required: true },
        { name: 'work_description', label: 'Work Description', type: 'textarea', required: true },
        { name: 'location_text', label: 'Location', type: 'text', required: true },
        { name: 'assigned_lead_user_id', label: 'Assigned Lead User ID', type: 'number' },
        { name: 'start_date', label: 'Start Date', type: 'date', value: UI.currentDate() },
        { name: 'expected_close_date', label: 'Expected Close', type: 'date' }
      ], 'Create', (payload) => simpleAction('task/create', payload, 'Task created'));
    });

    document.querySelectorAll('[data-task-id]').forEach(row => {
      row.addEventListener('click', () => {
        App.navigate('task.detail', { task_id: row.dataset.taskId });
      });
    });
  }
});

Views.register('task.my_tasks', {
  async render({ params = {} }) {
    const filters = {};
    if (params.status) filters.status = params.status;

    const data = await Api.get('task/my', filters);
    const rows = normalizeItems(data);

    const columns = [
      { key: 'id', label: 'Task ID' },
      { key: 'work_description', label: 'Description' },
      { key: 'location_text', label: 'Location' },
      { key: 'priority', label: 'Priority', html: true, render: (row) => UI.status(row.priority) },
      { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.status) },
      { key: 'progress_percent', label: 'Progress', render: (row) => `${row.progress_percent || 0}%` },
      { key: 'expected_close_date', label: 'Due By' },
      {
        key: 'actions',
        label: 'Actions',
        html: true,
        render: (row) => {
          const taskId = row.id;
          let buttons = '';
          if (row.status === 'OPEN') {
            buttons += `<button class="btn btn-sm btn-primary" data-start-task="${taskId}">Start</button> `;
          }
          if (row.status === 'RUNNING') {
            buttons += `<button class="btn btn-sm" data-update-progress="${taskId}" data-progress="${row.progress_percent || 0}">Progress</button> `;
            buttons += `<button class="btn btn-sm btn-primary" data-mark-done="${taskId}">Mark Done</button> `;
          }
          buttons += `<button class="btn btn-sm btn-ghost" data-open-detail="${taskId}">Detail</button>`;
          return buttons;
        }
      }
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'status', label: 'Status', type: 'select', value: params.status || '', options: [
        { value: '', label: 'All' }, 'OPEN', 'RUNNING', 'COMPLETED', 'CANCELLED', 'ARCHIVED'
      ]}
    ], 'Apply'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
                    UI.button('Worker Allocation', { icon: 'ph-users', attr: 'data-open-workers' });

    return UI.page('My Tasks', 'Tasks assigned to you for execution', actions)
      + filterUI
      + UI.panel('Records', UI.table(columns, rows, { empty: 'No tasks assigned to you' }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('task.my_tasks', payload));

    document.querySelector('[data-open-workers]')?.addEventListener('click', () => {
      App.navigate('task.worker_allocation');
    });

    document.querySelectorAll('[data-open-detail]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        App.navigate('task.detail', { task_id: btn.dataset.openDetail });
      });
    });

    document.querySelectorAll('[data-start-task]').forEach((btn) => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const taskId = btn.dataset.startTask;
        if (!confirm('Start this task? Status will transition from OPEN to RUNNING.')) return;
        await simpleAction('task/start', { task_id: parseInt(taskId, 10) }, 'Task started');
        App.refresh();
      });
    });

    document.querySelectorAll('[data-update-progress]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const taskId = btn.dataset.updateProgress;
        const currentProgress = parseInt(btn.dataset.progress, 10) || 0;
        openSimpleForm('Update Task Progress', [
          { name: 'task_id', type: 'hidden', value: taskId },
          { name: 'progress_percent', label: 'Progress %', type: 'number', value: String(currentProgress), required: true },
          { name: 'remark_1', label: 'Remark 1', type: 'text' },
          { name: 'remark_2', label: 'Remark 2', type: 'text' },
          { name: 'completion_note', label: 'Note', type: 'textarea' }
        ], 'Save Progress', (payload) => {
          payload.task_id = parseInt(payload.task_id, 10);
          payload.progress_percent = Math.max(0, Math.min(100, parseInt(payload.progress_percent, 10) || 0));
          return simpleAction('task/progress', payload, 'Progress updated');
        });
      });
    });

    document.querySelectorAll('[data-mark-done]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const taskId = btn.dataset.markDone;
        openSimpleForm('Mark Work Done', [
          { name: 'task_id', type: 'hidden', value: taskId },
          { name: 'progress_percent', label: 'Final Progress %', type: 'number', value: '100', required: true },
          { name: 'completion_note', label: 'Completion Note', type: 'textarea', required: true }
        ], 'Mark Done', (payload) => {
          payload.task_id = parseInt(payload.task_id, 10);
          payload.progress_percent = Math.max(0, Math.min(100, parseInt(payload.progress_percent, 10) || 0));
          return simpleAction('task/work-done', payload, 'Work marked done — awaiting Ops completion');
        });
      });
    });
  }
});

Views.register('task.detail', {
  async render({ params = {} }) {
    const task = await Api.get('task/get', { task_id: params.task_id });
    if (!task) return UI.error('Task not found');

    const metaColumns = [
      { key: 'label', label: 'Field' },
      { key: 'value', label: 'Value', html: true }
    ];
    const metaRows = [
      { label: 'Description', value: task.work_description },
      { label: 'Status', value: UI.status(task.status) },
      { label: 'Priority', value: UI.status(task.priority) },
      { label: 'Assigned Lead', value: task.assigned_lead_name || 'Unassigned' },
      { label: 'Progress', value: `${task.progress_percent}%` }
    ];

    const actions = UI.button('Back', { icon: 'ph-arrow-left', attr: 'data-back' })
      + UI.button('Manage Lead', { icon: 'ph-user-circle-plus', attr: 'data-manage-lead' })
      + UI.button('Assign Workers', { icon: 'ph-users', attr: 'data-assign-workers' });

    return UI.page(`Task #${task.id}`, task.vertical_type, actions)
      + UI.panel('Metadata', UI.table(metaColumns, metaRows))
      + UI.panel('Allocation', UI.table([
          { key: 'worker_name', label: 'Worker' },
          { key: 'skill_tag', label: 'Skill' },
          { key: 'assigned_at', label: 'Assigned' },
          { key: 'id', label: 'Action', render: (row) => `<button class="btn btn-ghost btn-sm" data-release="${row.id}">Release</button>` }
        ], task.allocations || [], { empty: 'No workers allocated' }));
  },
  async afterRender({ params = {} }) {
    document.querySelector('[data-back]')?.addEventListener('click', () => {
      const role = Auth.getUser()?.role_key;
      App.navigate(role === 'FABRICATION_LEAD' ? 'task.my_tasks' : 'task.management');
    });

    document.querySelector('[data-manage-lead]')?.addEventListener('click', () => {
      openSimpleForm('Assign Lead', [
        { name: 'task_id', type: 'hidden', value: params.task_id },
        { name: 'assigned_lead_user_id', label: 'Lead User ID', type: 'number', required: true }
      ], 'Assign', (payload) => {
        payload.task_id = parseInt(payload.task_id, 10);
        payload.assigned_lead_user_id = parseInt(payload.assigned_lead_user_id, 10);
        return simpleAction('task/update', payload, 'Lead assigned');
      });
    });

    document.querySelector('[data-assign-workers]')?.addEventListener('click', async () => {
      openSimpleForm('Assign Workers', [
        { name: 'task_id', type: 'hidden', value: params.task_id },
        { name: 'worker_ids_text', label: 'Worker IDs (comma separated)', type: 'textarea', required: true }
      ], 'Allocate', (payload) => {
        payload.worker_ids = payload.worker_ids_text.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
        delete payload.worker_ids_text;
        return simpleAction('taskworker/assign', payload, 'Workers allocated');
      });
    });

    document.querySelectorAll('[data-release]').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        if (confirm('Release this worker?')) {
          await simpleAction('taskworker/release', { allocation_id: btn.dataset.release }, 'Worker released');
          App.refresh();
        }
      });
    });
  }
});

Views.register('task.worker_allocation', {
  async render({ params = {} }) {
    const data = await Api.get('worker/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'id', label: 'ID' },
      { key: 'worker_name', label: 'Name' },
      { key: 'skill_tag', label: 'Skill', html: true, render: (row) => UI.status(row.skill_tag) },
      { key: 'is_active', label: 'Status', render: (row) => row.is_active ? 'Active' : 'Inactive' }
    ];

    const actions = UI.button('New Worker', { icon: 'ph-plus', kind: 'btn-primary', attr: 'data-create-worker' });

    return UI.page('Workers', 'Manage fabrication and mounting staff', actions)
      + UI.panel('Filters', UI.filters([
        { name: 'skill_tag', label: 'Skill', type: 'select', value: params.skill_tag, options: ['', 'FABRICATION', 'PRINTING', 'MOUNTING', 'MAINTENANCE'] },
        { name: 'is_active', label: 'Active Only?', type: 'select', value: params.is_active, options: [{value: '', label: 'All'}, {value: '1', label: 'Yes'}, {value: '0', label: 'No'}] }
      ], 'Apply'))
      + UI.panel('Records', UI.table(columns, rows, {
        empty: 'No workers found',
        rowAttr: (row) => `data-worker='${JSON.stringify(row).replace(/'/g, "&#39;")}'`
      }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('task.worker_allocation', payload));

    document.querySelector('[data-create-worker]')?.addEventListener('click', () => {
      openSimpleForm('Create Worker', [
        { name: 'worker_name', label: 'Full Name', required: true },
        { name: 'skill_tag', label: 'Skill Tag', type: 'select', options: ['FABRICATION', 'PRINTING', 'MOUNTING', 'MAINTENANCE'], required: true }
      ], 'Create', (payload) => simpleAction('worker/create', payload, 'Worker created'));
    });

    document.querySelectorAll('[data-worker]').forEach(row => {
      row.addEventListener('click', () => {
        const worker = JSON.parse(row.dataset.worker);
        openSimpleForm('Edit Worker', [
          { name: 'worker_id', type: 'hidden', value: worker.id },
          { name: 'worker_name', label: 'Full Name', value: worker.worker_name, required: true },
          { name: 'skill_tag', label: 'Skill Tag', type: 'select', value: worker.skill_tag, options: ['FABRICATION', 'PRINTING', 'MOUNTING', 'MAINTENANCE'], required: true },
          { name: 'is_active', label: 'Is Active?', type: 'select', value: worker.is_active ? '1' : '0', options: [{value: '1', label: 'Yes'}, {value: '0', label: 'No'}] }
        ], 'Update', (payload) => {
          payload.is_active = payload.is_active === '1';
          return simpleAction('worker/update', payload, 'Worker updated');
        });
      });
    });
  }
});

Views.register('governance.audit_logs', {
  async render({ params = {} }) {
    const data = await Api.get('audit/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'created_at', label: 'Timestamp' },
      { key: 'actor_user_name', label: 'Actor' },
      { key: 'action_type', label: 'Action' },
      { key: 'entity_type', label: 'Entity' },
      { key: 'entity_id', label: 'ID' }
    ];

    return UI.page('Audit Logs', 'Track all system activities')
      + UI.panel('Filters', UI.filters([
        { name: 'date_from', label: 'From', type: 'date', value: params.date_from },
        { name: 'date_to', label: 'To', type: 'date', value: params.date_to },
        { name: 'action_type', label: 'Action', value: params.action_type || '' },
        { name: 'entity_type', label: 'Entity', value: params.entity_type || '' }
      ], 'Search'))
      + UI.panel('History', UI.table(columns, rows, {
        empty: 'No audit logs found',
        rowAttr: (row) => `data-audit='${JSON.stringify(row).replace(/'/g, "&#39;")}'`
      }) + renderPagination(data.pagination, 'governance.audit_logs', params));
  },
  async afterRender() {
    attachRefresh();
    attachPagination();
    wireFilters((payload) => App.navigate('governance.audit_logs', payload));

    document.querySelectorAll('[data-audit]').forEach(row => {
      row.addEventListener('click', () => {
        const audit = JSON.parse(row.dataset.audit);
        const formatJson = (obj) => obj ? `<pre style="font-size: 0.8rem; background: var(--surface-soft); padding: 0.5rem; border-radius: 4px; border: 1px solid var(--line); overflow: auto; max-height: 200px;">${JSON.stringify(obj, null, 2)}</pre>` : 'None';
        
        UI.showModal('Audit Detail', `
          <div class="stack-form">
            <div class="form-grid">
              <div class="field"><span>Actor</span><input type="text" value="${audit.actor_user_name}" readonly></div>
              <div class="field"><span>Action</span><input type="text" value="${audit.action_type}" readonly></div>
              <div class="field"><span>Entity</span><input type="text" value="${audit.entity_type} (#${audit.entity_id})" readonly></div>
              <div class="field"><span>Time</span><input type="text" value="${audit.created_at}" readonly></div>
            </div>
            <div class="field full" style="margin-top: 1rem;">
              <span>Old Values</span>
              ${formatJson(audit.old_values)}
            </div>
            <div class="field full" style="margin-top: 1rem;">
              <span>New Values</span>
              ${formatJson(audit.new_values)}
            </div>
          </div>
        `);
      });
    });
  }
});

Views.register('settings.system', {
  async render() {
    const data = await Api.get('settings/list');
    const rows = normalizeItems(data);
    const columns = [
      { key: 'setting_key', label: 'Key' },
      { key: 'setting_value', label: 'Value' },
      { key: 'description', label: 'Description' }
    ];

    return UI.page('System Settings', 'Manage application-wide configuration')
      + UI.panel('Configuration', UI.table(columns, rows, {
        empty: 'No settings found',
        rowAttr: (row) => `data-setting='${JSON.stringify(row).replace(/'/g, "&#39;")}'`
      }));
  },
  async afterRender() {
    attachRefresh();
    document.querySelectorAll('[data-setting]').forEach(row => {
      row.addEventListener('click', () => {
        const setting = JSON.parse(row.dataset.setting);
        openSimpleForm(`Edit ${setting.setting_key}`, [
          { name: 'setting_key', type: 'hidden', value: setting.setting_key },
          { name: 'description', label: 'Description', type: 'text', value: setting.description, readonly: true },
          { name: 'setting_value', label: 'Value', type: setting.value_type === 'number' ? 'number' : 'text', value: setting.setting_value, required: true }
        ], 'Save Changes', (payload) => simpleAction('settings/update', payload, 'Setting updated successfully'));
      });
    });
  }
});

Views.register('governance.rejected_upload_cleanup', {
  async render({ params = {} }) {
    const data = await Api.get('upload/cleanup-list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'id', label: 'ID' },
      { key: 'belt_name', label: 'Belt' },
      { key: 'supervisor_name', label: 'Supervisor' },
      { key: 'rejection_reason', label: 'Reason' },
      { key: 'created_at', label: 'Created' }
    ];

    const actions = UI.button('Purge All Filtered', { icon: 'ph-trash', kind: 'btn-danger', attr: 'data-purge-all' });

    return UI.page('Rejected Uploads Cleanup', 'Manage and purge old rejected media', actions)
      + UI.panel('Filters', UI.filters([
        { name: 'date_from', label: 'From', type: 'date', value: params.date_from },
        { name: 'date_to', label: 'To', type: 'date', value: params.date_to }
      ], 'Apply'))
      + UI.panel('Records', UI.table(columns, rows, {
        empty: 'No rejected uploads found for cleanup',
        rowAttr: (row) => `data-upload-id="${row.id}"`
      }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('governance.rejected_upload_cleanup', payload));

    document.querySelector('[data-purge-all]')?.addEventListener('click', async () => {
      const rows = document.querySelectorAll('[data-upload-id]');
      const ids = Array.from(rows).map(row => parseInt(row.dataset.uploadId));
      
      if (ids.length === 0) {
        alert('No records to purge.');
        return;
      }

      if (confirm(`Are you sure you want to PERMANENTLY purge ${ids.length} rejected uploads? This cannot be undone.`)) {
        await simpleAction('upload/purge', { upload_ids: ids }, 'Uploads purged successfully');
        App.refresh();
      }
    });
  }
});

Views.register('green_belt.upload_review', {
  async render({ params = {} }) {
    const data = await Api.get('upload/list', params);
    const rows = normalizeItems(data);
    
    const columns = [
      { 
        key: 'thumbnail', 
        label: '<input type="checkbox" id="selectAllUploads">', 
        headerHtml: true,
        html: true, 
        render: (row) => {
          const disabled = isReviewableWorkUpload(row) ? '' : 'disabled';
          return `<input type="checkbox" class="upload-select" value="${row.id}" ${disabled}> <img src="${Api.url('upload/serve', { id: row.id })}" alt="Proof" class="photo-thumb photo-thumb-sm" data-gallery-id="${row.id}">`;
        }
      },
      { key: 'id', label: 'ID' },
      { key: 'created_at', label: 'Date/Time' },
      { key: 'parent_name', label: 'Belt/Site', render: (row) => row.parent_name || `#${row.parent_id}` },
      { key: 'created_by_user_name', label: 'Creator', render: (row) => row.created_by_user_name || 'System' },
      { key: 'upload_type', label: 'Type' },
      { key: 'authority_visibility', label: 'Visibility', html: true, render: (row) => UI.status(row.authority_visibility) },
      {
        key: 'actions',
        label: 'Actions',
        html: true,
        render: (row) => {
          if (!isReviewableWorkUpload(row)) return '<span style="opacity:0.5;font-size:0.8rem;">Not reviewable</span>';
          return `
            <button class="btn btn-sm btn-primary" data-approve="${row.id}">Approve</button>
            <button class="btn btn-sm btn-danger" data-reject="${row.id}">Reject</button>
          `;
        }
      }
    ];

    const actions = UI.button('Bulk Approve', { icon: 'ph-check-circle', kind: 'btn-primary', attr: 'data-bulk-approve' }) +
                    UI.button('Bulk Reject', { icon: 'ph-x-circle', kind: 'btn-danger', attr: 'data-bulk-reject' });

    return UI.page('Upload Review', 'Review and process field proofs', actions)
      + UI.panel('Filters', UI.filters([
        { name: 'date_from', label: 'From', type: 'date', value: params.date_from },
        { name: 'date_to', label: 'To', type: 'date', value: params.date_to },
        { name: 'upload_type', label: 'Type', type: 'select', value: params.upload_type || '', options: ['', 'WORK', 'ISSUE'] },
        { name: 'supervisor_user_id', label: 'Supervisor', type: 'select', options: [{ value: '', label: 'Loading...' }], value: params.supervisor_user_id || '' },
        { name: 'authority_visibility', label: 'Visibility', type: 'select', value: params.authority_visibility || 'PENDING', options: ['', 'PENDING', 'APPROVED', 'REJECTED', 'HIDDEN'] }
      ], 'Search'))
      + UI.panel('Review Queue', UI.table(columns, rows, {
        empty: 'No uploads found for review',
        rowAttr: (row) => `data-upload='${JSON.stringify(row).replace(/'/g, "&#39;")}'`
      }) + renderPagination(data.pagination, 'green_belt.upload_review', params));
  },
  async afterRender({ params = {} }) {
    attachRefresh();
    attachPagination();

    // Load supervisors dropdown
    const sups = await loadSupervisors();
    if (sups) {
      const select = document.querySelector('.js-filter-form [name="supervisor_user_id"]');
      if (select) {
        select.innerHTML = '<option value="">All Supervisors</option>' + sups.map(s => `<option value="${s.value}" ${String(s.value) === String(params.supervisor_user_id) ? 'selected' : ''}>${UI.escape(s.label)}</option>`).join('');
      }
    }

    wireFilters((payload) => App.navigate('green_belt.upload_review', payload));

    // Thumbnail click → shared gallery viewer with prev/next across all visible uploads.
    const uploadReviewItems = Array.from(document.querySelectorAll('[data-gallery-id]')).map((img) => {
      const row = img.closest('[data-upload]');
      const upload = row ? JSON.parse(row.dataset.upload) : {};
      return {
        id: parseInt(img.dataset.galleryId, 10),
        url: Api.url('upload/serve', { id: img.dataset.galleryId }),
        label: upload.photo_label || upload.work_type || '',
        time: upload.created_at || '',
        workType: upload.work_type || '',
        supervisor: upload.created_by_user_name || ''
      };
    });
    document.querySelectorAll('[data-gallery-id]').forEach((img) => {
      img.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = parseInt(img.dataset.galleryId, 10);
        const index = uploadReviewItems.findIndex((item) => item.id === id);
        openPhotoGallery(uploadReviewItems, index >= 0 ? index : 0);
      });
    });

    const selectAll = document.getElementById('selectAllUploads');
    selectAll?.addEventListener('change', (e) => {
      document.querySelectorAll('.upload-select:not(:disabled)').forEach(cb => cb.checked = e.target.checked);
    });

    document.querySelectorAll('[data-upload]').forEach(row => {
      row.addEventListener('click', (e) => {
        if (e.target.closest('button') || e.target.closest('input')) return;
        
        const upload = JSON.parse(row.dataset.upload);
        let actionButtons = '';
        
        if (isReviewableWorkUpload(upload)) {
          actionButtons = `
            <div class="modal-actions" style="margin-top: 1rem; border-top: 1px solid var(--line); padding-top: 1rem;">
              <button type="button" class="btn btn-primary" data-modal-approve="${upload.id}">Approve</button>
              <button type="button" class="btn btn-danger" data-modal-reject="${upload.id}">Reject</button>
            </div>
          `;
        }

        UI.showModal('Upload Detail', `
          <div class="stack-form">
            <div style="text-align: center; margin-bottom: 1rem;">
              <img src="${Api.url('upload/serve', { id: upload.id })}" alt="Proof" style="max-width: 100%; max-height: 50vh; border-radius: 4px;">
            </div>
            <div class="form-grid">
              <div class="field"><span>Type</span><input type="text" value="${UI.escape(`${upload.upload_type || ''} (${upload.work_type || 'N/A'})`)}" readonly></div>
              <div class="field"><span>Visibility</span><input type="text" value="${UI.escape(upload.authority_visibility || '')}" readonly></div>
              <div class="field"><span>Belt/Site</span><input type="text" value="${UI.escape(upload.parent_name || upload.parent_id || '')}" readonly></div>
              <div class="field"><span>Creator</span><input type="text" value="${UI.escape(upload.created_by_user_name || 'System')}" readonly></div>
              <div class="field full"><span>Comment</span><textarea readonly>${UI.escape(upload.comment_text || 'No comment')}</textarea></div>
            </div>
            ${actionButtons}
          </div>
        `);

        const modal = document.getElementById('modal-root');
        modal.querySelector('[data-modal-approve]')?.addEventListener('click', async () => {
          await simpleAction('upload/review', { upload_ids: [upload.id], decision: 'APPROVED' }, 'Upload approved');
          UI.closeModal();
          App.refresh();
        });

        modal.querySelector('[data-modal-reject]')?.addEventListener('click', () => {
          UI.closeModal();
          openSimpleForm('Reject Upload', [
            { name: 'decision', type: 'hidden', value: 'REJECTED' },
            { name: 'upload_ids_json', type: 'hidden', value: JSON.stringify([upload.id]) },
            { name: 'comment', label: 'Reason for Rejection', type: 'textarea', required: true }
          ], 'Confirm Rejection', (payload) => {
            payload.upload_ids = JSON.parse(payload.upload_ids_json);
            delete payload.upload_ids_json;
            return simpleAction('upload/review', payload, 'Upload rejected');
          });
        });
      });
    });

    document.querySelectorAll('[data-approve]').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        await simpleAction('upload/review', { upload_ids: [parseInt(btn.dataset.approve)], decision: 'APPROVED' }, 'Upload approved');
        App.refresh();
      });
    });

    document.querySelectorAll('[data-reject]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        openSimpleForm('Reject Upload', [
          { name: 'decision', type: 'hidden', value: 'REJECTED' },
          { name: 'upload_ids_json', type: 'hidden', value: JSON.stringify([parseInt(btn.dataset.reject)]) },
          { name: 'comment', label: 'Reason for Rejection', type: 'textarea', required: true }
        ], 'Confirm Rejection', (payload) => {
          payload.upload_ids = JSON.parse(payload.upload_ids_json);
          delete payload.upload_ids_json;
          return simpleAction('upload/review', payload, 'Upload rejected');
        });
      });
    });

    document.querySelector('[data-bulk-approve]')?.addEventListener('click', async () => {
      const selected = Array.from(document.querySelectorAll('.upload-select:checked:not(:disabled)')).map(cb => parseInt(cb.value));
      if (selected.length === 0) return alert('Select at least one upload.');
      if (confirm(`Approve ${selected.length} uploads?`)) {
        await simpleAction('upload/review', { upload_ids: selected, decision: 'APPROVED' }, `${selected.length} uploads approved`);
        App.refresh();
      }
    });

    document.querySelector('[data-bulk-reject]')?.addEventListener('click', () => {
      const selected = Array.from(document.querySelectorAll('.upload-select:checked:not(:disabled)')).map(cb => parseInt(cb.value));
      if (selected.length === 0) return alert('Select at least one upload.');
      openSimpleForm(`Reject ${selected.length} Uploads`, [
        { name: 'decision', type: 'hidden', value: 'REJECTED' },
        { name: 'upload_ids_json', type: 'hidden', value: JSON.stringify(selected) },
        { name: 'comment', label: 'Reason for Rejection', type: 'textarea', required: true }
      ], 'Confirm Rejection', (payload) => {
        payload.upload_ids = JSON.parse(payload.upload_ids_json);
        delete payload.upload_ids_json;
        return simpleAction('upload/review', payload, `${selected.length} uploads rejected`);
      });
    });
  }
});

Views.register('green_belt.issue_management', {
  async render({ params = {} }) {
    const data = await Api.get('issue/list', params);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'issue_code', label: 'Issue ID', render: (row) => row.issue_code || `IS-${String(row.id).padStart(5, '0')}` },
      { key: 'title', label: 'Title' },
      { key: 'priority', label: 'Priority', html: true, render: (row) => UI.status(row.priority) },
      { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.status) },
      { key: 'source_type', label: 'Source Type' },
      { key: 'belt_or_site_reference', label: 'Belt/Site Ref', render: (row) => row.belt_or_site_reference || '-' },
      { key: 'linked_task_id', label: 'Task ID', render: (row) => row.linked_task_id || '-' }
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'status', label: 'Status', type: 'select', value: params.status || '', options: ['', 'OPEN', 'IN_PROGRESS', 'CLOSED'] },
      { name: 'priority', label: 'Priority', type: 'select', value: params.priority || '', options: ['', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'] },
      { name: 'source_type', label: 'Source Type', type: 'select', value: params.source_type || '', options: ['', 'UPLOAD', 'DIRECT'] },
      { name: 'belt_id', label: 'Belt ID', type: 'number', value: params.belt_id || '' },
      { name: 'site_id', label: 'Site ID', type: 'number', value: params.site_id || '' }
    ], 'Apply'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' });

    return UI.page('Issue Management', 'Manage operational issues and task links', actions)
      + filterUI
      + UI.panel('Records', UI.table(columns, rows, {
        empty: 'No issues found',
        rowAttr: (row) => `data-issue-id="${row.id}"`
      }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('green_belt.issue_management', payload));

    document.querySelectorAll('[data-issue-id]').forEach(row => {
      row.addEventListener('click', async () => {
        const issueId = row.dataset.issueId;
        const issue = await Api.get('issue/get', { issue_id: issueId });
        if (!issue) return UI.toast('Issue not found', 'bad');

        const isOps = Auth.getUser()?.role_key === 'OPS_MANAGER';
        const isHeadSuper = Auth.getUser()?.role_key === 'HEAD_SUPERVISOR';
        
        let actionsHtml = '';
        if (issue.status === 'OPEN' && (isOps || isHeadSuper)) {
            actionsHtml += `<button type="button" class="btn btn-primary" data-in-progress="${issue.id}">Mark In Progress</button> `;
        }
        if (issue.status !== 'CLOSED' && isOps) {
            actionsHtml += `<button type="button" class="btn btn-danger" data-close="${issue.id}">Close Issue</button> `;
        }
        if (isOps) {
            actionsHtml += `<button type="button" class="btn btn-ghost" data-link-task="${issue.id}">Link Task</button> `;
            actionsHtml += `<button type="button" class="btn btn-ghost" data-create-task="${issue.id}">Create Task</button>`;
        }

        const actionPanel = actionsHtml ? `<div class="modal-actions" style="margin-top: 1rem; border-top: 1px solid var(--line); padding-top: 1rem;">${actionsHtml}</div>` : '';

        UI.showModal('Issue Detail', `
          <div class="stack-form">
            <div class="form-grid">
              <div class="field"><span>Issue ID</span><input type="text" value="${UI.escape(issue.issue_code || `IS-${String(issue.id).padStart(5, '0')}`)}" readonly></div>
              <div class="field"><span>Title</span><input type="text" value="${UI.escape(issue.title)}" readonly></div>
              <div class="field"><span>Status</span><input type="text" value="${issue.status}" readonly></div>
              <div class="field"><span>Priority</span><input type="text" value="${issue.priority}" readonly></div>
              <div class="field"><span>Source Type</span><input type="text" value="${issue.source_type}" readonly></div>
              <div class="field"><span>Source Ref ID</span><input type="text" value="${issue.source_reference_id || '-'}" readonly></div>
              <div class="field"><span>Belt ID</span><input type="text" value="${issue.belt_id || '-'}" readonly></div>
              <div class="field"><span>Site ID</span><input type="text" value="${issue.site_id || '-'}" readonly></div>
              <div class="field"><span>Linked Task ID</span><input type="text" value="${issue.linked_task_id || '-'}" readonly></div>
              <div class="field full"><span>Description</span><textarea readonly>${UI.escape(issue.description || '')}</textarea></div>
            </div>
            ${actionPanel}
          </div>
        `);

        const modal = document.getElementById('modal-root');

        modal.querySelector('[data-in-progress]')?.addEventListener('click', async () => {
            await simpleAction('issue/in-progress', { issue_id: issue.id }, 'Issue marked in progress');
        });

        modal.querySelector('[data-close]')?.addEventListener('click', () => {
            UI.closeModal();
            openSimpleForm('Close Issue', [
                { name: 'issue_id', type: 'hidden', value: issue.id }
            ], 'Confirm Close', (payload) => simpleAction('issue/close', payload, 'Issue closed'));
        });

        modal.querySelector('[data-link-task]')?.addEventListener('click', () => {
            UI.closeModal();
            openSimpleForm('Link Task', [
                { name: 'issue_id', type: 'hidden', value: issue.id },
                { name: 'task_id', label: 'Task ID', type: 'number', required: true }
            ], 'Link', (payload) => simpleAction('issue/link-task', payload, 'Task linked to issue'));
        });

        modal.querySelector('[data-create-task]')?.addEventListener('click', () => {
            UI.closeModal();
            openSimpleForm('Create & Link Task', [
                { name: 'task_category', label: 'Category', type: 'select', options: ['SITE_REPAIR', 'GENERAL', 'CLIENT_CAMPAIGN'], value: 'SITE_REPAIR', required: true },
                { name: 'vertical_type', label: 'Vertical', type: 'select', options: ['GREEN_BELT', 'ADVERTISEMENT', 'MONITORING'], value: issue.belt_id ? 'GREEN_BELT' : 'ADVERTISEMENT', required: true },
                { name: 'priority', label: 'Priority', type: 'select', options: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], value: issue.priority, required: true },
                { name: 'work_description', label: 'Work Description', type: 'textarea', value: `Fix issue: ${issue.title}`, required: true },
                { name: 'location_text', label: 'Location', type: 'text', value: issue.belt_id ? `Belt #${issue.belt_id}` : (issue.site_id ? `Site #${issue.site_id}` : ''), required: true },
                { name: 'assigned_lead_user_id', label: 'Assigned Lead User ID', type: 'number' },
                { name: 'start_date', label: 'Start Date', type: 'date', value: UI.currentDate() },
                { name: 'expected_close_date', label: 'Expected Close', type: 'date' }
            ], 'Create Task', async (payload) => {
                const taskResult = await Api.post('task/create', payload);
                const newTaskId = typeof taskResult === 'object' ? (taskResult.id || taskResult.task_id) : taskResult;
                await Api.post('issue/link-task', { issue_id: issue.id, task_id: newTaskId });
                UI.closeModal();
                UI.toast('Task created and linked', 'good');
                App.refresh();
            });
        });
      });
    });
  }
});

const AUTHORITY_VIEW_BUNDLE_CAP = 50;

const AUTHORITY_WORK_TYPES = [
  { value: '', label: 'All work types' },
  { value: 'ROUTINE_MAINTENANCE', label: 'Routine maintenance' },
  { value: 'REPAIR', label: 'Repair' },
  { value: 'PLANTING', label: 'Planting' },
  { value: 'WATERING', label: 'Watering' },
  { value: 'CLEANING', label: 'Cleaning' }
];

const AUTHORITY_GROUP_BY = [
  { value: 'date', label: 'Date' },
  { value: 'belt', label: 'Belt' },
  { value: 'work_type', label: 'Work Type' }
];

function authorityLocalDate(date = new Date()) {
  const offset = date.getTimezoneOffset();
  return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 10);
}

function authorityShiftDate(days) {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return authorityLocalDate(date);
}

function authorityMonthStart() {
  const date = new Date();
  date.setDate(1);
  return authorityLocalDate(date);
}

function authorityNormalizeParams(params = {}) {
  const next = { ...params };
  if (next.date && !next.date_from && !next.date_to) {
    next.date_from = next.date;
    next.date_to = next.date;
  }
  delete next.date;
  if (!next.date_from && !next.date_to) {
    const today = authorityLocalDate();
    next.date_from = today;
    next.date_to = today;
  }
  return next;
}

function authorityDateRangeDays(dateFrom, dateTo) {
  if (!dateFrom || !dateTo) return 0;
  const start = new Date(`${dateFrom}T00:00:00`);
  const end = new Date(`${dateTo}T00:00:00`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 0;
  return Math.floor((end - start) / 86400000) + 1;
}

function authorityHumanDate(value) {
  if (!value) return '';
  const parts = String(value).split('-');
  if (parts.length !== 3) return value;
  return `${parts[2]}-${parts[1]}-${parts[0]}`;
}

function authorityFormatBytes(bytes) {
  const size = Number(bytes || 0);
  if (size <= 0) return 'size unknown';
  if (size < 1024 * 1024) return `${Math.max(1, Math.round(size / 1024))} KB`;
  return `${(size / (1024 * 1024)).toFixed(size >= 10 * 1024 * 1024 ? 0 : 1)} MB`;
}

function authorityWorkTypeLabel(value) {
  return AUTHORITY_WORK_TYPES.find((item) => item.value === value)?.label || value || '';
}

function authoritySafeFilenamePart(value) {
  return String(value || '')
    .trim()
    .replace(/[^a-z0-9_-]+/gi, '-')
    .replace(/^-+|-+$/g, '')
    .toLowerCase();
}

function authorityZipName(params, beltOptions, mode) {
  const parts = ['authority-proof'];
  const selectedBelt = beltOptions.find((belt) => String(belt.id) === String(params.belt_id || ''));
  if (selectedBelt?.belt_code) parts.push(selectedBelt.belt_code);
  if (params.date_from || params.date_to) {
    parts.push(`${params.date_from || 'start'}-to-${params.date_to || 'today'}`);
  }
  if (params.work_type) parts.push(params.work_type);
  parts.push(mode);
  return `${parts.map(authoritySafeFilenamePart).filter(Boolean).join('_')}.zip`;
}

function authorityActiveFilterChips(params, beltOptions) {
  const chips = [];
  const selectedBelt = beltOptions.find((belt) => String(belt.id) === String(params.belt_id || ''));
  if (selectedBelt) chips.push({ label: selectedBelt.label, keys: ['belt_id'] });
  if (params.date_from || params.date_to) {
    chips.push({ label: `Range: ${authorityHumanDate(params.date_from) || 'Start'} to ${authorityHumanDate(params.date_to) || 'Today'}`, keys: ['date_from', 'date_to'] });
  }
  if (params.work_type) chips.push({ label: `Work: ${authorityWorkTypeLabel(params.work_type)}`, keys: ['work_type'] });
  if (!chips.length) return '<div class="av-filter-chips av-filter-chips-empty">Showing all approved proof photos in your authority scope.</div>';
  return `<div class="av-filter-chips">${chips.map((chip) => `
    <button type="button" class="av-filter-chip" data-av-clear="${UI.escape(chip.keys.join(','))}">
      <span>${UI.escape(chip.label)}</span><i class="ph ph-x"></i>
    </button>
  `).join('')}</div>`;
}

function authorityFormatLabel(row, groupKey) {
  if (groupKey === 'date') return (row.timestamp || '').substring(0, 10) || 'Unknown date';
  if (groupKey === 'belt') return row.belt_code ? `${row.belt_code} — ${row.belt_common_name || ''}` : 'Unassigned belt';
  if (groupKey === 'work_type') return row.work_type || 'UNKNOWN';
  return 'Group';
}

function authorityGroupItems(items, groupKey) {
  const groups = new Map();
  for (const item of items) {
    const label = authorityFormatLabel(item, groupKey);
    if (!groups.has(label)) groups.set(label, []);
    groups.get(label).push(item);
  }
  return Array.from(groups.entries()).map(([label, rows]) => ({ label, rows }));
}

async function authorityDownloadSingle(uploadId) {
  const url = Api.url('upload/serve', { id: uploadId, download: 1 });
  const link = document.createElement('a');
  link.href = url;
  link.rel = 'noopener';
  link.download = '';
  document.body.appendChild(link);
  link.click();
  link.remove();
}

async function authorityDownloadBundle(uploadIds, zipName) {
  if (!uploadIds.length) return;
  if (uploadIds.length > AUTHORITY_VIEW_BUNDLE_CAP) {
    UI.toast(`Too many photos selected (${uploadIds.length}). Limit is ${AUTHORITY_VIEW_BUNDLE_CAP}.`, 'bad');
    return;
  }
  if (typeof JSZip !== 'function' && typeof window.JSZip !== 'function') {
    UI.toast('JSZip library failed to load — single download only', 'bad');
    return;
  }
  const ZipCtor = window.JSZip || JSZip;
  const zip = new ZipCtor();
  UI.toast(`Preparing ${uploadIds.length} photos…`);
  for (const id of uploadIds) {
    try {
      const resp = await fetch(Api.url('upload/serve', { id, download: 1 }), { credentials: 'include' });
      if (!resp.ok) {
        console.warn(`upload ${id} fetch failed`, resp.status);
        continue;
      }
      const blob = await resp.blob();
      const disposition = resp.headers.get('Content-Disposition') || '';
      const match = disposition.match(/filename="?([^";]+)"?/i);
      const filename = (match && match[1]) ? match[1] : `photo-${id}.jpg`;
      zip.file(filename, blob);
    } catch (err) {
      console.warn(`upload ${id} fetch threw`, err);
    }
  }
  const archive = await zip.generateAsync({ type: 'blob' });
  const url = URL.createObjectURL(archive);
  const link = document.createElement('a');
  link.href = url;
  link.download = zipName;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1500);
  UI.toast(`Downloaded ${uploadIds.length} photos`, 'good');
}

/**
 * openPhotoGallery — universal photo preview modal.
 *
 * Opens a full-screen modal with the photo, optional metadata, Prev/Next
 * navigation, keyboard shortcuts (← → Esc D), and mobile swipe gestures.
 * Can be used by any page that displays upload thumbnails.
 *
 * @param {Array}  items       Array of photo descriptors:
 *   { id, url, [belt], [time], [workType], [supervisor], [label], [size] }
 * @param {number} startIndex  Index within items to open first (default 0)
 */
function openPhotoGallery(items, startIndex = 0) {
  if (!items || !items.length) return;

  let _keyHandler = null;
  let _touchStart = null;
  let _swipeTarget = null;
  let _touchStartHandler = null;
  let _touchEndHandler = null;

  const _detachKeys = () => {
    if (_keyHandler) { document.removeEventListener('keydown', _keyHandler); _keyHandler = null; }
  };

  const _detachSwipe = () => {
    if (_swipeTarget) {
      if (_touchStartHandler) _swipeTarget.removeEventListener('touchstart', _touchStartHandler);
      if (_touchEndHandler) _swipeTarget.removeEventListener('touchend', _touchEndHandler);
    }
    _swipeTarget = null; _touchStartHandler = null; _touchEndHandler = null; _touchStart = null;
  };

  const _open = (index) => {
    const item = items[index];
    if (!item) return;

    const counter = items.length > 1 ? `${index + 1} of ${items.length}` : '';
    const metaRows = [
      counter ? `<div><span>Photo</span><strong>${UI.escape(counter)}</strong></div>` : '',
      item.belt ? `<div><span>Belt</span><strong>${UI.escape(item.belt)}</strong></div>` : '',
      item.time ? `<div><span>Date / time</span><strong>${UI.escape(item.time)}</strong></div>` : '',
      item.label ? `<div><span>Label</span><strong>${UI.escape(item.label)}</strong></div>` : '',
      item.workType ? `<div><span>Work type</span><strong>${UI.escape(item.workType)}</strong></div>` : '',
      item.supervisor ? `<div><span>Supervisor</span><strong>${UI.escape(item.supervisor)}</strong></div>` : '',
      item.size ? `<div><span>File size</span><strong>${UI.escape(authorityFormatBytes(item.size))}</strong></div>` : '',
    ].filter(Boolean).join('');

    const hasPrev = index > 0;
    const hasNext = index < items.length - 1;

    UI.showModal('Photo Details', `
      <div class="av-preview-shell">
        <div class="av-preview-media">
          <img src="${UI.escape(item.url)}" alt="Photo ${UI.escape(String(item.id))}">
        </div>
        ${metaRows ? `<div class="av-preview-meta">${metaRows}</div>` : ''}
      </div>
      <div class="av-preview-actions">
        ${items.length > 1 ? `
          <button type="button" class="btn btn-ghost js-gallery-prev" ${hasPrev ? '' : 'disabled'}><i class="ph ph-caret-left"></i><span>Previous</span></button>
          <button type="button" class="btn btn-ghost js-gallery-next" ${hasNext ? '' : 'disabled'}><span>Next</span><i class="ph ph-caret-right"></i></button>
        ` : ''}
        <button type="button" class="btn btn-primary js-gallery-download"><i class="ph ph-download-simple"></i><span>Download</span></button>
        <button type="button" class="btn btn-ghost" data-modal-close>Close</button>
      </div>
      ${items.length > 1 ? `
        <p class="av-preview-hint">
          <span class="av-preview-hint-keys"><kbd>←</kbd> <kbd>→</kbd> navigate · <kbd>Esc</kbd> close · <kbd>D</kbd> download</span>
          <span class="av-preview-hint-touch">Swipe left or right to navigate</span>
        </p>
      ` : ''}
    `);

    document.querySelector('.js-gallery-download')?.addEventListener('click', () => authorityDownloadSingle(item.id));
    document.querySelector('.js-gallery-prev')?.addEventListener('click', () => _open(index - 1));
    document.querySelector('.js-gallery-next')?.addEventListener('click', () => _open(index + 1));

    // Keyboard shortcuts
    _detachKeys();
    _keyHandler = (e) => {
      if (!document.querySelector('.av-preview-shell')) { _detachKeys(); return; }
      if (e.key === 'ArrowLeft' && hasPrev) { e.preventDefault(); _open(index - 1); }
      else if (e.key === 'ArrowRight' && hasNext) { e.preventDefault(); _open(index + 1); }
      else if (e.key === 'Escape') { e.preventDefault(); _detachKeys(); _detachSwipe(); UI.closeModal(); }
      else if (e.key === 'd' || e.key === 'D') { e.preventDefault(); authorityDownloadSingle(item.id); }
    };
    document.addEventListener('keydown', _keyHandler);

    // Swipe gestures (mobile)
    _detachSwipe();
    _swipeTarget = document.querySelector('.av-preview-media');
    if (_swipeTarget && items.length > 1) {
      _touchStartHandler = (e) => {
        if (e.touches && e.touches.length === 1) {
          _touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
        }
      };
      _touchEndHandler = (e) => {
        if (!_touchStart || !e.changedTouches || e.changedTouches.length !== 1) { _touchStart = null; return; }
        const dx = e.changedTouches[0].clientX - _touchStart.x;
        const dy = e.changedTouches[0].clientY - _touchStart.y;
        _touchStart = null;
        if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy) * 1.4) return;
        if (dx < 0 && hasNext) _open(index + 1);
        else if (dx > 0 && hasPrev) _open(index - 1);
      };
      _swipeTarget.addEventListener('touchstart', _touchStartHandler, { passive: true });
      _swipeTarget.addEventListener('touchend', _touchEndHandler, { passive: true });
    }

    // Detach listeners when modal closes
    const modalRoot = document.getElementById('modal-root');
    if (modalRoot) {
      const obs = new MutationObserver(() => {
        if (!document.querySelector('.av-preview-shell')) { _detachKeys(); _detachSwipe(); obs.disconnect(); }
      });
      obs.observe(modalRoot, { childList: true, subtree: true });
    }
  };

  _open(startIndex);
}

Views.register('green_belt.authority_view', {
  async render({ params = {} }) {
    const effectiveParams = authorityNormalizeParams(params);
    const groupBy = effectiveParams.group_by || 'date';
    const loadedLimit = Math.max(AUTHORITY_VIEW_BUNDLE_CAP, parseInt(effectiveParams.loaded || effectiveParams.limit || AUTHORITY_VIEW_BUNDLE_CAP, 10) || AUTHORITY_VIEW_BUNDLE_CAP);
    let beltOptions = [];
    try {
      // Pass current filter context so the dropdown labels show counts that
      // match what the gallery will display for each belt.
      const beltLookupParams = {};
      if (effectiveParams.date_from) beltLookupParams.date_from = effectiveParams.date_from;
      if (effectiveParams.date_to) beltLookupParams.date_to = effectiveParams.date_to;
      if (effectiveParams.work_type) beltLookupParams.work_type = effectiveParams.work_type;
      const beltResp = await Api.get('authority/belt-options', beltLookupParams);
      beltOptions = (beltResp && beltResp.items) ? beltResp.items : [];
    } catch (err) {
      // Ops-equivalent role: belt-options refuses; fall back to empty dropdown.
    }

    const apiParams = { ...effectiveParams, page: 1, limit: loadedLimit };
    delete apiParams.group_by;
    delete apiParams.loaded;

    const summary = await Api.get('authority/summary', apiParams);
    const data = await Api.get('authority/view', apiParams);
    const rows = normalizeItems(data).sort((a, b) => String(b.timestamp || '').localeCompare(String(a.timestamp || '')));
    const totalFiltered = (data && data.pagination && data.pagination.total) ? data.pagination.total : rows.length;
    const hasMore = rows.length < totalFiltered;

    const beltOptionHtml = ['<option value="">All assigned belts</option>']
      .concat(beltOptions.map((b) => {
        const selected = String(b.id) === String(effectiveParams.belt_id || '') ? ' selected' : '';
        return `<option value="${UI.escape(String(b.id))}"${selected}>${UI.escape(b.label)}</option>`;
      }))
      .join('');

    const workTypeOptionHtml = AUTHORITY_WORK_TYPES.map((wt) => {
      const selected = wt.value === (effectiveParams.work_type || '') ? ' selected' : '';
      return `<option value="${UI.escape(wt.value)}"${selected}>${UI.escape(wt.label)}</option>`;
    }).join('');

    const groupByOptionHtml = AUTHORITY_GROUP_BY.map((g) => {
      const selected = g.value === groupBy ? ' selected' : '';
      return `<option value="${UI.escape(g.value)}"${selected}>${UI.escape(g.label)}</option>`;
    }).join('');

    // Collapsible Filters panel. Default state is collapsed so the gallery
    // gets full focus; user clicks the chevron to expand and edit. The chips
    // showing the active filter stay visible even when collapsed so the AR
    // always knows what range/belt is being shown.
    // Filter panel body (collapses) + chips (always visible via UI.panel alwaysVisible slot)
    const filterBody = `
      <form class="filter-grid js-filter-form av-filter-grid">
        <label class="field">
          <span>Belt</span>
          <select name="belt_id">${beltOptionHtml}</select>
        </label>
        <label class="field">
          <span>From</span>
          <input type="date" name="date_from" value="${UI.escape(effectiveParams.date_from || '')}">
        </label>
        <label class="field">
          <span>To</span>
          <input type="date" name="date_to" value="${UI.escape(effectiveParams.date_to || '')}">
        </label>
        <label class="field">
          <span>Work type</span>
          <select name="work_type">${workTypeOptionHtml}</select>
        </label>
        <label class="field">
          <span>Group by</span>
          <select name="group_by">${groupByOptionHtml}</select>
        </label>
        <button type="submit" class="btn btn-primary"><i class="ph ph-funnel"></i><span>Apply Filters</span></button>
      </form>
      <div class="av-date-presets" aria-label="Date shortcuts">
        <button type="button" class="btn btn-ghost btn-sm" data-av-preset="today">Today</button>
        <button type="button" class="btn btn-ghost btn-sm" data-av-preset="yesterday">Yesterday</button>
        <button type="button" class="btn btn-ghost btn-sm" data-av-preset="last7">Last 7 Days</button>
        <button type="button" class="btn btn-ghost btn-sm" data-av-preset="month">This Month</button>
      </div>
      <p class="av-filter-note">Defaults to today's approved photos to keep the page quick. Wider ranges load ${AUTHORITY_VIEW_BUNDLE_CAP} photos first, then you can load more.</p>
      ${authorityDateRangeDays(effectiveParams.date_from, effectiveParams.date_to) > 7 ? '<p class="av-range-warning"><i class="ph ph-warning-circle"></i><span>This is a broad date range, so photos load 50 at a time to save mobile data and keep downloads safe.</span></p>' : ''}
    `;

    // Build filter panel using universal UI.panel collapsible option.
    // Chips go in alwaysVisible so they show even when collapsed.
    const filterPanelHtml = UI.panel('Filters', filterBody, '', {
      collapsible: true,
      defaultOpen: false,
      alwaysVisible: authorityActiveFilterChips(effectiveParams, beltOptions)
    });

    // Summary uses universal UI.statGrid
    const summaryHtml = UI.statGrid([
      { label: 'Active belts',   value: summary.total_belts },
      { label: 'Morning photos', value: summary.total_morning_photos },
      { label: 'Evening photos', value: summary.total_evening_photos },
      { label: 'Total photos',   value: summary.total_photos }
    ]);

    let galleryHtml = '';
    if (!rows.length) {
      galleryHtml = `
        <div class="av-empty">
          <div class="av-empty-title">No approved photos found for this filter.</div>
          <p>Try This Month, choose All assigned belts, or clear Work Type.</p>
        </div>
      `;
    } else {
      const groups = authorityGroupItems(rows, groupBy);
      const groupHtml = groups.map((group) => {
        const cards = group.rows.map((row) => {
          const photoUrl = Api.url('upload/serve', { id: row.upload_id });
          const ts = row.timestamp || '';
          const dateLabel = ts.substring(0, 10);
          const timeOnly = ts.length >= 16 ? ts.substring(11, 16) : '';
          const humanDate = authorityHumanDate(dateLabel);
          const beltLabel = row.belt_code ? `${UI.escape(row.belt_code)} — ${UI.escape(row.belt_common_name || '')}` : 'Unassigned belt';
          const workLabel = UI.escape(row.work_type || 'UNKNOWN');
          const supervisorName = UI.escape(row.supervisor_name || '—');
          // data-time stores full timestamp for preview modal; card display uses dateLabel + timeOnly separately
          const fullTime = ts.replace('T', ' ').substring(0, 16);
          return `
            <article class="av-card" data-upload-id="${row.upload_id}" data-size="${row.file_size_bytes || 0}" data-photo-url="${photoUrl}" data-belt="${beltLabel}" data-work-type="${workLabel}" data-time="${UI.escape(fullTime)}" data-date="${UI.escape(dateLabel)}" data-supervisor="${UI.escape(row.supervisor_name || '')}">
              <div class="av-selected-badge"><i class="ph ph-check"></i></div>
              <label class="av-card-select">
                <input type="checkbox" class="js-av-check" data-upload-id="${row.upload_id}" aria-label="Select photo ${row.upload_id}">
              </label>
              <button type="button" class="av-card-photo" data-preview-id="${row.upload_id}" aria-label="Open photo ${row.upload_id}">
                <img src="${photoUrl}" alt="Proof ${row.upload_id}" loading="lazy">
              </button>
              <div class="av-card-meta">
                <div class="av-card-belt-row"><span class="av-meta-belt">${beltLabel}</span></div>
                <div class="av-card-row"><span class="av-meta-date">${UI.escape(humanDate)}</span><span class="av-meta-time">${UI.escape(timeOnly)}</span></div>
                <div class="av-card-row av-card-row-supervisor"><span class="av-meta-supervisor">${supervisorName}</span><span class="av-card-type av-meta-worktype">${workLabel}</span></div>
              </div>
              <div class="av-card-actions">
                <button type="button" class="btn btn-ghost btn-sm js-av-download-single" data-upload-id="${row.upload_id}" aria-label="Download photo ${row.upload_id}">
                  <i class="ph ph-download-simple"></i><span>Download</span>
                </button>
              </div>
            </article>
          `;
        }).join('');
        return `
          <section class="av-group">
            <header class="av-group-header">
              <div class="av-group-title"><span>${UI.escape(group.label)}</span><span class="av-group-count">${group.rows.length}</span></div>
              <label class="av-group-select"><input type="checkbox" class="js-av-group-check" data-group="${UI.escape(group.label)}"><span>Select group</span></label>
            </header>
            <div class="av-card-grid">${cards}</div>
          </section>
        `;
      }).join('');
      galleryHtml = `
        <div class="av-gallery-toolbar">
          <div><strong>Showing ${rows.length} of ${totalFiltered}</strong> approved photos</div>
          <button type="button" class="btn btn-ghost btn-sm js-av-select-page"><i class="ph ph-check-square"></i><span>Select all on page</span></button>
        </div>
        ${groupHtml}
      `;
    }

    const loadMoreHtml = hasMore ? `
      <div class="av-load-more-wrap">
        <p>Showing ${rows.length} of ${totalFiltered} approved photos. Load the next ${Math.min(AUTHORITY_VIEW_BUNDLE_CAP, totalFiltered - rows.length)} to view more.</p>
        <button type="button" class="btn btn-primary js-av-load-more" data-next-loaded="${Math.min(rows.length + AUTHORITY_VIEW_BUNDLE_CAP, totalFiltered)}">
          <i class="ph ph-plus-circle"></i><span>Load 50 More</span>
        </button>
      </div>
    ` : (rows.length ? `<div class="av-load-more-wrap av-load-more-done"><p>Showing all ${totalFiltered} approved photos for these filters.</p></div>` : '');

    const selectedZipName = authorityZipName(effectiveParams, beltOptions, 'selected');
    const loadedZipName = authorityZipName(effectiveParams, beltOptions, 'loaded');
    const bulkBar = `
      <div class="av-bulk-bar js-av-bulk-bar" hidden>
        <span class="av-bulk-count"><span class="js-av-bulk-count">0</span> selected</span>
        <span class="av-bulk-meta js-av-bulk-meta">0 KB estimated</span>
        <div class="av-bulk-spacer"></div>
        <button type="button" class="btn btn-ghost btn-sm js-av-bulk-clear">Clear</button>
        <button type="button" class="btn btn-primary btn-sm js-av-bulk-download" data-zip-name="${UI.escape(selectedZipName)}"><i class="ph ph-download-simple"></i><span>Download Selected</span></button>
      </div>
    `;

    const pageActions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' });
    const workPhotosActions =
      `<button type="button" class="btn btn-primary btn-sm av-download-loaded js-av-download-filtered" data-zip-name="${UI.escape(loadedZipName)}" ${rows.length ? '' : 'disabled'}><i class="ph ph-download-simple"></i><span>Download Loaded Photos (${rows.length})</span></button>`;

    return UI.page('Daily Work and Maintenance Photos', 'filter, browse, download', pageActions)
      + filterPanelHtml
      + UI.panel('Summary statistics', summaryHtml)
      + UI.panel('Work photos', galleryHtml, workPhotosActions)
      + loadMoreHtml
      + bulkBar;
  },
  async afterRender({ params = {} }) {
    attachRefresh();
    const effectiveParams = authorityNormalizeParams(params);
    // Filter panel collapse/expand is now handled by the global delegation in app.js.

    document.querySelector('.js-filter-form')?.addEventListener('submit', (event) => {
      event.preventDefault();
      const payload = UI.formData(event.currentTarget);
      delete payload.date;
      delete payload.page;
      delete payload.loaded;
      Object.keys(payload).forEach((key) => {
        if (payload[key] === '' || payload[key] == null) delete payload[key];
      });
      // Auto-swap reversed date range so the AR never gets silent empty results.
      if (payload.date_from && payload.date_to && payload.date_from > payload.date_to) {
        const swap = payload.date_from;
        payload.date_from = payload.date_to;
        payload.date_to = swap;
        UI.toast('Date range adjusted — From was after To', '');
      }
      App.navigate('green_belt.authority_view', payload);
    });

    const bulkBar = document.querySelector('.js-av-bulk-bar');
    const bulkCount = document.querySelector('.js-av-bulk-count');
    const bulkMeta = document.querySelector('.js-av-bulk-meta');
    const selectedIds = new Set();
    const selectedSizes = new Map();

    document.querySelectorAll('.av-card').forEach((card) => {
      selectedSizes.set(parseInt(card.dataset.uploadId, 10), Number(card.dataset.size || 0));
    });

    const refreshBulkBar = () => {
      if (!bulkBar) return;
      if (selectedIds.size === 0) {
        bulkBar.setAttribute('hidden', '');
      } else {
        bulkBar.removeAttribute('hidden');
        if (bulkCount) bulkCount.textContent = String(selectedIds.size);
        if (bulkMeta) {
          const bytes = Array.from(selectedIds).reduce((sum, id) => sum + (selectedSizes.get(id) || 0), 0);
          bulkMeta.textContent = `${authorityFormatBytes(bytes)} estimated`;
        }
      }
    };

    document.querySelectorAll('[data-av-preset]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const next = { ...effectiveParams };
        delete next.page;
        delete next.loaded;
        delete next.date;
        if (btn.dataset.avPreset === 'today') {
          next.date_from = authorityLocalDate();
          next.date_to = authorityLocalDate();
        } else if (btn.dataset.avPreset === 'yesterday') {
          const yesterday = authorityShiftDate(-1);
          next.date_from = yesterday;
          next.date_to = yesterday;
        } else if (btn.dataset.avPreset === 'last7') {
          next.date_from = authorityShiftDate(-6);
          next.date_to = authorityLocalDate();
        } else if (btn.dataset.avPreset === 'month') {
          next.date_from = authorityMonthStart();
          next.date_to = authorityLocalDate();
        }
        App.navigate('green_belt.authority_view', next);
      });
    });

    document.querySelectorAll('[data-av-clear]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const next = { ...effectiveParams };
        btn.dataset.avClear.split(',').forEach((key) => delete next[key]);
        delete next.page;
        delete next.loaded;
        App.navigate('green_belt.authority_view', next);
      });
    });

    document.querySelector('.js-av-load-more')?.addEventListener('click', (event) => {
      const next = { ...effectiveParams };
      delete next.page;
      next.loaded = event.currentTarget.dataset.nextLoaded || String(AUTHORITY_VIEW_BUNDLE_CAP);
      App.navigate('green_belt.authority_view', next);
    });

    document.querySelectorAll('.js-av-check').forEach((box) => {
      box.addEventListener('change', () => {
        const id = parseInt(box.dataset.uploadId, 10);
        if (box.checked) selectedIds.add(id); else selectedIds.delete(id);
        box.closest('.av-card')?.classList.toggle('av-card-selected', box.checked);
        refreshBulkBar();
      });
    });

    document.querySelectorAll('.js-av-group-check').forEach((groupBox) => {
      groupBox.addEventListener('change', () => {
        const section = groupBox.closest('.av-group');
        section?.querySelectorAll('.js-av-check').forEach((cb) => {
          cb.checked = groupBox.checked;
          const id = parseInt(cb.dataset.uploadId, 10);
          if (groupBox.checked) selectedIds.add(id); else selectedIds.delete(id);
          cb.closest('.av-card')?.classList.toggle('av-card-selected', groupBox.checked);
        });
        refreshBulkBar();
      });
    });

    document.querySelector('.js-av-select-page')?.addEventListener('click', () => {
      document.querySelectorAll('.js-av-check').forEach((cb) => {
        cb.checked = true;
        const id = parseInt(cb.dataset.uploadId, 10);
        selectedIds.add(id);
        cb.closest('.av-card')?.classList.add('av-card-selected');
      });
      document.querySelectorAll('.js-av-group-check').forEach((cb) => { cb.checked = true; });
      refreshBulkBar();
    });

    document.querySelector('.js-av-bulk-clear')?.addEventListener('click', () => {
      selectedIds.clear();
      document.querySelectorAll('.js-av-check').forEach((cb) => { cb.checked = false; cb.closest('.av-card')?.classList.remove('av-card-selected'); });
      document.querySelectorAll('.js-av-group-check').forEach((cb) => { cb.checked = false; });
      refreshBulkBar();
    });

    document.querySelector('.js-av-bulk-download')?.addEventListener('click', async () => {
      const ids = Array.from(selectedIds);
      if (!ids.length) return;
      const zipName = document.querySelector('.js-av-bulk-download')?.dataset.zipName || `authority-selected-${Date.now()}.zip`;
      await authorityDownloadBundle(ids, zipName);
    });

    document.querySelectorAll('.js-av-download-single').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        authorityDownloadSingle(parseInt(btn.dataset.uploadId, 10));
      });
    });

    document.querySelector('.js-av-download-filtered')?.addEventListener('click', async () => {
      const ids = Array.from(document.querySelectorAll('.js-av-check')).map((cb) => parseInt(cb.dataset.uploadId, 10));
      if (!ids.length) return;
      const zipName = document.querySelector('.js-av-download-filtered')?.dataset.zipName || `authority-filtered-${Date.now()}.zip`;
      await authorityDownloadBundle(ids, zipName);
    });

    // Build items array for the shared gallery viewer from card data attributes.
    const previewItems = Array.from(document.querySelectorAll('.av-card')).map((card) => ({
      id: parseInt(card.dataset.uploadId, 10),
      url: card.dataset.photoUrl,
      belt: card.dataset.belt || '',
      workType: card.dataset.workType || '',
      time: card.dataset.time || '',
      supervisor: card.dataset.supervisor || '',
      size: Number(card.dataset.size || 0)
    }));

    // Use shared openPhotoGallery — no per-page preview logic needed.
    document.querySelectorAll('[data-preview-id]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = parseInt(btn.dataset.previewId, 10);
        const index = previewItems.findIndex((item) => item.id === id);
        openPhotoGallery(previewItems, index >= 0 ? index : 0);
      });
    });
  }
});

Views.register('governance.user_management', {
  async render({ params = {} }) {
    const data = await Api.get('user/list', params);
    const rows = normalizeItems(data);
    const roles = await Api.get('role/list').then(normalizeItems);

    const columns = [
      { key: 'id', label: 'ID' },
      { key: 'full_name', label: 'Full Name' },
      { key: 'email', label: 'Email' },
      { key: 'role_name', label: 'Role' },
      { key: 'is_active', label: 'Active', html: true, render: (row) => UI.status(row.is_active ? 'ACTIVE' : 'INACTIVE') },
      { key: 'force_password_reset', label: 'Reset Req', html: true, render: (row) => row.force_password_reset ? '<span class="status-pill status-warn">Yes</span>' : '-' },
      { key: 'actions', label: 'Actions', html: true, render: (row) => {
          let buttons = `<button class="btn btn-icon btn-ghost" title="Edit" data-edit-id="${row.id}"><i class="ph ph-pencil"></i></button>`;
          if (row.is_active) {
            buttons += `<button class="btn btn-icon btn-ghost" title="Deactivate" data-deactivate-id="${row.id}"><i class="ph ph-pause"></i></button>`;
          } else {
            buttons += `<button class="btn btn-icon btn-ghost" title="Activate" data-activate-id="${row.id}"><i class="ph ph-play"></i></button>`;
          }
          buttons += `<button class="btn btn-icon btn-ghost" title="Delete" data-delete-id="${row.id}"><i class="ph ph-trash"></i></button>`;
          return `<div style="display: flex; gap: 0.5rem;">${buttons}</div>`;
      }}
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'role_id', label: 'Role', type: 'select', value: params.role_id || '', options: [{value:'', label:'All Roles'}, ...roles.map(r => ({value:r.id, label:r.role_name}))] },
      { name: 'is_active', label: 'Status', type: 'select', value: params.is_active || '', options: [{value:'', label:'All'}, {value:'1', label:'Active'}, {value:'0', label:'Inactive'}] }
    ], 'Apply Filters'));

    const actions = UI.button('Create User', { icon: 'ph-plus', attr: 'data-create' }) +
                    UI.button('Restore User', { icon: 'ph-arrow-counter-clockwise', attr: 'data-restore' });

    return UI.page('User Management', 'Manage operations and field personnel', actions)
      + filterUI
      + UI.panel('Users', UI.table(columns, rows, { empty: 'No users found.' }));
  },
  async afterRender() {
    wireFilters((payload) => App.navigate('governance.user_management', payload));

    const createBtn = document.querySelector('[data-create]');
    if (createBtn) {
        createBtn.addEventListener('click', async () => {
            const roles = await Api.get('role/list').then(normalizeItems);
            const roleOptions = roles.map(r => ({ value: r.id, label: r.role_name }));
            openSimpleForm('Create User', [
                { name: 'full_name', label: 'Full Name', type: 'text', required: true },
                { name: 'email', label: 'Email', type: 'email', required: true },
                { name: 'password', label: 'Password', type: 'password', required: true },
                { name: 'role_id', label: 'Role', type: 'select', options: roleOptions, required: true }
            ], 'Create User', (payload) => simpleAction('user/create', payload, 'User created'));
        });
    }

    const restoreBtn = document.querySelector('[data-restore]');
    if (restoreBtn) {
        restoreBtn.addEventListener('click', () => {
            openSimpleForm('Restore User', [
                { name: 'user_id', label: 'User ID to Restore', type: 'number', required: true }
            ], 'Restore User', (payload) => simpleAction('user/restore', payload, 'User restored'));
        });
    }

    document.querySelectorAll('[data-edit-id]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.currentTarget.dataset.editId;
            const roles = await Api.get('role/list').then(normalizeItems);
            const roleOptions = roles.map(r => ({ value: r.id, label: r.role_name }));
            const user = await Api.get('user/get', { user_id: id });
            
            openSimpleForm('Edit User', [
                { name: 'user_id', type: 'hidden', value: id },
                { name: 'full_name', label: 'Full Name', type: 'text', value: user.full_name, required: true },
                { name: 'email', label: 'Email', type: 'email', value: user.email, required: true },
                { name: 'role_id', label: 'Role', type: 'select', options: roleOptions, value: user.role_id, required: true }
            ], 'Update User', (payload) => simpleAction('user/update', payload, 'User updated'));
        });
    });

    document.querySelectorAll('[data-deactivate-id]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const id = e.currentTarget.dataset.deactivateId;
            confirmAction('Deactivate User', 'Are you sure you want to deactivate this user?', () => 
                simpleAction('user/deactivate', { user_id: id }, 'User deactivated')
            );
        });
    });

    document.querySelectorAll('[data-activate-id]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            simpleAction('user/activate', { user_id: e.currentTarget.dataset.activateId }, 'User activated');
        });
    });

    document.querySelectorAll('[data-delete-id]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const id = e.currentTarget.dataset.deleteId;
            confirmAction('Delete User', 'Are you sure you want to soft delete this user?', () => 
                simpleAction('user/delete', { user_id: id }, 'User deleted')
            );
        });
    });
  }
});

Views.register('governance.access_mappings', {
  async render() {
    const [roles, supervisors, authorities, outsourced] = await Promise.all([
      Api.get('role/list').then(normalizeItems),
      Api.get('supervisorassignment/list').then(normalizeItems),
      Api.get('authorityassignment/list').then(normalizeItems),
      Api.get('outsourcedassignment/list').then(normalizeItems)
    ]);

    const roleColumns = [
      { key: 'id', label: 'ID' },
      { key: 'role_key', label: 'Key' },
      { key: 'role_name', label: 'Name' },
      { key: 'permission_group_name', label: 'Perm Group' },
      { key: 'landing_module_key', label: 'Landing' },
      { key: 'is_active', label: 'Active', html: true, render: (row) => UI.status(row.is_active ? 'ACTIVE' : 'INACTIVE') },
      { key: 'actions', label: 'Actions', html: true, render: (row) => `<button class="btn btn-sm btn-ghost" data-edit-role="${row.id}"><i class="ph ph-pencil"></i></button>` }
    ];

    const assignColumns = (type) => [
      { key: 'id', label: 'ID' },
      { key: 'belt_code', label: 'Belt Code' },
      { key: 'user_name', label: 'User Name', render: (row) => row.user_name || row[`${type}_name`] || row.full_name || row[`${type}_user_id`] || '-' },
      { key: 'start_date', label: 'Start Date' },
      { key: 'end_date', label: 'End Date', render: (row) => row.end_date || '-' },
      { key: 'status', label: 'Status', html: true, render: (row) => {
          const now = UI.currentDate();
          let status = 'ACTIVE';
          if (row.end_date && row.end_date < now) status = 'EXPIRED';
          if (row.start_date > now) status = 'UPCOMING';
          return UI.status(status);
      }},
      { key: 'actions', label: 'Actions', html: true, render: (row) => `<button class="btn btn-sm btn-danger" data-close-${type}="${row.id}">Close</button>` }
    ];

    const actions = UI.button('Create Role', { icon: 'ph-plus', attr: 'data-create-role' }) +
                    UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' });

    return UI.page('Access Mappings', 'Manage roles and belt assignments', actions)
      + UI.panel('Roles & Module Scope', UI.table(roleColumns, roles, { empty: 'No roles found.' }))
      + UI.panel('Supervisor Assignments', `
          <div class="inline-actions" style="margin-bottom: 12px;">
            ${UI.button('Assign Supervisor', { icon: 'ph-plus', attr: 'data-assign="supervisor"' })}
          </div>
          ${UI.table(assignColumns('supervisor'), supervisors, { empty: 'No supervisor assignments found.' })}
        `)
      + UI.panel('Authority Assignments', `
          <div class="inline-actions" style="margin-bottom: 12px;">
            ${UI.button('Assign Authority', { icon: 'ph-plus', attr: 'data-assign="authority"' })}
          </div>
          ${UI.table(assignColumns('authority'), authorities, { empty: 'No authority assignments found.' })}
        `)
      + UI.panel('Outsourced Assignments', `
          <div class="inline-actions" style="margin-bottom: 12px;">
            ${UI.button('Assign Outsourced', { icon: 'ph-plus', attr: 'data-assign="outsourced"' })}
          </div>
          ${UI.table(assignColumns('outsourced'), outsourced, { empty: 'No outsourced assignments found.' })}
        `);
  },
  async afterRender() {
    attachRefresh();

    document.querySelector('[data-create-role]')?.addEventListener('click', () => {
      openSimpleForm('Create Role', [
        { name: 'role_name', label: 'Role Name', required: true },
        { name: 'role_key', label: 'Role Key', required: true },
        { name: 'description', label: 'Description' },
        { name: 'permission_group_id', label: 'Permission Group', type: 'select', options: [
            {value: '1', label: 'VIEW'},
            {value: '2', label: 'UPLOAD'},
            {value: '3', label: 'APPROVE'},
            {value: '4', label: 'MANAGE'}
        ], required: true },
        { name: 'landing_module_key', label: 'Landing Module Key', required: true },
        { name: 'module_keys_text', label: 'Allowed Module Keys (comma separated)', type: 'textarea', required: true }
      ], 'Create Role', (payload) => {
        payload.module_keys = payload.module_keys_text.split(',').map(s => s.trim()).filter(Boolean);
        delete payload.module_keys_text;
        return simpleAction('role/create', payload, 'Role created');
      });
    });

    document.querySelectorAll('[data-edit-role]').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const roleId = e.currentTarget.dataset.editRole;
        const roleData = await Api.get('role/get', { role_id: roleId });
        const role = roleData.role;
        const allowedModules = roleData.allowed_module_keys.join(', ');

        openSimpleForm('Edit Role', [
          { name: 'role_id', type: 'hidden', value: role.id },
          { name: 'role_name', label: 'Role Name', value: role.role_name, required: true },
          { name: 'description', label: 'Description', value: role.description || '' },
          { name: 'permission_group_id', label: 'Permission Group', type: 'select', value: roleData.permission_group.id, options: [
              {value: '1', label: 'VIEW'},
              {value: '2', label: 'UPLOAD'},
              {value: '3', label: 'APPROVE'},
              {value: '4', label: 'MANAGE'}
          ], required: true },
          { name: 'landing_module_key', label: 'Landing Module Key', value: role.landing_module_key, required: true },
          { name: 'module_keys_text', label: 'Allowed Module Keys (comma separated)', type: 'textarea', value: allowedModules, required: true }
        ], 'Update Role', (payload) => {
          payload.module_keys = payload.module_keys_text.split(',').map(s => s.trim()).filter(Boolean);
          delete payload.module_keys_text;
          return simpleAction('role/update', payload, 'Role updated');
        });
      });
    });

    document.querySelectorAll('[data-assign]').forEach(btn => {
      btn.addEventListener('click', () => {
        const type = btn.dataset.assign;
        openSimpleForm(`Assign ${UI.titleize(type)}`, [
          { name: 'belt_id', label: 'Belt ID', type: 'number', required: true },
          { name: `${type}_user_id`, label: 'User ID', type: 'number', required: true },
          { name: 'start_date', label: 'Start Date', type: 'date', required: true, value: UI.currentDate() },
          { name: 'end_date', label: 'End Date (Optional)', type: 'date' }
        ], 'Assign', (payload) => simpleAction(`${type}assignment/create`, payload, 'Assignment created'));
      });
    });

    ['supervisor', 'authority', 'outsourced'].forEach(type => {
      document.querySelectorAll(`[data-close-${type}]`).forEach(btn => {
        btn.addEventListener('click', (e) => {
          const assignmentId = e.currentTarget.getAttribute(`data-close-${type}`);
          openSimpleForm('Close Assignment', [
            { name: 'assignment_id', type: 'hidden', value: assignmentId },
            { name: 'end_date', label: 'End Date', type: 'date', required: true, value: UI.currentDate() }
          ], 'Close Now', (payload) => simpleAction(`${type}assignment/close`, payload, 'Assignment closed'));
        });
      });
    });
  }
});
Views.register('task.progress_read', {
  async render({ params = {} }) {
    const data = await Api.get('taskprogress/list', params);
    const rows = normalizeItems(data);

    const columns = [
      { key: 'id', label: 'ID' },
      { key: 'work_description', label: 'Description', render: (row) => UI.escape(row.work_description || '').substring(0, 50) + ((row.work_description || '').length > 50 ? '...' : '') },
      { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.status) },
      { key: 'progress_percent', label: 'Progress', render: (row) => `${row.progress_percent}%` },
      { key: 'assigned_lead_user_name', label: 'Lead', render: (row) => row.assigned_lead_user_name || '-' },
      { key: 'client_name', label: 'Client', render: (row) => row.client_name || '-' },
      { key: 'campaign_id', label: 'Campaign ID', render: (row) => row.campaign_id || '-' },
      { key: 'request_site_id', label: 'Site ID', render: (row) => row.request_site_id || '-' },
      { key: 'start_date', label: 'Start Date' }
    ];

    const filterUI = UI.panel('Filters', UI.filters([
      { name: 'status', label: 'Status', type: 'select', value: params.status || '', options: ['', 'OPEN', 'RUNNING', 'COMPLETED', 'CANCELLED'] },
      { name: 'client_name', label: 'Client Name', type: 'text', value: params.client_name || '' },
      { name: 'campaign_id', label: 'Campaign ID', type: 'number', value: params.campaign_id || '' },
      { name: 'site_id', label: 'Site ID', type: 'number', value: params.site_id || '' },
      { name: 'date_from', label: 'From Date', type: 'date', value: params.date_from || '' },
      { name: 'date_to', label: 'To Date', type: 'date', value: params.date_to || '' }
    ], 'Search'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' });

    return UI.page('Task Progress', 'Monitor operational progress of client requests and campaigns', actions)
      + filterUI
      + UI.panel('Tasks', UI.table(columns, rows, { empty: 'No tasks found for the given criteria.', rowAttr: (row) => `data-task-id="${row.id}"` }));
  },
  async afterRender() {
    attachRefresh();
    wireFilters((payload) => App.navigate('task.progress_read', payload));

    document.querySelectorAll('[data-task-id]').forEach(row => {
      row.addEventListener('click', async () => {
        const taskId = row.dataset.taskId;
        const taskProgress = await Api.get('taskprogress/get', { task_id: taskId });
        if (!taskProgress) return UI.toast('Task progress not found', 'bad');
        
        // Fetch proofs
        let proofsHtml = '<p style="color:var(--ink-500);">No proofs uploaded yet.</p>';
        try {
          const uploads = await Api.get('upload/list', { parent_type: 'TASK', parent_id: taskId });
          const items = normalizeItems(uploads);
          if (items.length > 0) {
            // Build gallery items for shared openPhotoGallery viewer.
            const taskProofGallery = items.map((u) => ({
              id: u.id,
              url: Api.url('upload/serve', { id: u.id }),
              label: u.photo_label || 'Proof',
              workType: u.work_type || '',
              time: u.created_at || ''
            }));
            proofsHtml = `<div class="photo-grid" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">` +
              items.map((u, idx) => `<div style="text-align:center;">
                <img src="${Api.url('upload/serve', { id: u.id })}" class="photo-thumb photo-thumb-lg" data-task-proof-idx="${idx}" alt="Proof">
                <div style="font-size:0.75rem;margin-top:4px;">${UI.escape(u.photo_label || 'Proof')}</div>
              </div>`).join('') + `</div>`;
            // Must open gallery after modal renders, so we store on window temporarily.
            window.__taskProofGallery = taskProofGallery;
          }
        } catch (e) {
          console.error("Failed to load uploads", e);
        }

        UI.showModal('Task Progress Details', `
          <div class="stack-form">
            <div class="form-grid">
              <div class="field"><span>Task ID</span><input type="text" value="${taskProgress.id}" readonly></div>
              <div class="field"><span>Status</span><input type="text" value="${taskProgress.status}" readonly></div>
              <div class="field"><span>Progress %</span><input type="text" value="${taskProgress.progress_percent}%" readonly></div>
              <div class="field"><span>Category</span><input type="text" value="${taskProgress.task_category || '-'}" readonly></div>
              <div class="field"><span>Vertical</span><input type="text" value="${taskProgress.vertical_type || '-'}" readonly></div>
              <div class="field"><span>Priority</span><input type="text" value="${taskProgress.priority || '-'}" readonly></div>
              <div class="field"><span>Client Name</span><input type="text" value="${UI.escape(taskProgress.client_name || '-')}" readonly></div>
              <div class="field"><span>Campaign ID</span><input type="text" value="${taskProgress.campaign_id || '-'}" readonly></div>
              <div class="field"><span>Site ID</span><input type="text" value="${taskProgress.request_site_id || '-'}" readonly></div>
              <div class="field"><span>Start Date</span><input type="text" value="${taskProgress.start_date || '-'}" readonly></div>
              <div class="field"><span>Expected Close</span><input type="text" value="${taskProgress.expected_close_date || '-'}" readonly></div>
              <div class="field"><span>Actual Close</span><input type="text" value="${taskProgress.actual_close_date || '-'}" readonly></div>
              <div class="field"><span>Assigned Lead</span><input type="text" value="${UI.escape(taskProgress.assigned_lead_user_name || '-')}" readonly></div>
              <div class="field"><span>Assigned By</span><input type="text" value="${UI.escape(taskProgress.assigned_by_user_name || '-')}" readonly></div>
              <div class="field full"><span>Location Text</span><input type="text" value="${UI.escape(taskProgress.location_text || '-')}" readonly></div>
              <div class="field full"><span>Work Description</span><textarea readonly>${UI.escape(taskProgress.work_description || '-')}</textarea></div>
              <div class="field full"><span>Remark 1</span><textarea readonly>${UI.escape(taskProgress.remark_1 || '-')}</textarea></div>
              <div class="field full"><span>Remark 2</span><textarea readonly>${UI.escape(taskProgress.remark_2 || '-')}</textarea></div>
              <div class="field full"><span>Completion Note</span><textarea readonly>${UI.escape(taskProgress.completion_note || '-')}</textarea></div>
            </div>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--line);">
              <h4>Execution Proofs</h4>
              ${proofsHtml}
            </div>
          </div>
        `);

        // Wire proof thumbnails to the shared gallery viewer after modal renders.
        const gallery = window.__taskProofGallery;
        if (gallery && gallery.length) {
          document.querySelectorAll('[data-task-proof-idx]').forEach((img) => {
            img.addEventListener('click', (e) => {
              e.stopPropagation();
              openPhotoGallery(gallery, parseInt(img.dataset.taskProofIdx, 10) || 0);
            });
          });
          window.__taskProofGallery = null;
        }
      });
    });
  }
});

// All modules have dedicated Views.register() implementations above.
// simpleLists is intentionally empty — no fallback stubs remain.
const simpleLists = {};

Object.entries(simpleLists).forEach(([moduleKey, [route, title, columns]]) => {
  Views.register(moduleKey, {
    async render() {
      const params = moduleKey === 'monitoring.plan' ? { month: UI.currentMonth() } : {};
      const data = await Api.get(route, params);
      return renderListPage(title, route, data, moduleKey, { columns });
    },
    async afterRender() {
      attachRefresh();
    }
  });
});

// ====================================================================
// PHASE 9: NEW V1 SURFACES
// ====================================================================

Views.register('governance.alert_panel', {
  async render() {
    const data = await Api.get('alert/list');

    function alertSection(title, items, renderRow) {
      const badge = `<span style="background: var(--bad); color: #fff; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; margin-left: 8px;">${items.length}</span>`;
      if (!items.length) {
        return UI.panel(title + badge, `<p style="color:var(--ink-500); padding:0.5rem 0;">No items — all clear.</p>`);
      }
      const rows = items.map(renderRow);
      return UI.panel(title + badge, `<table class="table"><tbody>${rows.join('')}</tbody></table>`);
    }

    const expiryRows = (data.expiry_warnings || []).map(r =>
      `<tr data-nav-belt="${r.id}"><td>${UI.escape(r.belt_code)}</td><td>${UI.escape(r.name)}</td><td>${UI.escape(r.expiry_date)}</td><td><span class="status-pill ${r.days_remaining <= 2 ? 'status-bad' : 'status-warn'}">${r.days_remaining}d left</span></td></tr>`);

    const monitoringRows = (data.overdue_monitoring || []).map(r =>
      `<tr><td>${UI.escape(r.site_code)}</td><td>${UI.escape(r.name)}</td><td>${UI.escape(r.due_date)}</td><td><span class="status-pill status-bad">${r.days_overdue}d overdue</span></td></tr>`);

    const cycleRows = (data.cycles_overdue || []).map(r =>
      `<tr data-nav-belt="${r.id}"><td>${UI.escape(r.belt_code)}</td><td>${UI.escape(r.name)}</td><td>${UI.escape(r.start_date)}</td><td><span class="status-pill status-warn">${r.days_open}d open</span></td></tr>`);

    const attendanceRows = (data.attendance_missing_today || []).map(r =>
      `<tr><td>${UI.escape(r.name)}</td></tr>`);

    const taskRows = (data.high_priority_tasks || []).map(r =>
      `<tr data-nav-task="${r.id}"><td>#${r.id}</td><td>${UI.escape((r.name || '').substring(0, 60))}</td><td>${UI.status(r.priority)}</td><td>${UI.status(r.status)}</td><td>${UI.escape(r.assigned_lead_name || 'Unassigned')}</td></tr>`);

    const campaignRows = (data.campaign_end_pending || []).map(r =>
      `<tr><td>#${r.id}</td><td>${UI.escape(r.name)}</td><td>${UI.escape(r.end_date || '-')}</td></tr>`);

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' });

    return UI.page('Alert Panel', 'Attention items across all domains', actions)
      + UI.panel('Permission Expiry Warnings' + ` <span style="background:var(--bad);color:#fff;padding:2px 8px;border-radius:99px;font-size:.75rem;margin-left:8px">${(data.expiry_warnings||[]).length}</span>`,
          (data.expiry_warnings||[]).length ? `<table class="table"><thead><tr><th>Belt Code</th><th>Name</th><th>Expiry Date</th><th>Urgency</th></tr></thead><tbody>${expiryRows.join('')}</tbody></table>` : `<p style="color:var(--ink-500)">No expiry warnings — all clear.</p>`)
      + UI.panel('Overdue Monitoring' + ` <span style="background:var(--bad);color:#fff;padding:2px 8px;border-radius:99px;font-size:.75rem;margin-left:8px">${(data.overdue_monitoring||[]).length}</span>`,
          (data.overdue_monitoring||[]).length ? `<table class="table"><thead><tr><th>Site Code</th><th>Name</th><th>Due Date</th><th>Overdue</th></tr></thead><tbody>${monitoringRows.join('')}</tbody></table>` : `<p style="color:var(--ink-500)">No overdue monitoring — all clear.</p>`)
      + UI.panel('Long-Running Cycles' + ` <span style="background:var(--warn,#f59e0b);color:#fff;padding:2px 8px;border-radius:99px;font-size:.75rem;margin-left:8px">${(data.cycles_overdue||[]).length}</span>`,
          (data.cycles_overdue||[]).length ? `<table class="table"><thead><tr><th>Belt Code</th><th>Name</th><th>Started</th><th>Days Open</th></tr></thead><tbody>${cycleRows.join('')}</tbody></table>` : `<p style="color:var(--ink-500)">No long-running cycles.</p>`)
      + UI.panel('Attendance Missing Today' + ` <span style="background:var(--bad);color:#fff;padding:2px 8px;border-radius:99px;font-size:.75rem;margin-left:8px">${(data.attendance_missing_today||[]).length}</span>`,
          (data.attendance_missing_today||[]).length ? `<table class="table"><thead><tr><th>Supervisor Name</th></tr></thead><tbody>${attendanceRows.join('')}</tbody></table>` : `<p style="color:var(--ink-500)">All supervisors have attendance records for today.</p>`)
      + UI.panel('High Priority Tasks' + ` <span style="background:var(--bad);color:#fff;padding:2px 8px;border-radius:99px;font-size:.75rem;margin-left:8px">${(data.high_priority_tasks||[]).length}</span>`,
          (data.high_priority_tasks||[]).length ? `<table class="table"><thead><tr><th>ID</th><th>Description</th><th>Priority</th><th>Status</th><th>Lead</th></tr></thead><tbody>${taskRows.join('')}</tbody></table>` : `<p style="color:var(--ink-500)">No high priority tasks open.</p>`)
      + UI.panel('Campaigns Awaiting Free Media Confirmation' + ` <span style="background:var(--bad);color:#fff;padding:2px 8px;border-radius:99px;font-size:.75rem;margin-left:8px">${(data.campaign_end_pending||[]).length}</span>`,
          (data.campaign_end_pending||[]).length ? `<table class="table"><thead><tr><th>ID</th><th>Campaign</th><th>Ended On</th></tr></thead><tbody>${campaignRows.join('')}</tbody></table>` : `<p style="color:var(--ink-500)">No campaigns pending confirmation.</p>`);
  },
  async afterRender() {
    attachRefresh();

    document.querySelectorAll('[data-nav-belt]').forEach(row => {
      row.addEventListener('click', () => App.navigate('green_belt.master'));
    });
    document.querySelectorAll('[data-nav-task]').forEach(row => {
      row.addEventListener('click', () => App.navigate('task.detail', { task_id: row.dataset.navTask }));
    });
  }
});

Views.register('task.worker_daily_entry', {
  async render({ params = {} }) {
    const date = params.date || UI.currentDate();
    const data = await Api.get('workday/my-list', { date });
    const rows = normalizeItems(data);

    const columns = [
      { key: 'worker_name', label: 'Worker' },
      { key: 'skill_tag', label: 'Skill', html: true, render: (row) => UI.status(row.skill_tag) },
      { key: 'attendance_status', label: 'Attendance', html: true, render: (row) => UI.status(row.attendance_status) },
      { key: 'activity_context', label: 'Activity Context', render: (row) => row.activity_context || '-' },
      { key: 'task_id', label: 'Task ID', render: (row) => row.task_id ? `#${row.task_id}` : '-' }
    ];

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
                    UI.button('Mark Entry', { icon: 'ph-plus', kind: 'btn-primary', attr: 'data-mark-entry' });

    return UI.page('Worker Daily Entry', `Daily attendance — ${date}`, actions)
      + UI.panel('Filters', UI.filters([
          { name: 'date', label: 'Date', type: 'date', value: date }
        ], 'Load'))
      + UI.panel('Daily Records', UI.table(columns, rows, { empty: 'No entries for this date.' }));
  },
  async afterRender({ params = {} }) {
    const date = params.date || UI.currentDate();
    attachRefresh();
    wireFilters((payload) => App.navigate('task.worker_daily_entry', payload));

    document.querySelector('[data-mark-entry]')?.addEventListener('click', () => {
      openSimpleForm('Mark Worker Entry', [
        { name: 'worker_id', label: 'Worker ID', type: 'number', required: true },
        { name: 'entry_date', label: 'Date', type: 'date', required: true, value: date },
        { name: 'attendance_status', label: 'Attendance Status', type: 'select', value: 'PRESENT', options: ['PRESENT', 'ABSENT', 'HALF_DAY'], required: true },
        { name: 'activity_type', label: 'Activity Type', type: 'select', value: 'INSTALLATION', options: ['INSTALLATION', 'MAINTENANCE', 'DRIVING', 'MONITORING', 'SUPPORT', 'OTHER'], required: true },
        { name: 'activity_context', label: 'Activity Context (optional)', type: 'textarea' },
        { name: 'task_id', label: 'Task ID (optional)', type: 'number' }
      ], 'Save Entry', (payload) => {
        if (!payload.task_id) delete payload.task_id;
        if (!payload.activity_context) delete payload.activity_context;
        return simpleAction('workday/my-mark', payload, 'Entry recorded');
      });
    });
  }
});

Views.register('commercial.client_media_library', {
  async render({ params = {} }) {
    const data = await Api.get('media/client-library', params);
    const rows = normalizeItems(data);

    const columns = [
      { key: 'created_at', label: 'Date/Time' },
      { key: 'belt_code', label: 'Belt Code' },
      { key: 'belt_name', label: 'Belt Name' },
      { key: 'work_type', label: 'Work Type', html: true, render: (row) => UI.status(row.work_type) },
      { key: 'thumbnail', label: 'Thumbnail', html: true, render: (row) => `<img src="${Api.url('upload/serve', { id: row.id })}" alt="Proof" class="photo-thumb" data-gallery-id="${row.id}">` },
      { key: 'comment_text', label: 'Comment', render: (row) => (row.comment_text || '-').substring(0, 40) }
    ];

    return UI.page('Client Media Library', 'Approved green belt proof available for client review')
      + UI.panel('Filters', UI.filters([
          { name: 'belt_id', label: 'Belt ID', type: 'number', value: params.belt_id || '' },
          { name: 'date_from', label: 'From', type: 'date', value: params.date_from || '' },
          { name: 'date_to', label: 'To', type: 'date', value: params.date_to || '' },
          { name: 'work_type', label: 'Work Type', type: 'select', value: params.work_type || '', options: ['', 'ROUTINE_MAINTENANCE', 'REPAIR', 'PLANTING', 'WATERING', 'CLEANING'] }
        ], 'Search'))
      + UI.panel('Media Library', UI.table(columns, rows, {
          empty: 'No approved media found for these filters.',
        }) + renderPagination(data.pagination, 'commercial.client_media_library', params));
  },
  async afterRender({ params = {} }) {
    attachRefresh();
    attachPagination();
    wireFilters((payload) => App.navigate('commercial.client_media_library', payload));

    // Build gallery items from table rows and wire shared gallery viewer.
    const mediaLibItems = Array.from(document.querySelectorAll('[data-gallery-id]')).map((img) => {
      const row = img.closest('tr');
      const cells = row ? Array.from(row.querySelectorAll('td')) : [];
      return {
        id: parseInt(img.dataset.galleryId, 10),
        url: Api.url('upload/serve', { id: img.dataset.galleryId }),
        time: cells[0]?.innerText?.trim() || '',
        belt: [cells[1]?.innerText?.trim(), cells[2]?.innerText?.trim()].filter(Boolean).join(' — '),
        workType: cells[3]?.innerText?.trim() || '',
      };
    });
    document.querySelectorAll('[data-gallery-id]').forEach((img) => {
      img.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = parseInt(img.dataset.galleryId, 10);
        const index = mediaLibItems.findIndex((item) => item.id === id);
        openPhotoGallery(mediaLibItems, index >= 0 ? index : 0);
      });
    });
  }
});

Views.register('commercial.media_planning_inventory', {
  async render({ params = {} }) {
    const data = await Api.get('media/planning-view', params);
    const rows = normalizeItems(data);

    const columns = [
      { key: 'site_code', label: 'Site Code' },
      { key: 'location_text', label: 'Location' },
      { key: 'site_category', label: 'Category', html: true, render: (row) => UI.status(row.site_category) },
      { key: 'status', label: 'Status', html: true, render: (row) => UI.status(row.status) },
      { key: 'discovered_date', label: 'Discovered' },
      { key: 'confirmed_date', label: 'Confirmed', render: (row) => row.confirmed_date || '-' },
      { key: 'expiry_date', label: 'Expires', render: (row) => row.expiry_date || '-' },
      { key: 'next_monitoring_due', label: 'Next Monitoring Due', render: (row) => row.next_monitoring_due || 'Not scheduled' },
      { key: 'actions', label: '', html: true, render: (row) => `<button class="btn btn-sm btn-ghost" data-raise-request="${row.site_id}">Raise Request</button>` }
    ];

    return UI.page('Media Planning View', 'Free media with monitoring context for planning')
      + UI.panel('Filters', UI.filters([
          { name: 'status', label: 'Status', type: 'select', value: params.status || '', options: ['', 'DISCOVERED', 'CONFIRMED_ACTIVE', 'EXPIRED', 'CONSUMED'] },
          { name: 'site_category', label: 'Category', type: 'select', value: params.site_category || '', options: ['', 'GREEN_BELT', 'CITY', 'HIGHWAY'] },
          { name: 'route_or_group', label: 'Route/Group', value: params.route_or_group || '' }
        ], 'Apply'))
      + UI.panel('Inventory', UI.table(columns, rows, {
          empty: 'No free media found matching criteria.',
        }) + renderPagination(data.pagination, 'commercial.media_planning_inventory', params));
  },
  async afterRender() {
    attachRefresh();
    attachPagination();
    wireFilters((payload) => App.navigate('commercial.media_planning_inventory', payload));

    document.querySelectorAll('[data-raise-request]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        App.navigate('task.request_intake', { site_id: btn.dataset.raiseRequest });
      });
    });
  }
});
