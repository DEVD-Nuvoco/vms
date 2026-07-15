<?php include("header.php");
// Start session to access session variables

// Store the session messages in variables and unset the session variables to clear them after displaying
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);?>
<script src="cities.js"></script>
<script src="ajaxable.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<style>#cameraStream {
      width: 300px; /* Set the desired width */
      height: auto; /* Maintain aspect ratio */
      border: 1px solid #ddd; /* Optional styling */
      border-radius: 8px; /* Optional rounded corners */
  }
  #visitorList {
    max-height: 300px; /* Set the maximum height for the list */
    overflow-y: auto; /* Enable vertical scrolling */
    display: none; /* Initially hide the list */
    border: 1px solid #ccc; /* Optional: Add a border for better visibility */
    border-radius: 5px; /* Optional: Rounded corners */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Optional: Add a shadow for a floating effect */
    background-color: #fff; /* Background color for the list */
    z-index: 1000; /* Ensure the list is above other elements */
    position: absolute; /* Required if the list needs to appear near an input field */
    width: 100%; /* Match the width of the input field or parent container */
}</style>
<div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">
<div id="messageModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body text-center">
        <!-- Icon will be set dynamically -->
        <div id="messageIcon" style="font-size: 3rem; margin-bottom: 10px;"></div>
        <!-- Message text -->
        <p id="messageText"></p>
      </div>
    </div>
  </div>
</div>

  <div class="container">
    
    <div class="az-content-left az-content-left-components">
      
      <div class="component-item">
        <label>Previous Meetings</label>
        <nav class="nav flex-column">
          <a href="watchman2.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'watchman2.php' ? 'active' : ''; ?>">Issue Gear</a>
          <a href="watchman.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'watchman.php' ? 'active' : ''; ?>">Gate-In & Gear Tracking</a>
          <a href="newMeeting.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'newMeeting.php' ? 'active' : ''; ?>">New Meeting</a>
          <a href="ongoingMeeting.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ongoingMeeting.php' ? 'active' : ''; ?>">Ongoing Meeting</a>
          <a href="blacklist.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'blacklist.php' ? 'active' : ''; ?>">Blacklist User</a>
        </nav>
      </div>
    </div>
    <div class="az-content-body pd-lg-l-40 d-flex flex-column">
      <h2 class="az-content-title">New Meeting</h2>
      <?php if (!empty($successMessage)) : ?>
        <div class="alert alert-success">
          <?php echo $successMessage; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($errorMessage)) : ?>
        <div class="alert alert-danger">
          <?php echo $errorMessage; ?>
        </div>
      <?php endif; ?>
      <div id="responseMessage" class="alert" style="display: none;"></div>
      <div class="row">
        <div class="container">
        <form id="visitorEntryForm" action="registereduser.php" method="POST" enctype="multipart/form-data" style="width:800px;">
        <div class="row">
    <!-- Column 1 -->
    <div class="col-lg-4 col-md-6">
    <div class="form-group">
        <label for="visitorName">Visitor's Name *</label>
        <input type="text" name="visitorName" id="visitorName" autocomplete="off" class="form-control"
            placeholder="Visitor's Name" pattern="[A-Za-z\s]+" title="Name should only contain alphabets and spaces."
            required oninput="validateName(this); fetchVisitorSuggestions(this.value)" />
            <ul id="visitorList" class="list-group" style="position: absolute; width: 100%; display: none;"></ul>        <small id="nameError" class="text-danger"></small>
    </div>
    <div class="form-group">
        <label for="visitorMobile">Visitor's Mobile Number *</label>
        <input type="text" name="visitorMobile" id="visitorMobile" autocomplete="off" class="form-control"
            placeholder="Visitor's Number" required maxlength="10" oninput="validateMobile(this)" />
        <small id="mobileError" class="text-danger"></small>
    </div>
    <div class="form-group">
        <label for="gender">Visitor's Gender* </label>
        <select id="gender" name="gender" class="form-control" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Others">Others</option>
        </select>
    </div>
    <div class="form-group">
        <label for="visitPurpose">Personal/Official Visit*</label>
        <select id="visitPurpose" name="visitPurpose" class="form-control" required>
            <option value="">Select Visit Purpose</option>
            <option value="Personal">Personal</option>
            <option value="Official">Official</option>
        </select>
    </div>
</div>

<!-- Column 2 -->
<div class="col-lg-4 col-md-12">
<div class="form-group">
            <label for="searchMeetTo">Meeting with *</label>
            <input type="text" name="searchMeetTo" id="searchMeetTo" autocomplete="off" class="form-control" placeholder="Person to meet" required />
            <input type="hidden" name="empCode" id="empCode" />
            <input type="hidden" name="emplocation" id="emplocation" />
            <ul id="empList" class="list-group" style="position: absolute; width: 100%; display: none;"></ul>
        </div>
    <div class="form-group">
        <label for="visitorCompany">Visitor's Company</label>
        <input type="text" id="visitorCompany" name="visitorCompany" class="form-control"
            placeholder="Enter visitor's company" />
    </div>
    <label for="cityState">Visitor's City/State</label>
    <div class="form-group d-flex">
        <select onchange="print_city('state', this.selectedIndex);" id="city" name="city" class="form-control" required></select>
        <select id="state" name="state" class="form-control" required></select>
        <script language="javascript">print_state("city");</script>
    </div>
    <div class="form-group">
        <label for="vehiclePermit">Vehicle Permit</label>
        <select id="vehiclePermit" name="vehiclePermit" class="form-control">
            <option value="">Select Permit Status</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    </div>
