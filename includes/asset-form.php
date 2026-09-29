<?php
/**
 * Shared create/edit form for an asset. Expects:
 *   $formAction, $formSubmitLabel, $formAsset (row or null), $formErrors, $formValues
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

if (!function_exists('dat_asset_form')) {
    function dat_asset_form(array $options)
    {
        $action = $options['action'];
        $submitLabel = $options['submit_label'];
        $asset = $options['asset'] ?? null;
        $errors = $options['errors'] ?? [];
        $values = $options['values'] ?? [];
        $cancelUrl = $options['cancel_url'] ?? dat_url('dashboard/index.php');
        $showStatus = (bool) ($options['show_status'] ?? false);

        $currentType = (int) ($values['type'] ?? ($asset['type'] ?? DAT_ASSET_TYPE_PET));
        $name = (string) ($values['name'] ?? ($asset['name'] ?? ''));
        $description = (string) ($values['description'] ?? ($asset['description'] ?? ''));
        $metadata = $values['metadata'] ?? ($asset !== null ? dat_asset_metadata($asset) : []);
        $status = (int) ($values['status'] ?? ($asset['status'] ?? DAT_ASSET_STATUS_ACTIVE));
        ?>
        <?php if ($errors): ?>
            <div class="flash flash-error" role="alert">
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e($action) ?>" class="form-card" novalidate>
            <?= dat_csrf_field() ?>

            <div class="form-row">
                <label><?= e(t('asset.type', 'Type')) ?></label>
                <?php foreach (dat_asset_type_categories() as $category): ?>
                    <fieldset class="type-group-select">
                        <legend><?= e($category['label']) ?></legend>
                        <div class="type-select">
                            <?php foreach ($category['types'] as $typeId): ?>
                                <?php $type = dat_asset_types()[$typeId] ?? null; if ($type === null) continue; ?>
                                <label class="type-option">
                                    <input type="radio" name="type" value="<?= (int) $typeId ?>"
                                           data-type-key="<?= e($type['key']) ?>"
                                           <?= (int) $typeId === $currentType ? 'checked' : '' ?>>
                                    <span>
                                        <em aria-hidden="true"><?= e($type['emoji']) ?></em>
                                        <?= e($type['label']) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
                <?php if ($asset !== null): ?>
                    <span class="form-hint"><?= e(t('asset.type_hint_edit', 'Changing the type replaces the detail fields of this asset.')) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <label for="name"><?= e(t('asset.name', 'Name')) ?></label>
                <input type="text" id="name" name="name" value="<?= e($name) ?>" required maxlength="120"
                       placeholder="<?= e(t('asset.name_placeholder', 'Lucky')) ?>">
            </div>

            <div class="form-row">
                <label for="description"><?= e(t('asset.description', 'Description')) ?></label>
                <textarea id="description" name="description" maxlength="2000"
                          placeholder="<?= e(t('asset.description_placeholder', 'Anything a finder should know. Published on the tag page.')) ?>"><?= e($description) ?></textarea>
            </div>

            <div class="form-row">
                <label><?= e(t('asset.details', 'Details')) ?></label>
                <span class="form-hint" style="margin-top:0;margin-bottom:12px"><?= e(t('asset.details_hint', 'Fields marked as private are stored for you but never shown on the public page.')) ?></span>
                <?php foreach (dat_asset_types() as $typeId => $type): ?>
                    <div class="meta-group" data-meta-group="<?= e($type['key']) ?>">
                        <div class="meta-grid">
                            <?php foreach ($type['fields'] as $field): ?>
                                <?php
                                $fieldId = 'meta_' . $type['key'] . '_' . $field['key'];
                                $fieldValue = (string) ($metadata[$field['key']] ?? '');
                                ?>
                                <div>
                                    <label for="<?= e($fieldId) ?>">
                                        <?= e($field['label']) ?>
                                        <?php if (empty($field['public'])): ?>
                                            <span class="pill pill-muted" style="margin-left:6px"><?= e(t('asset.private', 'private')) ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" id="<?= e($fieldId) ?>" name="metadata[<?= e($field['key']) ?>]"
                                           value="<?= e($fieldValue) ?>" maxlength="120">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($showStatus): ?>
                <div class="form-row">
                    <label for="status"><?= e(t('asset.status', 'Status')) ?></label>
                    <select id="status" name="status">
                        <?php foreach (dat_owner_selectable_statuses() as $statusId): ?>
                            <?php $meta = dat_asset_status_meta($statusId); ?>
                            <option value="<?= (int) $statusId ?>" <?= $statusId === $status ? 'selected' : '' ?>>
                                <?= e($meta['label']) ?> - <?= e($meta['hint']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php
            $whatsappValue = (string) ($values['contact_whatsapp'] ?? ($asset['contact_whatsapp'] ?? ''));
            ?>
            <div class="form-row">
                <label for="contact_whatsapp"><?= e(t('asset.whatsapp', 'WhatsApp number for finders (optional)')) ?></label>
                <input type="tel" id="contact_whatsapp" name="contact_whatsapp"
                       value="<?= e($whatsappValue) ?>" maxlength="32"
                       placeholder="<?= e(t('asset.whatsapp_placeholder', '+49 170 0000000')) ?>">
                <span class="form-hint"><?= e(t('asset.whatsapp_hint', 'Leave it empty and the tag page only offers the anonymous message. If you enter a number, the page adds a WhatsApp button with a ready-made message. The number is never printed on the page, but anyone who opens that chat will see it in WhatsApp.')) ?></span>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary btn-lg"><?= e($submitLabel) ?></button>
                <a class="btn btn-ghost btn-lg" href="<?= e($cancelUrl) ?>"><?= e(t('form.cancel', 'Cancel')) ?></a>
            </div>
        </form>
        <?php
    }
}
