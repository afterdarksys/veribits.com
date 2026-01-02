#!/bin/bash
# VeriBits CLI Bash Completion
# Install: source veribits.bash
# Or: cp veribits.bash /etc/bash_completion.d/veribits

_veribits_completions() {
    local cur prev opts commands

    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"

    # Main commands
    commands="verify scan dns ssl hash jwt regex ip secrets iam config help version"

    # Subcommands by category
    verify_opts="file email tx malware archive"
    scan_opts="secrets iam docker terraform kubernetes"
    dns_opts="lookup propagation dnssec reverse zone mx spf dkim dmarc"
    ssl_opts="validate chain csr convert generate"
    hash_opts="generate lookup identify batch"
    jwt_opts="decode validate sign"
    regex_opts="test"
    ip_opts="calculate whois rbl traceroute bgp"
    secrets_opts="scan report"
    iam_opts="analyze history"
    config_opts="init show set get"

    # Global options
    global_opts="--api-key --config --output --format --quiet --verbose --help"
    format_opts="json table csv yaml"
    output_opts="stdout file clipboard"

    case "${prev}" in
        veribits)
            COMPREPLY=( $(compgen -W "${commands}" -- ${cur}) )
            return 0
            ;;
        verify)
            COMPREPLY=( $(compgen -W "${verify_opts}" -- ${cur}) )
            return 0
            ;;
        scan)
            COMPREPLY=( $(compgen -W "${scan_opts}" -- ${cur}) )
            return 0
            ;;
        dns)
            COMPREPLY=( $(compgen -W "${dns_opts}" -- ${cur}) )
            return 0
            ;;
        ssl)
            COMPREPLY=( $(compgen -W "${ssl_opts}" -- ${cur}) )
            return 0
            ;;
        hash)
            COMPREPLY=( $(compgen -W "${hash_opts}" -- ${cur}) )
            return 0
            ;;
        jwt)
            COMPREPLY=( $(compgen -W "${jwt_opts}" -- ${cur}) )
            return 0
            ;;
        regex)
            COMPREPLY=( $(compgen -W "${regex_opts}" -- ${cur}) )
            return 0
            ;;
        ip)
            COMPREPLY=( $(compgen -W "${ip_opts}" -- ${cur}) )
            return 0
            ;;
        secrets)
            COMPREPLY=( $(compgen -W "${secrets_opts}" -- ${cur}) )
            return 0
            ;;
        iam)
            COMPREPLY=( $(compgen -W "${iam_opts}" -- ${cur}) )
            return 0
            ;;
        config)
            COMPREPLY=( $(compgen -W "${config_opts}" -- ${cur}) )
            return 0
            ;;
        --format)
            COMPREPLY=( $(compgen -W "${format_opts}" -- ${cur}) )
            return 0
            ;;
        --output)
            COMPREPLY=( $(compgen -W "${output_opts}" -- ${cur}) )
            return 0
            ;;
        --api-key|--config)
            # File completion
            COMPREPLY=( $(compgen -f -- ${cur}) )
            return 0
            ;;
        lookup|propagation|dnssec|reverse|zone|validate|chain|analyze)
            # Domain/host completion - suggest common TLDs
            COMPREPLY=()
            return 0
            ;;
        generate)
            # Hash algorithms
            if [[ "${COMP_WORDS[1]}" == "hash" ]]; then
                COMPREPLY=( $(compgen -W "md5 sha1 sha256 sha512" -- ${cur}) )
            fi
            return 0
            ;;
        *)
            ;;
    esac

    # Handle options starting with -
    if [[ ${cur} == -* ]]; then
        COMPREPLY=( $(compgen -W "${global_opts}" -- ${cur}) )
        return 0
    fi

    # Default to commands
    COMPREPLY=( $(compgen -W "${commands}" -- ${cur}) )
    return 0
}

complete -F _veribits_completions veribits
