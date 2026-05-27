<?php
/**
 * EventSnap Cloud - Authentication Handler Class
 */

class Auth {
    /**
     * Registers a new user account
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $role ('owner' or 'crew')
     * @return array ['success' => bool, 'message' => string]
     */
    public static function register(string $name, string $email, string $password, string $role = 'owner'): array {
        $db = getDBConnection();
        
        $name = trim($name);
        $email = trim(strtolower($email));
        
        if (empty($name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required.'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address format.'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
        }
        
        if (!in_array($role, ['owner', 'crew', 'admin'])) {
            $role = 'owner';
        }
        
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email address is already registered.'];
            }
            
            // Hash password using secure bcrypt
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert user
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword, $role]);
            
            $userId = $db->lastInsertId();
            
            // Seed a subscription record (Free Tier default for Owner)
            if ($role === 'owner') {
                $startsAt = date('Y-m-d H:i:s');
                $endsAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                $subStmt = $db->prepare("INSERT INTO subscriptions (user_id, plan_name, status, amount, starts_at, ends_at) VALUES (?, 'Free', 'active', 0.00, ?, ?)");
                $subStmt->execute([$userId, $startsAt, $endsAt]);
            }
            
            return ['success' => true, 'message' => 'Account successfully registered! You can now log in.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Authenticates a user based on email and password
     * @param string $email
     * @param string $password
     * @return array ['success' => bool, 'message' => string]
     */
    public static function login(string $email, string $password): array {
        $db = getDBConnection();
        $email = trim(strtolower($email));
        
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required.'];
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid email or password combination.'];
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            return ['success' => true, 'message' => 'Login successful!'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Logs out the user and destroys the session
     */
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Checks if a user is logged in
     * @return bool
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    /**
     * Requires the user to be logged in, otherwise redirects
     */
    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header("Location: login.php");
            exit;
        }
    }

    /**
     * Requires a specific role or list of roles
     * @param string|array $roles
     */
    public static function requireRole($roles): void {
        self::requireLogin();
        
        $userRole = $_SESSION['user_role'] ?? '';
        $allowed = false;
        
        if (is_array($roles)) {
            $allowed = in_array($userRole, $roles);
        } else {
            $allowed = ($userRole === $roles);
        }
        
        if (!$allowed) {
            // Unauthorized access page
            header("HTTP/1.1 403 Forbidden");
            echo "<h1 style='color:red; text-align:center; margin-top:50px;'>403 Forbidden: Access Denied</h1>";
            echo "<p style='text-align:center;'><a href='dashboard.php'>Go to Dashboard</a></p>";
            exit;
        }
    }

    /**
     * Checks subscription active state for owners
     * @return array ['plan' => string, 'active' => bool]
     */
    public static function getSubscriptionStatus(): array {
        if (!self::isLoggedIn() || $_SESSION['user_role'] !== 'owner') {
            return ['plan' => 'None', 'active' => false];
        }
        
        $db = getDBConnection();
        try {
            $stmt = $db->prepare("SELECT plan_name, ends_at, status FROM subscriptions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $sub = $stmt->fetch();
            
            if (!$sub) {
                return ['plan' => 'Free', 'active' => true]; // Fallback to Free
            }
            
            $isActive = (strtotime($sub['ends_at']) > time() && $sub['status'] === 'active');
            return [
                'plan' => $sub['plan_name'],
                'active' => $isActive,
                'ends_at' => $sub['ends_at']
            ];
        } catch (PDOException $e) {
            return ['plan' => 'Free', 'active' => true];
        }
    }
}
?>
