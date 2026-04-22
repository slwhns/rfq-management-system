export function initQuotationPage() {
    const page = document.getElementById('quotation-page');
    if (!page) {
        return;
    }

    const quoteNumber = page.dataset.quoteNumber || 'rfq_document';
    const projectName = page.dataset.projectName || 'project';

    const safePart = (value) => String(value || '')
        .trim()
        .replaceAll(' ', '_')
        .replaceAll(/[^A-Za-z0-9_-]/g, '');

    const fileName = `${safePart(quoteNumber)}_${safePart(projectName)}`;
    document.title = fileName;
}