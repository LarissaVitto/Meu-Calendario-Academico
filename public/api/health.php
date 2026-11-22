<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
requireMethods(['GET']);
try {
    db()->query('SELECT 1')->fetchColumn();
    jsonResponse(200, ['ok' => true, 'status' => 'API e PostgreSQL conectados']);
} catch (Throwable $error) {
    serviceError($error);
}
