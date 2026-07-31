<?php include("header.php");
// Start session to access session variables

// Store the session messages in variables and unset the session variables to clear them after displaying
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);
// Assuming a DB connection file
?>
<style>
  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
  }

  .az-content-title {
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 1rem;
    color: #42bb52; /* Match green from the theme */
  }

  .form-group label {
    font-weight: bold;
    font-size: 1rem;
  }

  .form-control {

    border-radius: 5px;
    padding: 10px;
    font-size: 1rem;
    margin-bottom: 1rem;
  }

  .btn {
    background-color: #42bb52; /* Theme green */
    color: #fff;
    border: none;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
  }

  .btn:hover {
    background-color: #38a644;
  }

  .alert {
    border-radius: 5px;
    padding: 10px;
    font-size: 0.9rem;
  }

  .alert-success {
    background-color: #d4edda;
    color: #155724;
  }

  .alert-danger {
    background-color: #f8d7da;
    color: #721c24;
  }

  .table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
  }

  .table th, .table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
  }

  .table th {
    background-color: #f1f1f1;
    font-weight: bold;
  }

  .modal-content {
    border-radius: 10px;
    z-index: inherit;
  }

  .modal-header, .modal-footer {
    background-color: #42bb52;
    color: #fff;
  }

  .modal-title {
    font-size: 1.5rem;
    font-weight: bold;
  }

  .modal-body {
    padding: 20px;
  }

  #searchMeetingId {
    border: 2px solid #42bb52;
    outline: none;
  }
  .form-group label i {
  color: #42bb52; /* Match the theme color */
  margin-right: 5px;
}

.form-group select {
  min-width: 150px;

}
.small-button {
    font-size: 0.8rem; /* Make the text smaller */
    padding: 5px 10px; /* Adjust padding for smaller size */
    width: auto; /* Ensure it adapts to the text content */
    display: inline-block; /* Keep it inline */
  }

  /* Optional: Align button to the right */
  .text-right {
    text-align: right;
  }
  .marquee-container {
    background-color: lightgray;
    color: #fff;
    overflow: hidden;
    position: fixed;
    left: 0; /* Position the marquee on the left side */
    top: 0;
    height: 100%; /* Full height of the screen */
    width: 200px; /* Adjust width as needed */
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: width 0.3s ease;
    z-index: 9999999; /* Smooth collapse/expand animation */
}

.marquee-container.collapsed {
    width: 50px; /* Shrink width when collapsed */
}

.marquee-content {
    display: flex;
    flex-direction: column; /* Stack elements vertically */
    animation: marquee-vertical 10s linear infinite;
}

.visitor-card {
    display: flex;
    flex-direction: column; /* Align image and name vertically */
    align-items: center;
    margin-bottom: 20px; /* Space between each card */
    opacity: 1; /* Fully visible */
    transition: opacity 0.3s ease; /* Smooth fade effect */
}

.marquee-container.collapsed .visitor-card {
    opacity: 0; /* Hide content when collapsed */
}

.visitor-card img {
    width: 150px; /* Square size */
    height: 150px; /* Square size */
    object-fit: cover; /* Ensure proper cropping */
    border: 1px solid red; /* Optional: Add a border for better visibility */
    margin-bottom: 5px; /* Space between image and name */
}

.visitor-name {
    font-size: 0.9rem; /* Smaller font for better alignment */
    color: red;
    font-weight: bold;
    text-align: center;
}

.toggle-button {
    position: absolute;
    top: 90px;
    right: 1px; /* Position outside the marquee */
    width: 20px;
    height: 100px;
    background-color: gray;
    color: white;
    border: none;
    cursor: pointer;
    writing-mode: vertical-rl; /* Rotate text vertically */
    transform: rotate(180deg); /* Flip text to be readable */
 
}

@keyframes marquee-vertical {
    0% {
        transform: translateY(200%); /* Start from below the container */
    }
    100% {
        transform: translateY(-200%); /* Move to above the container */
    }
}
</style>

<script src="cities.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css"
  />
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script src="ajaxable.min.js"></script>
<?php
// Fetch blacklist data
$query = "SELECT b.user_id, b.name, b.photo , u.userName
          FROM tbl_blacklist_person b 
          LEFT JOIN tbl_user u ON b.user_id = u.id";
