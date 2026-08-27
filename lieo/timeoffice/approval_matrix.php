<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['timeoffice']);
define('LIEO_MATRIX_PAGE', 1);
global $LIEO_TO_MATRIX_STEPS;
$lieoMatrixStepKeys = $LIEO_TO_MATRIX_STEPS;
$lieoMatrixLockPlant = true;
require __DIR__ . '/../admin/approval_matrix.php';
