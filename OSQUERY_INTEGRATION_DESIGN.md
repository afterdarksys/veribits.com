# osquery Integration - Design Document

## 🔍 Overview

osquery is Facebook's open-source tool that exposes the operating system as a relational database. You can query system information using SQL, making it perfect for:
- Security monitoring and auditing
- Compliance checking
- System inventory
- Threat hunting
- Incident response
- Configuration management

## 🎯 Integration Strategy

### Three-Tier Approach

```
┌─────────────────────────────────────────────┐
│  1. Web Tool (Execute Queries)              │
│     - Run SQL queries via browser           │
│     - Pre-built query library               │
│     - Real-time results                     │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  2. System Client (Local Queries)           │
│     - Run osquery on local machine          │
│     - Scheduled monitoring                  │
│     - Upload results to VeriBits            │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  3. Server Processing (Aggregate)           │
│     - Store query results                   │
│     - Historical analysis                   │
│     - Alert on anomalies                    │
└─────────────────────────────────────────────┘
```

## 📊 osquery Tables

osquery provides 200+ tables. Most useful for security:

### Process & Execution
```sql
-- Running processes
SELECT * FROM processes;

-- Listening network connections
SELECT * FROM listening_ports;

-- Scheduled tasks/cron jobs
SELECT * FROM crontab;
```

### User & Authentication
```sql
-- User accounts
SELECT * FROM users;

-- SSH authorized keys
SELECT * FROM authorized_keys;

-- Recent logins
SELECT * FROM last;
```

### File System
```sql
-- SUID binaries
SELECT * FROM suid_bin;

-- Recently modified files
SELECT * FROM file WHERE mtime > strftime('%s', 'now') - 86400;
```

### Network
```sql
-- Active connections
SELECT * FROM process_open_sockets;

-- ARP cache
SELECT * FROM arp_cache;

-- DNS cache
SELECT * FROM dns_cache;
```

### Security
```sql
-- Installed software
SELECT * FROM programs;

-- Kernel modules
SELECT * FROM kernel_modules;

-- Firewall rules (iptables)
SELECT * FROM iptables;
```

## 🛠️ Architecture

### 1. Web Tool Component

**File:** `app/public/tool/osquery.php`

**Features:**
- SQL query editor with syntax highlighting
- Pre-built query library (50+ common queries)
- Query history
- Export results (JSON/CSV)
- Real-time execution
- Query templates

**Query Categories:**
- 🔐 Security Auditing
- 👤 User Management
- 🌐 Network Monitoring
- 📁 File System Analysis
- 📦 Software Inventory
- ⚙️ System Configuration

### 2. System Client Component

**File:** `veribits-system-client/osquery_monitor.py`

**Features:**
- Run local osquery commands
- Scheduled monitoring (cron-like)
- Differential queries (detect changes)
- Upload results to VeriBits
- Offline mode with local storage

**Commands:**
```bash
# Run single query
veribits osquery run "SELECT * FROM processes"

# Run query pack
veribits osquery pack security-audit

# Start monitoring daemon
veribits osquery monitor --interval 60

# Upload results
veribits osquery upload --analysis-id abc123
```

### 3. Server-Side Controller

**File:** `app/src/Controllers/OsqueryController.php`

**Endpoints:**
```
POST /api/v1/osquery/execute       - Execute query
POST /api/v1/osquery/pack          - Run query pack
GET  /api/v1/osquery/tables        - List available tables
GET  /api/v1/osquery/schema        - Get table schema
GET  /api/v1/osquery/templates     - List query templates
POST /api/v1/osquery/save-query    - Save query
GET  /api/v1/osquery/history       - Query history
```

## 💡 Pre-Built Query Library

### Security Auditing Queries

**1. SUID Binaries**
```sql
SELECT
    path,
    username,
    mode,
    size,
    mtime
FROM suid_bin
WHERE path NOT IN (
    '/usr/bin/sudo',
    '/usr/bin/passwd',
    '/usr/bin/su'
)
ORDER BY mtime DESC;
```

**2. Suspicious Listening Ports**
```sql
SELECT
    p.name,
    l.port,
    l.address,
    p.path,
    p.cmdline
FROM listening_ports l
JOIN processes p ON l.pid = p.pid
WHERE l.port NOT IN (22, 80, 443, 3306, 5432)
ORDER BY l.port;
```

