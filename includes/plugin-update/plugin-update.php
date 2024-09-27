<?php

namespace benignware\wpconnect\Admin;

class PluginUpdateManager {

    private $license_key;
    private $hub_url;

    public function __construct() {
        // Set the API URL for checking updates
        $this->hub_url = 'https://hub.benignware.com';
        // Get the license key from the options table
        $this->license_key = get_option('benignware_license_key', '');

        // Hook to check plugin updates
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_plugin_updates']);
    }

    /**
     * Check for plugin updates.
     */
    public function check_plugin_updates($transient) {
        // Get all installed plugins
        $plugins = get_plugins();

        // Loop through each plugin to check for updates
        foreach ($plugins as $plugin_file => $plugin_data) {
            // Only check plugins authored by 'Rafael Nowrotek' or 'Benignware'
            if ($this->is_benignware_plugin($plugin_data)) {
                
                $plugin_slug = sanitize_title($plugin_data['Name']);
                $unprefixed_plugin_slug = str_replace('wp-', '', $plugin_slug);
                $current_version = $plugin_data['Version'];

                $update_info = $this->get_update_info($plugin_slug, $current_version);

                if ($update_info && version_compare($update_info->version, $current_version, '>')) {
                    $download_url = $this->build_download_url($update_info->download_url, $unprefixed_plugin_slug);
                    $plugin_update = new \stdClass();
                    $plugin_update->slug = $plugin_slug;
                    $plugin_update->new_version = $update_info->version;
                    $plugin_update->package = $download_url;
                    $transient->response[$plugin_file] = $plugin_update;
                }
            }
        }

        return $transient;
    }

    /**
     * Check if the plugin is authored by 'Rafael Nowrotek' or 'Benignware'.
     */
    private function is_benignware_plugin($plugin_data) {
        $author = $plugin_data['Author'] ?? '';
        return (stripos($author, 'Rafael Nowrotek') !== false || stripos($author, 'Benignware') !== false);
    }

    /**
     * Get update information from the API.
     */
    private function get_update_info($slug, $current_version) {
        $unprefixed_slug = preg_replace('/^wp-/', '', $slug);
        $url = "{$this->hub_url}/api/v1/packages/wp-{$unprefixed_slug}/latest";

        $license_key = get_option('benignware_license_key');

        if (!empty($license_key)) {
            $url = add_query_arg('key', $license_key, $url);
        }

        $response = wp_remote_get($url, ['timeout' => 15]);
        
        if (is_wp_error($response)) {
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            return null;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response));

        if (isset($response_body->download_url)) {
            $download_url = $response_body->download_url;
            $response_body->download_url = add_query_arg('zipname', $unprefixed_slug, $download_url);
        }
        
        return isset($response_body->version) ? $response_body : null;
    }

    /**
     * Build the download URL with the license key, if available.
     */
    private function build_download_url($download_url, $zipname = null) {
        if (!empty($this->license_key)) {
            return add_query_arg('key', $this->license_key, $download_url);
        }

        if ($zipname !== null) {
            $download_url = add_query_arg('zipname', $zipname, $download_url);
        }

        return $download_url;
    }
}

// Instantiate the class
new PluginUpdateManager();
