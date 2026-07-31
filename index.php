<?php 
include("header.php");
require_once 'phpqrcode/qrlib.php'; // Ensure you have the QR code library
// right after your <?php
date_default_timezone_set('Asia/Kolkata');  // or whatever your DB is using

if ($_SESSION['loginType'] == 'S') {
    header('location:watchman.php');
    exit();
}
$logType = $_SESSION['loginType'];
// echo $logType; 
?>
<style>
.modal {
    z-index: 99999; /* Default Bootstrap z-index for modals */
}
.nav-tabs .nav-item {
    margin-bottom: -1px;
}
.nav-tabs .nav-link {
    border: 1px solid transparent;
    border-radius: 0.25rem;
}
.nav-tabs .nav-link.active {
    background-color: #f8f9fa;
    border-color: #dee2e6 #dee2e6 #f8f9fa;
}
.filter-container {
    margin-top: 10px;
    margin-bottom: 5px;
}
.filter-options {
    width: 200px;
}
tr {
    background-color:#80c18d;
}
.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    table-layout: fixed;
}
.table th, .table td {
    text-align: center;
    padding: 8px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.table thead th {
    /* background-color: #f4f4f4; */
    font-weight: bold;
}
.table tbody tr:hover {
    /* background-color: #e9ecef; */
}

/* Responsive table styles */
@media (max-width: 768px) {
    .table thead {
        display: none;
    }
    .table, .table tbody, .table tr, .table td {
        display: block;
        width: 100%;
    }
    .table tr {
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
    }
    .table td {
        text-align: left;
        padding: 10px;
        position: relative;
    }
    .table td::before {
        content: attr(data-label);
        position: absolute;
        left: 10px;
        top: 10px;
        font-weight: bold;
        color: #555;
    }
}

/* Marquee styles */
.marquee-container {
    background-color: #000;
    color: #fff;
    overflow: hidden;
    white-space: nowrap;
    position: relative;
    opacity: 0.8;
    padding: 10px;
}
.marquee-content {
    display: inline-flex;
    animation: marquee 10s linear infinite;
}
.visitor-card {
    display: inline-flex;
    align-items: center;
    margin-right: 30px;
}
.visitor-card img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    margin-right: 10px;
    object-fit: cover;
}
.visitor-name {
    font-size: 1.2rem;
    color: #fff;
}
@keyframes marquee {
    0% {
        transform: translateX(250%);
    }
    100% {
        transform: translateX(-100%);
    }
}
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<div class="az-content az-content-dashboard">
    <div class="container">
        <div class="az-content-body">
            <div class="az-dashboard-one-title">
                <div>
                    <h2 class="az-dashboard-title" style="margin-left:10px;">Hi <?php echo $userName ?>, <span class="text-danger">welcome</span></h2>
                </div>
                <div class="az-content-header-right">
                    <div class="media">
                        <div class="media-body">
                            <a class="nav-link" href="newMeeting2.php"><i class="fa fa-user-plus"></i> New Meeting</a>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs" id="meetingTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="upcoming-tab" data-toggle="tab" href="#upcoming" role="tab" aria-controls="upcoming" aria-selected="true">Upcoming Meetings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="previous-tab" data-toggle="tab" href="#previous" role="tab" aria-controls="previous" aria-selected="false">Previous Meetings</a>
                </li>
                <?php if ($_SESSION['loginType'] == 'E'): ?>
    <li class="nav-item">
        <a class="nav-link" id="approvals-tab" data-toggle="tab" href="#approvals" role="tab" aria-controls="approvals" aria-selected="false">Approvals</a>
    </li>
