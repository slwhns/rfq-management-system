import { projectState } from './projects.js';

function normalizeSortValue(value) {
    return String(value || '').trim();
}

function sortByKey(items, keySelector) {
    return [...items].sort((left, right) => (
        normalizeSortValue(keySelector(left)).localeCompare(normalizeSortValue(keySelector(right)), undefined, {
            sensitivity: 'base',
            numeric: true,
        })
    ));
}

function parseProjects(response) {
    if (Array.isArray(response?.data)) {
        return response.data;
    }

    if (Array.isArray(response?.data?.data)) {
        return response.data.data;
    }

    return [];
}

function projectDisplayName(project) {
    const name = String(project?.project_name || project?.name || '').trim();
    const title = String(project?.project_title || '').trim();

    if (name && title && name.toLowerCase() !== title.toLowerCase()) {
        return `${name} - ${title}`;
    }

    return name || title || 'Untitled Project';
}

function updateCurrentProjectLabel(projectName) {
    const badge = document.getElementById('current-project-badge');
    if (!badge) {
        return;
    }

    badge.textContent = projectName ? `Selected: ${projectName}` : 'No project selected';
}

function renderProjectSelect(select, projects) {
    if (!select) {
        return;
    }

    select.innerHTML = projects.map((project) => {
        const projectName = projectDisplayName(project);
        const location = project.location || '-';
        const projectType = project.project_type || '-';

        return `<option value="${project.id}">${projectName} • ${location} • ${projectType}</option>`;
    }).join('');

    if (projectState.currentProjectId) {
        select.value = String(projectState.currentProjectId);
    }
}

export function getCurrentProjectId() {
    return projectState.currentProjectId;
}

export async function initPricingProjects(onProjectChange) {
    const select = document.getElementById('project-select');
    if (!select) {
        return;
    }

    const response = await globalThis.api_request('/api/projects', 'GET');
    const projects = sortByKey(parseProjects(response), (project) => projectDisplayName(project));

    if (projects.length === 0) {
        select.innerHTML = '<option value="">No projects found</option>';
        projectState.currentProjectId = null;
        updateCurrentProjectLabel(null);
        await onProjectChange?.(null);
        return;
    }

    if (!projectState.currentProjectId || !projects.some((project) => project.id === projectState.currentProjectId)) {
        projectState.currentProjectId = projects[0].id;
    }

    renderProjectSelect(select, projects);

    const selectedProject = projects.find((project) => project.id === projectState.currentProjectId);
    const selectedName = selectedProject ? projectDisplayName(selectedProject) : null;
    updateCurrentProjectLabel(selectedName);

    select.onchange = async () => {
        const selectedId = Number(select.value || 0);
        if (!selectedId) {
            return;
        }

        projectState.currentProjectId = selectedId;

        const project = projects.find((item) => item.id === selectedId);
        updateCurrentProjectLabel(project ? projectDisplayName(project) : null);

        await onProjectChange?.(selectedId);
    };

    await onProjectChange?.(projectState.currentProjectId);
}
