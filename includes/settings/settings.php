<?php

namespace benignware\wpconnect\Admin;

class Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page() {
        add_options_page(
            'Benignware Connect Settings',
            'Benignware',
            'manage_options',
            'benignware-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('benignware_settings_group', 'benignware_license_key');
        add_settings_section('benignware_settings_section', 'License Key Settings', null, 'benignware-settings');
        add_settings_field('benignware_license_key', 'License Key', [$this, 'license_key_field'], 'benignware-settings', 'benignware_settings_section');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Benignware Connect Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('benignware_settings_group');
                do_settings_sections('benignware-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function license_key_field() {
        $license_key = get_option('benignware_license_key', '');
        ?>
        <input type="text" name="benignware_license_key" value="<?php echo esc_attr($license_key); ?>" />
        <p class="description">Enter your license key here. This will be used for connecting to our update server.</p>
        <?php
    }
}


new Settings();