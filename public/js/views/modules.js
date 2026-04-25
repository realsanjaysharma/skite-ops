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
    }));
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

function openSimpleForm(title, fields, submitLabel, handler) {
  UI.showModal(title, UI.form(fields, submitLabel));
  document.querySelector('.js-action-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await handler(UI.formData(event.currentTarget));
    } catch (error) {
      UI.toast(error.message, 'bad');
    }
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

Views.register('dashboard.master_ops', dashboardView('dashboard/master', 'Master Operations Dashboard', 'High-level control across all domains', `
  ${UI.button('Green Belts', { icon: 'ph-tree', attr: `data-nav="green_belt.master"` })}
  ${UI.button('Tasks', { icon: 'ph-list-checks', attr: `data-nav="task.management"` })}
  ${UI.button('Upload Review', { icon: 'ph-image', attr: `data-nav="green_belt.upload_review"` })}
  ${UI.button('Reports', { icon: 'ph-file-csv', attr: `data-nav="reports.monthly"` })}
`));

Views.register('dashboard.green_belt', dashboardView('dashboard/green-belt', 'Green Belt Dashboard', 'Daily belt health and exceptions', `
  ${UI.button('Green Belts', { icon: 'ph-tree', attr: `data-nav="green_belt.master"` })}
  ${UI.button('Watering & Attendance', { icon: 'ph-drop', attr: `data-nav="green_belt.watering_oversight"` })}
  ${UI.button('Issues', { icon: 'ph-warning-circle', attr: `data-nav="green_belt.issue_management"` })}
`));

Views.register('dashboard.advertisement', dashboardView('dashboard/advertisement', 'Advertisement Dashboard', 'Campaigns, sites, and media operations', `
  ${UI.button('Site Master', { icon: 'ph-map-pin', attr: `data-nav="advertisement.site_master"` })}
  ${UI.button('Campaigns', { icon: 'ph-megaphone', attr: `data-nav="advertisement.campaign_management"` })}
  ${UI.button('Free Media', { icon: 'ph-gift', attr: `data-nav="media.free_media_inventory"` })}
`));

Views.register('dashboard.monitoring', dashboardView('dashboard/monitoring', 'Monitoring Dashboard', 'Due monitoring, coverage, and discovery', `
  ${UI.button('Monitoring Plan', { icon: 'ph-calendar-check', attr: `data-nav="monitoring.plan"` })}
  ${UI.button('Monitoring History', { icon: 'ph-clock-counter-clockwise', attr: `data-nav="monitoring.history"` })}
`));

Views.register('dashboard.management', dashboardView('dashboard/management', 'Management Dashboard', 'Read-only business overview', `
  ${UI.button('Reports', { icon: 'ph-file-csv', attr: `data-nav="reports.monthly"` })}
  ${UI.button('Authority View', { icon: 'ph-eye', attr: `data-nav="green_belt.authority_view"` })}
`));

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
      ]}
    ], 'Apply Filter'));

    const actions = UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }) +
                    UI.button('New Belt', { icon: 'ph-plus', kind: 'btn-primary', attr: 'data-create-belt' });

    return UI.page('Green Belts', 'Manage belts, permissions, and oversight', actions)
      + filterUI
      + UI.panel('Records', UI.table(columns, rows, { empty: 'No belts found', rowAttr: (row) => `data-open-belt="${row.id}"` }));
  },
  async afterRender() {
    attachRefresh();
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
        { name: 'watering_frequency', label: 'Watering Frequency', type: 'select', value: 'DAILY', options: ['DAILY', 'ALTERNATE_DAY', 'WEEKLY'] }
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
        ${UI.table(inferColumns(data.supervisor_assignments || []), data.supervisor_assignments || [], { empty: 'No supervisor assignments' })}
        <h4>Authorities</h4>
        ${UI.table(inferColumns(data.authority_assignments || []), data.authority_assignments || [], { empty: 'No authority assignments' })}
        <h4>Outsourced</h4>
        ${UI.table(inferColumns(data.outsourced_assignments || []), data.outsourced_assignments || [], { empty: 'No outsourced assignments' })}
      `)
      + UI.panel('Maintenance Cycles', `
        <div class="inline-actions" style="margin-bottom: 12px;">
          ${belt.maintenance_mode === 'MAINTAINED' ? UI.button('Start Cycle', { icon: 'ph-play', attr: 'data-start-cycle' }) : ''}
          ${belt.maintenance_mode === 'MAINTAINED' ? UI.button('Close Cycle', { icon: 'ph-stop', attr: 'data-close-cycle' }) : ''}
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

    document.querySelector('[data-close-cycle]')?.addEventListener('click', () => {
      openSimpleForm('Close Maintenance Cycle', [
        { name: 'cycle_id', label: 'Active Cycle ID', type: 'number', required: true },
        { name: 'end_date', label: 'End Date', type: 'date', required: true, value: UI.currentDate() },
        { name: 'close_reason', label: 'Reason', type: 'textarea' }
      ], 'Close Cycle', (payload) => simpleAction('cycle/close', payload, 'Cycle closed'));
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

function uploadView(surface, parentType, title) {
  return {
    async render() {
      return UI.page(title, 'Submit field proof with photos')
        + UI.panel('Upload Proof', `
          <form class="stack-form js-upload-form">
            <div class="form-grid">
              ${UI.field({ name: 'parent_id', label: `${UI.titleize(parentType)} ID`, type: 'number', required: true })}
              ${UI.field({ name: 'upload_type', label: 'Upload Type', type: 'select', value: 'WORK', options: parentType === 'TASK' ? ['WORK'] : ['WORK', 'ISSUE'] })}
              ${parentType === 'TASK' ? UI.field({ name: 'photo_label', label: 'Photo Label', type: 'select', value: 'AFTER_WORK', options: ['BEFORE_WORK', 'AFTER_WORK', 'GENERAL'] }) : ''}
              ${parentType === 'SITE' ? UI.field({ name: 'discovery_mode', label: 'Discovery Mode', type: 'select', value: '0', options: [{ value: '0', label: 'No' }, { value: '1', label: 'Yes' }] }) : ''}
              ${UI.field({ name: 'comment_text', label: 'Comment', type: 'textarea', full: true })}
              <label class="field full">
                <span>Photos</span>
                <input type="file" name="files[]" multiple accept="image/*" required>
              </label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ph ph-upload-simple"></i><span>Upload</span></button>
          </form>
        `);
    },
    async afterRender() {
      document.querySelector('.js-upload-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const formData = new FormData();
        formData.set('parent_type', parentType);
        formData.set('parent_id', form.elements.parent_id.value);
        formData.set('upload_type', form.elements.upload_type.value);
        if (form.elements.photo_label) formData.set('photo_label', form.elements.photo_label.value);
        if (form.elements.discovery_mode) formData.set('discovery_mode', form.elements.discovery_mode.value);
        formData.set('comment_text', form.elements.comment_text.value);
        Array.from(form.querySelector('input[type="file"]').files).forEach((file, index) => formData.append(`files[${index}]`, file));

        try {
          await Api.upload('upload/create', formData);
          UI.toast('Upload submitted', 'good');
          form.reset();
        } catch (error) {
          UI.toast(error.message, 'bad');
        }
      });
    }
  };
}

Views.register('green_belt.supervisor_upload', uploadView('SUPERVISOR', 'GREEN_BELT', 'Supervisor Upload'));
Views.register('green_belt.outsourced_upload', uploadView('OUTSOURCED', 'GREEN_BELT', 'Outsourced Upload'));
Views.register('monitoring.upload', uploadView('MONITORING', 'SITE', 'Monitoring Upload'));

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
  async render() {
    const data = await Api.get('oversight/watering');
    const watering = normalizeItems(data.watering || data.watering_records || data);
    const attendance = normalizeItems(data.attendance || []);
    const labour = normalizeItems(data.labour || []);
    return UI.page('Supervisor Attendance & Watering', UI.currentDate(), UI.button('Refresh', { icon: 'ph-arrows-clockwise', attr: 'data-refresh' }))
      + UI.panel('Watering', UI.table(inferColumns(watering), watering, { empty: 'No watering exceptions' }))
      + UI.panel('Attendance', UI.table(inferColumns(attendance), attendance, { empty: 'No attendance rows' }))
      + UI.panel('Labour Counts', UI.table(inferColumns(labour), labour, { empty: 'No labour rows' }));
  },
  async afterRender() {
    attachRefresh();
  }
});