</div>

<!-- Column 3 -->
<div class="col-lg-4">
    <div class="form-group">
        <label for="visitorEmail">Visitor's Email*</label>
        <input type="email" name="visitorEmail" id="visitorEmail" autocomplete="off" class="form-control"
            placeholder="Visitor's Email" required oninput="validateEmail(this)" />
        <small id="emailError" class="text-danger"></small>
    </div>
    <div class="form-group">
        <label for="visitorDesignation">Visitor Designation</label>
        <input type="text" id="visitorDesignation" name="visitorDesignation" class="form-control"
            placeholder="Enter visitor designation" required />
    </div>
    <div class="form-group">
        <label for="visitorAge">Visitor's Age</label>
        <input type="number" id="visitorAge" name="visitorAge" class="form-control" placeholder="Enter visitor age"
             min="1" max="120" oninput="validateAge(this)" />
        <small id="ageError" class="text-danger"></small>
    </div>
</div>
</div>

<div class="form-group">
  <label>Safety Induction done?</label>
  <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
    <label class="btn btn-outline-primary flex-fill">
      <input type="radio" name="safetyInduction" value="Yes" autocomplete="off" required> Yes
    </label>
    <label class="btn btn-outline-primary flex-fill">
      <input type="radio" name="safetyInduction" value="No" autocomplete="off"> No
    </label>
  </div>
</div>

  <!-- Dynamic Group Members Section -->
  <div class="form-group">
              <label for="visitType">Visit Type*</label>
              <select id="visitType" name="visitType" class="form-control" required>
                <option value="Single">Single</option>
                <option value="Group">Group</option>
              </select>
            </div>
            <div id="groupMembersSection" style="display: none;">
              <h4>Group Members</h4>
              <div id="groupMembers"></div>
              <button type="button" id="addMemberBtn" class="btn btn-secondary">Add Group Member</button>
            </div>
  <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
  <h4 style="margin-right: 10px; margin-bottom: 0;">Visit Time Range</h4>

    <div style="display: flex; align-items: center;">
    <input type="hidden" name="meetingDays" id="meetingDays" value="S">

        <input type="checkbox" id="multipleDay" name="visitType" value="multiple" style="margin-right: 5px;">
        <label for="multipleDay" style="margin-bottom: 0;">Select for Multiple Days</label>
    </div>
</div>

<div class="form-group" id="singleDayInputs" style="margin-top: 10px;">

<input type="datetime-local" id="startTime" name="startTime" >


<input type="datetime-local" id="endTime" name="endTime" >

</div>

<div class="form-group" id="multipleDayInputs" style="display: none; margin-top: 10px;">
    <label for="startDate">Multiple Day Visit:</label>
    <input type="datetime-local" id="startDate">
    to
    <input type="datetime-local" id="endDate">
</div>
<div class="form-group">
  <h4>Upload or Take a Picture</h4>
  <div class="d-flex justify-content-between align-items-center">
    <!-- Upload Image -->
    <div> 
      <label for="uploadImage">Upload Image:</label>
      <input type="file" id="uploadImage" name="visitorImage" accept="image/*" class="form-control" />
    </div>

    <!-- Open Camera -->
    <div>
    <button id="openCameraButton">Open Camera</button>
      <button type="button" id="closeCameraButton" class="btn btn-danger" style="display: none;">Close Camera</button>
    </div>
  </div>

  <!-- Camera Section (Initially Hidden) -->
  <div id="cameraSection">
    <video id="cameraStream" autoplay playsinline></video>
    <canvas id="capturedImage" style="display: none;"></canvas>
    <button id="captureButton">Capture</button>
</div>
<img id="imagePreview" alt="Captured Image Preview" style="max-width: 300px; border: 1px solid #ddd;" />
<input type="hidden" id="capturedImageData" name="capturedImageData" />
</div>
  <!-- Gear Section -->
  <div class="form-group">
  <h4>PP</h4>
<div id="gearSection">
  <div class="gear-item d-flex align-items-center">
  <label style="display:block;">PP Name</label>
    <select name="gear[]" class="form-control gear-select">
      <option value="">Select PP</option>
      <option value="Helmet">Helmet</option>
      <option value="Mask">Mask</option>
      <option value="custom">Add Custom Gear</option>
    </select>
    <label class="ml-2" style="display:block;">Qty</label>
    <input type="number" name="gear-quantity[]" class="form-control ml-2" min="1" value="1" style="width: 80px;">
    <label class="ml-2" style="display:block;">Is the PP returnable?</label>
    <select name="returnable[]" class="form-control ml-2">
    <option value="Yes" selected>Yes</option>
    <option value="No">No</option>
  </select>
    <button type="button" class="btn btn-danger ml-2 remove-gear-btn">Remove</button>
  </div>
