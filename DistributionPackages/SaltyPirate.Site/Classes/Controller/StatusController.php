<?php
declare(strict_types=1);

namespace SaltyPirate\Site\Controller;

use Neos\Flow\Mvc\Controller\ActionController;

/**
 * Bot-Status für den Footer: GET /api/status
 *
 * Der Wächter auf Centauri (D:\stacks\watch\saltypirate-watch.ps1) schreibt alle 5 Minuten
 * Data/Persistent/SaltyPirate/status.json ({"bot": "up"|"down", "checkedAt": unix}).
 * Ist die Datei älter als STALE_SECONDS, meldet die Seite "unknown" statt eines alten Stands.
 */
class StatusController extends ActionController
{
    private const STALE_SECONDS = 15 * 60;

    /** Sonst antwortet Flow auf "Accept: application/json" mit 406 */
    protected $supportedMediaTypes = ['application/json', 'text/html', '*/*'];

    public function showAction(): string
    {
        $bot = 'unknown';
        $checkedAt = null;
        $raw = @file_get_contents(FLOW_PATH_DATA . 'Persistent/SaltyPirate/status.json');
        $status = $raw === false ? null : json_decode($raw, true);
        if (is_array($status) && is_int($status['checkedAt'] ?? null)) {
            $checkedAt = $status['checkedAt'];
            if (time() - $checkedAt <= self::STALE_SECONDS && in_array($status['bot'] ?? null, ['up', 'down'], true)) {
                $bot = $status['bot'];
            }
        }

        $this->response->setContentType('application/json');
        $this->response->setHttpHeader('Cache-Control', 'no-store');
        return json_encode(['bot' => $bot, 'checkedAt' => $checkedAt]);
    }}
