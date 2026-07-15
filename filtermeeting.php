<?php
include('db.php');

header('Content-Type: application/json');

// Validate the filter
$filter = $_POST['filter'] ?? 'all';
$location = $_SESSION['userDetails']['userAddress'];
$condition = "";

// Apply filtering logic
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

$query = "
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
      m.meeting_location = '$location' $condition
  GROUP BY 
      m.meeting_id 
  ORDER BY 
      m.meeting_id DESC;
";

$result = $mysqli->query($query);

if ($result && $result->num_rows > 0) {
    $meetings = [];
    while ($row = $result->fetch_assoc()) {
        $meetings[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $meetings]);
} else {
    echo json_encode(['success' => false, 'message' => 'No meetings found']);
}
?>
