<?php
require_once 'config/database.php';

class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function registerCouple($data) {
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Generate unique IDs
            $registration_id = "FB-REG-" . strtoupper(uniqid());
            $couple_id = null;
            
            // Hash password
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert into couples table
            $sql = "INSERT INTO couples (
                registration_id, email, password_hash, district, address,
                income_range, marriage_years, health_status, residence_years,
                criminal_record, eligibility_score, payment_confirmation, payment_method
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $registration_id,
                $data['email'],
                $password_hash,
                $data['district'],
                $data['address'],
                $data['income_range'],
                $data['marriage_years'],
                $data['health_status'],
                $data['residence_years'],
                $data['criminal_record'],
                $data['eligibility_score'],
                $data['payment_confirmation'],
                $data['payment_method']
            ]);
            
            $couple_id = $this->conn->lastInsertId();
            
            // Insert partner 1
            $sql = "INSERT INTO partners (
                couple_id, partner_number, full_name, age, occupation,
                id_number, blood_group, medical_conditions
            ) VALUES (?, 1, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $couple_id,
                $data['partner1_full_name'],
                $data['partner1_age'],
                $data['partner1_occupation'],
                $data['partner1_id'],
                $data['partner1_blood_group'],
                $data['partner1_medical_conditions']
            ]);
            
            // Insert partner 2
            $sql = "INSERT INTO partners (
                couple_id, partner_number, full_name, age, occupation,
                id_number, blood_group, medical_conditions
            ) VALUES (?, 2, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $couple_id,
                $data['partner2_full_name'],
                $data['partner2_age'],
                $data['partner2_occupation'],
                $data['partner2_id'],
                $data['partner2_blood_group'],
                $data['partner2_medical_conditions']
            ]);
            
            // Log the registration
            $this->logAction($couple_id, 'REGISTRATION', 'New couple registration completed');
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'registration_id' => $registration_id,
                'couple_id' => $couple_id,
                'message' => 'Registration successful'
            ];
            
        } catch (Exception $e) {
            // Rollback on error
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function login($email, $password) {
        try {
            $sql = "SELECT id, email, password_hash, registration_id, status FROM couples WHERE email = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            if ($user['status'] !== 'approved') {
                return ['success' => false, 'message' => 'Account is not yet approved. Please wait for verification.'];
            }
            
            // Update last login
            $sql = "UPDATE couples SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$user['id']]);
            
            // Log login
            $this->logAction($user['id'], 'LOGIN', 'User logged in');
            
            return [
                'success' => true,
                'user_id' => $user['id'],
                'registration_id' => $user['registration_id'],
                'email' => $user['email']
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }
    
    public function getCoupleProfile($couple_id) {
        try {
            // Get couple info
            $sql = "SELECT c.*, 
                    p1.full_name as partner1_name, p1.age as partner1_age, p1.occupation as partner1_occupation,
                    p2.full_name as partner2_name, p2.age as partner2_age, p2.occupation as partner2_occupation
                    FROM couples c
                    LEFT JOIN partners p1 ON c.id = p1.couple_id AND p1.partner_number = 1
                    LEFT JOIN partners p2 ON c.id = p2.couple_id AND p2.partner_number = 2
                    WHERE c.id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$couple_id]);
            $couple = $stmt->fetch();
            
            if (!$couple) {
                return null;
            }
            
            return $couple;
            
        } catch (Exception $e) {
            error_log("Profile fetch error: " . $e->getMessage());
            return null;
        }
    }
    
    private function logAction($couple_id, $action, $description) {
        try {
            $sql = "INSERT INTO audit_logs (couple_id, action, description, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $couple_id,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }
    
    public function requestPasswordReset($email) {
        try {
            // Check if email exists
            $sql = "SELECT id FROM couples WHERE email = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$email]);
            
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Email not found'];
            }
            
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete old tokens
            $sql = "DELETE FROM password_resets WHERE email = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$email]);
            
            // Insert new token
            $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$email, $token, $expires]);
            
            return [
                'success' => true,
                'token' => $token,
                'expires' => $expires
            ];
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Password reset failed'];
        }
    }
    
    public function resetPassword($token, $new_password) {
        try {
            // Validate token
            $sql = "SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$token]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return ['success' => false, 'message' => 'Invalid or expired token'];
            }
            
            $email = $result['email'];
            
            // Update password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE couples SET password_hash = ? WHERE email = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$password_hash, $email]);
            
            // Delete used token
            $sql = "DELETE FROM password_resets WHERE email = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$email]);
            
            // Get couple ID for logging
            $sql = "SELECT id FROM couples WHERE email = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$email]);
            $couple = $stmt->fetch();
            
            if ($couple) {
                $this->logAction($couple['id'], 'PASSWORD_RESET', 'Password reset completed');
            }
            
            return ['success' => true, 'message' => 'Password reset successful'];
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Password reset failed'];
        }
    }
}