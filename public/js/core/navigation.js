/**
 * Frontend navigation map. Module keys stay aligned with config/rbac.php.
 */

const NavMap = {
  'dashboard.master_ops': { label: 'Master Dashboard', icon: 'ph-chart-line-up', route: 'dashboard/master', section: 'Dashboards' },
  'dashboard.green_belt': { label: 'Green Belt Dashboard', icon: 'ph-tree', route: 'dashboard/green-belt', section: 'Dashboards' },
  'dashboard.advertisement': { label: 'Advertisement Dashboard', icon: 'ph-megaphone', route: 'dashboard/advertisement', section: 'Dashboards' },
  'dashboard.monitoring': { label: 'Monitoring Dashboard', icon: 'ph-binoculars', route: 'dashboard/monitoring', section: 'Dashboards' },
  'dashboard.management': { label: 'Management Dashboard', icon: 'ph-briefcase', route: 'dashboard/management', section: 'Dashboards' },

  'green_belt.master': { label: 'Green Belts', icon: 'ph-map-pin', route: 'belt/list', section: 'Green Belt' },
  'green_belt.detail': { label: 'Belt Detail', icon: 'ph-info', route: 'belt/get', section: 'Green Belt', hidden: true },
  'green_belt.supervisor_upload': { label: 'Supervisor Upload', icon: 'ph-upload-simple', route: 'upload/supervisor', section: 'Green Belt', roles: ['GREEN_BELT_SUPERVISOR'] },
  'green_belt.my_uploads': { label: 'My Uploads', icon: 'ph-images', route: 'upload/my-list', section: 'Green Belt', roles: ['GREEN_BELT_SUPERVISOR', 'OUTSOURCED_MAINTAINER', 'MONITORING_TEAM', 'FABRICATION_LEAD'] },
  'green_belt.outsourced_upload': { label: 'Outsourced Upload', icon: 'ph-camera', route: 'upload/outsourced', section: 'Green Belt', roles: ['OUTSOURCED_MAINTAINER'] },
  'green_belt.watering_oversight': { label: 'Watering Oversight', icon: 'ph-drop', route: 'oversight/watering', section: 'Green Belt' },
  'green_belt.maintenance_cycles': { label: 'Maintenance Cycles', icon: 'ph-recycle', route: 'cycle/list', section: 'Green Belt' },
  'green_belt.supervisor_attendance': { label: 'Attendance', icon: 'ph-user-check', route: 'attendance/list', section: 'Green Belt' },
  'green_belt.labour_entries': { label: 'Labour Entries', icon: 'ph-users', route: 'labour/list', section: 'Green Belt' },
  'green_belt.upload_review': { label: 'Upload Review', icon: 'ph-checks', route: 'upload/list', section: 'Green Belt' },
  'green_belt.issue_management': { label: 'Issues', icon: 'ph-warning-circle', route: 'issue/list', section: 'Green Belt' },
  'green_belt.authority_view': { label: 'Authority View', icon: 'ph-bank', route: 'authority/view', section: 'Green Belt', roles: ['AUTHORITY_REPRESENTATIVE'] },

  'advertisement.site_master': { label: 'Site Master', icon: 'ph-storefront', route: 'site/list', section: 'Advertisement' },
  'advertisement.campaign_management': { label: 'Campaigns', icon: 'ph-flag-banner', route: 'campaign/list', section: 'Advertisement' },
  'monitoring.upload': { label: 'Monitoring Upload', icon: 'ph-eye', route: 'monitoring/upload', section: 'Monitoring', roles: ['MONITORING_TEAM'] },
  'monitoring.plan': { label: 'Monitoring Plan', icon: 'ph-map-trifold', route: 'monitoringplan/list', section: 'Monitoring' },
  'monitoring.history': { label: 'Monitoring History', icon: 'ph-clock-counter-clockwise', route: 'monitoring/history', section: 'Monitoring' },
  'media.free_media_inventory': { label: 'Free Media', icon: 'ph-gift', route: 'freemedia/list', section: 'Advertisement' },

  'task.request_intake': { label: 'Task Requests', icon: 'ph-tray-arrow-down', route: 'request/list', section: 'Tasks' },
  'task.progress_read': { label: 'Task Progress', icon: 'ph-activity', route: 'taskprogress/list', section: 'Tasks' },
  'task.management': { label: 'Task Management', icon: 'ph-list-checks', route: 'task/list', section: 'Tasks' },
  'task.detail': { label: 'Task Detail', icon: 'ph-list-dashes', route: 'task/get', section: 'Tasks', hidden: true },
  'task.my_tasks': { label: 'My Tasks', icon: 'ph-check-circle', route: 'task/my', section: 'Tasks' },
  'task.worker_allocation': { label: 'Workers', icon: 'ph-users-three', route: 'worker/list', section: 'Tasks' },

  'governance.user_management': { label: 'Users', icon: 'ph-users-four', route: 'user/list', section: 'Governance' },
  'governance.access_mappings': { label: 'Roles & Access', icon: 'ph-shield-check', route: 'role/list', section: 'Governance' },
  'governance.audit_logs': { label: 'Audit Logs', icon: 'ph-file-search', route: 'audit/list', section: 'Governance' },
  'governance.rejected_upload_cleanup': { label: 'Rejected Cleanup', icon: 'ph-trash', route: 'upload/cleanup-list', section: 'Governance' },
  'governance.alert_panel': { label: 'Alert Panel', icon: 'ph-bell-ringing', route: 'alert/list', section: 'Governance' },
  'reports.monthly': { label: 'Monthly Reports', icon: 'ph-file-csv', route: 'report/belt-health', section: 'Governance' },
  'settings.system': { label: 'Settings', icon: 'ph-gear', route: 'settings/list', section: 'Governance' },

  'task.worker_daily_entry': { label: 'Worker Daily Entry', icon: 'ph-calendar-check', route: 'workday/my-list', section: 'Tasks' },

  'commercial.client_media_library': { label: 'Client Media Library', icon: 'ph-images-square', route: 'media/client-library', section: 'Commercial' },
  'commercial.media_planning_inventory': { label: 'Media Planning View', icon: 'ph-chart-bar', route: 'media/planning-view', section: 'Commercial' }
};

