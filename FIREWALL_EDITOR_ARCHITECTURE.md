# Firewall Editor - System Architecture

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         VeriBits Platform                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌──────────────────┐         ┌──────────────────┐                  │
│  │   Web Browser    │         │   CLI Tools      │                  │
│  │  (User Interface)│         │  (Automation)    │                  │
│  └────────┬─────────┘         └────────┬─────────┘                  │
│           │                             │                            │
│           │ HTTPS                       │ HTTPS                      │
│           ▼                             ▼                            │
│  ┌─────────────────────────────────────────────────┐                │
│  │           Frontend Layer                        │                │
│  ├─────────────────────────────────────────────────┤                │
│  │  • firewall-editor.php                          │                │
│  │  • firewall-editor.js                           │                │
│  │  • Visual rule editor                           │                │
│  │  • Drag-and-drop interface                      │                │
│  │  • Live preview                                 │                │
│  └────────┬────────────────────────┬─────────────┬─┘                │
│           │                        │             │                  │
│           │ REST API               │             │ API Key          │
│           ▼                        │             ▼                  │
│  ┌────────────────────┐            │   ┌──────────────────┐         │
│  │  API Layer         │            │   │  Public API      │         │
│  ├────────────────────┤            │   ├──────────────────┤         │
│  │  /api/v1/firewall/ │            │   │ get-iptables.php │         │
│  │  - upload          │            │   │                  │         │
│  │  - save            │            │   │  • API key auth  │         │
│  │  - list            │            │   │  • Text/JSON     │         │
│  │  - get             │            │   │  • No login      │         │
│  │  - diff            │            │   └────────┬─────────┘         │
│  │  - export          │            │            │                   │
│  └────────┬───────────┘            │            │                   │
│           │                        │            │                   │
│           │                        │            │                   │
│           ▼                        ▼            ▼                   │
│  ┌─────────────────────────────────────────────────────┐            │
│  │            Backend Layer                            │            │
│  ├─────────────────────────────────────────────────────┤            │
│  │  FirewallController.php                             │            │
│  │  ┌───────────────────────────────────────────┐      │            │
│  │  │ • upload()      - Parse configurations    │      │            │
│  │  │ • save()        - Version control         │      │            │
│  │  │ • list()        - Retrieve configs        │      │            │
│  │  │ • get()         - Get specific config     │      │            │
│  │  │ • diff()        - Compare versions        │      │            │
│  │  │ • export()      - Download configs        │      │            │
│  │  └───────────────────────────────────────────┘      │            │
│  │                                                      │            │
│  │  Support Services:                                  │            │
│  │  • Auth.php        - Authentication                 │            │
│  │  • RateLimit.php   - Rate limiting                  │            │
│  │  • AuditLog.php    - Audit logging                  │            │
│  │  • Validator.php   - Input validation               │            │
│  └────────┬─────────────────────────────────────────┬──┘            │
│           │                                         │                │
│           │                                         │                │
│           ▼                                         ▼                │
│  ┌──────────────────────┐                ┌─────────────────┐        │
│  │   Database Layer     │                │   File System   │        │
│  ├──────────────────────┤                ├─────────────────┤        │
│  │  PostgreSQL          │                │  Temp uploads   │        │
│  │  ┌────────────────┐  │                │  Backups        │        │
│  │  │firewall_configs│  │                └─────────────────┘        │
│  │  │  • user_id     │  │                                           │
│  │  │  • device_name │  │                                           │
│  │  │  • config_type │  │                                           │
│  │  │  • config_data │  │                                           │
│  │  │  • version     │  │                                           │
│  │  │  • description │  │                                           │
│  │  └────────────────┘  │                                           │
│  │  ┌────────────────┐  │                                           │
│  │  │ firewall_tags  │  │                                           │
│  │  └────────────────┘  │                                           │
│  │  ┌─────────────────┐ │                                           │
│  │  │firewall_deploys │ │                                           │
│  │  └─────────────────┘ │                                           │
│  └──────────────────────┘                                           │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

## Data Flow

### 1. Upload Configuration Flow