<?php endif; ?>
<li class="nav-item">
                    <a class="nav-link" id="ongoing-tab" data-toggle="tab" href="#ongoing" role="tab" aria-controls="ongoing" aria-selected="false">Ongoing Meetings</a>
                </li>
            </ul>

            <div class="tab-content" id="meetingTabsContent">
            <div class="tab-pane fade" id="ongoing" role="tabpanel" aria-labelledby="ongoing-tab">
                    <div class="card card-table-one">
                        <h6 class="card-title">Ongoing <span class="text-success">Meetings</span></h6>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="Color:white;">Visitor</th>
                                        <th class="text-center" style="Color:white;">Meeting with</th>
                                        <th class="text-center" style="Color:white;">Start Time / End Time</th>
                                        <th class="text-center" style="Color:white;">Is this meeting ongoing?</th>
                                        <th class="text-center" style="Color:white;">Forward this meeting to</th>
                                    </tr>
                                </thead>
                                <tbody id="ongoing-meetings-container">
                               
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Upcoming Meetings Tab -->
                <div class="tab-pane fade show active" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                    <div class="filter-container">
                        <select class="filter-options form-control">
                            <option value="">All</option>
                            <option value="today">Today</option>
                            <option value="thisWeek">This Week</option>
                            <option value="thisMonth">This Month</option>
                            <option value="previousWeek">Previous Week</option>
                            <option value="previousMonth">Previous Month</option>
                        </select>
                    </div>
                    <div class="card card-table-one">
                    <h6 class="card-title">Your Upcoming <span class="text-success">Visits</span></h6>
                    <div class="table-responsive">
                        <table class="table">
                            <thead style="padding-top:10px;">
                                <tr style="padding-top:10px;">
                                    <th class="text-bottom">&nbsp;</th>
                                    <th class="text-center" style="Color:white;">Meeting With</th>
                                    <th class="text-center" style="Color:white;">Visit Type</th>
                                    <th class="text-center" style="Color:white;">Visit Purpose</th>
                                    <th class="text-center" style="Color:white;">Start Time</th>
                                    <th class="text-center" style="Color:white;">End Time</th>
                                    <th class="text-center" style="Color:white;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="upcoming-meetings-container">
                                <tr>
                                    <td colspan="7" class="text-center">Loading meetings...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
</div>
                <!-- Previous Meetings Tab -->
                <div class="tab-pane fade" id="previous" role="tabpanel" aria-labelledby="previous-tab">
                    <div class="filter-container">
                        <select class="filter-options form-control">
                            <option value="">All</option>
                            <option value="today">Today</option>
                            <option value="thisWeek">This Week</option>
                            <option value="thisMonth">This Month</option>
                            <option value="previousWeek">Previous Week</option>
                            <option value="previousMonth">Previous Month</option>
                        </select>
                    </div>
                    <div class="card card-table-one">
                    <h6 class="card-title">Your Previous <span class="text-success">Visits</span></h6>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="Color:white;">&nbsp;</th>
                                    <th class="text-center" style="Color:white;">Meeting With</th>
                                    <th class="text-center" style="Color:white;">Visit Type</th>
                                    <th class="text-center" style="Color:white;">Visit Purpose</th>
                                    <th class="text-center" style="Color:white;">Start Time</th>
                                    <th class="text-center" style="Color:white;">End Time</th>
                                    <th class="text-center" style="Color:white;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="previous-meetings-container">
                                <tr>
                                    <td colspan="7" class="text-center">Loading meetings...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($_SESSION['loginType'] == 'E'): ?>
    <div class="tab-pane fade" id="approvals" role="tabpanel" aria-labelledby="approvals-tab">
        <div class="card card-table-one">
            <h6 class="card-title">Pending <span class="text-success">Approvals</span></h6>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="text-center" style="Color:white;">&nbsp;</th>
                            <th class="text-center" style="Color:white;">Visitor Name</th>
                            <th class="text-center" style="Color:white;">Visit Purpose</th>
                            <th class="text-center" style="Color:white;">Start Time</th>
                            <th class="text-center" style="Color:white;">End Time</th>
                            <th class="text-center" style="Color:white;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="approvals-meetings-container">
                        <tr>
                            <td colspan="6" class="text-center">Loading approvals...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

            </div>
            <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Gate Pass Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="qr-and-details">
                    <div class="qr-code text-center mb-4">
                    <img id="qrCode" src="" alt="QR Code" class="qr" width="200" />
                    </div>

                    <h6><strong>Visitor Details</strong></h6>
                    <p><strong>Visitor Pass:</strong> <span id="visitorPass"></span></p>
                    <p><strong>Name:</strong> <span id="visitorName"></span></p>
                    <p><strong>Email:</strong> <span id="visitorEmail"></span></p>
                    <h6><strong>Visit Details</strong></h6>
                    <p><strong>Visit Purpose:</strong> <span id="visitPurpose"></span></p>
                    <p><strong>Visit Type:</strong> <span id="visitType"></span></p>
                    <h6><strong>Time Details</strong></h6>
                    <p><strong>Start Time:</strong> <span id="meetingStartTime"></span></p>
                    <p><strong>End Time:</strong> <span id="meetingEndTime"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Print</button>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
</div>

<script>

