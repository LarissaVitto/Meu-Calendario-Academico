<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
requireMethods(['GET']);
jsonResponse(200, ['ok' => true, 'usuario' => authenticatedStudent()]);
