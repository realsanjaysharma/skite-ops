/**
 * Authentication management
 * Stores session securely in sessionStorage.
 */

const Auth = {
  // We use sessionStorage to survive page refreshes
  SESSION_KEY: 'skite_session',

  async checkSession() {
    try {
      const data = await Api.get('auth/session');
      this.saveSession(data);
      return true;
    } catch (err) {
      this.clearSession();
      return false;
    }
  },

  async login(email, password) {
    const data = await Api.post('auth/login', { email, password });
    this.saveSession(data);
    return data;
  },

  async logout() {
    try {
      await Api.post('auth/logout');
    } catch(e) {
      // Ignore network errors on logout, we clear local state anyway
    }
    this.clearSession();
  },

  saveSession(data) {
    sessionStorage.setItem(this.SESSION_KEY, JSON.stringify(data));
  },

  getSession() {
    const raw = sessionStorage.getItem(this.SESSION_KEY);
    return raw ? JSON.parse(raw) : null;
  },

  clearSession() {
    sessionStorage.removeItem(this.SESSION_KEY);
  },

  getCsrfToken() {
    const s = this.getSession();
    return s ? s.csrf_token : null;
  },

  getAllowedModuleKeys() {
    const s = this.getSession();
    return s ? (s.allowed_module_keys || []) : [];
  },
  
  getLandingRoute() {
    const s = this.getSession();
    return s ? s.landing_route : null;
  },

  getUser() {
    const s = this.getSession();
    return s ? s.user : null;
  }
};
