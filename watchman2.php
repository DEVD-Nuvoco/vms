<?php include("header.php");?>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode/minified/html5-qrcode.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">
  <div class="container">
    <div class="az-content-left az-content-left-components">
      <div class="component-item">
        <label>Previous Meetings</label>
        <nav class="nav flex-column">
          <a href="watchman2.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'watchman2.php' ? 'active' : ''; ?>">Issue Gear</a>
          <a href="watchman.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'watchman.php' ? 'active' : ''; ?>">Gate-In & Gear Tracking</a>
          <a href="newMeeting.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'newMeeting.php' ? 'active' : ''; ?>">New Meeting</a>
          <a href="ongoingMeeting.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ongoingMeeting.php' ? 'active' : ''; ?>">Ongoing Meeting</a>
          <a href="blacklist.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'blacklist.php' ? 'active' : ''; ?>">Blacklist User</a>
        </nav>
      </div>
    </div>
    <div class="az-content-body pd-lg-l-40 d-flex flex-column">
      <style>
        /* Content Section */
        .az-content-body {
          padding: 20px;
          display: flex;
          flex-direction: column;
        }
        h1 {
          text-align: left;
          font-size: 1.8rem;
          font-weight: bold;
          margin-bottom: 1rem;
          color: #42bb52;
        }
        /* Containers */
        #container {
          display: flex;
          flex-wrap: wrap;
          gap: 20px;
        }
        #scanner,
        #details {
          flex: 1;
          background: #fff;
          border-radius: 10px;
          padding: 20px;
          box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        #reader {
          margin: 20px auto;
          max-width: 100%;
          border: 1px dashed #ccc;
          padding: 10px;
          background-color: #f9f9f9;
          border-radius: 10px;
        }
        /* Buttons */
        .btn {
          padding: 10px 15px;
          border: none;
          border-radius: 5px;
          transition: background-color 0.3s, transform 0.2s;
        }
        .btn:hover {
          transform: scale(1.05);
        }
        .btn-primary {
          background-color: #007bff;
          color: #fff;
        }
        .btn-secondary {
          background-color: #6c757d;
          color: #fff;
        }
        /* Form Inputs */
        .form-control {
          border-radius: 5px;
          margin-bottom: 15px;
        }
        /* Card Style */
        .card {
          padding: 15px;
          background: #f8f9fa;
          border: none;
          border-radius: 10px;
          margin-bottom: 20px;
        }
        /* Camera Section */
        #cameraSection {
          text-align: center;
        }
        video {
          width: 100%;
          max-width: 300px;
          border: 1px solid #ddd;
        }
        canvas {
          display: none;
          width: 100%;
          max-width: 300px;
          margin-top: 10px;
        }
        #captureButton,
        #closeCamera {
          margin-top: 10px;
        }
        /* Responsive Design */
        @media (max-width: 576px) {
          #container {
            flex-direction: column;
          }
          #scanner,
          #details {
            margin-bottom: 20px;
            padding: 15px;
          }
          h1 {
            font-size: 1.5rem;
            text-align: center;
          }
          .nav-link {
            padding: 8px 10px;
            font-size: 0.9rem;
          }
        }
        @media (max-width: 768px) {
          .az-header {
            flex-direction: column;
            text-align: center;
          }
          h1 {
            text-align: center;
            font-size: 1.6rem;
          }
          #reader {
            max-width: 90%;
          }
        }
        @media (min-width: 992px) {
          #container {
            flex-wrap: nowrap;
          }
          #scanner,
          #details {
            flex: 1;
            max-width: 48%;
          }
        }
      </style>
      <div style="display: flex; justify-content: flex-start; align-items: flex-start;">
        <h1 style="text-align: left; margin-left: 0; font-size: 1.8rem; font-weight: bold; margin-bottom: 1rem; color: #42bb52;">
          Issue Gear and Gate Timing
        </h1>
      </div>
      <div id="container">
        <!-- QR Scanner Section -->
        <div id="scanner">
          <h2>Scan QR Code</h2>
          <button id="openScanner" class="btn btn-primary">Use Camera to Scan QR</button>
          <button id="closeScanner" class="btn btn-secondary" style="display: none;">Close Camera</button>
          <div id="reader" style="display: none;"></div>
          <p id="decodedText" style="margin-top: 20px; font-weight: bold;"></p>
        </div>
        <!-- Details Section -->
        <div id="details">
          <!-- Meeting ID Search -->
          <div id="search-section" class="card">
            <h4>Search by Meeting ID</h4>
            <form id="searchForm">
              <input type="text" id="searchMeetingId" name="search_meeting_id" class="form-control" placeholder="Enter Meeting ID" required>
              <button type="submit" class="btn btn-primary mt-2">Search</button>
            </form>
          </div>
          <!-- Scanned or Searched Data -->
          <div id="debug" class="card" style="display: none;">
            <h4>Meeting Details</h4>
            <p><strong>Meeting ID:</strong> <span id="displayMeetingId">N/A</span></p>
            <div id="allMeetingData"></div>
          
          </div>
          <!-- Form Section -->
          <div id="meeting-details" class="card" style="display: none;">
            <form id="meetingForm">
              <!-- Vehicle Permit -->
              <label for="vehiclePermit">Vehicle Number:</label>
              <input type="text" id="vehiclePermit" name="vehicle_permit" class="form-control" placeholder="Enter vehicle number">
              <!-- Baggage Details -->
              <label for="baggageDetails">Baggage Details:</label>
              <input type="text" id="baggageDetails" name="baggage_details" class="form-control" placeholder="Enter baggage details">
              <!-- Token Number for Baggage (New Input) -->
              <label for="tokenNumber">Token Number:</label>
              <input type="text" id="tokenNumber" name="token_number" class="form-control" placeholder="Enter token number">
              <!-- Gear Section -->
              <h4>Gear</h4>
              <div id="gearSection">
                <div class="gear-item d-flex align-items-center">
                  <select name="gear[]" class="form-control gear-select">
                    <option value="">Select Gear</option>
                    <option value="Helmet">Helmet</option>
                    <option value="Mask">Mask</option>
                    <option value="custom">Add Custom Gear</option>
                  </select>
                  <input type="number" name="gear-quantity[]" class="form-control ml-2" min="1" value="1" style="width: 80px;">
                  <!-- New returnable select field -->
                  <select name="returnable[]" class="form-control ml-2" required>
                    <option value="Yes" selected>Yes</option>
                    <option value="No">No</option>
                  </select>
                  <button type="button" class="btn btn-danger ml-2 remove-gear-btn">Remove</button>
                </div>
              </div>
              <button type="button" id="addGearBtn" class="btn btn-secondary mt-3">Add Gear</button>
              <!-- ID Cards Section -->
              <h4 class="mt-4">ID Cards</h4>
              <div id="idCardSection">
                <div class="id-card-item d-flex align-items-center">
                  <select name="id_card[]" class="form-control id-card-select">
                    <option value="">Select ID Card</option>
                    <option value="Aadhar Card">Aadhar Card</option>
                    <option value="PAN Card">PAN Card</option>
                    <option value="Driver License">Driver License</option>
                    <option value="Election Card">Election Card</option>
                    <option value="custom">Add Custom ID Proof</option>
                  </select>
                  <input type="text" name="id-number[]" class="form-control ml-2" placeholder="Enter ID Number" style="width: 200px;">
                  <button type="button" class="btn btn-danger ml-2 remove-id-btn">Remove</button>
                </div>
              </div>
              <button type="button" id="addIdCardBtn" class="btn btn-secondary mt-3">Add ID Card</button>
              <!-- Camera Section -->
              <h4>Upload or Capture Image</h4>
              <input type="file" id="uploadImage" accept="image/*" class="form-control mb-3">
              <button id="openCamera" type="button" class="btn btn-primary">Use Camera</button>
              <button id="recaptureImage" type="button" class="btn btn-secondary" style="display: none;">Recapture Image</button>
              <div id="cameraSection" style="display: none; margin-top: 20px; text-align: center;">
                <video id="cameraStream" autoplay style="width: 100%; max-width: 300px; border: 1px solid #ddd;"></video>
                <canvas id="capturedImage" style="display: none; width: 100%; max-width: 300px; margin-top: 10px;"></canvas>
                <input type="hidden" id="capturedImageData" name="capturedImageData" />
                <div style="margin-top: 10px;">
                  <button type="button" id="captureButton" class="btn btn-secondary">Capture</button>
                  <button type="button" id="closeCamera" class="btn btn-danger">Close Camera</button>
                </div>
              </div>
              <!-- Submit Button -->
              <button type="submit" id="submitDetails" class="btn btn-primary mt-3">Submit Details</button>
            </form>
          </div>
          <!-- Gate Out Form -->
