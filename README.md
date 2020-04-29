
# tesonet-vpn
Test exercise for Linux Systems Automation Engineer role from Tesonet 

## Overview
Ansible playbook to setup IPSec/IKEv2 connection based on strongSwan with ability to monitor connected clients via http (nginx/php5-fpm).
The following Ansible roles are defined:
- nginx
- strongswan
- iptables

Before to run playbook the following variables should be defined inside inventory:
- domain_name

Everything was tested on Ubuntu 18.04 and Debian 10.

## Roles
### nginx
- Install nginx
- Get Let’s encrypt certificate, for that we need to have nginx installed
- Install php5-fpm with a webpage displaying currently connected strongSwan clients and their respective IP addresses (vici interface is used).

### strongSwan
-   Install strongSwan and swanctl (for vici interface):
-   Setup two connections: one for DNS and one for IP address. Use TLS (can be Let's Encrypt) for DNS connection (IPv4) and dual stack for IP connection (IPv4+v6).
-   Use Suite B Cryptographic Suites and EAP-MSCHAPv2 for authentication for both connections

### iptables
- Deny local clients (connected to the server) to ""see"" each other with firewall
- Secure VPN server 