$result = $mysqli->query($query);
?>

<div class="marquee-container" id="marquee">
    <button class="toggle-button" id="toggleButton">Collapse</button>
    <div class="marquee-content">
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="visitor-card">
                <?php if ($row['user_id']): ?>
                    <!-- Fetch photo from the faces directory -->
                    <img src="https://vms.nuvoco.in/vmsdb/serve_image.php?image=<?= $row['user_id']; ?>_profile.webp" alt="Photo">
                    <span class="visitor-name"><?= htmlspecialchars($row['userName']); ?></span>
                <?php else: ?>
                    <!-- Custom visitor with uploaded photo -->
                    <img src="<?= $row['photo'] ?: 'img/faces/default.png'; ?>" alt="Photo">
                    <span class="visitor-name"><?= htmlspecialchars($row['name']); ?></span>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">

  <div class="container">
    <div class="az-content-left az-content-left-components">
      <div class="component-item">
        <label>Meetings</label>
        <nav class="nav flex-column">
          <a href="watchman2.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'watchman2.php' ? 'active' : ''; ?>">Issue Gear</a>
          <a href="watchman.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'watchman.php' ? 'active' : ''; ?>">Gate-In & Gear Tracking</a>
          <a href="newMeeting.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'newMeeting.php' ? 'active' : ''; ?>">New Meeting</a>
          <a href="ongoingMeeting.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ongoingMeeting.php' ? 'active' : ''; ?>">Ongoing Meeting</a>
          <a href="blacklist.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'blacklist.php' ? 'active' : ''; ?>">Blacklist </a>

        </nav>
      </div>
    </div>
    <div class="az-content-body pd-lg-l-40 d-flex flex-column">
      
      <h2 class="az-content-title">Gear Assignment and Gate-In Tracking</h2>

      <div class="form-group">
        <label for="searchMeetingId">Search by Meeting ID</label>
        <input type="text" id="searchMeetingId" class="form-control" placeholder="Enter Meeting ID">
      </div>

    

      <div class="form-group d-flex justify-content-end align-items-center">
<button id="exportToExcel" class=" btn-success" style="margin-right: 10px;">Export to Excel</button>

      <button id="cancelMeetingsTodayBtn" class="btn-danger small-button" style="margin-right: 10px;">
  Cancel All Meetings for Today
</button>
  <label for="filterMeetingTime" class="mr-2"><i class="fas fa-filter"></i> Filter</label>
  <select id="filterMeetingTime" class="form-control w-auto" style="">
  <option value="all">All</option>
  <option value="today">Today</option>
  <option value="this_week">This Week</option>
  <option value="last_week">Last Week</option>
  <option value="last_month">Last Month</option>
</select>
</div>

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
   

      <!-- Table showing meetings -->
      <div class="table-responsive">
        <table id="meetingsTable" class="table table-bordered">
          <thead>
            <tr style="background-color:green;">
              <th style="color:white;">M.ID</th>
              <th style="color:white;">Visitor Name</th>
              <th style="color:white;">Visitor Details</th>
              <th style="color:white;">Meeting Details</th>
              <th style="color:white;">Actions</th>
            </tr>
          </thead>
         <tbody>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$location = $_SESSION['userDetails']['userAddress'] ?? '';

// Extract last word from admin location
$locParts = preg_split('/[-_]/', $location);
$workLocation = strtoupper(trim(end($locParts))); 
$workLocation = $mysqli->real_escape_string($workLocation);

