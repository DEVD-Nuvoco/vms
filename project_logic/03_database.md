# 03 — Database

> `database/vms.sql` (as of analysis) dumps events/routines only — **no table definitions**.  
> Schema below is **inferred from PHP** (`INSERT`/`SELECT`/`UPDATE`/`JOIN`). Treat as living documentation until a real dump is restored.

## Entity relationship (logical)

```
tbl_logindetail.userName  ↔  tbl_user.userEmail
                         ↔  tbl_nuvo_employee.empBusiEmail

meetings.visitor_id      →  tbl_user.id  OR  employee empCode (special cases)
meetings.meetperson_id   →  tbl_nuvo_employee.empCode

gear_issued.meeting_id   →  meetings.meeting_id
group_members.meeting_id →  meetings.meeting_id
group_members.registered_user_id → tbl_user.id

tbl_blacklist_person.user_id → tbl_user.id (nullable for custom bans)
tbl_meeting_forwards.meeting_id → meetings.meeting_id
```

## Tables

### `tbl_logindetail` — credentials & activation

| Column | Usage |
|--------|--------|
| `id` | PK; session `loginId` |
| `logType` | `V` visitor, `E` employee, `S` security |
| `activationStatus` | `t` active, `f` pending |
| `activationCode` | Signup OTP/code |
| `enCryptUrl` | `md5(activationCode)` for activation links |
| `userName` | Login email |
| `userPassword` | Stored as **plaintext** 6-digit PIN (legacy) |
| `profilePic` | Profile pic flag/path |
| `liveStatus` | `a` active, `p` passive, `d` off |
| `lastLiveDateTime` | Presence heartbeat |

### `tbl_user` — visitors & security users

Common fields used in code:  
`id`, `userName`, `userEmail`, `userMobile`, `userGender`, `userAge`, `userCompany`, `userDesignation`, `userCity`, `userState`, `userAddress`, `userZIPCode`, `userDOB` / `uDob`, `userCreatedOn`, `userIP`, `userBlock` (`t`/`f`).

Blocked users (`userBlock = 't'`) cannot log in as visitor/security.

### `tbl_nuvo_employee` — hosts (AMS / HR sync)

`empCode`, `empName`, `empBusiEmail`, `empBusiMobile`, `empGender`, `empAge`, `empDOB`, `empDesignation`, `empDepartment` / `Department`, `empWorkLocation`, `empLocation`, `empStatus`, `searchIndex`.

Login as Nuvocan requires Active status and matching business email.

### `meetings` — core visit record

| Column | Notes |
|--------|--------|
| `meeting_id` | PK |
| `visitor_id` | Visitor user id (or employee-style id in some gate flows) |
| `meetperson_id` | Host `empCode` |
| `meeting_person` | Host display name |
| `meeting_location` | Plant/site code |
| `where_meeting` | Venue (Office / Plant / custom) — often set on approval |
| `visit_type`, `visit_purpose` | Classification / reason |
| `meeting_start_time`, `meeting_end_time` | Schedule window |
| `meetingDays` | `S` single-day, `M` multi-day (affects gate-out rules) |
| `meetingAprroved` | Status (typo in column name) |
| `vehicle_permit`, `baggage_details` | Security intake |
| `safety_induction_done` | Safety flag |
| `gate_in`, `gate_out` | Timestamps; empty often `0000-00-00 00:00:00` |
| `token_number`, `meeting_details` | Token / ID card info |
| `extra_Item` | Gate-out extras |
| `forwarded_to` | Current forwarded host |
| `created_at` | Created timestamp |

**Approval statuses observed in code:** `On Hold`, `Approved`, `Disapproved`, `Canceled`.

### `gear_issued`

`gear_id`, `meeting_id`, `gear_name`, `gear_quantity`, `returnable`, `issued_at`, `collected_at`, `received`.

### `group_members`

`meeting_id`, `group_member_name`, `group_member_email`, `group_member_mobile`, `registered_user_id`, `created_at`.

Group create may also insert stub `tbl_user` + inactive `tbl_logindetail` rows.

### `tbl_blacklist_person`

`id`, `user_id` (nullable), `name`, `photo`, `added_by`.  
Shown as marquee on watchman UIs.

### `tbl_meeting_forwards`

`meeting_id`, `forwarded_to`, `forwarded_email`, `forwarded_by`.

### Supporting / peripheral

| Table | Role |
|-------|------|
| `tbl_todayload` | Day marker for employee sync (`loadDate`, `updateTime`) |
| `tbl_invoices` | Used by `invoice.php` (peripheral) |
| External `nuvoco_emp.tbl_nuvo_employee` | Source for AMS copy (when sync enabled) |

## Dashboard query intent (by tab)

| Tab | Typical filter |
|-----|----------------|
| Upcoming | `meeting_start_time > now`, `meetingAprroved = 'Approved'` |
| Previous | `gate_out` set, Approved |
| Ongoing | start ≤ now ≤ end, Approved |
| Approvals | Host’s meetings with `On Hold` |

Employee hosts often see meetings where they are visitor **or** host (`fetch_upcoming_meetings.php` / previous variants).

## Recommended follow-up

Export a full schema from MySQL (`SHOW CREATE TABLE` / mysqldump with `--no-data`) into `database/vms.sql` and reconcile this document.
