/**
 * Core API wrapper for the PHP route registry.
 */

const API_BASE = '../index.php?route=';

const Api = {
  buildRoute(route, params = {}) {
    const entries = Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '');
    if (entries.length === 0) return route;

    const query = entries
      .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
      .join('&');

    return route.includes('&') ? `${route}&${query}` : `${route}&${query}`;
  },

  async request(route, options = {}) {
    const body = options.body;
    const isFormData = body instanceof FormData;
    const headers = {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...(options.headers || {})
    };

    const csrfToken = Auth.getCsrfToken();
    const method = options.method || 'GET';
    if (csrfToken && method !== 'GET') {
      headers['X-CSRF-Token'] = csrfToken;
    }

    const config = { ...options, method, headers };
    if (body && typeof body === 'object' && !isFormData) {
      config.body = JSON.stringify(body);
    }

    const response = await fetch(`${API_BASE}${route}`, config);

    if (response.status === 401) {
      Auth.clearSession();
      if (typeof App !== 'undefined') App.showLogin();
      throw new Error('Unauthorized');
    }

    const rawText = await response.text();
    let parsed;
    try {
      parsed = JSON.parse(rawText);
    } catch (error) {
      throw new Error(`Invalid server response from ${route}`);
    }

    if (!response.ok || !parsed.success) {
      throw new Error(parsed.error || `HTTP ${response.status}`);
    }

    return parsed.data;
  },

  get(route, params = {}) {
    return this.request(this.buildRoute(route, params), { method: 'GET' });
  },

  post(route, data = {}) {
    return this.request(route, { method: 'POST', body: data });
  },

  upload(route, formData) {
    return this.request(route, { method: 'POST', body: formData });
  },

  url(route, params = {}) {
    return `${API_BASE}${this.buildRoute(route, params)}`;
  }
};
