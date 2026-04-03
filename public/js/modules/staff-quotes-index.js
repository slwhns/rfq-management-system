document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('quotes-page-staff');
    if (!page) {
        return;
    }

    const statusUpdateUrlTemplate = page.dataset.statusUpdateUrlTemplate || '';
    const responseUpdateUrlTemplate = page.dataset.responseUpdateUrlTemplate || '';
    const filterForm = document.getElementById('quote-filter-form');
    const statusFilter = document.getElementById('quote-status-filter');
    const projectSearchInput = document.getElementById('quote-project-search');
    const resetButton = document.getElementById('quote-filter-reset');
    let filterSubmitTimer = null;
    const statusSelects = document.querySelectorAll('.quote-status-select');
    const responseButtons = document.querySelectorAll('.save-staff-response-btn');

    const summaryMap = {};
    document.querySelectorAll('.status-summary-chip').forEach((chip) => {
        const status = chip.dataset.status;
        const countElement = chip.querySelector('.status-summary-count');
        summaryMap[status] = countElement;
    });

    const updateSummaryCounts = (oldStatus, newStatus) => {
        if (oldStatus === newStatus) {
            return;
        }

        const oldCountEl = summaryMap[oldStatus];
        const newCountEl = summaryMap[newStatus];

        if (oldCountEl) {
            const oldCount = Number.parseInt(oldCountEl.textContent || '0', 10);
            oldCountEl.textContent = String(Math.max(0, oldCount - 1));
        }

        if (newCountEl) {
            const newCount = Number.parseInt(newCountEl.textContent || '0', 10);
            newCountEl.textContent = String(newCount + 1);
        }
    };

    const submitFilters = () => {
        if (filterForm) {
            filterForm.requestSubmit();
        }
    };

    if (statusFilter) {
        statusFilter.addEventListener('change', submitFilters);
    }

    if (projectSearchInput) {
        projectSearchInput.addEventListener('input', () => {
            if (filterSubmitTimer) {
                clearTimeout(filterSubmitTimer);
            }

            filterSubmitTimer = setTimeout(() => {
                submitFilters();
            }, 350);
        });

        projectSearchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (filterSubmitTimer) {
                    clearTimeout(filterSubmitTimer);
                }
                submitFilters();
            }
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', () => {
            globalThis.location.href = filterForm?.action || globalThis.location.pathname;
        });
    }

    statusSelects.forEach((select) => {
        select.addEventListener('change', async () => {
            const quoteId = select.dataset.quoteId;
            const newStatus = select.value;
            const previousStatus = select.dataset.prevStatus || select.value;

            select.disabled = true;

            try {
                const updateUrl = statusUpdateUrlTemplate.replace('__QUOTE_ID__', quoteId);
                const response = await fetch(updateUrl, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({ status: newStatus })
                });
                const data = await response.json().catch(() => ({ success: false, message: 'Invalid server response' }));

                if (response.ok && data.success) {
                    select.dataset.prevStatus = newStatus;
                    updateSummaryCounts(previousStatus, newStatus);
                    globalThis.show_popup_temp?.('success', 'Success', ['Status updated successfully']);

                    setTimeout(() => {
                        globalThis.location.reload();
                    }, 500);
                } else {
                    select.value = previousStatus;
                    globalThis.show_popup_temp?.('error', 'Error', [data.message || 'Failed to update status']);
                }
            } catch (error) {
                console.error(error);
                select.value = previousStatus;
                globalThis.show_popup_temp?.('error', 'Error', ['Failed to update status']);
            } finally {
                select.disabled = false;
            }
        });
    });

    const saveStaffResponse = async (quoteId) => {
        const responseInput = document.querySelector(`.quote-staff-response[data-quote-id="${quoteId}"]`);
        const responseStatus = document.querySelector(`.staff-response-status[data-quote-id="${quoteId}"]`);
        const button = document.querySelector(`.save-staff-response-btn[data-quote-id="${quoteId}"]`);

        if (!responseInput || !button) {
            return;
        }

        button.disabled = true;
        if (responseStatus) {
            responseStatus.textContent = 'Saving...';
        }

        try {
            const updateUrl = responseUpdateUrlTemplate.replace('__QUOTE_ID__', quoteId);
            const response = await fetch(updateUrl, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ staff_response: responseInput.value })
            });

            const data = await response.json().catch(() => ({ success: false, message: 'Invalid server response' }));
            if (response.ok && data.success) {
                if (responseStatus) {
                    responseStatus.textContent = 'Saved';
                }
            } else {
                if (responseStatus) {
                    responseStatus.textContent = 'Failed';
                }
                globalThis.show_popup_temp?.('error', 'Error', [data.message || 'Failed to save staff response']);
            }
        } catch (error) {
            console.error(error);
            if (responseStatus) {
                responseStatus.textContent = 'Failed';
            }
            globalThis.show_popup_temp?.('error', 'Error', ['Failed to save staff response']);
        } finally {
            button.disabled = false;
            if (responseStatus) {
                setTimeout(() => {
                    responseStatus.textContent = '';
                }, 1200);
            }
        }
    };

    responseButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            await saveStaffResponse(button.dataset.quoteId);
        });
    });

    document.querySelectorAll('.quote-staff-response').forEach((textarea) => {
        textarea.addEventListener('blur', async () => {
            await saveStaffResponse(textarea.dataset.quoteId);
        });
    });
});