$(document).ready(function () {
    const userId = '<?php echo $userId; ?>';
    let meetingsData = { upcoming: [], previous: [] }; // Stores all fetched meetings
    let fetchedTabs = { upcoming: false, previous: false }; // Tracks if data has been fetched per tab
 const logintype = '<?php echo $logType ?>';
 console.log(logintype);
    function fetchMeetings(tab) {
        const url = tab === 'upcoming'
            ? `https://vms.nuvoco.in/vmsdb/fetch_upcoming_meetings.php?visitorId=${userId}&logintype=${logintype}`
            : `https://vms.nuvoco.in/vmsdb/fetch_previous_meetings.php?visitorId=${userId}&logintype=${logintype}`;

        $(`#${tab}-meetings-container`).html('<tr><td colspan="7" class="text-center">Loading meetings...</td></tr>');

        $.ajax({
            url,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    meetingsData[tab] = response.data;
                    renderMeetings(tab, meetingsData[tab]);
                } else {
                    $(`#${tab}-meetings-container`).html('<tr><td colspan="7" class="text-center">No meetings found.</td></tr>');
                }
            },
            error: function () {
                $(`#${tab}-meetings-container`).html('<tr><td colspan="7" class="text-center">Error fetching meetings.</td></tr>');
            }
        });
    }

    function renderMeetings(tab, meetings) {
        let html = '';

        if (meetings.length === 0) {
            html = '<tr><td colspan="7" class="text-center">No meetings found.</td></tr>';
        } else {
            meetings.forEach(meeting => {
                html += `
                    <tr>
                        <td ><img src="https://vms.nuvoco.in/vmsdb/serve_image.php?image=${meeting.visitor_id}_profile.webp" width="80" alt=""></td>
                        <td class="text-center">${meeting.meeting_person}</td>
                        <td class="text-center">${meeting.visit_type}</td>
                        <td class="text-center">${meeting.visit_purpose}</td>
                        <td class="text-center">${formatDateTime(meeting.meeting_start_time)}</td>
                        <td class="text-center">${formatDateTime(meeting.meeting_end_time)}</td>
                        <td class="text-center"><button class="btn btn-success">Details</button></td>
                    </tr>`;
            });
        }

        $(`#${tab}-meetings-container`).html(html);
    }function fetchOngoingMeetings() {
    $('#ongoing-meetings-container').html('<tr><td colspan="6" class="text-center">Loading meetings...</td></tr>');

    const url = `https://vms.nuvoco.in/vmsdb/fetch_ongoing_meetings.php?userId=${userId}&logType=${logintype}`;
    $.ajax({
        url,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                const loginType = response.loginType; // Capture loginType from response
                meetingsData.ongoing = response.data;
                renderOngoingMeetings(meetingsData.ongoing, loginType);
            } else {
                $('#ongoing-meetings-container').html('<tr><td colspan="6" class="text-center">No ongoing meetings found.</td></tr>');
            }
        },
        error: function () {
            $('#ongoing-meetings-container').html('<tr><td colspan="6" class="text-center">Error fetching ongoing meetings.</td></tr>');
        }
    });
}

function renderOngoingMeetings(meetings, loginType) {
    if (!meetings || !meetings.length) {
        $('#ongoing-meetings-container').html('<tr><td colspan="6" class="text-center">No ongoing meetings found.</td></tr>');
        return;
    }

    let html = '';
    meetings.forEach(meeting => {
        html += `
            <tr data-meeting-id="${meeting.meeting_id}">
                <td class="text-center">
                    ${meeting.visting_person}
                    <img src="https://vms.nuvoco.in/vmsdb/serve_image.php?image=${meeting.visitor_id}_profile.webp" width="80" alt="">
                </td>
                <td class="text-center">${meeting.meeting_person}</td>
                <td class="text-center">
                    ${formatDateTime(meeting.meeting_start_time)}<br>
                    ${formatDateTime(meeting.meeting_end_time)}
                </td>
        `;

        // Conditionally render actions based on loginType
        if (loginType === 'E') {
            html += `
                <td class="text-center">
                    <button class="btn btn-success">YES</button>
                    <button class="btn btn-danger" onclick="sendEndMeetingNotification('visitor@example.com','security@example.com')">NO</button>
                </td>
                <td class="text-center">
                    <select class="form-control forward_meeting">
                        <option value="">Select Member</option>
                        <!-- Options will be added here via AJAX -->
                    </select>
                    <button class="btn btn-primary mt-2 forward_btn">Forward</button>
                </td>
            `;
        } else if (loginType === 'V') {
            html += `
                <td colspan="2" class="text-center">Action not allowed for visitors.</td>
            `;
        }

        html += `</tr>`;
    });

    $('#ongoing-meetings-container').html(html);

    // Populate all dropdowns after rendering the meetings
    populateForwardDropdowns();
}

    $(document).on('click', '.btn-success', function () {
    const tab = $('#meetingTabs .nav-link.active').attr('id').includes('upcoming') ? 'upcoming' : 'previous';
    const index = $(this).closest('tr').index();
    showGatePass(tab, index);
});


