<?php
// Admin registration secret.
// Set this to a strong secret string to allow creating admin accounts via the registration form.
// Example: define('ADMIN_REG_SECRET', 'change-me-to-a-strong-secret');
// Leave empty to disable admin self-registration.

if (!defined('ADMIN_REG_SECRET')) {
    define('ADMIN_REG_SECRET', '');
}

?>