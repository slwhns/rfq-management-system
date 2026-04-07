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
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch('/quotes/generate',{
        method:'POST',
        headers:{
            'Content-Type':'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(data)
    });
    return res.json();
}