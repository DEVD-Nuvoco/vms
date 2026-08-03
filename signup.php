<?php include("db.php"); 
if(isset($_POST['login'])){
if(($_POST['emailVal']!='') and ($_POST['passVal']!='')){

}}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">



    <!-- Meta -->
    <meta name="description" content="">
    <meta name="author" content="BootstrapDash">

    <title><?php echo $Address;?></title>

    <!-- vendor css -->
    <link href="lib/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="lib/ionicons/css/ionicons.min.css" rel="stylesheet">
    <link href="lib/typicons.font/typicons.css" rel="stylesheet">

    <!-- azia CSS -->
    <link rel="stylesheet" href="css/azia.css">

  </head>
  <script src="cities.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Include jQuery (already included) and Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Flatpickr CSS -->
  <body class="az-body">
  <style type="text/css">
  body {
  padding:0px;

  }
  .az-header {
        height: 0px;
        position: relative; /* Ensures the gradient strip is positioned relative to the header */
        
        padding: 0px;
        z-index: 99999 !important;
      }
      
      .az-header::before {
        content: ""; /* Add a pseudo-element for the gradient strip */
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 10px; /* Adjust this to control the strip's height */
        background: linear-gradient(to right,  #38aa4e,#76F892); /* Dark green to green */
        z-index: 1; /* Ensures it appears above the header */
      }
  </style>
  <div class="az-header"></div>
	
    <div class="az-signup-wrapper">
      <div class="az-column-signup-left">
        <div>
          <h1 class="az-logo"><a href="index.php"><img src="images/nuvoco-ori.png" width="180"></a></h1>
          <h5><a href="index.php">Visitor Mangement System</a></h5>
          <p>Nuvoco Vistas Corporation Limited (<a href="https://www.nuvoco.com/" style="color:#000000;" target="_blank"><strong>N<span style="color:#FF0000">u</span><span style="color:#00CC33">v</span>oco</strong></a>) is a building materials company with a vision to build a Safer, Smarter and Sustainable world and aspires to be a "<em>Trusted Building Materials Company Creating Value for our Stakeholder</em>".<br /><br />
          The Visitor Management System (VMS) is a cutting-edge solution designed to revolutionize visitor access and meeting scheduling for plants and corporate facilities. With an emphasis on seamless operations, VMS integrates gate pass management, automated notifications, and robust safety compliance protocols to deliver an exceptional user experience.</p>
          <p class="mt-3 mb-2"><strong>Late IN / Early Out (LIEO)</strong></p>
          <a href="clgp/login.php" class="btn btn-az-primary">Go to LIEO Login</a>

        </div>
      </div><!-- az-column-signup-left -->
      <?php if(isset($_REQUEST['action']) AND ($_REQUEST['action']=='signin' OR $_REQUEST['action']=='')){?>
	  <div class="az-column-signup">
        <div class="az-signup-header">
          <h2>Get Sign-in</h2>
          <?php if(isset($_SESSION['mess']) AND $_SESSION['mess']!=''){?>
		  <script type="text/javascript">
			//$('.alert').animate({ left: this.x, top: this.y },"linear");
			//$(".alert").delay(1500).show('slow');
			setTimeout(function() { $('.alert').show('slow'); }, 8000);
			</script>
		   <div class="alert alert-primary alert-dismissible fade show" role="alert">
		  <?php echo $_SESSION['mess']?>

		</div> 
		<?php unset($_SESSION['mess']); }?>  
		<script type="text/javascript">
		setTimeout(function() { $('.alert').fadeOut('slow'); }, 8000);
		</script>
          <form action="checkLogin.php" method="post">
            <div class="form-group">
              <label>Email:</label>
              <input type="email" name="emailVal"  class="form-control" placeholder="Enter your email" required>
            </div><!-- form-group -->
            <div class="form-group">
  <label>Password:</label>
  <div style="position: relative; display: flex; align-items: center;">
    <input
      type="password"
      name="passVal"
      class="form-control"
      placeholder="Enter your password"
      required
      style="padding-right: 30px; width: 100%;"
      id="passwordField"
    />
    <button
      type="button"
      style="position: absolute; right: 5px; background: none; border: none; cursor: pointer; font-size: 16px;"
      onclick="togglePasswordVisibility()"
    >
      👁️
    </button>
  </div>
</div>
<!-- form-group -->

<div class="form-group">
  <label>Type: </label>
  <label for="visitor" style="cursor: pointer; margin-right: 10px; display: flex; align-items: center;">
    <input
      required
      type="radio"
      id="visitor"
      name="logType"
      value="visitor"
      style="margin-right: 5px;"
    /> Visitor
  </label>
  <label for="nuvocan" style="cursor: pointer; margin-right: 10px; display: flex; align-items: center;">
    <input
      required
      type="radio"
      id="nuvocan"
      name="logType"
      value="nuvocan"
      style="margin-right: 5px;"
    /> Nuvocan
  </label>
  <label for="security" style="cursor: pointer; display: flex; align-items: center;">
    <input
      required
      type="radio"
      id="security"
      name="logType"
      value="security"
      style="margin-right: 5px;"
    /> Security
  </label>
</div>
			
            <button class="btn btn-az-primary btn-block" name="login">Login</button>
            <!-- row -->
          </form>
		  <br /><div class="az-signup-footer">
          <p>Do'nt have login details, please get <a href="signup.php?action=signup">Sign Up</a></p>
		  <p>Forgot password? <a href="signup.php?action=getYourPassword">Get Your Password</a></p>
        </div><!-- az-signin-footer -->

        </div><!-- az-signup-header -->
      </div>
      <?php } else if (isset($_REQUEST['action']) AND $_REQUEST['action'] == 'getYourPassword') { ?>
<div class="az-column-signup">
    <div class="az-signup-header">
        <h2>Reset Your Password</h2>

        <!-- Form to send OTP -->
        <form id="getPasswordForm">
            <div class="form-group">
                <label>Email/User ID</label>
                <input type="text" id="userId" name="userId" required class="form-control" placeholder="Enter your Email or User ID">
                <small id="userIdError" class="text-danger"></small>
            </div>
            <button type="button" class="btn btn-az-primary btn-block" id="sendOtpBtn">Send OTP</button>
            <div id="otpNotification" class="text-success mt-2" style="display: none;">OTP has been sent to your email.</div>
        </form>

        <!-- Form to validate OTP and update password -->
        <form id="updatePasswordForm" style="display: none;">
            <div class="form-group">
                <label>Enter OTP</label>
                <input type="text" id="otp" name="otp" required class="form-control" placeholder="Enter the OTP">
                <small id="otpError" class="text-danger"></small>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" id="newPassword" name="newPassword" required class="form-control" placeholder="Enter your new password">
                <small id="newPasswordError" class="text-danger"></small>
            </div>
            <button type="button" class="btn btn-az-primary btn-block" id="updatePasswordBtn">Update Password</button>
        </form>

        <br />
        <div class="az-signup-footer">
            <p>Back to <a href="signup.php?action=signin">Sign In</a></p>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function () {
        // Handle OTP sending
        $('#sendOtpBtn').on('click', function () {
            const userId = $('#userId').val();

            // Validate email/user ID input
            if (!userId) {
                $('#userIdError').text('Email/User ID is required.');
                return;
            }

            // Clear previous error messages
            $('#userIdError').text('');

            // AJAX request to send OTP
            $.ajax({
                url: 'https://vms.nuvoco.in/vmsdb/requestOtp.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ email: userId }),
                success: function (response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;

                        if (result.status === 'success') {
                            // Display success message
                            $('#otpNotification').text(result.message).show();

                            // Make the email field read-only
                            $('#userId').prop('readonly', true);

                            // Show the OTP and new password form
                            $('#updatePasswordForm').show();
                        } else {
                            alert(result.message); // Show backend error
                        }
                    } catch (error) {
                        console.error('Invalid response:', response);
                        alert('An unexpected error occurred. Please try again later.');
                    }
                },
                error: function () {
                    alert('An error occurred while processing your request. Please try again later.');
                }
            });
        });

        // Handle password update
        $('#updatePasswordBtn').on('click', function () {
            const userId = $('#userId').val();
            const otp = $('#otp').val();
            const newPassword = $('#newPassword').val();

            // Validate inputs
            let hasError = false;
            if (!otp) {
                $('#otpError').text('OTP is required.');
                hasError = true;
            } else {
                $('#otpError').text('');
            }

            if (!newPassword || newPassword.length < 6) {
                $('#newPasswordError').text('New password must be at least 6 characters long.');
                hasError = true;
            } else {
                $('#newPasswordError').text('');
            }

            if (hasError) {
                return;
            }

            // AJAX request to validate OTP and update password
            $.ajax({
                url: 'https://vms.nuvoco.in/vmsdb/update_password2.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ email: userId, otp: otp, newpassword: newPassword }),
                success: function (response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;

                        if (result.success) {
                            alert(result.message); // Password updated successfully
                            window.location.href = 'signup.php?action=signin'; // Redirect to sign-in
                        } else {
                            alert(result.message); // Show backend error
                        }
                    } catch (error) {
                        console.error('Invalid response:', response);
                        alert('An unexpected error occurred. Please try again later.');
                    }
                },
                error: function () {
                    alert('An error occurred while processing your request. Please try again later.');
                }
            });
        });
    });
