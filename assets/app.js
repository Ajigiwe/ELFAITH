/**
 * EL-FAITH MICROFINANCE - Progressive Web App Core Library
 * Version: 2.1.0-PROD
 * Features:
 *  - Service Worker Registration & Scope Management
 *  - Offline Storage for Transactions (Local Queue)
 *  - Offline Loans & Customer Cache for Field Collecting
 *  - Background & Automatic Queue Syncing to Backend API
 *  - PWA Install Prompt Hook
 *  - GPS Location Capture
 *  - Non-blocking UUID Generator
 */

// 1. Universal UUIDv4 Generator (Safe on HTTPS, HTTP, local development, mobile WebViews)
function generateUUID() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        try {
            return crypto.randomUUID();
        } catch (e) {}
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

// 2. Non-blocking GPS Capture Helper
function captureAgentGps(callback) {
    if (typeof navigator !== 'undefined' && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                if (position && position.coords) {
                    callback(position.coords.latitude, position.coords.longitude);
                } else {
                    callback(null, null);
                }
            },
            error => {
                console.warn("GPS capture skipped or denied:", error ? error.message : 'Unknown');
                callback(null, null);
            },
            { enableHighAccuracy: false, timeout: 6000, maximumAge: 60000 }
        );
    } else {
        callback(null, null);
    }
}

// 3. Offline Data Engine: Collections Queue & Loan Cache
const OfflineStorage = {
    STORAGE_KEY: 'elfaith_offline_collections',
    LOANS_CACHE_KEY: 'elfaith_cached_loans',
    isSyncing: false,

    // --- Collections Queue Methods ---
    getAll() {
        try {
            return JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '[]');
        } catch (e) {
            return [];
        }
    },

    saveAll(items) {
        try {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items));
        } catch (e) {
            console.error('Failed to save collections to localStorage:', e);
        }
        this.updateUI();
    },

    add(item) {
        const items = this.getAll();
        // Prevent duplicate addition of the same UUID
        if (!items.some(i => i.uuid === item.uuid)) {
            items.push(item);
            this.saveAll(items);
        }
    },

    remove(uuid) {
        const items = this.getAll().filter(i => i.uuid !== uuid);
        this.saveAll(items);
    },

    clear() {
        try {
            localStorage.removeItem(this.STORAGE_KEY);
        } catch (e) {}
        this.updateUI();
    },

    count() {
        return this.getAll().length;
    },

    totalPesewas() {
        return this.getAll().reduce((sum, item) => sum + (parseInt(item.amount_pesewas) || 0), 0);
    },

    // --- Borrower / Active Loans Offline Cache ---
    cacheLoans(loansArray) {
        if (!Array.isArray(loansArray) || loansArray.length === 0) return;
        try {
            localStorage.setItem(this.LOANS_CACHE_KEY, JSON.stringify({
                timestamp: new Date().toISOString(),
                loans: loansArray
            }));
            console.log(`[PWA] Cached ${loansArray.length} active borrower facilities for offline use.`);
        } catch (e) {
            console.warn('[PWA] Failed to cache loans locally:', e);
        }
    },

    getCachedLoans() {
        try {
            const data = JSON.parse(localStorage.getItem(this.LOANS_CACHE_KEY) || '{}');
            return data.loans || [];
        } catch (e) {
            return [];
        }
    },

    // --- UI Badge & Counter Updates ---
    updateUI() {
        const count = this.count();
        const badge = document.getElementById('offline-count-badge');
        const mobBadge = document.getElementById('mobile-offline-badge');
        const queueBadge = document.getElementById('queue-count-badge');

        if (badge) {
            badge.textContent = count + ' Pending';
        }
        if (mobBadge) {
            if (count > 0) {
                mobBadge.classList.remove('hidden');
                mobBadge.textContent = count > 9 ? '9+' : count;
            } else {
                mobBadge.classList.add('hidden');
            }
        }
        if (queueBadge) {
            queueBadge.textContent = count + ' Items';
        }

        // Notify queue page if active
        if (typeof renderQueue === 'function') {
            try { renderQueue(); } catch (e) {}
        }
    }
};

