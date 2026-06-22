<?php
/**
 * Timezone helpers for Business Owner Dashboard
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('bod_site_timezone_string')) {
    function bod_site_timezone_string() {
        $tz = get_option('timezone_string');
        if ($tz) return $tz;
        $offset = (float) get_option('gmt_offset', 0);
        $sign = ($offset < 0) ? '-' : '+';
        $hours = abs((int) $offset);
        $mins  = abs(($offset - (int) $offset) * 60);
        return sprintf('Etc/GMT%s%d', ($offset < 0 ? '+' : '-'), $hours);
    }
}

if (!function_exists('bod_format_datetime')) {
    function bod_format_datetime($datetime, $format = 'M j, Y g:ia T') {
        if (empty($datetime)) return 'N/A';
        try {
            $tz_id  = bod_site_timezone_string();
            $tz     = new DateTimeZone($tz_id ?: 'Australia/Melbourne');
            $raw    = trim((string) $datetime);
            $has_offset = (bool) preg_match('/(Z|[+\-]\d{2}:?\d{2})$/', $raw);
            $src_tz = $has_offset ? null : (function_exists('wp_timezone') ? wp_timezone() : $tz);
            $dt     = $src_tz ? new DateTimeImmutable($raw, $src_tz) : new DateTimeImmutable($raw);
            return esc_html($dt->setTimezone($tz)->format($format));
        } catch (Exception $e) {
            return esc_html((string) $datetime);
        }
    }
}
