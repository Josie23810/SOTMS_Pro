# Feature Compliance Implementation TODO (Tutor + Admin + Student Progress)

## 1) Schema & Data Support
- [ ] Add safe schema updates (if missing):
  - [ ] `tutor_profiles`: availability_days, availability_start, availability_end, max_sessions_per_day
  - [ ] `users`: is_suspended
  - [ ] `sessions`: tutor_response_message
  - [ ] `session_feedback` table (grade/comments per session)
  - [ ] `notifications` table (instant user notifications)
  - [ ] `security_logs` table (admin audit page source)

## 2) Tutor Profile Flow (4.1)
- [ ] Update `tutor/profile.php`:
  - [ ] Add availability day/time selectors
  - [ ] Add max sessions/day input
  - [ ] Save and reload fields properly
- [ ] Ensure `tutor/dashboard.php` exposes easy "My Profile" access text per guide

## 3) Accept/Decline Booking Flow (4.2)
- [ ] Update `tutor/schedule.php`:
  - [ ] Add short message input on accept/decline
  - [ ] Save message to sessions
  - [ ] Create notification for student immediately
- [ ] Update `tutor/my_sessions.php` similarly for consistency

## 4) Session Start + Room Flow (4.3)
- [ ] Add tutor "Start Session" action in `tutor/my_sessions.php` for confirmed sessions
- [ ] Create `tutor/session_room.php`:
  - [ ] Secure room view
  - [ ] Toolbar buttons: mute/unmute, share screen, record (UI control level)
- [ ] Ensure meeting link handling remains functional

## 5) Grading & Feedback Flow (4.4)
- [ ] Create `tutor/feedback.php`:
  - [ ] Grade + comments form after session completion
  - [ ] Save feedback in `session_feedback`
- [ ] Update `tutor/complete_session.php` redirect to feedback form
- [ ] Add student progress visibility in `student/dashboard.php` or `student/resources.php`

## 6) Admin Functions (5.1, 5.2, 5.3)
- [ ] Update `admin/manage_users.php`:
  - [ ] Suspend/unsuspend account action
  - [ ] Reset password action (temporary password flow)
- [ ] Create `admin/security_logs.php` with date/user filters
- [ ] Update `admin/dashboard.php` nav link for Security Logs
- [ ] Update `admin/reports.php`:
  - [ ] Ensure visual chart section
  - [ ] Ensure download user reports action
  - [ ] Ensure download session reports action

## 7) Critical-Path Validation
- [ ] Tutor profile save with image/qualifications/availability/rate/max sessions
- [ ] Accept/decline booking with short message and student notification creation
- [ ] Start session button opens session room view
- [ ] Complete session redirects to feedback and saves grade/comments
- [ ] Student can view feedback/progress
- [ ] Admin suspend/reset, security log filtering, reports downloads
