<?php include('header.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode/minified/html5-qrcode.min.js"></script>
<style>
  #container {
    display: flex;
    gap: 20px;
  }

  #scanner, #details {
    flex: 1;
  }

  #reader {
    margin: 20px 0;
  }

  .form-group {
    margin-bottom: 15px;
  }

  .form-control {
    padding: 10px;
    margin-top: 5px;
    width: 100%;
    max-width: 400px;
    box-sizing: border-box;
  }

  .btn {
    padding: 10px 20px;
    cursor: pointer;
    border: none;
    border-radius: 5px;
  }

  .btn-primary {
    background-color: #007bff;
    color: white;
  }

  .btn-secondary {
    background-color: #6c757d;
    color: white;
  }

  .btn-danger {
    background-color: #dc3545;
    color: white;
  }

  .btn-danger:hover {
    background-color: #c82333;
  }

  .gear-item {
    margin-bottom: 10px;
  }

  #debug {
    display: none;
  }

  .card {
    background-color: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    font-size: 1.2rem;
    line-height: 1.5;
  }

  .card-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
  }

  .card-row strong {
    color: #007bff;
    flex: 0 0 40%;
  }

  .card-row span {
    flex: 1;
    text-align: left;
    color: #333;
  }

  #meeting-details {
    display: none;
  }
</style>


  <h1>QR Code Scanner with Meeting Details</h1>

  <div id="container">
    <div id="scanner">
      <h2>Scan QR Code</h2>
      <div id="reader" style="width: 500px;"></div>
      <button id="restart-scanner" style="display: none; margin-top: 20px;" class="btn btn-secondary">Restart Scanner</button>
    </div>

    <div id="details">
      <div id="debug" class="card">
        <div class="card-row">
          <strong>Decoded QR Text:</strong>
          <span id="decoded-text">None</span>
        </div>
        <div class="card-row">
          <strong>Extracted Meeting ID:</strong>
          <span id="meeting-id">None</span>
        </div>
      </div>

      <div id="meeting-details">
        <h2>Meeting Details</h2>
        <form id="meetingForm">
          <div class="form-group">
            <label for="vehiclePermit">Vehicle Permit:</label>
            <input type="text" id="vehiclePermit" name="vehicle_permit" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="baggageDetails">Baggage Details:</label>
            <input type="text" id="baggageDetails" name="baggage_details" class="form-control" required>
          </div>
          <div class="form-group">
            <h4>Gear</h4>
            <div id="gearSection">
              <div class="gear-item d-flex align-items-center">
                <select name="gear[]" class="form-control gear-select">
                  <option value="">Select Gear</option>
                  <option value="Helmet">Helmet</option>
                  <option value="Mask">Mask</option>
                </select>
                <input type="number" name="gear-quantity[]" class="form-control ml-2" min="1" value="1" style="width: 70px;">
                <button type="button" class="btn btn-danger ml-2 remove-gear-btn">Remove</button>
              </div>
            </div>
            <button type="button" id="addGearBtn" class="btn btn-secondary mt-2">Add Gear</button>
          </div>
          <button type="submit" class="btn btn-primary">Submit Details</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    let html5QrCode;

    async function startScanner() {
      if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
      }

      html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        async (decodedText) => {
          document.getElementById('decoded-text').innerText = decodedText;
          const { meetingId, gateIn } = extractDetailsFromQR(decodedText);
          document.getElementById('meeting-id').innerText = meetingId || 'Invalid QR Code';
          document.getElementById('debug').style.display = 'block';

          console.log('Extracted Gate In:', gateIn);

          if (meetingId) {
            await html5QrCode.stop();
            document.getElementById('reader').innerHTML = '';
            document.getElementById('restart-scanner').style.display = 'block';

            if (gateIn === '0000-00-00 00') {
              console.log("Gate-in not recorded, displaying meeting form.");
              document.getElementById('meeting-details').style.display = 'block';
            } else if (gateIn) {
              console.log(`Gate-in recorded at ${gateIn}, proceeding to Gate-out.`);
              alert(`Gate-in recorded at ${gateIn}. Proceeding to record Gate-out.`);
              await updateGateOut(meetingId);
            } else {
              console.error('Invalid Gate In data detected.');
              alert('Invalid Gate In data.');
            }
          }
        },
        (errorMessage) => {
          console.error(errorMessage);
        }
      ).catch((err) => {
        console.error("Error starting the scanner: ", err);
      });
    }

    function extractDetailsFromQR(qrData) {
      const lines = qrData.split('\n');
      let meetingId = null;
      let gateIn = null;

      for (const line of lines) {
        if (line.startsWith('Meeting ID:')) {
          meetingId = line.split(':')[1].trim();
        }
        if (line.startsWith('Gate In:')) {
          gateIn = line.split(':')[1].trim();
        }
      }

      return { meetingId, gateIn };
    }

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
          alert('Failed to record Gate-out: ' + data.message);
        }
      } catch (error) {
        alert('Error while recording Gate-out: ' + error.message);
      }
    }

    document.getElementById('restart-scanner').addEventListener('click', () => {
      document.getElementById('restart-scanner').style.display = 'none';
      document.getElementById('meeting-details').style.display = 'none';
      document.getElementById('debug').style.display = 'none';
      startScanner();
    });

    document.getElementById('addGearBtn').addEventListener('click', () => {
      const gearSection = document.getElementById('gearSection');
      const newGearItem = document.createElement('div');
      newGearItem.className = 'gear-item d-flex align-items-center';
      newGearItem.innerHTML = `
        <select name="gear[]" class="form-control gear-select">
          <option value="">Select Gear</option>
          <option value="Helmet">Helmet</option>
          <option value="Mask">Mask</option>
        </select>
        <input type="number" name="gear-quantity[]" class="form-control ml-2" min="1" value="1" style="width: 70px;">
        <button type="button" class="btn btn-danger ml-2 remove-gear-btn">Remove</button>
      `;
      newGearItem.querySelector('.remove-gear-btn').addEventListener('click', () => {
        newGearItem.remove();
      });
      gearSection.appendChild(newGearItem);
    });

    document.getElementById('meetingForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const meetingId = document.getElementById('meeting-id').innerText;
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
          alert('Failed to submit details: ' + result.message);
        }
      } catch (error) {
        alert('Error while submitting details: ' + error.message);
      }
    });

    window.onload = startScanner;
  </script>

