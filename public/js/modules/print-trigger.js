export function initPrintTriggers() {
    const triggers = Array.from(document.querySelectorAll('[data-print-trigger="true"]'));
    if (triggers.length === 0) {
        return;
    }

    const sanitizeFilePart = (value) => String(value || '')
        .trim()
        .replaceAll(' ', '_')
        .replaceAll(/[^A-Za-z0-9_-]/g, '');

    const restoreTitleAfterPrint = (originalTitle) => {
        const restore = () => {
            document.title = originalTitle;
            globalThis.removeEventListener?.('afterprint', restore);
        };

        globalThis.addEventListener?.('afterprint', restore, { once: true });
        globalThis.setTimeout?.(restore, 1500);
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();

            const originalTitle = document.title;
            const explicitName = sanitizeFilePart(trigger.dataset.printFilename || '');
            const quoteId = sanitizeFilePart(trigger.dataset.printQuoteId || '');
            const projectName = sanitizeFilePart(trigger.dataset.printProjectName || '');

            let printFileName = explicitName;
            if (!printFileName && quoteId && projectName) {
                printFileName = `${quoteId}_${projectName}`;
            }

            if (printFileName) {
                document.title = printFileName;
            }

            globalThis.print?.();

            if (printFileName) {
                restoreTitleAfterPrint(originalTitle);
            }
        });
    });
}
