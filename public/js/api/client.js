/**
 * API Client - Centralized API calls
 */
const API = {
    baseUrl: '/api',

    async request(endpoint, options = {}) {
        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...options.headers
                },
                ...options
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'API request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    // Categories
    async getCategories() {
        return this.request('/categories');
    },

    // Components
    async getComponents(categoryId) {
        return this.request(`/components/${categoryId}`);
    },

    // Project Components
    async addComponent(projectId, componentId, quantity) {
        return this.request('/add-component', {
            method: 'POST',
            body: JSON.stringify({
                project_id: projectId,
                component_id: componentId,
                quantity: parseInt(quantity)
            })
        });
    },

    async removeComponent(componentId) {
        return this.request(`/components/${componentId}`, {
            method: 'DELETE'
        });
    },

    // Pricing
    async calculatePrice(projectId) {
        return this.request(`/calculate/${projectId}`);
    },

    // Quotes
    async generateQuote(projectId) {
        return this.request('/generate-quote', {
            method: 'POST',
            body: JSON.stringify({ project_id: projectId })
        });
    },

    async getQuotes() {
        return this.request('/quotes');
    }
};

export default API;