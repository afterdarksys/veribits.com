# VeriBits Interactive Console Mode

## Overview

The VeriBits CLI now includes an interactive REPL (Read-Eval-Print Loop) console that allows developers to stay "logged in" and execute commands interactively with **TAB completion**.

## Features

✅ **Interactive Shell** - REPL interface for continuous sessions
✅ **Command Completion** - Press TAB to autocomplete commands
✅ **Persistent Authentication** - API key loaded at console start
✅ **Command History** - Use arrow keys for command history
✅ **Colored Output** - Visual feedback for better UX
✅ **Built-in Help** - Type `help` for command reference

## Usage

### Start Console Mode

```bash
# Node.js
vb console
# or
veribits console

# Python
vb console
# or
veribits console

# With API key
export VERIBITS_API_KEY="your-key-here"
vb console

# Or inline
vb --api-key your-key console
```

### Example Session

```
$ vb console

╔════════════════════════════════════════════════════════════╗
║           VeriBits Interactive Console v2.0.0              ║
╚════════════════════════════════════════════════════════════╝

Type 'help' for available commands or 'exit' to quit

✓ Authenticated with API key

veribits> health

💚 API Health Check
Status: HEALTHY
Version: 2.0.0
Uptime: 99.9%

veribits> email-spf google.com

📧 SPF Analysis: google.com
Valid: ✓ Yes
Record: v=spf1 include:_spf.google.com ~all

Mechanisms:
  • include:_spf.google.com
  • ~all

veribits> dns-validate github.com

🌐 DNS Validation: github.com
Records Found: 1
  • A: 140.82.112.4

veribits> tool-search email

🔍 Tool Search: email
Results: 8

• Email Verification
  Category: Email
  Comprehensive SMTP and deliverability check

• Email SPF Analyzer
  Category: Email
  Analyze SPF records for domain

...

veribits> exit

👋 Goodbye!
```

## Console Commands

### Available in Console

All 48 CLI commands work in console mode:

**Security & IAM:**
- `iam-analyze <file>`
- `secrets-scan <file>`
- `db-audit <connection>`
- `security-headers <url>`

**Developer Tools:**
- `jwt-decode <token>`
- `hash <text>`
- `regex <pattern> <text>`
- `url-encode <text>`
- `base64 <text>`

**Network Tools:**
- `dns-validate <domain>`
- `ip-calc <cidr>`
- `rbl-check <ip>`
- `traceroute <host>`
- `bgp-lookup <asn>`

**Email Tools:**
- `email-verify <email>`
- `email-spf <domain>`
- `email-dmarc <domain>`
- `email-dkim <domain>`
- `hibp-email <email>`
- `hibp-password <password>`

**SSL/TLS:**
- `ssl-check <host>`
- `ssl-convert -in <file> -out <file>`
- `ssl-resolve-chain <url>`
- `ssl-verify-keypair <cert> <key>`

**Cloud Security:**
- `cloud-storage-scan <bucket>`
- `cloud-storage-search <query>`
- `malware-scan <file>`

**Utilities:**
- `tool-search <query>`
- `tool-list`
- `health`
- `whois <domain>`

### Console-Specific Commands

- `help` - Show available commands
- `clear` / `cls` - Clear screen
- `exit` / `quit` - Exit console
- `Ctrl+D` - Also exits

## Command Completion

Press **TAB** to autocomplete:

```
veribits> em[TAB]
email-blacklist    email-dkim        email-mx
email-disposable   email-dmarc       email-score
email-verify       email-spf

veribits> email-[TAB]
email-blacklist    email-dkim        email-mx
email-disposable   email-dmarc       email-score
email-verify       email-spf

veribits> email-spf google.com
```

## Benefits

### For Developers

1. **Faster Workflow** - No need to type `vb` for every command
2. **Session Persistence** - API key loaded once
3. **Command History** - Reuse previous commands
4. **Tab Completion** - Discover commands interactively
5. **Less Context Switching** - Stay in the flow

### Use Cases

**Security Auditing:**
```
veribits> secrets-scan app.js
veribits> iam-analyze policy.json
veribits> security-headers https://example.com
```

**Email Domain Investigation:**
```
veribits> email-spf example.com
veribits> email-dmarc example.com
veribits> email-mx example.com
veribits> email-score example.com
veribits> whois example.com
```

**SSL Certificate Analysis:**
```
veribits> ssl-check example.com
veribits> ssl-resolve-chain example.com
veribits> whois example.com
```

**Rapid Testing:**
```
veribits> hash "test123"
veribits> hash "test456"
veribits> hash "test789"
veribits> base64 "secret"
```

## Comparison: Console vs Direct

### Direct Mode (Traditional)
```bash
$ vb email-spf google.com
$ vb email-dmarc google.com
$ vb email-mx google.com
$ vb whois google.com
```

### Console Mode (Interactive)
```bash
$ vb console
veribits> email-spf google.com
veribits> email-dmarc google.com
veribits> email-mx google.com
veribits> whois google.com
```

**Advantages:**
- ✅ Less typing
- ✅ Faster execution
- ✅ Tab completion
- ✅ Command history
- ✅ Single authentication

## Implementation Details

### Node.js
- Uses `readline` module for REPL
- Command completion via `completer` function
- Async command execution
- Colored output with `chalk`

### Python
- Uses `cmd.Cmd` framework
- Built-in command completion
- ANSI color codes for output
- Exception handling per command

## Future Enhancements

- [ ] Command aliases (`s` for `secrets-scan`)
- [ ] Output piping (`email-spf google.com | grep SPF`)
- [ ] Script execution (`.run script.vb`)
- [ ] Session saving (`.save session.vb`)
- [ ] Multi-line commands
- [ ] Syntax highlighting
- [ ] Auto-suggestions

---

**© After Dark Systems, LLC**
**VeriBits CLI Console v2.0.0**
