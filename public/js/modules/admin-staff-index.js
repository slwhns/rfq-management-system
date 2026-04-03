export function initAdminStaffIndex() {
    const page = document.getElementById('staff-page');
    if (!page) {
        return;
    }

    const parseJson = (value, fallback) => {
        if (!value) {
            return fallback;
        }

        try {
            return JSON.parse(value);
        } catch {
            return fallback;
        }
    };

    const updateRouteTemplate = page.dataset.updateRouteTemplate || '';
    const storeRoute = page.dataset.storeRoute || '';
    const flashType = page.dataset.flashType || '';
    const flashTitle = page.dataset.flashTitle || '';
    const flashMessage = page.dataset.flashMessage || '';
    const hasErrors = page.dataset.hasErrors === 'true';
    const oldEditId = page.dataset.oldEditId || '';
    const oldName = page.dataset.oldName || '';
    const oldEmail = page.dataset.oldEmail || '';
    const oldRole = page.dataset.oldRole || '';
    const oldCompany = page.dataset.oldCompany || '';
    const oldUsername = page.dataset.oldUsername || '';
    const validationMessages = parseJson(page.dataset.errors, []);

    const modalTitle = document.getElementById('staff-modal-title');
    const modalForm = document.getElementById('staff-modal-form');
    const modalMethod = document.getElementById('staff-modal-method');
    const editIdInput = document.getElementById('staff-edit-id');
    const modalSubmit = document.getElementById('staff-modal-submit');
    const passwordLabel = document.getElementById('staff-password-label');
    const passwordHelp = document.getElementById('staff-password-help');

    const nameInput = document.getElementById('staff-modal-name');
    const emailInput = document.getElementById('staff-modal-email');
    const roleInput = document.getElementById('staff-modal-role');
    const companyInput = document.getElementById('staff-modal-company');
    const passwordInput = document.getElementById('staff-modal-password');
    const usernameInput = document.getElementById('staff-modal-username');
    const searchInput = document.getElementById('staff-search');
    const searchEmpty = document.getElementById('staff-search-empty');
    const staffRows = Array.from(document.querySelectorAll('tr[data-staff-row="true"]'));

    const openModalSafe = (modalId) => {
        if (typeof globalThis.openModal === 'function') {
            globalThis.openModal(modalId);
            return;
        }

        document.getElementById('modal-overlay')?.classList.add('active');
        document.getElementById(modalId)?.classList.add('active');
    };

    globalThis.openAddStaffModal = () => {
        if (!modalForm || !modalMethod || !modalSubmit) {
            return;
        }

        modalTitle.textContent = 'Add User';
        modalForm.action = storeRoute;
        modalMethod.value = 'POST';
        editIdInput.value = '';
        modalSubmit.textContent = 'Create User';

        nameInput.value = '';
        emailInput.value = '';
        roleInput.value = 'staff';
        companyInput.value = '';
        passwordInput.value = '';
        passwordInput.required = true;
        passwordLabel.textContent = 'Temporary Password';
        passwordHelp.style.display = 'none';

        if (usernameInput) {
            usernameInput.value = '';
            usernameInput.required = true;
        }

        openModalSafe('staff-modal');
    };

    globalThis.openEditStaffModal = (button) => {
        if (!button || !modalForm || !modalMethod || !modalSubmit) {
            return;
        }

        const staffId = button.dataset.id;
        modalTitle.textContent = 'Edit User';
        modalForm.action = updateRouteTemplate.replace('__STAFF_ID__', staffId || '0');
        modalMethod.value = 'PATCH';
        editIdInput.value = staffId || '';
        modalSubmit.textContent = 'Save Changes';

        nameInput.value = button.dataset.name || '';
        emailInput.value = button.dataset.email || '';
        roleInput.value = button.dataset.role || 'staff';
        companyInput.value = button.dataset.company || '';
        passwordInput.value = '';
        passwordInput.required = false;
        passwordLabel.textContent = 'New Password (Optional)';
        passwordHelp.style.display = 'block';

        if (usernameInput) {
            usernameInput.value = button.dataset.username || '';
            usernameInput.required = true;
        }

        openModalSafe('staff-modal');
    };

    const applySearchFilter = () => {
        const query = String(searchInput?.value || '').trim().toLowerCase();
        let visibleCount = 0;

        staffRows.forEach((row) => {
            const searchable = row.dataset.search || '';
            const visible = query === '' || searchable.includes(query);
            row.style.display = visible ? '' : 'none';
            if (visible) {
                visibleCount += 1;
            }
        });

        if (searchEmpty) {
            searchEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    };

    searchInput?.addEventListener('input', applySearchFilter);

    if (flashMessage) {
        globalThis.show_popup_temp?.(flashType || 'success', flashTitle || 'Success', [flashMessage]);
    }

    if (hasErrors) {
        if (oldEditId) {
            modalTitle.textContent = 'Edit User';
            modalForm.action = updateRouteTemplate.replace('__STAFF_ID__', String(oldEditId));
            modalMethod.value = 'PATCH';
            editIdInput.value = String(oldEditId);
            modalSubmit.textContent = 'Save Changes';
            passwordInput.required = false;
            passwordLabel.textContent = 'New Password (Optional)';
            passwordHelp.style.display = 'block';
        } else {
            globalThis.openAddStaffModal();
        }

        nameInput.value = oldName || '';
        emailInput.value = oldEmail || '';
        roleInput.value = oldRole || 'staff';
        companyInput.value = oldCompany || '';
        if (usernameInput) {
            usernameInput.value = oldUsername || '';
        }

        openModalSafe('staff-modal');

        if (validationMessages.length > 0) {
            globalThis.show_popup_temp?.('error', 'Error', validationMessages);
        }
    }
}
