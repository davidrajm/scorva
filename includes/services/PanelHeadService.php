<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Repositories\PanelRepository;

final class PanelHeadService
{
    private PanelRepository $panels;

    public function __construct(?PanelRepository $panels = null)
    {
        $this->panels = $panels ?? new PanelRepository();
    }

    /**
     * @return true|\WP_Error
     */
    public function set_session_panel_head(int $reviewer_id, bool $is_head): bool|\WP_Error
    {
        $reviewer = $this->panels->find_reviewer($reviewer_id);
        if ($reviewer === null) {
            return new \WP_Error(
                'pr_reviewer_not_found',
                __('Reviewer not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $panel_id = (int) ($reviewer['panel_id'] ?? 0);
        if ($panel_id <= 0) {
            return new \WP_Error(
                'pr_reviewer_not_found',
                __('Reviewer not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        if ($is_head) {
            $user_id = isset($reviewer['user_id']) ? (int) $reviewer['user_id'] : 0;
            if ($user_id <= 0) {
                return new \WP_Error(
                    'panel_head_requires_account',
                    __('A linked account is required before designating a panel coordinator.', 'project-reviews'),
                    ['status' => 400]
                );
            }

            $this->panels->clear_panel_heads($panel_id);
            $this->panels->set_reviewer_panel_head($reviewer_id, true);

            return true;
        }

        $this->panels->set_reviewer_panel_head($reviewer_id, false);

        return true;
    }

}