</div>
<button type="button" id="addGearBtn" class="btn btn-secondary mt-3">Add More PP</button>
<h4 class="mt-4">ID Cards</h4>
<div id="idCardSection">
  <div class="id-card-item d-flex align-items-center">
    <select name="id_card[]" class="form-control id-card-select">
      <option value="">Select ID Card</option>
      <option value="Aadhar Card">Aadhar Card</option>
      <option value="PAN Card">PAN Card</option>
      <option value="Driver License">Driver License</option>
      <option value="Election Card">Election Card</option>
      <option value="custom">Add Custom ID Proof</option>
    </select>
    <input type="text" name="id-number[]" class="form-control ml-2" placeholder="Enter ID Number" style="width: 200px;">
    <button type="button" class="btn btn-danger ml-2 remove-id-btn">Remove</button>
  </div>
</div>
<button type="button" id="addIdCardBtn" class="btn btn-secondary mt-3">Add ID Card</button>
  <!-- Baggage and Submit Button Row -->
  <div class="row mt-3">
    <div class="col-lg-8">
      <div class="form-group">
        <label for="baggageDetails"> Visitor's Luggage Details</label>
        <textarea id="baggageDetails" name="baggageDetails" class="form-control" placeholder="Baggage Details (if any)"></textarea>
      </div>
    </div>
    <div class="col-lg-4 mt-4">
      <button type="submit" class="btn btn-primary w-100">Submit</button>
    </div>
  </div>
</form>
        </div>
        <div class="ht-40"></div>
        <?php include("footer.php") ?>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
  ajaxable('#visitorEntryForm').onResponse(function(res, params) {
      // Handle the response from the server
      var responseMessage = $('#responseMessage');
      if (res.status === 'success') {
        responseMessage.removeClass('alert-danger').addClass('alert-success');
        responseMessage.text(res.message).show().delay(5000).fadeOut();
        $('#visitorEntryForm')[0].reset(); // Clear the form fields
      } else {
        responseMessage.removeClass('alert-success').addClass('alert-danger');
        responseMessage.text('Error: ' + res.message).show().delay(5000).fadeOut();
      }
    })
</script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
  @media (max-width: 768px) {
    .form-group label {
      font-size: 0.9rem;
    }
    .form-control {
      font-size: 0.9rem;
    }
    .d-flex {
      flex-direction: column;
    }
    .form-group {
      margin-bottom: 1rem;
    }
  }
  @media (max-width: 576px) {
    .az-content-body {
      padding-left: 20px;
    }
  }
  #empList {
    border: 1px solid #ddd;
    max-height: 200px;
    overflow-y: auto;
    list-style-type: none;
    padding-left: 0;
    z-index: 1000;
    background-color: white;
  }
  #empList li {
    padding: 8px 12px;
    cursor: pointer;
  }
  #empList li:hover {
    background-color: #f1f1f1;
  }
    .camera-section video, .camera-section canvas {
      width: 100%;
      max-width: 300px;
    }

</style>

<script>
  const openCameraButton = document.getElementById('openCameraButton');
  const closeCameraButton = document.getElementById('closeCameraButton');
  const cameraSection = document.getElementById('cameraSection');
  const cameraStream = document.getElementById('cameraStream');
  const captureButton = document.getElementById('captureButton');
  const capturedImage = document.getElementById('capturedImage');
  const capturedImageData = document.getElementById('capturedImageData');
  const base = capturedImage.toDataURL('image/jpg'); // For PNG. Use 'image/jpeg' for JPEG.

