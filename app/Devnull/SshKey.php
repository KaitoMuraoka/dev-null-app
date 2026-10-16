<?php

namespace App\Devnull;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * An Ed25519 key pair that signs like `ssh-keygen -Y sign`.
 *
 * @see https://github.com/openssh/openssh-portable/blob/master/PROTOCOL.sshsig
 */
final readonly class SshKey
{
    private const string Type = 'ssh-ed25519';

    private const string Magic = 'SSHSIG';

    private const string HashAlgorithm = 'sha512';

    private function __construct(
        #[SensitiveParameter] private string $secretKey,
        public string $publicKey,
    ) {}

    public static function generate(): self
    {
        return self::fromSeed(random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES));
    }

    /**
     * Rebuilds the key pair from its 32-byte seed, which is all the Keychain keeps.
     */
    public static function fromSeed(#[SensitiveParameter] string $seed): self
    {
        if (strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new InvalidArgumentException('An Ed25519 seed is 32 bytes.');
        }

        $keyPair = sodium_crypto_sign_seed_keypair($seed);

        return new self(sodium_crypto_sign_secretkey($keyPair), sodium_crypto_sign_publickey($keyPair));
    }

    public function seed(): string
    {
        return substr($this->secretKey, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES);
    }

    /**
     * The public key in the SSH wire format: string("ssh-ed25519") + string(key).
     */
    public function publicKeyBlob(): string
    {
        return self::string(self::Type).self::string($this->publicKey);
    }

    /**
     * The public key as an OpenSSH line, as in `authorized_keys`.
     */
    public function openSsh(string $comment = 'devnull-mobile'): string
    {
        return rtrim(self::Type.' '.base64_encode($this->publicKeyBlob()).' '.$comment);
    }

    /**
     * The fingerprint `ssh-keygen -lf` prints.
     */
    public function fingerprint(): string
    {
        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $this->publicKeyBlob(), true)), '=');
    }

    /**
     * Signs the message and returns the SSHSIG blob, base64 encoded on one line.
     */
    public function sign(string $message, string $namespace): string
    {
        $signedData = self::Magic
            .self::string($namespace)
            .self::string('')
            .self::string(self::HashAlgorithm)
            .self::string(hash(self::HashAlgorithm, $message, true));

        $signature = self::string(self::Type).self::string(sodium_crypto_sign_detached($signedData, $this->secretKey));

        return base64_encode(
            self::Magic
            .pack('N', 1)
            .self::string($this->publicKeyBlob())
            .self::string($namespace)
            .self::string('')
            .self::string(self::HashAlgorithm)
            .self::string($signature)
        );
    }

    /**
     * @return array{type: string, fingerprint: string}
     */
    public function __debugInfo(): array
    {
        return ['type' => self::Type, 'fingerprint' => $this->fingerprint()];
    }

    /**
     * An SSH wire string: a big-endian uint32 length followed by the bytes.
     */
    private static function string(string $bytes): string
    {
        return pack('N', strlen($bytes)).$bytes;
    }
}
