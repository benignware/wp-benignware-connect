<?php

namespace benignware\wpconnect\Admin;

class PluginInstall {
    private $plugin_updates = null;
    private $installed_plugins = null;

    public function __construct() {
        // Add the custom tab
        add_action('install_plugins_tabs', [$this, 'add_custom_tab']);
        // Render content for the custom tab
        add_action('install_plugins_benignware', [$this, 'render_custom_tab_content']);
        // Handle plugins API requests
        add_filter('plugins_api', [$this, 'plugin_api_handler'], 10, 3);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_custom_scripts']);
    }

    /**
     * Enqueue custom JavaScript for handling the "benignware" filter.
     */
    public function enqueue_custom_scripts() {
        $screen = get_current_screen();
        
        if ($screen && $screen->id === 'plugin-install') {

            wp_enqueue_style(
                'benignware-connect-plugin-install',
                plugin_dir_url(__FILE__) . 'plugin-install.css',
                [],
                '1.0.0'
            );
        }
    }

    /**
     * Cache installed plugins.
     */
    public function get_installed_plugins() {
        if ($this->installed_plugins === null) {
            $this->installed_plugins = get_plugins();
        }

        return $this->installed_plugins;
    }

    /**
     * Cache plugin updates.
     */
    public function get_plugin_updates() {
        if ($this->plugin_updates === null) {
            $this->plugin_updates = get_site_transient('update_plugins');
        }

        return $this->plugin_updates;
    }

    public function is_installed($plugin) {
        $installed_plugins = $this->get_installed_plugins();
        return array_key_exists($plugin->slug . '/' . $plugin->slug . '.php', $installed_plugins);
    }

    public function has_update($plugin) {
        $installed_plugins = $this->get_installed_plugins();
        $plugin_updates_response = $this->get_plugin_updates();
        $plugin_updates = isset($plugin_updates_response->response) ? $plugin_updates_response->response : [];

        $plugin_file = $plugin->slug . '/' . $plugin->slug . '.php';

        // Check if the plugin is installed
        $is_installed = array_key_exists($plugin->slug . '/' . $plugin->slug . '.php', $installed_plugins);

        // Check for available updates
        $has_update = $is_installed && isset($plugin_updates[$plugin_file]);

        return $has_update;
    }

    public function add_custom_tab($tabs) {
        $tabs['benignware'] = __('Benignware', 'benignware-connect');
        return $tabs;
    }

