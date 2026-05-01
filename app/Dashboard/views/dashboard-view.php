<?php if (!defined('ABSPATH')) exit; ?>

<?php 
// Die aktuelle URL sicher kodieren, damit wir nach der Aktion genau hierher zurückkehren
$current_url = urlencode(wp_unslash($_SERVER['REQUEST_URI']));
?>

<div class="wrap">
    <h1 style="margin-bottom: 25px;">VeranstaltungsWart Dashboard</h1>
    
    <?php 
    // Erfolgsmeldung nach Aktionen anzeigen
    if (isset($_GET['status_updated'])): ?>
        <div class="updated notice is-dismissible">
            <p><strong>Aktion erfolgreich!</strong> Die Anmeldung wurde aktualisiert und die entsprechende E-Mail versendet.</p>
        </div>
    <?php endif; ?>

    <style>
        /* Responsive Flexbox für die Statistik-Karten */
        .vw-dashboard-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 35px;
            flex-wrap: wrap; /* Wichtig für Mobile, lässt die Karten umbrechen */
        }
        .vw-stat-card {
            flex: 1;
            min-width: 250px; /* Verhindert zu starkes Quetschen */
            background: #ffffff; 
            padding: 25px; 
            border: 1px solid #ccd0d4; 
            border-radius: 2px; 
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        /* Action-Buttons Styling (Konsistent mit Event-Übersicht) */
        .vw-dashboard-actions {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }

        /* WordPress Standard-Breakpoint für Mobile Admin-Ansichten */
        @media (max-width: 782px) {
            .vw-dashboard-stats {
                flex-direction: column; /* Karten untereinander */
            }
            .vw-table-responsive {
                overflow-x: auto; /* Erlaubt horizontales Wischen der Tabelle */
                display: block;
                width: 100%;
            }
            .vw-dashboard-actions {
                flex-direction: column; /* Buttons untereinander */
                align-items: stretch;
            }
            .vw-dashboard-actions .button {
                width: 100%; /* Buttons füllen die Zelle aus für leichteres Tippen */
                text-align: center;
                margin-bottom: 4px;
            }
        }
    </style>

    <div class="vw-dashboard-stats">
        <div class="vw-stat-card" style="border-left: 5px solid #ffa500;">
            <h2 style="margin: 0; font-size: 14px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">⏳ Offene Anmeldungen</h2>
            <p style="font-size: 32px; font-weight: bold; margin: 12px 0; color: #2c3338;"><?php echo number_format_i18n($total_pending_count); ?></p>
            <small style="color: #646970;">Warten auf deine Freigabe</small>
        </div>

        <div class="vw-stat-card" style="border-left: 5px solid #2271b1;">
            <h2 style="margin: 0; font-size: 14px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">📅 Aktive Veranstaltungen</h2>
            <p style="font-size: 32px; font-weight: bold; margin: 12px 0; color: #2c3338;"><?php echo number_format_i18n($active_events_count); ?></p>
            <small style="color: #646970;">Zukünftige veröffentlichte Events</small>
        </div>
    </div>

    <h2 style="font-size: 1.4em; margin: 30px 0 15px;">Aktuelle Warteschlange (Eingegangen)</h2>
    
    <div class="vw-table-responsive">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 15%;">Eingang am</th>
                    <th style="width: 25%;">Veranstaltung</th>
                    <th style="width: 25%;">Teilnehmer</th>
                    <th style="width: 10%; text-align: center;">Plätze</th>
                    <th style="width: 25%; text-align: right;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pending)): ?>
                    <?php foreach ($pending as $reg): 
                        // Link-Aufbau inkl. event_id und sicherem Referer
                        $base_url = admin_url('admin-post.php?action=vw_update_reg_status&id=' . $reg->id . '&event_id=' . $reg->event_post_id . '&_wp_http_referer=' . $current_url);
                    ?>
                        <tr>
                            <td class="column-date">
                                <strong><?php echo date_i18n('d. M Y', strtotime($reg->registered_at)); ?></strong><br>
                                <small><?php echo date_i18n('H:i', strtotime($reg->registered_at)); ?> Uhr</small>
                            </td>
                            <td>
                                <span class="dashicons dashicons-calendar-alt" style="font-size: 16px; color: #646970;"></span> 
                                <strong>
                                    <a href="<?php echo get_edit_post_link($reg->event_post_id); ?>" 
                                       title="Event bearbeiten" 
                                       style="text-decoration: none; color: #2271b1;">
                                        <?php echo esc_html($reg->post_title); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="background: #f0f0f1; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                        <span class="dashicons dashicons-admin-users" style="color: #2271b1;"></span>
                                    </div>
                                    <div>
                                        <strong><?php echo esc_html($reg->first_name . ' ' . $reg->last_name); ?></strong><br>
                                        <a href="mailto:<?php echo esc_attr($reg->email); ?>" style="text-decoration: none;"><?php echo esc_html($reg->email); ?></a>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" style="background: #eee; padding: 2px 8px; border-radius: 10px; font-weight: 600; border: 1px solid #ddd;">
                                    <?php echo intval($reg->seats_total); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="vw-dashboard-actions">
                                    <a href="<?php echo esc_url(wp_nonce_url($base_url . '&new_status=bestätigt', 'vw_reg_action_' . $reg->id)); ?>" 
                                       class="button button-small" 
                                       title="Jetzt bestätigen">
                                        <span class="dashicons dashicons-yes" style="color: #46b450; vertical-align: middle;"></span>
                                    </a>
                                    
                                    <a href="<?php echo esc_url(wp_nonce_url($base_url . '&new_status=abgelehnt', 'vw_reg_action_' . $reg->id)); ?>" 
                                       class="button button-small" 
                                       title="Ablehnen" 
                                       onclick="return confirm('Möchtest du diese Anmeldung wirklich ablehnen? Der Teilnehmer erhält eine E-Mail.')">
                                        <span class="dashicons dashicons-no-alt" style="color: #dba617; vertical-align: middle;"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 40px; text-align: center; color: #646970; background: #fff;">
                            <span class="dashicons dashicons-smiley" style="font-size: 40px; width: 40px; height: 40px; display: block; margin: 0 auto 10px;"></span>
                            <p style="font-size: 1.1em;">Keine offenen Anmeldungen vorhanden. Alles erledigt! 🎉</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 20px;">
        <p class="description">Hinweis: Bestätigte Anmeldungen verschwinden aus dieser Ansicht und sind in der Personen-Verwaltung oder unter dem jeweiligen Event einsehbar.</p>
    </div>
</div>