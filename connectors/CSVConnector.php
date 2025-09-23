<?php
require_once 'BaseConnector.php';

class CSVConnector extends BaseConnector {
    public function fetchAccounts() {
        $csvFile = $this->config['file_path'] ?? '';
        if (!file_exists($csvFile)) {
            throw new Exception("CSV file not found");
        }

        $accounts = [];
        $handle = fopen($csvFile, 'r');
        $headers = fgetcsv($handle);

        // Load field mapping from DB config
        $mapping = $this->config['field_mapping'] ?? [];

        // Default logical fields
        $employeeIdField = $mapping['employee_id'] ?? 'employee_id';
        $emailField      = $mapping['email'] ?? 'email';
        $usernameField   = $mapping['username'] ?? 'username';

        while (($row = fgetcsv($handle)) !== false) {
            // Pair headers with row values
            $account = array_combine($headers, $row);

            $accounts[] = $this->normalizeAccount([
                'account_id'      => $account[$employeeIdField] ?? $account[$emailField] ?? uniqid(),
                'username'        => $account[$usernameField] ?? null,
                'email'           => $account[$emailField] ?? null,
                'additional_data' => $account
            ]);
        }

        fclose($handle);
        return $accounts;
    }

    public function testConnection() {
        return file_exists($this->config['file_path'] ?? '');
    }
}