**3. Unauthorized SSH Keys**
```sql
SELECT
    u.username,
    ak.key,
    ak.key_file
FROM authorized_keys ak
JOIN users u ON ak.uid = u.uid
WHERE u.username NOT IN ('root', 'admin')
ORDER BY u.username;
```

**4. Recently Modified System Files**
```sql
SELECT
    path,
    uid,
    gid,
    mode,
    mtime,
    datetime(mtime, 'unixepoch') as modified_date
FROM file
WHERE
    (path LIKE '/etc/%' OR path LIKE '/usr/bin/%')
    AND mtime > strftime('%s', 'now') - 86400
ORDER BY mtime DESC;
```

**5. Unusual User Logins**
```sql
SELECT
    username,
    tty,
    host,
    time,
    datetime(time, 'unixepoch') as login_time
FROM last
WHERE time > strftime('%s', 'now') - 86400
ORDER BY time DESC;
```

### System Inventory

**6. Installed Software**
```sql
SELECT
    name,
    version,
    vendor,
    install_date,
    install_location
FROM programs
ORDER BY install_date DESC
LIMIT 100;
```

**7. User Accounts**
```sql
SELECT
    username,
    uid,
    gid,
    shell,
    directory,
    description
FROM users
ORDER BY uid;
```

**8. Running Services**
```sql
SELECT
    name,
    status,
    pid,
    start_type
FROM services
WHERE status = 'RUNNING'
ORDER BY name;
```

### Network Security

**9. Active Network Connections**
```sql
SELECT
    p.name,
    pos.remote_address,
    pos.remote_port,
    pos.local_port,
    pos.state,
    p.path
FROM process_open_sockets pos
JOIN processes p ON pos.pid = p.pid
WHERE pos.remote_address != '127.0.0.1'
ORDER BY pos.remote_port;
```

**10. DNS Queries**
```sql
SELECT
    name,
    type,
    address,
    COUNT(*) as query_count
FROM dns_cache
GROUP BY name
ORDER BY query_count DESC
LIMIT 50;
```

## 🎨 UI Design

### Query Editor Interface

```
┌─────────────────────────────────────────────┐
│  📊 osquery - SQL Query Interface           │
├─────────────────────────────────────────────┤
│                                             │
│  [Templates ▼]  [Tables ▼]  [History ▼]   │
│                                             │
│  ┌─────────────────────────────────────────┐│
│  │ SELECT * FROM processes                 ││
│  │ WHERE name LIKE '%ssh%';                ││
│  │                                         ││
│  │                                         ││
│  └─────────────────────────────────────────┘│
│                                             │
│  [▶ Execute Query]  [Save]  [Export]       │
│                                             │
│  📊 Results (5 rows)                        │
│  ┌─────────────────────────────────────────┐│
│  │ pid  │ name    │ path      │ cmdline   ││
│  │ 1234 │ sshd    │ /usr/sbin │ sshd -D   ││
│  │ 2345 │ ssh     │ /usr/bin  │ ssh user@ ││
│  └─────────────────────────────────────────┘│
│                                             │
│  ⚡ Executed in 0.45 seconds                │
└─────────────────────────────────────────────┘
```

### Query Templates Sidebar

```
🔐 Security Auditing
├─ SUID Binaries
├─ Open Ports
├─ SSH Keys
├─ Recent Logins
└─ File Changes

👤 User Management
├─ User Accounts
├─ Group Memberships
├─ Password Policies
└─ Login History

🌐 Network Monitoring
├─ Active Connections
├─ Listening Ports
├─ DNS Queries
└─ ARP Cache

📦 Software Inventory
├─ Installed Programs
├─ Running Services
├─ Kernel Modules
└─ Startup Items
```

## 🔒 Security Considerations

### Query Safety
- Whitelist allowed tables
- Read-only queries (no INSERT/UPDATE/DELETE)
- Query timeout limits (30 seconds)
- Result size limits (10,000 rows)
- Rate limiting

### Access Control
- Require authentication
- Pro/Enterprise tier only
- Audit all queries
- No system modification queries

### Privacy
- No PII in query results (unless authorized)
- Encrypted storage of results
- Automatic cleanup after 30 days

## 📝 Implementation Details

### Query Execution Flow

1. **User submits query**
```javascript
POST /api/v1/osquery/execute
{
  "query": "SELECT * FROM processes;",
  "timeout": 30
}
```

2. **Server validates query**
- Check for malicious SQL
- Verify table access
- Apply timeout limits

