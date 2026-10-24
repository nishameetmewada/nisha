(() => {
    'use strict';
    const form = document.getElementById('pricing-checkout');
    if (!form) return;
    const input = document.getElementById('promotion-code');
    const button = document.getElementById('apply-promotion');
    const message = document.getElementById('promotion-quote');
    let revision = 0;
    button.hidden = false;
    input.addEventListener('input', () => {
        revision++;
        message.textContent = 'Check the updated code to see your estimated total. The final amount is shown before you pay.';
    });
    button.addEventListener('click', async () => {
        const requestRevision = ++revision;
        button.disabled = true;
        message.textContent = 'Checking your promotion code…';
        try {
            const response = await fetch('api/promotion-quote.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': form.elements.csrf_token.value },
                body: JSON.stringify({ promotion_code: input.value.trim() }),
            });
            const reply = await response.json();
            if (requestRevision !== revision) return;
            if (!response.ok || !reply.success) throw new Error(reply.error || 'This promotion could not be checked.');
            const quote = reply.quote;
            message.textContent = quote.discount_minor > 0
                ? `${quote.code}: save ${quote.formatted_discount}. Estimated total: ${quote.formatted_total} for ${quote.access_days} days. Availability is checked again at checkout.`
                : `Access pass: ${quote.formatted_total} for ${quote.access_days} days. No promotion applied.`;
        } catch (error) {
            if (requestRevision === revision) message.textContent = error.message === 'Failed to fetch'
                ? 'The connection was interrupted. Your code is retained; try checking it again.'
                : error.message || 'Promotion codes are temporarily unavailable.';
        } finally {
            button.disabled = false;
        }
    });
})();
