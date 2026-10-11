<?php

namespace App\Mail\Dns;

class SpfParser
{
    public function parse(string $record): array
    {
        $terms = preg_split('/ +/', trim($record));
        $errors = [];
        $mechanisms = [];
        $modifiers = [];
        if (strtolower(array_shift($terms) ?? '') !== 'v=spf1') {
            $errors[] = 'Missing SPF version.';
        }
        foreach ($terms as $term) {
            if (preg_match('/^([a-z][a-z0-9_.-]*)=(.*)$/i', $term, $match)) {
                $key = strtolower($match[1]);
                if (isset($modifiers[$key])) {
                    $errors[] = 'Duplicate modifier '.$key;
                }if ($match[2] === '') {
                    $errors[] = 'Empty modifier '.$key;
                }$modifiers[$key] = $match[2];

                continue;
            }
            if (! preg_match('/^([+?~-]?)([a-z0-9]+)(.*)$/i', $term, $match)) {
                $errors[] = 'Invalid term '.$term;

                continue;
            }
            [$raw,$qualifier,$name,$argument] = $match;
            $qualifier = $qualifier ?: '+';
            $name = strtolower($name);
            if (! in_array($name, ['all', 'include', 'a', 'mx', 'ptr', 'ip4', 'ip6', 'exists'], true)) {
                $errors[] = 'Unknown mechanism '.$name;

                continue;
            }
            $value = ltrim($argument, ':');
            $macro = str_contains($value, '%');
            if ($name === 'all' && $argument !== '') {
                $errors[] = 'all must not have an argument.';
            }
            if (in_array($name, ['include', 'exists'], true) && (! str_starts_with($argument, ':') || $value === '')) {
                $errors[] = $name.' requires a domain.';
            }
            if (in_array($name, ['ip4', 'ip6'], true)) {
                $parts = explode('/', $value);
                $flag = $name === 'ip4' ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
                $max = $name === 'ip4' ? 32 : 128;
                if (! str_starts_with($argument, ':') || ! filter_var($parts[0], FILTER_VALIDATE_IP, $flag) || count($parts) > 2 || (isset($parts[1]) && (! ctype_digit($parts[1]) || (int) $parts[1] > $max))) {
                    $errors[] = 'Invalid '.$name.' network.';
                }
            }
            if (in_array($name, ['a', 'mx'], true) && ! preg_match('/^(?::[^\/\s]+)?(?:\/(?:[0-9]|[12][0-9]|3[0-2]))?(?:\/\/(?:[0-9]|[1-9][0-9]|1[01][0-9]|12[0-8]))?$/', $argument)) {
                $errors[] = 'Invalid '.$name.' domain/CIDR.';
            }
            if ($name === 'ptr' && $argument !== '' && ! str_starts_with($argument, ':')) {
                $errors[] = 'Invalid ptr domain.';
            }
            $mechanisms[] = ['name' => $name, 'qualifier' => $qualifier, 'value' => $value, 'macro' => $macro, 'lookup' => in_array($name, ['include', 'a', 'mx', 'ptr', 'exists'], true)];
        }

        return ['mechanisms' => $mechanisms, 'modifiers' => $modifiers, 'diagnostics' => $errors];
    }
}
