<?php include('header.php');

echo $userId;?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
    

        .container2 {
            max-width: 800px;
       
            background: #fff;
            padding: 20px;
            border-radius: 10px;

        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-group input[type="text"],
        .form-group input[type="datetime-local"] {
            height: 40px;
        }

        .form-group button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #76F892;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .form-group button:hover {
            background-color: #66e081;
        }

        .group-members {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f4f4f4;
        }

        .group-members .member {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .group-members .member span {
            font-size: 14px;
        }

        .group-members .member button {
            background: none;
            border: none;
            color: red;
            cursor: pointer;
        }

        .error {
            color: red;
            font-size: 12px;
        }

        .autocomplete-suggestions {
            border: 1px solid #ccc;
            max-height: 150px;
            overflow-y: auto;
            margin-top: -10px;
            background: #fff;
        }

        .autocomplete-suggestions div {
            padding: 10px;
            cursor: pointer;
        }

        .autocomplete-suggestions div:hover {
            background-color: #f4f4f4;
        }

        .submit {
            text-align: center;
        }

    </style>

<body>
<?php
// Include your database connection file

// Fetch visitor_id from session
$visitor_id = $_SESSION['userDetails']['id']; // Make sure this session is set when user logs in
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Fetch previous meetings from database
$query = "SELECT meeting_id FROM meetings 
          WHERE gate_out != '0000-00-00 00:00:00' AND visitor_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $visitor_id);
$stmt->execute();
$result = $stmt->get_result();

?>
<div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">
<div class="container" >
<div class="az-content-left az-content-left-components">
    <div class="component-item">
        <label>Previous Meetings</label>
        <nav class="nav flex-column">
            <?php
        
        if ($result->num_rows > 0) {
            // Display all previous meetings dynamically
            while ($row = $result->fetch_assoc()) {
                echo '<a href="meetingdetails.php?meeting_id=' . htmlspecialchars($row['meeting_id']) . '" class="nav-link">';
                echo 'Meeting ID: <span><strong>' . htmlspecialchars($row['meeting_id']) . '</strong></span></a>';
            }
        } else {
            echo '<p class="text-muted">No previous meetings found.</p>';
        }
        ?>
        </nav>
    </div>
</div>

<div class="az-content-body pd-lg-l-40 d-flex flex-column">
            <div class="container2">
            <h1 style="text-align: left; margin-left: 0;     font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 1rem;   color: #42bb52;">New Meeting</h1>
                <form id="newMeetingForm">
                    <div class="form-group">
                        <label for="searchMeetTo">Employee Name</label>
                        <input type="text" id="searchMeetTo" placeholder="Search by name">
                        <div id="autocompleteSuggestions" class="autocomplete-suggestions"></div>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
    <label for="visitType" style="margin-right: 10px; margin-bottom: 0;">Visit Time Range</label>
    <div style="display: flex; align-items: center;">
        <input type="checkbox" id="multipleDay" name="visitType" value="multiple" style="margin-right: 5px;">
        <label for="multipleDay" style="margin-bottom: 0;">Select for Multiple Days</label>
    </div>
</div>

<div class="form-group" id="singleDayInputs" style="margin-top: 10px;">
    <input type="datetime-local" id="startTime"> to
    <input type="datetime-local" id="endTime">
</div>

<div class="form-group" id="multipleDayInputs" style="display: none; margin-top: 10px;">
    <label for="startDate">Multiple Day Visit:</label>
    <input type="datetime-local" id="startDate">
    to
    <input type="datetime-local" id="endDate">
</div>

                    

                    <div class="form-group">
                        <label for="visitType">Visit Type</label>
                        <select id="visitType">
                            <option value="">Select Visit Type</option>
                            <option value="Single">Single</option>
                            <option value="Group">Group</option>
                        </select>
                    </div>

                    <div id="groupMembersSection" style="display: none;">
                        <div class="form-group">
                            <button type="button" id="addGroupMember">Add Group Member</button>
                        </div>
                        <div id="groupMembers" class="group-members"></div>
                    </div>

                    


