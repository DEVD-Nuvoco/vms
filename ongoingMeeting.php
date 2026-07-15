<?php include("header.php");
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);
date_default_timezone_set('Asia/Kolkata');

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
    text-align: center;
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
  .table-responsive {
    width: 100%;
    overflow-x: auto;
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
    font-size: 0.9rem;
  }
  .table th {
    background-color: #f1f1f1;
    font-weight: bold;
  }
  @media screen and (max-width: 768px) {
    .az-content-title {
      font-size: 1.2rem;
    }

    .btn {
      font-size: 0.8rem;
      padding: 5px 10px;
    }

    .table th, .table td {
      font-size: 0.8rem;
      padding: 5px;
    }

    .form-control {
      font-size: 0.8rem;
      padding: 8px;
    }
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
    opacity: 0; 
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
                    <img src="https://vms.nuvoco.in/vmsdb/faces/<?= $row['user_id']; ?>_profile.webp" alt="Photo">
                    <span class="visitor-name"><?= htmlspecialchars($row['userName']); ?></span>
                <?php else: ?>
                    <!-- Custom visitor with uploaded photo -->
                    <img src="<?= $row['photo'] ?: 'default.png'; ?>" alt="Photo">
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

        </nav>
      </div>
    </div>
    <div class="az-content-body pd-lg-l-40 d-flex flex-column">
      <h2 class="az-content-title">Ongoing Meeting</h2>

      <div class="form-group">
        <label for="searchMeetingId">Search by Meeting ID</label>
        <input type="text" id="searchMeetingId" class="form-control" placeholder="Enter Meeting ID">
      </div>

    

      <div class="form-group d-flex justify-content-end align-items-center">
<button id="exportToExcel" class=" btn-success" style="margin-right: 10px;">Export to Excel</button>

     
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
            $filter = $_GET['filter'] ?? 'all';
            $condition = "";

            switch ($filter) {
              case 'today':
                $condition = "AND DATE(m.meeting_start_time) = CURDATE()";
                break;
              case 'this_week':
                $condition = "AND WEEK(m.meeting_start_time) = WEEK(CURDATE())";
                break;
              case 'last_week':
                $condition = "AND WEEK(m.meeting_start_time) = WEEK(CURDATE()) - 1";
                break;
              case 'last_month':
                $condition = "AND MONTH(m.meeting_start_time) = MONTH(CURDATE()) - 1";
                break;
              default:
                $condition = "";
            }
            $currentTime = date('Y-m-d H:i:s');

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
                                m.gate_out,
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
                m.meeting_location = '$location' $condition
      AND m.meeting_start_time <= '$currentTime'
      AND m.meeting_end_time >= '$currentTime'
  GROUP BY 
      m.meeting_id 
  ORDER BY 
      m.meeting_id DESC
");
      
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
      echo '<tr style="color:black;">';
      echo '<td>' . htmlspecialchars($row['meeting_id']) . '</td>';
      echo '<td>' . htmlspecialchars($row['userName']) . '<br><img width="100" src="https://vms.nuvoco.in/vmsdb/faces/' . htmlspecialchars($row['visitor_id']) . '_profile.webp" alt="Visitor Image"></td>';
      echo '<td> <strong>Email : </strong>' . htmlspecialchars($row['userEmail']) . ' <br> <strong>Mobile : </strong>' . htmlspecialchars($row['userMobile']) . '<br> <strong>Company : </strong>' . htmlspecialchars($row['userCompany']) . ' <br> <strong>Designation : </strong>' . htmlspecialchars($row['userDesignation']) . '<br> <strong>Location : </strong>' . htmlspecialchars($row['userAddress']) . ' <br><strong>Visit Type : </strong>' . htmlspecialchars($row['visit_type']) . '</td>';
      echo '<td> <strong>Name : </strong>' . htmlspecialchars($row['meeting_person']) . ' <br><strong>Mobile : </strong>' . htmlspecialchars($row['empBusiMobile']) . '<br> <strong>Email : </strong>' . htmlspecialchars($row['empBusiEmail']) . ' <br><strong>Department : </strong>' . htmlspecialchars($row['Department']) . '<br><strong>Start time : </strong> <br>' . htmlspecialchars($row['meeting_start_time']) . ' <br> <strong>End time :</strong>  <br>' . htmlspecialchars($row['meeting_end_time']) . '</td>';
      if ($row['gate_in'] === '0000-00-00 00:00:00') {
          echo '<td></td>';
      } else {
          echo '<td>';
          echo '<strong>Meeting Status:</strong> ' . htmlspecialchars($row['meetingAprroved'])  . '<br>';
          echo '<strong>Gate-In Time:</strong> ' . ($row['gate_in'] !== '0000-00-00 00:00:00' ? htmlspecialchars($row['gate_in']) : 'Not recorded') . '<br>';
          echo '<strong>Gear Issued:</strong><br>';
          if (!empty($row['gear_names'])) {
              $gearNames = explode(',', $row['gear_names']);
              $gearQuantities = explode(',', $row['gear_quantities']);
              for ($i = 0; $i < count($gearNames); $i++) {
                  echo htmlspecialchars($gearNames[$i]) . ' - ' . htmlspecialchars($gearQuantities[$i]) . '<br>';
              }
          } else {
              echo 'No gear issued';
          }
          // Forward functionality: searchable select and button
          echo '<div style="margin-top:10px;"><strong>Forward this meeting to:</strong><br>';
          echo '<select class="form-control forward-to" data-meeting-id="' . htmlspecialchars($row['meeting_id']) . '">';
          echo '<option value="">Select Employee</option>';
          echo '</select> ';
          echo '<button class="btn btn-primary forward-btn" data-meeting-id="' . htmlspecialchars($row['meeting_id']) . '">Forward</button>';
          echo '</div>';
          echo '</td>';
      }
      echo '</tr>';
  }
} else {
  echo '<tr><td colspan="5" class="text-center">No meetings available</td></tr>';
}
?>
</tbody>
        </table>
      </div> 
    </div>
  </div>
