// js/pwa.js — Clean PWA, native prompt only
(function () {

  // Register Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then(reg => {
          console.log('[PWA] SW registered:', reg.scope);
          setInterval(() => reg.update(), 60_000);
        })
        .catch(err => console.error('[PWA] SW failed:', err));
    });
  }

  // Let browser handle install prompt natively — no custom UI
  window.addEventListener('beforeinstallprompt', e => {
    console.log('[PWA] Install prompt ready');
    // Do NOT call e.preventDefault() — browser shows native prompt
  });

  // Offline queue for orders
  window.offlineQueue = {
    async save(orderPayload) {
      const db = await openOfflineDB();
      const tx = db.transaction('pending_orders', 'readwrite');
      tx.objectStore('pending_orders').add({ payload: orderPayload, saved_at: new Date().toISOString() });
      return new Promise((res, rej) => {
        tx.oncomplete = () => res(true);
        tx.onerror    = e  => rej(e.target.error);
      });
    },
    async triggerSync() {
      if ('serviceWorker' in navigator && 'SyncManager' in window) {
        const reg = await navigator.serviceWorker.ready;
        await reg.sync.register('sync-orders');
      }
    }
  };

  function openOfflineDB() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open('lunas_pos_offline', 1);
      req.onupgradeneeded = e => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains('pending_orders')) {
          db.createObjectStore('pending_orders', { keyPath: 'id', autoIncrement: true });
        }
      };
      req.onsuccess = e => resolve(e.target.result);
      req.onerror   = e => reject(e.target.error);
    });
  }

  // Online/offline indicator
  function updateOnlineStatus() {
    let el = document.getElementById('pwa-online-status');
    if (!el) {
      el = document.createElement('div');
      el.id = 'pwa-online-status';
      el.style.cssText = 'position:fixed;top:10px;right:10px;z-index:88888;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;pointer-events:none;transition:opacity 0.5s;font-family:sans-serif;';
      document.body.appendChild(el);
    }
    if (navigator.onLine) {
      el.style.cssText += 'background:#dcfce7;color:#16a34a;opacity:1;';
      el.textContent = '● Online';
      setTimeout(() => el.style.opacity = '0', 2000);
    } else {
      el.style.cssText += 'background:#fef2f2;color:#dc2626;opacity:1;';
      el.textContent = '⚠ Offline';
    }
  }

  window.addEventListener('online', updateOnlineStatus);
  window.addEventListener('offline', updateOnlineStatus);

})();
