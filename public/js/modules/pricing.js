import API from '../api/client.js';

/**
 * Pricing Module
 */
export async function calculatePrice(projectId) {
    try {
        const response = await API.calculatePrice(projectId);
        const data = response.data || response;
        
        updateSummaryUI(data);
        return data;
    } catch (error) {
        console.error('Failed to calculate price:', error);
        showNotification('Failed to calculate price', 'error');
    }
}

function updateSummaryUI(data) {
    document.getElementById('subtotal').textContent = formatNumber(data.subtotal);
    document.getElementById('discount').textContent = formatNumber(data.total_discount);
    document.getElementById('tax').textContent = formatNumber(data.tax_amount);
    document.getElementById('total').textContent = formatNumber(data.total);
    
    // Update discounts list
    const discountsContainer = document.getElementById('discounts-list');
    if (discountsContainer && data.discounts.length > 0) {
        discountsContainer.innerHTML = data.discounts.map(d => `
            <div class="discount-item">
                <span>${d.rule}</span>
                <span>-$${formatNumber(d.amount)}</span>
            </div>
        `).join('');
    }
}

export async function generateQuote(projectId) {
    try {
        const response = await API.generateQuote(projectId);
        
        if (response.success) {
            const quoteNumbers = response?.data?.quote_numbers;
            if (Array.isArray(quoteNumbers) && quoteNumbers.length > 0) {
                showNotification(`Quotes generated: ${quoteNumbers.join(', ')}`, 'success');
            } else {
                showNotification(`Quote ${response.data.quote_number} generated!`, 'success');
            }
            
            const quoteElement = document.getElementById('quote-number');
            if (quoteElement) {
                quoteElement.textContent = Array.isArray(quoteNumbers) && quoteNumbers.length > 0
                    ? quoteNumbers.join(', ')
                    : response.data.quote_number;
            }
        }
    } catch (error) {
        console.error('Failed to generate Purchase Request:', error);
        showNotification('Failed to generate Purchase Request', 'error');
    }
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

function showNotification(message, type) {
    const event = new CustomEvent('show-notification', { 
        detail: { message, type } 
    });
    document.dispatchEvent(event);
}