```
User Browser
    │
    │ 1. Select file
    │
    ▼
firewall-editor.php
    │
    │ 2. Drag & drop or browse
    │
    ▼
firewall-editor.js
    │
    │ 3. FormData with file
    │
    ▼
POST /api/v1/firewall/upload
    │
    │ 4. Authentication check
    │
    ▼
FirewallController::upload()
    │
    ├─→ 5. Validate file
    ├─→ 6. Parse iptables-save format
    ├─→ 7. Extract chains and rules
    └─→ 8. Return parsed data
    │
    ▼
Response JSON
    │
    │ 9. Display in visual editor
    │
    ▼
User sees rules in table
```

### 2. Save Configuration Flow

```
User Browser
    │
    │ 1. Edit rules visually
    │
    ▼
firewall-editor.js
    │
    │ 2. Generate iptables commands
    │
    ▼
POST /api/v1/firewall/save
    │
    │ 3. Authentication check
    │
    ▼
FirewallController::save()
    │
    ├─→ 4. Validate config type
    ├─→ 5. Get next version number
    ├─→ 6. Insert into database
    └─→ 7. Log audit entry
    │
    ▼
Database (firewall_configs)
    │
    │ 8. Version created
    │
    ▼
Response with version ID
```

### 3. CLI Retrieval Flow

```
Server / CLI
    │
    │ 1. Request with API key
    │
    ▼
GET /get-iptables.php
    │
    │ 2. Validate API key
    │
    ▼
Query firewall_configs
    │
    ├─→ Filter by device
    ├─→ Filter by version
    └─→ Get latest if no version
    │
    ▼
Format response
    │
    ├─→ Text: iptables-save format
    └─→ JSON: structured data
    │
    ▼
Return configuration
    │
    │ 3. Apply with iptables-restore
    │
    ▼
Server firewall updated
```

### 4. Version Comparison Flow

```
User Browser
    │
    │ 1. Select two versions
    │
    ▼
GET /api/v1/firewall/diff
    │
    │ 2. Authentication check
    │
    ▼
FirewallController::diff()
    │
    ├─→ 3. Fetch both versions from DB
    ├─→ 4. Split into lines
    ├─→ 5. Generate line-by-line diff
    └─→ 6. Return diff array
    │
    ▼
Response with diff
    │
    │ 7. Display with color coding
    │    + Added lines (green)
    │    - Removed lines (red)
    │      Unchanged (white)
    │
    ▼
User sees visual comparison
```

## Component Interactions

### Frontend Components

```
┌────────────────────────────────────────┐
│  firewall-editor.php                   │
│  ┌──────────────────────────────────┐  │
│  │  HTML Structure                  │  │
│  │  • Navigation                    │  │
│  │  • Type selector                 │  │
│  │  • Control buttons               │  │
│  │  • Statistics dashboard          │  │
│  │  • Empty state                   │  │
│  │  • Firewall rules display        │  │
│  │  • Modals (upload, add rule)     │  │
│  │  • Command preview               │  │
│  └──────────────────────────────────┘  │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  firewall-editor.js              │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │  State Management          │  │  │
│  │  │  • currentFirewallType     │  │  │
│  │  │  • firewallConfig          │  │  │
│  │  │  • editingRuleIndex        │  │  │
│  │  └────────────────────────────┘  │  │
│  │                                  │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │  Functions                 │  │  │
│  │  │  • parseFirewallConfig()   │  │  │
│  │  │  • renderFirewallRules()   │  │  │
│  │  │  • generateIptablesCommand()│ │  │
│  │  │  • saveRule()              │  │  │
│  │  │  • deleteRule()            │  │  │
│  │  │  • updateCommandPreview()  │  │  │
│  │  └────────────────────────────┘  │  │
│  └──────────────────────────────────┘  │
└────────────────────────────────────────┘
```

### Backend Components

