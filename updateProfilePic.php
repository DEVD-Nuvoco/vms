<?php include("header.php") ?>
<div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">
  <div class="container">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/webcamjs/1.0.24/webcam.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

    <?php include("myProfileLeft.php") ?>
    <div class="az-content-body pd-lg-l-40 d-flex flex-column">
      <h2 class="az-content-title">New Meeting Details</h2>
      <p class="mg-b-20">Capture picture or upload a file for further meeting registration.</p>
      <div class="row">
        <!-- Capture Photo Section -->
        <div class="col-lg-6 text-center">
          <label><strong>Capture Photo</strong></label>
          <div id="my_camera" class="pre_capture_frame"></div>
          <input type="hidden" name="captured_image_data" id="captured_image_data">
          <br>
          <input type="button" class="btn btn-danger btn-round btn-file" value="Take Snapshot" onClick="take_snapshot()">
        </div>

        <!-- Display Output Section -->
        <div class="col-lg-6 text-center">
          <label><strong>Output</strong></label>
          <div id="results">
            <img style="width:290px;" class="after_capture_frame" src="img/faces/default.png" />
          </div>
          <br>
          <button type="button" class="btn btn-success" onClick="saveSnap()">Save Picture</button>
        </div>
      </div>

      <!-- Upload Picture Section -->
      <div class="row mt-4">
        <div class="col-lg-12 text-center">
          <form id="uploadForm" enctype="multipart/form-data">
            <label><strong>Upload Picture</strong></label>
            <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png" class="form-control mb-3">
            <input type="hidden" name="userId" id="userId" value="<?php echo $userId; ?>"> <!-- Replace with dynamic user ID -->
            <button type="submit" class="btn btn-primary">Upload Picture</button>
          </form>
          <p id="statusMessage" class="mt-3"></p>
        </div>
      </div>

      <script>
        // Configure webcam
        Webcam.set({
          width: 460,
          height: 478,
          image_format: 'jpeg',
          jpeg_quality: 90
        });
        Webcam.attach('#my_camera');

        // Take snapshot
        function take_snapshot() {
          Webcam.snap(function(data_uri) {
            document.getElementById('results').innerHTML =
              '<img class="after_capture_frame" width="460" height="478" src="' + data_uri + '"/>';
            document.getElementById('captured_image_data').value = data_uri;
          });
        }

        // Save snapshot to backend
        function saveSnap() {
    const base64data = document.getElementById('captured_image_data').value;
    const userId = document.getElementById('userId').value;

    if (!base64data || !userId) {
        alert("Captured image or userId is missing!");
        return;
    }

    $.ajax({
        type: "POST",
        dataType: "json",
        url: "https://vms.nuvoco.in/vmsdb/upload_profile.php",
        data: { image: base64data, userId: userId },
        success: function(response) {
            const statusMessage = document.getElementById('statusMessage');
            if (response.status === 'success') {
                statusMessage.textContent = response.message;
                statusMessage.className = 'text-success';
                document.getElementById('results').innerHTML =
                    '<img class="after_capture_frame" width="290" src="' + response.filePath + '"/>';
            } else {
                statusMessage.textContent = response.message;
                statusMessage.className = 'text-danger';
            }
			window.location.href='updateProfilePic.php';
        },
        error: function() {
            alert('An error occurred while saving the picture.');
        }
    });
}

        // Upload file to backend
        $('#uploadForm').on('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          $.ajax({
            url: 'https://vms.nuvoco.in/vmsdb/upload_profile.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
              const data = JSON.parse(response);
              const statusMessage = document.getElementById('statusMessage');
              if (data.status === 'success') {
                statusMessage.textContent = data.message;
                statusMessage.className = 'text-success';
                document.getElementById('results').innerHTML =
                  '<img class="after_capture_frame" width="290" src="' + data.filePath + '"/>';
              } else {
                statusMessage.textContent = data.message;
                statusMessage.className = 'text-danger';
              }
			  window.location.href='updateProfilePic.php';
            },
            error: function() {
              alert('An error occurred while uploading the image.');
            }
          });
        });
      </script>

      <?php include("footer.php") ?>
    </div>
  </div>
</div>
</body>
</html>
