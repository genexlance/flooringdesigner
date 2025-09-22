<?php
/**
 * Simple transient-based rate limiter.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NFD_Rate_Limiter
{
    private $prefix = 'nfd_rate_';

    public function allow(string $key, int $limit): bool
    {
        $key = sanitize_key($key);
        if ($limit <= 0) {
            return true;
        }
        $bucket = gmdate('YmdHi');
        $transient_key = $this->prefix . $bucket . '_' . $key;
        $count = (int) get_transient($transient_key);
        if ($count >= $limit) {
            return false;
        }
        set_transient($transient_key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }
}