<div id="gateOutForm" class="card" style="display: none; margin-top:20px;">
  <h4>Gate Out Form</h4>
  
  <!-- Display Meeting Image -->
  <div class="form-group">
    <label>Meeting Image:</label>
    <div>
      <img id="meetingImage" src="" alt="Meeting Image" style="max-width: 300px;">
    </div>
  </div>
  
  <!-- Items Recoverable -->
  <div class="form-group">
    <label>Items Recoverable:</label>
    <div id="itemsRecoverable">
      <!-- Gear items will be loaded here dynamically -->
    </div>
  </div>
  
  <!-- Extra Items Field -->
  <div class="form-group">
    <label for="extraItems">Extra Items (if any):</label>
    <input type="text" id="extraItems" name="extra_items" class="form-control" placeholder="Enter extra items">
  </div>
  
  <!-- Submit Button -->
  <button type="button" class="btn btn-primary" id="gateOutSubmit">Submit Gate Out</button>
</div>

        </div>
      </div>
      <script>
        // QR Scanner Section
        document.getElementById('openScanner').addEventListener('click', function () {
          document.getElementById('reader').style.display = 'block';
          document.getElementById('openScanner').style.display = 'none';
          document.getElementById('closeScanner').style.display = 'inline-block';

          scannerInstance = new Html5Qrcode("reader");
          scannerInstance.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            (decodedText) => {
              document.getElementById('decodedText').innerText = `Decoded QR Code: ${decodedText}`;
            },
            (error) => console.error(error)
          );
        });

        document.getElementById('closeScanner').addEventListener('click', function () {
          if (scannerInstance) {
            scannerInstance.stop();
            scannerInstance = null;
          }
          document.getElementById('reader').style.display = 'none';
          document.getElementById('openScanner').style.display = 'inline-block';
          document.getElementById('closeScanner').style.display = 'none';
        });

        // Camera Handlers
        let cameraStream = null;
        document.getElementById('openCamera').addEventListener('click', function () {
          navigator.mediaDevices
            .getUserMedia({ video: true })
            .then((stream) => {
              cameraStream = stream;
              document.getElementById('cameraStream').srcObject = stream;
              document.getElementById('cameraSection').style.display = 'block';
              document.getElementById('openCamera').style.display = 'none';
              document.getElementById('recaptureImage').style.display = 'inline-block';
            })
            .catch((error) => alert('Unable to access camera: ' + error.message));
        });

        document.getElementById('closeCamera').addEventListener('click', function () {
          if (cameraStream) {
            cameraStream.getTracks().forEach((track) => track.stop());
            cameraStream = null;
          }
          document.getElementById('cameraSection').style.display = 'none';
          document.getElementById('openCamera').style.display = 'inline-block';
          document.getElementById('recaptureImage').style.display = 'none';
        });

        document.getElementById('captureButton').addEventListener('click', function (event) {
          event.preventDefault();
          const canvas = document.getElementById('capturedImage');
          const context = canvas.getContext('2d');
          const video = document.getElementById('cameraStream');
          const capturedImageData = document.getElementById('capturedImageData');

          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
          context.drawImage(video, 0, 0, canvas.width, canvas.height);
          const base64Image = canvas.toDataURL('image/png');
          capturedImageData.value = base64Image;
          canvas.style.display = 'block';
        });

        // Upload Handler
        document.getElementById('uploadImage').addEventListener('change', function (event) {
          const file = event.target.files[0];
          if (file && file.type.startsWith('image/')) {
            alert('Image uploaded successfully.');
          } else {
            alert('Only image files are allowed.');
            event.target.value = '';
          }
        });
        document.getElementById('submitDetails').addEventListener('click', function () {
          alert('Details submitted successfully.');
        });

        let html5QrCode;
        $('#addGearBtn').on('click', function() {
          var gearHtml = `
            <div class="gear-item d-flex align-items-center mb-2">
              <select name="gear[]" class="form-control gear-select" required>
                <option value="">Select Gear</option>
                <option value="Helmet">Helmet</option>
                <option value="Mask">Mask</option>
                <option value="custom">Add Custom Gear</option>
              </select>
              <input type="number" name="gear-quantity[]" class="form-control ml-2" min="1" value="1" required style="width: 70px;">
              <!-- New returnable select field -->
              <select name="returnable[]" class="form-control ml-2" required>
                <option value="Yes" selected>Yes</option>
                <option value="No">No</option>
              </select>
              <button type="button" class="btn btn-danger ml-2 remove-gear-btn">Remove</button>
            </div>`;
          $('#gearSection').append(gearHtml);
        });

        // Remove gear fields
        $(document).on('click', '.remove-gear-btn', function() {
          $(this).closest('.gear-item').remove();
        });

        // Handle Form Submission
        document.getElementById('meetingForm').addEventListener('submit', async (event) => {
          event.preventDefault();
          const meetingId = document.getElementById('displayMeetingId').innerText;
          if (!meetingId || meetingId === 'N/A') {
            alert('Meeting ID is missing or invalid. Please try again.');
            return;
          }
          const formData = new FormData(event.target);
          formData.append('meeting_id', meetingId);
          try {
            const response = await fetch('https://vms.nuvoco.in/vmsdb/update_meeting_details.php', {
              method: 'POST',
              body: formData,
            });
            const result = await response.json();
            if (result.status === 'success') {
              alert('Details submitted successfully, and Gate-in recorded.');
              document.getElementById('meeting-details').style.display = 'none';
            } else {
              alert(`Failed to submit details: ${result.message}`);
            }
          } catch (error) {
            alert(`Error while submitting details: ${error.message}`);
          }
        });
        function loadGateOutForm(meetingId) {
  // Update the meeting image source using meetingId.jpg from the uploads directory
  document.getElementById('meetingImage').src = 'https://vms.nuvoco.in/vmsdb/uploads/' + meetingId + '.jpg';
  
  // Fetch the gear issued details from the gear_issued table for this meeting
  fetch(`https://vms.nuvoco.in/vmsdb/get_gear_issued.php?meeting_id=${meetingId}`)
    .then(response => response.json())
    .then(data => {
      const container = document.getElementById('itemsRecoverable');
      container.innerHTML = ''; // Clear previous content if any
      
      // Assume data is an array of objects like { id, gear_name }
      data.forEach(item => {
  const div = document.createElement('div');
  div.className = 'gear-item-check mb-2';
  div.innerHTML = `
    <label>${item.gear_name}:</label>
    <label class="ml-2">
      <input type="radio" name="gear_${item.gear_id}" value="yes" required> Received
    </label>
    <label class="ml-2">
      <input type="radio" name="gear_${item.gear_id}" value="no" required> Not Received
    </label>
  `;
  container.appendChild(div);
});
    })
    .catch(error => console.error('Error fetching gear issued:', error));
  
  // Show the Gate Out form
  document.getElementById('gateOutForm').style.display = 'block';
}

        // Start QR Scanner
        async function startScanner() {
          if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
          }
          html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            async (decodedText) => {
              document.getElementById('decodedText').innerText = `Decoded QR Code: ${decodedText}`;
              const { meetingId, gateIn, visitorId } = extractDetailsFromQR(decodedText);
              displayMeetingDetails(meetingId, gateIn, visitorId);
            },
            (errorMessage) => console.error(errorMessage)
          ).catch((err) => console.error("Error starting scanner:", err));
        }

        // Extract Details from QR
        function extractDetailsFromQR(qrData) {
          const lines = qrData.split('\n');
          let meetingId = null, gateIn = null, visitorId = null;
          for (const line of lines) {
            if (line.startsWith('Meeting ID:')) meetingId = line.split(':')[1].trim();
            if (line.startsWith('Gate In:')) gateIn = line.split(':')[1].trim();
            if (line.startsWith('Visitor ID:')) visitorId = line.split(':')[1].trim();
          }
          return { meetingId, gateIn, visitorId };
        }
        document.getElementById('gateOutSubmit').addEventListener('click', async function() {
  const meetingId = document.getElementById('displayMeetingId').innerText;
  
  // Gather the gear received status
  let gearStatus = {};
  const gearItems = document.querySelectorAll('#itemsRecoverable .gear-item-check');
  gearItems.forEach(item => {
    // Each gear item has radio buttons named like "gear_<id>"
    const radios = item.querySelectorAll('input[type="radio"]');
    radios.forEach(radio => {
      if (radio.checked) {
        gearStatus[radio.name] = radio.value;
      }
    });
  });
  
  // Get any extra items entered
  const extraItems = document.getElementById('extraItems').value;
  
  // Build the payload to send to update_gate_out endpoint
  const payload = {
    meeting_id: meetingId,
    gear_status: gearStatus,
    extra_items: extraItems
  };
  
  try {
    const response = await fetch('https://vms.nuvoco.in/vmsdb/update_gate_out.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (result.status === 'success') {
      alert('Gate-out recorded successfully.');
      // Optionally hide the form after successful submission
      document.getElementById('gateOutForm').style.display = 'none';
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error while submitting Gate Out: ' + error.message);
  }
});
        // Update Gate-Out
        async function updateGateOut(meetingId) {
          const userConfirmed = confirm("Are you sure you want to gate out?");
          if (!userConfirmed) {
            alert("Gate-out action canceled.");
            return;
          }
          const url = 'https://vms.nuvoco.in/vmsdb/update_gate_out.php';
          try {
            const response = await fetch(url, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ meeting_id: meetingId }),
            });
            const data = await response.json();
            if (data.status === 'success') {
              alert('Gate-out recorded successfully.');
            } else {
              alert(`Error: ${data.message}`);
              console.log(data.message);
            }
          } catch (error) {
            alert(`Error while recording Gate-out: ${error.message}`);
            console.log(error.message);
          }
        }

        // Display Meeting Details
        function displayMeetingDetails(meetingId, gateIn, visitorId) {
          document.getElementById('displayMeetingId').innerText = meetingId || 'N/A';
          document.getElementById('displayGateIn').innerText = gateIn || 'N/A';
          document.getElementById('displayVisitorId').innerText = visitorId || 'N/A';
          document.getElementById('debug').style.display = 'block';
          if (gateIn === '0000-00-00 00:00:00' || gateIn === '0000-00-00 00') {
            document.getElementById('meeting-details').style.display = 'block';
          } else {
            loadGateOutForm(meetingId);
          }
        }

        // Handle Search Form Submission
        document.getElementById('searchForm').addEventListener('submit', async (event) => {
          event.preventDefault();
          const meetingId = document.getElementById('searchMeetingId').value.trim();
          try {
            const response = await fetch(`https://vms.nuvoco.in/vmsdb/get_meeting_details.php?meeting_id=${meetingId}`);
            const result = await response.json();
            if (result.status === 'success') {
              displayMeetingDetails2(result.meeting);
            } else {
              alert('Meeting not found.');
            }
          } catch (error) {
            console.error('Error fetching meeting details:', error);
            alert('Error fetching meeting details. Check console for more info.');
          }
        });

        // Display Meeting Details (Search Result)
        function displayMeetingDetails2(meetingObj) {
  const debugCard = document.getElementById('debug');
  debugCard.style.display = 'block';
  const allMeetingData = document.getElementById('allMeetingData');
  allMeetingData.innerHTML = '';
  
  // Populate meeting details
  for (let key in meetingObj) {
    const value = meetingObj[key];
    if (key === 'meeting_id') {
      document.getElementById('displayMeetingId').innerText = value;
    } else {
      const p = document.createElement('p');
      p.innerHTML = `<strong>${key}:</strong> ${value}`;
      allMeetingData.appendChild(p);
    }
  }
  
  // Remove any existing buttons container
  let buttonsContainer = document.getElementById('buttonsContainer');
  if (buttonsContainer) {
    buttonsContainer.remove();
  }
  
  // Create a container to hold buttons side by side
  buttonsContainer = document.createElement('div');
  buttonsContainer.id = 'buttonsContainer';
  buttonsContainer.style.marginTop = '10px';
  
  const gateInValue = meetingObj.gate_in;
  // If gate_in is the default value, then nothing is recorded yet.
  if (gateInValue === '0000-00-00 00:00:00') {
    // Show the meeting update form so the user can record gate in
    document.getElementById('meeting-details').style.display = 'block';
  } else {
    // Gate in is already recorded – create a Gate Out button
    const gateOutButton = document.createElement('button');
    gateOutButton.id = 'gateOutButton';
    gateOutButton.className = 'btn btn-warning mr-2';
    gateOutButton.innerText = 'Gate Out';
    gateOutButton.addEventListener('click', function () {
      loadGateOutForm(meetingObj.meeting_id);
    });
    buttonsContainer.appendChild(gateOutButton);
    
    // Additionally, if meetingdays equals "M", add the Update Meeting Details button beside Gate Out
    if (meetingObj.meetingDays === "M") {
  const updateDetailsButton = document.createElement('button');
  updateDetailsButton.id = 'updateDetailsButton';
  updateDetailsButton.className = 'btn btn-success';
  updateDetailsButton.innerText = 'Update Meeting Details';
  updateDetailsButton.addEventListener('click', function () {
    // Show the meeting update form
    document.getElementById('meeting-details').style.display = 'block';
    // Remove the buttons container
    const btnContainer = document.getElementById('buttonsContainer');
    if (btnContainer) {
      btnContainer.remove();
    }
  });
  buttonsContainer.appendChild(updateDetailsButton);
}
  }
  
  // Append the buttons container if it contains any buttons
  if (buttonsContainer.childNodes.length > 0) {
    debugCard.appendChild(buttonsContainer);
  }
}

        window.onload = startScanner;

        // Additional event listener for adding gear items (already defined above)
        document.getElementById('addGearBtn').addEventListener('click', function () {
          const gearSection = document.getElementById('gearSection');
          const newGearItem = document.createElement('div');
          newGearItem.className = 'gear-item d-flex align-items-center';
          newGearItem.innerHTML = `
            <select name="gear[]" class="form-control gear-select" required>
              <option value="">Select Gear</option>
              <option value="Helmet">Helmet</option>
              <option value="Mask">Mask</option>
              <option value="custom">Add Custom Gear</option>
            </select>
            <input type="number" name="gear-quantity[]" class="form-control ml-2" min="1" value="1" required style="width: 80px;">
            <select name="returnable[]" class="form-control ml-2" required>
              <option value="Yes" selected>Yes</option>
              <option value="No">No</option>
            </select>
            <button type="button" class="btn btn-danger ml-2 remove-gear-btn">Remove</button>
          `;
          gearSection.appendChild(newGearItem);
          newGearItem.querySelector('.remove-gear-btn').addEventListener('click', function () {
            this.parentElement.remove();
          });
          newGearItem.querySelector('.gear-select').addEventListener('change', handleCustomGear);
        });

        // Remove functionality for existing gear items
        document.querySelectorAll('.remove-gear-btn').forEach(button => {
          button.addEventListener('click', function () {
            this.parentElement.remove();
          });
        });

        // Custom gear input handling
        function handleCustomGear(event) {
          if (event.target.value === 'custom') {
            const customGearName = prompt('Enter the custom gear name:');
            if (customGearName) {
              const newOption = document.createElement('option');
              newOption.value = customGearName;
              newOption.textContent = customGearName;
              event.target.appendChild(newOption);
              event.target.value = customGearName;
              saveCustomGear(customGearName);
            } else {
              event.target.value = '';
            }
          }
        }

        // Save custom gear to the database using AJAX
        function saveCustomGear(gearName) {
          fetch('/save-custom-gear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ gearName })
          })
          .then(response => {
            if (!response.ok) throw new Error('Failed to save gear');
            return response.json();
          })
          .then(data => {
            console.log('Custom gear saved:', data);
          })
          .catch(error => {
            console.error('Error saving custom gear:', error);
          });
        }

        // Add event listeners to existing gear dropdowns for custom gear
        document.querySelectorAll('.gear-select').forEach(select => {
          select.addEventListener('change', handleCustomGear);
        });

        // ID Card addition and custom input handling
        document.getElementById('addIdCardBtn').addEventListener('click', function () {
          const idCardSection = document.getElementById('idCardSection');
          const newIdCardItem = document.createElement('div');
          newIdCardItem.className = 'id-card-item d-flex align-items-center';
          newIdCardItem.innerHTML = `
            <select name="id_card[]" class="form-control id-card-select">
              <option value="">Select ID Card</option>
              <option value="Aadhar Card">Aadhar Card</option>
              <option value="PAN Card">PAN Card</option>
              <option value="Driver License">Driver License</option>
              <option value="Election Card">Election Card</option>
              <option value="custom">Add Custom ID Proof</option>
            </select>
            <input type="text" name="id-number[]" class="form-control ml-2" placeholder="Enter ID Number" style="width: 200px;">
            <button type="button" class="btn btn-danger ml-2 remove-id-btn">Remove</button>
          `;
          idCardSection.appendChild(newIdCardItem);
          newIdCardItem.querySelector('.remove-id-btn').addEventListener('click', function () {
            this.parentElement.remove();
          });
          newIdCardItem.querySelector('.id-card-select').addEventListener('change', handleCustomIdCard);
        });

        // Handle custom ID proof input
        function handleCustomIdCard(event) {
          if (event.target.value === 'custom') {
            const customIdProof = prompt('Enter the custom ID proof name:');
            if (customIdProof) {
              const newOption = document.createElement('option');
              newOption.value = customIdProof;
              newOption.textContent = customIdProof;
              event.target.appendChild(newOption);
              event.target.value = customIdProof;
              saveCustomId(customIdProof);
            } else {
              event.target.value = '';
            }
          }
        }

        // Save custom ID proof to the database
        function saveCustomId(idProofName) {
          fetch('/save-custom-id', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ idProofName })
          })
          .then(response => response.json())
          .then(console.log)
          .catch(console.error);
        }

        // Attach event listeners to existing ID card dropdowns
        document.querySelectorAll('.id-card-select').forEach(select => {
          select.addEventListener('change', handleCustomIdCard);
        });
      </script>
    </div>
  </div>
</div>

<?php include('footer.php'); ?>