</div>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- jQuery and Ajax logic -->
<script type="text/javascript">

$(document).ready(function () {
  const urlParams = new URLSearchParams(window.location.search);
  const filter = urlParams.get('filter') || 'all';
  $('#filterMeetingTime').val(filter);
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
    const marquee = document.getElementById('marquee');
        const toggleButton = document.getElementById('toggleButton');

        toggleButton.addEventListener('click', () => {
            marquee.classList.toggle('collapsed');
            toggleButton.textContent = marquee.classList.contains('collapsed') ? 'Expand' : 'Collapse';
        });
    $('#saveEditBtn').on('click', function() {
      $('#editMeetingForm').submit();
    });
  });
  $(document).ready(function () {
  $('#exportToExcel').click(function () {
    var table = document.querySelector('table');
    var workbook = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
    XLSX.writeFile(workbook, 'meeting_data.xlsx');
  });
});
$(document).ready(function () {
  // Populate each forward dropdown with employee data
  $('.forward-to').each(function(){
      var $select = $(this);
      $.ajax({
          url: 'https://vms.nuvoco.in/vmsdb/search_employee.php',
          type: 'GET',
          dataType: 'json',
          data: { searchIndex: '' },  // Pass an empty search term to fetch all active employees
          success: function(data){
              $select.empty();
              $select.append('<option value="">Select Employee</option>');
              $.each(data, function(index, item){
                  $select.append(
                    '<option value="'+item.empCode+'">'+
                      item.empName+' ('+item.empDepartment+', '+item.empLocation+')'+
                    '</option>'
                  );
              });
          },
          error: function(xhr, status, error){
              console.error("Error fetching employees: ", error);
          }
      });
  });

  // Handle the forward button click event
  $(document).on('click', '.forward-btn', function () {
      var meetingId = $(this).data('meeting-id');
      // Find the corresponding select element in the same container
      var forwardSelect = $(this).siblings('.forward-to');
      var forwardUser = forwardSelect.val();

      if (!forwardUser) {
          alert('Please select an employee to forward the meeting to.');
          return;
      }

      // Make the AJAX call to update the meeting forward
      $.ajax({
          url: 'https://vms.nuvoco.in/vmsdb/update_ongoing_meeting.php', // URL to your update file
          type: 'POST',
          data: {
              meeting_id: meetingId,
              forwardUser: forwardUser
          },
          dataType: 'json',
          success: function (response) {
              if (response.status === 'success') {
                  alert("Meeting forwarded successfully!");
                  // Optionally update the UI to reflect the change
              } else {
                  alert("Error: " + response.message);
              }
          },
          error: function (xhr, status, error) {
              alert("An error occurred while forwarding the meeting: " + error);
          }
      });
  });
});


</script>

<!-- Include Select2 JS -->


<?php include('footer.php'); ?>
