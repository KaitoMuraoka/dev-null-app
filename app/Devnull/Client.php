<?php

namespace App\Devnull;

use App\Devnull\Data\Commit;
use App\Devnull\Data\CommitThread;
use App\Devnull\Data\CurrentUser;
use App\Devnull\Data\Notification;
use App\Devnull\Data\Page;
use App\Devnull\Data\PairingCode;
use App\Devnull\Data\Profile;
use App\Devnull\Data\User;
use App\Devnull\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use SensitiveParameter;

/**
 * The devnull API, signed with this device's SSH key.
 */
final readonly class Client
{
    public function __construct(private DeviceKey $deviceKey, private string $baseUrl) {}

    /**
     * Asks the server to re-read the user's GitHub keys, find this one among
     * them, and say whether this phone is approved. A GitHub token for their
     * account approves it on the spot. Without one, a phone that isn't approved
     * yet gets a code to enter on the web, and asking again finds out when it was.
     */
    public function syncKey(string $username, #[SensitiveParameter] ?string $githubToken = null, ?string $deviceName = null): User|PairingCode
    {
        $payload = ['username' => $username];

        if ($githubToken !== null && $githubToken !== '') {
            $payload['github_token'] = $githubToken;
        }

        if ($deviceName !== null && $deviceName !== '') {
            $payload['device_name'] = $deviceName;
        }

        $response = $this->send('POST', 'key-syncs', payload: $payload);

        if ($response->status() === 202) {
            return PairingCode::fromArray($this->json($response))
                ?? throw new ApiException('fatal: devnull sent a pairing code this app could not read.', 202);
        }

        return User::fromArray($this->data($response));
    }

    public function me(): CurrentUser
    {
        return CurrentUser::fromArray($this->json($this->send('GET', 'me')));
    }

    /**
     * @return Page<Commit>
     */
    public function log(string $log, ?string $cursor = null): Page
    {
        return Page::fromArray($this->json($this->send('GET', 'log/'.rawurlencode($log), ['cursor' => $cursor])), Commit::fromArray(...));
    }

    public function commit(string $id): CommitThread
    {
        return CommitThread::fromArray($this->json($this->send('GET', 'commits/'.rawurlencode($id))));
    }

    /**
     * @return Page<Commit>
     */
    public function replies(string $id, ?string $cursor = null): Page
    {
        return Page::fromArray($this->json($this->send('GET', 'commits/'.rawurlencode($id).'/replies', ['cursor' => $cursor])), Commit::fromArray(...));
    }

    public function createCommit(string $body, ?string $parentId = null): Commit
    {
        $payload = $parentId === null ? ['body' => $body] : ['body' => $body, 'parent_id' => $parentId];

        return Commit::fromArray($this->data($this->send('POST', 'commits', payload: $payload)));
    }

    public function deleteCommit(string $id): void
    {
        $this->send('DELETE', 'commits/'.rawurlencode($id));
    }

    public function star(string $id): Commit
    {
        return Commit::fromArray($this->data($this->send('POST', 'commits/'.rawurlencode($id).'/star')));
    }

    public function unstar(string $id): Commit
    {
        return Commit::fromArray($this->data($this->send('DELETE', 'commits/'.rawurlencode($id).'/star')));
    }

    /**
     * Saves a commit privately, as the web's bookmark button does.
     */
    public function bookmark(string $id): Commit
    {
        return Commit::fromArray($this->data($this->send('PUT', 'commits/'.rawurlencode($id).'/bookmark')));
    }

    public function unbookmark(string $id): Commit
    {
        return Commit::fromArray($this->data($this->send('DELETE', 'commits/'.rawurlencode($id).'/bookmark')));
    }

    public function profile(string $username): Profile
    {
        return Profile::fromArray($this->json($this->send('GET', 'users/'.rawurlencode($username))));
    }

    /**
     * A tab of someone's profile: their commits, or with ?replies=1 their replies.
     * The server ignores anything else, so a wrong parameter would quietly show commits.
     *
     * @return Page<Commit>
     */
    public function userCommits(string $username, string $tab = 'commits', ?string $cursor = null): Page
    {
        $query = ['replies' => $tab === 'replies' ? '1' : null, 'cursor' => $cursor];

        return Page::fromArray($this->json($this->send('GET', 'users/'.rawurlencode($username).'/commits', $query)), Commit::fromArray(...));
    }

    public function follow(string $username): void
    {
        $this->send('POST', 'users/'.rawurlencode($username).'/follow');
    }

    public function unfollow(string $username): void
    {
        $this->send('DELETE', 'users/'.rawurlencode($username).'/follow');
    }

    /**
     * @return Page<Notification>
     */
    public function notifications(?string $cursor = null): Page
    {
        return Page::fromArray($this->json($this->send('GET', 'notifications', ['cursor' => $cursor])), Notification::fromArray(...));
    }

    /**
     * Sends a signed request. The query string and the body are built here and
     * sent as the exact bytes that were signed, so nothing can re-encode them.
     *
     * @param  array<string, string|null>  $query
     * @param  array<string, mixed>|null  $payload
     */
    private function send(string $method, string $path, array $query = [], ?array $payload = null): Response
    {
        $target = $this->target($path, $query);
        $body = $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $headers = (new RequestSigner($this->deviceKey->current()))
            ->headers($method, $target, $body, CarbonImmutable::now()->getTimestamp(), RequestSigner::nonce());

        $request = Http::acceptJson()->withHeaders($headers)->timeout(20);

        $request = $payload === null
            ? $request->withRequestMiddleware(fn (RequestInterface $request): RequestInterface => $request->withoutHeader('Content-Type'))
            : $request->withBody($body, 'application/json');

        try {
            $response = $request->send($method, $this->origin().$target);
        } catch (ConnectionException) {
            throw new ApiException("fatal: unable to access '{$this->baseUrl}': could not connect to the server");
        }

        if (! $response->successful()) {
            throw ApiException::fromResponse($response);
        }

        return $response;
    }

    /**
     * The request target: the path as the server sees it, plus the query string.
     *
     * @param  array<string, string|null>  $query
     */
    private function target(string $path, array $query): string
    {
        $prefix = rtrim((string) parse_url($this->baseUrl, PHP_URL_PATH), '/');
        $query = array_filter($query, fn (?string $value): bool => $value !== null && $value !== '');

        return $prefix.'/api/v1/'.$path.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    private function origin(): string
    {
        $parts = parse_url($this->baseUrl);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response): array
    {
        $data = $this->json($response)['data'] ?? null;

        return is_array($data) ? $data : [];
    }
}
