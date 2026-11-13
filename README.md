# EL-FAITH MICROFINANCE — Field Loan Collection & Management System

**Headquarters / Branch:** Wassa Powuako, Western Region, Ghana  
**Currency:** Ghanaian Cedi (`GHS` / `GH₵`) — Stored as integer pesewas (`100 pesewas = GH₵ 1.00`)  
**Technology Stack:** Raw PHP 8.2 + MySQL / MariaDB (Zero-dependency, Served directly via XAMPP)  
**System URL:** [http://localhost/ELFAITH%20MICROFINANCE/](http://localhost/ELFAITH%20MICROFINANCE/)

---

## 🏛️ System Overview

Rebuilt from the ground up based on the **Loan Collection App Planning Document**, this platform provides a high-reliability field collection workflow and executive microfinance management portal.

### Core Architectural Invariants:
1. **Users & Strict Role Separation**:
   - **Administrator**: Full visibility over loans, collections, route performance, daily cash reconciliations, overdue tracking, reports, and exports.
   - **Field Agent**: Mobile-first field route view showing assigned customers with due and overdue installments. Records collections with payment method (Cash or MoMo), timestamp, and field GPS capture.
   - **Customer**: Strictly **no login**. Interacts exclusively through automated SMS receipts, reminders, and field agent visits.
2. **Integer Currency Math (`pesewas`)**:
   - All financial amounts in the database are stored as **`BIGINT` in integer pesewas** (`GH₵ 1.00 = 100 pesewas`), eliminating floating-point rounding inaccuracies.
3. **Append-Only Ledger**:
   - Payments cannot be edited or deleted. Financial mistakes are corrected exclusively through explicit **reversal records** linking to the original payment, maintaining an untampered audit history.
4. **Agent Cash Reconciliation**:
   - Tracks cash collected in the field versus physical cash handed in at the office at the end of the day, flagging shortages or surpluses immediately.
5. **Audited Outbound Notifications**:
   - Outbound customer SMS receipts and Admin collection email alerts are logged in `notifications_log` (`queued`, `sent`, `failed`, `delivered`) providing verifiable proof of customer notification.
6. **Field-Ready Offline PWA**:
   - Installable Progressive Web App with root Service Worker (`sw.js`), offline fallback (`offline.html`), offline active loan caching, client-side UUIDs (`crypto.randomUUID()`), and automatic background synchronization when reconnected.
7. **Secure Backup, Restore & Guarded Wipe**:
   - Pure PHP SQL export/snapshot vault, one-click restore with automatic pre-restore safety snapshots, and password + confirmation-phrase guarded data wipe modes.

---

## 🔑 Default Credentials

| Portal Role | Name | Username | Password | Staff Code |
| :--- | :--- | :--- | :--- | :--- |
| **Super Administrator** | SYLVESTER | `SYLVESTER` | `VESTER442` | `EF-SAD-001` |
| **System Administrator** | System Admin | `admin` | `admin123` | `EF-ADM-002` |
| **Field Agent 1** | Kwame Mensah | `agent_kwame` | `agent123` | `EF-AGT-001` |
| **Field Agent 2** | Abena Serwaa | `agent_abena` | `agent123` | `EF-AGT-002` |

---

## 🚀 Directory Structure & Routing

```
ELFAITH MICROFINANCE/
├── config/
│   ├── app.php               # Application initialization, constants, and settings
│   └── database.php          # PDO connection with persistent configuration & UTF-8mb4
├── database/
│   ├── schema.sql            # Core database schema (8 core tables + indexes)
│   └── seed.sql              # Initial system users, sample borrowers, and loans
├── includes/
│   ├── auth.php              # Authentication guards & live active-status enforcement
│   ├── backup_manager.php    # Pure PHP SQL backup, restore & guarded data wipe engine
│   ├── functions.php         # Money engine (pesewas <-> GHS), UUIDs, schedule generator, reversals
│   ├── header.php            # Responsive navbar, role badge, PWA install & offline indicator
│   ├── footer.php            # Mobile bottom navigation bar for field agents, offline sync script
│   ├── sms.php               # SMS dispatch provider (Arkesel, Hubtel, mNotify, Simulated)
│   └── email.php             # Admin collection notification provider
├── api/
│   ├── record_payment.php    # Payment API with client UUID idempotency
│   ├── sync_offline.php      # Bulk offline collections synchronizer
│   └── customer_search.php   # Fast AJAX lookup for customers
├── assets/
│   ├── icons/                # PWA application icons (192x192, 512x512, maskable, Apple touch)
│   ├── app.js                # PWA offline storage, loan cache, sync & network monitor
│   ├── manifest.json         # PWA Web App Manifest for mobile installation
│   └── sw.js                 # Asset service worker mirror
├── backups/                  # .htaccess-protected database snapshot vault
├── admin/
│   ├── index.php             # Executive Dashboard: collected today, expected vs actual, PAR
│   ├── customers.php         # Manage customers, register new borrower with guarantor
│   ├── customer_view.php     # 360 borrower profile, active loans, payment history
│   ├── loans.php             # Loan origination, term schedule calculator, portfolio ledger
│   ├── loan_view.php         # Repayment schedule table, payment waterfall breakdown
│   ├── collections.php       # Complete payment ledger, reversal creation, CSV export
│   ├── reconciliation.php    # Agent cash reconciliation (collected vs handed in)
│   ├── overdue.php           # Overdue & defaulter tracking, aging analysis (PAR 30)
│   ├── agents.php            # Staff management: create, edit, activate/deactivate accounts
│   ├── reports.php           # Filterable reports with CSV & printable statements
│   ├── notifications.php     # SMS/Email delivery log and queue status
│   ├── audit_logs.php        # Immutable security audit trail
│   ├── backup.php            # Database backup, snapshot restore & guarded data wipe portal
│   └── settings.php          # Business settings, SMS/Email provider configuration
├── agent/
│   ├── index.php             # Agent Route: today's due & overdue assigned collections
│   ├── collect.php           # One-tap collection form (online & offline capable)
│   ├── my_customers.php      # Customer list assigned to this agent
│   ├── customer_new.php      # Field customer onboarding form
│   ├── history.php           # Agent's collections today with cash vs MoMo summary
│   └── offline_queue.php     # Offline pending queue inspector, receipt preview & sync trigger
├── login.php                 # Unified authentication portal
├── logout.php                # Session termination
├── offline.html              # Standalone PWA offline fallback shell
├── receipt.php               # Official printable & WhatsApp-shareable payment receipt
├── sw.js                     # Root-scope PWA Service Worker
└── index.php                 # Root entry point that routes based on role
```

---

## 🛠️ Database Setup (If Reinstalling)

Ensure MariaDB/MySQL is running on `127.0.0.1:3306`:
```powershell
Get-Content "database/schema.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root
Get-Content "database/seed.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root
```

---

## 📍 Business Location
**EL-FAITH MICROFINANCE**  
Main Commercial Road, Next to Powuako Central Mosque,  
Wassa Powuako, Western Region, Ghana  
Phone: +233 24 556 7890  
Email: info@elfaithmicrofinance.com

