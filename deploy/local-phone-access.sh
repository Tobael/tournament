#!/bin/sh
# Temporary access to this workstation's local POC from its home LAN only.
set -eu
if [ "$(id -u)" -ne 0 ]; then
    echo 'Run from the project directory: sudo sh deploy/local-phone-access.sh' >&2
    exit 1
fi
case "${1:-open}" in
    open) action=--add-rich-rule ;;
    close) action=--remove-rich-rule ;;
    *) echo 'Usage: sudo sh deploy/local-phone-access.sh [open|close]' >&2; exit 1 ;;
esac
for port in 8000 8080; do
    rule="rule family=\"ipv4\" source address=\"10.0.10.0/24\" port port=\"$port\" protocol=\"tcp\" accept"
    if [ "$action" = --add-rich-rule ]; then
        firewall-cmd --zone=public "$action=$rule" --timeout=2h
    else
        firewall-cmd --zone=public "$action=$rule"
    fi
done
if [ "$action" = --add-rich-rule ]; then
    echo 'Home LAN access allowed for two hours: http://10.0.10.129:8000'
else
    echo 'Temporary home LAN access removed.'
fi
