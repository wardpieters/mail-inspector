<?php

namespace Tests\Support;

class CertificateFixtures
{
    private static array $cache = [];

    private static array $caPaths = [];

    private static array $serverPaths = [];

    private static ?string $rootPath = null;

    public static function chain(string $scenario = 'trusted'): array
    {
        if (isset(self::$cache[$scenario])) {
            self::$rootPath = self::$caPaths[$scenario];

            return self::$cache[$scenario];
        }
        $file = tempnam(sys_get_temp_dir(), 'mail-cert-');
        chmod($file, 0600);
        file_put_contents($file, "[req]\ndistinguished_name=dn\n[dn]\n[v3_ca]\nbasicConstraints=critical,CA:true\nkeyUsage=critical,keyCertSign,cRLSign\nsubjectKeyIdentifier=hash\n[leaf]\nbasicConstraints=critical,CA:false\nkeyUsage=critical,digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\nsubjectAltName=DNS:".($scenario === 'wrong-name' ? 'wrong.test' : ($scenario === 'wildcard' ? '*.test' : 'mx.test'))."\n");
        try {
            $rootKey = openssl_pkey_new(['private_key_bits' => 2048]);
            $rootCsr = openssl_csr_new(['commonName' => 'Fixture CA'], $rootKey, ['config' => $file, 'digest_alg' => 'sha256']);
            $root = openssl_csr_sign($rootCsr, null, $rootKey, 365, ['config' => $file, 'x509_extensions' => 'v3_ca', 'digest_alg' => 'sha256']);
            $key = openssl_pkey_new(['private_key_bits' => $scenario === 'weak' ? 1024 : 2048]);
            $csr = openssl_csr_new(['commonName' => 'mx.test'], $key, ['config' => $file, 'digest_alg' => 'sha256']);
            $leaf = openssl_csr_sign($csr, $scenario === 'self-signed' ? null : $root, $scenario === 'self-signed' ? $key : $rootKey, $scenario === 'expires-soon' ? 29 : 365, ['config' => $file, 'x509_extensions' => 'leaf', 'digest_alg' => 'sha256']);
            openssl_x509_export($leaf, $leafPem);
            openssl_x509_export($root, $rootPem);
            $path = tempnam(sys_get_temp_dir(), 'mail-ca-');
            chmod($path, 0600);
            file_put_contents($path, $rootPem);
            self::$rootPath = $path;
            self::$caPaths[$scenario] = $path;
            openssl_pkey_export($key, $privatePem);
            $serverPath = tempnam(sys_get_temp_dir(), 'mail-server-cert-');
            chmod($serverPath, 0600);
            file_put_contents($serverPath, $leafPem.$rootPem.$privatePem);
            self::$serverPaths[$scenario] = $serverPath;
            $owner = getmypid();
            register_shutdown_function(fn () => getmypid() === $owner ? @unlink($serverPath) : null);
            register_shutdown_function(fn () => getmypid() === $owner ? @unlink($path) : null);

            return self::$cache[$scenario] = [$leafPem, $rootPem];
        } finally {
            unlink($file);
        }
    }

    public static function serverCertificate(string $scenario = 'trusted'): string
    {
        self::chain($scenario);

        return self::$serverPaths[$scenario];
    }

    public static function caFile(): string
    {
        return self::$rootPath;
    }
}