// 4. Background Sync Manager
async function syncPendingCollections(baseUrl) {
    if (OfflineStorage.isSyncing) return;
    const pending = OfflineStorage.getAll();
    if (!pending || pending.length === 0) return;

    if (!navigator.onLine) {
        console.log('[PWA Sync] Skipping sync: Device is offline.');
        return;
    }

    OfflineStorage.isSyncing = true;
    const url = (baseUrl ? baseUrl.replace(/\/$/, '') : '') + '/api/sync_offline.php';

    // Update banner during sync
    const bannerText = document.getElementById('offline-banner-text');
    if (bannerText) {
        bannerText.textContent = `Synchronizing ${pending.length} offline payment(s)...`;
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ collections: pending })
        });

        if (response.ok) {
            const result = await response.json();
            if (result.synced_uuids && result.synced_uuids.length > 0) {
                result.synced_uuids.forEach(uuid => OfflineStorage.remove(uuid));
                console.log(`[PWA Sync] Successfully synchronized ${result.synced_uuids.length} collections.`);
                showToast(`✓ Synced ${result.synced_uuids.length} offline payment(s) to server!`, 'success');
            }
        } else {
            console.warn('[PWA Sync] Sync API returned status:', response.status);
        }
    } catch (err) {
        console.warn('[PWA Sync] Network error during synchronization (will retry automatically):', err);
    } finally {
        OfflineStorage.isSyncing = false;
        OfflineStorage.updateUI();
        // Reset banner text
        if (bannerText) {
            bannerText.textContent = 'Offline Mode • Collections saved locally on device';
        }
    }
}

// 5. Toast Notification System
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('pwa-toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'pwa-toast-container';
        toastContainer.className = 'fixed bottom-20 md:bottom-6 right-4 z-50 flex flex-col gap-2 max-w-sm pointer-events-none';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    const bgClass = (type === 'success') ? 'bg-blue-600 text-white border border-blue-500' : 'bg-black text-white border border-zinc-800';
    toast.className = `${bgClass} p-3 rounded-lg shadow-lg text-xs font-medium flex items-center justify-between gap-2 pointer-events-auto transition-all duration-300 transform translate-y-2 opacity-0`;
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <span>${message}</span>
        </div>
    `;

    toastContainer.appendChild(toast);
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    });

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// 6. Network Monitor & Service Worker Initializer
function initNetworkMonitor(baseUrl) {
    const banner = document.getElementById('offline-banner');

    function updateNetworkStatus() {
        if (!navigator.onLine) {
            if (banner) banner.classList.remove('hidden');
        } else {
            if (banner) banner.classList.add('hidden');
            // When connection is restored, trigger auto-sync
            syncPendingCollections(baseUrl);
        }
    }

    // Sync company branding for offline fallback views
    try {
        if (window.COMPANY_LOGO_URL) {
            localStorage.setItem('elfaith_company_logo', window.COMPANY_LOGO_URL);
        } else if (window.COMPANY_LOGO_URL === null) {
            localStorage.removeItem('elfaith_company_logo');
        }
        if (window.COMPANY_DISPLAY_NAME) {
            localStorage.setItem('elfaith_company_name', window.COMPANY_DISPLAY_NAME);
        }
    } catch (e) {}

    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);
    updateNetworkStatus();
    OfflineStorage.updateUI();

    // Register PWA Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const swUrl = (baseUrl ? baseUrl.replace(/\/$/, '') : '') + '/sw.js';
            const scopeUrl = (baseUrl ? baseUrl.replace(/\/$/, '') : '') + '/';

            navigator.serviceWorker.register(swUrl, { scope: scopeUrl })
                .then(registration => {
                    console.log('[PWA] Service Worker active with scope:', registration.scope);
                })
                .catch(err => {
                    console.warn('[PWA] Service Worker registration failed:', err);
                });
        });
    }

    // Capture PWA Install Prompt (Add to Home Screen)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.deferredPwaPrompt = e;
        const installBtns = document.querySelectorAll('.pwa-install-trigger');
        installBtns.forEach(b => b.classList.remove('hidden'));
    });
}

// Trigger manual install when user clicks "Install App" button
function promptPwaInstall() {
    if (window.deferredPwaPrompt) {
        window.deferredPwaPrompt.prompt();
        window.deferredPwaPrompt.userChoice.then(choice => {
            if (choice.outcome === 'accepted') {
                console.log('[PWA] User accepted PWA installation');
            }
            window.deferredPwaPrompt = null;
            document.querySelectorAll('.pwa-install-trigger').forEach(b => b.classList.add('hidden'));
        });
    }
}
