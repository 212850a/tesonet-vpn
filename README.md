
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
- php_fpm_socket_name (to be ready for different versions of php_fpm)

It can be done via `/etc/ansible/host` or in vars for specific role:
```
cat /etc/ansible/host
[linode]
172.105.80.243 domain_name=li2041-243.members.linode.com php_fpm_socket_name=php7.3-fpm.sock

cat ~/# cat strongswan/vars/main.yml
virtual_ip4_range: 10.0.1.0/24
virtual_ip6_range: 2001:db8::/96
```

For the second (ip-based) VPN connection certificate for local CA has to be generated first. strongswan-pki package has to be installed if you want to use `ipsec pki` command for it. As example:
```
ipsec pki --gen --type rsa --size 4096 --outform pem > /etc/ipsec.d/private/cakey.pem

ipsec pki --self --ca --lifetime 3650 --in /etc/ipsec.d/private/cakey.pem --type rsa --dn "CN=VPN root CA" --outform pem > /etc/ipsec.d/certs/cacert.pem
```
CA certificate then has to be added to trusted on VPN client's side, otherwise ip-based certificate won't be accepted.
Certificate for ip-based VPN communication has to be signed off by CA-certificate. As example:
```
ipsec pki --gen --type rsa --size 4096 --outform pem > /etc/ipsec.d/private/privkeyip.pem

ipsec pki --pub --in /etc/ipsec.d/private/privkeyip.pem --type rsa | ipsec pki --issue --lifetime 1825 --cacert /etc/ipsec.d/certs/cacert.pem --cakey /etc/ipsec.d/private/cakey.pem --dn "CN=172.105.80.243" --san "172.105.80.243" --flag serverAuth --flag ikeIntermediate --outform pem > /etc/ipsec.d/certs/certip.pem
```
strongswan role has already generated key and certificate included for ip-based VPN connection.

Everything was tested on Ubuntu 18.04 (AWS, IPv4 only) and Debian 10.3 (IPv4+IPv6)

## Roles
### nginx
- Install nginx
- Get Let’s encrypt certificate, for that we need to have nginx already installed
- Install php5-fpm with a webpage displaying currently connected strongSwan clients and their respective IP addresses (vici interface is used). Until strongSwan packages are installed it will show nothing.

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
- To allow nginx to have access to vici socket in charon.conf the following is added:
```
# Name of the group the daemon changes to after startup.
group = www-data
```
- Enable IPv4 and IPv6 packet forwarding

### iptables
- To deny local clients (connected to the server) to "see" each other the following drop rule was created as first one for FORWARD
```
-A FORWARD -s virtual_ip_range -d virtual_ip_range -j DROP`	
```	
- As per requirements to secure VPN server only required INPUT and FORWARD traffic is allowed, everything else is DROP'ed. Additionally ssh for root should be closed and some local account should be created instead.

## Status
Status page example with two clients connected on different connections (ikev2-mschapv2-ip and ikev2-mschapv2-dns)
```
IKE_SAs: 2 total, 0 half-open

ikev2-mschapv2-ip: #7, ESTABLISHED, IKEv2, f857c78f7de69769_i 0e52b55f420fcca1_r*
  local  '172.105.80.243' @ 172.105.80.243[4500]
  remote '192.168.0.113' @ 77.77.77.77[4500] EAP: 'BruceLee' [10.0.1.1 2001:db8::1]
  AES_CBC-256/HMAC_SHA2_256_128/PRF_HMAC_SHA2_256/MODP_2048
  established 2s ago
  ikev2-mschapv2-ip: #6, reqid 6, INSTALLED, TUNNEL-in-UDP, ESP:AES_CBC-256/HMAC_SHA2_256_128
    installed 2s ago
    in  c8195d9f,  11996 bytes,   100 packets,     0s ago
    out 01646347,  29730 bytes,    84 packets,     0s ago
    local  0.0.0.0/0 ::/0
    remote 10.0.1.1/32 2001:db8::1/128
ikev2-mschapv2-dns: #6, ESTABLISHED, IKEv2, 1cfbf9aa16084b5d_i 40114e057872fa0b_r*
  local  'li2041-243.members.linode.com' @ 172.105.80.243[4500]
  remote '192.168.0.128' @ 77.77.77.77[1029] EAP: 'ChuckNorris' [10.0.1.2]
  AES_CBC-256/HMAC_SHA2_256_128/PRF_HMAC_SHA2_256/MODP_2048
  established 33s ago
  ikev2-mschapv2-dns: #5, reqid 5, INSTALLED, TUNNEL-in-UDP, ESP:AES_CBC-256/HMAC_SHA2_256_128
    installed 33s ago
    in  c2935cb9,  24453 bytes,   152 packets,     6s ago
    out 0220effc,  54480 bytes,   125 packets,     6s ago
    local  0.0.0.0/0
    remote 10.0.1.2/32
```

## Issues
### Default gateway for ipv6
Until router-advertisement icmpv6-type INPUT rule was added to ip6tables default gateway for ipv6 disappeared from route table. 
### AWS EC2 instances are behind NAT
If AWS EC2 is planned to be used as strongSwan VPN server you have to remember that EC2 instances are already located behind NAT, so instead of MASQUERADE SNAT POSTROUTING rule should be used. As example:
```
iptables -t nat -A POSTROUTING -s 10.0.1.0/24 -o eth0 -j SNAT --to-source 172.31.47.2
```
where 172.31.47.2 is internal ip-address of EC2 instance


## External Links
- [strongSwan IKEv2 server configuration](https://www.cl.cam.ac.uk/~mas90/resources/strongswan/)
- [ipsec.conf - IPsec configuration and connections](https://manpages.debian.org/testing/strongswan-starter/ipsec.conf.5.en.html)
- [IPV6 Tester](https://test-ipv6.com/)
- [How to Set Up an IKEv2 VPN Server with StrongSwan on Ubuntu 18.04](https://www.digitalocean.com/community/tutorials/how-to-set-up-an-ikev2-vpn-server-with-strongswan-on-ubuntu-18-04-2)
