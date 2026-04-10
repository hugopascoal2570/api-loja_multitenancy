<?php

return [
    'super_admins' => array_filter(explode(',', env('SUPER_ADMIN_EMAILS', ''))),
];
