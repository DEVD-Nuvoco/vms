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
    border: 2px solid #dcdcdc;
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
</style>
<script src="cities.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script src="ajaxable.min.js"></script>
<div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">
  <div class="container">
    <div class="az-content-left az-content-left-components">
      <div class="component-item">
        <label>Previous Meetings</label>
        <nav class="nav flex-column">
          <a href="watchman2.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'watchman2.php' ? 'active' : ''; ?>">Issue Gear</a>
          <a href="watchman.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'watchman.php' ? 'active' : ''; ?>">Gate-In & Gear Tracking</a>
          <a href="newMeeting.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'newMeeting.php' ? 'active' : ''; ?>">New Meeting</a>
        </nav>
      </div>
    </div>
    <div class="az-content-body pd-lg-l-40 d-flex flex-column">
      <h2 class="az-content-title">Gear Assignment and Gate-In Tracking</h2>

      <div class="form-group">
        <label for="searchMeetingId">Search by Meeting ID</label>
        <input type="text" id="searchMeetingId" class="form-control" placeholder="Enter Meeting ID">
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
        <table class="table table-bordered">
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
            $location =  $_SESSION['userDetails']['userAddress'];

            // Fetch meetings and issued gear details
            $result = $mysqli->query("
 SELECT 
    m.meeting_id, 
    m.visitor_id,
    u.*,
    ne.Department,
    ne.empBusiMobile,
    ne.empBusiEmail,
    m.meeting_person, 
    m.visit_type, 
    m.meetingAprroved,
    m.meeting_start_time, 
    m.meeting_end_time, 
    m.gate_in,
    GROUP_CONCAT(g.gear_name) AS gear_names, 
    GROUP_CONCAT(g.gear_quantity) AS gear_quantities
FROM 
    meetings m
LEFT JOIN 
    gear_issued g ON m.meeting_id = g.meeting_id
LEFT JOIN
    tbl_user u ON m.visitor_id = u.id
LEFT JOIN
    tbl_nuvo_employee ne ON m.meetperson_id = ne.empCode
WHERE 
    m.meeting_location = '$location'
GROUP BY 
    m.meeting_id 
    order by m.meeting_id  DESC;
");
      
            if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                echo '<tr style="color:black;">';
                echo '<td>' . htmlspecialchars($row['meeting_id']) . '</td>';

                echo '<td>' . htmlspecialchars($row['userName']) . '<br><img width = 100 src="https://vms.nuvoco.in/vmsdb/faces/' . htmlspecialchars($row['visitor_id']) . '_profile.webp" alt="Visitor Image"></td>';
                echo '<td> <strong>Email : </strong>' . htmlspecialchars($row['userEmail']) .' <br> <strong>Mobile : </strong>' . htmlspecialchars($row['userMobile']) . '<br> <strong>Company : </strong>' . htmlspecialchars($row['userCompany']) . ' <br> <strong>Designation : </strong>' . htmlspecialchars($row['userDesignation']) . '<br> <strong>Location : </strong>' . htmlspecialchars($row['userAddress']) . ' <br><strong>Visit Type : </strong>' . htmlspecialchars($row['visit_type']) . '</td>';

                echo '<td> <strong>Name : </strong>' . htmlspecialchars($row['meeting_person']) . ' <br><strong>Mobile : </strong>' . htmlspecialchars($row['empBusiMobile']) . '<br> <strong>Email : </strong>' . htmlspecialchars($row['empBusiEmail']) .' <br><strong>Department : </strong>' . htmlspecialchars($row['Department']) .'<br><strong>Start time : </strong> <br>'. htmlspecialchars($row['meeting_start_time']) . ' <br> <strong>End time :</strong>  <br>' . htmlspecialchars($row['meeting_end_time']) . '</td>';
                

                // Check if gate_in is '0000-00-00 00:00:00'
                if ($row['gate_in'] === '0000-00-00 00:00:00') {
                  echo '<td> <strong>Meeting Status:</strong> '.htmlspecialchars($row['meetingAprroved'])  .'<br>';
                  
                  // Show Edit Meeting button
                  echo '<button class="btn btn-primary edit-meeting-btn"
                    data-meeting-id="' . htmlspecialchars($row['meeting_id']) . '"
                    data-visitor-id="' . htmlspecialchars($row['visitor_id']) . '"
                    data-user-name="' . htmlspecialchars($row['userName']) . '"
                    data-user-email="' . htmlspecialchars($row['userEmail']) . '"
                    data-user-mobile="' . htmlspecialchars($row['userMobile']) . '"
                    data-user-company="' . htmlspecialchars($row['userCompany']) . '"
                    data-user-designation="' . htmlspecialchars($row['userDesignation']) . '"
                    data-meeting-start="' . htmlspecialchars($row['meeting_start_time']) . '"
                    data-meeting-end="' . htmlspecialchars($row['meeting_end_time']) . '"
                    >Edit Meeting</button><br>';

                  // If meeting is On hold, show Approve Meeting button
                  if ($row['meetingAprroved'] === 'On Hold') {
                    echo '<br><button class="btn btn-info approve-meeting-btn" 
                          data-meeting-id="' . htmlspecialchars($row['meeting_id']) . '">Approve Meeting</button>';
                  }

                  echo '</td>';
                  echo '</tr>';
                } else {
                  // Show issued gear and gate-in time
                  echo '<td>';
                  echo '<strong>Meeting Status:</strong> '.htmlspecialchars($row['meetingAprroved'])  . '<br>';

                  echo '<strong>Gate-In Time:</strong> ' . ($row['gate_in'] !== '0000-00-00 00:00:00' ? htmlspecialchars($row['gate_in']) : 'Not recorded') . '<br>';
                  echo '<strong>Gear Issued:</strong><br>';

                  // Display the gear names and quantities
                  if (!empty($row['gear_names'])) {
                    $gearNames = explode(',', $row['gear_names']);
                    $gearQuantities = explode(',', $row['gear_quantities']);
                    for ($i = 0; $i < count($gearNames); $i++) {
                      echo htmlspecialchars($gearNames[$i]) . ' - ' . htmlspecialchars($gearQuantities[$i]) . '<br>';
                    }
                  } else {
                    echo 'No gear issued';
                  }
                  echo '</td>';
                }
                
              }
            } else {
              echo '<tr><td colspan="5" class="text-center">No meetings available</td></tr>';
            }
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
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              <button type="submit" id="saveEditBtn" class="btn btn-primary">Save changes</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- jQuery and Ajax logic -->
<script type="text/javascript">
  $(document).ready(function() {
    $('#searchMeetingId').on('keyup', function() {
      var searchValue = $(this).val().toLowerCase(); 
      $('table tbody tr').filter(function() {
        $(this).toggle($(this).find('td:first').text().toLowerCase().indexOf(searchValue) > -1);
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
  });
</script>

<!-- Include Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<?php include('footer.php'); ?>