$result = $mysqli->query("
  SELECT
    m.meeting_id,
    m.visitor_id,
    u.*,
    ue.empName,
    ue.empCode,
    ue.empBusiEmail AS empBusiEmailv,
    ue.empBusiMobile AS empBusiMobilev,
    ue.empDesignation AS empDesignation,
    ue.empWorkLocation,
    ne.Department,
    ne.empBusiMobile,
    ne.empBusiEmail,
    m.meeting_person,
    m.visit_type,
    m.meetingAprroved,
    m.meeting_start_time,
    m.meeting_end_time,
    m.safety_induction_done,
    m.gate_in,
    m.gate_out,
    gi.gear_names,
    gi.gear_quantities
  FROM meetings m
  LEFT JOIN (
    SELECT
      meeting_id,
      GROUP_CONCAT(gear_name ORDER BY gear_name) AS gear_names,
      GROUP_CONCAT(gear_quantity ORDER BY gear_name) AS gear_quantities
    FROM gear_issued
    GROUP BY meeting_id
  ) gi ON gi.meeting_id = m.meeting_id
  LEFT JOIN tbl_user u
    ON u.id = m.visitor_id
   AND CAST(m.visitor_id AS CHAR) NOT REGEXP '^3000[0-9]{4}$'
  LEFT JOIN tbl_nuvo_employee ue
    ON ue.empCode = m.visitor_id
   AND CAST(m.visitor_id AS CHAR) REGEXP '^3000[0-9]{4}$'
  LEFT JOIN tbl_nuvo_employee ne
    ON ne.empCode = m.meetperson_id
  WHERE
    UPPER(
      TRIM(
        SUBSTRING_INDEX(
          REPLACE(REPLACE(m.meeting_location, '-', '_'), '__', '_'), 
          '_', 
          -1
        )
      )
    ) = '$workLocation'
  ORDER BY m.meeting_id DESC
");

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    echo '<tr style="color:black;">';

    // 1) M.ID
    echo '<td>' . htmlspecialchars($row['meeting_id']) . '</td>';

    // 2) Visitor Name + photo
    echo '<td>' .
         htmlspecialchars($row['userName'] ?? $row['empName'] ?? '') .
         '<br><img width="100" src="https://vms.nuvoco.in/vmsdb/serve_image.php?image=' .
         htmlspecialchars($row['visitor_id']) .
         '_profile.webp" alt="Visitor Image"></td>';

    // 3) Visitor Details (FIX: mobile fallback to empBusiMobilev)
    echo '<td>' .
         '<strong>Email : </strong>' . htmlspecialchars($row['userEmail'] ?? $row['empBusiEmailv'] ?? '') . '<br>' .
         '<strong>Mobile : </strong>' . htmlspecialchars($row['userMobile'] ?? $row['empBusiMobilev'] ?? '') . '<br>' .
         '<strong>Company : </strong>' . htmlspecialchars($row['userCompany'] ?? 'Nuvoco Vistas') . '<br>' .
         '<strong>Designation : </strong>' . htmlspecialchars($row['userDesignation'] ?? $row['empDesignation'] ?? '') . '<br>' .
         '<strong>Location : </strong>' . htmlspecialchars($row['userAddress'] ?? $row['empWorkLocation'] ?? '') . '<br>' .
         '<strong>Visit Type : </strong>' . htmlspecialchars($row['visit_type'] ?? '') .
         '</td>';

    // 4) Meeting Details
    echo '<td>' .
         '<strong>Name : </strong>' . htmlspecialchars($row['meeting_person'] ?? '') . '<br>' .
         '<strong>Mobile : </strong>' . htmlspecialchars($row['empBusiMobile'] ?? '') . '<br>' .
         '<strong>Email : </strong>' . htmlspecialchars($row['empBusiEmail'] ?? '') . '<br>' .
         '<strong>Department : </strong>' . htmlspecialchars($row['Department'] ?? '') . '<br>' .
         '<strong>Start time : </strong><br>' . htmlspecialchars($row['meeting_start_time'] ?? '') . '<br>' .
         '<strong>End time :</strong><br>' . htmlspecialchars($row['meeting_end_time'] ?? '') .
         '</td>';

    // 5) Actions / Status (ALWAYS close the row)
    if (($row['gate_in'] ?? '0000-00-00 00:00:00') === '0000-00-00 00:00:00') {
      echo '<td>' .
           '<strong>Meeting Status:</strong> ' . htmlspecialchars($row['meetingAprroved'] ?? '') . '<br>' .

           '<button class="btn btn-primary edit-meeting-btn"
              data-meeting-id="' . htmlspecialchars($row['meeting_id']) . '"
              data-visitor-id="' . htmlspecialchars($row['visitor_id']) . '"
              data-user-name="' . htmlspecialchars($row['userName'] ?? $row['empName'] ?? '') . '"
              data-user-email="' . htmlspecialchars($row['userEmail'] ?? $row['empBusiEmailv'] ?? '') . '"
              data-user-mobile="' . htmlspecialchars($row['userMobile'] ?? $row['empBusiMobilev'] ?? '') . '"
              data-user-company="' . htmlspecialchars($row['userCompany'] ?? 'Nuvoco Vistas') . '"
              data-user-designation="' . htmlspecialchars($row['userDesignation'] ?? $row['empDesignation'] ?? '') . '"
              data-meeting-start="' . htmlspecialchars($row['meeting_start_time'] ?? '') . '"
              data-meeting-end="' . htmlspecialchars($row['meeting_end_time'] ?? '') . '">Edit Meeting</button><br>';

      if (($row['meetingAprroved'] ?? '') === 'On Hold' || ($row['meetingAprroved'] ?? '') === 'Canceled') {
        echo '<br><button class="btn btn-info approve-meeting-btn"
                data-meeting-id="' . htmlspecialchars($row['meeting_id']) . '">Approve Meeting</button>';
      }
      if (($row['meetingAprroved'] ?? '') === 'On Hold' || ($row['meetingAprroved'] ?? '') === 'Approved') {
        echo '<br><br><button class="btn btn-danger cancel-meeting-btn"
                style="background-color:red;color:white;"
                data-meeting-id="' . htmlspecialchars($row['meeting_id']) . '">Cancel Meeting</button>';
      }

      echo '</td>';
      echo '</tr>';
    } else {
      echo '<td>';
      echo '<strong>Meeting Status:</strong> ' . htmlspecialchars($row['meetingAprroved'] ?? '') . '<br>';
      echo '<strong>Gate-In Time:</strong> ' .
           (($row['gate_in'] ?? '0000-00-00 00:00:00') !== '0000-00-00 00:00:00'
             ? htmlspecialchars($row['gate_in']) : 'Not recorded') . '<br>';
      echo '<strong>Safety Induction:</strong> ' . htmlspecialchars($row['safety_induction_done'] ?? 'No') . '<br>';
      echo '<strong>Gate-Out Time:</strong> ' .
           (($row['gate_out'] ?? '0000-00-00 00:00:00') !== '0000-00-00 00:00:00'
             ? htmlspecialchars($row['gate_out']) : 'Not recorded') . '<br>';

      echo '<strong>Gear Issued:</strong><br>';
      if (!empty($row['gear_names'])) {
        $gearNames      = explode(',', $row['gear_names']);
        $gearQuantities = explode(',', $row['gear_quantities']);
        $n = max(count($gearNames), count($gearQuantities));
        for ($i = 0; $i < $n; $i++) {
          $g = $gearNames[$i]      ?? '';
          $q = $gearQuantities[$i] ?? '';
          if ($g !== '' || $q !== '') {
            echo htmlspecialchars($g) . ' - ' . htmlspecialchars($q) . '<br>';
          }
        }
      } else {
        echo 'No gear issued';
      }

      echo '</td>';
      echo '</tr>';
    }
  }
}
// If no rows, leave <tbody> empty; DataTables will show its own "empty" message.
?>
</tbody>

        </table>
      </div>

      <!-- Modal for editing meeting and user details -->
      <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="editModalLabel">Edit Meeting & User Details</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <form id="editMeetingForm" action="editMeeting.php" method="POST">
                <input type="hidden" id="editMeetingId" name="meeting_id" />
                <input type="hidden" id="editVisitorId" name="visitor_id" />

                <div class="form-group">
                  <label for="editMeetingStart">Meeting Start Time</label>
                  <input type="datetime-local" id="editMeetingStart" name="meeting_start_time" class="form-control" required />
                </div>

                <div class="form-group">
                  <label for="editMeetingEnd">Meeting End Time</label>
                  <input type="datetime-local" id="editMeetingEnd" name="meeting_end_time" class="form-control" required />
                </div>

                <hr>
                <h5>Edit Visitor Details</h5>
                <div class="form-group">
                  <label for="editUserName">Visitor Name</label>
                  <input type="text" id="editUserName" name="userName" class="form-control" required />
                </div>

                <div class="form-group">
                  <label for="editUserEmail">Visitor Email</label>
                  <input type="email" id="editUserEmail" name="userEmail" class="form-control" required />
                </div>

                <div class="form-group">
                  <label for="editUserMobile">Visitor Mobile</label>
                  <input type="text" id="editUserMobile" name="userMobile" class="form-control" required />
                </div>

                <div class="form-group">
                  <label for="editUserCompany">Visitor Company</label>
                  <input type="text" id="editUserCompany" name="userCompany" class="form-control" />
                </div>

                <div class="form-group">
                  <label for="editUserDesignation">Visitor Designation</label>
                  <input type="text" id="editUserDesignation" name="userDesignation" class="form-control" />
                </div>
                <input type="hidden" id="action" name="action" /> <!-- Hidden field for action -->

                <hr>    
              </form>
            </div>
            <div class="modal-footer">
            <button 
    type="button" 
    class="btn btn-secondary" 
    data-dismiss="modal" 
    style="z-index: 1050; position: relative; border: 2px solid #6c757d; border-radius: 4px;"
