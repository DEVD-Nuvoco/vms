# 04 — Business flows

## Authentication

### Login

1. UI: `signup.php` (login form) posts to `checkLogin.php`.
2. Inputs: `emailVal`, `passVal`, `logType` (`visitor` | `nuvocan` | `security`).
3. Match `tbl_logindetail` where `activationStatus = 't'` and password equals submitted value.
4. Role checks:
   - **nuvocan:** email in active `tbl_nuvo_employee.empBusiEmail` → session from employee row; `loginType` from login row (`E`).
   - **visitor:** `logType = 'V'` and email in `tbl_user` with `userBlock != 't'`.
   - **security:** `logType = 'S'` with same user-table check.
5. Success → `index.php`. Security users are redirected onward to `watchman.php` from the dashboard.

JSON alternate: `vmsdb/login.php` (no PHP session; returns user id/name for other clients).

### Signup (visitors)

1. `signup.php?action=signup` → `processSignUp.php`.
2. If active login already exists → email existing password via `sent_email()`.
3. Else insert/update `tbl_logindetail` (`logType='V'`, `activationStatus='f'`, activation code + `enCryptUrl`, random password) and `tbl_user`.
4. Email activation link: `activateAccount.php?acDet={md5}` plus code.

### Activate

`activateAccount.php` matches `enCryptUrl` + `activationCode` with `activationStatus='f'`, sets `activationStatus='t'`, clears code → `signup.php`.

### Password recovery / change

| Path | Behavior |
|------|----------|
| `processGetPassword.php` | Emails current plaintext password |
| `vmsdb/requestOtp.php` | Overwrites `userPassword` with 6-digit OTP and emails it |
| `changePassword.php` | Visitors/security; employees blocked from this UI |
| `vmsdb/update_password.php` / `update_password2.php` | API password updates |

### Logout

`logout.php` → `session_destroy()` → `signup.php`.

---

## Meeting creation

### A. Self-service (visitor / employee)

- UI: `newmeeting2.php`
- API: `vmsdb/submit.php`
- Inserts `meetings` (location, host, visitor, times, visit type/purpose, `meetingDays`).
- Optional group: create/find `tbl_user`, stub login (`activationStatus='f'`), insert `group_members`.
- Emails host with link to `vmsdb/approve_meeting.php?meeting_id=…`.

### B. Security walk-in

- UI: `newMeeting.php`
- API: `vmsdb/newwmeeting.php`
- Richer intake: vehicle/baggage, safety induction, gear, camera photo.
- May create visitor + **auto-activated** login (`activationStatus='t'`).
- Inserts meeting + `gear_issued` + group members; face image as `{meetingId}.webp` under `faces/`.

Legacy path: `registereduser.php` also creates meetings.

---

## Approval & status

| Mechanism | File | Notes |
|-----------|------|--------|
| Email deep link UI | `vmsdb/approve_meeting.php` | Approve / Disapprove; set `where_meeting`; optional forward |
| Dashboard Approvals tab | `vmsdb/fetch_meeting_approvals.php` | Host’s `On Hold` meetings |
| Quick approve | `approveMeeting.php` | Sets Approved |
| Generic status | `vmsdb/update_meeting_status.php` | Updates `meetingAprroved` + notify |

Statuses: **On Hold** → **Approved** | **Disapproved** | **Canceled**.

Cancel:

- Single: `cancelMeeting.php`
- Bulk (today @ location): `vmsdb/cancelMeetings.php` (+ email)

Edit times/visitor: `editMeeting.php`.  
Forward ongoing: `vmsdb/update_ongoing_meeting.php` (+ `tbl_meeting_forwards`).

---

## Gate-in / gate-out & gear

Primary UI: **`watchman.php`** (location-filtered meetings, blacklist marquee, gate modal).

| Action | Endpoint |
|--------|----------|
| Gate-in + gear + token + ID cards + photo | `vmsdb/update_meeting_details.php` |
| Simple gate-in/out toggle | `vmsdb/update_gate_in.php` |
| Gate-out + gear return | `vmsdb/update_gate_out.php` |
| Issue gear only | `gearissue.php` / related watchman UI |

`meetingDays`:

- `S` — single-day visit rules
- `M` — multi-day; gate-out logic differs in `update_gate_out.php`

Visitor IDs matching pattern `^3000[0-9]{4}$` are treated as **employee visitors** and joined to `tbl_nuvo_employee` in watchman queries.

---

## Gate pass & QR

- `generate_gate_pass.php` — printable pass; QR PNG at `qrcodes/{meeting_id}.png` via phpqrcode.
- `generate_qr.php` — generic QR from POST `qrData`.
- Dashboard can POST meeting JSON into the gate-pass flow.

---

## Blacklist

- UI: `blacklist.php` (security only).
- Ban existing `user_id` or custom name + photo → `tbl_blacklist_person`.
- Unban deletes row.
- Surfaced on watchman as scrolling marquee.

---

## Profile & presence

- View: `myProfile.php` (+ `myProfileLeft.php` partial).
- Edit visitor info: `updatePersonalInfo.php` (employees generally cannot).
- Photo: `updateProfilePic.php` / `vmsdb/upload_profile.php` → `{userId}_profile.webp`.
- Presence: `autoSetLiveStatus` + `liveStatus.php` polling from `header.php`.

---

## Email notifications

Central helper: `emailSMTP.php` → `sent_email()`.

Typical triggers:

- Signup / existing-account password share
- Forgot password / OTP
- New meeting → host approval link
- Approve / disapprove / forward
- Bulk cancel by location

SMTP is Office 365 (`smtp.office365.com:587`). Credentials are hardcoded in multiple files — treat as a security debt.

---

## Phone directory

`phoneDir.php` is a security-style **meeting directory by location/filters**, not a classic corporate phone book.
