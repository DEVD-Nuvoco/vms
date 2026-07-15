<?php include("header.php");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if($_SESSION['loginType']=='E'){ 
$_SESSION['mess'] = 'You are not allowed to change your official details.';
header('location:index.php'); exit();
}

if(isset($_POST['SaveUpdate'])){

$name           = $_POST['userName'] ??" ";
$company        = $_POST['userCompany']??" ";
$designation    = $_POST['userDesignation']?? " " ;
$gender         = $_POST['userGender']??" ";
$city           = $_POST['userCity']??" ";
$state          = $_POST['userState']??" ";
$email          = $_POST['userEmail']??" ";
$mobile         = $_POST['userMobile']??" ";
$age             = $_POST['userAge']??" ";
$address        = $_POST['userAddress']??" ";
$zip            = $_POST['userZIPCode']??" ";

$updatedata = mysqli_query($mysqli, "UPDATE tbl_user SET
                                        userName = '$name',          
                                        userCompany = '$company',  
                                        userDesignation = '$designation',           
                                        userMobile = '$mobile',         
                                        userGender = '$gender',             
                                        userAge = $age,                     
                                        userAddress = '$address',      
                                        userCity = '$city',            
                                        userState = '$state',                 
                                        userZIPCode = '$zip',           
                                        userEmail = '$email'
                                    WHERE id = $userId ");

if($updatedata){    $_SESSION['mess'] = 'Details Updated Successfully.';
                    header('location:updatePersonalInfo.php'); exit();
}}

$getData = mysqli_query($mysqli,"Select * from tbl_user where id = $userId");
$result = mysqli_fetch_array($getData);

?>
<div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">
  <div class="container">

    <?php include("myProfileLeft.php")?>
    <div class="az-content-body pd-lg-l-40 d-flex flex-column">
      <h2 class="az-content-title">Update Personal Information</h2>
      <form action="updatePersonalInfo.php" method="post" id="updateForm">
        <div class="row">
          <div class="col-lg-6" align="center">
            <table width="100%" border="0" cellspacing="4" cellpadding="4">
              <tr>
                <td><strong>Name:</strong></td>
                <td>
                  <input type="text" value="<?php echo $result['userName']?>" name="userName" id="userName" required pattern="^[a-zA-Z\s]{3,50}$" title="Name should only contain letters and spaces.">
                  <span class="error" id="userNameError"></span>
                </td>
              </tr>
              <tr>
                <td><strong>Company Name:</strong></td>
                <td>
                  <input type="text" value="<?php echo $result['userCompany']?>" name="userCompany" id="userCompany" required pattern="^[a-zA-Z\s]{3,100}$" title="Company Name should only contain letters and spaces.">
                  <span class="error" id="userCompanyError"></span>
                </td>
              </tr>
              <tr>
                <td><strong>Designation:</strong></td>
                <td>
                  <input type="text" value="<?php echo $result['userDesignation']?>" name="userDesignation" id="userDesignation" required pattern="^[a-zA-Z\s]{3,50}$" title="Designation should only contain letters and spaces.">
                  <span class="error" id="userDesignationError"></span>
                </td>
              </tr>
              <tr>
                <td><strong>Gender:</strong></td>
                <td>
                  <label for="Male" style="cursor:pointer;"> Male <input required type="radio" id="Male" name="userGender" value="Male" <?php if($result['userGender']=='Male'){ echo "checked"; }?> ></label>
                  <label for="Female" style="cursor:pointer;"> Female <input required type="radio" id="Female" name="userGender" value="Female" <?php if($result['userGender']=='Female'){ echo "checked"; }?>></label>
                  <span class="error" id="userGenderError"></span>
                </td>
              </tr>
              <tr>
                <td><strong>City:</strong></td>
                <td>
                  <input type="text" value="<?php echo $result['userCity']?>" name="userCity" id="userCity" required pattern="^[a-zA-Z\s]{3,50}$" title="City should only contain letters and spaces.">
                  <span class="error" id="userCityError"></span>
                </td>
              </tr>
              <tr>
                <td><strong>State:</strong></td>
                <td>
                  <input type="text" value="<?php echo $result['userState']?>" name="userState" id="userState" required pattern="^[a-zA-Z\s]{3,50}$" title="State should only contain letters and spaces.">
                  <span class="error" id="userStateError"></span>
                </td>
              </tr>
            </table>
          </div>
          <div class="col-lg-6" align="center">
            <table width="100%" border="0" cellspacing="4" cellpadding="4">
              <tr>
                <td><strong>Email:</strong></td>
                <td>
                  <input type="email" value="<?php echo $result['userEmail']?>" name="userEmail" id="userEmail" required>
                  <span class="error" id="userEmailError"></span>
                </td>
              </tr>
              <tr>
                <td><strong>Mobile:</strong></td>
                <td>
                  <input type="text" maxlength="10" minlength="10" value="<?php echo $result['userMobile']?>" name="userMobile" id="userMobile" required pattern="^[0-9]{10}$" title="Mobile number should be 10 digits.">
                  <span class="error" id="userMobileError"></span>
                </td>
              </tr>
              <tr>
                <td><strong>Age:</strong></td>
                <td>
                  <input type="number" maxlength="2" value="<?php echo $result['userAge']; ?>" name="userAge" id="userAge" required min="18" max="99" title="Age must be between 18 and 99.">
                  <span class="error" id="userAgeError"></span>
                </td>
              </tr>
              <tr>
                <td valign="top"><strong>Address:</strong></td>
                <td>
                  <textarea rows="3" cols="30" name="userAddress" id="userAddress" required><?php echo $result['userAddress']?></textarea>
                  <span class="error" id="userAddressError"></span>
                </td>
              </tr>
              <tr>
                <td valign="top"><strong>ZIP/Postal Code:</strong></td>
                <td>
                  <input type="text" maxlength="6" minlength="5" value="<?php echo $result['userZIPCode']; ?>" name="userZIPCode" id="userZIPCode" required pattern="^[0-9]{5,6}$" title="ZIP/Postal Code should be 5-6 digits.">
                  <span class="error" id="userZIPCodeError"></span>
                </td>
              </tr>
              <tr>
                <td>
                  <input type="submit" value="Submit" class="btn btn-success" name="SaveUpdate" id="submitButton"> 
                  <input type="button" onClick="window.location.href='myProfile.php';" value="Back" class="btn btn-danger">
                </td>
                <td></td>
              </tr>
            </table>
          </div>
        </div>
      </form>
      <div class="ht-40"></div>
      <?php include("footer.php")?>
    </div><!-- az-content-body -->
  </div><!-- container -->
</div><!-- az-content -->
<script>
// Real-time validation with error highlighting and messages
const form = document.getElementById("updateForm");
const submitButton = document.getElementById("submitButton");
const fields = ["userName", "userCompany", "userDesignation", "userCity", "userState", "userEmail", "userMobile", "userAge", "userAddress", "userZIPCode"];

fields.forEach(fieldId => {
    const field = document.getElementById(fieldId);
    const errorField = document.getElementById(`${fieldId}Error`);
    field.addEventListener("input", () => {
        if (field.checkValidity()) {
            field.style.border = "1px solid #ddd";
            errorField.textContent = "";
        } else {
            field.style.border = "1px solid red";
            errorField.textContent = field.title || "Invalid input.";
            errorField.style.color = "red";
        }
        validateForm();
    });
});

function validateForm() {
    let isValid = true;
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (!field.checkValidity()) {
            isValid = false;
        }
    });
    submitButton.disabled = !isValid;
}

validateForm();
</script>
</body>
</html>
