<?php
namespace benignware\wpconnect\Admin;

class ThemeInstall {

    private $custom_themes = [];
    private $default_screenshot_url;

    public function __construct() {
        // Hook to modify theme API
        add_filter('themes_api', [$this, 'add_custom_themes_to_api'], 10, 3);

        // Enqueue custom JavaScript to handle custom sorting and tabs
        add_action('admin_enqueue_scripts', [$this, 'enqueue_custom_scripts']);

        // Set default screenshot URL
        $this->default_screenshot_url = includes_url('images/w-logo-blue.png');
    }

    /**
     * Enqueue custom JavaScript for handling the "benignware" filter.
     */
    public function enqueue_custom_scripts() {
        $screen = get_current_screen();
        
        if ($screen && $screen->id === 'theme-install') {
            wp_enqueue_script(
                'benignware-connect-theme-install',
                plugin_dir_url(__FILE__) . 'theme-install.js',
                [],
                '1.0.0',
                true
            );

            wp_enqueue_style(
                'benignware-connect-theme-install',
                plugin_dir_url(__FILE__) . 'theme-install.css',
                [],
                '1.0.0'
            );
        }
    }

    /**
     * Add custom themes to the themes API response.
     */
    public function add_custom_themes_to_api($result, $action, $args) {
        if ($action === 'query_themes' && isset($args->browse) && $args->browse === 'benignware') {
            if (!is_object($result)) {
                $result = new \stdClass();
            }

            if (!isset($result->themes)) {
                $result->themes = [];
            }

            $themes = $this->fetch_themes_from_api();

            $search_term = isset($args->search) ? strtolower($args->search) : '';

            if ($search_term) {
                $themes = array_filter($themes, function($theme) use ($search_term) {
                    return strpos(strtolower($theme['name']), $search_term) !== false ||
                           strpos(strtolower($theme['description']), $search_term) !== false;
                });
            }

            $themes = array_map(function($theme) {
                return (object) [
                    'name' => $theme['name'],
                    'slug' => $theme['slug'],
                    'version' => $theme['version'],
                    'author' => $theme['author'],
                    'screenshot' => $theme['screenshot'] ?: $this->default_screenshot_url,
                    'description' => $theme['description'],
                    'preview_url' => $theme['preview_url'],
                    'requires' => $theme['requires'],
                    'requires_php' => $theme['requires_php'],
                    'rating' => $theme['rating'],
                    'num_ratings' => $theme['num_ratings'],
                ];
            }, $themes);

            $result->themes = array_merge($result->themes, $themes);

            $result->info = (object) [
                'page' => 1,
                'pages' => 1,
                'results' => count($result->themes),
            ];

            return $result;
        }

        if ($action === 'theme_information') {
            $theme_slug = $args->slug;
            $themes = $this->fetch_themes_from_api();

            foreach ($themes as $theme) {
                if ($theme['slug'] === $theme_slug) {

                    return (object) [
                        'name' => $theme['name'],
                        'slug' => $theme['slug'],
                        'version' => $theme['version'],
                        'author' => $theme['author'],
                        'requires' => $theme['requires'],
                        'requires_php' => $theme['requires_php'],
                        'rating' => $theme['rating'],
                        'num_ratings' => $theme['num_ratings'],
                        'download_link' => $theme['download_link'],
                        'preview_url' => $theme['preview_url'],
                        'screenshot_url' => $theme['screenshot_url'] ?: $this->default_screenshot_url,
                        'sections' => [
                            'description' => $theme['description'],
                        ],
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Fetch themes from the API.
     */
    private function fetch_themes_from_api() {
        $api_url = 'https://hub.benignware.com/api/v1/packages/index?filter=wp-&keywords=wordpress-theme';
        $key = get_option('benignware_license_key');

        if (!empty($key)) {
            $api_url = add_query_arg('key', $key, $api_url);
        }

        $response = wp_remote_get($api_url, ['timeout' => 100]);

        if (is_wp_error($response)) {
            error_log('Error fetching themes from API: ' . $response->get_error_message());
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $themes = json_decode($body, true);

        if (is_array($themes)) {
            return array_map([$this, 'format_theme_data'], $themes);
        } else {
            error_log('Invalid theme data received from API');
            return [];
        }
    }

    /**
     * Format the theme name by stripping 'wp-' prefix and capitalizing.
     */
    private function format_theme_name($name) {
        // Check if the name starts with 'wp-' and is in hyphen-case
        if (strpos($name, 'wp-') === 0) {
            // Strip 'wp-' prefix
            $name = substr($name, 3);
            // Replace hyphens with spaces and capitalize each word
            $name = ucwords(str_replace('-', ' ', $name));
        }
        return $name;
    }

    /**
     * Format theme data for internal use.
     */
    private function format_theme_data($theme) {
        // Remove 'wp-' prefix from the slug
        $slug = sanitize_title($theme['name']);
        $slug = str_replace('wp-', '', $slug);

        $download_link = add_query_arg('zipname', $slug, $theme['download_link']);
    
        return [
            'name' => $this->format_theme_name($theme['name']),
            'slug' => $slug,
            'screenshot' => $theme['screenshot'] ?? $this->default_screenshot_url,
            'author' => ['display_name' => $theme['author'] ?? ''],
            'description' => $theme['description'] ?? '',
            'version' => $theme['version'] ?? '',
            'download_link' => $download_link
        ];
    }
}

// Instantiate the class
new ThemeInstall();
