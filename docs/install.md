# Installation Guide

## Requirements
* php
  * ext-ldap
  * ext-pdo 
* openldap server (see below)
* mysql
* composer
* npm
* webserver which is .htaccess compatible (apache) -> default laravel stuff 

## LDAP-Requirements (here: compile from source)
```bash
git clone https://git.symas.net/symas-public/openldap.git
cd openldap
git checkout symas/symas-openldap-2.6.10-5 # or whatever version you want
```
* you need the following ./configure flags during compilation, if you compile openldap from scratch you can use the 
following compile script for LTS LDAP 2.5.X
* otp,  sssvlv and ppolicy are there for future use
* tls, argon2 and dynlist are needed and used at the moment

The following scripts builds openldap from source into $HOME/ldapbuild change that to your liking if you want
```shell
#!/bin/bash

set -e #stop on error
set -x #prints commands

cd openldap/

./configure --prefix=$HOME/ldapbuild \
--with-tls=yes \
--enable-modules --enable-ppolicy --enable-otp --enable-argon2 --enable-sssvlv --enable-dynlist

make depend
make
make install
```

## Configuration of the ldap server

Use your favorite editor to edit the provided slapd.ldif example (found at `<PREFIX>/etc/openldap/slapd.ldif`) 
to contain a MDB database for the ldap server.

Some good starting points for the detailed configuration checkout the [official documentation](https://www.openldap.org/doc/admin24/) and the given default config file. 

Here follows a working example, ajust as needed

### slapd.ldif example
```ldif
#
# See slapd-config(5) for details on configuration options.
# This file should NOT be world readable.
#
dn: cn=config
objectClass: olcGlobal
cn: config
#
#
# Define global ACLs to disable default read access.
#
olcArgsFile: <your-path>/ldapbuild/var/run/slapd.args
olcPidFile: <your-path/ldapbuild/var/run/slapd.pid
#
# Do not enable referrals until AFTER you have a working directory
# service AND an understanding of referrals.
#olcReferral:   ldap://root.openldap.org
#
# Sample security restrictions
#       Require integrity protection (prevent hijacking)
#       Require 112-bit (3DES or better) encryption for updates
#       Require 64-bit encryption for simple bind
#olcSecurity: ssf=1 update_ssf=112 simple_bind=64

#
# Load dynamic backend modules:
#
#dn: cn=module,cn=config
#objectClass: olcModuleList
#cn: module
#olcModulepath: /home/pacs/opa00/users/ldap/ldapbuild/libexec/openldap
#olcModuleload: back_mdb.la
#olcModuleload: back_ldap.la
#olcModuleload: back_passwd.la
#olcModuleload: back_shell.la


dn: cn=schema,cn=config
objectClass: olcSchemaConfig
cn: schema

include: file:///home/pacs/opa00/users/ldap/ldapbuild/etc/openldap/schema/core.ldif

# Frontend settings
#
dn: olcDatabase=frontend,cn=config
objectClass: olcDatabaseConfig
objectClass: olcFrontendConfig
olcDatabase: frontend
#
# Sample global access control policy:
#       Root DSE: allow anyone to read it
#       Subschema (sub)entry DSE: allow anyone to read it
#       Other DSEs:
#               Allow self write access
#               Allow authenticated users read access
#               Allow anonymous users to authenticate
#
#olcAccess: to dn.base="" by * read
#olcAccess: to dn.base="cn=Subschema" by * read
#olcAccess: to *
#       by self write
#       by users read
#       by anonymous auth
#
# if no access controls are present, the default policy
# allows anyone and everyone to read anything but restricts
# updates to rootdn.  (e.g., "access to * by * read")
#
# rootdn can always read and write EVERYTHING!
#
 



```
## Running the ldap server

Here we do it with systemd use a service file like this, ajust the paths and the port to your liking if needed
```ini
[Unit]
Description=OpenLDAP Service

[Service]
Type=simple
WorkingDirectory=%h/ldapbuild
ExecStart=%h/ldapbuild/libexec/slapd -d0 -h ldap://127.0.0.1:10100 -F %h/ldapbuild/etc/slapd.d
Restart=always

[Install]
WantedBy=default.target
```


