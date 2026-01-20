#!/usr/bin/env sh

# Function to add a site configuration to the config/sites.yaml file.
site_add_config() {
    local domain="'$1'"
    local repo="'$2'"

    if [ -n "$BASH_SOURCE" ]; then
        DEPLOYER_DIR="${DEPLOYER_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
    else
        DEPLOYER_DIR="${DEPLOYER_DIR:-$(cd "$(dirname "${(%):-%N}")" && pwd)}"
    fi

    local config_file="${DEPLOYER_DIR:-.}/config/sites.yaml"

    if [ -f "$config_file" ] && grep -Eq "^$domain:" "$config_file"; then
        echo "The configuration for $domain already exists."
    else
        echo "$domain: $repo" >> "$config_file"
        echo "The configuration for $domain has been added."
    fi
}

# Validate the arguments and show the usage if needed.
if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Usage: $0 \"www.example.com\" \"git@github.com:example/www.example.com.git\""
    exit 1
fi

# Add the site configuration to the config/sites.yaml file.
site_add_config "$1" "$2"