</script>



<!-- az-column-signup -->
      <?php } else { ?>
        <div class="az-column-signup">
    <div class="az-signup-header">
        <h2>Sign-Up for Visitors</h2>

        <form id="signupForm">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="vName" required class="form-control validate-name" placeholder="Enter your Full Name" pattern="[A-Za-z\s]+" title="Name should only contain alphabets and spaces." oninput="validateName(this)">
                <small class="text-danger name-error"></small>
            </div><!-- form-group -->

            <div class="form-group">
                <label>Official Email</label>
                <input type="email" name="vEmail" required class="form-control validate-email" placeholder="Enter your Email" oninput="validateEmail(this)">
                <small class="text-danger email-error"></small>
            </div><!-- form-group -->

            <div class="form-group">
                <label>Official Mobile Number</label>
                <input type="tel" name="vMobile" maxlength="10" required class="form-control validate-mobile" placeholder="Enter your Mobile No." oninput="validateMobile(this)">
                <small class="text-danger mobile-error"></small>
            </div><!-- form-group -->

            <div class="form-group">
                <label>Company Name</label>
                <input type="text" name="vCompany" class="form-control" required placeholder="Enter your Company Name">
            </div><!-- form-group -->

            <div class="form-group">
                <label for="cityState">Visitor's City/State</label>
                <div class="form-group d-flex">
                <select onchange="print_city('state', this.selectedIndex);" id="city" name="vLocation" class="form-control" required>
    <option value="">Select City</option>
</select>
<select id="state" name="state" class="form-control" required>
    <option value="">Select State</option>
</select>
<script>
    // Debug to check dropdown population
    document.addEventListener('DOMContentLoaded', () => {
        print_state("city");
        console.log("City dropdown:", document.getElementById('city').innerHTML);
        console.log("State dropdown:", document.getElementById('state').innerHTML);
    });
</script>

            </div><!-- form-group -->

            <div class="form-group">
                <label>Date of Birth</label>
                <input type="text" name="vBirthday" required class="form-control validate-dob" placeholder="DD-MM-YYYY" maxlength="10" oninput="validateDOB(this)">
                <small class="text-danger dob-error"></small>
            </div><!-- form-group -->

            <div class="form-group">
                <label>Designation</label>
                <input type="text" name="vDesignation" class="form-control validate-designation" required placeholder="Enter your Designation" pattern="[A-Za-z\s]+" title="Designation should only contain alphabets and spaces." oninput="validateDesignation(this)">
                <small class="text-danger designation-error"></small>
            </div><!-- form-group -->

            <button type="submit" class="btn btn-az-primary btn-block">Create Account</button>
        </form>
        <br />
        <div class="az-signup-footer">
            <p>Already have an account? <a href="signup.php?action=signin">Sign In</a></p>
        </div><!-- az-signin-footer -->
    </div><!-- az-signup-header -->
</div><!-- az-column-signup -->
<?php } ?>

    </div><!-- az-signup-wrapper -->

