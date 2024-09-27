<?php

namespace benignware\wpconnect\Admin;

class ThemeUpdateManager {

    private $license_key;
    private $hub_url;
    private $default_icon_url = null;

    public function __construct() {
        // Set the API URL for checking updates
        $this->hub_url = 'https://hub.benignware.com';
        // Get the license key from the options table
        $this->license_key = get_option('benignware_license_key', '');

        // Hook to check theme updates
        add_filter('pre_set_site_transient_update_themes', [$this, 'check_theme_updates']);

        $this->default_icon_url = includes_url('images/w-logo-blue.png');
    }

    /**
     * Check for theme updates.
     */
    public function check_theme_updates($transient) {
        $themes = wp_get_themes();
        
        foreach ($themes as $theme_slug => $theme) {
            // Only check themes authored by 'Rafael Nowrotek' or 'Benignware'
            if ($this->is_benignware_theme($theme)) {
                $unprefixed_theme_slug = str_replace('wp-', '', sanitize_title($theme_slug));
                $current_version = $theme->get('Version');
                $update_info = $this->get_update_info($theme_slug, $current_version);

                if ($update_info && version_compare($update_info->version, $current_version, '>')) {
                    $download_url = $this->build_download_url($update_info->download_url, $unprefixed_theme_slug);
                    $theme_update = [
                        'theme' => $unprefixed_theme_slug,
                        'new_version' => $update_info->version,
                        'url' => $download_url,
                        'package' => $download_url,
                    ];
                    $transient->response[$theme_slug] = $theme_update;
                }
            }
        }

        return $transient;
    }

    /**
     * Check if the theme is authored by 'Rafael Nowrotek' or 'Benignware'.
     */
    private function is_benignware_theme($theme) {
        $author = $theme->get('Author') ?? '';
        return (stripos($author, 'Rafael Nowrotek') !== false || stripos($author, 'Benignware') !== false);
    }

    /**
     * Get update information from the API.
     */
    private function get_update_info($slug, $current_version) {
        $unprefixed_slug = str_replace('wp-', '', $slug);
        $url = "{$this->hub_url}/api/v1/packages/wp-{$unprefixed_slug}/latest";

        // Add license key to the URL if available
        if (!empty($this->license_key)) {
            $url = add_query_arg('key', $this->license_key, $url);
        }

        $response = wp_remote_get($url, ['timeout' => 50]);
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
        // $download_url = $this->hub_url . $download_url;

        if (!empty($this->license_key)) {
            $download_url = add_query_arg('key', $this->license_key, $download_url);
        }

        if ($zipname !== null) {
            $download_url = add_query_arg('zipname', $zipname, $download_url);
        }

        return $download_url;
    }
}

// Instantiate the class
new ThemeUpdateManager();