// Assign Base64 data to the hidden input field
captureButton.addEventListener('click', async function (e) {
  e.preventDefault();
    try {
        // Ensure video is playing
        if (cameraStream.readyState === HTMLMediaElement.HAVE_ENOUGH_DATA && 
            cameraStream.videoWidth > 0 && cameraStream.videoHeight > 0) {
            
            const offscreenCanvas = document.createElement('canvas');
            const context = offscreenCanvas.getContext('2d');            
            offscreenCanvas.width = cameraStream.videoWidth;
            offscreenCanvas.height = cameraStream.videoHeight;

            // Draw the current video frame to the canvas
            context.drawImage(cameraStream, 0, 0, offscreenCanvas.width, offscreenCanvas.height);

            // Convert the canvas to a Base64 string
            const base64Image = offscreenCanvas.toDataURL('image/png');
            capturedImageData.value = base64Image;

            // Display the captured image for preview
            const imgPreview = document.getElementById('imagePreview');
            imgPreview.src = base64Image;

            // Stop the video stream after capture (optional)
            const tracks = cameraStream.srcObject.getTracks();
            tracks.forEach(track => track.stop());
        } else {
            console.error('Video is not ready or video dimensions are invalid.');
        }
    } catch (error) {
        console.error('Error capturing image:', error);
    }
});
function fetchVisitorSuggestions(query) {
    const visitorList = document.getElementById('visitorList');
    visitorList.innerHTML = '';

    if (query.length > 0) {
        $.ajax({
            url: 'https://vms.nuvoco.in/vmsdb/search_users.php', // Backend PHP script to fetch visitors
            type: 'GET',
            data: { searchIndex: query },
            success: function (response) {
                const data = JSON.parse(response);
                if (data.length > 0) {
                    visitorList.style.display = 'block'; // Show suggestions
                    data.forEach(visitor => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex align-items-center'; // Styling for list item

                        const img = document.createElement('img');
                        img.src = `https://vms.nuvoco.in/vmsdb/faces/${visitor.userId}_profile.webp`; // Dynamic image URL
                        img.alt = visitor.userName; // Alt text
                        img.className = 'rounded-circle me-3'; // Bootstrap styling for rounded images
                        img.style.width = '40px'; // Set image size
                        img.style.height = '40px';
                        img.style.margin = '10px';  // Set image size
                        img.onerror = function () {
                            this.src = 'https://vms.nuvoco.in/vmsdb/faces/default2.jpg'; // Default image URL
                        };
                        // Add visitor name
                        const span = document.createElement('span');
                        span.textContent = visitor.userName;

                        li.appendChild(img);
                        li.appendChild(span);

                        // Add onclick functionality
                        li.onclick = function () {
                            selectVisitor(visitor);
                        };

                        visitorList.appendChild(li);
                    });
                } else {
                    visitorList.style.display = 'none'; // Hide if no suggestions
                }
            },
            error: function () {
                console.error('Error fetching visitor suggestions.');
            }
        });
    } else {
        clearPopulatedFields(); // Clear fields when input is cleared
        visitorList.style.display = 'none'; // Hide suggestions for empty query
    }
}

function selectVisitor(visitor) {
    document.getElementById('visitorName').value = visitor.userName;
    document.getElementById('visitorMobile').value = visitor.userMobile || '';
    document.getElementById('visitorEmail').value = visitor.userEmail || '';
    document.getElementById('visitorDesignation').value = visitor.userDesignation || '';
    document.getElementById('visitorCompany').value = visitor.userCompany || '';
    document.getElementById('visitorAge').value = visitor.userAge || '';
    document.getElementById('state').value = visitor.userCity || '';
    document.getElementById('city').value = visitor.userState || '';
  

    // Set text color to black
    setFieldTextColor('black');

    // Hide the suggestion list
    const visitorList = document.getElementById('visitorList');
    visitorList.innerHTML = '';
    visitorList.style.display = 'none';
}

function clearPopulatedFields() {
    document.getElementById('visitorMobile').value = '';
    document.getElementById('visitorEmail').value = '';
    document.getElementById('visitorDesignation').value = '';
    document.getElementById('visitorCompany').value = '';
    document.getElementById('visitorAge').value = '';

    // Reset text color to default
    setFieldTextColor('');
}

function setFieldTextColor(color) {
    const fields = [
        'visitorMobile',
        'visitorEmail',
        'visitorDesignation',
        'visitorCompany',
        'visitorAge',
    ];
    fields.forEach(fieldId => {
        document.getElementById(fieldId).style.color = color;
    });
}

// Hide suggestions when the input loses focus
document.getElementById('visitorName').addEventListener('blur', function () {
    setTimeout(() => {
        const visitorList = document.getElementById('visitorList');
        visitorList.style.display = 'none';
    }, 200); // Small delay to allow for selection if a suggestion is clicked
});

