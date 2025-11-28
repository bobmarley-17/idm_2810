<?php
class UserManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createUser($data) {
        $stmt = $this->db->prepare("
            INSERT INTO users (username, employee_id, first_name, last_name, email, status)
            VALUES (:username, :employee_id, :first_name, :last_name, :email, :status)
        ");

        $stmt->execute([
            ':username' => $data['username'],
            ':employee_id' => $data['employee_id'],
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':status' => $data['status'] ?? 'active'
        ]);

        return $this->db->lastInsertId();
    }

    public function addUserAccount($userId, $accountData) {
        $stmt = $this->db->prepare("
            INSERT INTO user_accounts
            (user_id, source_id, account_id, username, email, additional_data)
            VALUES (:user_id, :source_id, :account_id, :username, :email, :additional_data)
            ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            email = VALUES(email),
            additional_data = VALUES(additional_data),
            updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':source_id' => $accountData['source_id'],
            ':account_id' => $accountData['account_id'],
            ':username' => $accountData['username'],
            ':email' => $accountData['email'],
            ':additional_data' => $accountData['additional_data']
        ]);
    }
    public function getUserWithAccounts($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                u.id AS user_id,
                u.employee_id,
                u.first_name,
                u.last_name,
                u.username AS user_username,
                u.email AS user_email,
                u.status AS user_status,
                u.supervisor_email,
    
                ua.id AS ua_row_id,
                ua.user_id AS account_user_id,
                ua.source_id,
                ua.account_id AS source_account_id,
                ua.username AS account_username,
                ua.email AS account_email,
                ua.additional_data,
                ua.status AS account_status,
    
                s.name AS source_name,
                s.type AS source_type,
                s.category AS category,
    
                du.deleted_at AS deletion_date
    
            FROM users u
            LEFT JOIN user_accounts ua ON ua.user_id = u.id
            LEFT JOIN account_sources s ON s.id = ua.source_id
            LEFT JOIN defunct_users du ON du.user_id = ua.user_id AND du.source_id = ua.source_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
    
        $user = null;
        $accounts = [];
    
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($user === null) {
                $user = [
                    'id' => $row['user_id'],
                    'employee_id' => $row['employee_id'] ?? null,
                    'first_name' => $row['first_name'] ?? '',
                    'last_name' => $row['last_name'] ?? '',
                    'username' => $row['user_username'] ?? '',
                    'email' => $row['user_email'] ?? '',
                    'status' => $row['user_status'] ?? '',
                    'supervisor_email' => $row['supervisor_email'] ?? '',
                    'accounts' => []
                ];
            }
    
            // If there's a user_accounts row (ua_row_id not null) then append it
            if (!empty($row['ua_row_id'])) {
                // decode additional_data safely
                $additional = [];
                if (!empty($row['additional_data'])) {
                    $decoded = json_decode($row['additional_data'], true);
                    if (is_array($decoded)) {
                        $additional = $decoded;
                    }
                }
    
                $user['accounts'][] = [
                    'id' => $row['ua_row_id'],
                    'user_id' => $row['account_user_id'] ?? $row['user_id'],
                    'source_id' => $row['source_id'] ?? null,
                    'source_name' => $row['source_name'] ?? null,
                    'source_type' => $row['source_type'] ?? null,
                    'category' => $row['category'] ?? null,
                    'account_id' => $row['source_account_id'] ?? null,
                    'username' => $row['account_username'] ?? null,
                    'email' => $row['account_email'] ?? null,
                    'status' => $row['account_status'] ?? 'active',
                    'additional_data' => $additional,
                    'deletion_date' => $row['deletion_date'] ?? null
                ];
            }
        }
    
        return $user;
    }
}

