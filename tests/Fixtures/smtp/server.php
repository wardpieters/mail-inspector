<?php

$bind = $argv[1];
$mode = $argv[2] ?? 'tls';
$certificate = $argv[3] ?? '';
$log = $argv[4];
$context = stream_context_create(['ssl' => ['local_cert' => $certificate, 'verify_peer' => false, 'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER]]);
$server = @stream_socket_server($bind, $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if (! $server) {
    fwrite(STDERR, $error);
    exit(2);
}fwrite(STDOUT, stream_socket_get_name($server, false)."\n");
fflush(STDOUT);
function serverName(string $record): ?string
{
    if (strlen($record) < 44 || ord($record[5]) !== 1) {
        return null;
    }$offset = 43;
    $offset += 1 + ord($record[$offset]);
    if (strlen($record) < $offset + 2) {
        return null;
    }$length = unpack('n', substr($record, $offset, 2))[1];
    $offset += 2 + $length;
    if (strlen($record) < $offset + 1) {
        return null;
    }$offset += 1 + ord($record[$offset]);
    if (strlen($record) < $offset + 2) {
        return null;
    }$offset += 2;
    while ($offset + 4 <= strlen($record)) {
        $type = unpack('n', substr($record, $offset, 2))[1];
        $length = unpack('n', substr($record, $offset + 2, 2))[1];
        $offset += 4;
        if ($type === 0 && $length >= 5) {
            $nameLength = unpack('n', substr($record, $offset + 3, 2))[1];

            return substr($record, $offset + 5, $nameLength);
        }$offset += $length;
    }

    return null;
}
while ($client = @stream_socket_accept($server, 30)) {
    stream_set_timeout($client, 5);
    fwrite($client, $mode === 'refuse' ? "554 5.3.2 IPv6 fixture refuses mail\r\n" : "220 mx.fixture.test ESMTP fixture\r\n");
    if ($mode === 'refuse') {
        fclose($client);

        continue;
    }
    while (($line = fgets($client, 1024)) !== false) {
        $line = trim($line);
        $verb = strtoupper(explode(' ', $line)[0]);
        file_put_contents($log, json_encode(['verb' => $verb])."\n", FILE_APPEND);
        if ($verb === 'DATA') {
            fwrite($client, "554 DATA forbidden in fixture\r\n");
            break;
        }
        if ($verb === 'EHLO') {
            fwrite($client, "250-mx.fixture.test\r\n250-STARTTLS\r\n250 SIZE 1024\r\n");
        } elseif ($verb === 'HELO') {
            fwrite($client, "250 mx.fixture.test\r\n");
        } elseif ($verb === 'STARTTLS') {
            fwrite($client, "220 Ready for TLS\r\n");
            $socket = socket_import_stream($client);
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
            $header = '';
            $count = @socket_recv($socket, $header, 5, MSG_PEEK | MSG_WAITALL);
            if ($count === 5) {
                $record = '';
                $size = unpack('n', substr($header, 3, 2))[1] + 5;
                if ($size <= 16389) {
                    @socket_recv($socket, $record, $size, MSG_PEEK | MSG_WAITALL);
                    file_put_contents($log, json_encode(['sni' => serverName($record)])."\n", FILE_APPEND);
                }
            }
            if (@stream_socket_enable_crypto($client, true) !== true) {
                break;
            }
        } elseif (in_array($verb, ['MAIL', 'RCPT', 'RSET'], true)) {
            fwrite($client, "250 2.0.0 Accepted fixture envelope\r\n");
        } elseif ($verb === 'QUIT') {
            fwrite($client, "221 Bye\r\n");
            break;
        } else {
            fwrite($client, "500 Unsupported command\r\n");
        }
    }
    fclose($client);
}
fclose($server);
