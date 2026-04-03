export function initPurchaseOrders() {
    const page = document.getElementById('purchase-orders-create-page') || document.getElementById('purchase-orders-page');
    if (!page) {
        return;
    }

    const refreshButton = document.getElementById('purchase-order-refresh');
    const templateStatus = page.dataset.poTemplateStatus || 'pending';

    const renderStatus = () => {
        page.dataset.poTemplateStatus = templateStatus;
        const statusBadge = page.querySelector('[data-po-status-badge]');
        if (statusBadge) {
            const prNumber = page.dataset.prNumber ? ` for PR ${page.dataset.prNumber}` : '';
            statusBadge.textContent = `PO scaffold${prNumber}: ${templateStatus}`;
        }
    };

    refreshButton?.addEventListener('click', () => {
        globalThis.show_popup_temp?.('success', 'Success', ['Purchase Order scaffold is ready for the template next step.']);
    });

    if (page.id === 'purchase-orders-create-page' && page.dataset.poDownload === '1') {
        setTimeout(() => {
            globalThis.print?.();
        }, 250);
    }

    renderStatus();
}
