<?php
/**
 * Main ImageGrid extension class.
 *
 * @package MDS\Extensions\ImageGrid
 */

namespace MDS\Extensions\ImageGrid;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates ImageGrid integration.
 */
class Main {

    /**
     * Singleton instance.
     *
     * @var Main|null
     */
    private static $instance = null;

    /**
     * Whether hooks have been registered.
     *
     * @var bool
     */
    private $initialized = false;

    /**
     * Get singleton instance.
     *
     * @return Main
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the extension.
     *
     * @return void
     */
    public static function init() {
        self::instance()->initialize();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    private function initialize() {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        if (is_admin()) {
            Admin::init();
        }

        add_filter('mds_grid_remote_tile_request', [$this, 'get_remote_tile_request'], 10, 5);
        add_filter('mds_grid_has_remote_tiles', [$this, 'has_remote_tiles'], 10, 2);
        add_filter('mds_image_processor_generate_tiles_for_grid', [$this, 'maybe_start_remote_generation'], 10, 3);
        add_filter('mds_imagegrid_client', [$this, 'filter_client']);
    }

    /**
     * Return a client instance to callers.
     *
     * @return API_Client
     */
    public function filter_client() {
        return $this->client();
    }

    /**
     * Report whether ImageGrid has a completed remote tileset for a grid.
     *
     * @param bool $has_tiles Existing result.
     * @param int  $grid_id   Grid ID.
     * @return bool
     */
    public function has_remote_tiles($has_tiles, $grid_id) {
        if ($has_tiles || !self::is_enabled()) {
            return (bool) $has_tiles;
        }

        $state = self::get_grid_state($grid_id);

        return !empty($state['tileset_id']) && 'succeeded' === ($state['status'] ?? '');
    }

    /**
     * Return a server-side remote tile request for a ready grid.
     *
     * @param mixed $request Existing request value.
     * @param int   $grid_id Grid ID.
     * @param int   $z       Zoom level.
     * @param int   $x       Tile x coordinate.
     * @param int   $y       Tile y coordinate.
     * @return mixed
     */
    public function get_remote_tile_request($request, $grid_id, $z, $x, $y) {
        if ($request || !self::is_enabled()) {
            return $request;
        }

        $state = self::get_grid_state($grid_id);
        $tileset_id = $state['tileset_id'] ?? '';

        if (!$tileset_id || 'succeeded' !== ($state['status'] ?? '')) {
            return $request;
        }

        $tile_request = $this->client()->get_tileset_tile_request(
            $tileset_id,
            absint($z),
            absint($x),
            absint($y),
            $state['format'] ?? 'png'
        );

        return is_wp_error($tile_request) ? $request : $tile_request;
    }

    /**
     * Optionally offload tile generation to ImageGrid.
     *
     * @param mixed $result  Existing filter result.
     * @param int   $grid_id Grid ID.
     * @param array $context Generation context.
     * @return mixed
     */
    public function maybe_start_remote_generation($result, $grid_id, $context) {
        if (null !== $result || !self::is_enabled() || !get_option('mds_imagegrid_auto_render', false)) {
            return $result;
        }

        $grid = isset($context['grid']) ? $context['grid'] : null;

        if (!$grid || !is_object($grid)) {
            $grid_model = new \MDS\Core\Grid();
            $grid = $grid_model->get($grid_id);
        }

        if (!$grid) {
            return $result;
        }

        $existing = self::get_grid_state($grid_id);
        if (!empty($existing['job_id']) && in_array($existing['status'] ?? '', ['queued', 'running'], true)) {
            return [
                'status' => 'remote_in_progress',
                'job_id' => $existing['job_id'],
            ];
        }

        $response = self::submit_grid_render($grid_id, $grid);

        if (is_wp_error($response)) {
            self::update_grid_state($grid_id, [
                'status'     => 'failed',
                'last_error' => $response->get_error_message(),
                'updated_at' => current_time('mysql'),
            ]);

            return null;
        }

        return [
            'status' => 'remote_queued',
            'job_id' => $response['id'] ?? '',
        ];
    }

    /**
     * Submit a grid render job.
     *
     * @param int                 $grid_id Grid ID.
     * @param \MDS\Core\Grid|null $grid    Optional grid object.
     * @return array|\WP_Error
     */
    public static function submit_grid_render($grid_id, $grid = null) {
        $grid_id = absint($grid_id);

        if (!$grid) {
            $grid_model = new \MDS\Core\Grid();
            $grid = $grid_model->get($grid_id);
        }

        if (!$grid) {
            return new \WP_Error('mds_imagegrid_grid_not_found', __('Grid not found.', 'mds-imagegrid'));
        }

        $response = self::instance()->client()->create_grid_render_job($grid);

        if (is_wp_error($response)) {
            return $response;
        }

        self::update_grid_state($grid_id, [
            'job_id'     => $response['id'] ?? '',
            'status'     => $response['status'] ?? 'queued',
            'operation'  => $response['operation'] ?? 'grid_render',
            'tileset_id' => '',
            'format'     => 'png',
            'submitted_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'last_error' => '',
        ]);

        return $response;
    }

    /**
     * Refresh a saved ImageGrid job and persist grid state.
     *
     * @param int $grid_id Grid ID.
     * @return array|\WP_Error
     */
    public static function refresh_grid_job($grid_id) {
        $grid_id = absint($grid_id);
        $state = self::get_grid_state($grid_id);
        $job_id = $state['job_id'] ?? '';

        if (!$job_id) {
            return new \WP_Error('mds_imagegrid_missing_job', __('No ImageGrid job is stored for this grid.', 'mds-imagegrid'));
        }

        $response = self::instance()->client()->get_job($job_id);

        if (is_wp_error($response)) {
            self::update_grid_state($grid_id, [
                'last_error' => $response->get_error_message(),
                'updated_at' => current_time('mysql'),
            ]);

            return $response;
        }

        $result = isset($response['result']) && is_array($response['result']) ? $response['result'] : [];

        self::update_grid_state($grid_id, [
            'job_id'     => $response['id'] ?? $job_id,
            'status'     => $response['status'] ?? ($state['status'] ?? ''),
            'tileset_id' => $result['tileset_id'] ?? ($state['tileset_id'] ?? ''),
            'format'     => $result['format'] ?? ($state['format'] ?? 'png'),
            'tile_size'  => $result['tile_size'] ?? ($state['tile_size'] ?? 256),
            'min_level'  => $result['min_level'] ?? ($state['min_level'] ?? 0),
            'max_level'  => $result['max_level'] ?? ($state['max_level'] ?? 0),
            'total_tiles' => $result['total_tiles'] ?? ($state['total_tiles'] ?? 0),
            'last_error' => $response['error_message'] ?? '',
            'updated_at' => current_time('mysql'),
        ]);

        return $response;
    }

    /**
     * Get grid remote state.
     *
     * @param int $grid_id Grid ID.
     * @return array
     */
    public static function get_grid_state($grid_id) {
        $state = get_option(self::grid_state_option_name($grid_id), []);

        return is_array($state) ? $state : [];
    }

    /**
     * Update grid remote state.
     *
     * @param int   $grid_id Grid ID.
     * @param array $state   State patch.
     * @return void
     */
    public static function update_grid_state($grid_id, array $state) {
        $grid_id = absint($grid_id);
        $existing = self::get_grid_state($grid_id);
        $updated = array_merge($existing, $state);

        update_option(self::grid_state_option_name($grid_id), $updated, false);
    }

    /**
     * Whether remote integration is enabled.
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option('mds_imagegrid_enabled', false);
    }

    /**
     * Create an API client.
     *
     * @return API_Client
     */
    private function client() {
        return new API_Client();
    }

    /**
     * Per-grid option name.
     *
     * @param int $grid_id Grid ID.
     * @return string
     */
    private static function grid_state_option_name($grid_id) {
        return 'mds_imagegrid_grid_' . absint($grid_id);
    }
}
