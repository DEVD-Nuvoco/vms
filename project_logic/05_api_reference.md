# 05 — API / backend reference

Primary backend folder: **`vmsdb/`**. Most endpoints return JSON; `approve_meeting.php` is an HTML action page. Root also has a few POST handlers used by forms.

## Connection

- Include: `vmsdb/db.php` (`$conn` mysqli, CORS `*`).
- Many scripts enable `display_errors` — noisy in production.

## Auth & account (`vmsdb/`)

| File | Purpose |
|------|---------|
| `login.php` | JSON login by username/password; resolves visitor vs employee |
| `register.php` | API visitor signup; emails password |
| `requestOtp.php` | Replace password with OTP and email it |
| `update_password.php` | Change password (older string SQL) |
| `update_password2.php` | Change password (prepared statements) |
| `validate_password.php` | Verify old password for a user id |
| `update.php` | Update visitor profile fields |
| `upload_profile.php` | Profile WebP upload |

## Meetings — create / search

| File | Purpose |
|------|---------|
| `submit.php` | Create meeting (dashboard / `newmeeting2.php`) |
| `newwmeeting.php` | Create meeting (security `newMeeting.php`) + gear + group |
| `search_employee.php` | Active employee typeahead (`searchIndex`) |
| `search_users.php` | Visitor search (`userBlock='f'`) |
| `fetch.php` | User fetch helper |

## Meetings — lists (dashboard)

| File | Purpose |
|------|---------|
| `fetch_upcoming_meetings.php` | Upcoming Approved meetings |
| `fetch_previous_meetings.php` | Past visits with gate-out |
| `fetch_ongoing_meetings.php` | Currently in time window |
| `fetch_meeting_approvals.php` | Host pending (`On Hold`) |

## Meetings — status / detail

| File | Purpose |
|------|---------|
| `approve_meeting.php` | Email-link Approve/Disapprove UI + updates |
| `update_meeting_status.php` | Set `meetingAprroved` + notify |
| `get_meeting_details.php` | Meeting fields JSON |
| `update_ongoing_meeting.php` | Forward meeting; log `tbl_meeting_forwards` |
| `cancelMeetings.php` | Cancel all today’s meetings at a location |

## Gate & gear

| File | Purpose |
|------|---------|
| `update_meeting_details.php` | Gate-in + gear rows + photo |
| `update_gate_in.php` | Toggle/simple gate_in / gate_out |
| `update_gate_out.php` | Gate-out + mark gear collected/received |
| `get_gear_issued.php` | List gear for a meeting |

## Media

| File | Purpose |
|------|---------|
| `serve_image.php` | Serve file from `faces/` (sanitize `image` param carefully) |

## Misc / unclear

| File | Notes |
|------|--------|
| `index.php` | Placeholder / unused entry |
| `xyz.php` | Scratch / unknown |

---

## Root PHP handlers (non-`vmsdb`)

| File | Purpose |
|------|---------|
| `checkLogin.php` | Session login |
| `processSignUp.php` | Visitor registration |
| `activateAccount.php` | Activation |
| `processGetPassword.php` | Email current password |
| `approveMeeting.php` | Quick Approved update |
| `cancelMeeting.php` | Cancel one meeting |
| `editMeeting.php` | Edit meeting / visitor |
| `gearissue.php` | Issue gear POST |
| `capture_image_upload.php` | Camera capture upload |
| `generate_gate_pass.php` | Printable pass + QR |
| `generate_qr.php` | Generic QR |
| `search_employee.php` | Root sibling search (may duplicate `vmsdb`) |
| `liveStatus.php` | Presence decay job |
| `logout.php` | Destroy session |
| `emailSMTP.php` | Shared mail function |

---

## Security notes for API work

1. Prefer prepared statements (some endpoints still concatenate user input).
2. Do not rely on CORS `*` + missing auth for sensitive mutations.
3. `approve_meeting.php?meeting_id=` is effectively **link-secret** auth — anyone with the URL can act.
4. Avoid logging or committing SMTP/DB secrets; rotate if exposed.
5. Validate/sanitize paths in `serve_image.php` to prevent traversal.
