<?php
/**
 * ImageGrid client.
 *
 * @package MDS\Extensions\ImageGrid
 */

namespace MDS\Extensions\ImageGrid;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HTTP client for the ImageGrid service.
 */
class API_Client {

    /**
     * API base URL.
     *
     * @var string
     */
    private $api_url;

    /**
     * API key.
     *
     * @var string
     */
    private $api_key;

    /**
     * Constructor.
     *
     * @param string|null $api_url Optional API URL.
     * @param string|null $api_key Optional API key.
     */
    public function __construct($api_url = null, $api_key = null) {
        $this->api_url = untrailingslashit((string) ($api_url ?: get_option('mds_imagegrid_api_url', mds_imagegrid_default_api_url())));
        $this->api_key = (string) ($api_key ?? get_option('mds_imagegrid_api_key', ''));
    }

    /**
     * Return the configured API URL.
     *
     * @return string
     */
    public function get_api_url() {
        return $this->api_url;
    }

    /**
     * Check basic service health.
     *
     * @return array|\WP_Error
     */
    public function health() {
        return $this->request_json('GET', '/healthz', [], false);
    }

    /**
     * Check readiness.
     *
     * @return array|\WP_Error
     */
    public function ready() {
        return $this->request_json('GET', '/readyz', [], false);
    }

    /**
     * Fetch account information for the configured API key.
     *
     * @return array|\WP_Error
     */
    public function account() {
        return $this->request_json('GET', '/v1/account');
    }

    /**
     * Create a grid_render job.
     *
     * @param \MDS\Core\Grid $grid    Grid object.
     * @param array          $options Additional ImageGrid options.
     * @return array|\WP_Error
     */
    public function create_grid_render_job($grid, array $options = []) {
        $payload = [
            'operation' => 'grid_render',
            'options'   => $this->build_grid_render_options($grid, $options),
        ];

        return $this->request_json('POST', '/v1/jobs', $payload);
    }

    /**
     * Fetch a job by ID.
     *
     * @param string $job_id Job UUID.
     * @return array|\WP_Error
     */
    public function get_job($job_id) {
        $job_id = sanitize_text_field((string) $job_id);

        if (!$this->looks_like_uuid($job_id)) {
            return new \WP_Error('mds_imagegrid_invalid_job_id', __('Invalid ImageGrid job ID.', 'mds-imagegrid'));
        }

        return $this->request_json('GET', '/v1/jobs/' . rawurlencode($job_id));
    }

    /**
     * Build a remote tile request descriptor for server-side proxying.
     *
     * @param string $tileset_id Tileset UUID.
     * @param int    $z          Zoom level.
     * @param int    $x          Tile x coordinate.
     * @param int    $y          Tile y coordinate.
     * @param string $format     Tile format.
     * @return array|\WP_Error
     */
    public function get_tileset_tile_request($tileset_id, $z, $x, $y, $format = 'png') {
        $tileset_id = sanitize_text_field((string) $tileset_id);

        if (!$this->looks_like_uuid($tileset_id)) {
            return new \WP_Error('mds_imagegrid_invalid_tileset_id', __('Invalid ImageGrid tileset ID.', 'mds-imagegrid'));
        }

        $format = strtolower(sanitize_key((string) $format));
        if (!in_array($format, ['png', 'jpeg', 'jpg', 'webp'], true)) {
            $format = 'png';
        }

        if ('jpg' === $format) {
            $format = 'jpeg';
        }

        return [
            'url'      => $this->build_url(sprintf(
                '/v1/tilesets/%s/xyz/%d/%d/%d.%s',
                rawurlencode($tileset_id),
                absint($z),
                absint($x),
                absint($y),
                $format
            )),
            'headers'  => array_merge($this->auth_headers(), $this->host_header()),
            'timeout'  => 20,
            'cache_ttl' => DAY_IN_SECONDS,
        ];
    }

    /**
     * Create a JSON request.
     *
     * @param string $method        HTTP method.
     * @param string $path          Request path.
     * @param array  $payload       Request body/query.
     * @param bool   $requires_auth Whether API key auth is required.
     * @return array|\WP_Error
     */
    public function request_json($method, $path, array $payload = [], $requires_auth = true) {
        $method = strtoupper((string) $method);
        $url = $this->build_url($path);

        if (!$url) {
            return new \WP_Error('mds_imagegrid_invalid_api_url', __('The ImageGrid URL is invalid.', 'mds-imagegrid'));
        }

        if ($requires_auth && '' === $this->api_key) {
            return new \WP_Error('mds_imagegrid_missing_api_key', __('Enter an ImageGrid API key before connecting.', 'mds-imagegrid'));
        }

        $args = [
            'method'      => $method,
            'timeout'     => 45,
            'redirection' => 2,
            'headers'     => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent'   => 'MDS-ImageGrid/' . MDS_IMAGEGRID_VERSION . ' WordPress/' . get_bloginfo('version'),
                'X-Site-URL'   => home_url(),
            ],
        ];

