<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Services\AuditService;

final class AuditServiceTest extends TestCase
{
    public function test_log_and_list_for_session(): void
    {
        $wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $wpdb;
        $service = new AuditService($wpdb);

        $service->log('session_closed', 'session', 5, 'active', 'closed', 1);
        $service->log('mark_override', 'mark', 12, '7', '{"score":8}', 1);

        $wpdb->insert($wpdb->prefix . 'pr_marks', [
            'id' => 12,
            'session_id' => 5,
            'review_id' => 1,
            'student_id' => 1,
            'reviewer_user_id' => 1,
            'criterion_id' => 1,
            'status' => 'submitted',
        ]);

        $result = $service->list_for_session(5);
        $this->assertSame(2, $result['total']);
        unset($GLOBALS['wpdb']);
    }
}
