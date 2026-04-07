function formatAmount(value) {
    const amount = Number(value || 0);
    if (!Number.isFinite(amount)) {
        return '0';
    }

    const hasDecimal = Math.abs(amount % 1) > 0;
    return amount.toLocaleString('en-MY', {
        minimumFractionDigits: hasDecimal ? 2 : 0,
        maximumFractionDigits: 2,
    });
}

function formatCurrency(value, currency = 'RM') {
    return `${currency}${formatAmount(value)}`;
}

function updateSummaryUI(summary) {
    const subtotalEl = document.getElementById('subtotal');
    const taxEl = document.getElementById('tax');
    const totalEl = document.getElementById('total');

    if (subtotalEl) subtotalEl.textContent = formatCurrency(summary.after_discount ?? summary.subtotal ?? 0, 'RM');
    if (taxEl) taxEl.textContent = formatCurrency(summary.tax_amount ?? summary.tax ?? 0, 'RM');
    if (totalEl) totalEl.textContent = formatCurrency(summary.total ?? 0, 'RM');
}

export async function calculatePricing(getCurrentProjectId) {
    try {
        const projectId = getCurrentProjectId?.();
        if (!projectId) {
            globalThis.show_popup_temp('error', 'Error', ['Please select a project first']);
            return;
        }

        const response = await globalThis.api_request(`/api/calculate/${projectId}`, 'GET');
        const summary = response?.data || {};
        updateSummaryUI(summary);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', ['Failed to calculate price']);
    }
}

export async function generatePricingQuote(getCurrentProjectId) {
    try {
        const projectId = getCurrentProjectId?.();
        if (!projectId) {
            globalThis.show_popup_temp('error', 'Error', ['Please select a project first']);
            return;
        }

        const response = await globalThis.api_request('/quotes/generate', 'POST', {
            project_id: projectId,
        });

        const quoteNumbers = response?.data?.quote_numbers;
        if (Array.isArray(quoteNumbers) && quoteNumbers.length > 0) {
            globalThis.show_popup_temp('success', 'Purchase Request Created', quoteNumbers);
            setTimeout(() => {
                globalThis.location.href = '/quotes';
            }, 250);
            return;
        }

        const quoteNumber = response?.data?.quote_number;
        if (quoteNumber) {
            globalThis.show_popup_temp('success', 'Purchase Request Created', [quoteNumber]);
            setTimeout(() => {
                globalThis.location.href = '/quotes';
            }, 250);
            return;
        }

        globalThis.show_popup_temp('success', 'Success', ['Purchase Request generated']);
        setTimeout(() => {
            globalThis.location.href = '/quotes';
        }, 250);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', ['Failed to generate Purchase Request']);
    }
}
