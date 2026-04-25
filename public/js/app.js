/**
 * Skite Ops frontend orchestrator.
 */

const App = {
  els: {},
  currentModuleKey: null,
  currentParams: {},
  allowedModuleKeys: [],

  async init() {
    this.cacheDOM();
    this.bindEvents();

    const isAuthed = await Auth.checkSession();
    if (isAuthed) {
      this.bootShell();
    } else {
      this.showLogin();
    }
  },

  cacheDOM() {
    this.els.loginView = document.getElementById('login-view');
    this.els.appShell = document.getElementById('app-shell');
    this.els.loginForm = document.getElementById('login-form');
    this.els.loginError = document.getElementById('login-error');
    this.els.userDisplayName = document.getElementById('user-display-name');
    this.els.userRole = document.getElementById('user-role');
    this.els.btnLogout = document.getElementById('btn-logout');
    this.els.btnMobileMenu = document.getElementById('btn-mobile-menu');
    this.els.mobileScrim = document.getElementById('mobile-scrim');
    this.els.moduleContainer = document.getElementById('module-container');
    this.els.pageKicker = document.getElementById('page-kicker');
  },

  bindEvents() {
    this.els.loginForm?.addEventListener('submit', this.handleLogin.bind(this));
    this.els.btnLogout?.addEventListener('click', this.handleLogout.bind(this));
    this.els.btnMobileMenu?.addEventListener('click', () => this.els.appShell.classList.add('nav-open'));
    this.els.mobileScrim?.addEventListener('click', () => this.els.appShell.classList.remove('nav-open'));
  },

  async handleLogin(event) {
    event.preventDefault();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    this.els.loginError.textContent = '';

    try {
      await Auth.login(email, password);
      this.bootShell();
    } catch (error) {
      this.els.loginError.textContent = error.message || 'Login failed.';
    }
  },

  async handleLogout() {
    await Auth.logout();
    this.showLogin();
  },

  showLogin() {
    this.els.appShell.classList.add('hidden');
    this.els.appShell.classList.remove('nav-open');
    this.els.loginView.classList.remove('hidden');
    this.els.loginForm?.reset();
  },

  bootShell() {
    this.els.loginView.classList.add('hidden');
    this.els.appShell.classList.remove('hidden');

    const user = Auth.getUser();
    const roleKey = user?.role_key || '';
    this.els.userDisplayName.textContent = user?.full_name || user?.email || 'User';
    this.els.userRole.textContent = UI.titleize(roleKey);

    this.allowedModuleKeys = Auth.getAllowedModuleKeys();
    Navigation.renderSidebar(this.allowedModuleKeys, roleKey);

    const landingModule = Navigation.findModuleByRoute(Auth.getLandingRoute())
      || Navigation.firstVisibleModule(this.allowedModuleKeys, roleKey);
    this.navigate(landingModule);
  },

  async navigate(moduleKey, params = {}) {
    const config = Navigation.getConfig(moduleKey);
    if (!config) {
      this.render(UI.error(`Unknown module: ${moduleKey}`));
      return;
    }

    if (!this.allowedModuleKeys.includes(moduleKey)) {
      UI.toast('This page is not available for your role.', 'bad');
      return;
    }

    this.currentModuleKey = moduleKey;
    this.currentParams = params;
    this.els.appShell.classList.remove('nav-open');
    this.els.pageKicker.textContent = config.section || 'Workspace';
    Navigation.setActive(moduleKey);
    this.render(UI.page(config.label, 'Loading module') + UI.loading(config.label));

    try {
      const view = Views.get(moduleKey);
      const html = await view.render({ moduleKey, config, params });
      this.render(html);
      await view.afterRender?.({ moduleKey, config, params });
    } catch (error) {
      this.render(UI.page(config.label, 'Something needs attention') + UI.error(error.message));
    }
  },

  refresh() {
    if (this.currentModuleKey) {
      this.navigate(this.currentModuleKey, this.currentParams);
    }
  },

  render(html) {
    this.els.moduleContainer.innerHTML = html;
  }
};

document.addEventListener('DOMContentLoaded', () => App.init());
