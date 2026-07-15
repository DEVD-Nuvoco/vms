<?php include('header.php'); ?>



    <title>Upcoming Meetings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <style>
  

        .container2 {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .meeting-card {
            margin: 15px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            background-color: #fff;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .meeting-card:hover {
            background-color: #f4f4f4;
        }

        .meeting-card h5 {
            margin-bottom: 10px;
            font-weight: bold;
            color: #333;
        }

        .meeting-card p {
            margin: 5px 0;
            color: #555;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
    </style>


<body>
<div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">
    <div class="container">
    <div class="container"><div class="az-content-left az-content-left-components">
            <div class="component-item">
                <label>Previous Meetings</label>
                <nav class="nav flex-column">
                    <a href="watchman2.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'watchman2.php' ? 'active' : ''; ?>">Issue Gear</a>
                    <a href="upcomingmeeting.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'upcomingmeeting.php' ? 'active' : ''; ?>">Upcoming Meeting</a>
                    <a href="newMeeting2.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'newMeeting2.php' ? 'active' : ''; ?>">New Meeting</a>
                </nav>
            </div>
        </div>
    <div class="az-content-body pd-lg-l-40 d-flex flex-column">
            <h2>Upcoming Meetings</h2>
            <div id="responseMessage" class="alert" style="display: none;"></div>
            <div class="container2" id="meetingsContainer">
                <div class="loading" id="loadingIndicator">
                    <span>Loading meetings...</span>
                </div>
            </div>
        </div>
 
    </div>
</div>

      

    <script>
        $(document).ready(function () {
            const userId = '<?php echo $_SESSION['userDetails']['id']; ?>';

            function fetchUpcomingMeetings() {
                $.ajax({
                    url: `https://vms.nuvoco.in/vmsdb/fetch_upcoming_meetings.php?visitorId=${userId}`,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            const meetings = response.data;
                            renderMeetings(meetings);
                        } else {
                            $('#meetingsContainer').html('<p>No upcoming meetings found.</p>');
                        }
                    },
                    error: function () {
                        $('#meetingsContainer').html('<p>Error fetching meetings. Please try again later.</p>');
                    },
                    complete: function () {
                        $('#loadingIndicator').hide();
                    }
                });
            }


            function formatDateTime(dateTime) {
                const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
                return new Date(dateTime).toLocaleDateString('en-US', options);
            }

            function renderMeetings(meetings) {
    let html = '';

    meetings.forEach((meeting, index) => {
        html += `
            <div class="meeting-card" onclick="generateGatePass(${index})">
                <h5>Meeting with: ${meeting.meeting_person}</h5>
                <p>Visit Type: ${meeting.visit_type}</p>
                <p>Visit Purpose: ${meeting.visit_purpose}</p>
                <p>Start Time: ${formatDateTime(meeting.meeting_start_time)}</p>
                <p>End Time: ${formatDateTime(meeting.meeting_end_time)}</p>
            </div>
        `;
    });

    $('#meetingsContainer').html(html);

    // Save meetings in a global variable for later access
    window.meetingsList = meetings;
}

window.generateGatePass = function (meetingIndex) {
    const meeting = window.meetingsList[meetingIndex]; // Retrieve the meeting data from the global variable
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generate_gate_pass.php';

    const meetingInput = document.createElement('input');
    meetingInput.type = 'hidden';
    meetingInput.name = 'meetingData';
    meetingInput.value = JSON.stringify(meeting);
    form.appendChild(meetingInput);

    document.body.appendChild(form);
    form.submit();
};

            fetchUpcomingMeetings();
        });
    </script>
</body>

</html>
