/**
 * University Course Recommendation System (UCRS)
 * JavaScript form interactivity and dynamic DOM toggling
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Navigation Menu Toggle
    const navToggle = document.querySelector('.mobile-nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function () {
            navMenu.classList.toggle('show');
            const isExpanded = navMenu.classList.contains('show');
            navToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });
    }

    // 2. Education Level Dynamic Form Fields Toggle (FR-15, FR-16, FR-46)
    const eduLevelRadios = document.querySelectorAll('input[name="education_level"]');
    const eduLevelSelect = document.getElementById('education_level_select');
    const secondaryFields = document.getElementById('secondary-fields');
    const tertiaryFields = document.getElementById('tertiary-fields');

    function toggleEducationFields(level) {
        if (!secondaryFields || !tertiaryFields) return;

        if (level === 'SECONDARY') {
            secondaryFields.style.display = 'block';
            tertiaryFields.style.display = 'none';
        } else if (level === 'TERTIARY') {
            secondaryFields.style.display = 'none';
            tertiaryFields.style.display = 'block';
        } else {
            secondaryFields.style.display = 'none';
            tertiaryFields.style.display = 'none';
        }
    }

    if (eduLevelRadios.length > 0) {
        eduLevelRadios.forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.checked) {
                    toggleEducationFields(this.value);
                }
            });
            if (radio.checked) {
                toggleEducationFields(radio.value);
            }
        });
    }

    if (eduLevelSelect) {
        eduLevelSelect.addEventListener('change', function () {
            toggleEducationFields(this.value);
        });
        toggleEducationFields(eduLevelSelect.value);
    }

    // 3. Signup & Profile Form Client Validation (FR-11, FR-13, FR-14)
    const signupForm = document.getElementById('signup-form');
    if (signupForm) {
        signupForm.addEventListener('submit', function (e) {
            let isValid = true;

            // Name check (min 2 chars)
            const nameInput = document.getElementById('full_name');
            const nameError = document.getElementById('full_name_error');
            if (nameInput && nameError) {
                if (nameInput.value.trim().length < 2) {
                    nameError.textContent = 'Full name must contain at least 2 characters.';
                    nameInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    nameError.textContent = '';
                    nameInput.classList.remove('is-invalid');
                }
            }

            // Password check (min 6 chars)
            const passwordInput = document.getElementById('password');
            const passwordError = document.getElementById('password_error');
            if (passwordInput && passwordError) {
                if (passwordInput.value.length < 6) {
                    passwordError.textContent = 'Password must be at least 6 characters.';
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    passwordError.textContent = '';
                    passwordInput.classList.remove('is-invalid');
                }
            }

            // Confirm password check
            const confirmInput = document.getElementById('confirm_password');
            const confirmError = document.getElementById('confirm_password_error');
            if (passwordInput && confirmInput && confirmError) {
                if (passwordInput.value !== confirmInput.value) {
                    confirmError.textContent = 'Passwords do not match.';
                    confirmInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    confirmError.textContent = '';
                    confirmInput.classList.remove('is-invalid');
                }
            }

            // Education level selection check
            const selectedLevel = document.querySelector('input[name="education_level"]:checked') || document.getElementById('education_level_select');
            const levelVal = selectedLevel ? selectedLevel.value : '';
            const eduError = document.getElementById('education_level_error');

            if (!levelVal && eduError) {
                eduError.textContent = 'Please choose your education level.';
                isValid = false;
            } else if (eduError) {
                eduError.textContent = '';
            }

            if (!isValid) {
                e.preventDefault();
            }
        });
    }

    // 4. Auto-dismiss alerts after 6 seconds if desired
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Allow click to dismiss
        alert.style.cursor = 'pointer';
        alert.addEventListener('click', function () {
            this.style.opacity = '0';
            setTimeout(() => this.remove(), 250);
        });
    });
});