Views.register('reports.monthly', {
  async render({ params }) {
    const month = params.month || UI.currentMonth();
    const data = await Api.get('report/belt-health', { month });
    return UI.page('Monthly Reports', 'Calendar-month exports and summaries')
      + UI.panel('Filters', UI.filters([{ name: 'month', label: 'Month', type: 'month', value: month }], 'Load'))
      + UI.panel('Belt Health Summary', UI.table(inferColumns(normalizeItems(data)), normalizeItems(data), { empty: 'No report rows' }), `
        <a class="btn" href="${Api.url('report/belt-health', { month, format: 'csv' })}"><i class="ph ph-download-simple"></i><span>CSV</span></a>
      `)
      + UI.panel('Other Reports', `
        <div class="inline-actions">
          <a class="btn" href="${Api.url('report/supervisor-activity', { month, format: 'csv' })}"><i class="ph ph-download-simple"></i><span>Supervisor Activity CSV</span></a>
          <a class="btn" href="${Api.url('report/worker-activity', { month, format: 'csv' })}"><i class="ph ph-download-simple"></i><span>Worker Activity CSV</span></a>
          <a class="btn" href="${Api.url('report/advertisement-operations', { month, format: 'csv' })}"><i class="ph ph-download-simple"></i><span>Ad Ops CSV</span></a>
        </div>
      `);
  },
  async afterRender() {
    wireFilters((payload) => App.navigate('reports.monthly', payload));
  }
});

