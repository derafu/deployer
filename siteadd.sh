#!/usr/bin/env sh

# Function to add a site configuration to the sites.php file.
add_site_config() {
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
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i "" "/];/i\\
    $domain => $repo,
" "$config_file"
        else
            sed -i "/];/i\\
    $domain => $repo,
" "$config_file"
        fi
        echo "The configuration for $domain has been added."
    fi
}

if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Usage: ./siteadd.sh \"www.example.com\" \"git@github.com:example/www.example.com.git\""
    exit 1
fi

add_site_config "$1" "$2"