// ajx call 
function populateForwardDropdowns() {
    $.ajax({
        url: 'https://vms.nuvoco.in/vmsdb/search_employee.php',
        type: 'GET',
        data: { searchIndex: '' },
        dataType: 'json',
        success: function (data) {
            $('.forward_meeting').each(function () {
                const dropdown = $(this);
                data.forEach(function (user) {
                    dropdown.append(
                        `<option value="${user.empCode}">${user.empName} (${user.empDepartment})</option>`
                    );
                });
            });
        },
        error: function (xhr, status, error) {
            console.error('Error fetching users:', error);
        }
    });
}



$(document).on('click', '.forward_btn', function(){
    // Get the row where the clicked button resides
    const row = $(this).closest('tr');
    const meetingId = row.data('meeting-id');
    const forwardUser = row.find('.forward_meeting').val();

    if(forwardUser === ''){
        alert("Please select a member to forward the meeting to.");
        return;
    }
  
    $.ajax({
        url: 'https://vms.nuvoco.in/vmsdb/update_ongoing_meeting.php',
        type: 'POST',
        data: { meeting_id: meetingId, forwardUser: forwardUser },
        dataType: 'json',
        success: function(response) {
            if(response.status === 'success'){
                alert("Meeting forwarded successfully!");
                // Optionally update the UI to reflect the successful forward.
            } else {
                alert("Error: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred while forwarding the meeting: " + error);
        }
    });
});



function showGatePass(tab, index) {
    const meeting = meetingsData[tab][index];
    const qrData = `
        Meeting ID: ${meeting.meeting_id}
        Gate In: ${meeting.gate_in || 'Not Recorded'}
        Visitor: ${meeting.visitor_name}
        Email: ${meeting.visitor_email}
        Phone: ${meeting.userMobile}
        Visit Purpose: ${meeting.visit_purpose}
        Start Time: ${meeting.meeting_start_time}
        Expected End Time: ${meeting.meeting_end_time}
        To Meet: ${meeting.meeting_person || 'N/A'}
        Department: ${meeting.meeting_location || 'N/A'}
    `;

    // Send POST request to the backend
    $.ajax({
        url: 'generate_qr.php',
        type: 'POST',
        data: { qrData: qrData },
        success: function (response) {
            if (response) {
                // Display the QR code in the modal
                $('#qrCode').attr('src', response);
            } else {
                console.error('QR Code generation failed.');
                $('#qrCode').attr('src', '');
            }
        },
        error: function (xhr, status, error) {
            console.error('Error generating QR code:', xhr.responseText);
            alert('Failed to generate QR code. Check the console for details.');
        }
    });

    // Populate other modal fields with meeting details
    $('#visitorPass').text(meeting.meeting_id);
    $('#visitorName').text(meeting.visitor_name);
    $('#visitorEmail').text(meeting.visitor_email);
    $('#visitPurpose').text(meeting.visit_purpose);
    $('#visitType').text(meeting.visit_type);
    $('#meetingStartTime').text(formatDateTime(meeting.meeting_start_time));
    $('#meetingEndTime').text(formatDateTime(meeting.meeting_end_time));

    // Show the modal
    $('#exampleModal').modal('show');
}
    function filterMeetings(meetings, filterType) {
        const today = new Date();
        let filteredMeetings = [];

        switch (filterType) {
            case 'today':
                filteredMeetings = meetings.filter(m => {
                    const date = new Date(m.meeting_start_time);
                    return date.toDateString() === today.toDateString();
                });
                break;
            case 'thisWeek':
                const startOfWeek = new Date(today.setDate(today.getDate() - today.getDay()));
                const endOfWeek = new Date(today.setDate(startOfWeek.getDate() + 6));
                filteredMeetings = meetings.filter(m => {
                    const date = new Date(m.meeting_start_time);
                    return date >= startOfWeek && date <= endOfWeek;
                });
                break;
            case 'thisMonth':
                const month = today.getMonth();
                const year = today.getFullYear();
                filteredMeetings = meetings.filter(m => {
                    const date = new Date(m.meeting_start_time);
                    return date.getMonth() === month && date.getFullYear() === year;
                });
                break;
            case 'previousWeek':
                const lastWeekStart = new Date(today.setDate(today.getDate() - today.getDay() - 7));
                const lastWeekEnd = new Date(today.setDate(lastWeekStart.getDate() + 6));
                filteredMeetings = meetings.filter(m => {
                    const date = new Date(m.meeting_start_time);
                    return date >= lastWeekStart && date <= lastWeekEnd;
                });
                break;
            case 'previousMonth':
                const previousMonth = today.getMonth() === 0 ? 11 : today.getMonth() - 1;
                const previousYear = today.getMonth() === 0 ? today.getFullYear() - 1 : today.getFullYear();
                filteredMeetings = meetings.filter(m => {
                    const date = new Date(m.meeting_start_time);
                    return date.getMonth() === previousMonth && date.getFullYear() === previousYear;
                });
                break;
            default:
                filteredMeetings = meetings;
        }

        return filteredMeetings;
    }

    $('.filter-options').on('change', function () {
        const filterType = $(this).val();
        const activeTab = $('#meetingTabs .nav-link.active').attr('id').includes('upcoming') ? 'upcoming' : 'previous';
        const filteredMeetings = filterMeetings(meetingsData[activeTab], filterType);
        renderMeetings(activeTab, filteredMeetings);
    });

    $('#meetingTabs a').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
        const tab = $(this).attr('id').includes('upcoming') ? 'upcoming' : 'previous';

        if (!fetchedTabs[tab]) {
            fetchedTabs[tab] = true;
            fetchMeetings(tab);
        } else {
            renderMeetings(tab, meetingsData[tab]);
        }
    });

    fetchMeetings('upcoming');

 
