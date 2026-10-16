<?php

namespace App\Devnull;

use Illuminate\Http\Client\ConnectionException;
use Native\Mobile\AsyncTask;

/**
 * Sends a GET, signed on the UI thread, from one of NativePHP's background PHP
 * threads, so a refresh never holds up a tap. It only carries bytes: the screen
 * that asked decides what the answer means and keeps it (see
 * Concerns\RefreshesInBackground). Nothing here touches the Keychain or the cache.
 */
final class FetchInBackground extends AsyncTask
{
    /**
     * @param  array<string, string>  $headers
     * @return array{status: int, json: mixed, retry_after: string}
     */
    public function handle(string $url, array $headers): array
    {
        try {
            $response = Client::sendSigned(new SignedRequest($url, $headers));
        } catch (ConnectionException) {
            return ['status' => 0, 'json' => null, 'retry_after' => ''];
        }

        return ['status' => $response->status(), 'json' => $response->json(), 'retry_after' => $response->header('Retry-After')];
    }
}
