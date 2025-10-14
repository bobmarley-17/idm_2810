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
	    SELECT u.*,  -- u.username now included as it is a column on users table
		   ua.id as account_id, ua.user_id as account_user_id, ua.source_id, ua.account_id as source_account_id,
		   ua.username as account_username, ua.email as account_email, ua.additional_data, ua.status as account_status,
                   s.name as source_name, s.type as source_type
            FROM users u
            LEFT JOIN user_accounts ua ON ua.user_id = u.id
            LEFT JOIN account_sources s ON s.id = ua.source_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        
        $user = null;
        $accounts = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($user === null) {
                $user = [
                    'id' => $row['id'],
                    'employee_id' => $row['employee_id'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
		    'username' => $row['username'],
                    'email' => $row['email'],
                    'status' => $row['status'],
                    'user_id' => $row['account_user_id'],
                    'supervisor_email' => $row['supervisor_email'],
                    'accounts' => []
                ];
            }
            
            if ($row['account_id']) {
                $user['accounts'][] = [
                    'id' => $row['account_id'],
                    'user_id' => $row['account_user_id'],
                    'source_id' => $row['source_id'],
                    'source_name' => $row['source_name'],
                    'source_type' => $row['source_type'],
                    'account_id' => $row['source_account_id'],
                    'username' => $row['account_username'],
                    'email' => $row['account_email'],
                    'status' => $row['account_status'] ?? 'active',
		    'additional_data' => $row['additional_data'] ? json_decode($row['additional_data'], true) : []

                ];
            }
        }
        
        return $user;
    }
}