// Function to fetch approvals
function fetchApprovals() {
    const url = `https://vms.nuvoco.in/vmsdb/fetch_meeting_approvals.php?meetpersonId=<?php echo $_SESSION['userDetails']['empCode']; ?>`;
    
    $('#approvals-meetings-container').html('<tr><td colspan="6" class="text-center">Loading approvals...</td></tr>');

    $.ajax({
        url,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                renderApprovals(response.data);
            } else {
                $('#approvals-meetings-container').html('<tr><td colspan="6" class="text-center">No approvals found.</td></tr>');
            }
        },
        error: function () {
            $('#approvals-meetings-container').html('<tr><td colspan="6" class="text-center">Error fetching approvals.</td></tr>');
        }
    });
}

// Function to render approvals in the table
function renderApprovals(meetings) {
    let html = '';
    if (meetings.length === 0) {
        html = '<tr><td colspan="6" class="text-center">No approvals pending.</td></tr>';
    } else {
        meetings.forEach(meeting => {
            html += `
                <tr>
                    <td class="text-center">
                        <img src="https://vms.nuvoco.in/vmsdb/serve_image.php?image=${meeting.visitor_id}_profile.webp" width="80" alt="">
                    </td>
                    <td class="text-center">${meeting.visitor_name}</td>
                    <td class="text-center">${meeting.visit_purpose}</td>
                    <td class="text-center">${formatDateTime(meeting.meeting_start_time)}</td>
                    <td class="text-center">${formatDateTime(meeting.meeting_end_time)}</td>
                    <td class="text-center">
                        <button class="btn  approve-btn appr" style='background-color:lightgreen;' data-id="${meeting.meeting_id}">Approve</button>
                        <button class="btn btn-danger disapprove-btn" data-id="${meeting.meeting_id}">Disapprove</button>
                    </td>
                </tr>
            `;
        });
    }
    $('#approvals-meetings-container').html(html);
}

// Handle Approve and Disapprove Button Clicks
$(document).on('click', '.appr, .disapprove-btn', function () {
    const meetingId = $(this).data('id');
    const status = $(this).hasClass('appr') ? 'Approved' : 'Disapproved';
    updateMeetingStatus(meetingId, status);
});

// Function to update meeting status
function updateMeetingStatus(meetingId, status) {
    $.ajax({
        url: 'https://vms.nuvoco.in/vmsdb/update_meeting_status.php',
        type: 'POST',
        data: { meetingId, status },
        success: function (response) {
            const res = JSON.parse(response);
            if (res.status === 'success') {
                alert(`Meeting ${status.toLowerCase()} successfully.`);
                fetchApprovals(); // Refresh the table
            } else {
                alert('Failed to update status.');
            }
        },
        error: function () {
            alert('Error updating meeting status.');
        }
    });
}
$('#ongoing-tab').on('click', function () {
    fetchOngoingMeetings();
});
// Trigger fetch on tab click
$('#approvals-tab').on('click', function () {
    fetchApprovals();
});
function formatDateTime(dateTime) {
        const date = new Date(dateTime);
        const formattedDate = date.toLocaleDateString('en-GB');
        const formattedTime = date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        return `${formattedDate} ${formattedTime}`;
    }
});
</script>
<?php include("footer.php"); ?>
