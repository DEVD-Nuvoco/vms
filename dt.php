<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <!-- 1) jQuery -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

  <!-- 2) DataTables CSS & JS -->
  <link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css"
  />
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
</head>
<body>
  <table id="meetingsTable">
    <thead>
      <tr><th>Test</th></tr>
    </thead>
    <tbody>
      <tr><td>Hello</td></tr>
      <tr><td>World</td></tr>
    </tbody>
  </table>

  <script>
    console.log("jQ:", $.fn.jquery);
    console.log("DT:", typeof $.fn.DataTable);
    $('#meetingsTable').DataTable({
      pageLength: 2
    });
  </script>
</body>
</html>
