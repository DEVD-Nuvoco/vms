<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Approval</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f4f4f9;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
        }
        .content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .photo-container {
            flex: 1 1 150px;
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .photo-container img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .details {
            flex: 2 1 400px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .details p {
            margin: 0;
            line-height: 1.6;
        }
        .form-section {
            margin-top: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
        }
        select, input {
            width: calc(100% - 20px);
            padding: 8px;
            margin-bottom: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .custom-location-container {
            display: none;
            margin-bottom: 10px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
        }
        .approve {
            background-color: #28a745;
            color: white;
        }
        .approve:hover {
            background-color: #218838;
        }
        .disapprove {
            background-color: #dc3545;
            color: white;
        }
        .disapprove:hover {
            background-color: #c82333;
        }
        .forward-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        #searchMeetTo {
            width: calc(100% - 20px);
            padding: 8px;
            font-size: 14px;
        }
        .search-results {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #fff;
            display: none;
            position: absolute;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .search-results li {
            padding: 8px;
            list-style: none;
            cursor: pointer;
        }
        .search-results li:hover {
            background-color: #f1f1f1;
        }
        .tick-mark {
            font-size: 50px;
            color: #28a745;
            text-align: center;
            margin-top: 20px;
            animation: pop-in 0.5s ease-in-out;
        }
        @keyframes pop-in {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#meeting-location').change(function () {
                if ($(this).val() === 'Custom') {
                    $('.custom-location-container').slideDown();
                } else {
                    $('.custom-location-container').slideUp();
                }
            });

            $('#save-custom-location').click(function () {
                const customLocation = $('#custom-location').val();
                if (customLocation.trim() !== '') {
                    $('#meeting-location').append(`<option value="${customLocation}" selected>${customLocation}</option>`);
                    $('.custom-location-container').slideUp();
                } else {
                    alert('Please enter a valid location.');
                }
            });

            $('#searchMeetTo').on('input', function () {
                const searchValue = $(this).val();
                if (searchValue.trim() !== '') {
                    $.ajax({
                        url: 'https://vms.nuvoco.in/vmsdb/search_employee.php',
                        type: 'GET',
                        data: { searchIndex: searchValue },
                        success: function (response) {
                            const employees = JSON.parse(response);
                            if (employees.length > 0) {
                                let listItems = '';
                                employees.forEach(function (employee) {
                                    listItems += `<li>${employee.empName} (${employee.empLocation})</li>`;
                                });
                                $('.search-results').html(listItems).show();
                            } else {
                                $('.search-results').hide();
                            }
                        },
                        error: function () {
                            $('.search-results').html('<li>Error retrieving data.</li>').show();
                        }
                    });
                } else {
                    $('.search-results').hide();
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h2>Meeting Approval</h2>
        <div class="content">
            <div class="photo-container">
                <img src="https://vms.nuvoco.in/vmsdb/faces/<?php echo $userId; ?>_profile.webp" alt="Profile Picture">
            </div>
            <div class="details">
                <p><strong>Location:</strong> <?php echo htmlspecialchars($location); ?></p>
                <p><strong>Meeting With:</strong> <?php echo htmlspecialchars($person); ?></p>
                <p><strong>Visit Type:</strong> <?php echo htmlspecialchars($visitType); ?></p>
                <p><strong>Purpose:</strong> <?php echo htmlspecialchars($purpose); ?></p>
                <p><strong>Start Time:</strong> <?php echo htmlspecialchars($startTime); ?></p>
                <p><strong>End Time:</strong> <?php echo htmlspecialchars($endTime); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($status); ?></p>
            </div>
        </div>

        <div class="form-section">
            <label for="meeting-location">Where would you meet?</label>
            <select id="meeting-location" name="meeting_location">
                <option value="Plant">Plant</option>
                <option value="Office">Office</option>
                <option value="Warehouse">Warehouse</option>
                <option value="Custom">Custom Location</option>
            </select>
            <div class="custom-location-container">
                <input type="text" id="custom-location" placeholder="Enter custom location">
                <button id="save-custom-location">Save</button>
            </div>

            <div class="forward-container">
                <label for="searchMeetTo">Forward this meeting to:</label>
                <input type="text" id="searchMeetTo" placeholder="Search for user">
                <ul class="search-results"></ul>
            </div>
        </div>

        <?php if ($showAnimation): ?>
            <div class="tick-mark">✔</div>
            <h3>Meeting has been <?php echo htmlspecialchars($status); ?> successfully.</h3>
        <?php else: ?>
            <form method="POST">
                <button type="submit" name="action" value="approve" class="approve">Approve</button>
                <button type="submit" name="action" value="disapprove" class="disapprove">Disapprove</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
