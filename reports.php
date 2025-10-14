<?php
// reports.php - Report selection menu
include 'templates/header.php'; // Include your site's header and nav here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Reports Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="container mt-4">
    <h2>Reports</h2>
    <div class="row">
        <!-- Source Report Card -->
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body d-flex flex-column align-items-start">
                    <h5 class="card-title">Source Report</h5>
                    <p class="card-text">
                        Generate or export accounts filtered by source, with status options and CSV export functionality.
                    </p>
                    <a href="reports_source.php" class="btn btn-primary mt-auto">Open Source Report</a>
                </div>
            </div>
        </div>

        <!-- Master Identity Report Card -->
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body d-flex flex-column align-items-start">
                    <h5 class="card-title">Master Identity Report</h5>
                    <p class="card-text">
                        Export a CSV file listing all user identities, accounts, and data sources.
                    </p>
                    <a href="master_report_form.php" class="btn btn-success mt-auto">Export Master Report (CSV)</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include 'templates/footer.php'; // Include your site's footer here
?>
</body>
</html>

