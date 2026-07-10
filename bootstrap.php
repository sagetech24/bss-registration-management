<?php

require_once __DIR__ . '/../wp-load.php';

require_once __DIR__ . '/includes/schema-install.php';
require_once __DIR__ . '/includes/registration-config-service.php';
require_once __DIR__ . '/includes/form-schema-service.php';
require_once __DIR__ . '/includes/pricing-service.php';
require_once __DIR__ . '/includes/event-registration-service.php';
require_once __DIR__ . '/includes/event-registrant-service.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/event-service.php';
require_once __DIR__ . '/includes/registrant-service.php';
require_once __DIR__ . '/includes/registration-service.php';
require_once __DIR__ . '/includes/payment-service.php';
require_once __DIR__ . '/includes/hitpay-sync-service.php';
require_once __DIR__ . '/includes/payment-transactions-service.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/event-presenter.php';
require_once __DIR__ . '/includes/controller.php';
require_once __DIR__ . '/includes/view.php';
require_once __DIR__ . '/includes/legacy-redirect.php';

if (rm_event_registration_tables_exist() === false) {
    rm_install_event_registration_tables();
}

rm_legacy_redirect_bootstrap();
