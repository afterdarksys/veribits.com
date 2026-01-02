# VeriBits CLI Fish Completion
# Install: cp veribits.fish ~/.config/fish/completions/

# Disable file completion by default
complete -c veribits -f

# Main commands
complete -c veribits -n "__fish_use_subcommand" -a "verify" -d "Verify files, emails, and transactions"
complete -c veribits -n "__fish_use_subcommand" -a "scan" -d "Security scanning tools"
complete -c veribits -n "__fish_use_subcommand" -a "dns" -d "DNS lookup and validation tools"
complete -c veribits -n "__fish_use_subcommand" -a "ssl" -d "SSL/TLS certificate tools"
complete -c veribits -n "__fish_use_subcommand" -a "hash" -d "Hash generation and lookup"
complete -c veribits -n "__fish_use_subcommand" -a "jwt" -d "JWT token tools"
complete -c veribits -n "__fish_use_subcommand" -a "regex" -d "Regular expression testing"
complete -c veribits -n "__fish_use_subcommand" -a "ip" -d "IP and network tools"
complete -c veribits -n "__fish_use_subcommand" -a "secrets" -d "Secrets scanning"
complete -c veribits -n "__fish_use_subcommand" -a "iam" -d "IAM policy analysis"
complete -c veribits -n "__fish_use_subcommand" -a "config" -d "Configuration management"
complete -c veribits -n "__fish_use_subcommand" -a "help" -d "Show help information"
complete -c veribits -n "__fish_use_subcommand" -a "version" -d "Show version information"

# Global options
complete -c veribits -l api-key -d "API key for authentication" -r
complete -c veribits -l config -d "Path to config file" -r -F
complete -c veribits -l output -d "Output destination" -r -a "stdout file clipboard"
complete -c veribits -l format -d "Output format" -r -a "json table csv yaml"
complete -c veribits -l quiet -d "Suppress non-essential output"
complete -c veribits -l verbose -d "Enable verbose output"
complete -c veribits -l help -d "Show help"

# verify subcommands
complete -c veribits -n "__fish_seen_subcommand_from verify" -a "file" -d "Verify file hash"
complete -c veribits -n "__fish_seen_subcommand_from verify" -a "email" -d "Verify email domain"
complete -c veribits -n "__fish_seen_subcommand_from verify" -a "tx" -d "Verify blockchain transaction"
complete -c veribits -n "__fish_seen_subcommand_from verify" -a "malware" -d "Scan for malware"
complete -c veribits -n "__fish_seen_subcommand_from verify" -a "archive" -d "Inspect archive contents"

# scan subcommands
complete -c veribits -n "__fish_seen_subcommand_from scan" -a "secrets" -d "Scan for exposed secrets"
complete -c veribits -n "__fish_seen_subcommand_from scan" -a "iam" -d "Analyze IAM policies"
complete -c veribits -n "__fish_seen_subcommand_from scan" -a "docker" -d "Scan Docker configurations"
complete -c veribits -n "__fish_seen_subcommand_from scan" -a "terraform" -d "Scan Terraform files"
complete -c veribits -n "__fish_seen_subcommand_from scan" -a "kubernetes" -d "Scan Kubernetes manifests"

# dns subcommands
complete -c veribits -n "__fish_seen_subcommand_from dns" -a "lookup" -d "DNS record lookup"
complete -c veribits -n "__fish_seen_subcommand_from dns" -a "propagation" -d "Check DNS propagation"
complete -c veribits -n "__fish_seen_subcommand_from dns" -a "dnssec" -d "Validate DNSSEC"
complete -c veribits -n "__fish_seen_subcommand_from dns" -a "reverse" -d "Reverse DNS lookup"
complete -c veribits -n "__fish_seen_subcommand_from dns" -a "zone" -d "Validate zone file"
complete -c veribits -n "__fish_seen_subcommand_from dns" -a "mx" -d "Check MX records"
complete -c veribits -n "__fish_seen_subcommand_from dns" -a "spf" -d "Analyze SPF record"
complete -c veribits -n "__fish_seen_subcommand_from dns" -a "dkim" -d "Analyze DKIM"
complete -c veribits -n "__fish_seen_subcommand_from dns" -a "dmarc" -d "Analyze DMARC"

# ssl subcommands
complete -c veribits -n "__fish_seen_subcommand_from ssl" -a "validate" -d "Validate SSL certificate"
complete -c veribits -n "__fish_seen_subcommand_from ssl" -a "chain" -d "Resolve certificate chain"
complete -c veribits -n "__fish_seen_subcommand_from ssl" -a "csr" -d "Validate CSR"
complete -c veribits -n "__fish_seen_subcommand_from ssl" -a "convert" -d "Convert certificate formats"
complete -c veribits -n "__fish_seen_subcommand_from ssl" -a "generate" -d "Generate CSR"

# hash subcommands
complete -c veribits -n "__fish_seen_subcommand_from hash" -a "generate" -d "Generate hash from input"
complete -c veribits -n "__fish_seen_subcommand_from hash" -a "lookup" -d "Look up hash in databases"
complete -c veribits -n "__fish_seen_subcommand_from hash" -a "identify" -d "Identify hash type"
complete -c veribits -n "__fish_seen_subcommand_from hash" -a "batch" -d "Batch hash operations"

# hash generate algorithms
complete -c veribits -n "__fish_seen_subcommand_from hash; and __fish_seen_subcommand_from generate" -a "md5 sha1 sha256 sha512" -d "Hash algorithm"

# jwt subcommands
complete -c veribits -n "__fish_seen_subcommand_from jwt" -a "decode" -d "Decode JWT token"
complete -c veribits -n "__fish_seen_subcommand_from jwt" -a "validate" -d "Validate JWT signature"
complete -c veribits -n "__fish_seen_subcommand_from jwt" -a "sign" -d "Sign new JWT"

# ip subcommands
complete -c veribits -n "__fish_seen_subcommand_from ip" -a "calculate" -d "Calculate IP/CIDR info"
complete -c veribits -n "__fish_seen_subcommand_from ip" -a "whois" -d "WHOIS lookup"
complete -c veribits -n "__fish_seen_subcommand_from ip" -a "rbl" -d "Check RBL status"
complete -c veribits -n "__fish_seen_subcommand_from ip" -a "traceroute" -d "Visual traceroute"
complete -c veribits -n "__fish_seen_subcommand_from ip" -a "bgp" -d "BGP prefix lookup"

# config subcommands
complete -c veribits -n "__fish_seen_subcommand_from config" -a "init" -d "Initialize configuration"
complete -c veribits -n "__fish_seen_subcommand_from config" -a "show" -d "Show current config"
complete -c veribits -n "__fish_seen_subcommand_from config" -a "set" -d "Set config value"
complete -c veribits -n "__fish_seen_subcommand_from config" -a "get" -d "Get config value"
