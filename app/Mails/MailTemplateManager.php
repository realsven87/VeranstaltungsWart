<?php 
namespace VW\Mails;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modul: E-Mail-Vorlagen-Verwaltung
 * Erstellt einen Post-Type, um E-Mail-Texte im Backend editierbar zu machen.
 * Inklusive Platzhalter-Tipps und Test-Mail-Funktion.
 */
class MailTemplateManager {

    /**
     * Registriert die Hooks für den Custom Post Type und die Admin-Oberfläche.
     */
    public function register() {
        add_action('init', [$this, 'register_template_cpt']);
        
        // Spalten in der Übersicht anpassen
        add_filter('manage_vw_mail_template_posts_columns', [$this, 'add_slug_column']);
        add_action('manage_vw_mail_template_posts_custom_column', [$this, 'render_slug_column'], 10, 2);
        
        // Hilfetext mit Platzhaltern (nur im klassischen Editor sichtbar)
        add_action('edit_form_after_title', [$this, 'render_placeholder_help']);
        
        // Meta-Box für Test-Mail-Versand
        add_action('add_meta_boxes', [$this, 'add_test_mail_metabox']);
        add_action('admin_post_vw_send_test_mail', [$this, 'handle_send_test_mail']);
    }

    /**
     * Registriert den CPT für E-Mail-Vorlagen.
     */
    public function register_template_cpt() {
        $labels = [
            'name'               => __('E-Mail-Vorlagen', 'veranstaltungswart'),
            'singular_name'      => __('E-Mail-Vorlage', 'veranstaltungswart'),
            'add_new'            => __('Neue Vorlage', 'veranstaltungswart'),
            'add_new_item'       => __('Neue E-Mail-Vorlage erstellen', 'veranstaltungswart'),
            'edit_item'          => __('E-Mail-Vorlage bearbeiten', 'veranstaltungswart'),
            'all_items'          => __('E-Mail-Vorlagen', 'veranstaltungswart'),
            'menu_name'          => __('E-Mail-Vorlagen', 'veranstaltungswart')
        ];
        
        $args = [
            'labels'             => $labels,
            'public'             => false, 
            'show_ui'            => true,  
            'show_in_menu'       => 'vw_dashboard',
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title', 'editor'],
            'show_in_rest'       => false, // WICHTIG: Erzwingt klassischen Editor für Hooks & E-Mail-Sauberkeit
        ];
        register_post_type('vw_mail_template', $args);
    }