```
┌────────────────────────────────────────┐
│  FirewallController.php                │
│  ┌──────────────────────────────────┐  │
│  │  Private Methods                 │  │
│  │  • parseFirewallConfig()         │  │
│  │  │  ├─ Parse table markers       │  │
│  │  │  ├─ Parse chain policies      │  │
│  │  │  └─ Parse individual rules    │  │
│  │  │                               │  │
│  │  • parseRule()                   │  │
│  │  │  ├─ Extract target (-j)       │  │
│  │  │  ├─ Extract protocol (-p)     │  │
│  │  │  ├─ Extract source/dest       │  │
│  │  │  ├─ Extract ports             │  │
│  │  │  ├─ Extract state             │  │
│  │  │  └─ Extract comment           │  │
│  │  │                               │  │
│  │  • generateDiff()                │  │
│  │  │  ├─ Line-by-line comparison   │  │
│  │  │  ├─ Look-ahead matching       │  │
│  │  │  └─ Generate +/- markers      │  │
│  │  └──────────────────────────────┘  │
│  │                                     │
│  │  ┌──────────────────────────────┐  │
│  │  │  Public Methods (API)        │  │
│  │  │  • upload()                  │  │
│  │  │  • save()                    │  │
│  │  │  • list()                    │  │
│  │  │  • get()                     │  │
│  │  │  • diff()                    │  │
│  │  │  • export()                  │  │
│  │  └──────────────────────────────┘  │
└────────────────────────────────────────┘
```

## Database Schema Relationships

```
┌──────────────────┐
│     users        │
│  ┌────────────┐  │
│  │ id (PK)    │◄─┼──────────────┐
│  │ email      │  │              │
│  │ tier       │  │              │
│  └────────────┘  │              │
└──────────────────┘              │
                                  │
                                  │ user_id (FK)
                                  │
┌──────────────────────────────────┼──────────────────┐
│  firewall_configs               │                  │
│  ┌──────────────────────────────┼────────────────┐ │
│  │ id (PK)                      │                │ │
│  │ user_id (FK) ────────────────┘                │ │
│  │ device_name                                   │ │
│  │ config_type (iptables/ip6tables/ebtables)    │ │
│  │ config_data (TEXT)                            │ │
│  │ version (INT)                                 │ │
│  │ description                                   │ │
│  │ created_at                                    │ │
│  │ updated_at                                    │ │
│  └───────────────────────────────────────────────┘ │
│         ▲                                           │
│         │ config_id (FK)                            │
│         │                                           │
│    ┌────┴──────────┐                                │
│    │               │                                │
└────┼───────────────┼────────────────────────────────┘
     │               │
     │               │
┌────┴──────────┐  ┌─┴──────────────────┐
│firewall_tags  │  │firewall_deployments│
│┌─────────────┐│  │┌──────────────────┐│
││id (PK)      ││  ││id (PK)           ││
││config_id(FK)││  ││config_id (FK)    ││
││tag_name     ││  ││server_hostname   ││
││created_at   ││  ││server_ip         ││
│└─────────────┘│  ││deployed_at       ││
└───────────────┘  ││deployed_by (FK)  ││
                   ││deployment_status ││
                   │└──────────────────┘│
                   └────────────────────┘
```

## Security Layers

```
┌─────────────────────────────────────────┐
│  Layer 1: Transport Security            │
│  • HTTPS/TLS encryption                 │
│  • Secure headers                       │
└───────────────┬─────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│  Layer 2: Authentication                │
│  • Session-based (web)                  │
│  • API key (CLI)                        │
│  • Token validation                     │
└───────────────┬─────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│  Layer 3: Authorization                 │
│  • User isolation                       │
│  • Resource ownership check             │
│  • Tier-based limits                    │
└───────────────┬─────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│  Layer 4: Input Validation              │
│  • File type validation                 │
│  • Size limits (10MB)                   │
│  • Config format validation             │
│  • SQL injection prevention             │
└───────────────┬─────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│  Layer 5: Rate Limiting                 │
│  • 20 uploads per hour                  │
│  • Per-user tracking                    │
│  • Configurable limits                  │
└───────────────┬─────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│  Layer 6: Audit Logging                 │
│  • All actions logged                   │
│  • User, IP, timestamp                  │
│  • Compliance tracking                  │
└─────────────────────────────────────────┘
```

