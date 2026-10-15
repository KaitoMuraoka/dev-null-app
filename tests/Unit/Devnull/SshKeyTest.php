<?php

use App\Devnull\RequestSigner;
use App\Devnull\SshKey;
use Tests\Support\OpenSsh;

function fixedKey(): SshKey
{
    return SshKey::fromSeed(implode('', array_map(chr(...), range(0, 31))));
}

function fixedMessage(): string
{
    return RequestSigner::message('1760000000', '0123456789abcdef0123456789abcdef', 'post', '/api/v1/commits', '{"body":"Shipped the mobile app"}');
}

it('builds the public key line and fingerprint ssh-keygen would', function () {
    $key = fixedKey();

    expect($key->openSsh())->toBe('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAOhB7/zzhC+HXDdGOdLwJln5NYwm6UNXx3chmQSVTG4 devnull-mobile')
        ->and($key->fingerprint())->toBe('SHA256:lbmsoA0yIEcEiVDRnMWuzm+nV+3ZEEpVIURqFoeSspg')
        ->and($key->publicKeyBlob())->toBe("\0\0\0\x0bssh-ed25519\0\0\0\x20".$key->publicKey);
});

it('joins the five signed parts with newlines and uppercases the method', function () {
    expect(fixedMessage())->toBe("1760000000\n0123456789abcdef0123456789abcdef\nPOST\n/api/v1/commits\n{\"body\":\"Shipped the mobile app\"}")
        ->and(RequestSigner::message('1', 'n', 'get', '/api/v1/me', ''))->toBe("1\nn\nGET\n/api/v1/me\n");
});

it('signs byte for byte the way ssh-keygen -Y sign does', function () {
    // Produced by `ssh-keygen -Y sign -n devnull-api` with the same seed and message.
    expect(fixedKey()->sign(fixedMessage(), 'devnull-api'))->toBe(
        'U1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgA6EHv/POEL4dcN0Y50vAmWfk1jCbpQ1fHdyGZBJVMbgAAAALZGV2bnVsbC1hcGkAAAAAAAAABnNoYTUxMgAAAFMAAAALc3NoLWVkMjU1MTkAAABAQU4un8l6tO+Bf0ZnHV7BgkPyTLCLyMJZUYIgFdVkoGuvh7GwuRRnDhISWyG2L9Uy74s0dxamphyE7cU0XPXyAA=='
    );
});

it('puts the signature for a fixed timestamp and nonce in the headers', function () {
    $headers = (new RequestSigner(fixedKey()))->headers('POST', '/api/v1/commits', '{"body":"Shipped the mobile app"}', 1760000000, '0123456789abcdef0123456789abcdef');

    expect($headers)->toBe([
        'X-Devnull-Timestamp' => '1760000000',
        'X-Devnull-Nonce' => '0123456789abcdef0123456789abcdef',
        'X-SSH-Signature' => fixedKey()->sign(fixedMessage(), 'devnull-api'),
    ]);
});

it('makes a fresh 32 character lowercase hex nonce each time', function () {
    $nonces = collect(range(1, 20))->map(fn (): string => RequestSigner::nonce());

    expect($nonces->unique())->toHaveCount(20);
    $nonces->each(fn (string $nonce) => expect($nonce)->toMatch('/^[0-9a-f]{32}$/'));
});

it('round trips through its seed', function () {
    $key = SshKey::generate();

    expect(SshKey::fromSeed($key->seed())->openSsh())->toBe($key->openSsh());
});

it('rejects a seed of the wrong length', function () {
    SshKey::fromSeed('too short');
})->throws(InvalidArgumentException::class);

it('keeps the secret key out of dumps', function () {
    expect(print_r(fixedKey(), true))->not->toContain(fixedKey()->seed())
        ->toContain('SHA256:lbmsoA0yIEcEiVDRnMWuzm+nV+3ZEEpVIURqFoeSspg');
});

describe('against OpenSSH', function () {
    it('passes ssh-keygen -Y check-novalidate', function () {
        $key = SshKey::generate();
        $message = RequestSigner::message((string) time(), RequestSigner::nonce(), 'GET', '/api/v1/log/everyone?cursor=abc', '');
        $signatureFile = OpenSsh::temporaryFile(OpenSsh::armorSignature($key->sign($message, 'devnull-api')));

        [$exitCode, $stdout, $stderr] = OpenSsh::keygen(['-Y', 'check-novalidate', '-n', 'devnull-api', '-s', $signatureFile], $message);

        expect($exitCode)->toBe(0, $stderr)
            ->and($stdout)->toContain($key->fingerprint());

        [$exitCode] = OpenSsh::keygen(['-Y', 'check-novalidate', '-n', 'devnull-api', '-s', $signatureFile], $message.'tampered');

        expect($exitCode)->not->toBe(0);
    });

    it('produces the same bytes as ssh-keygen -Y sign', function () {
        $key = SshKey::generate();
        $keyFile = OpenSsh::temporaryFile(OpenSsh::privateKeyFile($key->seed()));
        $message = RequestSigner::message((string) time(), RequestSigner::nonce(), 'DELETE', '/api/v1/commits/abc', '');

        [$exitCode, $stdout, $stderr] = OpenSsh::keygen(['-Y', 'sign', '-f', $keyFile, '-n', 'devnull-api'], $message);

        expect($exitCode)->toBe(0, $stderr)
            ->and(preg_replace(['/-----(BEGIN|END) SSH SIGNATURE-----/', '/\s+/'], '', $stdout))->toBe($key->sign($message, 'devnull-api'));
    });

    it('has the fingerprint ssh-keygen -lf prints', function () {
        $key = SshKey::generate();

        [, $stdout] = OpenSsh::keygen(['-lf', OpenSsh::temporaryFile($key->openSsh()."\n")]);

        expect($stdout)->toContain($key->fingerprint());
    });
})->skip(fn (): bool => ! OpenSsh::available(), 'ssh-keygen is not on PATH');