// Hide suggestions and clear fields when the input is empty
document.getElementById('visitorName').addEventListener('input', function () {
    const visitorList = document.getElementById('visitorList');
    if (this.value.trim() === '') {
        visitorList.style.display = 'none'; // Hide suggestions
        clearPopulatedFields(); // Clear populated fields
    }
});

   function validateName(input) {
        const pattern = /^[A-Za-z\s]+$/;
        const errorElement = document.getElementById('nameError');
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
        const errorElement = document.getElementById('mobileError');
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
        const errorElement = document.getElementById('emailError');
        if (!pattern.test(input.value) && input.value.length > 0) {
            errorElement.textContent = 'Enter a valid email address.';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = '';
            input.style.borderColor = '';
        }
    }

    function validateAge(input) {
        const age = parseInt(input.value, 10);
        const errorElement = document.getElementById('ageError');
        if (age < 1 || age > 120) {
            errorElement.textContent = 'Age must be between 1 and 120.';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = '';
            input.style.borderColor = '';
        }
    }
    $(document).ready(function () {
  $('#visitorEntryForm').on('submit', function (e) {
    e.preventDefault(); // Prevent default form submission
    if (!validateTime()) {
        return; // Abort submission if validation fails
    }
    const formData = new FormData(document.getElementById('visitorEntryForm'))
if (capturedImage) {
    formData.append('capturedImage3', base);
}
const gearDetails = [];
    document.querySelectorAll('#gearSection .gear-item').forEach(item => {
        const gearName = item.querySelector('.gear-select').value;
        const gearQuantity = item.querySelector('[name="gear-quantity[]"]').value;

        if (gearName && gearQuantity) {
            gearDetails.push({ gearName, gearQuantity: parseInt(gearQuantity, 10) });
        }
    });

    // Add gear details to formData as JSON string
    formData.append('gear', JSON.stringify(gearDetails));

    const groupMembersDetails = [];
document.querySelectorAll('#groupMembers .form-group').forEach(member => {
    const groupMemberName = member.querySelector('input[name="groupMemberName[]"]').value;
    const groupMemberEmail = member.querySelector('input[name="groupMemberEmail[]"]').value;
    const groupMemberMobile = member.querySelector('input[name="groupMemberPhone[]"]').value; // Change: later update the input name to groupMemberMobile[]
    const groupMemberGender = member.querySelector('select[name="groupMemberGender[]"]').value;

    if (groupMemberName && groupMemberEmail && groupMemberMobile && groupMemberGender) {
        groupMembersDetails.push({
            groupMemberName,
            groupMemberEmail,
            groupMemberMobile,  // key must match the PHP side
            groupMemberGender
        });
    }
});

formData.append('groupMembers', JSON.stringify(groupMembersDetails));


// Add group members details to formData as JSON string
formData.append('groupMembers', JSON.stringify(groupMembersDetails));
    // Submit data via AJAX
    $.ajax({
  url: 'https://vms.nuvoco.in/vmsdb/newwmeeting.php',
  method: 'POST',
  processData: false,
  contentType: false,
  data: formData,
  success: function (response) {
    let iconHtml = '';
    let modalClass = '';
    
    if (response.status === 'success') {
      iconHtml = '<i class="fas fa-check-circle text-success"></i>';
      modalClass = 'alert-success';
    } else {
      iconHtml = '<i class="fas fa-times-circle text-danger"></i>';
      modalClass = 'alert-danger';
    }
    
    $('#messageIcon').html(iconHtml);
    $('#messageText').html(response.message);
    $('#messageModal .modal-content').removeClass('alert-success alert-danger').addClass(modalClass);
    
    $('#messageModal').modal('show');
    setTimeout(function() {
      $('#messageModal').modal('hide');
    }, 3000);
    
    if(response.status === 'success'){
      $('#visitorEntryForm')[0].reset();
    }
  },
  error: function (xhr) {
    let iconHtml = '<i class="fas fa-times-circle text-danger"></i>';
    let errorMessage = 'An error occurred: ' + xhr.responseText;
    
    $('#messageIcon').html(iconHtml);
    $('#messageText').html(errorMessage);
    $('#messageModal .modal-content').removeClass('alert-success').addClass('alert-danger');
    
    $('#messageModal').modal('show');
    setTimeout(function() {
      $('#messageModal').modal('hide');
    }, 3000);
  }
});

  });
});

$(document).ready(function () {
    // Show/Hide group members section
    $('#visitType').on('change', function () {
      if ($(this).val() === 'Group') {
        $('#groupMembersSection').show();
      } else {
        $('#groupMembersSection').hide();
        $('#groupMembers').html('');
      }
    });

    // Add new group member
    $('#addMemberBtn').on('click', function () {
      const memberHtml = `
        <div class="form-group row">
          <div class="col-lg-2">
            <input
              type="text"
              name="groupMemberName[]"
              class="form-control validate-name"
              placeholder="Member Name"
              pattern="[A-Za-z\\s]+"
              title="Name should only contain alphabets and spaces."
              required
            >
            <small class="text-danger name-error"></small>
          </div>
          <div class="col-lg-3">
            <input
              type="email"
              name="groupMemberEmail[]"
              class="form-control validate-email"
              placeholder="Member Email"
              required
            >
            <small class="text-danger email-error"></small>
          </div>
          <div class="col-lg-2">
            <input
              type="text"
              name="groupMemberPhone[]"
              class="form-control validate-phone"
              placeholder="Phone Number"
                 maxlength="10"
              pattern="\\d{10}"
              title="Phone number must be 10 digits."
              required
            >
            <small class="text-danger phone-error"></small>
          </div>
          <div class="col-lg-2">
            <select name="groupMemberGender[]" class="form-control" required>
              <option value="">Select Gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Others">Others</option>
            </select>
          </div>
          <div class="col-lg-1">
            <button type="button" class="btn btn-danger removeMemberBtn">Remove</button>
          </div>
        </div>`;
      $('#groupMembers').append(memberHtml);
    });

    // Remove member
    $(document).on('click', '.removeMemberBtn', function () {
      $(this).closest('.form-group').remove();
    });

    // Validation on input for name
    $(document).on('input', '.validate-name', function () {
      const value = $(this).val();
      const pattern = /^[A-Za-z\s]+$/;
      const errorElement = $(this).next('.name-error');
      if (!pattern.test(value) && value.length > 0) {
        errorElement.text('Name should only contain alphabets and spaces.');
        $(this).addClass('is-invalid');
      } else {
        errorElement.text('');
        $(this).removeClass('is-invalid');
      }
    });

    // Validation on input for email
    $(document).on('input', '.validate-email', function () {
      const value = $(this).val();
      const pattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
      const errorElement = $(this).next('.email-error');
      if (!pattern.test(value) && value.length > 0) {
        errorElement.text('Enter a valid email address.');
        $(this).addClass('is-invalid');
      } else {
        errorElement.text('');
        $(this).removeClass('is-invalid');
      }
    });

    // Validation on input for phone
    $(document).on('input', '.validate-phone', function (e) {
      const value = $(this).val();
      const pattern = /^\d{10}$/;
      const errorElement = $(this).next('.phone-error');

      // Prevent entering more than 10 digits
      if (value.length > 10) {
        $(this).val(value.slice(0, 10));
      }

      // Validate phone number
      if (!pattern.test(value) && value.length > 0) {
        errorElement.text('Phone number must be exactly 10 digits.');
        $(this).addClass('is-invalid');
      } else {
        errorElement.text('');
        $(this).removeClass('is-invalid');
      }
    });

  });

