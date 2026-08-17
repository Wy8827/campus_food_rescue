function switchRole(role) {
    const tabS  = document.getElementById('tabStudent');
    const tabP  = document.getElementById('tabProvider');
    const pf    = document.getElementById('providerFields');
    const input = document.getElementById('roleInput');
    const btn   = document.getElementById('submitBtn');

    if (role === 'student') {
        tabS.classList.add('active');
        tabP.classList.remove('active');
        pf.classList.remove('show');
        input.value = 'student';
        btn.textContent = 'Create Student Account';
    } else {
        tabP.classList.add('active');
        tabS.classList.remove('active');
        pf.classList.add('show');
        input.value = 'provider';
        btn.textContent = 'Submit Provider Application';
    }
}

document.getElementById('registerForm').addEventListener('submit', function (e) {
    let valid = true;

    function showErr(id, msg) {
        const el = document.getElementById(id);
        if (el) { el.textContent = msg; }
        valid = false;
    }
    function clearErr(id) {
        const el = document.getElementById(id);
        if (el) el.textContent = '';
    }

    ['user_name_error','email_error','password_error','confirm_error','answer_error'].forEach(clearErr);

    const name  = document.getElementById('user_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const pw    = document.getElementById('password').value;
    const cf    = document.getElementById('confirm').value;
    const ans   = document.getElementById('security_answer').value.trim();

    if (!name)  showErr('user_name_error', 'Username is required.');
    if (!email) showErr('email_error', 'Email is required.');
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) showErr('email_error', 'Enter a valid email address.');
    
    if (!pw || pw.length < 6) showErr('password_error', 'Password must be at least 6 characters.');
    if (pw !== cf) showErr('confirm_error', 'Passwords do not match.');
    if (!ans) showErr('answer_error', 'Security answer is required.');

    if (!valid) e.preventDefault();
});