    public function render_custom_tab_content() {
        // Ensure this is the correct tab
        $tab = isset($_GET['tab']) ? $_GET['tab'] : '';
        if ($tab !== 'benignware') {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Benignware Plugins', 'benignware-connect'); ?></h1>
            <p><?php _e('Here is a list of available plugins from Benignware:', 'benignware-connect'); ?></p>

            <form id="plugin-filter" method="post">
                <div class="wp-list-table widefat plugin-install">
                    <?php
                    $plugins = $this->fetch_plugins_from_api();

                    

                    if (!empty($plugins)) {
                        foreach ($plugins as $plugin) {
                            // echo '<pre>';
                            // print_r($plugin);
                            // echo '</pre>';
                            // echo '<br>';
                            $this->render_plugin_card($plugin);
                        }
                    } else {
                        echo '<p>' . __('No plugins available.', 'benignware-connect') . '</p>';
                    }
                    ?>
                </div>
            </form>
        </div>
        <?php
    }

    private function generate_install_url($slug) {
        $plugin_slug = sanitize_title($slug);
        return add_query_arg([
            'action' => 'install-plugin',
            'plugin' => $plugin_slug,
            '_wpnonce' => wp_create_nonce('install-plugin_' . $plugin_slug),
        ], admin_url('update.php'));
    }

    private function render_plugin_card($plugin) {
        $install_url = $this->generate_install_url($plugin->slug);

        $installed_plugins = $this->get_installed_plugins();
        $plugin_updates = $this->get_plugin_updates(); 
        
        // Check if the plugin is installed
        $is_installed = $this->is_installed($plugin);

        // Check for available updates
        $has_update = $this->has_update($plugin);
    
        // Set the fallback image (WordPress logo)
        $fallback_icon_url = includes_url('images/w-logo-blue.png');
        // Use the plugin's icon_url if available, otherwise use the fallback image
        $icon_url = !empty($plugin->icon_url) ? esc_url($plugin->icon_url) : esc_url($fallback_icon_url);

        $plugin_file = $plugin->slug . '/' . $plugin->slug . '.php';

        ?>
        <div class="plugin-card plugin-card-<?php echo esc_attr($plugin->slug); ?>">
            <div class="plugin-card-top">
                <div class="name column-name">
                    <h3>
                        <a href="<?php echo esc_url(network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $plugin->slug . '&TB_iframe=true&width=600&height=550')); ?>" class="thickbox open-plugin-details-modal" aria-label="<?php _e('More details', 'benignware-connect'); ?>" data-title="<?php echo esc_attr($plugin->name); ?>">
                            <?php echo esc_html($plugin->name); ?>
                            <img src="<?php echo $icon_url; ?>" class="plugin-icon" alt="">
                        </a>
                    </h3>
                </div>
                <div class="action-links">
                    <ul class="plugin-action-buttons">
                        <li>
                            <?php if ($is_installed): ?>
                                <?php if ($has_update): ?>
                                    <a class="update-now button aria-button-if-js" 
                                        data-plugin="<?php echo esc_attr($plugin_file); ?>" 
                                        data-slug="<?php echo esc_attr($plugin->slug); ?>" 
                                        href="<?php echo esc_url(network_admin_url('update.php?action=upgrade-plugin&plugin=' . $plugin_file . '&_wpnonce=' . wp_create_nonce('upgrade-plugin_' . $plugin_file))); ?>" 
                                        aria-label="<?php printf(__('Update %s now', 'benignware-connect'), esc_html($plugin->name)); ?>" 
                                        role="button">
                                            <?php _e('Update Now', 'benignware-connect'); ?>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="button button-disabled" disabled="disabled"><?php _e('Active', 'benignware-connect'); ?></button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a class="install-now button" data-slug="<?php echo esc_attr($plugin->slug); ?>" href="<?php echo esc_url($install_url); ?>" aria-label="<?php echo esc_attr($plugin->aria_label); ?>" role="button">
                                    <?php _e('Install Now', 'benignware-connect'); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $plugin->slug . '&TB_iframe=true&width=600&height=550')); ?>" class="thickbox open-plugin-details-modal" aria-label="<?php _e('More details', 'benignware-connect'); ?>" data-title="<?php echo esc_attr($plugin->name); ?>">
                                <?php _e('More Details', 'benignware-connect'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="desc column-description">
                    <p><?php echo esc_html($plugin->description); ?></p>
                    <p class="authors"> <cite><?php _e('By', 'benignware-connect'); ?> <a href="<?php echo esc_url($plugin->author_url); ?>"><?php echo esc_html($plugin->author); ?></a></cite></p>
                </div>
            </div>
            <div class="plugin-card-bottom">
                <div class="vers column-rating">
                    <!-- Add rating stars here if available -->
                </div>
                <div class="column-updated">
                    <strong><?php _e('Last Updated:', 'benignware-connect'); ?></strong>
                    <?php echo esc_html($plugin->last_updated); ?>
                </div>
                <div class="column-compatibility">
                    <span class="compatibility-compatible"><strong><?php _e('Compatible', 'benignware-connect'); ?></strong> <?php _e('with your WordPress version', 'benignware-connect'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    
  
  public function plugin_api_handler($false, $action, $args) {

    // Intercept 'query_plugins' action for the 'benignware' slug
    if ($action === 'query_plugins' && isset($args->slug) && $args->slug === 'benignware') {
        // Fetch and return the list of formatted plugins for 'benignware'
        $plugins = $this->fetch_plugins_from_api();

        print_r($plugins);
        exit;
        return (object) [
            'plugins' => $plugins
        ];
    }

    // Intercept 'plugin_information' only for benignware plugins
    if ($action === 'plugin_information' && isset($args->slug)) {
        $plugin_slug = sanitize_title($args->slug);
        $plugins = $this->fetch_plugins_from_api();

       

        // Check if the requested plugin is one of your own
        foreach ($plugins as $plugin) {

            if (sanitize_title($plugin->slug) === $plugin_slug) {
                // Return plugin information for your own plugin
                echo 'PLUGIN FOUND: ' . $plugin->name;

                return (object) [
                    'name' => $plugin->name,
                    'slug' => $plugin->slug,
                    'version' => $plugin->version,
                    'description' => $plugin->description,
                    'homepage' => $plugin->homepage,
                    'download_link' => $plugin->download_link,
                    'author' => $plugin->author,
                    'author_url' => $plugin->author_url,
                    'last_updated' => $plugin->last_updated,
                    'rating' => 4, // Example rating
                    'num_ratings' => 100, // Example number of ratings
                    'icons' => ['default' => $plugin->icon_url], // Plugin icons
                ];
            }
        }

        // If the plugin slug does not match any benignware plugins, return false
        // This allows other plugins to work as expected
        return $false;
    }

    // For any other action, return false to avoid breaking other plugins
    return $false;
}

    private function format_plugin_name($name) {
        // Check if the name starts with 'wp-' and is in hyphen-case
        if (strpos($name, 'wp-') === 0) {
            // Strip 'wp-' prefix
            $name = substr($name, 3);
            // Replace hyphens with spaces and capitalize each word
            $name = ucwords(str_replace('-', ' ', $name));
        }
        return $name;
    }



    private function format_plugin_data($plugin) {
        // Remove 'wp-' prefix from the slug
        $slug = sanitize_title($plugin['name']);
        $slug = str_replace('wp-', '', $slug);

        // Add target filename parameter to the download link
        $download_link = add_query_arg('zipname', $slug, $plugin['download_link']);
    
        return (object) [
            'name' => $this->format_plugin_name($plugin['name']),
            'slug' => $slug,
            'version' => !empty($plugin['version']) ? $plugin['version'] : __('Unknown', 'benignware-connect'),
            'description' => $plugin['description'],
            'homepage' => $plugin['homepage'],
            'download_link' => $download_link,
            'author' => 'Benignware', // Adjust if needed
            'rating' => 0,
            'num_ratings' => 0,
            'last_updated' => isset($plugin['last_updated']) ? $plugin['last_updated'] : date('Y-m-d'),
            'icon_url' => $plugin['icon_url'] ?? '', // Ensure icon URL is included
            'author_url' => $plugin['author_url'] ?? '' // Ensure author URL is included
        ];
    }
    
    private function fetch_plugins_from_api() {
        $api_url = 'https://hub.benignware.com/api/v1/packages/index?filter=wp-&keywords=wordpress-plugin';
        $key = get_option('benignware_license_key'); // Assuming you store the license key in an option

        if (!empty($key)) {
            $api_url = add_query_arg('key', $key, $api_url);
        }

        $response = wp_remote_get($api_url, ['timeout' => 50]);

        if (is_wp_error($response)) {
            error_log('Error fetching plugins from API: ' . $response->get_error_message());
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $plugins = json_decode($body, true);

        // Ensure the data is an array and format it
        if (is_array($plugins)) {
            return array_map([$this, 'format_plugin_data'], $plugins);
        } else {
            error_log('Invalid plugin data received from API');
            return [];
        }
    }
}


// Instantiate the class
new PluginInstall();