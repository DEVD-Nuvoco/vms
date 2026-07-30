# Action Plan — Contract Workman Entry/Exit Pass (CLGP)

**Goal:** Paperless **Late Coming / Early Going** tracking for workmen (Phase 1), then Gate Pass module (Phase 2).

**Sources:** [requirment.md](requirment.md), [Late Coming Early Going Flow.docx](Late%20Coming%20Early%20Going%20Flow.docx) → [Late Coming Early Going Flow.md](Late%20Coming%20Early%20Going%20Flow.md), [MOM - UI Review 20 July 2026.md](MOM%20-%20UI%20Review%2020%20July%202026.md), Gate pass process (PDF — Phase 2), `project_logic/`, `database/vms.sql`

**App title (user doc):** Access control for Contract Workman Entry/Exit Pass

---

## Locked decisions (Phase 1 — approved)

| # | Decision | Choice |
|---|----------|--------|
| 1 | Scope | **Phase 1:** Late Coming / Early Going only. **Phase 2:** Gate pass (Insider/Outsider) |
| 2 | Login model | Separate logins: Admin, **Supervisor**, N-1, HOD, Time Office, Security, HR Head — **no Contractor login** |
| 3 | Approval chain | **Supervisor → N-1 → HOD** (application workflow) |
| 4 | Approval matrix | Plant + Dept → Supervisor, **Head HR**, N-1, HOD (AMS dropdown; no Access Type on matrix) |
| 5 | Application creator | **Time Office** on workman’s behalf (reason required) |
| 6 | After approval | Time Office attests → **Security** executes gate IN/OUT |
| 7 | Contractor master | Vendor Name, Contractor Name, Email, 2 mobiles, Supervisor; types: Supply / Temporary / Measurement; deactivate only |
| 8 | UI | VMS/Azia theme; standalone app at `clgp/` |
| 9 | Credentials | Email login + password on user create; **change password on first login** |
| 10 | Prototype | Approved → wire to **MySQL** (`database/clgp_phase1.sql`) |

**Entry:** [`../clgp/login.php`](../clgp/login.php)

---

## Phase 1 — Deliverables

| Area | Deliverable |
|------|-------------|
| Admin dashboard | IN / OUT split; **today default** + date filter |
| Approval matrix | Supervisor, Head HR, N-1, HOD per plant/dept |
| Contractor master | Mandatory fields; deactivate; HR re-activation |
| Applications | Late Coming / Early Going create → approve → attest → gate |
| Roles & auth | DB-backed `tbl_logindetail` + `tbl_clgp_user` |
| Email | Credentials on create; notify on approval steps |

---

## UI structure (Phase 1)

### Admin (`clgp/admin/`)

| Page | Function |
|------|----------|
| `index.php` | IN/OUT dashboard (today + filter) |
| `approval_matrix.php` | Plant, Dept, Supervisor, Head HR, N-1, HOD |
| `contractors.php` | Vendor master |
| `users.php` | Create users + email credentials |

### Time Office (`clgp/timeoffice/` — new)

| Page | Function |
|------|----------|
| `index.php` | Today IN/OUT summary |
| `create_application.php` | Late Coming / Early Going + reason |
| `attest.php` | Attest after HOD approval |
| `applications.php` | History |

### Approver (`clgp/approver/`)

| Page | Function |
|------|----------|
| `pending.php` | Supervisor, N-1, HOD approve/reject |

### Security (`clgp/security/`)

| Page | Function |
|------|----------|
| `attendance.php` | Gate IN/OUT for attested applications |

### Phase 2 only (defer)

- `clgp/contractor/*` — gate pass create/renew
- Insider/Outsider, 6-month validity, documents

---

## Workflow

```
Time Office creates (Late Coming / Early Going + reason)
    → Supervisor → N-1 → HOD
    → Time Office attests
    → Security gate IN/OUT
```

---

## Database (Phase 1 migration)

File: **`database/clgp_phase1.sql`**

| Table | Purpose |
|-------|---------|
| `tbl_clgp_contractor` | Vendor master |
| `tbl_clgp_workman` | Workmen for selection |
| `tbl_clgp_approval_matrix` | Approvers per plant/dept/step |
| `tbl_clgp_user` | CLGP profile + role |
| `tbl_clgp_application` | Late Coming / Early Going requests |
| `tbl_clgp_application_approval` | Approval audit trail |
| `tbl_clgp_shift_master` | Shifts + grace (optional seed) |

Reuse: `tbl_logindetail`, `tbl_nuvo_employee` (AMS lookup). Do not break VMS visitor flow.

**Application statuses:**  
`Pending_supervisor` → `Pending_n1` → `Pending_hod` → `Approved` → `Attested` → `Gate_completed` | `Rejected`

---

## Build order (current)

1. ~~Working prototypes~~ ✓  
2. ~~**`database/clgp_phase1.sql`** + run on `vms` DB~~ ✓  
3. ~~DB layer (`clgp/includes/db.php`, `repository.php`) + refactor `config.php`~~ ✓  
4. ~~Production login + users + first-login password change~~ ✓  
5. ~~Admin: matrix, contractors, workmen, dashboard~~ ✓  
6. ~~Time Office: create + attest~~ ✓  
7. ~~Approver: Supervisor → N-1 → HOD~~ ✓  
8. ~~Security: gate execution~~ ✓  
9. Email on user create ✓ (step notifications optional polish)  
10. UAT  

**Run migration:** `php database/run_clgp_migration.php`  
**Optional demo seed:** `php database/seed_clgp_demo.php`  
**Login:** `http://localhost/vms/clgp/login.php` — admin@nuvoco.com / 123456 (must change password)  

---

## Open points

1. Grace minutes for Late Coming / Early Going (prototype: 08:15 / 17:00)  
2. Gate pass Insider/Outsider — Phase 2  
3. Signature fields on paper form vs digital reason-only in Phase 1  

---

## Success criteria (Phase 1)

- Time Office can create Late Coming / Early Going for any workman  
- Approval runs **Supervisor → N-1 → HOD** from matrix  
- Time Office attests; Security completes gate action  
- Admin IN/OUT dashboard shows today’s data  
- Contractor deactivate preserves history; HR re-activation enforced  
- VMS visitor module unchanged  
