<?php include('header.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode/minified/html5-qrcode.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

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
      <style>
        h1 {
          text-align: center;
          margin-bottom: 40px;
          color: #333;
        }

        #container {
          display: flex;
          flex-wrap: wrap;
          gap: 20px;
        }

        #scanner, #details {
          flex: 1;
          background: #fff;
          border-radius: 10px;
          padding: 20px;
          box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        #reader {
          margin: 20px auto;
          max-width: 450px;
          border: 1px dashed #ccc;
          padding: 10px;
          background-color: #f9f9f9;
          border-radius: 10px;
        }

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

        .form-control {
          border-radius: 5px;
          margin-bottom: 15px;
          padding: 12px;
        }

        .card {
          padding: 15px;
          background: #f8f9fa;
          border: none;
          border-radius: 10px;
          margin-bottom: 20px;
        }
      </style>
<div style="display: flex; justify-content: flex-start; align-items: flex-start;">
  <h1 style="text-align: left; margin-left: 0;     font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 1rem;   color: #42bb52;">Issue Gear and Gate Timing</h1>
</div>

      <div id="container">
        <!-- QR Scanner Section -->
        <div id="scanner">
          <h2>Scan QR Code</h2>
          <div id="reader"></div>
          <p id="decodedText" style="margin-top: 20px; font-weight: bold;"></p>
          <button id="restart-scanner" class="btn btn-secondary mt-3" style="display: none;">Restart Scanner</button>
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
            <p><strong>Gate In:</strong> <span id="displayGateIn">N/A</span></p>
            <p><strong>Visitor ID:</strong> <span id="displayVisitorId">N/A</span></p>
          </div>

          <!-- Form Section -->
          <div id="meeting-details" class="card" style="display: none;">
            <form id="meetingForm">
              <label for="vehiclePermit">Vehicle Permit:</label>
              <input type="text" id="vehiclePermit" name="vehicle_permit" class="form-control" required placeholder="Enter vehicle permit number">

              <label for="baggageDetails">Baggage Details:</label>
              <input type="text" id="baggageDetails" name="baggage_details" class="form-control" required placeholder="Enter baggage details">

              <!-- Gear Section -->
              <h4>Gear</h4>
              <div id="gearSection">
                <div class="gear-item d-flex align-items-center">
                  <select name="gear[]" class="form-control gear-select">
                    <option value="">Select Gear</option>
                    <option value="Helmet">Helmet</option>
                    <option value="Mask">Mask</option>
                  </select>
                  <input type="number" name="gear-quantity[]" class="form-control ml-2" min="1" value="1" style="width: 80px;">
                  <button type="button" class="btn btn-danger ml-2 remove-gear-btn">Remove</button>
                </div>
              </div>
              <button type="button" id="addGearBtn" class="btn btn-secondary mt-3">Add Gear</button>

              <button type="submit" class="btn btn-primary mt-3">Submit Details</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  let html5QrCode;

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

        // Call displayMeetingDetails with the extracted data
        displayMeetingDetails(meetingId, gateIn, visitorId);
      },
      (errorMessage) => console.error(errorMessage)
    ).catch((err) => console.error("Error starting scanner:", err));
  }

  // Extract Details from QR
  function extractDetailsFromQR(qrData) {
    const lines = qrData.split('\n');
    let meetingId = null;
    let gateIn = null;
    let visitorId = null;

    for (const line of lines) {
      if (line.startsWith('Meeting ID:')) meetingId = line.split(':')[1].trim();
      if (line.startsWith('Gate In:')) gateIn = line.split(':')[1].trim();
      if (line.startsWith('Visitor ID:')) visitorId = line.split(':')[1].trim();
    }

    return { meetingId, gateIn, visitorId };
  }

  // Update Gate-Out
  async function updateGateOut(meetingId) {
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
      }
    } catch (error) {
      alert(`Error while recording Gate-out: ${error.message}`);
    }
  }

  // Display Meeting Details
  function displayMeetingDetails(meetingId, gateIn, visitorId) {
    document.getElementById('displayMeetingId').innerText = meetingId || 'N/A';
    document.getElementById('displayGateIn').innerText = gateIn || 'N/A';
    document.getElementById('displayVisitorId').innerText = visitorId || 'N/A';
    document.getElementById('debug').style.display = 'block';

    // Check gateIn value
    if (gateIn === '0000-00-00 00:00:00' || gateIn === '0000-00-00 00') {
      document.getElementById('meeting-details').style.display = 'block';
    } else {
      updateGateOut(meetingId); // Call gate-out update if gateIn is valid
    }
  }

  // Handle Search Form Submission
  document.getElementById('searchForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const meetingId = document.getElementById('searchMeetingId').value.trim();

    const response = await fetch(`https://vms.nuvoco.in/vmsdb/get_meeting_details.php?meeting_id=${meetingId}`);
    const result = await response.json();

    if (result.status === 'success') {
      const { meeting_id, gate_in, visitor_id } = result.meeting;

      // Call displayMeetingDetails with the searched data
      displayMeetingDetails(meeting_id, gate_in, visitor_id);
    } else {
      alert('Meeting not found.');
    }
  });

  window.onload = startScanner;
</script>

<?php include('footer.php'); ?>
