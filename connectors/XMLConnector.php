<?php
require_once 'BaseConnector.php';

class XMLConnector extends BaseConnector {
    private $filePath;
    private $config;
    
    public function __construct($db, $config) {
        parent::__construct($db);
        $this->config = $config;
        $this->filePath = $config['file_path'] ?? '';
    }
    
    public function getAccounts() {
        if (!file_exists($this->filePath)) {
            throw new Exception("XML file not found: " . $this->filePath);
        }

        $xml = simplexml_load_file($this->filePath);
        if ($xml === false) {
            throw new Exception("Failed to parse XML file");
        }

        $accounts = [];
        $userPath = $this->config['xml_user_path'] ?? '//user';
        $mappings = [
            'email' => $this->config['email_field'] ?? 'email',
            'username' => $this->config['username_field'] ?? 'username',
            'employee_id' => $this->config['employee_id_field'] ?? 'employee_id'
        ];

        foreach ($xml->xpath($userPath) as $user) {
            $account = [];
            foreach ($mappings as $key => $xpath) {
                $nodes = $user->xpath($xpath);
                $account[$key] = $nodes ? (string)$nodes[0] : null;
            }
            
            // Add any additional fields configured
            if (!empty($this->config['additional_fields'])) {
                $additional = [];
                foreach ($this->config['additional_fields'] as $field => $xpath) {
                    $nodes = $user->xpath($xpath);
                    $additional[$field] = $nodes ? (string)$nodes[0] : null;
                }
                $account['additional_data'] = json_encode($additional);
            }
            
            if (!empty($account['email']) || !empty($account['username']) || !empty($account['employee_id'])) {
                $accounts[] = $account;
            }
        }
        
        return $accounts;
    }
    
    public static function detectFields($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("XML file not found");
        }

        $xml = simplexml_load_file($filePath);
        if ($xml === false) {
            throw new Exception("Failed to parse XML file");
        }

        // Try to find a sample user element
        $sampleUsers = $xml->xpath('//user');
        if (empty($sampleUsers)) {
            // If no 'user' elements found, try to guess the user container
            foreach ($xml->children() as $child) {
                if ($child->count() > 0) {
                    $sampleUsers = [$child];
                    break;
                }
            }
        }

        if (empty($sampleUsers)) {
            throw new Exception("Could not detect XML structure");
        }

        $fields = [];
        $sampleUser = $sampleUsers[0];
        
        // Recursively get all possible paths
        self::getXMLPaths($sampleUser, '', $fields);
        
        return [
            'success' => true,
            'fields' => array_keys($fields),
            'suggested_mappings' => self::suggestMappings($fields)
        ];
    }
    
    private static function getXMLPaths($element, $currentPath, &$paths) {
        foreach ($element as $key => $child) {
            $newPath = $currentPath ? "$currentPath/$key" : $key;
            if ($child->count() > 0) {
                self::getXMLPaths($child, $newPath, $paths);
            } else {
                $paths[$newPath] = (string)$child;
            }
        }
    }
    
    private static function suggestMappings($fields) {
        $suggestions = [
            'email_field' => null,
            'username_field' => null,
            'employee_id_field' => null
        ];
        
        $patterns = [
            'email_field' => ['email', 'mail', 'e-mail'],
            'username_field' => ['username', 'user', 'login', 'userid'],
            'employee_id_field' => ['employee_id', 'employeeid', 'empid', 'emp_id', 'id']
        ];
        
        foreach ($fields as $path => $value) {
            $lower_path = strtolower($path);
            foreach ($patterns as $field => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($lower_path, $keyword) !== false) {
                        $suggestions[$field] = $path;
                        break 2;
                    }
                }
            }
        }
        
        return $suggestions;
    }
}
