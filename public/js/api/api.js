export async function getCategories() {
    const res = await fetch('/api/categories');
    return res.json();
}

export async function getComponents(categoryId) {
    const res = await fetch(`/api/components/${categoryId}`);
    return res.json();
}

export async function addComponent(data) {
    const res = await fetch('/api/add-component',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(data)
    });
    return res.json();
}

export async function calculatePrice(projectId) {
    const res = await fetch(`/api/calculate/${projectId}`);
    return res.json();
}

export async function generateQuote(data) {
    const res = await fetch('/api/generate-quote',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(data)
    });
    return res.json();
}