async function api_request(url, method = 'GET', payload = null) {
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

    const options = {
        method,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    if (method !== 'GET' && method !== 'HEAD') {
        options.headers['Content-Type'] = 'application/json';
        options.headers['X-CSRF-TOKEN'] = csrfToken;
        if (payload !== null) {
            options.body = JSON.stringify(payload);
        }
    }

    const response = await fetch(url, options);
    const contentType = response.headers.get('content-type') || '';
    const data = contentType.includes('application/json') ? await response.json() : null;

    if (!response.ok) {
        const errorMessage = data?.message || `Request failed: ${response.status}`;
        throw new Error(errorMessage);
    }

    return data || { success: true };
}

function set_active_nav_by_path(pathname) {
    const navButtons = document.querySelectorAll('.nav-btn[data-route]');

    navButtons.forEach((button) => {
        const route = button.dataset.route || '';
        const isActive = route === 'dashboard'
            ? pathname === '/dashboard' || pathname === '/'
            : pathname.startsWith(`/${route}`);

        button.classList.toggle('app-nav-active', isActive);
    });
}

async function update_html(url, options = {}) {
    const { pushState = true } = options;

    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Navigation failed: ${response.status}`);
        }

        const html = await response.text();
        apply_html_content(html, response.url || url, pushState);
    } catch (error) {
        console.error(error);
        globalThis.location.href = url;
    }
}

function apply_html_content(html, url, pushState = true) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const newContent = doc.getElementById('main-content');
    const currentContent = document.getElementById('main-content');

    if (!newContent || !currentContent) {
        throw new Error('Unable to update page content');
    }

    currentContent.innerHTML = newContent.innerHTML;

    const title = doc.querySelector('title')?.textContent;
    if (title) {
        document.title = title;
    }

    if (pushState) {
        history.pushState({}, '', url);
    }

    const pathname = new URL(url, globalThis.location.origin).pathname;
    set_active_nav_by_path(pathname);
    globalThis.initPageModules?.();
    globalThis.scrollTo({ top: 0, behavior: 'instant' });
}

async function submit_spa_form(form, submitter) {
    const action = form.getAttribute('action') || globalThis.location.href;
    const actionUrl = new URL(action, globalThis.location.href);
    const method = String(form.getAttribute('method') || 'GET').toUpperCase();

    if (method === 'GET') {
        const params = new URLSearchParams(new FormData(form));
        const query = params.toString();
        const suffix = query ? `?${query}` : '';
        const target = `${actionUrl.pathname}${suffix}`;
        await update_html(target);
        return;
    }

    const response = await fetch(actionUrl.toString(), {
        method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'text/html,application/json',
        },
        body: new FormData(form),
    });

    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
        const data = await response.json();

        if (!response.ok) {
            const messages = data?.errors
                ? Object.values(data.errors).flat().map(String)
                : [data?.message || 'Request failed'];
            globalThis.show_popup_temp?.('error', 'Error', messages);
            return;
        }

        if (data?.redirect) {
            await update_html(data.redirect);
            return;
        }

        if (data?.message) {
            globalThis.show_popup_temp?.('success', 'Success', [data.message]);
        }

        await update_html(actionUrl.pathname + actionUrl.search);
        return;
    }

    const html = await response.text();
    apply_html_content(html, response.url || actionUrl.toString(), true);

    if (!response.ok) {
        globalThis.show_popup_temp?.('error', 'Validation', ['Please review the highlighted form fields.']);
    }

    if (submitter) {
        submitter.disabled = false;
    }
}

function custom_nav_click(_element, _groupClass, _activeClass, url) {
    update_html(url);
}

function show_popup_temp(type, title, messages = []) {
    const normalizedType = String(type || 'success').toLowerCase();
    const popupId = 'global-feedback-popup';
    const overlayId = 'global-feedback-popup-overlay';

    document.getElementById(popupId)?.remove();
    document.getElementById(overlayId)?.remove();

    const overlay = document.createElement('div');
    overlay.id = overlayId;
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(0, 0, 0, 0.35)';
    overlay.style.zIndex = '9998';

    const popup = document.createElement('div');
    popup.id = popupId;
    popup.style.position = 'fixed';
    popup.style.top = '50%';
    popup.style.left = '50%';
    popup.style.transform = 'translate(-50%, -50%)';
    popup.style.width = 'min(92vw, 440px)';
    popup.style.background = '#ffffff';
    popup.style.borderRadius = '10px';
    popup.style.boxShadow = '0 16px 50px rgba(0, 0, 0, 0.28)';
    popup.style.overflow = 'hidden';
    popup.style.zIndex = '9999';

    let accentColor = '#2f55c7';
    if (normalizedType === 'success') {
        accentColor = '#1f8a4c';
    } else if (normalizedType === 'error') {
        accentColor = '#bf2f2f';
    }

    const header = document.createElement('div');
    header.style.padding = '12px 16px';
    header.style.color = '#ffffff';
    header.style.fontWeight = '700';
    header.style.fontSize = '14px';
    header.textContent = title || (normalizedType === 'success' ? 'Success' : 'Notice');
    header.style.background = accentColor;

    const body = document.createElement('div');
    body.style.padding = '14px 16px 16px';
    body.style.color = '#2a2a2a';
    body.style.fontSize = '13px';

    const contentLines = Array.isArray(messages) ? messages : [String(messages || '')];
    body.innerHTML = contentLines
        .filter(Boolean)
        .map((message) => `<div style="margin-bottom:6px;">${String(message)}</div>`)
        .join('') || '<div>Operation completed.</div>';

    const footer = document.createElement('div');
    footer.style.padding = '0 16px 14px';
    footer.style.textAlign = 'right';

    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'OK';
    button.style.border = '0';
    button.style.borderRadius = '6px';
    button.style.padding = '8px 14px';
    button.style.cursor = 'pointer';
    button.style.fontWeight = '600';
    button.style.color = '#ffffff';
    button.style.background = accentColor;

    const closePopup = () => {
        popup.remove();
        overlay.remove();
    };

    button.addEventListener('click', closePopup);
    overlay.addEventListener('click', closePopup);

    footer.appendChild(button);
    popup.appendChild(header);
    popup.appendChild(body);
    popup.appendChild(footer);

    document.body.appendChild(overlay);
    document.body.appendChild(popup);
}

globalThis.api_request = api_request;
globalThis.custom_nav_click = custom_nav_click;
globalThis.show_popup_temp = show_popup_temp;
globalThis.update_html = update_html;

globalThis.addEventListener('popstate', () => {
    update_html(globalThis.location.pathname, { pushState: false });
});

document.addEventListener('click', (event) => {
    const anchor = event.target.closest('a[href]');

    if (!anchor) {
        return;
    }

    const href = anchor.getAttribute('href') || '';
    const isExternal = anchor.origin !== globalThis.location.origin;
    const isSpecial = href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:');
    const hasTarget = anchor.getAttribute('target');
    const isOptedOut = anchor.dataset.noSpa === 'true' || anchor.hasAttribute('download');

    if (isExternal || isSpecial || hasTarget || isOptedOut) {
        return;
    }

    event.preventDefault();
    update_html(anchor.pathname + anchor.search);
});

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (form.dataset.noSpa === 'true' || form.getAttribute('target')) {
        return;
    }

    const action = form.getAttribute('action') || globalThis.location.href;
    const actionUrl = new URL(action, globalThis.location.href);
    if (actionUrl.origin !== globalThis.location.origin) {
        return;
    }

    event.preventDefault();

    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    if (submitter && 'disabled' in submitter) {
        submitter.disabled = true;
    }

    try {
        await submit_spa_form(form, submitter);
    } catch (error) {
        console.error(error);
        globalThis.location.href = actionUrl.toString();
    } finally {
        if (submitter && 'disabled' in submitter) {
            submitter.disabled = false;
        }
    }
});