    /**
     * Zeigt dem Admin direkt unter dem Titel an, welche Platzhalter verfügbar sind.
     */
    public function render_placeholder_help($post) {
        if (!isset($post->post_type) || $post->post_type !== 'vw_mail_template') return;
        
        // Erfolgsmeldung nach Test-Versand
        if (isset($_GET['test_sent'])) {
            echo '<div class="notice notice-success inline"><p><strong>' . esc_html__('Test-Mail wurde versendet!', 'veranstaltungswart') . '</strong> ' . esc_html__('Prüfe dein Postfach.', 'veranstaltungswart') . '</p></div>';
        }
        ?>
        <div class="notice notice-info inline" style="margin: 20px 0 0 0; border-left-color: #72aee6; background: #fff;">
            <p><strong><?php esc_html_e('Verfügbare Platzhalter:', 'veranstaltungswart'); ?></strong><br>
            <code>{vorname}</code>, <code>{nachname}</code>, <code>{email}</code>, <code>{event_name}</code>, <code>{event_datum}</code>, <code>{event_zeit}</code>, <code>{event_adresse}</code>, <code>{event_plaetze}</code>, <code>{storno_link}</code></p>
            
            <p style="font-size: 11px; color: #666; border-top: 1px dotted #ccc; padding-top: 8px;">
                <?php echo wp_kses_post(__('Hinweis: Der <strong>Titel</strong> dieser Vorlage wird automatisch als <strong>E-Mail-Betreff</strong> verwendet.', 'veranstaltungswart')); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Fügt die Meta-Box für den Test-Versand in die Seitenleiste ein.
     */
    public function add_test_mail_metabox() {
        add_meta_box(
            'vw_test_mail_box',
            __('E-Mail testen', 'veranstaltungswart'),
            [$this, 'render_test_mail_box'],
            'vw_mail_template',
            'side',
            'low'
        );
    }

    /**
     * Rendert das Formular in der Test-Mail Meta-Box.
     */
    public function render_test_mail_box($post) {
        if ($post->post_status !== 'publish') {
            echo '<p class="description">' . wp_kses_post(__('Bitte die Vorlage erst <strong>veröffentlichen</strong>, um eine Test-Mail zu senden.', 'veranstaltungswart')) . '</p>';
            return;
        }
        
        $user = wp_get_current_user();
        ?>
        <p style="margin-bottom: 10px;"><?php esc_html_e('Sende diese Vorlage mit Beispieldaten an deine Adresse:', 'veranstaltungswart'); ?></p>
        <code style="display:block; margin-bottom: 15px;"><?php echo esc_html($user->user_email); ?></code>
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=vw_send_test_mail&template_id=' . $post->ID), 'vw_test_mail_nonce')); ?>" 
           class="button button-secondary">
           <?php esc_html_e('Test-Mail jetzt senden', 'veranstaltungswart'); ?>
        </a>
        <?php
    }

    /**
     * Verarbeitet den Klick auf "Test-Mail jetzt senden".
     */
    public function handle_send_test_mail() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Zugriff verweigert.', 'veranstaltungswart'));
        
        $template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
        check_admin_referer('vw_test_mail_nonce');
        
        $post = get_post($template_id);
        if (!$post) wp_die(esc_html__('Vorlage nicht gefunden.', 'veranstaltungswart'));
        
        $user = wp_get_current_user();
        
        // Beispieldaten generieren (ohne absage_grund)
        $test_data = [
            'vorname'      => $user->display_name,
            'nachname'     => __('(Test-Nachname)', 'veranstaltungswart'),
            'email'        => $user->user_email,
            'event_name'   => __('Demo-Veranstaltung am Sonntag', 'veranstaltungswart'),
            'event_datum'  => date_i18n(get_option('date_format')),
            'event_zeit'   => '18:00',
            'event_adresse'=> __('Musterstraße 1, 12345 Stadt', 'veranstaltungswart'),
            'event_plaetze'=> '2',
            'storno_link'  => '<a href="#">' . esc_html__('Anmeldung stornieren', 'veranstaltungswart') . '</a>'
        ];
        
        // Mail rendern
        $mail = self::get_rendered_mail($post->post_name, $test_data);
        
        // Versand als HTML
        wp_mail(
            $user->user_email,
            '[TEST] ' . $mail['subject'],
            $mail['body'],
            ['Content-Type: text/html; charset=UTF-8']
        );
        
        // Zurückleiten mit Parameter
        wp_redirect(get_edit_post_link($template_id, 'url') . '&test_sent=1');
        exit;
    }

