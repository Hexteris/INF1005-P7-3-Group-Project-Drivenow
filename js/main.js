/* DriveNow – main.js */

// ---- Client-side form validation ----

function validateRegisterForm() {
    let valid = true;
    clearErrors();

    const name  = document.getElementById('full_name');
    const email = document.getElementById('email');
    const phone = document.getElementById('phone');
    const lic   = document.getElementById('licence_no');
    const pass  = document.getElementById('password');
    const conf  = document.getElementById('confirm_password');

    if (name && name.value.trim().length < 2) {
        showError('full_name', 'Full name must be at least 2 characters.'); valid = false;
    }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        showError('email', 'Please enter a valid email address.'); valid = false;
    }
    if (phone && phone.value.trim() !== '' && !/^\+?[\d\s\-]{8,15}$/.test(phone.value.trim())) {
        showError('phone', 'Please enter a valid phone number.'); valid = false;
    }
    if (pass && (pass.value.length < 8 || !/[!@#$%^&*(),.?":{}|<>_\-]/.test(pass.value))) {
        showError('password', 'Password must be at least 8 characters and include at least 1 special character.'); valid = false;
    }
    if (pass && conf && pass.value !== conf.value) {
        showError('confirm_password', 'Passwords do not match.'); valid = false;
    }
    if (lic && lic.value.trim() !== '' && !/^\d{9}[A-Za-z]$/.test(lic.value.trim())) {
        showError('licence_no', 'Licence must be in format 123456789K (9 digits followed by 1 letter).'); valid = false;
    }

    return valid;
}

function validateLoginForm() {
    let valid = true;
    clearErrors();

    const email = document.getElementById('email');
    const pass  = document.getElementById('password');

    if (email && email.value.trim() === '') {
        showError('email', 'Email is required.'); valid = false;
    }
    if (pass && pass.value.trim() === '') {
        showError('password', 'Password is required.'); valid = false;
    }

    return valid;
}

function validateBookingForm() {
    let valid = true;
    clearErrors();

    const start = document.getElementById('start_time');
    const end   = document.getElementById('end_time');

    if (!start || !end || start.value === '' || end.value === '') {
        if (start) showError('start_time', 'Please select a start time.'); valid = false;
        return valid;
    }

    const s = new Date(start.value);
    const e = new Date(end.value);
    const now = new Date();

    if (s <= now) {
        showError('start_time', 'Start time must be in the future.'); valid = false;
    }
    if (e <= s) {
        showError('end_time', 'End time must be after start time.'); valid = false;
    }

    const diffHrs = (e - s) / 36e5;
    if (diffHrs < 1) {
        showError('end_time', 'Minimum rental duration is 1 hour.'); valid = false;
    }

    return valid;
}

function showError(fieldId, msg) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.add('is-invalid');
    const err = document.createElement('div');
    err.className = 'form-error';
    err.textContent = msg;
    field.parentNode.appendChild(err);
}

function clearErrors() {
    document.querySelectorAll('.form-error').forEach(el => el.remove());
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

// ---- Live price calculator ----
function initPriceCalculator() {
    const start  = document.getElementById('start_time');
    const end    = document.getElementById('end_time');
    const rate   = parseFloat(document.getElementById('price_per_hr')?.value || 0);
    const output = document.getElementById('price_output');
    const hours  = document.getElementById('hours_output');

    if (!start || !end || !output) return;

    function update() {
        if (!start.value || !end.value) return;
        const s = new Date(start.value);
        const e = new Date(end.value);
        const h = Math.max(0, (e - s) / 36e5);
        const cost = (h * rate).toFixed(2);
        if (output) output.textContent = 'S$ ' + cost;
        if (hours)  hours.textContent  = h.toFixed(1) + ' hrs';

        // update hidden total_cost field
        const tc = document.getElementById('total_cost');
        if (tc) tc.value = cost;
    }

    start.addEventListener('change', update);
    end.addEventListener('change', update);
}

// ---- Scroll-in animations ----
function initScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.car-card, .how-card, .review-card, .stat-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(el);
    });
}

// ---- Admin: confirm delete ----
function confirmDelete(msg) {
    return confirm(msg || 'Are you sure you want to delete this item?');
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', function () {
    initScrollAnimations();
    initPriceCalculator();
});