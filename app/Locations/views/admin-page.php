<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1 class="wp-heading-inline">Veranstaltungsorte</h1>
    <hr class="wp-header-end">

    <?php if (isset($_GET['status'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo $_GET['status'] === 'success' ? 'Ort erfolgreich gespeichert.' : 'Ort wurde gelöscht.'; ?></p>
        </div>
    <?php endif; ?>

    <style>
        /* Responsive Flexbox für das Grid (Formular & Tabelle) */
        .vw-location-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        .vw-location-left {
            flex: 0 0 350px;
            min-width: 300px;
        }
        .vw-location-right {
            flex: 1;
            min-width: 400px;
        }

        /* Styling für die Action-Buttons */
        .vw-location-actions {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .vw-location-actions a {
            text-decoration: none;
        }
        .vw-action-pipe {
            color: #ddd;
            margin: 0 4px;
        }

        /* WordPress Standard-Breakpoint für Mobile Admin-Ansichten */
        @media (max-width: 782px) {
            /* Spalten auf 100% Breite zwingen */
            .vw-location-left, 
            .vw-location-right {
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
            .vw-location-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            .vw-action-pipe {
                display: none; /* Trennstrich auf dem Handy ausblenden */
            }
            .vw-location-actions a {
                display: block;
                background: #f0f0f1;
                border: 1px solid #dcdcde;
                padding: 6px 12px;
                border-radius: 4px;
                text-align: center;
            }
        }
    </style>

    <div class="vw-location-container wp-clearfix">
        
        <div class="vw-location-left">
            <div class="card" style="padding: 15px; margin: 0;">
                <h2 style="margin-top: 0; font-size: 1.3em;"><?php echo $edit_location ? 'Ort bearbeiten' : 'Neuen Ort hinzufügen'; ?></h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="vw_save_location">
                    <input type="hidden" name="location_id" value="<?php echo $edit_location ? $edit_location->id : 0; ?>">
                    <?php wp_nonce_field('vw_location_action', 'vw_location_nonce'); ?>

                    <div style="margin-bottom: 15px;">
                        <label for="name" style="display: block; font-weight: 600; margin-bottom: 5px;">Name des Ortes</label>
                        <input name="name" type="text" id="name" value="<?php echo $edit_location ? esc_attr($edit_location->name) : ''; ?>" class="widefat" placeholder="z.B. Gemeindesaal" required>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label for="address" style="display: block; font-weight: 600; margin-bottom: 5px;">Anschrift / Adresse</label>
                        <textarea name="address" id="address" class="widefat" rows="4" placeholder="Straße, PLZ Ort"><?php echo $edit_location ? esc_textarea($edit_location->address) : ''; ?></textarea>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label for="default_capacity" style="display: block; font-weight: 600; margin-bottom: 5px;">Standardkapazität</label>
                        <input name="default_capacity" type="number" id="default_capacity" value="<?php echo $edit_location ? intval($edit_location->default_capacity) : '25'; ?>" min="0" class="small-text">
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center;">
                        <?php submit_button($edit_location ? 'Speichern' : 'Ort anlegen', 'primary', 'submit', false); ?>
                        <?php if ($edit_location): ?>
                            <a href="<?php echo admin_url('admin.php?page=vw_locations'); ?>" class="button">Abbrechen</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="vw-location-right">
            <div class="vw-table-responsive">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Name</th>
                            <th style="width: 40%;">Adresse</th>
                            <th style="width: 15%; text-align: center;">Kap.</th>
                            <th style="width: 15%;">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($locations)): foreach ($locations as $location): ?>
                            <tr>
                                <td><strong><?php echo esc_html($location->name); ?></strong></td>
                                <td style="color: #646970; font-size: 0.9em;"><?php echo nl2br(esc_html($location->address)); ?></td>
                                <td style="text-align: center;"><?php echo intval($location->default_capacity); ?></td>
                                <td>
                                    <div class="vw-location-actions">
                                        <a href="<?php echo admin_url('admin.php?page=vw_locations&edit=' . $location->id); ?>" title="Bearbeiten">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        <span class="vw-action-pipe">|</span>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=vw_delete_location&id=' . $location->id), 'vw_delete_nonce_' . $location->id); ?>" 
                                           style="color:#d63638;" 
                                           onclick="return confirm('Ort wirklich löschen?')" 
                                           title="Löschen">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" style="padding:30px; text-align: center;">Noch keine Orte hinterlegt.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>