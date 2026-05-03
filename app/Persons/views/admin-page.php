<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Personenverwaltung', 'veranstaltungswart'); ?></h1>
    <hr class="wp-header-end">
    
    <?php 
    // Erfolgs- und Fehlermeldungen anzeigen
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
            // sprintf für dynamische Werte bei Übersetzungen verwenden
            $msg = sprintf(_n('%s Person wurde erfolgreich aktualisiert/gelöscht.', '%s Personen wurden erfolgreich aktualisiert/gelöscht.', $count, 'veranstaltungswart'), $count);
        }
    ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><strong><?php echo esc_html($msg); ?></strong></p>
        </div>
    <?php endif; ?>

    <style>
        /* Responsive Flexbox für das Grid (Formular & Tabelle) */
        .vw-person-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        .vw-person-left {
            flex: 0 0 350px;
            min-width: 300px;
        }
        .vw-person-right {
            flex: 1;
            min-width: 450px;
        }
        
        /* Konsistentes Styling für die Action-Buttons */
        .vw-person-actions {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }

        /* WordPress Standard-Breakpoint für Mobile Admin-Ansichten */
        @media (max-width: 782px) {
            /* Spalten auf 100% Breite zwingen */
            .vw-person-left, 
            .vw-person-right {
                flex: 1 1 100% !important;
                min-width: 100% !important;
            }
            
            /* Tabelle horizontal scrollbar machen */
            .vw-table-responsive {
                overflow-x: auto;
                display: block;
                width: 100%;
            }

            /* Buttons untereinander und als klickbare Fläche darstellen */
            .vw-person-actions {
                flex-direction: column;
                align-items: flex-end;
            }
            .vw-person-actions .button {
                width: 100%;
                text-align: center;
                margin-bottom: 2px;
            }
        }
    </style>

    <div class="vw-person-container">
        
        <!-- LINKE SPALTE: FORMULAR -->
        <div class="vw-person-left">
            <div class="card" style="padding: 15px; margin: 0;">
                <h2 style="margin-top:0; font-size: 1.3em;">
                    <?php echo $edit_person ? esc_html__('Daten bearbeiten', 'veranstaltungswart') : esc_html__('Neue Person anlegen', 'veranstaltungswart'); ?>
                </h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="vw_save_person">
                    <input type="hidden" name="person_id" value="<?php echo $edit_person ? (int)$edit_person->id : 0; ?>">
                    <?php wp_nonce_field('vw_person_action', 'vw_person_nonce'); ?>
                    
                    <p>
                        <label style="font-weight:600; display: block; margin-bottom: 5px;"><?php esc_html_e('Vorname', 'veranstaltungswart'); ?></label>
                        <input type="text" name="first_name" class="widefat" value="<?php echo $edit_person ? esc_attr($edit_person->first_name) : ''; ?>" required>
                    </p>
                    <p>
                        <label style="font-weight:600; display: block; margin-bottom: 5px;"><?php esc_html_e('Nachname', 'veranstaltungswart'); ?></label>
                        <input type="text" name="last_name" class="widefat" value="<?php echo $edit_person ? esc_attr($edit_person->last_name) : ''; ?>" required>
                    </p>
                    <p>
                        <label style="font-weight:600; display: block; margin-bottom: 5px;"><?php esc_html_e('E-Mail-Adresse', 'veranstaltungswart'); ?></label>
                        <input type="email" name="email" class="widefat" value="<?php echo $edit_person ? esc_attr($edit_person->email) : ''; ?>" required>
                    </p>
                    
                    <p>
                        <label style="font-weight:600; display: block; margin-bottom: 5px;"><?php esc_html_e('Vertrauensstatus', 'veranstaltungswart'); ?></label>
                        <select name="trust_status" class="widefat">
                            <option value="eingegangen" <?php selected($edit_person ? $edit_person->trust_status : '', 'eingegangen'); ?>>
                                <?php esc_html_e('Prüfung erforderlich', 'veranstaltungswart'); ?>
                            </option>
                            <option value="freigegeben" <?php selected($edit_person ? $edit_person->trust_status : 'freigegeben', 'freigegeben'); ?>>
                                <?php esc_html_e('Freigegeben (Sofort-Bestätigung)', 'veranstaltungswart'); ?>
                            </option>
                        </select>
                        <?php if (!$edit_person): ?>
                            <small class="description"><?php esc_html_e('Neue manuelle Einträge sind standardmäßig freigegeben.', 'veranstaltungswart'); ?></small>
                        <?php endif; ?>
                    </p>

                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; display: flex; gap: 10px; align-items: center;">
                        <?php submit_button($edit_person ? __('Speichern', 'veranstaltungswart') : __('Person anlegen', 'veranstaltungswart'), 'primary', 'submit', false); ?>
                        <?php if ($edit_person): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vw_persons')); ?>" class="button"><?php esc_html_e('Abbrechen', 'veranstaltungswart'); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- RECHTE SPALTE: TABELLE -->
        <div class="vw-person-right">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirmBulkAction();">
                <input type="hidden" name="action" value="vw_bulk_person_action">
                <?php wp_nonce_field('vw_bulk_person_nonce', '_wpnonce_bulk'); ?>

                <div class="tablenav top" style="margin-bottom: 10px;">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action" id="bulk-action-selector">
                            <option value="-1"><?php esc_html_e('Aktion wählen', 'veranstaltungswart'); ?></option>
                            <option value="freigegeben"><?php esc_html_e('Als freigegeben markieren', 'veranstaltungswart'); ?></option>
                            <option value="eingegangen"><?php esc_html_e('Auf "Prüfung" zurücksetzen', 'veranstaltungswart'); ?></option>
                            <option value="delete"><?php esc_html_e('Löschen', 'veranstaltungswart'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php esc_attr_e('Übernehmen', 'veranstaltungswart'); ?>">
                    </div>
                </div>

                <div class="vw-table-responsive">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 5%;" class="check-column">
                                    <input type="checkbox" id="vw-select-all">
                                </th>
                                <th style="width: 20%;"><?php esc_html_e('Nachname', 'veranstaltungswart'); ?></th>
                                <th style="width: 20%;"><?php esc_html_e('Vorname', 'veranstaltungswart'); ?></th>
                                <th style="width: 25%;"><?php esc_html_e('E-Mail', 'veranstaltungswart'); ?></th>
                                <th style="width: 18%;"><?php esc_html_e('Status', 'veranstaltungswart'); ?></th>
                                <th style="width: 12%; text-align: right;"><?php esc_html_e('Aktion', 'veranstaltungswart'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($persons)): foreach ($persons as $person): ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="person_ids[]" value="<?php echo (int)$person->id; ?>" class="vw-person-checkbox">
                                    </th>
                                    <td><strong><?php echo esc_html($person->last_name); ?></strong></td>
                                    <td><?php echo esc_html($person->first_name); ?></td>
                                    <td><a href="mailto:<?php echo esc_attr($person->email); ?>"><?php echo esc_html($person->email); ?></a></td>
                                    <td>
                                        <?php if ($person->trust_status === 'freigegeben'): ?>
                                            <span class="dashicons dashicons-shield-alt" style="color: #00a32a; vertical-align: middle;" title="<?php esc_attr_e('Freigegeben', 'veranstaltungswart'); ?>"></span>
                                            <small style="color: #00a32a;"><?php esc_html_e('Freigegeben', 'veranstaltungswart'); ?></small>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-warning" style="color: #ffa500; vertical-align: middle;" title="<?php esc_attr_e('Wartet auf Prüfung', 'veranstaltungswart'); ?>"></span>
                                            <small style="color: #ffa500;"><?php esc_html_e('Prüfung', 'veranstaltungswart'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="vw-person-actions">
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=vw_persons&edit=' . $person->id)); ?>" 
                                                class="button button-small" title="<?php esc_attr_e('Bearbeiten', 'veranstaltungswart'); ?>">
                                                <span class="dashicons dashicons-edit" style="vertical-align: middle;"></span>
                                            </a>
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=vw_delete_person&id=' . $person->id), 'vw_delete_person_nonce_' . $person->id)); ?>" 
                                                class="button button-small" 
                                                onclick="return confirm('<?php esc_attr_e('Person wirklich löschen?', 'veranstaltungswart'); ?>')"
                                                title="<?php esc_attr_e('Löschen', 'veranstaltungswart'); ?>">
                                                <span class="dashicons dashicons-trash" style="color:#d63638; vertical-align: middle;"></span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" style="text-align:center; padding: 40px; color: #646970;"><?php esc_html_e('Keine Teilnehmer gefunden.', 'veranstaltungswart'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // JS für den "Alle auswählen" Button
    document.getElementById('vw-select-all').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.vw-person-checkbox');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = this.checked;
        }
    });

    // Sicherheitsabfrage beim Löschen mehrerer Einträge
    function confirmBulkAction() {
        var action = document.getElementById('bulk-action-selector').value;
        if (action === '-1') {
            alert('<?php echo esc_js(__('Bitte wähle zuerst eine Aktion aus dem Dropdown-Menü.', 'veranstaltungswart')); ?>');
            return false;
        }
        if (action === 'delete') {
            return confirm('<?php echo esc_js(__('Bist du sicher, dass du alle markierten Personen endgültig löschen möchtest? (Achtung: Verbundene Anmeldungen werden ebenfalls gelöscht!)', 'veranstaltungswart')); ?>');
        }
        return true;
    }
</script>