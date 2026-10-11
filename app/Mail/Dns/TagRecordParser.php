<?php

namespace App\Mail\Dns;

class TagRecordParser
{
    public function parse(string $record): array
    {
        $tags = [];
        $errors = [];
        $order = [];
        foreach (explode(';', $record) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (! preg_match('/^([a-z][a-z0-9_]*)\s*=\s*(.*)$/is', $part, $m)) {
                $errors[] = 'Malformed tag.';

                continue;
            }
            $name = strtolower($m[1]);
            if (array_key_exists($name, $tags)) {
                $errors[] = 'Duplicate tag '.$name;
            }$tags[$name] = trim($m[2]);
            $order[] = $name;
        }

        return ['tags' => $tags, 'diagnostics' => $errors, 'order' => $order];
    }
}
