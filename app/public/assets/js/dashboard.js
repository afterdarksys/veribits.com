// Dashboard.js - VeriBits Dashboard Logic
let apiKeys = [];

// Load dashboard data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    loadApiKeys();
});

async function loadDashboardData() {
    try {
        const token = localStorage.getItem('access_token');
        if (!token) {
            window.location.href = '/login.php';
            return;
        }

        const response = await fetch('/api/v1/auth/me', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = '/login.php';
                return;
            }
            throw new Error('Failed to load dashboard data');
        }

        const data = await response.json();

        // Update stats
        if (data.data.quotas && data.data.quotas[0]) {
            const quota = data.data.quotas[0];
            document.getElementById('scans-remaining').textContent =
                (quota.allowance - quota.used).toLocaleString();
            document.getElementById('total-scans').textContent =
                quota.used.toLocaleString();
        }

        if (data.data.user) {
            document.getElementById('plan-name').textContent =
                data.data.user.plan || 'Free';
            document.getElementById('account-status').textContent =
                data.data.user.status || 'Active';
        }

    } catch (error) {
        console.error('Failed to load dashboard:', error);
        showError('Failed to load dashboard data');
    }
}

async function loadApiKeys() {
    try {
        const token = localStorage.getItem('access_token');
        if (!token) return;

        const response = await fetch('/api/v1/api-keys', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        if (!response.ok) {
            throw new Error('Failed to load API keys');
        }

        const data = await response.json();
        apiKeys = data.data.api_keys || [];

        renderApiKeys();

    } catch (error) {
        console.error('Failed to load API keys:', error);
        document.getElementById('api-keys-list').innerHTML =
            '<p style="color: var(--error);">Failed to load API keys</p>';
    }
}

function renderApiKeys() {
    const container = document.getElementById('api-keys-list');

    if (apiKeys.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                <p style="margin-bottom: 1rem;">No API keys yet</p>
                <p>Create your first API key to start using the VeriBits API</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 1rem;">Name</th>
                        <th style="padding: 1rem;">Key</th>
                        <th style="padding: 1rem;">Created</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${apiKeys.map(key => `
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 1rem; font-weight: 600;">${escapeHtml(key.name)}</td>
                            <td style="padding: 1rem;">
                                <code style="background: var(--background); padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.9rem;">
                                    ${key.revoked ? '••••••••' : escapeHtml(key.key)}
                                </code>
                            </td>
                            <td style="padding: 1rem; color: var(--text-secondary); font-size: 0.9rem;">
                                ${new Date(key.created_at).toLocaleDateString()}
                            </td>
                            <td style="padding: 1rem;">
                                ${key.revoked
                                    ? '<span style="color: var(--error); font-weight: 600;">Revoked</span>'
                                    : '<span style="color: var(--success); font-weight: 600;">Active</span>'
                                }
                            </td>
                            <td style="padding: 1rem;">
                                ${!key.revoked
                                    ? `<button onclick="revokeApiKey('${key.id}', '${escapeHtml(key.name)}')" class="btn btn-danger" style="padding: 0.4rem 1rem; font-size: 0.9rem;">Revoke</button>`
                                    : '<span style="color: var(--text-secondary);">—</span>'
                                }
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function createNewApiKey() {
    const name = prompt('Enter a name for this API key (e.g., "Production Server", "Dev Environment"):');

    if (!name || name.trim() === '') {
        return;
    }

    try {
        const token = localStorage.getItem('access_token');

        const response = await fetch('/api/v1/api-keys', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name: name.trim() })
        });

        if (!response.ok) {
            throw new Error('Failed to create API key');
        }

        const data = await response.json();
        const newKey = data.data.api_key;

        // Show the key in a modal/alert (only shown once!)
        showApiKeyModal(newKey, name);

        // Reload the keys list
        await loadApiKeys();

    } catch (error) {
        console.error('Failed to create API key:', error);
        alert('Failed to create API key. Please try again.');
    }
}

function showApiKeyModal(apiKey, name) {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 1rem;
    `;

    modal.innerHTML = `
        <div style="
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        ">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="font-size: 2rem;">🔑</div>
                <h2 style="margin: 0; color: var(--primary);">API Key Created!</h2>
            </div>

            <div style="background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem;">
                <p style="margin: 0 0 0.5rem 0; font-weight: 600; color: #0c4a6e;">
                    ${escapeHtml(name)}
                </p>
                <div style="background: white; padding: 1rem; border-radius: 6px; font-family: monospace; word-break: break-all; font-size: 0.9rem; color: #0c4a6e;">
                    ${escapeHtml(apiKey)}
                </div>
            </div>

            <div style="background: #fff7ed; border: 2px solid #f59e0b; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                <p style="margin: 0; font-weight: 600; color: #92400e; font-size: 0.9rem;">
                    ⚠️ Important: Copy this key now!
                </p>
                <p style="margin: 0.5rem 0 0 0; color: #92400e; font-size: 0.85rem;">
                    For security reasons, this key will not be shown again. Store it in a secure location.
                </p>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button onclick="copyToClipboard('${apiKey.replace(/'/g, "\\'")}'); this.textContent='Copied!'; this.style.background='var(--success)';" class="btn btn-primary">
                    📋 Copy to Clipboard
                </button>
                <button onclick="this.closest('[style*=fixed]').remove()" class="btn btn-secondary">
                    Close
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Close on background click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

async function revokeApiKey(keyId, keyName) {
    if (!confirm(`Are you sure you want to revoke the API key "${keyName}"?\n\nThis action cannot be undone and any applications using this key will stop working.`)) {
        return;
    }

    try {
        const token = localStorage.getItem('access_token');

        const response = await fetch(`/api/v1/api-keys/${keyId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        if (!response.ok) {
            throw new Error('Failed to revoke API key');
        }

        alert(`API key "${keyName}" has been revoked successfully.`);

        // Reload the keys list
        await loadApiKeys();

    } catch (error) {
        console.error('Failed to revoke API key:', error);
        alert('Failed to revoke API key. Please try again.');
    }
}

function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: var(--error);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 9999;
    `;
    errorDiv.textContent = message;
    document.body.appendChild(errorDiv);

    setTimeout(() => errorDiv.remove(), 5000);
}
