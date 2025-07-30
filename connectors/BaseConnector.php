<?php
abstract class BaseConnector {
    protected $config;
    protected $sourceId;
    
    public function __construct($sourceId, $config) {
        $this->sourceId = $sourceId;
        $this->config = $config;
    }
    
    abstract public function fetchAccounts();
    abstract public function testConnection();
    
    protected function normalizeAccount($account) {
        // Standardize account data structure
        return [
            'source_id' => $this->sourceId,
            'account_id' => $account['account_id'] ?? null,
            'username' => $account['username'] ?? null,
            'email' => $account['email'] ?? null,
            'additional_data' => json_encode($account['additional_data'] ?? [])
        ];
    }
}