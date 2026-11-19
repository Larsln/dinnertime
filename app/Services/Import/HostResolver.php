<?php

namespace App\Services\Import;

/**
 * Looks up the IP addresses a host name points to, the way the server
 * would when it connects.
 */
class HostResolver
{
    /**
     * IPv4 and IPv6 addresses of the host. An IP address is answered as it
     * is; an unknown host gets an empty list.
     *
     * @return list<string>
     */
    public function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ipv6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return [...(gethostbynamel($host) ?: []), ...$ipv6];
    }
}
