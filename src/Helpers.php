<?php
declare(strict_types=1);

use MongoDB\BSON\UTCDateTime;

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function msToYmd($v): string {
    if ($v instanceof UTCDateTime) {
        return $v->toDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }
    if ($v === null || $v === '') return '';
    if (is_numeric($v)) return gmdate('Y-m-d', ((int)$v) / 1000);
    return substr((string)$v, 0, 10);
}

function ymdToMs($s): ?int {
    $s = trim((string)$s);
    if ($s === '') return null;

    // déjà en ms
    if (ctype_digit($s) && strlen($s) >= 12) return (int)$s;

    $s = substr($s, 0, 10);
    $dt = DateTime::createFromFormat('Y-m-d', $s, new DateTimeZone('UTC'));
    if (!$dt) return null;

    return $dt->getTimestamp() * 1000;
}

function moneyToInt($v): int {
    $s = trim((string)$v);
    if ($s === '') return 0;
    if (is_numeric($s)) return (int)$s;
    $digits = preg_replace('/[^\d]/', '', $s);
    return (int)($digits ?: 0);
}

function makeSlug(string $name, ?int $overall = null): string {
    $base = strtolower(trim($name));
    $base = preg_replace('/[^\p{L}\p{N}]+/u', '-', $base);
    $base = trim((string)$base, '-');
    if ($base === '') $base = 'player';
    if ($overall !== null) $base .= '-' . $overall;
    return $base;
}
