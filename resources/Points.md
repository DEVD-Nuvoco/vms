# LIEO (Late IN / Early Out) — Email recipients & confirmed process

SMTP uses shared VMS `emailSMTP.php` (`sent_email`).

Every LIEO email includes a green **Click here to login** button plus the full URL:
`https://vms.nuvoco.in/lieo/login.php`
(override with `LIEO_PUBLIC_BASE_URL` if the server path differs).

> Branding: **LIEO** = Late IN / Early Out. Folder/URL path is `/lieo`. LIEO login is **separate from VMS**.

---

## Confirmed process (Aug 2026)

### Roles & flow

- **Section Incharge** creates applications (Time Office does **not** create).
- Chain: **Section Incharge → Time Office → N-1 → HOD → Security** (gate close + remark).
- Contractor **Supervisor** is **not** an approver; information email only on create.
- Department-wise matrix: Section Incharge, Time Office, N-1.
- Plant-wise matrix (one each): HOD, Security, HR Head.



### Ownership

- **Admin** only manages Time Office users (add / edit / delete).
- **Time Office** manages: Approval Matrix (except Time Office role), plant departments, Contractor Master, Notification Mail. Can add / edit / delete matrix rows they own. Cannot assign Time Office for other departments.
- Department list: plant master if present, else AMS.
- Department data isolation: users see only their department’s data.



### Dashboards & tracking

- Dashboards: Section Incharge, Time Office, N-1, HOD.
- Track Application: status, trail, remarks; filters plant / department / date / status / workman / contractor.



### Contractor reactivation notification (Time Office menu)

- Heading: **HR Head Contractor Activation Notification**.
- Multiple CC emails per plant (example RCP: `ABC@gmail.com`, `PQR@gmail.com`).
- Mail: **TO** HR Head, **CC** plant notification emails, from Time Office request **until HR Head approve/reject**.

---



## A. When a **user / login is created** (Approval Matrix)


| Event                                   | Who receives email                 | Content                     |
| --------------------------------------- | ---------------------------------- | --------------------------- |
| New matrix assignee (no LIEO login yet) | That employee’s **business email** | Login ID + default password |
| Matrix update for **existing** login    | **No** new credentials email       | Profile updated only        |


Roles that can get credentials mail (when first created): Section Incharge, Time Office, N-1, HOD, Security, HR Head.

---



## B. Late IN / Early Out — **application flow**


| #   | Event                          | Who receives email                       | Source                               |
| --- | ------------------------------ | ---------------------------------------- | ------------------------------------ |
| 1   | **Created** (Section Incharge) | **Time Office** (pending approval)       | Approval Matrix                      |
| 1b  | Same event                     | **Contractor Supervisor**                | Contractor master (information only) |
| 2   | Time Office **approves**       | **N-1**                                  | Approval Matrix (dept)               |
| 3   | N-1 **approves**               | **HOD**                                  | Approval Matrix (plant)              |
| 4   | HOD **approves**               | **Security**                             | Approval Matrix (plant)              |
| 5   | Anyone **rejects**             | **Section Incharge** who created the app | `created_by`                         |
| 6   | Security **closes at gate**    | **Section Incharge** who created the app | `created_by`                         |


Flow:

`Create (SI) → Time Office → N-1 → HOD → Security closes`

Application numbers: `LIEO-YYYYMMDD-####`.

---



## C. Contractor reactivation


| Event                                | TO                           | CC                           |
| ------------------------------------ | ---------------------------- | ---------------------------- |
| Time Office **Request Reactivation** | Plant **HR Head**            | Plant Notification Mail list |
| HR **Approve / Reject**              | Time Office (requester) + HR | Same plant CC list           |


---



## D. Not emailed (by design unless listed above)

- Change password  
- Contractor create / deactivate (except reactivation flow)

If matrix email is missing for the next step, that notification is skipped (action still saves).

---



## E. Contractor Master fields

- Contractor Name  
- Contractor Email  
- Contractor Mobile Number  
- Contractor Type  
- Supervisor  
- Supervisor Mobile Number


| **Role**         | **Email**                    |
| ---------------- | ---------------------------- |
| Admin            | `lieo.admin@local.test`      |
| Section Incharge | `lieo.si@local.test`         |
| Time Office      | `lieo.timeoffice@local.test` |
| N-1              | `lieo.n1@local.test`         |
| HOD              | `lieo.hod@local.test`        |
| Security         | `lieo.security@local.test`   |
| HR Head          | `lieo.hr@local.test`         |





database/run_lieo_rename_clgp_tables.php
database/run_clgp_section_incharge_flow.php




