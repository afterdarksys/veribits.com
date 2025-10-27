// VeriBits Settings

// Load user profile on page load
document.addEventListener('DOMContentLoaded', async () => {
    await loadProfile();
    await loadSubscriptionInfo();
});

// Load user profile
async function loadProfile() {
    try {
        const data = await apiRequest('/user/profile');

        if (data.success && data.data) {
            const user = data.data;
            document.getElementById('email').value = user.email || '';
            if (document.getElementById('name')) {
                document.getElementById('name').value = user.name || '';
            }
        }
    } catch (error) {
        console.error('Failed to load profile:', error);
        showAlert('Failed to load profile information', 'error');
    }
}

// Load subscription information
async function loadSubscriptionInfo() {
    try {
        const data = await apiRequest('/billing/account');

        if (data.success && data.data) {
            const account = data.data;
            const planElement = document.getElementById('current-plan');
            const scansElement = document.getElementById('scans-remaining');

            if (planElement) {
                planElement.textContent = account.plan?.name || 'Free Trial';
            }
            if (scansElement) {
                scansElement.textContent = account.limits?.remaining_scans !== undefined ?
                    account.limits.remaining_scans : '--';
            }
        }
    } catch (error) {
        console.error('Failed to load subscription info:', error);
    }
}

// Profile form handler
const profileForm = document.getElementById('profile-form');
if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;
        const name = document.getElementById('name')?.value;

        try {
            const data = await apiRequest('/user/profile', {
                method: 'PUT',
                body: JSON.stringify({ email, name })
            });

            if (data.success) {
                showAlert('Profile updated successfully!', 'success');
            }
        } catch (error) {
            showAlert(error.message || 'Failed to update profile', 'error');
        }
    });
}

// Password change form handler
const passwordForm = document.getElementById('password-form');
if (passwordForm) {
    passwordForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const currentPassword = document.getElementById('current-password').value;
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-new-password').value;

        if (newPassword !== confirmPassword) {
            showAlert('New passwords do not match', 'error');
            return;
        }

        if (newPassword.length < 8) {
            showAlert('Password must be at least 8 characters', 'error');
            return;
        }

        try {
            const data = await apiRequest('/user/password', {
                method: 'PUT',
                body: JSON.stringify({ current_password: currentPassword, new_password: newPassword })
            });

            if (data.success) {
                showAlert('Password changed successfully!', 'success');
                passwordForm.reset();
            }
        } catch (error) {
            showAlert(error.message || 'Failed to change password', 'error');
        }
    });
}

// Cancel subscription
async function cancelSubscription() {
    if (!confirm('Are you sure you want to cancel your subscription? You will lose access to premium features.')) {
        return;
    }

    try {
        const data = await apiRequest('/billing/cancel', {
            method: 'POST'
        });

        if (data.success) {
            showAlert('Subscription cancelled successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    } catch (error) {
        showAlert(error.message || 'Failed to cancel subscription', 'error');
    }
}

// Delete account
async function deleteAccount() {
    if (!confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
        return;
    }

    const confirmation = prompt('Type "DELETE" to confirm account deletion:');
    if (confirmation !== 'DELETE') {
        showAlert('Account deletion cancelled', 'warning');
        return;
    }

    try {
        const data = await apiRequest('/user/account', {
            method: 'DELETE'
        });

        if (data.success) {
            showAlert('Account deleted successfully', 'success');
            setTimeout(() => {
                removeAuthToken();
                window.location.href = '/';
            }, 2000);
        }
    } catch (error) {
        showAlert(error.message || 'Failed to delete account', 'error');
    }
}
