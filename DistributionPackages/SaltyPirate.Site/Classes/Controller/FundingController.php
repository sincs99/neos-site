<?php
declare(strict_types=1);

namespace SaltyPirate\Site\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;

/**
 * Kostenbalken live: Buy-Me-a-Coffee-Webhook annehmen und den Monatsstand als JSON liefern.
 *
 * Daten: Data/Persistent/SaltyPirate/funding.json (Volume neos-persistent), ohne E-Mail-Adressen.
 * Secret: Umgebungsvariable BMC_WEBHOOK_SECRET (Signing Secret aus dem BMC-Dashboard)
 * oder Datei Data/Persistent/SaltyPirate/bmc-webhook-secret.
 */
class FundingController extends ActionController
{
    private const TIMEZONE = 'Europe/Zurich';
    private const RECENT = 5;

    /** Sonst antwortet Flow auf "Accept: application/json" mit 406 */
    protected $supportedMediaTypes = ['application/json', 'text/html', '*/*'];

    /**
     * POST /bmc-webhook
     *
     * @Flow\SkipCsrfProtection
     */
    public function webhookAction(): string
    {
        $secret = $this->secret();
        if ($secret === '') {
            return $this->json(['error' => 'webhook not configured'], 503);
        }
        $http = $this->request->getHttpRequest();
        $body = (string)$http->getBody();
        $signature = strtolower(trim($http->getHeaderLine('x-signature-sha256')));
        if ($signature === '' || !hash_equals(hash_hmac('sha256', $body, $secret), $signature)) {
            return $this->json(['error' => 'invalid signature'], 401);
        }
        $event = json_decode($body, true);
        if (!is_array($event) || !is_string($event['type'] ?? null) || !is_array($event['data'] ?? null)) {
            return $this->json(['error' => 'invalid payload'], 400);
        }

        $this->update(function (array $store) use ($event): array {
            return $this->apply($store, $event);
        });
        return $this->json(['ok' => true]);
    }

    /**
     * GET /api/funding
     */
    public function statusAction(): string
    {
        $store = $this->load();
        $tz = new \DateTimeZone(self::TIMEZONE);
        $now = new \DateTimeImmutable('now', $tz);
        $start = $now->modify('first day of this month')->setTime(0, 0);
        $end = $start->modify('+1 month');
        [$from, $to] = [$start->getTimestamp(), $end->getTimestamp()];

        $raised = 0.0;
        $entries = [];
        foreach ($store['donations'] as $d) {
            if ($d['test'] || $d['refunded'] || $d['at'] < $from || $d['at'] >= $to) {
                continue;
            }
            $raised += $d['amount'];
            $entries[] = $d;
        }
        foreach ($store['subscriptions'] as $s) {
            $active = !$s['test'] && $s['startedAt'] < $to && ($s['endedAt'] === null || $s['endedAt'] >= $from);
            if (!$active) {
                continue;
            }
            $raised += $s['perMonth'];
            $entries[] = ['id' => $s['id'], 'name' => $s['name'], 'amount' => $s['perMonth'], 'coffees' => null, 'monthly' => true, 'at' => max($s['startedAt'], $from)];
        }
        usort($entries, fn ($a, $b) => $b['at'] <=> $a['at']);

        $this->response->setHttpHeader('Cache-Control', 'no-store');
        return $this->json([
            'period' => $start->format('F Y'),
            'raised' => round($raised, 2),
            'supporters' => count($entries),
            'recent' => array_map(fn ($e) => [
                'id' => (string)$e['id'],
                'name' => $e['name'],
                'amount' => round((float)$e['amount'], 2),
                'coffees' => $e['coffees'],
                'monthly' => $e['monthly'] ?? false,
                'at' => $e['at'],
            ], array_slice($entries, 0, self::RECENT)),
            'now' => $now->getTimestamp(),
        ]);
    }

    private function apply(array $store, array $event): array
    {
        $type = $event['type'];
        $data = $event['data'];
        $test = ($event['live_mode'] ?? true) === false;
        $name = trim((string)($data['supporter_name'] ?? ''));
        $name = $name === '' ? 'Someone' : mb_substr($name, 0, 40);

        if (str_starts_with($type, 'donation.')) {
            $id = 'p' . ($data['id'] ?? $data['transaction_id'] ?? $event['event_id']);
            $store['donations'][$id] = [
                'id' => $id,
                'name' => $store['donations'][$id]['name'] ?? $name,
                'amount' => (float)($data['amount'] ?? 0),
                'coffees' => isset($data['coffee_count']) ? (int)$data['coffee_count'] : null,
                'at' => (int)($data['created_at'] ?? $event['created'] ?? time()),
                'refunded' => $type === 'donation.refunded' || ($data['status'] ?? '') === 'refunded',
                'test' => $test,
            ];
        } elseif (str_starts_with($type, 'recurring_donation.') || str_starts_with($type, 'membership.')) {
            $id = 's' . ($data['id'] ?? $data['psp_id'] ?? $event['event_id']);
            $amount = (float)($data['amount'] ?? 0);
            $ended = null;
            if (str_ends_with($type, '.cancelled') || str_ends_with($type, '.paused') || in_array($data['status'] ?? 'active', ['canceled', 'paused'], true)) {
                $ended = (int)($data['canceled_at'] ?? $data['paused_at'] ?? $event['created'] ?? time());
            }
            $store['subscriptions'][$id] = [
                'id' => $id,
                'name' => $name,
                'perMonth' => ($data['duration_type'] ?? 'month') === 'year' ? $amount / 12 : $amount,
                'startedAt' => (int)($data['started_at'] ?? $store['subscriptions'][$id]['startedAt'] ?? $event['created'] ?? time()),
                'endedAt' => $ended,
                'test' => $test,
            ];
        }
        return $store;
    }

    /** Signing Secret: Umgebungsvariable, sonst Datei neben funding.json */
    private function secret(): string
    {
        $secret = trim((string)getenv('BMC_WEBHOOK_SECRET'));
        if ($secret === '') {
            $secret = trim((string)@file_get_contents(dirname($this->file()) . '/bmc-webhook-secret'));
        }
        return $secret;
    }

    private function file(): string
    {
        return FLOW_PATH_DATA . 'Persistent/SaltyPirate/funding.json';
    }

    private function load(): array
    {
        $raw = @file_get_contents($this->file());
        $store = $raw === false ? null : json_decode($raw, true);
        return is_array($store) ? $store + ['donations' => [], 'subscriptions' => []] : ['donations' => [], 'subscriptions' => []];
    }

    /** Lesen, ändern und schreiben unter Dateisperre (Webhooks können gleichzeitig kommen) */
    private function update(callable $change): void
    {
        $file = $this->file();
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        $handle = fopen($file, 'c+');
        flock($handle, LOCK_EX);
        $raw = stream_get_contents($handle);
        $store = $raw ? json_decode($raw, true) : null;
        $store = is_array($store) ? $store + ['donations' => [], 'subscriptions' => []] : ['donations' => [], 'subscriptions' => []];
        $store = $change($store);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function json(array $data, int $status = 200): string
    {
        $this->response->setStatusCode($status);
        $this->response->setContentType('application/json');
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