Views.register('monitoring.plan', {
  async render({ params }) {
    const month = params.month || UI.currentMonth();
    const filters = { month };
    if (params.site_category) filters.site_category = params.site_category;
    if (params.lighting_type) filters.lighting_type = params.lighting_type;
    if (params.route_or_group) filters.route_or_group = params.route_or_group;

    const data = await Api.get('monitoringplan/list', filters);
    const rows = normalizeItems(data);
    const columns = [
      { key: 'site_id', label: 'Site ID' },
      { key: 'site_code', label: 'Site Code' },
      { key: 'location_text', label: 'Location' },
      { key: 'site_category', label: 'Category', html: true, render: (row) => UI.status(row.site_category) },
      { key: 'lighting_type', label: 'Lighting', html: true, render: (row) => UI.status(row.lighting_type) },
      { key: 'route_or_group', label: 'Route / Group' },
      { key: 'selected_due_dates_count', label: 'Due Dates' },
      { key: 'due_dates', label: 'Dates', render: (row) => valueForDisplay(row.due_dates) }
    ];

    return UI.page('Monitoring Plan', 'Stored monthly due dates are the due-truth for monitoring')
      + UI.panel('Filters', UI.filters([
        { name: 'month', label: 'Month', type: 'month', value: month },
        { name: 'site_category', label: 'Category', type: 'select', value: params.site_category || '', options: [
          { value: '', label: 'All' },
          'GREEN_BELT',
          'CITY',
          'HIGHWAY'
        ] },
        { name: 'lighting_type', label: 'Lighting', type: 'select', value: params.lighting_type || '', options: [
          { value: '', label: 'All' },
          'LIT',
          'NON_LIT'
        ] },
        { name: 'route_or_group', label: 'Route / Group', value: params.route_or_group || '' }
      ], 'Load'))
      + UI.panel('Sites', UI.table(columns, rows, { empty: 'No sites found for this month' }));
  },
  async afterRender() {
    wireFilters((payload) => App.navigate('monitoring.plan', payload));
  }
});

const simpleLists = {
  'green_belt.maintenance_cycles': ['cycle/list', 'Maintenance Cycles', ['id', 'belt_code', 'common_name', 'start_date', 'end_date', 'status']],
  'green_belt.supervisor_attendance': ['attendance/list', 'Supervisor Attendance', ['id', 'supervisor_name', 'attendance_date', 'status', 'created_by_user_name']],
  'green_belt.labour_entries': ['labour/list', 'Labour Entries', ['id', 'belt_name', 'entry_date', 'labour_count', 'gardener_count', 'night_guard_count']],
  'green_belt.upload_review': ['upload/list', 'Upload Review', ['id', 'parent_type', 'parent_id', 'upload_type', 'authority_visibility', 'created_by_user_name', 'created_at']],
  'green_belt.issue_management': ['issue/list', 'Issues', ['id', 'title', 'priority', 'status', 'belt_id', 'site_id', 'created_at']],
  'green_belt.authority_view': ['authority/view', 'Authority View', ['id', 'belt_name', 'upload_type', 'work_type', 'created_at']],
  'advertisement.site_master': ['site/list', 'Site Master', ['id', 'site_code', 'location_text', 'site_category', 'lighting_type', 'is_active']],
  'advertisement.campaign_management': ['campaign/list', 'Campaigns', ['id', 'campaign_code', 'client_name', 'campaign_name', 'status', 'start_date', 'expected_end_date']],
  'monitoring.history': ['monitoring/history', 'Monitoring History', ['id', 'site_code', 'is_discovery_mode', 'created_by_user_name', 'created_at']],
  'media.free_media_inventory': ['freemedia/list', 'Free Media', ['id', 'site_code', 'source_type', 'status', 'discovered_date', 'expiry_date']],
  'task.request_intake': ['request/list', 'Task Requests', ['id', 'requester_name', 'request_type', 'status', 'priority', 'created_at']],
  'task.progress_read': ['taskprogress/list', 'Task Progress', ['id', 'work_description', 'status', 'progress_percent', 'assigned_lead_name']],
  'task.management': ['task/list', 'Task Management', ['id', 'work_description', 'task_category', 'vertical_type', 'priority', 'status', 'progress_percent']],
  'task.my_tasks': ['task/my', 'My Tasks', ['id', 'work_description', 'task_category', 'priority', 'status', 'progress_percent']],
  'task.worker_allocation': ['worker/list', 'Workers', ['id', 'worker_name', 'skill_tag', 'is_active']],
  'governance.user_management': ['user/list', 'Users', ['id', 'full_name', 'email', 'role_name', 'is_active']],
  'governance.access_mappings': ['role/list', 'Roles & Access', ['id', 'role_key', 'role_name', 'permission_group_key', 'landing_module_key', 'is_active']],
  'governance.audit_logs': ['audit/list', 'Audit Logs', ['id', 'actor_user_name', 'action_type', 'entity_type', 'entity_id', 'created_at']],
  'governance.rejected_upload_cleanup': ['upload/cleanup-list', 'Rejected Upload Cleanup', ['id', 'belt_name', 'created_by_user_name', 'authority_visibility', 'reviewed_at']],
  'settings.system': ['settings/list', 'System Settings', ['setting_key', 'setting_value', 'value_type', 'description']]
};

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
