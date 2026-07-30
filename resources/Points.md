# CLGP — Email recipients (who gets mail)

SMTP uses shared VMS `emailSMTP.php` (`sent_email`).

## A. When a **user / login is created** (Approval Matrix)

| Event | Who receives email | Content |
|-------|--------------------|---------|
| Admin saves **new** matrix assignee (no existing CLGP login) | That employee’s **business email** | Login ID + default password |
| Matrix update for **existing** login | **No** new credentials email | Profile updated only |

Roles that can get credentials mail (when first created): Time Office, Supervisor, N-1, HOD, Security, HR Head.

---

## B. Late Coming / Early Going — **application flow**

| # | Event | Who receives email | Source of address |
|---|--------|--------------------|-------------------|
| 1 | **Application created** (Time Office) | **Supervisor** for that plant/dept | Approval Matrix |
| 2 | Supervisor **approves** | **N-1** for that plant/dept | Approval Matrix |
| 3 | N-1 **approves** | **HOD** for that plant/dept | Approval Matrix |
| 4 | HOD **approves** | **Security** for that **plant** | Approval Matrix (plant-scoped) |
| 5 | Anyone **rejects** | **Time Office** who created the app | `created_by` user email |
| 6 | Security **closes at gate** | **Time Office** who created the app | `created_by` user email |

Flow (no attestation):

`Create → Supervisor → N-1 → HOD → Security closes`

---

## C. Contractor reactivation

| Event | Who receives email |
|-------|--------------------|
| Admin **Request Reactivation** | All active **HR Head** users |
| HR **Approve Reactivation** | (no email yet) |

---

## D. Not emailed (by design for now)

- Change password  
- Contractor create / deactivate (except reactivation request → HR)  
- Admin dashboard actions  

If matrix email is missing for the next step, that notification is skipped (action still saves in the portal).
