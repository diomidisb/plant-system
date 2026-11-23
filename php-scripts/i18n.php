<?php

function ps_language(): string
{
    return ($_COOKIE['plant_language'] ?? 'el') === 'en' ? 'en' : 'el';
}
function ps_translations(): array
{
    static $map;
    return $map ??= json_decode(file_get_contents(__DIR__.'/translations.json'), true, 512, JSON_THROW_ON_ERROR);
}
function ps_translate_text(string $text): string
{
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $key = preg_replace('/\s+/u', ' ', trim($decoded));
    $map = ps_translations();
    if (ps_language() === 'el') {
        $translated = $map[$key] ?? null;
    } else {
        static $reverse;
        $reverse ??= array_flip($map);
        $translated = $reverse[$key] ?? null;
    }
    if ($translated === null) {
        return $text;
    }
    preg_match('/\A\s*/u', $text, $start);
    preg_match('/\s*\z/u', $text, $end);
    return $start[0].htmlspecialchars($translated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').$end[0];
}
function ps_translate_html(string $html): string
{
    // JSON APIs, MQTT, exports and redirects are untouched.
    if (!preg_match('/<html\b/i', $html)) {
        return $html;
    }
    $html = preg_replace('/(<html\b[^>]*\blang=)["\'][^"\']*["\']/i', '$1"'.ps_language().'"', $html);
    $html = preg_replace('/<\/head>/i', '<link rel="stylesheet" href="/styles/typography.css?v=20261009"></head>', $html, 1);
    $parts = preg_split('/(<!--[\s\S]*?-->|<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $skip = [];
    foreach ($parts as &$part) {
        if (str_starts_with($part, '<')) {
            if ($skip) {
                if (preg_match('/^<\/(script|style|textarea|pre|code)\s*>/i', $part, $m) && strtolower($m[1]) === end($skip)) {
                    array_pop($skip);
                }
            } elseif (preg_match('/^<(script|style|textarea|pre|code)\b/i', $part, $m)) {
                $skip[] = strtolower($m[1]);
            }
            if (!$skip) {
                $part = preg_replace_callback('/\b(placeholder|aria-label|title)="([^"]*)"/i', fn ($m) => $m[1].'="'.ps_translate_text($m[2]).'"', $part);
            }
        } elseif (!$skip) {
            $part = ps_translate_text($part);
        }
    }
    unset($part);
    return implode('', $parts);
}
if (PHP_SAPI !== 'cli' && !defined('PS_I18N_BUFFER')) {
    define('PS_I18N_BUFFER',true);
    ob_start('ps_translate_html');
}
