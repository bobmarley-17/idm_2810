<?php
// alumni_report_form.php

require_once 'bootstrap.php';
require_once 'auth_check.php';

$pageTitle = "Alumni Access Exception Report";
include 'templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Alumni Access Exception Report</h2>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="card">
        <div class="card-header">
            Generate Report
        </div>
        <div class="card-body">
            <p class="card-text">
                This report will find all currently <strong>active</strong> accounts for users who are present in the provided Alumni List. It helps identify accounts that may need to be deprovisioned.
            </p>
            <hr>
            
            <form action="alumni_report_generate.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_input(); ?>
                
                <div class="row g-3">
                    <!-- Date Range Selection -->
                    <div class="col-md-4">
                        <label for="start_date" class="form-label"><strong>Start Date</strong> (for account activity)</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label"><strong>End Date</strong> (for account activity)</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <!-- Alumni List File Upload -->
                    <div class="col-md-4">
                        <label for="alumni_file" class="form-label"><strong>Alumni List (CSV)</strong></label>
                        <input class="form-control" type="file" id="alumni_file" name="alumni_file" accept=".csv" required>
                        <div class="form-text">A single-column CSV file containing alumni email addresses.</div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-cogs"></i> Generate and Download Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