<!-- Hidden input to store the database value -->
<input type="hidden" id="dbValue" name="dbValue">


                    <div class="form-group">
                        <label for="poVisit">Visit Purpose</label>
                        <select id="poVisit">
                            <option value="">Select Purpose</option>
                            <option value="Personal">Personal</option>
                            <option value="Official">Official</option>
                        </select>
                    </div>


                    <div class="form-group submit">
                        <button type="submit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

        
   


    <script>
        document.getElementById('searchMeetTo').addEventListener('input', function () {
        const query = this.value;
        const suggestionsBox = document.getElementById('autocompleteSuggestions');

        if (query.length > 2) {
            fetch(`https://vms.nuvoco.in/vmsdb/search_employee.php?searchIndex=${query}`)
                .then(response => response.json())
                .then(data => {
                    suggestionsBox.innerHTML = '';
                    data.forEach(emp => {
                        const suggestionDiv = document.createElement('div');
                        suggestionDiv.textContent = `${emp.empName} (${emp.empDepartment}, ${emp.empLocation})`;
                        empCode = emp.empCode;
                        empLocation = emp.empLocation;
                        suggestionDiv.addEventListener('click', () => {
                            document.getElementById('searchMeetTo').value = emp.empName;
                            suggestionsBox.innerHTML = '';
                        });
                        suggestionsBox.appendChild(suggestionDiv);
                    });
                });
        } else {
            suggestionsBox.innerHTML = '';
        }
    }); 
       const visitorId = "<?php echo $userId ?>";
const groupMembersArray = [];

// Show/Hide Group Member Section
const visitTypeSelect = document.getElementById('visitType');
const groupMembersSection = document.getElementById('groupMembersSection');
visitTypeSelect.addEventListener('change', function () {
    groupMembersSection.style.display = this.value === 'Group' ? 'block' : 'none';
});

// Add Group Member Logic
const addGroupMemberButton = document.getElementById('addGroupMember');
const groupMembersDiv = document.getElementById('groupMembers');
addGroupMemberButton.addEventListener('click', function () {
    const name = prompt('Enter Group Member Name:');
    const email = prompt('Enter Group Member Email:');
    const mobile = prompt('Enter Group Member Mobile (e.g., +911234567890):');

    // Regex Validations
    const nameRegex = /^[A-Za-z ]+$/;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const mobileRegex = /^\d{10}$/;

    if (!nameRegex.test(name)) {
        alert('Invalid Name. Only alphabets are allowed.');
        return;
    }

    if (!emailRegex.test(email)) {
        alert('Invalid Email. Please provide a valid email address.');
        return;
    }

    if (!mobileRegex.test(mobile)) {
        alert('Invalid Mobile Number. It should start with +91 and be followed by 10 digits.');
        return;
    }

    const memberData = {
        groupMemberName: name,
        groupMemberEmail: email,
        groupMemberMobile: mobile
    };

    groupMembersArray.push(memberData);

    const memberDiv = document.createElement('div');
    memberDiv.className = 'member';
    memberDiv.innerHTML = `<span>Name: ${name}, Email: ${email}, Mobile: ${mobile}</span>
                           <button type="button">Remove</button>`;
    groupMembersDiv.appendChild(memberDiv);

    // Remove Member Logic
    memberDiv.querySelector('button').addEventListener('click', function () {
        const index = groupMembersArray.indexOf(memberData);
        if (index > -1) {
            groupMembersArray.splice(index, 1);
        }
        groupMembersDiv.removeChild(memberDiv);
    });
});

