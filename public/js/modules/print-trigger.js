export function initPrintTriggers() {
    const triggers = Array.from(document.querySelectorAll('[data-print-trigger="true"]'));
    if (triggers.length === 0) {
        return;
    }

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            globalThis.print?.();
        });
    });
}
