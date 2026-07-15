<?php 
include("header.php");
require_once 'phpqrcode/qrlib.php'; // Ensure you have the QR code library

if ($_SESSION['loginType'] == 'S') {
    header('location:watchman.php');
    exit();
}
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
            </ul>

            <div class="tab-content" id="meetingTabsContent">
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
                            <thead>
                                <tr>
                                    <th class="text-center">&nbsp;</th>
                                    <th class="text-center">Meeting With</th>
                                    <th class="text-center">Visit Type</th>
                                    <th class="text-center">Visit Purpose</th>
                                    <th class="text-center">Start Time</th>
                                    <th class="text-center">End Time</th>
                                    <th class="text-center">Action</th>
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
                                    <th class="text-center">&nbsp;</th>
                                    <th class="text-center">Meeting With</th>
                                    <th class="text-center">Visit Type</th>
                                    <th class="text-center">Visit Purpose</th>
                                    <th class="text-center">Start Time</th>
                                    <th class="text-center">End Time</th>
                                    <th class="text-center">Action</th>
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
    const userId = '<?php echo $_SESSION['userDetails']['id']; ?>';
    let meetingsData = { upcoming: [], previous: [] }; // Stores all fetched meetings
    let fetchedTabs = { upcoming: false, previous: false }; // Tracks if data has been fetched per tab

    function fetchMeetings(tab) {
        const url = tab === 'upcoming'
            ? `https://vms.nuvoco.in/vmsdb/fetch_upcoming_meetings.php?visitorId=${userId}`
            : `https://vms.nuvoco.in/vmsdb/fetch_previous_meetings.php?visitorId=${userId}`;

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
                        <td ><img src="https://vms.nuvoco.in/vmsdb/faces/${meeting.visitor_id}_profile.webp" width="80" alt=""></td>
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
    }
    $(document).on('click', '.btn-success', function () {
    const tab = $('#meetingTabs .nav-link.active').attr('id').includes('upcoming') ? 'upcoming' : 'previous';
    const index = $(this).closest('tr').index();
    showGatePass(tab, index);
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
