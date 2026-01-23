<?php
/**
 * Setup Dean Role in Database
 * This script adds the dean role to the users table
 */

require_once __DIR__ . '/config/database.php';

echo "=== Setting up Dean Role ===\n\n";

try {
    // Step 1: Check current role column definition
    echo "1. Checking current role column...\n";
    $roleColumn = db()->fetchOne("SHOW COLUMNS FROM users LIKE 'role'");
    echo "Current Type: " . $roleColumn['Type'] . "\n\n";

    // Step 2: Update role enum to include dean
    echo "2. Adding 'dean' to role enum...\n";
    db()->execute("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'dean', 'instructor', 'student') NOT NULL DEFAULT 'student'");
    echo "✓ Role enum updated successfully!\n\n";

    // Step 3: Verify the change
    echo "3. Verifying change...\n";
    $roleColumn = db()->fetchOne("SHOW COLUMNS FROM users LIKE 'role'");
    echo "New Type: " . $roleColumn['Type'] . "\n\n";

    // Step 4: Show current role distribution
    echo "4. Current user role distribution:\n";
    $roleCounts = db()->fetchAll("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY role");
    foreach ($roleCounts as $roleCount) {
        echo "   - " . ucfirst($roleCount['role']) . ": " . $roleCount['count'] . " users\n";
    }

    echo "\n=== Setup Complete! ===\n";
    echo "\nYou can now:\n";
    echo "1. Create dean users via the admin panel\n";
    echo "2. Or manually update existing users: UPDATE users SET role='dean' WHERE users_id=X\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