>
    Close
</button>
<button 
    type="submit" 
    id="saveEditBtn" 
    class="btn btn-primary" 
    style="z-index: 1060; position: relative; border: 2px solid #6c757d; border-radius: 4px;"
>
    Save changes
</button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal fade" id="cancelReasonModal" tabindex="-1" role="dialog" aria-labelledby="cancelReasonModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cancelReasonModalLabel">Cancel All Meetings for Today</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="cancelMeetingsForm">
          <div class="form-group">
            <label for="cancelReason">Reason for Cancellation:</label>
            <textarea id="cancelReason" class="form-control" rows="3" required></textarea>
          </div>
          <input type="hidden" id="cancelLocation" value="<?php echo $_SESSION['userDetails']['userAddress']; ?>" />
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="submit" id="confirmCancelMeetings" class="btn btn-danger">Confirm Cancellation</button>
      </div>
    </div>
  </div>
</div>

    </div>
  </div>
</div>

<!-- jQuery and Ajax logic -->
<script type="text/javascript">
  $(document).ready(function () {

     $('#meetingsTable').DataTable({
      paging: true,
      pageLength: 20,
      lengthChange: false,
      searching: true,
      ordering: true,
      info: true,
      autoWidth: false
    });
  $('#cancelMeetingsTodayBtn').on('click', function () {
    $('#cancelReasonModal').modal('show');
  });

  $('#confirmCancelMeetings').on('click', function () {
    const reason = $('#cancelReason').val();
    const location = $('#cancelLocation').val();

    if (reason.trim() === '') {
      alert('Please provide a reason for cancellation.');
      return;
    }

    $.ajax({
      url: 'https://vms.nuvoco.in/vmsdb/cancelMeetings.php',
      type: 'POST',
      data: {
        reason: reason,
        location: location,
      },
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          alert(res.message);
          location.reload();
        } else {
          alert('Error: ' + res.message);
        }
      },
      error: function (xhr, status, error) {
        alert('An error occurred: ' + error);
      },
    });
  });
});
$(document).ready(function () {
  // Get the filter parameter from the URL
  const urlParams = new URLSearchParams(window.location.search);
  const filter = urlParams.get('filter') || 'all';

  // Set the selected option in the dropdown
  $('#filterMeetingTime').val(filter);

  // Handle dropdown change event
  $('#filterMeetingTime').on('change', function () {
    const selectedFilter = $(this).val();
    window.location.href = "?filter=" + selectedFilter;
  });
});
  $(document).ready(function() {
    $('#searchMeetingId').on('keyup', function() {
      var searchValue = $(this).val().toLowerCase(); 
      $('table tbody tr').filter(function() {
        $(this).toggle($(this).find('td').text().toLowerCase().indexOf(searchValue) > -1);
      });
    });

    // Show modal when clicking Edit Meeting button
    $(document).on('click', '.edit-meeting-btn', function() {
      var meetingId = $(this).data('meeting-id');
      var visitorId = $(this).data('visitor-id');
      var userName = $(this).data('user-name');
      var userEmail = $(this).data('user-email');
      var userMobile = $(this).data('user-mobile');
      var userCompany = $(this).data('user-company');
      var userDesignation = $(this).data('user-designation');
      var meetingStart = $(this).data('meeting-start');
      var meetingEnd = $(this).data('meeting-end');

      $('#editMeetingId').val(meetingId);
      $('#editVisitorId').val(visitorId);
      $('#editUserName').val(userName);
      $('#editUserEmail').val(userEmail);
      $('#editUserMobile').val(userMobile);
      $('#editUserCompany').val(userCompany);
      $('#editUserDesignation').val(userDesignation);

      function toLocalDateTime(dtString) {
        if(!dtString || dtString === '0000-00-00 00:00:00') return '';
        var parts = dtString.split(' ');
        var date = parts[0];
        var time = parts[1];
        return date + 'T' + time; // For datetime-local input
      }

      $('#editMeetingStart').val(toLocalDateTime(meetingStart));
      $('#editMeetingEnd').val(toLocalDateTime(meetingEnd));

      $('#editModal').modal('show');
    });
    const marquee = document.getElementById('marquee');
        const toggleButton = document.getElementById('toggleButton');

        toggleButton.addEventListener('click', () => {
            marquee.classList.toggle('collapsed');
            toggleButton.textContent = marquee.classList.contains('collapsed') ? 'Expand' : 'Collapse';
        });
    // Handle form submission using jQuery AJAX for editing meeting
    $('#editMeetingForm').on('submit', function(e) {
      e.preventDefault();
      var formData = $(this).serialize();

      $.ajax({
        url: 'editMeeting.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(res) {
          var responseMessage = $('#responseMessage');
          if (res.status === 'success') {
            responseMessage.removeClass('alert-danger').addClass('alert-success');
            responseMessage.text(res.message).show().delay(5000).fadeOut();
            $('#editMeetingForm')[0].reset();
            $('#editModal').modal('hide');
            location.reload(); 
          } else {
            responseMessage.removeClass('alert-success').addClass('alert-danger');
            responseMessage.text('Error: ' + res.message).show().delay(5000).fadeOut();
          }
        },
        error: function(xhr, status, error) {
          var responseMessage = $('#responseMessage');
          responseMessage.removeClass('alert-success').addClass('alert-danger');
          responseMessage.text('An error occurred: ' + error).show().delay(5000).fadeOut();
        }
      });
    });

 


    // Trigger form submission when Save changes is clicked
    $('#saveEditBtn').on('click', function() {
      $('#editMeetingForm').submit();
    });
    

    // Approve Meeting button click
    $(document).on('click', '.approve-meeting-btn', function() {
      var meetingId = $(this).data('meeting-id');
      $.ajax({
        url: 'approveMeeting.php',
        type: 'POST',
        data: { meeting_id: meetingId },
        dataType: 'json',
        success: function(res) {
          var responseMessage = $('#responseMessage');
          if (res.status === 'success') {
            responseMessage.removeClass('alert-danger').addClass('alert-success');
            responseMessage.text(res.message).show().delay(5000).fadeOut();
            location.reload();
          } else {
            responseMessage.removeClass('alert-success').addClass('alert-danger');
            responseMessage.text('Error: ' + res.message).show().delay(5000).fadeOut();
          }
        },
        error: function(xhr, status, error) {
          var responseMessage = $('#responseMessage');
          responseMessage.removeClass('alert-success').addClass('alert-danger');
          responseMessage.text('An error occurred: ' + error).show().delay(5000).fadeOut();
        }
      });
    });
    $(document).on('click', '.cancel-meeting-btn', function() {
  var meetingId = $(this).data('meeting-id');
  $.ajax({
    url: 'cancelMeeting.php',
    type: 'POST',
    data: { meeting_id: meetingId },
    dataType: 'json',
    success: function(res) {
      var responseMessage = $('#responseMessage');
      if (res.status === 'success') {
        responseMessage.removeClass('alert-danger').addClass('alert-success');
        responseMessage.text(res.message).show().delay(5000).fadeOut();
        location.reload();
      } else {
        responseMessage.removeClass('alert-success').addClass('alert-danger');
        responseMessage.text('Error: ' + res.message).show().delay(5000).fadeOut();
      }
    },
    error: function(xhr, status, error) {
      var responseMessage = $('#responseMessage');
      responseMessage.removeClass('alert-success').addClass('alert-danger');
      responseMessage.text('An error occurred: ' + error).show().delay(5000).fadeOut();
    }
  });
});

  });
  $(document).ready(function () {
  $('#exportToExcel').click(function () {
    // Get the table element
    var table = document.querySelector('table');

    // Use SheetJS to convert the table into a workbook
    var workbook = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });

    // Export the workbook as an Excel file
    XLSX.writeFile(workbook, 'meeting_data.xlsx');
  });
});
</script>

<!-- Include Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>


<?php include('footer.php'); ?>
