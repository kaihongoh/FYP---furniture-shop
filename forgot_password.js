document.addEventListener('DOMContentLoaded',function() {
    const emailInput=document.getElementById('email');
    const answerInput=document.getElementById('security_answer');
    const form=document.getElementById('forgotForm');
    const submitBtn=document.getElementById('submitBtn');

    const emailError=document.getElementById('emailError');
    const securityAnswerError=document.getElementById('securityAnswerError');

    if(!form) return;

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
    function validateAnswer() {
        if(!answerInput.value.trim()) {
            showError(answerInput, securityAnswerError, 'Please enter your security answer.');
            return false;
        }
        clearError(answerInput, securityAnswerError);
        return true;
    }
   //real time get security question
    emailInput.addEventListener('input', function() {
        clearError(emailInput, emailError);
        const email = this.value.trim();
        const questionInput = document.getElementById('security_question');
        
        if (email.length > 0 && email.includes('@')) {
            //show loading state
            questionInput.placeholder = "Loading security question...";
            
            // use fetch API to get security question
            fetch('get_security_question.php?email=' + encodeURIComponent(email))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    if (data) {
                        questionInput.value = data;
                        questionInput.placeholder = "";
                    } else {
                        questionInput.value = "";
                        questionInput.placeholder = "No security question found for this email";
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    questionInput.value = "";
                    questionInput.placeholder = "Error loading security question";
                });
        } else {
            questionInput.value = "";
            questionInput.placeholder = "Enter email above to see security question";
        }
    });

       //clear error messages while user is typing or selecting
    answerInput.addEventListener('input', () => clearError(answerInput, securityAnswerError));

    //validate input fields when user leaves the field (blur)
    emailInput.addEventListener('blur', validateEmail);
    answerInput.addEventListener('blur', validateAnswer);

      // final validation before submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        //validate all field and show red color
        const validations = [
            validateEmail(),
            validateAnswer()
            
        ];
        const isValid = validations.every(v => v === true);
        
        if (isValid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Verifying...';
            form.submit();
        }
        });
});