document.getElementById('newMeetingForm').addEventListener('submit', function (event) {
    event.preventDefault();

    // Get validated time values
    const timeData = validateTime();
    if (!timeData) {
        return; // Validation failed
    }

    const formData = {
        visitorId: visitorId,
        searchMeetTo: document.getElementById('searchMeetTo').value,
        startTime: timeData.startTime.toISOString(), // Convert to ISO format for consistency
        endTime: timeData.endTime.toISOString(),
        visitType: document.getElementById('visitType').value,
        poVisit: document.getElementById('poVisit').value,
        empCode: empCode, // Update with logic to set empCode
        meetLocation: empLocation, // Update with logic to set meetLocation
        groupMembers: groupMembersArray,
        meetingDays: multipleDayCheckbox.checked ? 'M' : 'S', // Add this field
    };

    // Submit Data
    fetch('https://vms.nuvoco.in/vmsdb/submit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    }).then(response => response.json())
        .then(data => {
            alert(data.message);
            // Clear Form on Success
            document.getElementById('newMeetingForm').reset();
            document.getElementById('groupMembers').innerHTML = '';
            groupMembersArray.length = 0;
        })
        .catch(err => alert('Error: ' + err));
});



const multipleDayCheckbox = document.getElementById('multipleDay');
const singleDayInputs = document.getElementById('singleDayInputs');
const multipleDayInputs = document.getElementById('multipleDayInputs');
const startDateInput = document.getElementById('startDate');
const endDateInput = document.getElementById('endDate');
const startTimeInput = document.getElementById('startTime');
const endTimeInput = document.getElementById('endTime');

// Add additional time inputs for multiple-day visits
// const multipleStartTimeInput = document.createElement('input');
// multipleStartTimeInput.type = 'time';
// multipleStartTimeInput.id = 'multipleStartTime';
// multipleStartTimeInput.placeholder = 'Start Time';

// const multipleEndTimeInput = document.createElement('input');
// multipleEndTimeInput.type = 'time';
// multipleEndTimeInput.id = 'multipleEndTime';
// multipleEndTimeInput.placeholder = 'End Time';

// multipleDayInputs.appendChild(multipleStartTimeInput);
// multipleDayInputs.appendChild(multipleEndTimeInput);

// Toggle visibility of single-day and multiple-day inputs
const toggleVisitType = () => {
    if (multipleDayCheckbox.checked) {
        singleDayInputs.style.display = 'none';
        multipleDayInputs.style.display = 'block';
    } else {
        singleDayInputs.style.display = 'block';
        multipleDayInputs.style.display = 'none';
    }
};

// Add event listener for the checkbox
multipleDayCheckbox.addEventListener('change', toggleVisitType);

// Validate time based on visit type
const validateTime = () => {
    const isMultipleDay = multipleDayCheckbox.checked;
    const currentDay = new Date();

    let startTime, endTime; // Declare variables to store final start and end times

    if (!isMultipleDay) {
        // Validate single-day visit
        startTime = startTimeInput.value ? new Date(startTimeInput.value) : null;
        endTime = endTimeInput.value ? new Date(endTimeInput.value) : null;

        if (!startTime || !endTime) {
            alert('Please enter both start and end times.');
            return false;
        }

        if (startTime < currentDay || endTime < currentDay) {
            alert('Past Date bookings are not allowed');
            startTimeInput.value = ''; // Clear invalid input
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
        // Validate multiple-day visit
        const startDate = startDateInput.value ? new Date(startDateInput.value) : null;
        const endDate = endDateInput.value ? new Date(endDateInput.value) : null;

        if (!startDate || !endDate) {
            alert('Please enter both start and end date-time values.');
            return false;
        }

        if (startDate < currentDay || endDate < currentDay) {
            alert('Past Date bookings are not allowed');
            startDateInput.value = ''; // Clear invalid input
            endDateInput.value = '';
            return false;
        }

        if (startDate > endDate) {
            alert('End date cannot be earlier than start date!');
            startDateInput.value = '';
            endDateInput.value = '';
            return false;
        }

        // Assign multiple-day inputs to the same variables as single-day
        startTime = startDate; // Use the full datetime from the `startDateInput`
        endTime = endDate;   
          // Use the full datetime from the `endDateInput`
    }

    console.log('Start Time:', startTime.toISOString());
    console.log('End Time:', endTime.toISOString());

    return { startTime, endTime }; // Return values for further use
};
function updateSingleDayInputs() {
    if (startDateInput.value) {
        startTimeInput.value = startDateInput.value; // Set start date to start time
    }
    if (endDateInput.value) {
        endTimeInput.value = endDateInput.value; // Set end date to end time
    }
}
startDateInput.addEventListener('change', updateSingleDayInputs);
endDateInput.addEventListener('change', updateSingleDayInputs);Z
// Add event listeners for validation
startTimeInput.addEventListener('change', validateTime);
endTimeInput.addEventListener('change', validateTime);
startDateInput.addEventListener('change', validateTime);
endDateInput.addEventListener('change', validateTime);
multipleStartTimeInput.addEventListener('change', validateTime);
multipleEndTimeInput.addEventListener('change', validateTime);

    // Submit Form Logic
 
    // Autocomplete Logic for Employee Search
    
    </script>
</body>
<?php include('footer.php'); ?>


