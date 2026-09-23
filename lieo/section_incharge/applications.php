<?php
/** Section Incharge role discontinued — N-1 creates applications directly now. */
require_once __DIR__ . '/../config.php';
lieo_require_role(['section_incharge']);
http_response_code(403);
die('This role has been discontinued. N-1 now creates applications directly. Contact Admin.');
