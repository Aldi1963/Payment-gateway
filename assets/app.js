/**
 * PayGate Pro - Frontend JavaScript
 * Vanilla JS - No frameworks
 */

// ===========================================
// Sidebar Toggle (Mobile)
// ===========================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!sidebar) return;

    sidebar.classList.toggle('-translate-x-full');
    if (overlay) {
        overlay.classList.toggle('hidden');
    }
}

// ===========================================
// Toast Notifications
// ===========================================
function showToast(message, type = 'success', duration = 3000) {
    const container = getToastContainer();
    const toast = document.createElement('div');
    
    const bgColor = type === 'success' ? 'bg-emerald-600' : 
                    type === 'error' ? 'bg-red-600' : 'bg-blue-600';
    
    toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium flex items-center gap-2 transform translate-x-full transition-transform duration-300`;
    toast.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-white/70 hover:text-white">&times;</button>
    `;
    
    container.appendChild(toast);
    
    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-x-full');
    });
    
    // Auto remove
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function getToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-[100] space-y-2';
        document.body.appendChild(container);
    }
    return container;
}

// ===========================================
// Copy to Clipboard
// ===========================================
function copyToClipboard(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    
    const text = el.value || el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        showToast('Berhasil disalin!');
    }).catch(() => {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Berhasil disalin!');
    });
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Berhasil disalin!');
    }).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Berhasil disalin!');
    });
}

// ===========================================
// Format Currency (Client-side)
// ===========================================
function formatCurrency(amount) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
}

// ===========================================
// Confirm Actions
// ===========================================
function confirmAction(message, callback) {
    if (confirm(message)) {
        if (typeof callback === 'function') callback();
        return true;
    }
    return false;
}

// ===========================================
// Loading State
// ===========================================
function setLoading(button, loading = true) {
    if (loading) {
        button.disabled = true;
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = `<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>Loading...`;
    } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || 'Submit';
    }
}

// ===========================================
// AJAX Helper
// ===========================================
async function apiRequest(url, options = {}) {
    const defaults = {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    };

    const config = { ...defaults, ...options };
    config.headers = { ...defaults.headers, ...options.headers };

    try {
        const response = await fetch(url, config);
        const data = await response.json();
        return { ok: response.ok, status: response.status, data };
    } catch (error) {
        return { ok: false, status: 0, data: { error: error.message } };
    }
}

// ===========================================
// Auto-dismiss flash messages
// ===========================================
document.addEventListener('DOMContentLoaded', function() {
    // Auto dismiss flash messages after 5 seconds
    document.querySelectorAll('[role="alert"]').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            alert.style.transition = 'all 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
                toggleSidebar();
            }
            // Close any open modals
            document.querySelectorAll('[id$="Modal"]').forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                }
            });
        }
    });

    // Format number inputs with thousand separators (display only)
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('focus', function() {
            this.select();
        });
    });
});

// ===========================================
// Table Search (client-side filter)
// ===========================================
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
}

// ===========================================
// Date/Time formatting
// ===========================================
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { 
        day: '2-digit', month: 'short', year: 'numeric', 
        hour: '2-digit', minute: '2-digit' 
    });
}
