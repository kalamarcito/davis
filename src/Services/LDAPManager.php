<?php

declare(strict_types=1);

namespace App\Services;

class LDAPManager
{
    private string $url;
    private string $dnPattern;
    private string $mailAttribute;
    private string $baseDn;
    private string $adminDn;
    private string $adminPassword;

    public function __construct(
        string $LDAPAuthUrl,
        string $LDAPDnPattern,
        string $LDAPMailAttribute,
        string $LDAPBaseDn,
        string $LDAPAdminDn,
        string $LDAPAdminPassword,
    ) {
        $this->url = $LDAPAuthUrl;
        $this->dnPattern = $LDAPDnPattern;
        $this->mailAttribute = $LDAPMailAttribute;
        $this->baseDn = $LDAPBaseDn;
        $this->adminDn = $LDAPAdminDn;
        $this->adminPassword = $LDAPAdminPassword;
    }

    public function isConfigured(): bool
    {
        return !empty($this->url) && !empty($this->adminDn) && !empty($this->adminPassword);
    }

    /**
     * Build the DN for a given username based on the DN pattern.
     */
    public function buildUserDn(string $username): string
    {
        return str_replace('%u', $username, $this->dnPattern);
    }

    /**
     * Create a user in LDAP.
     */
    public function createUser(string $username, string $password, string $displayName, string $email): bool
    {
        $ldap = $this->connect();
        if (!$ldap) {
            return false;
        }

        $dn = $this->buildUserDn($username);

        $entry = [
            'objectClass' => ['inetOrgPerson', 'organizationalPerson', 'person'],
            'uid' => $username,
            'cn' => $displayName,
            'sn' => $displayName,
            $this->mailAttribute => $email,
            'userPassword' => $this->hashPassword($password),
        ];

        try {
            $result = ldap_add($ldap, $dn, $entry);
        } catch (\Exception $e) {
            error_log('LDAP Error (createUser): '.$e->getMessage());
            $result = false;
        }

        ldap_close($ldap);

        return $result;
    }

    /**
     * Update a user's attributes in LDAP.
     */
    public function updateUser(string $username, ?string $password, string $displayName, string $email): bool
    {
        $ldap = $this->connect();
        if (!$ldap) {
            return false;
        }

        $dn = $this->buildUserDn($username);

        $entry = [
            'cn' => $displayName,
            'sn' => $displayName,
            $this->mailAttribute => $email,
        ];

        if ($password) {
            $entry['userPassword'] = $this->hashPassword($password);
        }

        try {
            $result = ldap_modify($ldap, $dn, $entry);
        } catch (\Exception $e) {
            error_log('LDAP Error (updateUser): '.$e->getMessage());
            $result = false;
        }

        ldap_close($ldap);

        return $result;
    }

    /**
     * Delete a user from LDAP.
     */
    public function deleteUser(string $username): bool
    {
        $ldap = $this->connect();
        if (!$ldap) {
            return false;
        }

        $dn = $this->buildUserDn($username);

        try {
            $result = ldap_delete($ldap, $dn);
        } catch (\Exception $e) {
            error_log('LDAP Error (deleteUser): '.$e->getMessage());
            $result = false;
        }

        ldap_close($ldap);

        return $result;
    }

    private function connect()
    {
        try {
            $ldap = ldap_connect($this->url);
        } catch (\Exception $e) {
            error_log('LDAP Error (connect): '.$e->getMessage());
            return false;
        }

        if (!$ldap) {
            return false;
        }

        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

        try {
            if (!ldap_bind($ldap, $this->adminDn, $this->adminPassword)) {
                error_log('LDAP Error: admin bind failed');
                return false;
            }
        } catch (\Exception $e) {
            error_log('LDAP Error (bind): '.$e->getMessage());
            return false;
        }

        return $ldap;
    }

    private function hashPassword(string $password): string
    {
        return '{SSHA}'.base64_encode(
            sha1($password.$salt = random_bytes(4), true).$salt
        );
    }
}
