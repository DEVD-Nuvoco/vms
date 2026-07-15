<div class="az-content-left az-content-left-components">
          <div class="component-item">
            <label>Previous Meetings</label>
            <nav class="nav flex-column">
              <a href="#" data-toggle="modal" data-target="#exampleModal" class="nav-link">Meeting No: 11587</a>
              <a href="#" class="nav-link">Meeting No: 11365</a>
              <a href="#" class="nav-link">Meeting No: 11201</a>
            </nav>
			<!-- Modal -->
				<?php /*?><div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="false">
				  <div class="modal-dialog" role="document">
					<div class="modal-content">
					  <div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">Print Gatepass</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						  <span aria-hidden="true">&times;</span>
						</button>
					  </div>
					  <div class="modal-body">
					<table width="100%" border="0" cellspacing="5" cellpadding="4">
					  <tr>
						<td colspan="2" align="left"><img src="images/nuvoco-ori.png" width="110"  /></td>
						<td align="right"><img src="QRCode/1.png" width="90" /></td>
					  </tr>
					
					  <tr>
						<td width="33%"><strong>Visitor Name:</strong><br />Rakesh Swami</td>
						<td width="33%"><strong>Meet To:</strong><br />Nitin Jain</td>
						<td width="34%"><strong>Meeting Timming:</strong><br />Oct 10, 2023 12:30 PM</td>
					  </tr>
					  <tr>
						<td><strong>Location:</strong><br />Mumbai-HO</td>
						<td><strong>Meeting Status:</strong><br />Approve</td>
						<td><strong>Approve Timming:</strong><br />Oct 10, 2023 12:35 PM</td>
					  </tr>
					  <tr>
						<td>&nbsp;</td>
						<td rowspan="2">&nbsp;</td>
						<td>&nbsp;</td>
					  </tr>
					  <tr>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
					  </tr>
					  <tr>
						<td width="33%"><strong>Safety:</strong><br />9460086241</td>
						<td width="33%"><strong>Fire:</strong><br />9460086241</td>
						<td width="34%"><strong>Hospital</strong><br />9460086241</td>
					  </tr>
					</table>

					  </div>
					  <div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
						<button type="button" class="btn btn-primary">Print</button>
					  </div>
					</div>
				  </div>
				</div><?php */?>
            <?php /*?><label>Recent Photos</label>
			<table width="100%" border="0" cellspacing="0" cellpadding="1">
			<?php $p=1; echo '<tr>';
			//$files = scandir();  
			$files = glob('image' . "/*");
			for($i=0;$i<count($files);$i++){
				 echo '<td><td width="33.33%"><img src="'.$files[$i].'" width="54" ></td></td>';
				if($p % 3 == 0) {
					echo '</tr><tr>';
				}?>
			<?php $p++;}?>		  
			</table><?php */?>

            <?php /*?><label>Charts</label>
            <nav class="nav flex-column">
              <a href="chart-chartjs.html" class="nav-link">ChartJS</a>
            </nav>

            <label>Tables</label>
            <nav class="nav flex-column">
              <a href="table-basic.html" class="nav-link">Basic Tables</a>
            </nav><?php */?>
          </div><!-- component-item -->

        </div><!-- az-content-left -->