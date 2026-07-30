# Minutes of Meeting (MOM)

| Field | Detail |
|-------|--------|
| **Subject** | DIRE — UI Review: Contract Workman Gate Entry / Exit Pass (Early Out & Late IN Module) |
| **Date** | 20 July 2026 |
| **Duration** | ~54 minutes |
| **Prepared by** | Ashiq Ali |
| **Reference** | `resources/requirment.md`, CLGP UI prototype (`clgp/`) |

---

## Attendees

| Name | Role |
|------|------|
| Moloy Chatterjee | Business owner / reviewer |
| Ashiq Ali | Development — UI demo & notes |
| Shivam Tiwari | Project coordination |
| Pramod Pathak | Security stakeholder (joined mid-session) |

---

## 1. Meeting objective

Review the **CLGP UI prototype** for the **Early Out / Late IN** tracking module (paperless process for contract workmen), aligned with the broader requirement to integrate contractor management within the Visitor Management System look-and-feel.

Ashiq demonstrated the Admin panel prototype: Dashboard, Approval Matrix, Contractor Master, and Users & Roles.

---

## 2. Key decisions

### 2.1 Application scope & naming

| # | Decision |
|---|----------|
| 1 | Rename **Contract Labour** → **Workman** across the application. |
| 2 | Application title: **Contract Workman Gate Entry / Exit Pass** (main gate naming to be updated). |
| 3 | This is a **standalone application** with its own login — **not** embedded inside the existing VMS login selector. |
| 4 | UI theme based on existing **VMS / Azia Admin** — **approved for prototype phase**; changes can be revisited later. |
| 5 | Current build is a **working prototype**; after sign-off, wire to real database and fields. |

### 2.2 Phasing (important)

| Phase | Scope | Status |
|-------|--------|--------|
| **Phase 1 (current — target: this month)** | **Early Out / Late IN** application only — Time Office, Security, Supervisor → N-1 → HOD workflow | In UI review |
| **Phase 2 (later)** | **Gate Pass Management** (Insider / Outsider vendor passes, documents, 6-month validity per `requirment.md`) | **Separate module** — start after Phase 1 is complete |

> Moloy confirmed: *“What you built for gate pass — build that separately. First finish Early Out / Late IN, then come to gate pass.”*

### 2.3 Admin dashboard

| # | Decision |
|---|----------|
| 1 | Split dashboard into **IN** and **OUT** sections (bifurcation). |
| 2 | Default view: **today’s data only** (single day) to avoid confusion. |
| 3 | Optional **date filter** for historical view. |
| 4 | Admin may see aggregate counts (contractors, approval rules, pending passes). |
| 5 | Future: report download, contractor block/deactivate (not hard delete). |

### 2.4 Approval Matrix (master for approvers)

| # | Decision |
|---|----------|
| 1 | Purpose: define **who approves** per **Plant + Department** — not related to IN/OUT access type. |
| 2 | Fields: Plant → Department → Approval Step → select Employee (from AMS / employee master). |
| 3 | Approval steps: **Supervisor → N-1 → HOD** — replace earlier “N+1” label with **N-1**; Supervisor and N-1 are separate steps. |
| 4 | Employee name is **selectable from dropdown** (filtered by plant/department); code auto-fills — admin does not type codes manually. |
| 5 | **Supervisor is the first approver**, then **N-1**, then **HOD**. |
| 6 | Saved rules appear on the **right panel** with **Edit** option. |
| 7 | **No Access Type / Vendor Type** on Approval Matrix screen — removed as irrelevant here. |

### 2.5 Contractor Master

| # | Decision |
|---|----------|
| 1 | Separate screen from Approval Matrix — **contractor database**. |
| 2 | **Vendor Type** (3 values): **Supply**, **Temporary**, **Measurement** — **Permanent removed** (legal/compliance: cannot show permanent contractor in pan-India rollout). |
| 3 | **Company Name** (not “Contractor Name” as primary label). |
| 4 | **Contact Person** = Supervisor name. |
| 5 | Mandatory fields: Company Name, Contractor Name, Contact Person (Supervisor), Email, Contractor Mobile, Supervisor Mobile — **all mandatory**. |
| 6 | **No extra dynamic fields** per vendor type (keep form simple). |
| 7 | **Edit** and **Deactivate** supported; **do not hard-delete** — use block/deactivate to preserve history. |
| 8 | **Re-activation** of deactivated contractor/vendor may require **HR Head approval**. |
| 9 | Decline/rejection reason visible to **Time Office** and **Security** (portal + email). |

### 2.6 User roles & login

| Role | Purpose in Phase 1 |
|------|-------------------|
| **Admin** | Masters, users, dashboard, reports, deactivate contractors |
| **Supervisor** | First approver |
| **N-1** | Second approver |
| **HOD** | Final approver; can view department IN/OUT / late-early stats |
| **Time Office** | Creates Early Out / Late IN applications **on behalf of workmen**; attests after Security approval |
| **Security** | Receives workman at gate; approves; coordinates with Time Office |
| **HR Head** | Approval for first-time activation / re-activation scenarios |