3. **Execute via osqueryi**
```bash
osqueryi --json "SELECT * FROM processes;"
```

4. **Parse and return results**
```json
{
  "success": true,
  "data": {
    "rows": [...],
    "row_count": 45,
    "execution_time": 0.234,
    "columns": ["pid", "name", "path", ...]
  }
}
```

### System Client Monitoring

**Scheduled Queries:**
```python
# Monitor configuration
monitors = [
    {
        "name": "Process Monitoring",
        "query": "SELECT * FROM processes;",
        "interval": 60,  # seconds
        "differential": True  # Only report changes
    },
    {
        "name": "Network Connections",
        "query": "SELECT * FROM process_open_sockets;",
        "interval": 300
    }
]
```

**Differential Monitoring:**
- Compare results between runs
- Only report new/changed/removed entries
- Reduce noise and bandwidth

## 💰 Pricing Tiers

### Free Tier
- 10 queries per day
- Pre-built templates only
- Results stored 7 days
- No monitoring

### Pro Tier ($19.99/mo)
- 1,000 queries per day
- Custom queries
- Query history (30 days)
- Basic monitoring (1 agent)
- Export results

### Enterprise Tier ($99.99/mo)
- Unlimited queries
- Multiple agents
- Advanced monitoring
- Real-time alerts
- API access
- Custom query packs
- Results stored indefinitely

## 🚀 Installation Requirements

### Server-Side

**Ubuntu/Debian:**
```bash
# Add osquery repository
export OSQUERY_KEY=1484120AC4E9F8A1A577AEEE97A80C63C9D8B80B
sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys $OSQUERY_KEY
sudo add-apt-repository 'deb [arch=amd64] https://pkg.osquery.io/deb deb main'

# Install osquery
sudo apt-get update
sudo apt-get install osquery
```

**macOS:**
```bash
brew install osquery
```

**Verify Installation:**
```bash
osqueryi --version
```

### Client-Side

**Python Client:**
```bash
pip install osquery
pip install pyosquery
```

## 📚 Use Cases

### 1. Security Monitoring
- Detect unauthorized processes
- Monitor network connections
- Track file modifications
- Audit user activities

### 2. Compliance Auditing
- Inventory installed software
- Check security configurations
- Verify user permissions
- Generate compliance reports

### 3. Incident Response
- Investigate suspicious activity
- Timeline reconstruction
- Network forensics
- Process analysis

### 4. System Administration
- Monitor system health
- Track configuration changes
- Software inventory
- User management

## 📊 Example Queries for Common Tasks

### Find Processes Using High CPU
```sql
SELECT
    pid,
    name,
    CAST(user_time/1000000000.0 AS INTEGER) as cpu_seconds,
    cmdline
FROM processes
WHERE user_time > 10000000000
ORDER BY user_time DESC
LIMIT 10;
```

### Check for Rootkits (Hidden Processes)
```sql
SELECT DISTINCT
    p.pid,
    p.name
FROM processes p
WHERE p.pid NOT IN (
    SELECT DISTINCT pid FROM process_open_files
)
AND p.pid > 1;
```

### Monitor Cronjob Changes
```sql
SELECT
    command,
    path,
    minute,
    hour,
    day_of_month,
    month
FROM crontab
ORDER BY command;
```

### Find World-Writable Files
```sql
SELECT
    path,
    uid,
    gid,
    mode
FROM file
WHERE
    (path LIKE '/tmp/%' OR path LIKE '/var/tmp/%')
    AND (mode LIKE '%0002' OR mode LIKE '%0006')
LIMIT 100;
```

## 🎯 Competitive Advantage

### vs. Standalone osquery
- ✅ Web interface (no CLI needed)
- ✅ Pre-built query library
- ✅ Historical data storage
- ✅ Multi-system aggregation
- ✅ Alerting capabilities

### vs. Kolide Fleet
- ✅ Simpler setup
- ✅ Lower cost
- ✅ Part of full security suite
- ✅ Integrated with other VeriBits tools

### vs. Manual SQL Queries
- ✅ Query templates
- ✅ Syntax validation
- ✅ Result visualization
- ✅ Query history
- ✅ Export capabilities

## 📈 Success Metrics

- Query execution time: <1 second (average)
- System impact: <5% CPU usage
- Query success rate: >95%
- User adoption: Track active users
- Most popular queries: Analytics

---

**Ready to Implement!** Complete osquery integration with web tool, system client, and API support.
