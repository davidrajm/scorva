<?php

declare(strict_types=1);

namespace ProjectReviews;

final class Rest_Binary_Response
{
    public const SERVE_RAW_HEADER = 'X-PR-Serve-Raw';

    public static function register(): void
    {
        add_filter('rest_pre_serve_request', [self::class, 'maybe_serve_raw'], 10, 4);
    }

    public static function from_body(
        string $body,
        string $content_type,
        string $filename
    ): \WP_REST_Response {
        $response = new \WP_REST_Response($body, 200);
        $response->header('Content-Type', $content_type);
        $response->header(
            'Content-Disposition',
            'attachment; filename="' . sanitize_file_name($filename) . '"'
        );
        $response->header(self::SERVE_RAW_HEADER, '1');

        return $response;
    }

    /**
     * @param mixed $result
     */
    public static function maybe_serve_raw(
        bool $served,
        $result,
        \WP_REST_Request $request,
        \WP_REST_Server $server
    ): bool {
        unset($request, $server);

        if ($served || !($result instanceof \WP_REST_Response)) {
            return $served;
        }

        if (($result->get_headers()[self::SERVE_RAW_HEADER] ?? '') !== '1') {
            return $served;
        }

        $data = $result->get_data();
        if (!is_string($data)) {
            return $served;
        }

        if (!headers_sent()) {
            status_header($result->get_status());
            foreach ($result->get_headers() as $key => $value) {
                if ($key === self::SERVE_RAW_HEADER) {
                    continue;
                }
                header($key . ': ' . $value);
            }
        }

        echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary

        return true;
    }
}
