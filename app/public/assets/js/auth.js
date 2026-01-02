// VeriBits Authentication

// Login form handler
const loginForm = document.getElementById('login-form');
if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        try {
            const response = await apiRequest('/auth/login', {
                method: 'POST',
                body: JSON.stringify({ email, password })
            });

            if (response.data && response.data.access_token) {
                setAuthToken(response.data.access_token);
                localStorage.setItem('veribits_user', JSON.stringify(response.data.user));

                // Update navigation before redirecting
                if (typeof updateNavigation === 'function') {
                    updateNavigation();
                }

                window.location.href = '/dashboard.php';
            } else {
                showAlert('Login failed - invalid response', 'error');
            }
        } catch (error) {
            showAlert(error.message || 'Login failed', 'error');
        }
    });
}

// Signup form handler
const signupForm = document.getElementById('signup-form');
if (signupForm) {
    signupForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        if (password !== confirmPassword) {
            showAlert('Passwords do not match', 'error');
            return;
        }

        try {
            const response = await apiRequest('/auth/register', {
                method: 'POST',
                body: JSON.stringify({ email, password })
            });

            if (response.data && response.data.access_token) {
                setAuthToken(response.data.access_token);
                localStorage.setItem('veribits_user', JSON.stringify(response.data.user));
                showAlert('Account created successfully!', 'success');

                // Update navigation
                if (typeof updateNavigation === 'function') {
                    updateNavigation();
                }

                setTimeout(() => {
                    window.location.href = '/dashboard.php';
                }, 1500);
            } else {
                showAlert('Registration failed - invalid response', 'error');
            }
        } catch (error) {
            showAlert(error.message || 'Registration failed', 'error');
        }
    });
}

// Reset password form handler
const resetForm = document.getElementById('reset-form');
if (resetForm) {
    resetForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;

        try {
            await apiRequest('/auth/reset-password', {
                method: 'POST',
                body: JSON.stringify({ email })
            });

            showAlert('Password reset email sent! Check your inbox.', 'success');
        } catch (error) {
            showAlert(error.message || 'Reset failed', 'error');
        }
    });
}