<script src="lib/jquery/jquery.min.js"></script>
<script src="lib/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="lib/ionicons/ionicons.js"></script>
<script src="js/jquery.cookie.js" type="text/javascript"></script>
<script src="js/azia.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
 $('#signupForm').on('submit', function (e) {
    e.preventDefault();

    const formData = {};
    $(this).serializeArray().forEach((field) => {
        formData[field.name] = field.value.trim(); // Collect and trim all fields
    });

    console.log('Form Data:', formData); // Debugging form data

    $.ajax({
    url: 'https://vms.nuvoco.in/vmsdb/register.php',
    type: 'POST',
    dataType: 'json',
    contentType: 'application/json', // Sending data as JSON
    data: JSON.stringify(formData), // Your form data
    success: function (response) {
        if (response.success) {
            alert(response.message); // Show success message
            $('#signupForm')[0].reset(); // Reset the form
            // Redirect after 3 seconds if a redirect URL is provided
            if(response.redirect) {
                setTimeout(function(){
                    window.location.href = response.redirect;
                }, 1500);
            }
        } else {
            alert(response.message); // Show error message
        }
    },
    error: function (xhr, status, error) {
        console.error('AJAX Error:', error);
        alert('Something went wrong! Please try again.');
    },
  });
});

    
</script>
<script>
    function validateName(input) {
        const pattern = /^[A-Za-z\s]+$/;
        const errorElement = input.nextElementSibling;
        if (!pattern.test(input.value) && input.value.length > 0) {
            errorElement.textContent = 'Name should only contain alphabets and spaces.';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = '';
            input.style.borderColor = '';
        }
    }

    function validateMobile(input) {
        const pattern = /^\d{10}$/;
        const errorElement = input.nextElementSibling;
        if (!pattern.test(input.value) && input.value.length > 0) {
            errorElement.textContent = 'Mobile number must be exactly 10 digits.';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = '';
            input.style.borderColor = '';
        }
    }

    function validateEmail(input) {
        const pattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        const errorElement = input.nextElementSibling;
        if (!pattern.test(input.value) && input.value.length > 0) {
            errorElement.textContent = 'Enter a valid email address.';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = '';
            input.style.borderColor = '';
        }
    }

    function validateDOB(input) {
        const pattern = /^\d{2}-\d{2}-\d{4}$/;
        const errorElement = input.nextElementSibling;
        if (!pattern.test(input.value) && input.value.length > 0) {
            errorElement.textContent = 'Date must be in DD-MM-YYYY format.';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = '';
            input.style.borderColor = '';
        }
    }

    function validateDesignation(input) {
        const pattern = /^[A-Za-z\s]+$/;
        const errorElement = input.nextElementSibling;
        if (!pattern.test(input.value) && input.value.length > 0) {
            errorElement.textContent = 'Designation should only contain alphabets and spaces.';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = '';
            input.style.borderColor = '';
        }
    }

    // Initialize Select2
    $(document).ready(function () {
        $('#city, #state').select2({
            placeholder: function () {
                return $(this).data('placeholder');
            },
            allowClear: true
        });
        $('#state').on('change', function () {
    $(this).val($.trim($(this).val()));
});

    });
</script>

<script>
  function togglePasswordVisibility() {
    const passwordField = document.getElementById('passwordField');
    if (passwordField.type === 'password') {
      passwordField.type = 'text';
    } else {
      passwordField.type = 'password';
    }
  }
</script>

<?php /*?>    <script src="lib/jquery/jquery.min.js"></script>
    <script src="lib/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="lib/ionicons/ionicons.js"></script>
    <script src="js/jquery.cookie.js" type="text/javascript"></script>

    <script src="js/azia.js"></script>
    <script>
      $(function(){
        'use strict'

      });
    </script>
<?php */?>  </body>
</html>
