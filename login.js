const root = document.documentElement;
const eye = document.querySelector('#eyeball');
const beam = document.querySelector('#beam');
const passwordInput = document.querySelector('#password');

if (eye && beam && passwordInput) {
    root.addEventListener('mousemove', (e) => {
        let rect = beam.getBoundingClientRect();
        let mouseX = rect.right + (rect.width / 2);
        let mouseY = rect.top + (rect.height / 2);
        let rad = Math.atan2(mouseX - e.pageX, mouseY - e.pageY);
        let degrees = (rad * (20/Math.PI) * -1) -350;
        root.style.setProperty('--beamDegrees', `${degrees}deg`);
    });

    eye.addEventListener('click', (e) => {
        e.preventDefault();
        document.body.classList.toggle('show-password');
        passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.focus();
    });
}


document.addEventListener('DOMContentLoaded', function() {
    //get main form element
    const form=document.getElementById('loginForm');
    const emailInput=document.getElementById('email');   
    const password=document.getElementById('password');
    const submitBtn=document.getElementById('submitBtn');

    // get all error message element
    const emailError=document.getElementById('emailError');
    const passwordError=document.getElementById('passwordError');


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
        clearError(password, passwordError);
        return true;
    }

   //clear error messages while user is typing or selecting
    emailInput.addEventListener('input', () => clearError(emailInput, emailError));
    password.addEventListener('input', () => clearError(password, passwordError));

    //validate input fields when user leaves the field (blur)
    emailInput.addEventListener('blur', validateEmail);
    password.addEventListener('blur', validatePassword);

    // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validateEmail(),
            validatePassword()
        ];
        
        const isValid = validations.every(v => v === true);
        
        if (isValid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Logging...';
            form.submit();
        }
    });
});