## Deployment Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Production Servers                    │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐        │
│  │ Web Server │  │ Web Server │  │ Web Server │        │
│  │    01      │  │    02      │  │    03      │        │
│  └──────┬─────┘  └──────┬─────┘  └──────┬─────┘        │
│         │               │               │               │
│         └───────────────┼───────────────┘               │
│                         │                               │
│                         ▼                               │
│  ┌──────────────────────────────────────────────┐       │
│  │         Load Balancer / Reverse Proxy        │       │
│  └──────────────────────────────────────────────┘       │
│                         │                               │
└─────────────────────────┼───────────────────────────────┘
                          │
                          │ HTTPS
                          │
┌─────────────────────────┼───────────────────────────────┐
│                    VeriBits Platform                     │
│                         │                               │
│                         ▼                               │
│  ┌──────────────────────────────────────────────┐       │
│  │           Application Layer                  │       │
│  │  • firewall-editor.php                       │       │
│  │  • FirewallController.php                    │       │
│  │  • get-iptables.php                          │       │
│  └──────────────────┬───────────────────────────┘       │
│                     │                                   │
│                     ▼                                   │
│  ┌──────────────────────────────────────────────┐       │
│  │         Database (PostgreSQL)                │       │
│  │  • firewall_configs                          │       │
│  │  • firewall_tags                             │       │
│  │  • firewall_deployments                      │       │
│  └──────────────────────────────────────────────┘       │
│                                                          │
└──────────────────────────────────────────────────────────┘
                          ▲
                          │
                          │ CLI Tools
                          │
┌─────────────────────────┴───────────────────────────────┐
│              Automated Systems / Cron Jobs               │
│  • get-firewall-config.sh                               │
│  • Daily backups                                        │
│  • Deployment scripts                                   │
└──────────────────────────────────────────────────────────┘
```

## Technology Stack

```
┌────────────────────────────────────────┐
│  Frontend                              │
├────────────────────────────────────────┤
│  • HTML5                               │
│  • CSS3 (Custom properties)            │
│  • JavaScript (ES6+)                   │
│  • Fetch API                           │
└────────────────────────────────────────┘

┌────────────────────────────────────────┐
│  Backend                               │
├────────────────────────────────────────┤
│  • PHP 8.2+                            │
│  • Namespace architecture              │
│  • PSR-4 autoloading                   │
│  • RESTful API design                  │
└────────────────────────────────────────┘

┌────────────────────────────────────────┐
│  Database                              │
├────────────────────────────────────────┤
│  • PostgreSQL 14+                      │
│  • UUID primary keys                   │
│  • TIMESTAMPTZ for timestamps          │
│  • Triggers for automation             │
└────────────────────────────────────────┘

┌────────────────────────────────────────┐
│  CLI / Automation                      │
├────────────────────────────────────────┤
│  • Bash scripting                      │
│  • curl for HTTP requests              │
│  • iptables-restore                    │
│  • jq for JSON parsing                 │
└────────────────────────────────────────┘
```

## File Structure

```
veribits.com/
├── app/
│   ├── public/
│   │   ├── tool/
│   │   │   └── firewall-editor.php       Frontend UI
│   │   ├── assets/
│   │   │   └── js/
│   │   │       └── firewall-editor.js    Frontend logic
│   │   ├── index.php                     API routes
│   │   └── get-iptables.php              Public API
│   │
│   └── src/
│       └── Controllers/
│           └── FirewallController.php    Backend logic
│
├── db/
│   └── migrations/
│       └── 012_firewall_configs.sql      Database schema
│
├── scripts/
│   └── get-firewall-config.sh            CLI tool
│
├── tests/
│   └── test-firewall-editor.sh           Test suite
│
└── docs/
    ├── FIREWALL_EDITOR_DOCUMENTATION.md  Full docs
    ├── FIREWALL_EDITOR_QUICKSTART.md     Quick start
    ├── FIREWALL_EDITOR_SUMMARY.md        Implementation summary
    └── FIREWALL_EDITOR_ARCHITECTURE.md   This file
```

---

© 2025 After Dark Systems, LLC. All rights reserved.
