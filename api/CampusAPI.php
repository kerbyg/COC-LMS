<?php
header('Content-Type: application/json');
http_response_code(501);
echo json_encode(['success' => false, 'message' => 'Campus management is not yet implemented.']);
