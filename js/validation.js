function validateRegistrationForm() {
    const passwordInput = document.getElementById('password').value;
    const errorDisplay = document.getElementById('password-error');

    // Rule: Min 6 chars, 1 Uppercase, 1 Number, 1 Special Character
    const passwordRegex = /^(?=.*\d)(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]).{6,}$/;

    if (!passwordRegex.test(passwordInput)) {
        errorDisplay.style.display = 'block';
        errorDisplay.innerText = "Password must be at least 6 characters, contain 1 uppercase letter, 1 number, and 1 special character.";
        return false; // Prevents form submission
    }

    errorDisplay.style.display = 'none';
    return true; // Allows form submission
}


Swal.fire({
    title: 'Booking Confirmed!',
    text: 'Payment successful! Slot reserved and email receipt sent.',
    icon: 'success',
    confirmButtonText: 'View My Requests',
    confirmButtonColor: '#28a745'
}).then((result) => {
    if (result.isConfirmed) {
        window.location.href = 'my_requests.php';
    }
});


Swal.fire({
    title: 'Payment Failed!',
    text: 'Your payment was cancelled or failed. Please try again.',
    icon: 'error',
    confirmButtonText: 'Try Again',
    confirmButtonColor: '#dc3545'
});


Swal.fire({
    title: 'Slot Unavailable',
    text: 'Sorry, this station has no free slots available right now.',
    icon: 'warning',
    confirmButtonText: 'OK',
    confirmButtonColor: '#ffc107'
});

