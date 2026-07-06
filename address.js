document.addEventListener('DOMContentLoaded', function() { 
    const form=document.getElementById('addressForm');
    const submitBtn=document.getElementById('submitBtn');

    const fullnameInput=document.getElementById('full_name');
    const phoneInput=document.getElementById('phoneNumber');
    const stateSelect=document.getElementById('stateSelect');
    const citySelect=document.getElementById('citySelect');
    const postcodeInput=document.getElementById('postcode');
    const addressInput=document.getElementById('address');

    const fullNameError=document.getElementById('fullNameError');
    const phoneError=document.getElementById('phoneError');
    const stateError=document.getElementById('stateError');
    const cityError=document.getElementById('cityError');
    const postcodeError=document.getElementById('postcodeError');
    const addressError=document.getElementById('addressError');

    if(!form) {
        return;
    }
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

    //fullname validation
    function validateFullname() {
        if(!fullnameInput.value.trim()) {
            showError(fullnameInput, fullNameError, 'fullname is required.');
            return false;
        }
        clearError(fullnameInput, fullNameError);
        return true;       
    }

    //phone number validation
    function validatePhone() {
        const phonePattern = /^\d{3}-\d{3}-\d{4}$/;
        if(!phoneInput.value.trim()) {
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
            if(phoneError) {
                phoneError.style.display='none';
            }
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
        const value=addressInput.value.trim();
        if (!value) {
            showError(addressInput, addressError, 'Address is required.');
            return false;
        } else if (value.length < 5) {
            showError(addressInput, addressError, 'Address must be at least 5 characters.');
            return false;
        }
        clearError(addressInput, addressError);
        return true;
    }

    //clear error messages while user is typing or selecting
    fullnameInput.addEventListener('input', () => clearError(fullnameInput, fullNameError));
    phoneInput.addEventListener('input', () => clearError(phoneInput, phoneError));
    stateSelect.addEventListener('change', () => clearError(stateSelect, stateError));
    citySelect.addEventListener('change', () => clearError(citySelect, cityError));
    postcodeInput.addEventListener('input', () => clearError(postcodeInput, postcodeError));
    addressInput.addEventListener('input', () => clearError(addressInput, addressError));

    stateSelect.addEventListener('change', loadCity);

    //validate input fields when user leaves the field (blur)
    fullnameInput.addEventListener('blur', validateFullname);
    phoneInput.addEventListener('blur', validatePhone);
    stateSelect.addEventListener('change', validateState);
    citySelect.addEventListener('change', validateCity);
    postcodeInput.addEventListener('blur', validatePostcode);
    addressInput.addEventListener('blur', validateAddress);

    // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validateFullname(),
            validatePhone(),
            validateState(),
            validateCity(),
            validatePostcode(),
            validateAddress(),
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (isValid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Adding Address...';
            form.submit();
        }
    });
        if(stateSelect.value){ //when backend validation prompt, auto show just now select city
            loadCity();
        }
});
