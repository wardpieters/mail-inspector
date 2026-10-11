<?php

$path = $argv[1] ?? dirname(__DIR__).'/.env';
$template = dirname(__DIR__).'/.env.example';
if (! file_exists($path)) {
    if (! copy($template, $path)) {
        fwrite(STDERR, "Cannot create environment file.\n");
        exit(1);
    }
}
chmod($path, 0600);
$text = file_get_contents($path);
foreach (['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'DB_PASSWORD' => bin2hex(random_bytes(24)), 'REDIS_PASSWORD' => bin2hex(random_bytes(24))] as $key => $value) {
    if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $text, $match)) {
        $existing = trim($match[1], " \t\r\n\"'");
        if ($existing !== '' && ! in_array(strtolower($existing), ['null', '(null)'], true)) {
            continue;
        }
        $text = preg_replace('/^'.preg_quote($key, '/').'=[^\r\n]*$/m', $key.'='.$value, $text, 1);
    } else {
        $text .= "\n".$key.'='.$value."\n";
    }
}
$temp = $path.'.tmp.'.bin2hex(random_bytes(6));
$handle = fopen($temp, 'x');
chmod($temp, 0600);
fwrite($handle, $text);
fclose($handle);
rename($temp, $path);
fwrite(STDOUT, "Environment prepared. Existing secrets were preserved.\n");
