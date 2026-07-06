document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const form=document.getElementById('resetForm');
    const submitBtn=document.getElementById('submitBtn');

    const passwordError=document.getElementById('passwordError');
    const confirmPasswordError=document.getElementById('confirmPasswordError');

    if(!form) return;

    //display inline error message and apply error style
    function showError(input, errorElement, message) {
        if (input) input.classList.add('error');
        if (errorElement) {
            errorElement.innerText = message;
            errorElement.style.display = 'block';
        }
    }
    //clear error message and remove error style
    function clearError(input, errorElement) {
        if (input) input.classList.remove('error');
        if (errorElement) {
            errorElement.innerText = '';
            errorElement.style.display = 'none';
        }
    }
        //password validation
    function validatePassword() {
        const passwordValue = newPassword.value;
        if (!passwordValue) {
            showError(newPassword, passwordError, 'Password is required.');
            return false;
        }
        
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).{8,}$/;
        if (!passwordRegex.test(passwordValue)) {
            showError(newPassword, passwordError, 
                'Password must be at least 8 characters with uppercase, lowercase, number, and special character.');
            return false;
        }
        
        clearError(newPassword, passwordError);
        return true;
    }
    //comfirm password validation
    function validateConfirmPassword() {
        if (!confirmPassword.value.trim()) {
            showError(confirmPassword, confirmPasswordError, 'Please confirm your password.');
            return false;
        }
        //password match
        if (newPassword.value !== confirmPassword.value) {
            showError(confirmPassword, confirmPasswordError, 'Passwords do not match.');
            return false;
        }
        clearError(confirmPassword, confirmPasswordError);
        return true;
    }

        

        newPassword.addEventListener('input', () => clearError(newPassword, passwordError));
        confirmPassword.addEventListener('input', () => clearError(confirmPassword, confirmPasswordError));

        newPassword.addEventListener('blur', validatePassword);
        confirmPassword.addEventListener('blur', validateConfirmPassword);

       // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validatePassword(),
            validateConfirmPassword()
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (isValid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Updating...';
            form.submit();
        }
    });
});

