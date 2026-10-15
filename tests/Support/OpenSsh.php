<?php

namespace Tests\Support;

/**
 * Writes keys and signatures in the files `ssh-keygen` reads, so tests can
 * check the app's signer against OpenSSH itself.
 */
final class OpenSsh
{
    public static function available(): bool
    {
        return trim((string) shell_exec('command -v ssh-keygen')) !== '';
    }

    /**
     * Wraps a one-line SSHSIG blob in the armor `ssh-keygen -Y` expects.
     */
    public static function armorSignature(string $signature): string
    {
        return "-----BEGIN SSH SIGNATURE-----\n".chunk_split($signature, 70, "\n")."-----END SSH SIGNATURE-----\n";
    }

    /**
     * An unencrypted `openssh-key-v1` private key file for the given seed.
     */
    public static function privateKeyFile(string $seed, string $comment = 'devnull-mobile'): string
    {
        $keyPair = sodium_crypto_sign_seed_keypair($seed);
        $publicKey = sodium_crypto_sign_publickey($keyPair);
        $blob = self::string('ssh-ed25519').self::string($publicKey);

        $private = pack('NN', 0x01020304, 0x01020304)
            .self::string('ssh-ed25519')
            .self::string($publicKey)
            .self::string(sodium_crypto_sign_secretkey($keyPair))
            .self::string($comment);

        for ($padding = 1; strlen($private) % 8 !== 0; $padding++) {
            $private .= chr($padding);
        }

        $key = "openssh-key-v1\0"
            .self::string('none')
            .self::string('none')
            .self::string('')
            .pack('N', 1)
            .self::string($blob)
            .self::string($private);

        return "-----BEGIN OPENSSH PRIVATE KEY-----\n".chunk_split(base64_encode($key), 70, "\n")."-----END OPENSSH PRIVATE KEY-----\n";
    }

    /**
     * Runs ssh-keygen with the given stdin and returns [exit code, stdout, stderr].
     *
     * @param  list<string>  $arguments
     * @return array{int, string, string}
     */
    public static function keygen(array $arguments, string $stdin = ''): array
    {
        $process = proc_open(
            ['ssh-keygen', ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    public static function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'devnull-ssh-');
        file_put_contents($path, $contents);
        chmod($path, 0600);

        return $path;
    }

    private static function string(string $bytes): string
    {
        return pack('N', strlen($bytes)).$bytes;
    }
}