| # | Decision |
|---|----------|
| 1 | **No Contractor login** in this module — contractor does not use the system directly. |
| 2 | When a user is created in Approval Matrix, system sends **email with login ID & password** (same pattern as VMS). |
| 3 | User can log in with assigned credentials — separate role assignment screen is for admin visibility only. |

### 2.7 Early Out / Late IN — core business flow (Phase 1)

**Problem statement:** Workmen currently use paper forms for late entry and early exit; supervisors/HOD must be found manually, causing delays at the gate.

**Agreed process:**

```
Workman arrives at Security
    → Security refers to Time Office
    → Time Office creates application (Late IN or Early Out) on workman’s behalf
        (select workman, plant, department, reason)
    → Approval: Supervisor → N-1 → HOD
    → After HOD approval: visible to Time Office + Security
    → Time Office attests / completes gate IN or OUT
    → Security executes gate action
```

| # | Decision |
|---|----------|
| 1 | Rename contractor-side screens to **Early Out Application** / **Late Entry Application** — **not** “Create Gate Pass” in this phase. |
| 2 | Application includes **reason** field. |
| 3 | Approval chain for Early/Late: **Supervisor → N-1 → HOD** (not N+1). |
| 4 | **Supervisor does not** need dashboard for IN/OUT stats — that view is for **Time Office** (and Security where needed). |
| 5 | Time Office can submit **on behalf of any workman** (select name → auto-fill details). |

**Real example cited:** Moloy arrived late → Time Office (Ashiq) creates Late Entry application in Moloy’s name → approval chain runs → gate entry allowed.

---

## 3. Mapping to `requirment.md`

| Requirement | Meeting outcome |
|-------------|-----------------|
| Early Out & Late IN tracking | **Confirmed — Phase 1 priority; detailed flow agreed** |
| Contractor & vendor management | **Contractor Master agreed** (Supply / Temporary / Measurement) |
| UI reference to VMS | **Approved** (Azia theme, separate app URL) |
| Gate pass — Insider & Outsider vendors | **Deferred to Phase 2** (separate build) |
| Gate pass 6-month validity & renewal | **Not discussed** — to cover in gate-pass phase |
| Outsider N+1 approval | **Not discussed for gate pass**; for Early/Late, approvers are **Supervisor → N-1 → HOD** |
| Admin panel & RBAC | **Confirmed** with roles listed above |
| Process flow Word document | **Still pending** — to be shared with dev team |

---

## 4. Action items

| # | Action | Owner | Target |
|---|--------|-------|--------|
| 1 | Update UI labels: Workman, Gate Entry/Exit Pass, Access Type → **Entry / Exit** | Ashiq Ali | Next build |
| 2 | Split Admin dashboard: **IN / OUT**, default **today only** + date picker | Ashiq Ali | Next build |
| 3 | Approval Matrix: Plant, Dept, **Supervisor / N-1 / HOD**, employee dropdown, remove Access Type | Ashiq Ali | Next build |
| 4 | Contractor Master: Supply / Temporary / Measurement, mandatory fields, edit/deactivate | Ashiq Ali | Next build |
| 5 | Replace contractor “Create Pass” with **Early Out / Late Entry** application flow | Ashiq Ali | Next build |
| 6 | Implement roles: Admin, Supervisor, N-1, HOD, Time Office, Security, HR Head | Ashiq Ali | Next build |
| 7 | Email notification on user creation (login credentials) | Ashiq Ali | Next build |
| 8 | Share **MOM** with team; collect naming corrections via **email reply** | Ashiq Ali | 21 July 2026 |
| 9 | Share **Gate Pass process Word document** with dev team | Moloy / business | ASAP |
| 10 | **Weekly review meetings** (min. 2× per week); inform Moloy **2–3 hours in advance** | Shivam / team | Ongoing |
| 11 | Complete **Phase 1 (Early Out / Late IN)** for go-live readiness | Ashiq Ali | **July 2026** |
| 12 | Plan **Phase 2 Gate Pass module** (Insider/Outsider) after Phase 1 sign-off | Team | August 2026+ |

---

## 5. Open points / clarifications needed

1. **Grace minutes** for Late IN / Early OUT (e.g. shift start 08:00, grace till 08:15?) — not finalized.
2. **Gate pass Insider vs Outsider** definition and full approval chain — for Phase 2 only.
3. **6-month validity** enforcement and renewal UX — for Phase 2 only.
4. **Supporting documents** upload for Insider vendor passes — for Phase 2 only.
5. **Medical / Safety** steps in gate-pass workflow — not covered in this meeting.
6. Standard **vendor type** list may change for pan-India rollout — keep configurable.

---

## 6. Next steps

1. Ashiq to incorporate all UI changes from this review and share updated prototype link.
2. Team to reply on the meeting email with any corrections to naming or flow.
3. Next sync: **twice weekly** (or ad-hoc with 2–3 hr notice to Moloy).
4. Focus delivery: **Early Out / Late IN live this month**; gate pass module follows as separate track.

---

## 7. Meeting closure

- Pramod (Security) reviewed dashboard and login flow — no additional changes requested; asked to continue demo.
- Shivam requested formal MOM circulation for alignment.
- Moloy: *“Good progress”* — proceed with agreed changes.

---

*Please reply to this MOM with corrections or additions within 2 business days so development can proceed without ambiguity.*
