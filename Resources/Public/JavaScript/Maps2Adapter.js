const ADAPTER_SELECTOR = '.maps2-bayernatlas-adapter';
const ITEM_SELECT_EVENT = 'bayernatlas:item-select';

export function normalizeBoolean(value, fallback = true) {
  if (value === undefined || value === null || value === '') {
    return fallback;
  }

  if (typeof value === 'boolean') {
    return value;
  }

  return !['0', 'false', 'no', 'off'].includes(String(value).toLowerCase());
}

export function claimAdapterElements(root) {
  const elements = [];

  root.querySelectorAll(ADAPTER_SELECTOR).forEach((element) => {
    if (element.dataset.maps2BayernAtlasInitialized === '1') {
      return;
    }

    element.dataset.maps2BayernAtlasInitialized = '1';
    elements.push(element);
  });

  return elements;
}

export class Maps2InfoWindowAdapter {
  constructor(element) {
    this.element = element;
    this.ajaxUrl = element.dataset.ajaxUrl || '';
    this.enabled = normalizeBoolean(element.dataset.infoWindow, true);
    this.errorMessage = element.dataset.errorMessage
      || 'The information could not be loaded.';

    element.addEventListener(ITEM_SELECT_EVENT, (event) => this.handleSelection(event));
  }

  async handleSelection(event) {
    if (!this.enabled || !this.ajaxUrl) {
      return;
    }

    const uid = Number(event.detail?.item?.id);
    if (!Number.isInteger(uid) || uid < 1) {
      return;
    }

    event.preventDefault();
    event.detail.showLoading();

    try {
      const response = await fetch(this.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'ext-maps2': 'infoWindowContent',
        },
        body: JSON.stringify({ poiCollection: uid }),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();

      if (data.error) {
        console.error('maps2_bayernatlas: info window response error', data.error);
        event.detail.showError(this.errorMessage);

        return;
      }

      event.detail.showInfo(data.content || '');
    } catch (error) {
      console.error('maps2_bayernatlas: info window request failed', error);
      event.detail.showError(this.errorMessage);
    }
  }
}

export function initializeAdapters(root = document) {
  return claimAdapterElements(root).map((element) => new Maps2InfoWindowAdapter(element));
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initializeAdapters(), { once: true });
  } else {
    initializeAdapters();
  }
}
