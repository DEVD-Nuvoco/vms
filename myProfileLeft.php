<div class="az-content-left az-content-left-components">
          <div class="component-item">
            <h5>PROFILE SETTINGS<?php /*?>Profile Settings<?php */?></h5>
            <nav class="nav flex-column">
              <?php if($_SESSION['loginType']=='V'){ ?>
			  <a href="updatePersonalInfo.php" class="nav-link" style="font-size:14px;">Update Personal Info.</a>
			  <?php }?>
              <a href="changePassword.php" class="nav-link" style="font-size:14px;">Change Password</a>
              <a href="updateProfilePic.php" class="nav-link" style="font-size:14px;">Update Profile Pic.</a>
            </nav>
			<!-- Modal -->
            
          </div><!-- component-item -->

        </div><!-- az-content-left -->