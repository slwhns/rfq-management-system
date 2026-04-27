export function initPurchaseRequestProjects() {
    const projectSelect = document.getElementById('quotation-project-select');
    const projectList = document.getElementById('quotation-project-list');
    const generateButton = document.getElementById('quotation-generate-btn');

    if (!projectSelect || !projectList || !generateButton || typeof globalThis.api_request !== 'function') {
        return;
    }

    const parseProjects = (response) => {
        if (Array.isArray(response?.data)) {
            return response.data;
        }

        if (Array.isArray(response?.data?.data)) {
            return response.data.data;
        }

        return [];
    };

    const projectDisplayName = (project) => {
        const name = String(project?.project_name || project?.name || '').trim();
        const title = String(project?.project_title || '').trim();

        if (name && title && name.toLowerCase() !== title.toLowerCase()) {
            return `${name} - ${title}`;
        }

        return name || title || 'Untitled Project';
    };

    const renderProjects = (projects) => {
        if (projects.length === 0) {
            projectList.innerHTML = '<li class="pd-10 clr-grey1">No projects found.</li>';
            projectSelect.value = '';
            return;
        }

        const currentSelectedId = Number(projectSelect.value || 0);
        const selectedProjectId = projects.some((project) => project.id === currentSelectedId)
            ? currentSelectedId
            : projects[0].id;

        projectSelect.value = String(selectedProjectId);

        projectList.innerHTML = '';

        projects.forEach((project) => {
            const li = document.createElement('li');
            const isSelected = project.id === selectedProjectId;
            const projectName = projectDisplayName(project);
            const location = project.location || '-';
            const projectType = project.project_type || '-';

            li.className = `pd-10 cursor-pointer bdr-bottom-22 ${isSelected ? 'app-selected-item bg-blue clr-white br-10' : ''}`;
            li.style.border = '0';
            li.innerHTML = `
                <div class="fw-bold mg-b-5">${projectName}</div>
                <div class="fs-12 ${isSelected ? 'clr-white' : 'clr-grey1'}">${location} • ${projectType}</div>
            `;

            li.addEventListener('click', () => {
                projectSelect.value = String(project.id);
                projectSelect.dispatchEvent(new Event('change'));
                renderProjects(projects);
            });

            projectList.appendChild(li);
        });
    };

    const loadProjects = async () => {
        try {
            const response = await globalThis.api_request('/api/projects', 'GET');
            const projects = parseProjects(response).sort((left, right) => {
                const leftName = projectDisplayName(left);
                const rightName = projectDisplayName(right);
                return leftName.localeCompare(rightName, undefined, { sensitivity: 'base', numeric: true });
            });

            renderProjects(projects);
        } catch (error) {
            console.error(error);
            projectList.innerHTML = '<li class="pd-10 clr-grey1">Failed to load projects.</li>';
            projectSelect.value = '';
        }
    };

    generateButton.addEventListener('click', async () => {
        const projectId = Number(projectSelect.value || 0);
        if (!projectId) {
            globalThis.show_popup_temp?.('error', 'Validation Error', ['Please select a project first']);
            return;
        }

        try {
            const response = await globalThis.api_request('/quotes/generate', 'POST', { project_id: projectId });
            const quoteNumbers = response?.data?.quote_numbers;
            if (Array.isArray(quoteNumbers) && quoteNumbers.length > 0) {
                globalThis.show_popup_temp?.('success', 'Purchase Request Created', quoteNumbers);
                globalThis.location.reload();
                return;
            }

            const quoteNumber = response?.data?.quote_number;
            if (quoteNumber) {
                globalThis.show_popup_temp?.('success', 'Purchase Request Created', [quoteNumber]);
                globalThis.location.reload();
                return;
            }

            globalThis.show_popup_temp?.('success', 'Success', ['Purchase Request generated']);
            globalThis.location.reload();
        } catch (error) {
            console.error(error);
            globalThis.show_popup_temp?.('error', 'Error', [error?.message || 'Failed to generate Purchase Request']);
        }
    });

    loadProjects();
}