// Access the camera and display the stream

  let stream = null;


  // Open Camera Stream
  openCameraButton.addEventListener('click', (e) => {
    e.preventDefault();
    navigator.mediaDevices.getUserMedia({ video: true }).then((cameraStreamObj) => {
      stream = cameraStreamObj;
      cameraStream.srcObject = stream;
      cameraStream.style.display = 'block';
      cameraSection.style.display = 'block';
      openCameraButton.style.display = 'none';
      closeCameraButton.style.display = 'inline-block';
    }).catch((error) => {
      alert('Unable to access the camera.');
    });
  });

  // Close Camera Functionality
  closeCameraButton.addEventListener('click', (e) => {
    e.preventDefault();
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
      stream = null;
    }
    cameraStream.style.display = 'none';
    cameraSection.style.display = 'none';
    openCameraButton.style.display = 'inline-block';
    closeCameraButton.style.display = 'none';
  });

  // Capture the image from the video stream
 

  document.getElementById('addGearBtn').addEventListener('click', function () {
  const gearSection = document.getElementById('gearSection');
  const newGearItem = document.createElement('div');
  newGearItem.className = 'gear-item d-flex align-items-center';
  newGearItem.innerHTML = `
    <select name="gear[]" class="form-control gear-select">
      <option value="">Select PP</option>
      <option value="Helmet">Helmet</option>
      <option value="Mask">Mask</option>
      <option value="custom">Add Custom Gear</option>
    </select>
    <input type="number" name="gear-quantity[]" class="form-control ml-2" min="1" value="1" style="width: 80px;">
    <!-- New returnable select field -->
    <select name="returnable[]" class="form-control ml-2">
      <option value="Yes" selected>Yes</option>
      <option value="No">No</option>
    </select>
    <button type="button" class="btn btn-danger ml-2 remove-gear-btn">Remove</button>
  `;
  gearSection.appendChild(newGearItem);

  // Add event listener for "Remove" button
  newGearItem.querySelector('.remove-gear-btn').addEventListener('click', function () {
    this.parentElement.remove();
  });

  // Add event listener for custom gear input if needed
  newGearItem.querySelector('.gear-select').addEventListener('change', handleCustomGear);
});


  // Remove functionality for existing items
  document.querySelectorAll('.remove-gear-btn').forEach(button => {
    button.addEventListener('click', function () {
      this.parentElement.remove();
    });
  });

  // Custom gear input handling
  function handleCustomGear(event) {
    if (event.target.value === 'custom') {
      const customGearName = prompt('Enter the custom gear name:');
      if (customGearName) {
        const newOption = document.createElement('option');
        newOption.value = customGearName;
        newOption.textContent = customGearName;
        event.target.appendChild(newOption);
        event.target.value = customGearName;

        // Save custom gear to the database
        saveCustomGear(customGearName);
      } else {
        event.target.value = '';
      }
    }
  }

  // Save custom gear to the database using AJAX
  function saveCustomGear(gearName) {
    fetch('/save-custom-gear', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ gearName })
    })
      .then(response => {
        if (!response.ok) throw new Error('Failed to save gear');
        return response.json();
      })
      .then(data => {
        console.log('Custom gear saved:', data);
      })
      .catch(error => {
        console.error('Error saving custom gear:', error);
      });
  }
  $('#searchMeetTo').on('keyup', function() {
 
 var searchValue = $(this).val();
 if (searchValue.length === 0) {
   $('#empList').hide();
   return;
 }

 $.ajax({
   url: 'https://vms.nuvoco.in/vmsdb/search_employee.php',
   type: 'GET',
   data: { searchIndex: searchValue },
   success: function(response) {
    const employees = JSON.parse(response); // Parse JSON response
    if (employees.length > 0) {
      let listItems = '';
      employees.forEach(function(employee) {
        listItems += `
  <li data-name="${employee.empName}" data-empcode="${employee.empCode}" data-emplocation="${employee.empLocation}">
    ${employee.empName} (${employee.empLocation})
  </li>
`;
      });
      $('#empList').html(listItems).show(); // Populate the list and show it
    } else {
      $('#empList').hide(); // Hide if no results
    }
  },
   error: function() {
     $('#empList').html('<li>Error retrieving data.</li>').show();
   }
 });
});

