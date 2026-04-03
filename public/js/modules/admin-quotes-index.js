document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('quotes-page-admin');
    if (!page) {
        return;
    }

    const statusUpdateUrlTemplate = page.dataset.statusUpdateUrlTemplate || '';
    const noteUpdateUrlTemplate = page.dataset.noteUpdateUrlTemplate || '';
    const statusSelects = document.querySelectorAll('.quote-status-select');
    const noteButtons = document.querySelectorAll('.save-admin-note-btn');
    const noteStatusTimers = new Map();

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

    const saveAdminNote = async (quoteId) => {
        const noteInput = document.querySelector(`.quote-admin-note[data-quote-id="${quoteId}"]`);
        const noteStatus = document.querySelector(`.admin-note-status[data-quote-id="${quoteId}"]`);
        const button = document.querySelector(`.save-admin-note-btn[data-quote-id="${quoteId}"]`);

        if (!noteInput || !button) {
            return;
        }

        button.disabled = true;
        const originalButtonText = button.textContent;
        button.textContent = 'Saving...';
        if (noteStatus) {
            noteStatus.textContent = 'Saving...';
        }

        if (noteStatusTimers.has(quoteId)) {
            clearTimeout(noteStatusTimers.get(quoteId));
            noteStatusTimers.delete(quoteId);
        }

        try {
            const updateUrl = noteUpdateUrlTemplate.replace('__QUOTE_ID__', quoteId);
            const response = await fetch(updateUrl, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ admin_notes: noteInput.value })
            });

            const data = await response.json().catch(() => ({ success: false, message: 'Invalid server response' }));
            if (response.ok && data.success) {
                if (noteStatus) {
                    noteStatus.textContent = 'Saved';
                }
                button.textContent = 'Saved';
            } else {
                if (noteStatus) {
                    noteStatus.textContent = 'Failed';
                }
                button.textContent = 'Save Note';
                globalThis.show_popup_temp?.('error', 'Error', [data.message || 'Failed to save admin note']);
            }
        } catch (error) {
            console.error(error);
            if (noteStatus) {
                noteStatus.textContent = 'Failed';
            }
            button.textContent = 'Save Note';
            globalThis.show_popup_temp?.('error', 'Error', ['Failed to save admin note']);
        } finally {
            button.disabled = false;
            if (noteStatus) {
                const timerId = setTimeout(() => {
                    noteStatus.textContent = '';
                    button.textContent = originalButtonText || 'Save Note';
                    noteStatusTimers.delete(quoteId);
                }, 1800);
                noteStatusTimers.set(quoteId, timerId);
            } else {
                button.textContent = originalButtonText || 'Save Note';
            }
        }
    };

    noteButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            await saveAdminNote(button.dataset.quoteId);
        });
    });

    document.querySelectorAll('.quote-admin-note').forEach((textarea) => {
        textarea.addEventListener('blur', async () => {
            await saveAdminNote(textarea.dataset.quoteId);
        });
    });
});