        if ($requires_auth) {
            $args['headers'] = array_merge($args['headers'], $this->auth_headers());
        }

        $args['headers'] = array_merge($args['headers'], $this->host_header());

        if ('GET' === $method && !empty($payload)) {
            $url = add_query_arg($payload, $url);
        } elseif (!empty($payload)) {
            $args['body'] = wp_json_encode($payload);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = null;

        if ('' !== $body) {
            $decoded = json_decode($body, true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                return new \WP_Error(
                    'mds_imagegrid_invalid_json',
                    __('ImageGrid returned an invalid JSON response.', 'mds-imagegrid'),
                    ['status' => $code, 'body' => $body]
                );
            }
        }

        if ($code < 200 || $code >= 300) {
            $message = is_array($decoded) && isset($decoded['error'])
                ? sanitize_text_field((string) $decoded['error'])
                : sprintf(__('ImageGrid returned HTTP %d.', 'mds-imagegrid'), $code);

            return new \WP_Error('mds_imagegrid_http_error', $message, [
                'status' => $code,
                'body'   => $decoded,
            ]);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build grid_render options from an MDS grid.
     *
     * @param \MDS\Core\Grid $grid    Grid object.
     * @param array          $options Additional options.
     * @return array
     */
    private function build_grid_render_options($grid, array $options = []) {
        $grid_id = method_exists($grid, 'get_id') ? absint($grid->get_id()) : 0;
        $background = get_post_meta($grid_id, '_mds_grid_background_color', true) ?: mds_get_option('mds_grid_background_color', '#ffffff');
        $line_color = get_post_meta($grid_id, '_mds_grid_line_color', true) ?: mds_get_option('mds_grid_line_color', '#999999');
        $line_width = get_post_meta($grid_id, '_mds_grid_line_width', true) ?: mds_get_option('mds_grid_line_width', 1);
        $show_lines = get_post_meta($grid_id, '_mds_grid_show_lines', true);

        if ('' === $show_lines) {
            $show_lines = mds_get_option('mds_grid_show_lines', true);
        } else {
            $show_lines = (bool) $show_lines;
        }

        $default_options = [
            'width'            => max(1, absint($grid->get_width())),
            'height'           => max(1, absint($grid->get_height())),
            'block_width'      => max(1, absint($grid->get_block_width())),
            'block_height'     => max(1, absint($grid->get_block_height())),
            'background_color' => sanitize_hex_color($background) ?: '#ffffff',
            'grid_line_color'  => sanitize_hex_color($line_color) ?: '#999999',
            'grid_line_width'  => max(0, absint($line_width)),
            'show_grid_lines'  => (bool) $show_lines,
            'tile_size'        => max(64, absint(get_option('mds_grid_tile_size', 256))),
            'format'           => 'png',
            'strategy'         => 'deepzoom',
        ];

        $max_zoom = absint(get_option('mds_max_zoom_level', 5));
        $min_zoom = absint(get_option('mds_min_zoom_level', 0));
        $default_options['min_level'] = min($min_zoom, $max_zoom);
        $default_options['max_level'] = max($min_zoom, $max_zoom);

        return array_merge($default_options, $options);
    }

    /**
     * Authentication headers.
     *
     * @return array
     */
    private function auth_headers() {
        return [
            'Authorization' => 'Bearer ' . $this->api_key,
            'X-API-Key'     => $this->api_key,
        ];
    }

    /**
     * Host header override for Docker-to-host local development.
     *
     * @return array
     */
    private function host_header() {
        $parts = wp_parse_url($this->api_url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ('host.docker.internal' !== $host) {
            return [];
        }

        $target = 'localhost';
        if (!empty($parts['port'])) {
            $target .= ':' . absint($parts['port']);
        }

        return ['Host' => $target];
    }

    /**
     * Build an absolute API URL.
     *
     * @param string $path Request path.
     * @return string
     */
    private function build_url($path) {
        $url = untrailingslashit($this->api_url) . '/' . ltrim((string) $path, '/');

        return self::is_allowed_url($url) ? $url : '';
    }

    /**
     * Validate a service URL, allowing loopback hosts for local or self-hosted ImageGrid instances.
     *
     * @param string $url URL to validate.
     * @return bool
     */
    public static function is_allowed_url($url) {
        $parts = wp_parse_url((string) $url);

        if (empty($parts['scheme']) || empty($parts['host']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        $is_local_host = in_array($host, ['localhost', '127.0.0.1', '::1', 'host.docker.internal'], true);

        if ($is_local_host) {
            return true;
        }

        return (bool) wp_http_validate_url((string) $url);
    }

    /**
     * Check whether a string looks like a UUID.
     *
     * @param string $value Value to check.
     * @return bool
     */
    private function looks_like_uuid($value) {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $value);
    }
}