// Handle click on dropdown item
$(document).on('click', '#empList li', function() {
  var selectedEmployee = $(this).data('name');
  var empCode = $(this).data('empcode');
  var emplocation = $(this).data('emplocation'); // Use the correct attribute name here
  console.log(emplocation); // Ensure you log the correct variable
  $('#searchMeetTo').val(selectedEmployee); 
  $('#empCode').val(empCode);
  $('#emplocation').val(emplocation); // Use the correct variable here
  $('#empList').hide(); // Hide dropdown
});

// Hide dropdown when clicking outside of the input
$(document).on('click', function(event) {
 if (!$(event.target).closest('#searchMeetTo').length) {
   $('#empList').hide();
 }
});
  // Add event listeners to existing dropdowns for custom gear
  document.querySelectorAll('.gear-select').forEach(select => {
    select.addEventListener('change', handleCustomGear);
  });
  document.getElementById('addIdCardBtn').addEventListener('click', function () {
    const idCardSection = document.getElementById('idCardSection');
    const newIdCardItem = document.createElement('div');
    newIdCardItem.className = 'id-card-item d-flex align-items-center';
    newIdCardItem.innerHTML = `
      <select name="id_card[]" class="form-control id-card-select">
        <option value="">Select ID Card</option>
        <option value="Aadhar Card">Aadhar Card</option>
        <option value="PAN Card">PAN Card</option>
        <option value="Driver License">Driver License</option>
        <option value="Election Card">Election Card</option>
        <option value="custom">Add Custom ID Proof</option>
      </select>
      <input type="text" name="id-number[]" class="form-control ml-2" placeholder="Enter ID Number" style="width: 200px;">
      <button type="button" class="btn btn-danger ml-2 remove-id-btn">Remove</button>
    `;
    idCardSection.appendChild(newIdCardItem);

    // Add event listeners
    newIdCardItem.querySelector('.remove-id-btn').addEventListener('click', function () {
      this.parentElement.remove();
    });
    newIdCardItem.querySelector('.id-card-select').addEventListener('change', handleCustomIdCard);
  });

  // Handle custom gear input
  function handleCustomGear(event) {
    if (event.target.value === 'custom') {
      const customGearName = prompt('Enter the custom gear name:');
      if (customGearName) {
        const newOption = document.createElement('option');
        newOption.value = customGearName;
        newOption.textContent = customGearName;
        event.target.appendChild(newOption);
        event.target.value = customGearName;
        saveCustomGear(customGearName);
      } else {
        event.target.value = '';
      }
    }
  }

  // Handle custom ID proof input
  function handleCustomIdCard(event) {
    if (event.target.value === 'custom') {
      const customIdProof = prompt('Enter the custom ID proof name:');
      if (customIdProof) {
        const newOption = document.createElement('option');
        newOption.value = customIdProof;
        newOption.textContent = customIdProof;
        event.target.appendChild(newOption);
        event.target.value = customIdProof;
        saveCustomId(customIdProof);
      } else {
        event.target.value = '';
      }
    }
  }
  function saveCustomGear(gearName) {
    fetch('/save-custom-gear', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ gearName })
    }).then(response => response.json()).then(console.log).catch(console.error);
  }

  // Save custom ID proof to the database
  function saveCustomId(idProofName) {
    fetch('/save-custom-id', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ idProofName })
    }).then(response => response.json()).then(console.log).catch(console.error);
  }

  // Attach event listeners to existing ID card dropdowns
  document.querySelectorAll('.id-card-select').forEach(select => {
    select.addEventListener('change', handleCustomIdCard);
  });

const multipleDayCheckbox = document.getElementById('multipleDay');
const singleDayInputs = document.getElementById('singleDayInputs');
const multipleDayInputs = document.getElementById('multipleDayInputs');
const startDateInput = document.getElementById('startDate');
const endDateInput = document.getElementById('endDate');
const startTimeInput = document.getElementById('startTime');
const endTimeInput = document.getElementById('endTime');


// Toggle visibility of single-day and multiple-day inputs
const meetingDaysInput = document.getElementById('meetingDays');

// Toggle visibility of single-day and multiple-day inputs and update the meetingDays value
const toggleVisitType = () => {
    if (multipleDayCheckbox.checked) {
        singleDayInputs.style.display = 'none';
        multipleDayInputs.style.display = 'block';
        meetingDaysInput.value = 'M'; // Set value for multiple days
    } else {
        singleDayInputs.style.display = 'block';
        multipleDayInputs.style.display = 'none';
        meetingDaysInput.value = 'S'; // Set value for single day
    }
};

// Add event listener for the checkbox
multipleDayCheckbox.addEventListener('change', toggleVisitType);

// Add event listener for the checkbox
multipleDayCheckbox.addEventListener('change', toggleVisitType);

