import { toDataURL } from 'qrcode';
import Chart from 'chart.js/auto';

function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    return new Promise((resolve, reject) => {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.readOnly = true;
        ta.style.position = 'fixed';
        ta.style.top = '0';
        ta.style.left = '0';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        ta.setSelectionRange(0, ta.value.length);
        try {
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (ok) resolve();
            else reject(new Error('copy failed'));
        } catch (e) {
            document.body.removeChild(ta);
            reject(e);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Salin kode/teks referensi pembayaran
    document.querySelectorAll('[data-copy-text]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const value = btn.getAttribute('data-copy-text') || '';
            const label = btn.getAttribute('data-copy-label') || btn.textContent;
            if (!value) return;
            copyText(value).then(() => {
                btn.textContent = 'Tersalin';
                setTimeout(() => { btn.textContent = label; }, 2000);
            }).catch(() => {
                btn.textContent = 'Gagal menyalin';
                setTimeout(() => { btn.textContent = label; }, 2000);
            });
        });
    });

    // QR Code pembayaran client-side (dengan fallback teks server)
    document.querySelectorAll('[data-qr-url]').forEach((slot) => {
        const url = slot.getAttribute('data-qr-url');
        const img = slot.querySelector('img.qr-image');
        if (!url || !img) return;

        toDataURL(url.slice(0, 2048), { scale: 8 })
            .then((dataUrl) => {
                img.src = dataUrl;
                img.hidden = false;
                const fallback = slot.querySelector('.qr-fallback');
                if (fallback) fallback.hidden = true;
            })
            .catch(() => {
                // Fallback teks server tetap tampil
            });
    });

    // Toggle tampilkan/sembunyikan kata sandi
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        const input = document.getElementById(btn.getAttribute('data-password-toggle'));
        if (!input) return;
        const iconShow = btn.querySelector('[data-icon-show]');
        const iconHide = btn.querySelector('[data-icon-hide]');
        btn.hidden = false;
        btn.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            btn.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            if (iconShow) iconShow.hidden = reveal;
            if (iconHide) iconHide.hidden = !reveal;
        });
    });

    // Dialog ajakan masuk untuk pengunjung di halaman produk
    document.querySelectorAll('[data-guest-popup-open]').forEach((opener) => {
        const popup = document.getElementById(opener.getAttribute('data-guest-popup-open'));
        if (!popup) return;
        const panel = popup.querySelector('[role="dialog"]');
        const focusables = () => panel.querySelectorAll('a[href], button:not([disabled])');

        const close = () => {
            popup.hidden = true;
            document.removeEventListener('keydown', onKey);
            opener.focus();
        };
        const onKey = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
                return;
            }
            if (e.key === 'Tab') {
                const items = focusables();
                if (items.length === 0) return;
                const first = items[0];
                const last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };

        opener.addEventListener('click', () => {
            popup.hidden = false;
            document.addEventListener('keydown', onKey);
            panel.focus();
        });
        popup.querySelectorAll('[data-guest-popup-close]').forEach((el) => el.addEventListener('click', close));
    });

    // Stepper kuantitas keranjang & detail produk
    document.querySelectorAll('[data-stepper]').forEach((box) => {
        const input = box.querySelector('input[type="number"]');
        const minus = box.querySelector('[data-stepper-minus]');
        const plus = box.querySelector('[data-stepper-plus]');
        if (!input || !minus || !plus) return;
        const step = (delta) => {
            const min = Number(input.min) || 1;
            const max = Number(input.max) || 99;
            const next = Math.min(max, Math.max(min, (Number(input.value) || min) + delta));
            input.value = String(next);
        };
        minus.hidden = false;
        plus.hidden = false;
        minus.addEventListener('click', () => step(-1));
        plus.addEventListener('click', () => step(1));
    });

    // Drawer sidebar admin (< 1024px)
    const sidebar = document.getElementById('admin-sidebar');
    const openBtn = document.querySelector('[data-admin-drawer-open]');
    const backdrop = document.querySelector('.admin-sidebar-backdrop');
    if (sidebar && openBtn) {
        const closeDrawer = () => {
            sidebar.classList.remove('is-open');
            openBtn.setAttribute('aria-expanded', 'false');
            if (backdrop) backdrop.hidden = true;
            document.removeEventListener('keydown', onDrawerKey);
            openBtn.focus();
        };

        const onDrawerKey = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeDrawer();
            }
        };

        openBtn.addEventListener('click', () => {
            const isOpen = sidebar.classList.toggle('is-open');
            openBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (backdrop) backdrop.hidden = !isOpen;
            if (isOpen) {
                document.addEventListener('keydown', onDrawerKey);
                const closeBtn = sidebar.querySelector('.admin-drawer-close');
                if (closeBtn) closeBtn.focus();
            } else {
                document.removeEventListener('keydown', onDrawerKey);
            }
        });

        document.querySelectorAll('[data-admin-drawer-close]').forEach((el) => {
            el.addEventListener('click', closeDrawer);
        });
    }

    // Menu navigasi storefront mobile (< 768px)
    const topbarNav = document.getElementById('topbar-nav');
    const topbarOpenBtn = document.querySelector('[data-topbar-menu-open]');
    const topbarBackdrop = document.querySelector('.topbar-backdrop');
    if (topbarNav && topbarOpenBtn) {
        const closeTopbarMenu = () => {
            topbarNav.classList.remove('is-open');
            topbarOpenBtn.setAttribute('aria-expanded', 'false');
            if (topbarBackdrop) topbarBackdrop.hidden = true;
            document.removeEventListener('keydown', onTopbarKey);
            topbarOpenBtn.focus();
        };

        const onTopbarKey = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeTopbarMenu();
            }
        };

        topbarOpenBtn.addEventListener('click', () => {
            const isOpen = topbarNav.classList.toggle('is-open');
            topbarOpenBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (topbarBackdrop) topbarBackdrop.hidden = !isOpen;
            if (isOpen) {
                document.addEventListener('keydown', onTopbarKey);
            } else {
                document.removeEventListener('keydown', onTopbarKey);
            }
        });

        document.querySelectorAll('[data-topbar-menu-close]').forEach((el) => {
            el.addEventListener('click', closeTopbarMenu);
        });
    }

    // Grafik analitik admin (tema Minimalist Monochrome Slate)
    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('[data-chart]').forEach((canvas) => {
        let config;
        try {
            config = JSON.parse(canvas.dataset.chartConfig || '{}');
        } catch {
            return;
        }
        if (!config.type || !config.data) return;

        const isRupiah = canvas.hasAttribute('data-chart-rupiah');
        const formatNum = (val) => Number(val ?? 0).toLocaleString('id-ID');
        const rupiah = (value) => 'Rp ' + formatNum(value);

        config.options = config.options || {};
        if (prefersReducedMotion) {
            config.options.animation = false;
        }

        if (config.type === 'line') {
            config.options.interaction = {
                mode: 'index',
                intersect: false,
                ...(config.options.interaction || {}),
            };
            if (Array.isArray(config.data.datasets)) {
                const hasAnyFilled = config.data.datasets.some((d) => d.fill !== false);
                config.data.datasets = config.data.datasets.map((ds, idx) => {
                    const isFilled = ds.fill !== false;
                    const stroke = idx === 0 ? '#111827' : (ds.borderColor || '#6B7280');
                    return {
                        borderWidth: idx === 0 ? 2 : 1.75,
                        borderDash: hasAnyFilled && !isFilled && idx > 0 ? [5, 4] : undefined,
                        pointRadius: 2.5,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#FFFFFF',
                        pointBorderColor: stroke,
                        pointBorderWidth: 1.75,
                        ...ds,
                        borderColor: ds.borderColor && !ds.borderColor.includes('6655F5') ? ds.borderColor : stroke,
                        backgroundColor: isFilled ? 'rgba(17, 24, 39, 0.08)' : 'transparent',
                    };
                });
            }
        } else if (config.type === 'bar' && Array.isArray(config.data.datasets)) {
            config.data.datasets = config.data.datasets.map((ds) => ({
                maxBarThickness: 32,
                borderSkipped: false,
                ...ds,
                backgroundColor: Array.isArray(ds.backgroundColor)
                    ? ds.backgroundColor.map((c) => (typeof c === 'string' && c.includes('102, 85, 245') ? '#6B7280' : c))
                    : (ds.backgroundColor && String(ds.backgroundColor).includes('102, 85, 245') ? '#111827' : ds.backgroundColor),
            }));
        }

        config.options.plugins = config.options.plugins || {};
        if (config.options.plugins.legend && config.options.plugins.legend.display) {
            config.options.plugins.legend = {
                ...config.options.plugins.legend,
                labels: {
                    usePointStyle: true,
                    boxWidth: 8,
                    padding: 16,
                    color: '#111827',
                    font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '600' },
                    ...(config.options.plugins.legend.labels || {}),
                },
            };
        }

        config.options.plugins.tooltip = {
            backgroundColor: '#111827',
            titleColor: '#FFFFFF',
            bodyColor: '#F3F4F6',
            titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '700' },
            bodyFont: { family: "'Inter', sans-serif", size: 12 },
            padding: 10,
            cornerRadius: 8,
            displayColors: true,
            boxPadding: 4,
            ...(config.options.plugins.tooltip || {}),
            callbacks: {
                ...((config.options.plugins.tooltip && config.options.plugins.tooltip.callbacks) || {}),
                label: (ctx) => {
                    const rawVal = config.options.indexAxis === 'y'
                        ? (ctx.parsed.x ?? ctx.parsed.y ?? 0)
                        : (ctx.parsed.y ?? ctx.parsed.x ?? 0);
                    const formatted = isRupiah ? rupiah(rawVal) : formatNum(rawVal);
                    const prefix = ctx.dataset && ctx.dataset.label ? ctx.dataset.label + ': ' : '';
                    return ' ' + prefix + formatted;
                },
            },
        };

        const axisTickStyle = {
            color: '#6B7280',
            font: { family: "'Inter', sans-serif", size: 11 },
        };
        const axisGridStyle = {
            color: '#E5E7EB',
            drawTicks: false,
        };

        config.options.scales = config.options.scales || {};
        config.options.scales.x = {
            grid: config.options.indexAxis === 'y' ? axisGridStyle : { display: false },
            border: { display: false },
            ...(config.options.scales.x || {}),
            ticks: {
                ...axisTickStyle,
                ...((config.options.scales.x && config.options.scales.x.ticks) || {}),
            },
        };
        config.options.scales.y = {
            grid: config.options.indexAxis === 'y' ? { display: false } : axisGridStyle,
            border: { display: false },
            ...(config.options.scales.y || {}),
            ticks: {
                ...axisTickStyle,
                ...((config.options.scales.y && config.options.scales.y.ticks) || {}),
                ...(isRupiah ? { callback: (value) => rupiah(value) } : {}),
            },
        };

        new Chart(canvas, config);
    });
});
