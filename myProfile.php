<?php include("header.php");
$getData = mysqli_query($mysqli, "SELECT * FROM tbl_user WHERE id = $userId");
$result = mysqli_fetch_array($getData);
?>

<style>
/* General styling for profile picture */
.az-img-profileSet {
    text-align: center;
    margin: 20px auto;
}

.az-img-profileSet img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #80c18d;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

/* Centered buttons */
.button-container {
    margin-top: 15px;
    text-align: center;
}

.button-container button {
    background-color: #45b85c;
    color: white;
    border: none;
    padding: 8px 16px;
    font-size: 14px;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.button-container button:hover {
    background-color: #45b85c;
}

/* Responsive adjustments for mobile */
@media (max-width: 768px) {
    .az-img-profileSet {
        margin-bottom: 15px;
    }

    .row {
        flex-direction: column;
        align-items: center;
    }

    table {
        width: 90%;
        margin-bottom: 15px;
    }

    .az-img-profileSet img {
        width: 120px;
        height: 120px;
    }

    .button-container {
        margin-top: 10px;
    }
}
</style>

<div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">
    <div class="container">
        <?php include("myProfileLeft.php")?>
        <div class="az-content-body pd-lg-l-40 d-flex flex-column">
            <h2 class="az-content-title">My Profile</h2>
            <div class="row">
                <!-- Profile Picture -->
                <div class="col-lg-5 align-center az-img-profileSet">
                    <img 
  src="https://vms.nuvoco.in/vmsdb/serve_image.php?image=<?= $userId; ?>_profile.webp&v=<?= $version; ?>" 
  alt="Profile Photo" 
/>
                    <div class="button-container">
                        <a href="updateProfilePic.php">
                            <button>Update Profile Picture</button>
                        </a>
                    </div>
                </div>

                <!-- Left Details -->
                <div class="col-lg-3" align="center">
                    <table width="100%" border="0" cellspacing="4" cellpadding="4">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td><?php echo $result['userName']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Company Name:</strong></td>
                            <td><?php echo $result['userCompany']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Designation:</strong></td>
                            <td><?php echo $result['userDesignation']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Gender:</strong></td>
                            <td><?php echo $result['userGender']; ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Right Details -->
                <div class="col-lg-4" align="center">
                    <table width="100%" border="0" cellspacing="4" cellpadding="4">
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?php echo $result['userEmail']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Mobile:</strong></td>
                            <td><?php echo $result['userMobile']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Age:</strong></td>
                            <td><?php echo $result['userAge']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Address:</strong></td>
                            <td><?php echo $result['userAddress']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>ZIP/Postal Code:</strong></td>
                            <td><?php echo $result['userZIPCode']; ?></td>
                        </tr>
                    </table>
                    <div class="button-container">
                        <a href="updatePersonalInfo.php">
                            <button>Update Personal Information</button>
                        </a>
                    </div>
                </div>
            </div>

            <div class="ht-40"></div>
            <?php include("footer.php")?>
        </div><!-- az-content-body -->
    </div><!-- container -->
</div><!-- az-content -->
</body>
</html>