// Validate time based on visit type
const validateTime = () => {
    const isMultipleDay = multipleDayCheckbox.checked;
    const currentDay = new Date();

    // Initialize start and end time variables
    let startTime = null;
    let endTime = null;

    if (!isMultipleDay) {
        // Single-day visit
        startTime = startTimeInput.value ? new Date(startTimeInput.value) : null;
        endTime = endTimeInput.value ? new Date(endTimeInput.value) : null;

        if (!startTime || !endTime) {
            alert('Please enter both start and end times.');
            return false;
        }

        if (startTime < currentDay || endTime < currentDay) {
            alert('Past Date bookings are not allowed.');
            startTimeInput.value = '';
            endTimeInput.value = '';
            return false;
        }

        if (startTime >= endTime) {
            alert('End time cannot be the same as or earlier than the start time!');
            startTimeInput.value = '';
            endTimeInput.value = '';
            return false;
        }

        if (startTime.getDate() !== endTime.getDate()) {
            alert('Single-day visits must have the same start and end date.');
            startTimeInput.value = '';
            endTimeInput.value = '';
            return false;
        }
    } else {
        // Multiple-day visit
        const startDate = startDateInput.value ? new Date(startDateInput.value) : null;
        const endDate = endDateInput.value ? new Date(endDateInput.value) : null;

        if (!startDate || !endDate) {
            alert('Please enter both start and end date-time values.');
            return false;
        }

        if (startDate < currentDay || endDate < currentDay) {
            alert('Past Date bookings are not allowed.');
            startDateInput.value = '';
            endDateInput.value = '';
            return false;
        }

        if (startDate > endDate) {
            alert('End date cannot be earlier than start date!');
            startDateInput.value = '';
            endDateInput.value = '';
            return false;
        }

        // Assign multiple-day inputs to startTime and endTime
        startTime = startDate;
        endTime = endDate;

        // Optionally calculate and log the number of days
        const dayCount = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
        console.log(`Number of days selected: ${dayCount}`);

        // Set the day count in a hidden input if needed
        const dayCountInput = document.getElementById('dayCount');
        if (dayCountInput) {
            dayCountInput.value = dayCount;
        }
    }

    console.log('Start Time:', startTime);
    console.log('End Time:', endTime);

    // Return true for successful validation
    return true;
};
function updateSingleDayInputs() {
    if (startDateInput.value) {
        startTimeInput.value = startDateInput.value; // Set start date to start time
    }
    if (endDateInput.value) {
        endTimeInput.value = endDateInput.value; // Set end date to end time
    }
}

// Add event listeners to handle updates when multiple-day inputs change
startDateInput.addEventListener('change', updateSingleDayInputs);
endDateInput.addEventListener('change', updateSingleDayInputs);
// Add event listeners for validation
startTimeInput.addEventListener('change', validateTime);
endTimeInput.addEventListener('change', validateTime);
startDateInput.addEventListener('change', validateTime);
endDateInput.addEventListener('change', validateTime);
multipleStartTimeInput.addEventListener('change', validateTime);
multipleEndTimeInput.addEventListener('change', validateTime);
 $(document).ready(function() {
  // Initialize Flatpickr for date-time range selection
  flatpickr("#gatepassTimeRange", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    mode: "range",
    time_24hr: true,  // Set to true for 24-hour format
    minDate: "today",
    onChange: function(selectedDates) {
      if (selectedDates.length === 2) {
        // Format the dates to 'YYYY-MM-DD HH:MM:SS' before storing them in the hidden inputs
        var startDate = flatpickr.formatDate(selectedDates[0], "Y-m-d H:i:s");
        var endDate = flatpickr.formatDate(selectedDates[1], "Y-m-d H:i:s");
        
        document.getElementById("startTime").value = startDate;
        document.getElementById("endTime").value = endDate;

        console.log("Formatted Start Time:", startDate);
        console.log("Formatted End Time:", endDate);
      }
    }
});

  // Handle employee search input with AJAX
 

  // Prevent form submission if visitor's age is less than 18
  $('#visitorEntryForm').on('submit', function(e) {
    const startTime = document.getElementById('startTime');
    const endTime = document.getElementById('endTime');
    var visitorAge = $('#visitorAge').val();
    if (visitorAge < 18) {
      alert('Visitor must be at least 18 years old to submit the form.');
      e.preventDefault(); // Prevent form submission
    }
    if (startTime.style.display === 'none' || endTime.style.display === 'none') {
        alert('Please ensure start and end times are visible and filled out.');
        e.preventDefault();
        return;
    }
    
    if (!startTime.value || !endTime.value) {
        alert('Start and End times are required.');
        e.preventDefault();
    }
  });

  // Initialize Select2 for City and State dropdowns


  // Toggle group member section based on visit type selection
 
});
$('#sts, #state').select2({
    placeholder: function() {
      return $(this).data('placeholder');
    },
    allowClear: true
  });
 
  $('#addGearBtn').on('click', function() {
        var gearHtml = `
        <div class="gear-item d-flex align-items-center mb-2">
            <select name="gear[]" class="form-control gear-select w-25" required>
                <option value="">Select PP</option>
                <option value="Helmet">Helmet</option>
                <option value="Mask">Mask</option>
            </select>
            <input type="number" name="gear-quantity[]" class="form-control ml-2" min="1" value="1" required style="width: 70px;">
            <button type="button" class="btn btn-danger ml-2 remove-gear-btn">Remove</button>
        </div>`;
        $('#gearSection').append(gearHtml);
    });

    // Remove gear fields
    $(document).on('click', '.remove-gear-btn', function() {
        $(this).closest('.gear-item').remove();
    });
    
</script> 

<!-- jQuery -->

</html>
