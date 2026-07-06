document.addEventListener('DOMContentLoaded', function() {
    //get main form element
    const form=document.getElementById('registerForm');
    const password=document.getElementById('password');
    const confirmPassword=document.getElementById('confirmPassword');
    const submitBtn=document.getElementById('submitBtn');
    const postcodeInput=document.getElementById('postcode');
       
    // get all error message element
    const userNameError=document.getElementById('userNameError');
    const fullNameError=document.getElementById('fullNameError');
    const emailError=document.getElementById('emailError');
    const passwordError=document.getElementById('passwordError');
    const confirmPasswordError=document.getElementById('confirmPasswordError');
    const phoneError=document.getElementById('phoneError');
    const stateError=document.getElementById('stateError');
    const cityError=document.getElementById('cityError');
    const postcodeError=document.getElementById('postcodeError');
    const addressError=document.getElementById('addressError');
    const securityQuestionError=document.getElementById('securityQuestionError');
    const securityAnswerError=document.getElementById('securityAnswerError');
    const termsError=document.getElementById('termsError');

    // get all input fields
    const usernameInput=form.querySelector('[name="user_name"]');
    const fullnameInput=form.querySelector('[name="full_name"]');
    const emailInput=form.querySelector('[name="email"]');
    const phoneInput=form.querySelector('[name="phoneNumber"]');
    const addressInput=form.querySelector('[name="address_line1"]');
    const stateSelect=document.getElementById('stateSelect');
    const citySelect=document.getElementById('citySelect');
    const securityQuestionSelect=form.querySelector('[name="security_question"]');
    const securityAnswerInput=form.querySelector('[name="security_answer"]');
    const termsCheckbox=form.querySelector('[name="terms"]');
    //stop script execution if form does not exist
    if (!form) return;

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

    // username validation
    function validateUsername() {
        if (!usernameInput.value.trim()) {
            showError(usernameInput, userNameError, 'Username is required.');
            return false;
        }
        clearError(usernameInput, userNameError);
        return true;
    }
    //fullname validate
    function validateFullname() {
        if (!fullnameInput.value.trim()) {
            showError(fullnameInput, fullNameError, 'Full name is required.');
            return false;
        }
        clearError(fullnameInput, fullNameError);
        return true;
    }

    //email validation
    function validateEmail() {
        const email = emailInput.value.trim();
        if (!email) {
            showError(emailInput, emailError, 'Email is required.');
            return false;
        }
        
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email)) {
            showError(emailInput, emailError, 'Invalid email format.');
            return false;
        }
        
        clearError(emailInput, emailError);
        return true;
    }
    //password validation
    function validatePassword() {
        const passwordValue = password.value;
        if (!passwordValue) {
            showError(password, passwordError, 'Password is required.');
            return false;
        }
        
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).{8,}$/;
        if (!passwordRegex.test(passwordValue)) {
            showError(password, passwordError, 
                'Password must be at least 8 characters with uppercase, lowercase, number, and special character.');
            return false;
        }
        
        clearError(password, passwordError);
        return true;
    }
    //comfirm password validation
    function validateConfirmPassword() {
        if (!confirmPassword.value.trim()) {
            showError(confirmPassword, confirmPasswordError, 'Please confirm your password.');
            return false;
        }
        //password match
        if (password.value !== confirmPassword.value) {
            showError(confirmPassword, confirmPasswordError, 'Passwords do not match.');
            return false;
        }
        clearError(confirmPassword, confirmPasswordError);
        return true;
    }
    //phone number validation
    function validatePhone() {
        const phonePattern = /^\d{3}-\d{3}-\d{4}$/;
        if (!phoneInput.value.trim()) {
            showError(phoneInput, phoneError, 'Phone number is required.');
            return false;
        } else if (!phonePattern.test(phoneInput.value)) {
            showError(phoneInput, phoneError, 'Phone number must be in format: 012-345-6789');
            return false;
        }
        clearError(phoneInput, phoneError);
        return true;
    }
    // auto phone number format while typing, ex: 123-456-7890
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            let digits = this.value.replace(/\D/g, ''); // remove non-numbers
            digits = digits.substring(0, 10); // max 10 digits

            let formatted = '';
            if (digits.length > 0) {
                formatted = digits.substring(0, 3);
            }
            if (digits.length >= 4) {
                formatted += '-' + digits.substring(3, 6);
            }
            if (digits.length >= 7) {
                formatted += '-' + digits.substring(6, 10);
            }

            this.value = formatted;

            // clear inline error while typing
            clearError(phoneInput, phoneError);
        });
    }

    //state validation
    function validateState() {
        if (!stateSelect.value) {
            showError(stateSelect, stateError, 'Please select a state.');
            return false;
        }
        clearError(stateSelect, stateError);
        return true;
    }

    //load the city for that state
    function loadCity(){
        citySelect.innerHTML='<option value="">Select city</option>';
        if(stateSelect.value && cityByState[stateSelect.value]){
            cityByState[stateSelect.value].forEach(city=>{
                const option=document.createElement('option');
                option.value=city;
                option.textContent=city;
                if(city===selectedCity){
                    option.selected=true;
                }
                citySelect.appendChild(option);
            });
        }
        clearError(citySelect, cityError);
    }


    function validateCity(){
        if(!citySelect.value){
            showError(citySelect, cityError, 'Please select a city.');
            return false;
        }
        clearError(citySelect, cityError);
        return true;
    }


    //postcode validation
    function validatePostcode() {
        const postcodePattern = /^\d{5}$/;
        if (!postcodeInput.value.trim()) {
            showError(postcodeInput, postcodeError, 'Postcode is required.');
            return false;
        } else if (!postcodePattern.test(postcodeInput.value)) {
            showError(postcodeInput, postcodeError, 'Postcode must be 5 digits.');
            return false;
        }
        clearError(postcodeInput, postcodeError);
        return true;
    }
        // auto postcode number format while typing, ex: 75000
    if (postcodeInput) {
        postcodeInput.addEventListener('input', function () {
            let digits = this.value.replace(/\D/g, ''); // remove non-numbers
            digits = digits.substring(0, 5); // max 5 digits

            this.value=digits;
            
            // clear inline error while typing
            clearError(postcodeInput, postcodeError);
        });
    }

    //address validation
    function validateAddress() {
        if (!addressInput.value.trim()) {
            showError(addressInput, addressError, 'Address is required.');
            return false;
        } else if (addressInput.value.trim().length < 5) {
            showError(addressInput, addressError, 'Address must be at least 5 characters.');
            return false;
        }
        clearError(addressInput, addressError);
        return true;
    }
    //security question validation
    function validateSecurityQuestion() {
        if (!securityQuestionSelect.value) {
            showError(securityQuestionSelect, securityQuestionError, 'Please select a security question.');
            return false;
        }
        clearError(securityQuestionSelect, securityQuestionError);
        return true;
    }
    //security answer validation
    function validateSecurityAnswer() {
        if (!securityAnswerInput.value.trim()) {
            showError(securityAnswerInput, securityAnswerError, 'Security answer is required.');
            return false;
        }
        clearError(securityAnswerInput, securityAnswerError);
        return true;
    }
    //terms and condition validation
    function validateTerms() {
        if (!termsCheckbox.checked) {
            showError(termsCheckbox, termsError, 'please accept the Terms of Service & Privacy Policy');
            return false;
        }
        clearError(termsCheckbox, termsError);
        return true;
    }
    //clear error messages while user is typing or selecting
    usernameInput.addEventListener('input', () => clearError(usernameInput, userNameError));
    fullnameInput.addEventListener('input', () => clearError(fullnameInput, fullNameError));
    emailInput.addEventListener('input', () => clearError(emailInput, emailError));
    password.addEventListener('input', () => clearError(password, passwordError));
    confirmPassword.addEventListener('input', () => clearError(confirmPassword, confirmPasswordError));
    phoneInput.addEventListener('input', () => clearError(phoneInput, phoneError));
    stateSelect.addEventListener('change', () => clearError(stateSelect, stateError));
    citySelect.addEventListener('change', () => clearError(citySelect, cityError));
    postcodeInput.addEventListener('input', () => clearError(postcodeInput, postcodeError));
    addressInput.addEventListener('input', () => clearError(addressInput, addressError));
    securityQuestionSelect.addEventListener('change', () => clearError(securityQuestionSelect, securityQuestionError));
    securityAnswerInput.addEventListener('input', () => clearError(securityAnswerInput, securityAnswerError));
    
    stateSelect.addEventListener('change', loadCity);

    //validate input fields when user leaves the field (blur)
    usernameInput.addEventListener('blur', validateUsername);
    fullnameInput.addEventListener('blur', validateFullname);
    emailInput.addEventListener('blur', validateEmail);
    password.addEventListener('blur', validatePassword);
    confirmPassword.addEventListener('blur', validateConfirmPassword);
    phoneInput.addEventListener('blur', validatePhone);
    stateSelect.addEventListener('change', validateState);
    citySelect.addEventListener('change', validateCity);
    postcodeInput.addEventListener('blur', validatePostcode);
    addressInput.addEventListener('blur', validateAddress);
    securityQuestionSelect.addEventListener('change', validateSecurityQuestion);
    securityAnswerInput.addEventListener('blur', validateSecurityAnswer);

    // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validateUsername(),
            validateFullname(),
            validateEmail(),
            validatePassword(),
            validateConfirmPassword(),
            validatePhone(),
            validateState(),
            validateCity(),
            validatePostcode(),
            validateAddress(),
            validateSecurityQuestion(),
            validateSecurityAnswer(),
            validateTerms()
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (isValid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Creating Account...';
            form.submit();
        }
    });

    if(stateSelect.value){ //when backend validation prompt, auto show just now select city
            loadCity();
        }
});