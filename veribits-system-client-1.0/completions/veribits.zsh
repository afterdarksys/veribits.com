#compdef veribits
# VeriBits CLI Zsh Completion
# Install: fpath=(~/.zsh/completions $fpath) && autoload -Uz compinit && compinit

_veribits() {
    local -a commands
    local -a global_opts

    commands=(
        'verify:Verify files, emails, and transactions'
        'scan:Security scanning tools'
        'dns:DNS lookup and validation tools'
        'ssl:SSL/TLS certificate tools'
        'hash:Hash generation and lookup'
        'jwt:JWT token tools'
        'regex:Regular expression testing'
        'ip:IP and network tools'
        'secrets:Secrets scanning'
        'iam:IAM policy analysis'
        'config:Configuration management'
        'help:Show help information'
        'version:Show version information'
    )

    global_opts=(
        '--api-key[API key for authentication]:api key:_files'
        '--config[Path to config file]:config file:_files'
        '--output[Output destination]:output:(stdout file clipboard)'
        '--format[Output format]:format:(json table csv yaml)'
        '--quiet[Suppress non-essential output]'
        '--verbose[Enable verbose output]'
        '--help[Show help]'
    )

    _arguments -C \
        $global_opts \
        '1:command:->command' \
        '*::arg:->args'

    case $state in
        command)
            _describe -t commands 'veribits command' commands
            ;;
        args)
            case $words[1] in
                verify)
                    local -a verify_cmds
                    verify_cmds=(
                        'file:Verify file hash'
                        'email:Verify email domain'
                        'tx:Verify blockchain transaction'
                        'malware:Scan for malware'
                        'archive:Inspect archive contents'
                    )
                    _describe -t verify_cmds 'verify command' verify_cmds
                    ;;
                scan)
                    local -a scan_cmds
                    scan_cmds=(
                        'secrets:Scan for exposed secrets'
                        'iam:Analyze IAM policies'
                        'docker:Scan Docker configurations'
                        'terraform:Scan Terraform files'
                        'kubernetes:Scan Kubernetes manifests'
                    )
                    _describe -t scan_cmds 'scan command' scan_cmds
                    ;;
                dns)
                    local -a dns_cmds
                    dns_cmds=(
                        'lookup:DNS record lookup'
                        'propagation:Check DNS propagation'
                        'dnssec:Validate DNSSEC'
                        'reverse:Reverse DNS lookup'
                        'zone:Validate zone file'
                        'mx:Check MX records'
                        'spf:Analyze SPF record'
                        'dkim:Analyze DKIM'
                        'dmarc:Analyze DMARC'
                    )
                    _describe -t dns_cmds 'dns command' dns_cmds
                    ;;
                ssl)
                    local -a ssl_cmds
                    ssl_cmds=(
                        'validate:Validate SSL certificate'
                        'chain:Resolve certificate chain'
                        'csr:Validate CSR'
                        'convert:Convert certificate formats'
                        'generate:Generate CSR'
                    )
                    _describe -t ssl_cmds 'ssl command' ssl_cmds
                    ;;
                hash)
                    local -a hash_cmds
                    hash_cmds=(
                        'generate:Generate hash from input'
                        'lookup:Look up hash in databases'
                        'identify:Identify hash type'
                        'batch:Batch hash operations'
                    )
                    _describe -t hash_cmds 'hash command' hash_cmds
                    ;;
                jwt)
                    local -a jwt_cmds
                    jwt_cmds=(
                        'decode:Decode JWT token'
                        'validate:Validate JWT signature'
                        'sign:Sign new JWT'
                    )
                    _describe -t jwt_cmds 'jwt command' jwt_cmds
                    ;;
                ip)
                    local -a ip_cmds
                    ip_cmds=(
                        'calculate:Calculate IP/CIDR info'
                        'whois:WHOIS lookup'
                        'rbl:Check RBL status'
                        'traceroute:Visual traceroute'
                        'bgp:BGP prefix lookup'
                    )
                    _describe -t ip_cmds 'ip command' ip_cmds
                    ;;
                config)
                    local -a config_cmds
                    config_cmds=(
                        'init:Initialize configuration'
                        'show:Show current config'
                        'set:Set config value'
                        'get:Get config value'
                    )
                    _describe -t config_cmds 'config command' config_cmds
                    ;;
                *)
                    _files
                    ;;
            esac
            ;;
    esac
}

_veribits "$@"
