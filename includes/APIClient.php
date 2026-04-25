<?php
/**
 * ImageGrid client.
 *
 * @package MDS\Extensions\ImageGrid
 */

namespace MDS\Extensions\ImageGrid;

use MDS\Media\OriginalAttachmentResolver;
use MDS\Rendering\RenderEstimate;

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
     * Exchange a browser-returned connect token for a site-scoped API key.
     *
     * The hosted service owns token issuance. WordPress only accepts the short
     * return token and stores the resulting runtime credential server-side.
     *
     * @param string $token Connect token returned by ImageGrid.
     * @param array  $site  Site metadata for the exchange.
     * @return array|\WP_Error
     */
    public function exchange_connect_token($token, array $site = []) {
        $token = $this->sanitize_connect_token($token);
        if (!$token) {
            return new \WP_Error('mds_imagegrid_invalid_connect_token', __('The ImageGrid connect token is invalid.', 'mds-imagegrid'));
        }

        return $this->request_json('POST', '/v1/integrations/mds/connect/exchange', [
            'token' => $token,
            'site'  => $this->site_payload($site),
        ], false);
    }

    /**
     * Ask ImageGrid to revoke this site's integration credential.
     *
     * Local credential removal is handled by the admin layer even if the remote
     * service cannot be reached.
     *
     * @param array $connection Stored connection metadata.
     * @return array|\WP_Error
     */
    public function disconnect_site(array $connection = []) {
        return $this->request_json('POST', '/v1/integrations/mds/disconnect', [
            'connection' => $this->site_payload($connection),
            'site'       => $this->site_payload([]),
        ]);
    }

    /**
     * Preflight a grid composition render.
     *
     * @param \MDS\Core\Grid $grid    Grid object.
     * @param array          $options Additional ImageGrid options.
     * @return array|\WP_Error
     */
    public function preflight_grid_render($grid, array $options = []) {
        $render_options = $this->build_grid_render_options($grid, $options);
        $payload = [
            'operation' => 'grid_render',
            'options'   => $render_options,
            'estimate'  => $this->estimate_grid_render($grid, $render_options),
        ];

        $response = $this->request_json('POST', '/v1/renders/preflight', $payload);
        if (is_wp_error($response) && in_array((int) ($response->get_error_data()['status'] ?? 0), [404, 405], true)) {
            return [
                'ok' => true,
                'skipped' => true,
                'reason' => 'preflight_endpoint_unavailable',
                'estimate' => $payload['estimate'],
            ];
        }

        return $response;
    }

    /**
     * Create a grid composition render job.
     *
     * @param \MDS\Core\Grid $grid    Grid object.
     * @param array          $options Additional ImageGrid options.
     * @return array|\WP_Error
     */
    public function create_grid_render_job($grid, array $options = []) {
        $render_options = $this->build_grid_render_options($grid, $options);
        $payload = [
            'operation' => 'grid_render',
            'options'   => $render_options,
        ];

        return $this->request_json('POST', '/v1/jobs', $payload);
    }

    /**
     * Create a grid composition render job from an MDS3 manifest.
     *
     * @param array $manifest  MDS3 render manifest.
     * @param array $preflight MDS3 preflight data.
     * @return array|\WP_Error
     */
    public function create_manifest_render_job(array $manifest, array $preflight = []) {
        $grid = is_array($manifest['grid'] ?? null) ? $manifest['grid'] : [];
        $options = [
            'width' => max(1, absint($grid['width'] ?? 0)),
            'height' => max(1, absint($grid['height'] ?? 0)),
            'block_width' => max(1, absint($grid['block_width'] ?? 0)),
            'block_height' => max(1, absint($grid['block_height'] ?? 0)),
            'background_color' => '#ffffff',
            'grid_line_color' => '#999999',
            'grid_line_width' => 1,
            'show_grid_lines' => true,
            'tile_size' => max(64, absint($manifest['tile_size'] ?? 256)),
            'format' => 'png',
            'strategy' => 'deepzoom',
        ];

        $payload = [
            'operation' => 'grid_render',
            'options' => $options,
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

            if (JSON_ERROR_NONE !== json_last_error() && $code >= 200 && $code < 300) {
                return new \WP_Error(
                    'mds_imagegrid_invalid_json',
                    __('ImageGrid returned an invalid JSON response.', 'mds-imagegrid'),
                    ['status' => $code, 'body' => $body]
                );
            }

            if (JSON_ERROR_NONE !== json_last_error()) {
                $decoded = null;
            }
        }

        if ($code < 200 || $code >= 300) {
            $message = '';
            if (is_array($decoded)) {
                $message = sanitize_text_field((string) ($decoded['error'] ?? $decoded['message'] ?? ''));
            }
            if ('' === $message && '' !== $body) {
                $message = sanitize_text_field(wp_strip_all_tags($body));
            }
            if ('' === $message) {
                $message = sprintf(__('ImageGrid returned HTTP %d.', 'mds-imagegrid'), $code);
            }

            return new \WP_Error('mds_imagegrid_http_error', $message, [
                'status' => $code,
                'body'   => is_array($decoded) ? $decoded : $body,
            ]);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build grid composition render options from an MDS grid.
     *
     * @param \MDS\Core\Grid $grid    Grid object.
     * @param array          $options Additional options.
     * @return array
     */
    private function build_grid_render_options($grid, array $options = []) {
        $grid_data = $this->grid_data($grid);
        $grid_id = absint($grid_data['id']);
        $settings = is_array($grid_data['settings'] ?? null) ? $grid_data['settings'] : [];
        $option_getter = function($key, $default) {
            return function_exists('mds_get_option') ? mds_get_option($key, $default) : $default;
        };
        $background = $settings['background_color'] ?? (get_post_meta($grid_id, '_mds_grid_background_color', true) ?: $option_getter('mds_grid_background_color', '#ffffff'));
        $line_color = get_post_meta($grid_id, '_mds_grid_line_color', true) ?: $option_getter('mds_grid_line_color', '#999999');
        $line_width = get_post_meta($grid_id, '_mds_grid_line_width', true) ?: $option_getter('mds_grid_line_width', 1);
        $show_lines = get_post_meta($grid_id, '_mds_grid_show_lines', true);

        if ('' === $show_lines) {
            $show_lines = $option_getter('mds_grid_show_lines', true);
        } else {
            $show_lines = (bool) $show_lines;
        }

        $default_options = [
            'width'            => max(1, absint($grid_data['width'])),
            'height'           => max(1, absint($grid_data['height'])),
            'block_width'      => max(1, absint($grid_data['block_width'])),
            'block_height'     => max(1, absint($grid_data['block_height'])),
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
     * Estimate a render for quota preflight.
     *
     * @param \MDS\Core\Grid $grid    Grid object.
     * @param array          $options Render options.
     * @return array
     */
    private function estimate_grid_render($grid, array $options) {
        $grid_data = $this->grid_data($grid);
        $source_images = [];
        foreach ($options['placements'] ?? [] as $placement) {
            if (!empty($placement['source']['width']) && !empty($placement['source']['height'])) {
                $source_images[] = [
                    'width' => (int) $placement['source']['width'],
                    'height' => (int) $placement['source']['height'],
                ];
            }
        }

        if (class_exists(RenderEstimate::class)) {
            return RenderEstimate::for_grid(
                [
                    'width' => max(1, absint($grid_data['width'])),
                    'height' => max(1, absint($grid_data['height'])),
                ],
                $source_images,
                (int) ($options['tile_size'] ?? 256),
                (int) ($options['max_level'] ?? 0)
            );
        }

        return [
            'grid_megapixels' => round((max(1, absint($grid_data['width'])) * max(1, absint($grid_data['height']))) / 1000000, 4),
            'source_megapixels' => 0,
            'tile_estimate' => 0,
            'storage_estimate_bytes' => 0,
            'processing_credits' => 1,
        ];
    }

    /**
     * Build placement manifest entries from the MDS placement table.
     *
     * @param int $grid_id Grid ID.
     * @return array
     */
    private function build_grid_placements($grid_id) {
        global $wpdb;

        $grid_id = absint($grid_id);
        if (!$grid_id) {
            return [];
        }

        $table = $wpdb->prefix . 'mds3_placements';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            $table = $wpdb->prefix . 'mds_placements';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                return [];
            }
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE grid_id = %d AND status IN ('active', 'pending', 'approved', 'ready', 'sold', 'purchased') ORDER BY sort_order ASC, id ASC",
            $grid_id
        ));

        $resolver = $this->source_resolver();
        $placements = [];

        foreach ($rows as $row) {
            $source = $resolver && method_exists($resolver, 'resolve') ? $resolver->resolve((int) $row->attachment_id) : null;

            $placements[] = [
                'id' => (int) $row->id,
                'attachment_id' => (int) $row->attachment_id,
                'x' => (int) $row->x,
                'y' => (int) $row->y,
                'width' => (int) $row->width,
                'height' => (int) $row->height,
                'crop' => [
                    'x' => null === $row->crop_x ? null : (float) $row->crop_x,
                    'y' => null === $row->crop_y ? null : (float) $row->crop_y,
                    'width' => null === $row->crop_width ? null : (float) $row->crop_width,
                    'height' => null === $row->crop_height ? null : (float) $row->crop_height,
                ],
                'focal_point' => [
                    'x' => null === $row->focal_x ? null : (float) $row->focal_x,
                    'y' => null === $row->focal_y ? null : (float) $row->focal_y,
                ],
                'fit_mode' => sanitize_key((string) $row->fit_mode),
                'link_url' => esc_url_raw((string) $row->link_url),
                'alt_text' => sanitize_text_field((string) $row->alt_text),
                'status' => sanitize_key((string) $row->status),
                'source' => $source ? [
                    'url' => $source['url'],
                    'mime_type' => $source['mime_type'],
                    'width' => $source['width'],
                    'height' => $source['height'],
                    'megapixels' => $source['megapixels'],
                ] : null,
            ];
        }

        return $placements;
    }

    /**
     * Normalize grid data across supported MDS versions.
     *
     * @param object $grid Grid object.
     * @return array
     */
    private function grid_data($grid) {
        if (is_object($grid) && method_exists($grid, 'to_array')) {
            $data = $grid->to_array();

            return [
                'id' => absint($data['id'] ?? 0),
                'width' => max(1, absint($data['width'] ?? 1000)),
                'height' => max(1, absint($data['height'] ?? 1000)),
                'block_width' => max(1, absint($data['block_width'] ?? 10)),
                'block_height' => max(1, absint($data['block_height'] ?? 10)),
                'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
            ];
        }

        return [
            'id' => is_object($grid) && method_exists($grid, 'get_id') ? absint($grid->get_id()) : 0,
            'width' => is_object($grid) && method_exists($grid, 'get_width') ? max(1, absint($grid->get_width())) : 1000,
            'height' => is_object($grid) && method_exists($grid, 'get_height') ? max(1, absint($grid->get_height())) : 1000,
            'block_width' => is_object($grid) && method_exists($grid, 'get_block_width') ? max(1, absint($grid->get_block_width())) : 10,
            'block_height' => is_object($grid) && method_exists($grid, 'get_block_height') ? max(1, absint($grid->get_block_height())) : 10,
            'settings' => [],
        ];
    }

    /**
     * Resolve the best available source image resolver.
     *
     * @return object|null
     */
    private function source_resolver() {
        if (class_exists('\\MDS3\\Media\\OriginalImage')) {
            return new \MDS3\Media\OriginalImage();
        }

        if (class_exists(OriginalAttachmentResolver::class)) {
            return new OriginalAttachmentResolver();
        }

        return null;
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
     * Sanitize a connect token without weakening entropy.
     *
     * @param string $token Raw token.
     * @return string
     */
    private function sanitize_connect_token($token) {
        $token = trim((string) $token);
        $token = preg_replace('/[^A-Za-z0-9._~:-]/', '', $token);

        return strlen((string) $token) >= 12 && strlen((string) $token) <= 512 ? (string) $token : '';
    }

    /**
     * Build site metadata for ImageGrid integration calls.
     *
     * @param array $site Additional metadata.
     * @return array
     */
    private function site_payload(array $site) {
        return array_filter([
            'site_url' => esc_url_raw((string) ($site['site_url'] ?? home_url('/'))),
            'admin_url' => esc_url_raw((string) ($site['admin_url'] ?? admin_url())),
            'site_name' => sanitize_text_field((string) ($site['site_name'] ?? get_bloginfo('name'))),
            'timezone' => sanitize_text_field((string) ($site['timezone'] ?? wp_timezone_string())),
            'wordpress_version' => sanitize_text_field((string) get_bloginfo('version')),
            'plugin_version' => MDS_IMAGEGRID_VERSION,
            'mds_version' => defined('MDS3_VERSION') ? MDS3_VERSION : '',
            'connection_id' => sanitize_text_field((string) ($site['connection_id'] ?? '')),
            'account_id' => sanitize_text_field((string) ($site['account_id'] ?? '')),
            'account_slug' => sanitize_key((string) ($site['account_slug'] ?? '')),
            'api_key_id' => sanitize_text_field((string) ($site['api_key_id'] ?? '')),
        ], static function ($value) {
            return '' !== $value && null !== $value;
        });
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
