# 02 — Architecture

## Layout (classic PHP + AJAX API)

```
vms/                          # Session UI pages (include db.php + header/footer)
├── db.php                    # Session, mysqli ($mysqli / $mysqli2), helpers
├── header.php / footer.php   # Auth gate + chrome
├── *.php                     # Feature pages (dashboard, meetings, watchman, …)
├── vmsdb/                    # Backend/API scripts (own db.php, CORS *)
│   ├── db.php                # API connection (separate DB user)
│   ├── faces/                # Visitor/meeting photos (WebP)
│   └── *.php                 # JSON/HTML endpoints
├── PHPMailer/                # Mail library (also copied under vmsdb/)
├── phpqrcode/                # QR generation
├── css/ js/ scss/            # Azia theme assets
├── database/                 # Intended schema dump (currently empty of tables)
├── docs/                     # Azia template documentation
├── Star-VMS/                 # Unused theme subset
└── project_logic/            # This documentation
```

## Two database connection points

| File | Typical use | Notes |
|------|-------------|--------|
| Root `db.php` | All session pages | `$mysqli` (localhost), `$mysqli2` (secondary host). Starts session; sets `$ProjectName`, `$appURL`. |
| `vmsdb/db.php` | AJAX/API scripts | Separate credentials; sets `Access-Control-Allow-Origin: *`. |

**Important:** Credentials are hardcoded in source. Prefer env/config outside the repo for any new work; do not commit new secrets.

## Session & page chrome

1. Pages include `header.php` → `db.php` → `session_start()`.
2. If `$_SESSION['loginType']` is empty → redirect to `signup.php`.
3. Role-specific `$userId` / name / email derived from `userDetails`:
   - `V` / `S` → `tbl_user` fields (`id`, `userEmail`, …)
   - `E` → `tbl_nuvo_employee` fields (`empCode`, `empBusiEmail`, …)
4. `autoSetLiveStatus($loginId)` marks user active; `liveStatus.php` is polled from the header for presence decay.

## Request patterns

| Pattern | Example |
|---------|---------|
| Form POST → PHP handler → redirect | `signup.php` → `checkLogin.php` → `index.php` |
| Page AJAX → `vmsdb/*.php` JSON | Dashboard tabs → `fetch_*_meetings.php` |
| Email deep link (weak auth) | Host opens `vmsdb/approve_meeting.php?meeting_id=…` |
| File/serve | `serve_image.php`, gate-pass QR under `qrcodes/` |

Production front-end often hardcodes `https://vms.nuvoco.in/vmsdb/...` rather than relative paths.

## Employee sync helper (disabled)

`copyAllEmpfromAMS()` in `db.php` can:

1. Once per day (`tbl_todayload`): truncate/copy `tbl_nuvo_employee` from `nuvoco_emp.tbl_nuvo_employee`
2. Auto-create `tbl_logindetail` rows (`logType='E'`) for active emails missing logins
3. Recompute `empAge` from `empDOB`

Call is **commented out** (`// copyAllEmpfromAMS();`).

## Libraries & shared helpers

| Component | Role |
|-----------|------|
| `emailSMTP.php` | `sent_email($to, …)` via Office365 SMTP |
| `phpqrcode` | Used by `generate_gate_pass.php` / `generate_qr.php` |
| Dual `PHPMailer/` trees | Root + `vmsdb/PHPMailer` — avoid drifting duplicates when changing mail code |

## Architectural quirks

- Not layered (controllers/services); business logic lives inside page and endpoint scripts.
- Column typo **`meetingAprroved`** is used everywhere — keep the misspelling when writing SQL.
- Security UI has multiple watchman variants; navigation mainly targets `watchman.php` / `watchman2.php`.
- Self-service meeting create (`newmeeting2.php` → `submit.php`) vs security walk-in (`newMeeting.php` → `newwmeeting.php`) are separate code paths.
