// VeriBits Dashboard

// Load dashboard data on page load
document.addEventListener('DOMContentLoaded', async () => {
    await loadDashboardData();
    await loadRecentScans();
});

// Load dashboard stats
async function loadDashboardData() {
    try {
        const data = await apiRequest('/billing/account');

        if (data.success && data.data) {
            const account = data.data;

            // Update stats
            document.getElementById('scans-remaining').textContent =
                account.limits?.remaining_scans !== undefined ? account.limits.remaining_scans : '--';
            document.getElementById('total-scans').textContent =
                account.usage?.total_verifications || 0;
            document.getElementById('plan-name').textContent =
                account.plan?.name || 'Free Trial';
            document.getElementById('account-status').textContent =
                account.status === 'active' ? 'Active' : account.status;
        }
    } catch (error) {
        console.error('Failed to load dashboard data:', error);
        // Set default values on error
        document.getElementById('scans-remaining').textContent = '--';
        document.getElementById('total-scans').textContent = '--';
        document.getElementById('plan-name').textContent = '--';
    }
}

// Load recent scans
async function loadRecentScans() {
    const container = document.getElementById('recent-scans');

    try {
        const data = await apiRequest('/verifications?limit=10');

        if (data.success && data.data && data.data.length > 0) {
            let html = '<div style="display: grid; gap: 0.75rem;">';

            data.data.forEach(scan => {
                const statusColor = scan.result === 'valid' ? 'var(--success-color)' :
                                  scan.result === 'invalid' ? 'var(--error-color)' :
                                  'var(--text-secondary)';

                html += `
                    <div style="padding: 1rem; background: rgba(255, 255, 255, 0.05); border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; margin-bottom: 0.25rem;">${scan.type || 'Verification'}</div>
                            <div style="font-size: 0.85rem; color: var(--text-secondary);">${formatDate(scan.created_at)}</div>
                        </div>
                        <div style="color: ${statusColor}; font-weight: 600; text-transform: capitalize;">
                            ${scan.result || 'Pending'}
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 2rem;">No recent scans found. <a href="/tools.php">Start scanning</a></p>';
        }
    } catch (error) {
        console.error('Failed to load recent scans:', error);
        container.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 2rem;">Unable to load recent scans.</p>';
    }
}
