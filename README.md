
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
- virtual_ip4_range
- virtual_ip6_range

For the second (ip-based) VPN connection certificate for local CA has to be generated first. strongswan-pki package has to be installed if you want to use `ipsec pki` command for it. As example:
```
ipsec pki --gen --type rsa --size 4096 --outform pem > /etc/ipsec.d/private/cakey.pem

ipsec pki --self --ca --lifetime 3650 --in /etc/ipsec.d/private/cakey.pem --type rsa --dn "CN=VPN root CA" --outform pem > /etc/ipsec.d/certs/cacert.pem
```
CA certificate then has to be added to trusted for VPN clients, otherwise ip-based certificate won't be accepted.
Certificate for ip-based VPN communication has to be signed off by CA-certificate. As example:
```
ipsec pki --gen --type rsa --size 4096 --outform pem > /etc/ipsec.d/private/privkeyip.pem

ipsec pki --pub --in /etc/ipsec.d/private/privkeyip.pem --type rsa | ipsec pki --issue --lifetime 1825 --cacert /etc/ipsec.d/certs/cacert.pem --cakey /etc/ipsec.d/private/cakey.pem --dn "CN=172.105.80.243" --san "172.105.80.243" --flag serverAuth --flag ikeIntermediate --outform pem > /etc/ipsec.d/certs/certip.pem
```
strongswan role has already generated key and certificate included for ip-based VPN connection.
Everything was tested on Ubuntu 18.04 and Debian 10.

## Roles
### nginx
- Install nginx
- Get Let’s encrypt certificate, for that we need to have nginx installed
- Install php5-fpm with a webpage displaying currently connected strongSwan clients and their respective IP addresses (vici interface is used).

### strongSwan
-   Install strongSwan, swanctl (for vici interface), plugins and strongswan-pki
-   Setup two VPN connections: 
	- `ikev2-mschapv2-dns` - based on letsencrypt certificate (FQDN), IPv4 address only assigned for clients
	- `ikev2-mschapv2-ip` - based on local CA certificate (ip-based), dual stack (IPv4 an IPv6) addresses are assigned for clients
-   Use Suite B Cryptographic Suites and EAP-MSCHAPv2 for authentication for both connections, as per [IKEv2 Cipher Suites](https://wiki.strongswan.org/projects/strongswan/wiki/IKEv2CipherSuites#Commercial-National-Security-Algorithm-CNSA-Suite-Suite-B-Cryptographic-Suites-for-IPsec-RFC-6379)
	```
	ike=aes128gcm16-prfsha256-ecp256
	esp=aes128gcm16-ecp256
	```

### iptables
- To deny local clients (connected to the server) to "see" each other the following drop rule was created as first one for FORWARD
``` -A FORWARD -s virtual_ip_range -d virtual_ip_range -j DROP ```		
- As per requirements to secure VPN server only required INPUT and FORWARD traffic is allowed, everything else is DROP'ed

## Issues
### Default gateway for ipv6
Until router-advertisement icmpv6-type INPUT rule was added to ip6tables default gateway for ipv6 disappeared from route table. 

## External Links
- [strongSwan IKEv2 server configuration](https://www.cl.cam.ac.uk/~mas90/resources/strongswan/)
- [ipsec.conf - IPsec configuration and connections](https://manpages.debian.org/testing/strongswan-starter/ipsec.conf.5.en.html)
- [IPV6 Tester](https://test-ipv6.com/)
- [How to Set Up an IKEv2 VPN Server with StrongSwan on Ubuntu 18.04](https://www.digitalocean.com/community/tutorials/how-to-set-up-an-ikev2-vpn-server-with-strongswan-on-ubuntu-18-04-2)