const Navigation = {
  getConfig(moduleKey) {
    return NavMap[moduleKey] || null;
  },

  findModuleByRoute(route) {
    return Object.entries(NavMap).find(([, config]) => config.route === route)?.[0] || null;
  },

  visibleModules(allowedModuleKeys, roleKey) {
    return allowedModuleKeys
      .map((moduleKey) => ({ moduleKey, config: NavMap[moduleKey] }))
      .filter(({ config }) => config && !config.hidden)
      .filter(({ config }) => !config.roles || config.roles.includes(roleKey));
  },

  firstVisibleModule(allowedModuleKeys, roleKey) {
    return this.visibleModules(allowedModuleKeys, roleKey)[0]?.moduleKey || 'dashboard.master_ops';
  },

  renderSidebar(allowedModuleKeys, roleKey) {
    const sidebar = document.getElementById('sidebar-menu');
    if (!sidebar) return;

    const modules = this.visibleModules(allowedModuleKeys, roleKey);
    let currentSection = '';
    sidebar.innerHTML = '';

    modules.forEach(({ moduleKey, config }) => {
      if (config.section !== currentSection) {
        currentSection = config.section;
        const section = document.createElement('li');
        section.className = 'nav-section';
        section.textContent = currentSection;
        sidebar.appendChild(section);
      }

      const item = document.createElement('li');
      item.className = 'nav-item';
      item.innerHTML = `
        <button type="button" class="nav-link" data-module-key="${moduleKey}">
          <i class="ph ${config.icon} nav-icon"></i>
          <span>${UI.escape(config.label)}</span>
        </button>
      `;
      item.querySelector('button').addEventListener('click', () => App.navigate(moduleKey));
      sidebar.appendChild(item);
    });
  },

  setActive(moduleKey) {
    document.querySelectorAll('.nav-link').forEach((link) => {
      link.classList.toggle('active', link.dataset.moduleKey === moduleKey);
    });
  }
};
