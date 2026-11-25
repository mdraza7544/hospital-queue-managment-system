// Universal Portal Login JavaScript (Admin / Staff / Doctor)

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.querySelector('.login-form');
    
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                alert('Please enter both username and password.');
                return;
            }
            
            const loginData = {
                username: username,
                password: password
            };
            
            try {
                const response = await fetch('/STI_Queueing-System/api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(loginData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Redirect to role-specific dashboard
                    window.location.href = result.redirect || '/STI_Queueing-System/staff-dashboard';
                } else {
                    alert('Login failed: ' + (result.message || 'Invalid credentials'));
                }
            } catch (error) {
                console.error('Login error:', error);
                alert('Login failed. Please check network connection and try again.');
            }
        });
    }
});
