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
        
        while (($row = fgetcsv($handle)) !== false) {
            $account = array_combine($headers, $row);
            $accounts[] = $this->normalizeAccount([
                'account_id' => $account['employee_id'] ?? $account['email'] ?? uniqid(),
                'username' => $account['username'] ?? null,
                'email' => $account['email'] ?? null,
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