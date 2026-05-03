<?php if (!defined('ABSPATH')) exit; ?>
<?php 
// Vorbereitung für die visuelle Nachrücker-Hilfe
$max_cap = intval($stats['max_capacity']);
$already_confirmed = intval($stats['confirmed_count']);
$theoretical_free_slots = ($max_cap > 0) ? ($max_cap - $already_confirmed) : 9999;

// Die aktuelle URL sicher kodieren, damit wir nach der Aktion genau hierher zurückkehren
$current_url = urlencode(wp_unslash($_SERVER['REQUEST_URI']));
?>
<style>
    /* Styling für die Statistik-Box (Responsive) */
    .vw-registration-stats {
        display: flex;
        flex-wrap: wrap; /* Lässt die Boxen auf dem Handy umbrechen */
        gap: 15px;
        margin-bottom: 20px;
        background: #f6f7f7;
        padding: 15px;
        border-radius: 4px;
        border: 1px solid #ccd0d4;
        font-size: 13px;
    }
    .vw-registration-stats > div {
        flex: 1 1 auto;
        min-width: 140px; /* Verhindert, dass es zu sehr gequetscht wird */
    }
    /* Styling für die Action-Buttons */
    .vw-actions-container {
        display: flex;
        gap: 5px;
        justify-content: flex-end;
    }
    
    /* WordPress Standard-Breakpoint für Mobile Admin-Ansichten */
    @media (max-width: 782px) {
        .vw-actions-container {
            flex-direction: column; /* Buttons untereinander */
            align-items: flex-end;
        }
        .vw-actions-container .button {
            width: 100%; /* Buttons füllen die Zelle aus für leichteres Tippen */
            text-align: center;
            margin-bottom: 2px;
        }
        .vw-table-responsive {
            overflow-x: auto; /* Erlaubt horizontales Wischen bei zu langen Texten */
            display: block;
            width: 100%;
        }
    }
</style>

<div class="vw-registration-stats">
    <div><strong><?php esc_html_e('Gesamt-Kapazität:', 'veranstaltungswart'); ?></strong>
        <?php echo $max_cap > 0 ? $max_cap : esc_html__('Unbegrenzt', 'veranstaltungswart'); ?></div>
        
    <div><strong><?php esc_html_e('Bestätigte Teilnehmer:', 'veranstaltungswart'); ?></strong> <span
            style="color: #46b450; font-weight: bold;"><?php echo $already_confirmed; ?></span></div>
            
    <div><strong><?php esc_html_e('Auf Warteliste:', 'veranstaltungswart'); ?></strong> <span
            style="color: #dba617; font-weight: bold;"><?php echo isset($stats['waitlist']) ? intval($stats['waitlist']) : 0; ?></span></div>
            
    <div><strong><?php esc_html_e('Verfügbare Plätze:', 'veranstaltungswart'); ?></strong>
        <span style="font-weight: bold;">
            <?php echo $max_cap > 0 ? max(0, $theoretical_free_slots) : '&infin;'; ?>
        </span>
    </div>
</div>