    /**
     * Fügt die Spalte für den Slug (Technischer Name) hinzu.
     */
    public function add_slug_column($columns) {
        $new_columns = [];
        foreach($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['slug'] = __('Technischer Name (Slug)', 'veranstaltungswart');
            }
        }
        return $new_columns;
    }

    /**
     * Rendert den Inhalt der Slug-Spalte.
     */
    public function render_slug_column($column, $post_id) {
        if ($column === 'slug') {
            $post = get_post($post_id);
            echo '<code style="background:#f0f0f1; padding:3px 5px; border-radius:3px; color:#c92c2c; font-weight:bold;">' . esc_html($post->post_name) . '</code>';
            echo '<br><small style="color:#666;">(ID: ' . $post_id . ')</small>';
        }
    }

    /**
     * Hilfsmethode: Holt eine Vorlage und ersetzt Platzhalter.
     * Kann statisch aufgerufen werden: MailTemplateManager::get_rendered_mail(...)
     */
    public static function get_rendered_mail($template_slug, $replacements) {
        $args = [
            'name'           => $template_slug,
            'post_type'      => 'vw_mail_template',
            'post_status'    => 'publish',
            'posts_per_page' => 1
        ];
        $posts = get_posts($args);
        $template = $posts ? $posts[0] : null;
        
        if (!$template) {
            return [
                'subject' => '[' . get_bloginfo('name') . '] ' . __('Benachrichtigung', 'veranstaltungswart'),
                /* translators: %s: Slug der fehlenden Vorlage */
                'body'    => sprintf(esc_html__('Systemfehler: E-Mail-Vorlage "%s" wurde nicht gefunden.', 'veranstaltungswart'), esc_html($template_slug))
            ];
        }
        
        $subject = $template->post_title;
        $body    = $template->post_content;
        
        // Platzhalter ersetzen (unterstützt {key} und [key])
        foreach ($replacements as $key => $value) {
            $subject = str_replace(['{' . $key . '}', '[' . $key . ']'], $value, $subject);
            $body    = str_replace(['{' . $key . '}', '[' . $key . ']'], $value, $body);
        }
        
        // Standard WordPress Formatierung (Absätze) + Shortcode-Support
        $body = wpautop(do_shortcode($body));
        
        return [
            'subject' => $subject,
            'body'    => $body
        ];
    }

    /**
     * Erstellt Standard-Vorlagen, falls diese noch nicht existieren.
     * Aufzurufen über den Activation-Hook des Plugins.
     */
    public function create_default_templates() {
        $defaults = [
            [
                'slug'    => 'eingangsbestaetigung',
                'title'   => __('[ {event_name} ] Anmeldung eingegangen', 'veranstaltungswart'),
                'content' => __("Hallo {vorname},\n\nvielen Dank für dein Interesse an unserer Veranstaltung <strong>{event_name}</strong> am {event_datum}!\n\nDeine Anmeldung ist eingegangen.\n\nWir prüfen aktuell die Anmeldungen und senden dir in Kürze eine separate Bestätigung zu.\n\nFalls du doch nicht teilnehmen kannst, nutze bitte diesen Link zur Stornierung:\n{storno_link}\n\nViele Grüße,\ndein VeranstaltungsWart", 'veranstaltungswart')
            ],
            [
                'slug'    => 'anmeldebestaetigung',
                'title'   => __('[ {event_name} ] Anmeldung bestätigt', 'veranstaltungswart'),
                'content' => __("Hallo {vorname},\n\nvielen Dank für deine Anmeldung zu der Veranstaltung <strong>{event_name}</strong> am <strong>{event_datum} um {event_zeit}</strong>.\n\nDeine Anmeldung ist hiermit <strong>bestätigt</strong>!\n\nDie Veranstaltung findet hier statt:<br><strong>{event_adresse}</strong>\n\nSolltest du wider Erwarten doch nicht teilnehmen können, gib deinen Platz bitte rechtzeitig frei, damit andere nachrücken können:\n{storno_link}\n\nWir freuen uns auf dich!\n\nViele Grüße,\ndein VeranstaltungsWart", 'veranstaltungswart')
            ],
            [
                'slug'    => 'ablehnung-info',
                'title'   => __('[ {event_name} ] Anmeldung abgelehnt', 'veranstaltungswart'),
                'content' => __("Hallo {vorname},\n\nleider können wir deine Anmeldung zu der Veranstaltung <strong>{event_name}</strong> am {event_datum} nicht bestätigen.\n\nHoffentlich klappt es beim nächsten Mal!\n\nViele Grüße,\ndein VeranstaltungsWart", 'veranstaltungswart')
            ],
            [
                'slug'    => 'warteliste-info',
                'title'   => __('[ {event_name} ] Anmeldung auf der Warteliste', 'veranstaltungswart'),
                'content' => __("Hallo {vorname},\n\nvielen Dank für dein Interesse an unserer Veranstaltung <strong>{event_name}</strong> am {event_datum}! Aktuell sind alle Plätze belegt, aber du stehst auf der Warteliste.\n\nSobald ein Platz frei wird, informieren wir dich sofort per E-Mail.\n\nFalls du nicht mehr auf der Warteliste bleiben möchtest, kannst du dich hier wieder abmelden:\n{storno_link}\n\nViele Grüße,\ndein VeranstaltungsWart", 'veranstaltungswart')
            ],
            [
                'slug'    => 'freigabe-info',
                'title'   => __('[ {event_name} ] Neue Anmeldung', 'veranstaltungswart'),
                'content' => __("Eine neue Anmeldung für die Veranstaltung <strong>{event_name}</strong> am {event_datum} ist eingegangen und wartet auf Freigabe:\n\nName: <strong>{vorname} {nachname}</strong>\nE-Mail-Adresse: {email}\n\nBitte prüfe die Anmeldung.\n\nViele Grüße,\ndein VeranstaltungsWart", 'veranstaltungswart')
            ]
        ];

        foreach ($defaults as $tpl) {
            $existing = get_posts([
                'name'           => $tpl['slug'],
                'post_type'      => 'vw_mail_template',
                'post_status'    => 'any',
                'posts_per_page' => 1
            ]);
            
            if (empty($existing)) {
                wp_insert_post([
                    'post_title'   => $tpl['title'],
                    'post_name'    => $tpl['slug'],
                    'post_content' => $tpl['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'vw_mail_template'
                ]);
            }
        }
    }
}