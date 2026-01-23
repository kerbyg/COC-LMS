<?php
/**
 * Setup Groq API Key
 * Run once to save the Groq API key to the database
 */
require_once __DIR__ . '/config/database.php';

// The Groq API key
$apiKey = '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';

try {
    // Save Groq API key to system_settings
    db()->execute(
        "INSERT INTO system_settings (setting_key, setting_value, updated_at)
         VALUES ('groq_api_key', ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
        [$apiKey, $apiKey]
    );

    // Set default AI model
    db()->execute(
        "INSERT INTO system_settings (setting_key, setting_value, updated_at)
         VALUES ('ai_model', 'llama-3.1-8b-instant', NOW())
         ON DUPLICATE KEY UPDATE setting_value = IF(setting_value LIKE 'mistral%' OR setting_value LIKE 'Qwen%' OR setting_value LIKE 'meta-llama/Llama%', 'llama-3.1-8b-instant', setting_value), updated_at = NOW()"
    );

    echo "<h2 style='color: green;'>Groq API Key Configured Successfully!</h2>";
    echo "<p>Your Groq API key has been saved to the database.</p>";
    echo "<p>Default model set to: <strong>Llama 3.1 8B Instant</strong></p>";
    echo "<br>";
    echo "<p><strong>You can now:</strong></p>";
    echo "<ul>";
    echo "<li><a href='pages/instructor/quiz-ai-generate.php'>Use the AI Quiz Generator</a></li>";
    echo "<li><a href='pages/admin/settings.php'>Manage settings in Admin Panel</a></li>";
    echo "</ul>";
    echo "<br>";
    echo "<p style='color: orange;'><strong>Security Note:</strong> Delete this file after running it.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
