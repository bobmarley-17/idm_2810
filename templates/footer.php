</div> <!-- Close main container -->

<footer class="mt-5 mb-3 text-muted">
    <div class="row">
        <div class="col-md-6">
            Identity Management Tool &copy; <?= date('Y') ?>
        </div>
        <div class="col-md-6 text-end">
            v1.0.0
            <?php 
            try {
                require_once(__DIR__ . '/../config/database.php');
                $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $lastSync = $db->query("SELECT MAX(last_sync) as last_sync FROM account_sources")->fetch(PDO::FETCH_ASSOC);
                if ($lastSync && isset($lastSync['last_sync'])) {
                    echo " | Last sync: " . ($lastSync['last_sync'] ? date('M j, Y H:i', strtotime($lastSync['last_sync'])) : 'Never');
                } else {
                    echo " | Last sync: Never";
                }
            } catch (Exception $e) {
                error_log("Error getting last sync time: " . $e->getMessage());
                echo " | Last sync: N/A";
            }
            ?>
        </div>
    </div>
</footer><script>
// Initialize Bootstrap tooltips and popovers
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});
</script>
</body>
</html>
            
<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script>
$(document).ready(function() {
    // Initialize all Bootstrap tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Tab switching functionality
    $('a[data-bs-toggle="tab"]').on('click', function(e) {
        e.preventDefault();
        $(this).tab('show');
    });
    
    // Source type configuration switcher
    $('#source-type').change(function() {
        $('.source-config').hide();
        const selectedConfig = $(this).val().toLowerCase() + '-config';
        $('#' + selectedConfig).show();
    });
    
    // Initialize the first config if needed
    if ($('#source-type').val()) {
        $('#source-type').trigger('change');
    }
	// Initialize tabs
	var tabElms = [].slice.call(document.querySelectorAll('a[data-bs-toggle="tab"]'));
	tabElms.forEach(function(tabEl) {
		new bootstrap.Tab(tabEl);
	});
	
	// Source type switcher
	document.getElementById('source-type').addEventListener('change', function() {
		document.querySelectorAll('.source-config').forEach(el => {
			el.style.display = 'none';
		});
		const configEl = document.getElementById(this.value.toLowerCase() + '-config');
		if (configEl) configEl.style.display = 'block';
	});
	});
</script>

</body>
</html>