# Story 3.2: Sessions REST and dashboard with session cards

Status: review

## Story

As a **coordinator**,
I want to create, list, and open review sessions from a card-based dashboard,
So that I can manage multiple events in one place.

## Acceptance Criteria

1. **Given** a user with `pr_manage_sessions` **When** they create a session via REST **Then** it is stored with status `draft` by default **When** they open `/reviews/` dashboard **Then** each session renders as `SessionCard` with title, StatusChip, and progress placeholder **And** clicking a card navigates into session context **And** status filter chips filter the dashboard list (UX-DR29)

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Agent Record

### Completion Notes List

- `Rest_Sessions`: CRUD, list with status filter, wizard-state endpoint.
- Dashboard: create session form, status filter chips, linked `SessionCard` rows.
- `RestSessionsTest` covers draft default and status filter.

### File List

- includes/rest/class-rest-sessions.php
- includes/rest/class-rest-bootstrap.php
- src/coordinator/pages/Dashboard.jsx
- src/shared/components/SessionCard.jsx
- tests/RestSessionsTest.php

### Change Log

- 2026-05-17: Sessions REST and coordinator dashboard (Epic 3.2).
