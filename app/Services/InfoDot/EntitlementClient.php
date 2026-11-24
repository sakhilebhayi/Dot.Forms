<?php

namespace App\Services\InfoDot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks InfoDot whether a company still holds this platform (R-50).
 *
 * One question, about one company, answered from InfoDot's read endpoint with this platform's own service secret.
 * The answer is kept for sixty seconds per company (R-61: a platform switch at InfoDot shows within about a minute); a
 * failure for one minute. The answer also says whether InfoDot has the platform switched on (`available`, `message`).
 * Every clear answer is also remembered for six hours: when InfoDot gives no clear answer (InfoDot down, slow,
 * refusing, a body this app cannot read, no secret configured) the last clear answer under six hours old is used, so
 * a platform last told "unavailable" stays unavailable. With none, the answer is "unknown", which never locks a
 * company out: the caller carries on as if the check did not exist. A failure is logged once per window.
 */
class EntitlementClient
{
    public const ANSWER_TTL = 60;

    public const FAILURE_TTL = 60;

    /** How long the last clear answer is used when InfoDot cannot be reached. */
    public const LAST_KNOWN_TTL = 21600;

    public function enabled(): bool
    {
        return (bool) config('ecosystem.infodot.entitlement_check', true);
    }

    /**
     * The answer already remembered for this company, or null. Never calls InfoDot.
     *
     * @return array{entitled: bool, status: string, available: bool, message: string|null}|null
     */
    public function remembered(string $uuid): ?array
    {
        try {
            $cached = Cache::get($this->key($uuid));
        } catch (Throwable) {
            return null;
        }

        return is_array($cached) && isset($cached['entitled']) ? $this->shape($cached) : null;
    }

    /**
     * @return array{entitled: bool, status: string, available: bool, message: string|null}|null null when InfoDot gave no usable answer and none is remembered
     */
    public function forCompany(string $uuid): ?array
    {
        $key = $this->key($uuid);

        try {
            $cached = Cache::get($key);
        } catch (Throwable) {
            $cached = null;
        }

        if (is_array($cached)) {
            return isset($cached['entitled']) ? $this->shape($cached) : null;
        }

        $answer = $this->ask($uuid);

        if (is_string($answer)) {
            Log::warning('InfoDot entitlement check gave no answer; carrying on without it.', ['company' => $uuid, 'reason' => $answer]);

            $last = null;
            try {
                $last = Cache::get($key.':last');
            } catch (Throwable) {
                // No memory: carry on as before.
            }

            if (is_array($last) && is_array($last['answer'] ?? null) && isset($last['answer']['entitled']) && time() - (int) ($last['at'] ?? 0) < self::LAST_KNOWN_TTL) {
                $this->remember($key, $last['answer'], self::FAILURE_TTL);

                return $this->shape($last['answer']);
            }

            $this->remember($key, ['unknown' => true], self::FAILURE_TTL);

            return null;
        }

        $this->remember($key, $answer, self::ANSWER_TTL);
        $this->remember($key.':last', ['answer' => $answer, 'at' => time()], self::LAST_KNOWN_TTL);

        return $answer;
    }

    private function key(string $uuid): string
    {
        return 'infodot:entitlement:'.$this->platform().':'.$uuid;
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{entitled: bool, status: string, available: bool, message: string|null}
     */
    private function shape(array $c): array
    {
        return [
            'entitled' => (bool) $c['entitled'],
            'status' => (string) ($c['status'] ?? ''),
            // R-61: an older cached answer, or an InfoDot without the field, is never a switch-off.
            'available' => ($c['available'] ?? true) !== false,
            'message' => is_string($c['message'] ?? null) && $c['message'] !== '' ? $c['message'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function remember(string $key, array $value, int $seconds): void
    {
        try {
            Cache::put($key, $value, $seconds);
        } catch (Throwable) {
            // A cache that cannot be written only means the next request asks again.
        }
    }

    /**
     * @return array{entitled: bool, status: string, available: bool, message: string|null}|string the answer, or why there is none
     */
    private function ask(string $uuid): array|string
    {
        $platform = $this->platform();
        $credential = (string) config('ecosystem.infodot.secret');
        $url = $this->url($uuid);

        if ($credential === '' || $platform === '' || $url === null) {
            return 'not configured';
        }

        try {
            $response = Http::withToken($credential)
                ->withHeaders(['X-Dot-Platform' => $platform])
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout(max(1, min(3, (int) config('ecosystem.infodot.timeout', 5))))
                ->get($url, ['platform' => $platform]);
        } catch (Throwable $e) {
            return 'unreachable ('.class_basename($e).')';
        }

        if (! $response->successful()) {
            return 'status '.$response->status();
        }

        $body = $response->json();

        if (! is_array($body) || ($body['platform'] ?? null) !== $platform || ! is_array($body['platforms'] ?? null)) {
            return 'malformed body';
        }

        foreach ($body['platforms'] as $row) {
            if (is_array($row) && ($row['key'] ?? null) === $platform) {
                if (! is_bool($row['entitled'] ?? null)) {
                    return 'malformed body';
                }

                return [
                    'entitled' => $row['entitled'],
                    'status' => is_string($row['status'] ?? null) ? $row['status'] : '',
                    // R-61: absent, or anything but exactly false, is available.
                    'available' => ($row['available'] ?? true) !== false,
                    'message' => is_string($row['message'] ?? null) && $row['message'] !== '' ? mb_substr($row['message'], 0, 400) : null,
                ];
            }
        }

        return 'own row missing';
    }

    private function platform(): string
    {
        return (string) config('ecosystem.infodot.platform');
    }

    /** The read endpoint sits beside the redeem endpoint: .../api/ecosystem/companies/{uuid}/platforms. */
    private function url(string $uuid): ?string
    {
        $redeem = (string) config('ecosystem.infodot.redeem_url');

        if ($redeem === '' || ! preg_match('#/redeem/?$#', $redeem)) {
            return null;
        }

        return preg_replace('#/redeem/?$#', '', $redeem).'/companies/'.rawurlencode($uuid).'/platforms';
    }
}
