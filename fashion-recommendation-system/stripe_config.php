<?php
// Place your Stripe keys here or set environment variables.
// For testing, use Stripe test keys. Example:
// define('STRIPE_SECRET', 'sk_test_xxx');
// define('STRIPE_PUBLISHABLE', 'pk_test_xxx');

define('STRIPE_SECRET', getenv('STRIPE_SECRET') ?: '');
define('STRIPE_PUBLISHABLE', getenv('STRIPE_PUBLISHABLE') ?: '');

?>
