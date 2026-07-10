<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Personenverwaltung', 'veranstaltungswart'); ?></h1>
    <hr class="wp-header-end">
    
    <?php 
    // Sicherstellen, dass Sortier-Variablen definiert sind (Fallbacks)
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'last_name';
    $order   = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';

    // Erfolgs- und Fehlermeldungen
    if (isset($_GET['status'])): 
        $msg = __('Änderungen gespeichert.', 'veranstaltungswart');
        $type = 'success';
        
        if ($_GET['status'] === 'deleted') {
            $msg = __('Person wurde dauerhaft gelöscht.', 'veranstaltungswart');
        } elseif ($_GET['status'] === 'email_exists') { 
            $msg = __('Fehler: Diese E-Mail-Adresse wird bereits von einer anderen Person verwendet!', 'veranstaltungswart');
            $type = 'error'; 
        } elseif ($_GET['status'] === 'error') {
            $msg = __('Fehler: Bitte geben Sie eine gültige E-Mail-Adresse ein!', 'veranstaltungswart');
            $type = 'error';
        } elseif ($_GET['status'] === 'bulk_success') {
            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
            $msg = sprintf(_n('%s Person wurde erfolgreich aktualisiert/gelöscht.', '%s Personen wurden erfolgreich aktualisiert/gelöscht.', $count, 'veranstaltungswart'), $count);
        } elseif ($_GET['status'] === 'approved') {
            $msg = __('Person wurde erfolgreich freigegeben.', 'veranstaltungswart');
        }
    ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><strong><?php echo esc_html($msg); ?></strong></p>
        </div>
    <?php endif; ?>

    <style>
        .vw-person-container { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
        .vw-person-left { flex: 0 0 350px; min-width: 300px; }
        .vw-person-right { flex: 1; min-width: 450px; }
        .vw-person-actions { display: flex; gap: 5px; justify-content: flex-end; }
        @media (max-width: 782px) {
            .vw-person-left, .vw-person-right { flex: 1 1 100% !important; }
            .vw-table-responsive { overflow-x: auto; display: block; width: 100%; }
        }
    </style>

    <div class="vw-person-container">
        
        <div class="vw-person-left">
            <div class="card" style="padding: 15px; margin: 0;">
                <h2><?php echo $edit_person ? esc_html__('Daten bearbeiten', 'veranstaltungswart') : esc_html__('Neue Person anlegen', 'veranstaltungswart'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="vw_save_person">
                    <input type="hidden" name="person_id" value="<?php echo $edit_person ? (int)$edit_person->id : 0; ?>">
                    <?php wp_nonce_field('vw_person_action', 'vw_person_nonce'); ?>
                    
                    <p><label><?php esc_html_e('Vorname', 'veranstaltungswart'); ?></label><input type="text" name="first_name" class="widefat" value="<?php echo $edit_person ? esc_attr($edit_person->first_name) : ''; ?>" required></p>
                    <p><label><?php esc_html_e('Nachname', 'veranstaltungswart'); ?></label><input type="text" name="last_name" class="widefat" value="<?php echo $edit_person ? esc_attr($edit_person->last_name) : ''; ?>" required></p>
                    <p><label><?php esc_html_e('E-Mail-Adresse', 'veranstaltungswart'); ?></label><input type="email" name="email" class="widefat" value="<?php echo $edit_person ? esc_attr($edit_person->email) : ''; ?>" required></p>
                    
                    <p><label><?php esc_html_e('Vertrauensstatus', 'veranstaltungswart'); ?></label>
                        <select name="trust_status" class="widefat">
                            <option value="eingegangen" <?php selected($edit_person ? $edit_person->trust_status : '', 'eingegangen'); ?>><?php esc_html_e('Prüfung erforderlich', 'veranstaltungswart'); ?></option>
                            <option value="freigegeben" <?php selected($edit_person ? $edit_person->trust_status : 'freigegeben', 'freigegeben'); ?>><?php esc_html_e('Freigegeben (Sofort-Bestätigung)', 'veranstaltungswart'); ?></option>
                        </select>
                    </p>
                    <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                        <?php submit_button($edit_person ? __('Speichern', 'veranstaltungswart') : __('Person anlegen', 'veranstaltungswart'), 'primary', 'submit', false); ?>
                        <?php if ($edit_person): ?> <a href="<?php echo esc_url(admin_url('admin.php?page=vw_persons')); ?>" class="button"><?php esc_html_e('Abbrechen', 'veranstaltungswart'); ?></a> <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card" style="padding: 15px; margin: 20px 0;">
                <h2><?php esc_html_e('Personen importieren (CSV)', 'veranstaltungswart'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="vw_import_persons">
                    <?php wp_nonce_field('vw_import_persons_nonce', 'vw_import_nonce'); ?>
                    <p><input type="file" name="csv_file" accept=".csv" required></p>
                    <p class="description"><?php esc_html_e('Format: Vorname;Nachname;E-Mail', 'veranstaltungswart'); ?></p>
                    <?php submit_button(__('Importieren', 'veranstaltungswart'), 'secondary', 'submit', false); ?>
                </form>
            </div>
        </div>

        <div class="vw-person-right">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirmBulkAction();">
                <input type="hidden" name="action" value="vw_bulk_person_action">
                <?php wp_nonce_field('vw_bulk_person_nonce', '_wpnonce_bulk'); ?>

                <div class="tablenav top"><div class="alignleft actions">
                    <select name="bulk_action" id="bulk-action-selector">
                        <option value="-1"><?php esc_html_e('Aktion wählen', 'veranstaltungswart'); ?></option>
                        <option value="freigegeben"><?php esc_html_e('Als freigegeben markieren', 'veranstaltungswart'); ?></option>
                        <option value="delete"><?php esc_html_e('Löschen', 'veranstaltungswart'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php esc_attr_e('Übernehmen', 'veranstaltungswart'); ?>">
                </div></div>

                <div class="vw-table-responsive">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 5%;" class="check-column"><input type="checkbox" id="vw-select-all"></th>
                                <?php 
                                $cols = [
                                    'last_name'    => __('Nachname', 'veranstaltungswart'),
                                    'first_name'   => __('Vorname', 'veranstaltungswart'),
                                    'email'        => __('E-Mail', 'veranstaltungswart'),
                                    'trust_status' => __('Status', 'veranstaltungswart')
                                ];
                                foreach ($cols as $key => $label) {
                                    $new_order = ($orderby === $key && $order === 'ASC') ? 'DESC' : 'ASC';
                                    $url = admin_url('admin.php?page=vw_persons&orderby=' . $key . '&order=' . $new_order);
                                    echo '<th><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></th>';
                                }
                                ?>
                                <th style="width: 12%; text-align: right;"><?php esc_html_e('Aktion', 'veranstaltungswart'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($persons)): foreach ($persons as $person): ?>
                                <tr>
                                    <th class="check-column"><input type="checkbox" name="person_ids[]" value="<?php echo (int)$person->id; ?>" class="vw-person-checkbox"></th>
                                    <td><strong><?php echo esc_html($person->last_name); ?></strong></td>
                                    <td><?php echo esc_html($person->first_name); ?></td>
                                    <td><a href="mailto:<?php echo esc_attr($person->email); ?>"><?php echo esc_html($person->email); ?></a></td>
                                    <td><?php echo ($person->trust_status === 'freigegeben') ? '<span style="color:#00a32a;">' . esc_html__('Freigegeben', 'veranstaltungswart') . '</span>' : '<span style="color:#ffa500;">' . esc_html__('Prüfung', 'veranstaltungswart') . '</span>'; ?></td>
                                    <td style="text-align: right;">
                                        <div class="vw-person-actions">
                                            <?php if ($person->trust_status !== 'freigegeben'): ?>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=vw_approve_person&id=' . $person->id), 'vw_approve_person_nonce_' . $person->id)); ?>" class="button button-small" style="color: #46b450; border-color: #46b450;"><span class="dashicons dashicons-yes"></span></a>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=vw_persons&edit=' . $person->id)); ?>" class="button button-small"><span class="dashicons dashicons-edit"></span></a>
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=vw_delete_person&id=' . $person->id), 'vw_delete_person_nonce_' . $person->id)); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Person wirklich löschen?', 'veranstaltungswart'); ?>')"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" style="text-align:center; padding: 40px;"><?php esc_html_e('Keine Teilnehmer gefunden.', 'veranstaltungswart'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>