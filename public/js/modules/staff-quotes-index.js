document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('quotes-page-staff');
    if (!page) {
        return;
    }

    const filterForm = document.getElementById('quote-filter-form');
    const statusFilter = document.getElementById('quote-status-filter');
    const projectSearchInput = document.getElementById('quote-project-search');
    const resetButton = document.getElementById('quote-filter-reset');
    let filterSubmitTimer = null;


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


});
