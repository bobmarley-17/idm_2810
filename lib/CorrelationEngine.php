<?php
class CorrelationEngine {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get all correlation rules for a source
     */
    public function getRulesForSource(int $sourceId): array {
        $stmt = $this->db->prepare("
            SELECT id, source_id, priority, match_field, match_type, created_at
            FROM correlation_rules 
            WHERE source_id = :source_id 
            ORDER BY priority
        ");
        $stmt->execute([':source_id' => $sourceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add a new correlation rule
     */
    public function addRule(int $sourceId, string $matchField, string $matchType, int $priority): bool {
        $stmt = $this->db->prepare("
            INSERT INTO correlation_rules 
            (source_id, priority, match_field, match_type)
            VALUES (:source_id, :priority, :match_field, :match_type)
        ");
        
        return $stmt->execute([
            ':source_id' => $sourceId,
            ':priority' => $priority,
            ':match_field' => $matchField,
            ':match_type' => $matchType
        ]);
    }
    
    /**
     * Delete a correlation rule
     */
    public function deleteRule(int $ruleId): bool {
        $stmt = $this->db->prepare("
            DELETE FROM correlation_rules 
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $ruleId]);
    }
    
    /**
     * Correlate accounts with users using defined rules
     */
    public function correlateAccounts(int $sourceId, array $accounts): array {
        $rules = $this->getRulesForSource($sourceId);
        $correlated = [];
        $unmatched = [];
        
        foreach ($accounts as $account) {
            $matched = false;
            
            // Try each rule in priority order
            foreach ($rules as $rule) {
                $fieldValue = $this->extractFieldValue($account, $rule['match_field']);
                
                if ($fieldValue !== null) {
                    $user = $this->findUserByRule($fieldValue, $rule);
                    
                    if ($user) {
                        $correlated[] = [
                            'user_id' => $user['id'],
                            'account' => $account,
                            'matched_by' => [
                                'rule_id' => $rule['id'],
                                'field' => $rule['match_field'],
                                'type' => $rule['match_type'],
                                'value' => $fieldValue
                            ]
                        ];
                        $matched = true;
                        break;
                    }
                }
            }
            
            if (!$matched) {
                $unmatched[] = $account;
            }
        }
        
        return [
            'correlated' => $correlated,
            'unmatched' => $unmatched
        ];
    }
    
    /**
     * Find user based on correlation rule
     */
    private function findUserByRule(string $value, array $rule): ?array {
        switch ($rule['match_type']) {
            case 'exact':
                return $this->findUserByField($rule['match_field'], $value);
            case 'partial':
                return $this->findUserByPartialMatch($rule['match_field'], $value);
            case 'regex':
                return $this->findUserByRegex($rule['match_field'], $value);
            default:
                return null;
        }
    }
    
    /**
     * Find user by exact field match
     */
    private function findUserByField(string $field, string $value): ?array {
        if (!$this->isValidField($field)) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT id FROM users 
            WHERE $field = :value 
            LIMIT 1
        ");
        $stmt->execute([':value' => $value]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find user by partial field match
     */
    private function findUserByPartialMatch(string $field, string $value): ?array {
        if (!$this->isValidField($field)) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT id FROM users 
            WHERE $field LIKE :value 
            LIMIT 1
        ");
        $stmt->execute([':value' => "%$value%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find user by regex field match (MySQL limited support)
     */
    private function findUserByRegex(string $field, string $pattern): ?array {
        if (!$this->isValidField($field)) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM users 
                WHERE $field REGEXP :pattern 
                LIMIT 1
            ");
            $stmt->execute([':pattern' => $pattern]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Regex error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract field value from account data (supports nested fields)
     */
    private function extractFieldValue(array $account, string $field): ?string {
        $keys = explode('.', $field);
        $value = $account;
        
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }
        
        return is_string($value) ? trim($value) : null;
    }
    
    /**
     * Validate field is allowed for correlation
     */
    private function isValidField(string $field): bool {
        $allowedFields = ['email', 'username', 'employee_id'];
        return in_array($field, $allowedFields);
    }
    
    /**
     * Save correlated accounts to database
     */
    public function saveCorrelatedAccounts(int $sourceId, array $correlated): bool {
        $this->db->beginTransaction();
        
        try {
            foreach ($correlated as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_accounts 
                    (user_id, source_id, account_id, username, email, additional_data, matched_by)
                    VALUES (:user_id, :source_id, :account_id, :username, :email, :additional_data, :matched_by)
                    ON DUPLICATE KEY UPDATE
                    username = VALUES(username),
                    email = VALUES(email),
                    additional_data = VALUES(additional_data),
                    matched_by = VALUES(matched_by),
                    updated_at = NOW()
                ");
                
                $stmt->execute([
                    ':user_id' => $item['user_id'],
                    ':source_id' => $sourceId,
                    ':account_id' => $item['account']['username'] ?? $item['account']['email'] ?? uniqid(),
                    ':username' => $item['account']['username'] ?? null,
                    ':email' => $item['account']['email'] ?? null,
                    ':additional_data' => json_encode($item['account']),
                    ':matched_by' => json_encode($item['matched_by'])
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Failed to save correlated accounts: " . $e->getMessage());
            return false;
        }
    }
}