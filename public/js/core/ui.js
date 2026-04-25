/**
 * Small UI toolkit used by vanilla module views.
 */

const UI = {
  escape(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  },

  titleize(value) {
    return String(value ?? '')
      .replaceAll('_', ' ')
      .replace(/\b\w/g, (char) => char.toUpperCase());
  },

  localDateParts() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return { year, month, day };
  },

  currentDate() {
    const { year, month, day } = this.localDateParts();
    return `${year}-${month}-${day}`;
  },

  currentMonth() {
    const { year, month } = this.localDateParts();
    return `${year}-${month}`;
  },

  page(title, subtitle = '', actions = '') {
    return `
      <div class="page-title-row">
        <div class="page-title">
          <h1>${this.escape(title)}</h1>
          ${subtitle ? `<p>${this.escape(subtitle)}</p>` : ''}
        </div>
        ${actions ? `<div class="toolbar">${actions}</div>` : ''}
      </div>
    `;
  },

  loading(label = 'Loading') {
    return `<div class="panel"><div class="loading-state">${this.escape(label)}...</div></div>`;
  },

  empty(label = 'No records found') {
    return `<div class="empty-state">${this.escape(label)}</div>`;
  },

  error(message) {
    return `<div class="panel"><div class="error-state">${this.escape(message)}</div></div>`;
  },

  button(label, options = {}) {
    const icon = options.icon ? `<i class="ph ${this.escape(options.icon)}"></i>` : '';
    const type = options.type || 'button';
    const kind = options.kind || 'btn';
    const attr = options.attr || '';
    return `<button type="${type}" class="btn ${kind}" ${attr}>${icon}<span>${this.escape(label)}</span></button>`;
  },

  status(value) {
    const text = String(value ?? 'UNKNOWN');
    const lower = text.toLowerCase();
    let cls = 'status-muted';
    if (/(approved|active|present|done|completed|healthy|confirmed|running|open)/.test(lower)) cls = 'status-good';
    if (/(pending|submitted|warning|hidden|not_required|half|due)/.test(lower)) cls = 'status-warn';
    if (/(rejected|risk|overdue|absent|expired|cancelled|deleted|closed)/.test(lower)) cls = 'status-bad';
    if (/(progress|discovered|not_eligible|view)/.test(lower)) cls = 'status-info';
    return `<span class="status-pill ${cls}">${this.escape(this.titleize(text))}</span>`;
  },

  cards(items) {
    return `
      <div class="card-grid">
        ${items.map((item) => `
          <article class="metric-card">
            <span>${this.escape(item.label)}</span>
            <strong>${this.escape(item.value ?? 0)}</strong>
          </article>
        `).join('')}
      </div>
    `;
  },

  panel(title, body, actions = '') {
    return `
      <section class="panel">
        <div class="section-header">
          <h2>${this.escape(title)}</h2>
          ${actions ? `<div class="inline-actions">${actions}</div>` : ''}
        </div>
        <div class="section-body">${body}</div>
      </section>
    `;
  },

  table(columns, rows, options = {}) {
    if (!rows || rows.length === 0) {
      return this.empty(options.empty || 'No records found');
    }

    return `
      <div class="data-table-wrap">
        <table class="data-table">
          <thead>
            <tr>${columns.map((column) => `<th>${this.escape(column.label)}</th>`).join('')}</tr>
          </thead>
          <tbody>
            ${rows.map((row) => {
              const rowAttr = options.rowAttr ? options.rowAttr(row) : '';
              const rowClass = rowAttr ? 'clickable' : '';
              return `
                <tr class="${rowClass}" ${rowAttr}>
                  ${columns.map((column) => {
                    const raw = typeof column.render === 'function' ? column.render(row) : row[column.key];
                    return `<td>${column.html ? raw : this.escape(raw ?? '')}</td>`;
                  }).join('')}
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>
    `;
  },

  filters(fields, actionLabel = 'Apply') {
    return `
      <form class="filter-grid js-filter-form">
        ${fields.map((field) => this.field(field)).join('')}
        <button type="submit" class="btn btn-primary"><i class="ph ph-funnel"></i><span>${this.escape(actionLabel)}</span></button>
      </form>
    `;
  },

  field(field) {
    const value = field.value ?? '';
    const required = field.required ? 'required' : '';
    const name = this.escape(field.name);
    const label = this.escape(field.label);

    if (field.type === 'select') {
      return `
        <label class="field">
          <span>${label}</span>
          <select name="${name}" ${required}>
            ${(field.options || []).map((option) => {
              const optionValue = typeof option === 'string' ? option : option.value;
              const optionLabel = typeof option === 'string' ? this.titleize(option) : option.label;
              const selected = String(optionValue) === String(value) ? 'selected' : '';
              return `<option value="${this.escape(optionValue)}" ${selected}>${this.escape(optionLabel)}</option>`;
            }).join('')}
          </select>
        </label>
      `;
    }

    if (field.type === 'textarea') {
      return `
        <label class="field ${field.full ? 'full' : ''}">
          <span>${label}</span>
          <textarea name="${name}" ${required}>${this.escape(value)}</textarea>
        </label>
      `;
    }

    return `
      <label class="field ${field.full ? 'full' : ''}">
        <span>${label}</span>
        <input type="${this.escape(field.type || 'text')}" name="${name}" value="${this.escape(value)}" ${required}>
      </label>
    `;
  },

  form(fields, submitLabel = 'Save', extra = '') {
    return `
      <form class="stack-form js-action-form">
        <div class="form-grid">${fields.map((field) => this.field(field)).join('')}</div>
        ${extra}
        <div class="modal-actions">
          ${this.button('Cancel', { kind: 'btn-ghost', attr: 'data-modal-close' })}
          ${this.button(submitLabel, { kind: 'btn-primary', type: 'submit' })}
        </div>
      </form>
    `;
  },

  showModal(title, body) {
    const root = document.getElementById('modal-root');
    root.innerHTML = `
      <div class="modal-backdrop">
        <section class="modal" role="dialog" aria-modal="true">
          <div class="modal-header"><h2>${this.escape(title)}</h2></div>
          <div class="modal-body">${body}</div>
        </section>
      </div>
    `;
    root.querySelectorAll('[data-modal-close], .modal-backdrop').forEach((element) => {
      element.addEventListener('click', (event) => {
        if (event.target === element || element.hasAttribute('data-modal-close')) this.closeModal();
      });
    });
  },

  closeModal() {
    const root = document.getElementById('modal-root');
    if (root) root.innerHTML = '';
  },

  toast(message, type = '') {
    const root = document.getElementById('toast-root');
    if (!root) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="ph ph-info"></i><span>${this.escape(message)}</span>`;
    root.appendChild(toast);
    window.setTimeout(() => toast.remove(), 3600);
  },

  formData(form) {
    return Object.fromEntries(new FormData(form).entries());
  },

  getItems(data) {
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.items)) return data.items;
    if (Array.isArray(data?.data?.items)) return data.data.items;
    return [];
  }
};
