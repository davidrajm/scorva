-- Golden score validation (Story 6.7).
-- Replace {prefix} with your WordPress table prefix (e.g. wp_).
--
-- Fixture: one review, two criteria (max 10 each), three reviewers weight 1.
-- Marks: R1 5+5, R2 5+9, R3 4+5 (submitted).
-- Expected: reviewer totals 10, 14, 9; review score 11.00; combined 11.00 (single review).

-- Spot-check raw marks for student reg_no (adjust IDs as needed):
SELECT
    s.reg_no,
    m.reviewer_user_id,
    rc.label AS criterion,
    m.score,
    m.status
FROM {prefix}pr_marks m
INNER JOIN {prefix}pr_students s ON s.id = m.student_id
INNER JOIN {prefix}pr_rubric_criteria rc ON rc.id = m.criterion_id
WHERE m.session_id = 1
  AND m.review_id = 1
  AND s.reg_no = 'G001'
ORDER BY m.reviewer_user_id, rc.sort_order;

-- Reviewer totals should equal SUM(score) per reviewer (submitted only).
-- Review score = AVG(reviewer totals) when all reviewer weights are 1.
