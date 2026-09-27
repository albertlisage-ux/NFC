<?php
/**
 * Imprint placeholder. German and EU operators must fill this in with real
 * company details before going live; the required fields are listed so nothing
 * is silently missing.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

dat_page_start([
    'title' => t('imprint.title', 'Imprint') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, follow',
    'unread' => 0,
]);
?>
<main id="main" class="shell">
    <div class="page-head">
        <div>
            <h1><?= e(t('imprint.title', 'Imprint')) ?></h1>
            <p><?= e(t('imprint.subtitle', 'Required details for the operator of this portal.')) ?></p>
        </div>
    </div>

    <div class="notice notice-warn">
        <strong><?= e(t('imprint.todo_title', 'To be completed before launch')) ?></strong>
        <p><?= e(t('imprint.todo_body', 'Replace every placeholder below with the real company details of the operator.')) ?></p>
    </div>

    <div class="panel">
        <h2><?= e(t('imprint.operator', 'Operator')) ?></h2>
        <dl class="detail-list">
            <div><dt><?= e(t('imprint.company', 'Company')) ?></dt><dd><?= e(t('imprint.company_value', 'COMPANY NAME')) ?></dd></div>
            <div><dt><?= e(t('imprint.address', 'Address')) ?></dt><dd><?= e(t('imprint.address_value', 'STREET, POSTCODE, CITY, COUNTRY')) ?></dd></div>
            <div><dt><?= e(t('imprint.represented', 'Represented by')) ?></dt><dd><?= e(t('imprint.represented_value', 'MANAGING DIRECTOR')) ?></dd></div>
            <div><dt><?= e(t('imprint.contact', 'Contact')) ?></dt><dd><?= e(t('imprint.contact_value', 'EMAIL ADDRESS, PHONE NUMBER')) ?></dd></div>
            <div><dt><?= e(t('imprint.register', 'Commercial register')) ?></dt><dd><?= e(t('imprint.register_value', 'REGISTER COURT AND NUMBER')) ?></dd></div>
            <div><dt><?= e(t('imprint.vat', 'VAT ID')) ?></dt><dd><?= e(t('imprint.vat_value', 'VAT IDENTIFICATION NUMBER')) ?></dd></div>
            <div><dt><?= e(t('imprint.responsible', 'Responsible for content')) ?></dt><dd><?= e(t('imprint.responsible_value', 'NAME AND ADDRESS')) ?></dd></div>
        </dl>
    </div>
</main>
<?php
dat_page_end();