<div class="vw-table-responsive">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 25%;"><?php esc_html_e('Name', 'veranstaltungswart'); ?></th>
                <th style="width: 25%;"><?php esc_html_e('E-Mail', 'veranstaltungswart'); ?></th>
                <th style="width: 10%; text-align: center;"><?php esc_html_e('Plätze', 'veranstaltungswart'); ?></th>
                <th style="width: 15%;"><?php esc_html_e('Status', 'veranstaltungswart'); ?></th>
                <th style="width: 25%; text-align: right;"><?php esc_html_e('Aktionen', 'veranstaltungswart'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($registrations)):
                foreach ($registrations as $reg):
                    $rid = isset($reg->id) ? $reg->id : (isset($reg->reg_id) ? $reg->reg_id : 0);
                    $eid = isset($reg->event_post_id) ? $reg->event_post_id : 0;
                    $seats = isset($reg->seats_total) ? intval($reg->seats_total) : 1;
                    
                    // Prüfen, ob dieser Teilnehmer "nachrücken" könnte (Stornierte natürlich nicht!)
                    $can_move_up = false;
                    if ($reg->status !== 'bestätigt' && $reg->status !== 'abgelehnt' && $reg->status !== 'storniert' && $max_cap > 0) {
                        if ($seats <= $theoretical_free_slots) {
                            $can_move_up = true;
                            $theoretical_free_slots -= $seats;
                        }
                    }
                    $row_style = $can_move_up ? 'background-color: #edfaef !important;' : '';
                    
                    // Link-Aufbau inkl. _wp_http_referer für die kugelsichere Rückleitung
                    $base_url = admin_url('admin-post.php?action=vw_update_reg_status&id=' . $rid . '&event_id=' . $eid . '&_wp_http_referer=' . $current_url);
                    ?>
                    <tr style="<?php echo $row_style; ?>">
                        <td>
                            <strong><?php echo esc_html($reg->last_name . ', ' . $reg->first_name); ?></strong>
                            
                            <?php 
                            if (isset($reg->trust_status) && $reg->trust_status === 'freigegeben'): ?>
                               <span class="dashicons dashicons-shield-alt"
                                     style="color: #00a32a; vertical-align: middle;"
                                     title="<?php esc_attr_e('Global bestätigte Person (Vertrauenswürdig)', 'veranstaltungswart'); ?>"></span>
                            <?php endif; ?>
                            
                            <?php if ($can_move_up): ?>
                                <span class="dashicons dashicons-info"
                                       style="color: #46b450; font-size: 17px; width: 17px; height: 17px; vertical-align: text-bottom; margin-left: 4px; cursor: help;"
                                       title="<?php esc_attr_e('Platz frei! Dieser Teilnehmer kann jetzt bestätigt werden.', 'veranstaltungswart'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        
                        <td><a href="mailto:<?php echo esc_attr($reg->email); ?>"><?php echo esc_html($reg->email); ?></a></td>
                        
                        <td style="text-align: center;">
                            <span style="background: #eee; padding: 2px 8px; border-radius: 10px; font-weight: 600; border: 1px solid #ddd;">
                                <?php echo $seats; ?>
                            </span>
                        </td>
                        
                        <td>
                            <?php
                            // Bessere Farblogik für die verschiedenen Stati
                            if ($reg->status === 'bestätigt') {
                                $status_color = '#46b450'; // Grün
                            } elseif ($reg->status === 'abgelehnt') {
                                $status_color = '#d63638'; // Rot
                            } elseif ($reg->status === 'storniert') {
                                $status_color = '#646970'; // Grau
                            } else {
                                $status_color = '#dba617'; // Orange (Eingegangen / Warteliste)
                            }
                            ?>
                            <span style="color: <?php echo $status_color; ?>; font-weight: 600;">
                                <?php 
                                // Die Statuswerte kommen aus der Datenbank, idealerweise mappen wir sie hier für die Übersetzung.
                                // Ansonsten ist die einfache Ausgabe mit esc_html in Ordnung, wenn die Datenbankwerte ohnehin deutsch sind.
                                // Für ein offizielles Repo ist es besser, bekannte Stati zu mappen:
                                $status_labels = [
                                    'bestätigt'  => __('Bestätigt', 'veranstaltungswart'),
                                    'abgelehnt'  => __('Abgelehnt', 'veranstaltungswart'),
                                    'storniert'  => __('Storniert', 'veranstaltungswart'),
                                    'eingegangen'=> __('Eingegangen', 'veranstaltungswart'),
                                    'warteliste' => __('Warteliste', 'veranstaltungswart')
                                ];
                                $display_status = isset($status_labels[$reg->status]) ? $status_labels[$reg->status] : ucfirst($reg->status);
                                echo esc_html($display_status); 
                                ?>
                            </span>
                        </td>
                        
                        <td style="text-align: right;">
                            <div class="vw-actions-container">
                                <?php if ($reg->status !== 'bestätigt' && $reg->status !== 'storniert'): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url($base_url . '&new_status=bestätigt', 'vw_reg_action_' . $rid)); ?>"
                                        class="button button-small <?php echo $can_move_up ? 'button-primary' : ''; ?>" title="<?php esc_attr_e('Jetzt bestätigen', 'veranstaltungswart'); ?>">
                                        <span class="dashicons dashicons-yes" style="vertical-align: middle;"></span>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($reg->status !== 'abgelehnt' && $reg->status !== 'storniert'): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url($base_url . '&new_status=abgelehnt', 'vw_reg_action_' . $rid)); ?>"
                                        class="button button-small" title="<?php esc_attr_e('Ablehnen', 'veranstaltungswart'); ?>"
                                        onclick="return confirm('<?php esc_attr_e('Teilnehmer ablehnen?', 'veranstaltungswart'); ?>')">
                                        <span class="dashicons dashicons-no-alt" style="color: #dba617; vertical-align: middle;"></span>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=vw_delete_registration&id=' . $rid . '&event_id=' . $eid . '&_wp_http_referer=' . $current_url), 'vw_delete_reg_nonce_' . $rid)); ?>"
                                    class="button button-small" onclick="return confirm('<?php esc_attr_e('Anmeldung endgültig löschen?', 'veranstaltungswart'); ?>')"
                                    title="<?php esc_attr_e('Löschen', 'veranstaltungswart'); ?>">
                                    <span class="dashicons dashicons-trash" style="color:#d63638; vertical-align: middle;"></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 30px; color: #646970;">
                        <?php esc_html_e('Noch keine Anmeldungen für dieses Event vorhanden.', 'veranstaltungswart'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<p class="description" style="margin-top: 15px;">
    <strong><?php esc_html_e('Status-Info:', 'veranstaltungswart'); ?></strong> 
    <?php esc_html_e('"Eingegangen" sind neue Anmeldungen.', 'veranstaltungswart'); ?> 
    
    <span style="background: #edfaef; padding: 2px 5px; border-radius: 3px; color: #2e7d32; border: 1px solid #c3e6cb; margin: 0 5px;">
        <?php esc_html_e('Grün markierte Zeilen', 'veranstaltungswart'); ?>
    </span> 
    
    <?php esc_html_e('haben aktuell Platz im Event. Das', 'veranstaltungswart'); ?> 
    <span class="dashicons dashicons-shield-alt" style="font-size: 14px; color: #00a32a;"></span> 
    <?php esc_html_e('Icon zeigt global vertrauenswürdige Personen.', 'veranstaltungswart'); ?>
</p>