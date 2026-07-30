# VMS — Project Logic

Internal documentation for **Nuvoco Visitor Management System (VMS)**.

| Document | Contents |
|----------|----------|
| [01_overview.md](01_overview.md) | Purpose, users, tech stack, high-level lifecycle |
| [02_architecture.md](02_architecture.md) | Folders, config, sessions, dual DB/API layout |
| [03_database.md](03_database.md) | Inferred schema, relationships, status values |
| [04_business_flows.md](04_business_flows.md) | Auth, meetings, gate, gear, blacklist, email |
| [05_api_reference.md](05_api_reference.md) | `vmsdb/` endpoints and root handlers |
| [06_page_map.md](06_page_map.md) | UI pages → features by role |

**App:** Visitor access, meeting scheduling, gate-in/out, PPE gear, QR gate passes  
**Production URL (from code):** `https://vms.nuvoco.in/vms/`  
**Schema note:** `database/vms.sql` currently has no `CREATE TABLE` statements; schema in these docs is inferred from PHP SQL.
