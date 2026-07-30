# 06 — Page map

## By role

### Visitor (`V`)

| Page | Function |
|------|----------|
| `signup.php` | Sign-in / sign-up / forgot password |
| `index.php` | Dashboard: upcoming / previous / ongoing / create |
| `newmeeting2.php` | Create meeting (self-service) |
| `meetingdetails.php` | Meeting detail |
| `generate_gate_pass.php` | Gate pass (when allowed) |
| `myProfile.php` | Profile |
| `updatePersonalInfo.php` | Edit profile |
| `updateProfilePic.php` | Profile photo |
| `changePassword.php` | Change PIN |

### Employee / Nuvocan (`E`)

| Page | Function |
|------|----------|
| `signup.php` | Sign-in as Nuvocan |
| `index.php` | Dashboard + **Approvals** tab |
| `newmeeting2.php` | Create / host meetings |
| Email → `vmsdb/approve_meeting.php` | Approve / disapprove / forward |
| `approveMeeting.php` | Quick approve (legacy/simple) |
| Profile pages | View largely read-only for employees |

### Security (`S`)

| Page | Function |
|------|----------|
| `index.php` → redirect | Lands on `watchman.php` |
| `watchman.php` | Primary gate-in/out & gear |
| `watchman2.php` | Issue Gear nav target / variant |
| `newMeeting.php` | Walk-in meeting create |
| `ongoingMeeting.php` | Ongoing + forward |
| `blacklist.php` | Blacklist CRUD |
| `phoneDir.php` | Location meeting directory |
| `gearissue.php` | Gear issue handler |
| `watchman3.php`, `watchman4.php`, `watvchman6.php` | Experimental / alternate UIs |

Typical security sidebar intent: **Issue Gear → Gate-In & Gear → New Meeting → Ongoing → Blacklist**.

---

## Auth & system pages

| Page | Function |
|------|----------|
| `checkLogin.php` | Login POST |
| `processSignUp.php` | Registration POST |
| `activateAccount.php` | Email activation |
| `processGetPassword.php` | Recover password email |
| `logout.php` | End session |
| `liveStatus.php` | Presence heartbeat/decay |
| `header.php` / `footer.php` | Layout + session guard |
| `db.php` | Bootstrap DB + session |

---

## Meeting utilities

| Page | Function |
|------|----------|
| `upcomingmeeting.php` | Older standalone upcoming list |
| `filtermeeting.php` | Filtered meeting list |
| `cancelMeeting.php` | Cancel one |
| `editMeeting.php` | Edit meeting/visitor |
| `registereduser.php` | Legacy create path |
| `invoice.php` | Invoice name lookup (peripheral) |

---

## Assets / non-app

| Path | Notes |
|------|--------|
| `Star-VMS/` | Theme copy — not wired as app |
| `docs/` | Azia documentation |
| `elem-*.html`, `form-elements.html`, `chart-chartjs.html`, `login.html` | Template demos |
| `css/`, `js/`, `scss/`, `images/` | Front-end assets |
| `PHPMailer/`, `phpqrcode/` | Libraries |
| `database/vms.sql` | Empty of tables — regenerate from MySQL |

---

## Quick “where do I change X?”

| Goal | Start here |
|------|------------|
| Login rules | `checkLogin.php`, `tbl_logindetail` |
| New self-service meeting | `newmeeting2.php` → `vmsdb/submit.php` |
| New security walk-in | `newMeeting.php` → `vmsdb/newwmeeting.php` |
| Approval email UI | `vmsdb/approve_meeting.php` |
| Gate-in modal | `watchman.php` → `vmsdb/update_meeting_details.php` |
| Gate-out / gear return | `vmsdb/update_gate_out.php` |
| Gate pass QR | `generate_gate_pass.php` |
| Blacklist | `blacklist.php` |
| Emails | `emailSMTP.php` (+ duplicates inside some `vmsdb` scripts) |
| Employee master | `tbl_nuvo_employee` / `copyAllEmpfromAMS()` in `db.php` |
