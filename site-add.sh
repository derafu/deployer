#!/usr/bin/env sh

# Function to add a site configuration to the sites.php file.
site_add_config() {
    local domain="'$1'"
    local repo="'$2'"

    if [ -n "$BASH_SOURCE" ]; then
        DEPLOYER_DIR="${DEPLOYER_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
    else
        DEPLOYER_DIR="${DEPLOYER_DIR:-$(cd "$(dirname "${(%):-%N}")" && pwd)}"
    fi

    local config_file="${DEPLOYER_DIR:-.}/sites.php"

    if grep -q "$domain" "$config_file"; then
        echo "The configuration for $domain already exists."
    else
        case "$OSTYPE" in
            darwin*)
                sed -i "" "/];/i\\
    $domain => $repo,
" "$config_file"
            ;;
            *)
                sed -i "/];/i\\
    $domain => $repo,
" "$config_file"
            ;;
        esac
        echo "The configuration for $domain has been added."
    fi
}

if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Usage: ./siteadd.sh \"www.example.com\" \"git@github.com:example/www.example.com.git\""
    exit 1
fi

site_add_config "$1" "$2"
