<?php
/**
 * Setup AI API Key
 * Run once to save the Hugging Face API key to the database
 */
require_once __DIR__ . '/config/database.php';

// The API key provided by the user
$apiKey = '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';

try {
    // Save API key to system_settings
    db()->execute(
        "INSERT INTO system_settings (setting_key, setting_value, updated_at)
         VALUES ('huggingface_api_key', ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
        [$apiKey, $apiKey]
    );

    // Also set default AI model
    db()->execute(
        "INSERT INTO system_settings (setting_key, setting_value, updated_at)
         VALUES ('ai_model', 'mistralai/Mistral-7B-Instruct-v0.2', NOW())
         ON DUPLICATE KEY UPDATE setting_value = setting_value" // Don't overwrite if exists
    );

    echo "<h2>AI Settings Configured Successfully!</h2>";
    echo "<p style='color: green;'>&#10003; Hugging Face API Key has been saved to the database.</p>";
    echo "<p style='color: green;'>&#10003; Default AI Model (Mistral 7B) has been configured.</p>";
    echo "<br>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>Go to <a href='pages/instructor/quiz-ai-generate.php'>AI Quiz Generator</a> to create AI-generated quizzes</li>";
    echo "<li>Admins can manage the API key in <a href='pages/admin/settings.php'>System Settings</a></li>";
    echo "</ul>";
    echo "<br>";
    echo "<p style='color: orange;'><strong>Security Note:</strong> For production, delete this setup file after running it.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
