<?php

declare(strict_types=1);

namespace SaltyPirate\Site\Command;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeCreation\Dto\NodeAggregateIdsByNodePaths;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;

/**
 * Füllt die Site "saltypirate" mit den Startinhalten (aus README, CHANGELOG und Befehlsregister des Bots).
 */
class AhoiCommandController extends CommandController
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SecurityContext $securityContext;

    private ContentRepository $cr;
    private WorkspaceName $workspace;
    private OriginDimensionSpacePoint $origin;

    /** Befehlsbereiche wie in commands/registry.py */
    private const CATEGORIES = [
        ['🧭', 'Start here', 'Guided tour, server settings and bot status.', 'blurple', [
            '/discover | Guided tour of all features',
            '/help | Command overview',
            '/invite | Add the bot to your server, support server',
            '/settings | Server settings (Manage Server)',
            '/ping | Check if the bot is alive',
            '/about | Bot and setup info',
        ]],
        ['🗺️', 'Navigation', 'Maps, islands, factions, Tall Tales, riddles, lanterns.', 'blue', [
            '/map | Coordinates and region of an island',
            '/island | Island details or the full list',
            '/faction | Faction encyclopedia',
            '/talltale | Tall Tale progression hints',
            '/riddle | Riddle map help: landmarks and clue meanings',
            '/lantern | Lantern color meanings',
            '/skeleton | Skeleton enemy guide',
        ]],
        ['🎣', 'Survival', 'Fish, bait, food, cooking, Hunter’s Call, Megalodon.', 'green', [
            '/fish | Fish overview or a specific fish',
            '/bait | Bait for a fish or bait type',
            '/food | Food overview or a specific item',
            '/cook | Cook time and optional reminder',
            '/hunterscall | Hunter’s Call tips',
            '/megalodon | Megalodon survival tips',
        ]],
        ['⛵', 'Voyage', 'Commodities, routes, world events, trading companies.', 'gold', [
            '/voyage | Voyage planning tips',
            '/fort | Skeleton fort tips',
            '/worldevent | Strategy briefing for a world event',
            '/commodity | Merchant commodity helper',
            '/trading | Trading company tips',
            '/routes | Merchant route tips',
        ]],
        ['🏴‍☠️', 'Guild', 'Pirate profiles, roster, crews and reputation.', 'red', [
            '/profile set | Your gamertag, platform, roles on board',
            '/profile show | A member’s profile',
            '/roster | Guild roster, filter by role or crew',
            '/rep set | Log your reputation level',
            '/rep show | Levels and weekly progress',
            '/rep board | Ranking for one faction',
            '/rep week | Level-ups of the last 7 days',
            '/crew create | Create a crew with its role (admin)',
            '/crew join | Ask to join a crew',
            '/crew info | Members and captain of a crew',
        ]],
        ['🍻', 'Community', 'Crew finder, session planner, loot leaderboard, news.', 'teal', [
            '/lfg | Crew finder with Join/Leave buttons',
            '/session plan | Plan a session with RSVPs and reminders',
            '/session list | Upcoming sessions',
            '/loot log | Log your loot',
            '/loot me | Your loot totals',
            '/leaderboard | Server loot leaderboard',
            '/news | Latest Sea of Thieves announcements',
            '/stats | Milestone card for gold and coins',
        ]],
    ];

    /** Versionen wie in CHANGELOG.md */
    private const RELEASES = [
        ['1.5.0', '2026-10-12', ['New: <code>/invite</code> – add Salty Pirate to your own server or join the support server', 'New: <code>/help</code> shows invite and support server buttons', 'New: updates like this one are announced automatically']],
        ['1.4.0', '2026-10-12', ['New: crew voice channels – when an LFG crew fills up, the bot can open a temporary voice channel', 'New: planned sessions can show up under the server’s Events']],
        ['1.3.0', '2026-10-12', ['New: <code>/session plan</code> with Going / Maybe / Can’t buttons and reminders 30 and 5 minutes before the start', 'New: <code>/loot log</code>, <code>/loot me</code>, <code>/loot undo</code> and a server <code>/leaderboard</code>']],
        ['1.2.0', '2026-10-12', ['New: <code>/lfg</code> crew cards with Join / Leave / Close buttons', 'New: automatic Sea of Thieves news in a channel of your choice and <code>/news</code>']],
        ['1.1.0', '2026-10-12', ['Improved: slash-first <code>/help</code>, clickable commands and a guided <code>/discover</code> tour', 'Fixed: cooking times, settings storage and world event timers per server']],
        ['1.0.0', '2026-03-13', ['First release: islands, maps, factions, fish, bait, food, cooking timers, Tall Tales, riddles, world events and trading companies']],
    ];

    /**
     * Startinhalte anlegen (nur wenn die Site noch leer ist)
     *
     * @param string $site Knotenname der Site
     * @param string $language Sprache (Dimension) der Inhalte
     */
    public function anlegenCommand(string $site = 'saltypirate', string $language = 'en_US'): void
    {
        $this->securityContext->withoutAuthorizationChecks(function () use ($site, $language) {
            $this->seed($site, $language);
        });
    }

    /**
     * Bot-Links der Startseite setzen (nur angegebene Werte werden geändert, "" leert einen Link)
     *
     * @param string|null $invite Einladungslink (Add to Discord)
     * @param string|null $support Community-/Support-Server
     * @param string|null $donate Unterstützen-Link (Buy Me a Coffee)
     * @param string|null $version Aktuelle Bot-Version
     * @param string $site Knotenname der Site
     * @param string $language Sprache (Dimension) der Inhalte
     */
    public function linksCommand(?string $invite = null, ?string $support = null, ?string $donate = null, ?string $version = null, string $site = 'saltypirate', string $language = 'en_US'): void
    {
        $values = array_filter(
            ['inviteUrl' => $invite, 'supportUrl' => $support, 'donateUrl' => $donate, 'version' => $version],
            fn ($v) => $v !== null
        );
        foreach ($values as $key => $value) {
            if ($value !== '' && $key !== 'version' && !preg_match('#^https://#', $value)) {
                $this->outputLine('<error>%s muss mit https:// beginnen.</error>', [$key]);
                $this->quit(1);
            }
        }
        if ($values === []) {
            $this->outputLine('Nichts angegeben (--invite, --support, --donate, --version).');
            return;
        }
        $this->securityContext->withoutAuthorizationChecks(function () use ($values, $site, $language) {
            $cr = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
            $graph = $cr->getContentGraph(WorkspaceName::forLive());
            $sites = $graph->findRootNodeAggregateByType(NodeTypeNameFactory::forSites());
            $siteAggregate = $sites ? $graph->findChildNodeAggregateByName($sites->nodeAggregateId, NodeName::fromString($site)) : null;
            if ($siteAggregate === null) {
                $this->outputLine('<error>Site "%s" nicht gefunden.</error>', [$site]);
                $this->quit(1);
            }
            $cr->handle(SetNodeProperties::create(
                WorkspaceName::forLive(),
                $siteAggregate->nodeAggregateId,
                OriginDimensionSpacePoint::fromArray(['language' => $language]),
                PropertyValuesToWrite::fromArray($values)
            ));
            foreach ($values as $key => $value) {
                $this->outputLine('%s = %s', [$key, $value === '' ? '(leer)' : $value]);
            }
        });
    }

    /**
     * Seite "Setup" (Einrichtungs-Anleitung für Server-Admins) anlegen, vor "Changelog"
     *
     * Inhalte nach main.py (/settings), crews.py und commands/invite.py.
     *
     * @param string $site Knotenname der Site
     * @param string $language Sprache (Dimension) der Inhalte
     */
    public function setupCommand(string $site = 'saltypirate', string $language = 'en_US'): void
    {
        $this->securityContext->withoutAuthorizationChecks(function () use ($site, $language) {
            $this->cr = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
            $this->workspace = WorkspaceName::forLive();
            $this->origin = OriginDimensionSpacePoint::fromArray(['language' => $language]);
            $graph = $this->cr->getContentGraph($this->workspace);
            $sites = $graph->findRootNodeAggregateByType(NodeTypeNameFactory::forSites());
            $siteAggregate = $sites ? $graph->findChildNodeAggregateByName($sites->nodeAggregateId, NodeName::fromString($site)) : null;
            if ($siteAggregate === null) {
                $this->outputLine('<error>Site "%s" nicht gefunden.</error>', [$site]);
                $this->quit(1);
            }
            $before = null;
            foreach ($graph->findChildNodeAggregates($siteAggregate->nodeAggregateId) as $child) {
                if (!$child->occupiesDimensionSpacePoint($this->origin)) {
                    continue;
                }
                $segment = $child->getNodeByOccupiedDimensionSpacePoint($this->origin)->getProperty('uriPathSegment');
                if ($segment === 'setup') {
                    $this->outputLine('<comment>Seite "Setup" gibt es schon - nichts angelegt.</comment>');
                    return;
                }
                if ($segment === 'changelog') {
                    $before = $child->nodeAggregateId;
                }
            }

            $id = NodeAggregateId::create();
            $main = NodeAggregateId::create();
            $this->cr->handle(CreateNodeAggregateWithNode::create(
                $this->workspace,
                $id,
                NodeTypeName::fromString('SaltyPirate.Site:Document.Page'),
                $this->origin,
                $siteAggregate->nodeAggregateId,
                $before,
                PropertyValuesToWrite::fromArray([
                    'title' => 'Setup',
                    'uriPathSegment' => 'setup',
                    'teaser' => 'From invite to your first crew card – set up Salty Pirate on your server in a few minutes.',
                ])
            )->withTetheredDescendantNodeAggregateIds(NodeAggregateIdsByNodePaths::fromArray(['main' => $main])));

            [, $steps] = $this->content($main, 'Steps', [
                'kicker' => 'Setup guide',
                'headline' => 'Ready to sail in a few steps',
                'text' => '<p>The first three steps are all you need. Everything after that is optional – turn on what your crew will use.</p>',
            ], 'items');

            $this->content($steps, 'Step', [
                'title' => 'Invite Salty Pirate',
                'text' => '<p>Click <strong>Add to Discord</strong>, pick your server and keep the suggested permissions – they are exactly what the features need. You need the <strong>Manage Server</strong> permission on that server.</p><p>Already have the bot on another server? <code>/invite</code> gives you the same link.</p>',
                'mockCommand' => '/invite',
                'mockTitle' => '🏴‍☠️ Bring Salty Pirate aboard',
                'mockBody' => "Add Salty Pirate to your server\nJoin the support server – questions, bugs, ideas",
                'mockButtons' => 'Add to your server, Support server',
                'mockColor' => 'blurple',
            ]);
            $this->content($steps, 'Step', [
                'title' => 'Check that it’s aboard',
                'text' => '<p>Type <code>/ping</code> – if the bot answers, you’re good. <code>/about</code> shows bot and setup info. If the slash commands don’t show up right away, wait a moment and reopen Discord.</p>',
            ]);
            $this->content($steps, 'Step', [
                'title' => 'Set your server’s timezone',
                'text' => '<p>When members type times into <code>/session plan</code> (like <code>fri 21:00</code>), the bot reads them in your server’s timezone. Set it once:</p><p><code>/settings timezone:Europe/Berlin</code></p><p>Run <code>/settings</code> without options any time to see all current settings.</p>',
                'mockCommand' => '/settings',
                'mockTitle' => '⚙️ Salty Pirate Settings',
                'mockBody' => "News channel: Not set\nCooking reminders: Enabled\nTimezone for /session: Europe/Berlin\nCrew voice channels: Off\nSessions as server events: Off\nWeekly reputation summary: Off",
                'mockButtons' => '',
                'mockColor' => 'teal',
            ]);
            $this->content($steps, 'Step', [
                'title' => 'Sea of Thieves news',
                'badge' => 'Optional',
                'text' => '<p>Pick a channel for the official Sea of Thieves announcements – the bot checks every 30 minutes and posts new ones there:</p><p><code>/settings news_channel:#sot-news</code></p><p>The bot needs <strong>Send Messages</strong> in that channel. Stop it with <code>/settings disable_news:True</code>. <code>/news</code> shows the latest three on demand.</p>',
            ]);
            $this->content($steps, 'Step', [
                'title' => 'Weekly reputation summary',
                'badge' => 'Optional',
                'text' => '<p>Members log their faction levels with <code>/rep set</code>. Pick a channel and the bot posts who levelled up every Monday at 09:00 in your server’s timezone:</p><p><code>/settings rep_channel:#reputation</code></p><p>Turn it off with <code>/settings disable_rep_summary:True</code>.</p>',
            ]);
            $this->content($steps, 'Step', [
                'title' => 'Voice channels for full crews',
                'badge' => 'Optional',
                'text' => '<p>When an <code>/lfg</code> crew fills up, the bot can open a temporary voice channel for it – limited to the crew size and removed once everyone has left (or after 10 minutes if nobody joins).</p><p><code>/settings lfg_voice:True lfg_voice_category:Crews</code></p><p>Needs <strong>Manage Channels</strong> (part of the invite). Without a category, the channel goes next to the LFG post.</p>',
            ]);
            $this->content($steps, 'Step', [
                'title' => 'Sessions under Events',
                'badge' => 'Optional',
                'text' => '<p>Every <code>/session plan</code> can also be listed under your server’s <strong>Events</strong>. Clicking <em>Interested</em> there counts as Going on the card.</p><p><code>/settings session_events:True</code></p><p>Needs <strong>Manage Events</strong> (part of the invite).</p>',
            ]);
            $this->content($steps, 'Step', [
                'title' => 'Crews with their own role',
                'badge' => 'Optional',
                'text' => '<p>With <code>/crew create</code> every crew gets a mentionable Discord role. For that the bot needs one extra permission that is <strong>not</strong> part of the invite:</p><ul><li>Give the bot’s role <strong>Manage Roles</strong> in <em>Server Settings → Roles</em>.</li><li>Drag the bot’s role <strong>above</strong> the crew roles – Discord only lets it manage roles below its own.</li></ul><p>Then pick captains with <code>/crew captain</code>. Without the permission, crews still work – just without a role.</p>',
            ]);
            $this->content($steps, 'Step', [
                'title' => 'Show your crew around',
                'text' => '<p>Tell your members about <code>/discover</code> – a guided tour of all features – and <code>/help</code> for the full list. A good first step for everyone: <code>/profile set</code> with gamertag, platform and favourite roles on board.</p>',
                'mockCommand' => '/discover',
                'mockTitle' => '🏴‍☠️ Discover Salty Pirate',
                'mockBody' => "Choose a category below to quickly learn which commands to use.",
                'mockButtons' => 'Start here, Navigation, Survival, Voyage, Guild, Community',
                'mockColor' => 'blurple',
            ]);
            $this->cta($main);
            $this->outputLine('<success>Seite "Setup" angelegt.</success>');
        });
    }

    /**
     * Kostenbalken auf der Startseite anlegen (vor dem Aufruf) bzw. Werte ändern
     *
     * @param string|null $raised Bereits gedeckt, z. B. 3.50
     * @param string|null $goal Ziel pro Zeitraum, z. B. 7.42
     * @param string|null $period Zeitraum, z. B. "November 2026"
     * @param string|null $updated Stand, z. B. "1 Nov 2026"
     * @param string $site Knotenname der Site
     * @param string $language Sprache (Dimension) der Inhalte
     */
    public function fundingCommand(?string $raised = null, ?string $goal = null, ?string $period = null, ?string $updated = null, string $site = 'saltypirate', string $language = 'en_US'): void
    {
        $this->securityContext->withoutAuthorizationChecks(function () use ($raised, $goal, $period, $updated, $site, $language) {
            [$siteAggregate, $graph] = $this->openSite($site, $language);
            $main = $graph->findChildNodeAggregateByName($siteAggregate->nodeAggregateId, NodeName::fromString('main'));
            $existing = null;
            $cta = null;
            foreach ($graph->findChildNodeAggregates($main->nodeAggregateId) as $child) {
                $type = $child->nodeTypeName->value;
                if ($type === 'SaltyPirate.Site:Content.Funding') {
                    $existing = $child;
                }
                if ($type === 'SaltyPirate.Site:Content.Cta') {
                    $cta = $child->nodeAggregateId;
                }
            }
            $values = array_filter(['raised' => $raised, 'goal' => $goal, 'period' => $period, 'updated' => $updated], fn ($v) => $v !== null);
            if ($existing !== null) {
                if ($values === []) {
                    $this->outputLine('Kostenbalken gibt es schon. Werte ändern mit --raised, --goal, --period, --updated.');
                    return;
                }
                $this->cr->handle(SetNodeProperties::create($this->workspace, $existing->nodeAggregateId, $this->origin, PropertyValuesToWrite::fromArray($values)));
                foreach ($values as $k => $v) {
                    $this->outputLine('%s = %s', [$k, $v]);
                }
                return;
            }
            $this->cr->handle(CreateNodeAggregateWithNode::create(
                $this->workspace,
                NodeAggregateId::create(),
                NodeTypeName::fromString('SaltyPirate.Site:Content.Funding'),
                $this->origin,
                $main->nodeAggregateId,
                $cta,
                PropertyValuesToWrite::fromArray(array_merge([
                    'kicker' => 'Keep the ship afloat',
                    'headline' => 'Help cover the costs',
                    'text' => '<p>Salty Pirate is free to use and runs on our own hardware. What it costs is the domain and keeping everything running. If the bot helps your crew, a coffee helps us stay afloat – and here you can see every month how much of the costs the community covers.</p>',
                    'goal' => '7.42',
                    'raised' => '0.00',
                    'currency' => '€',
                    'period' => 'October 2026',
                    'costs' => "Domain saltypirate.gg | € 7.42 / month | planned – € 88.98 per year\nServer & hosting | € 0.00 | runs on our own hardware",
                    'updated' => '21 Oct 2026',
                    'anchor' => 'support',
                ], $values))
            ));
            $this->outputLine('<success>Kostenbalken angelegt.</success>');
        });
    }

    /**
     * Seite "Terms" (Terms of Service, Entwurf) anlegen; Privacy und Terms aus dem Hauptmenü nehmen
     *
     * @param string $site Knotenname der Site
     * @param string $language Sprache (Dimension) der Inhalte
     */
    public function termsCommand(string $site = 'saltypirate', string $language = 'en_US'): void
    {
        $this->securityContext->withoutAuthorizationChecks(function () use ($site, $language) {
            [$siteAggregate, $graph] = $this->openSite($site, $language);
            foreach ($graph->findChildNodeAggregates($siteAggregate->nodeAggregateId) as $child) {
                if (!$child->occupiesDimensionSpacePoint($this->origin)) {
                    continue;
                }
                $segment = $child->getNodeByOccupiedDimensionSpacePoint($this->origin)->getProperty('uriPathSegment');
                if ($segment === 'terms') {
                    $this->outputLine('<comment>Seite "Terms" gibt es schon - nichts angelegt.</comment>');
                    return;
                }
                if ($segment === 'privacy') {
                    $this->cr->handle(SetNodeProperties::create($this->workspace, $child->nodeAggregateId, $this->origin, PropertyValuesToWrite::fromArray(['hiddenInMenu' => true])));
                }
            }

            $main = NodeAggregateId::create();
            $this->cr->handle(CreateNodeAggregateWithNode::create(
                $this->workspace,
                NodeAggregateId::create(),
                NodeTypeName::fromString('SaltyPirate.Site:Document.Page'),
                $this->origin,
                $siteAggregate->nodeAggregateId,
                null,
                PropertyValuesToWrite::fromArray([
                    'title' => 'Terms',
                    'uriPathSegment' => 'terms',
                    'teaser' => 'The rules of the ship – what you agree to when you use Salty Pirate.',
                    'hiddenInMenu' => true,
                ])
            )->withTetheredDescendantNodeAggregateIds(NodeAggregateIdsByNodePaths::fromArray(['main' => $main])));

            $this->content($main, 'Text', [
                'kicker' => 'Draft – to be reviewed',
                'headline' => 'Terms of Service',
                'text' => '<p>These terms apply when you add Salty Pirate (“the bot”) to a Discord server or use its commands. By doing so you agree to them.</p>'
                    . '<p><strong>1. What the bot is.</strong> Salty Pirate is an unofficial, fan-made companion for Sea of Thieves. It is not affiliated with, endorsed or sponsored by Rare Ltd. or Microsoft. Game information is provided as help only and may be incomplete or out of date.</p>'
                    . '<p><strong>2. Use of the bot.</strong> You may use the bot on your servers in line with Discord’s Terms of Service and Community Guidelines. Do not use it to spam, harass others, or to break or overload the bot (for example with automated command spam).</p>'
                    . '<p><strong>3. Your content and data.</strong> Profiles, reputation levels, loot entries, sessions and server settings are entered by you and your members and stored so the features work. What is stored and how to remove it is described in our <a href="/en/privacy">Privacy Policy</a>.</p>'
                    . '<p><strong>4. Availability.</strong> The bot is provided “as is”. We try to keep it running, but there is no guarantee of availability, and features may change or be removed.</p>'
                    . '<p><strong>5. Liability.</strong> As far as the law allows, we are not liable for damages arising from the use of the bot or from incorrect information.</p>'
                    . '<p><strong>6. Ending use.</strong> You can stop using the bot at any time by removing it from your server. We may block servers or users that break these terms.</p>'
                    . '<p><strong>7. Changes.</strong> We may update these terms. The current version is always on this page.</p>'
                    . '<p><strong>8. Contact.</strong> Questions? Reach us on the support server.</p>',
            ]);
            $this->outputLine('<success>Seite "Terms" angelegt, Privacy und Terms aus dem Hauptmenü genommen.</success>');
        });
    }

    /** @return array{\Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate, \Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphInterface} */
    private function openSite(string $site, string $language): array
    {
        $this->cr = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $this->workspace = WorkspaceName::forLive();
        $this->origin = OriginDimensionSpacePoint::fromArray(['language' => $language]);
        $graph = $this->cr->getContentGraph($this->workspace);
        $sites = $graph->findRootNodeAggregateByType(NodeTypeNameFactory::forSites());
        $siteAggregate = $sites ? $graph->findChildNodeAggregateByName($sites->nodeAggregateId, NodeName::fromString($site)) : null;
        if ($siteAggregate === null) {
            $this->outputLine('<error>Site "%s" nicht gefunden.</error>', [$site]);
            $this->quit(1);
        }
        return [$siteAggregate, $graph];
    }

    private function seed(string $siteName, string $language): void
    {
        $this->cr = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $this->workspace = WorkspaceName::forLive();
        $this->origin = OriginDimensionSpacePoint::fromArray(['language' => $language]);

        $graph = $this->cr->getContentGraph($this->workspace);
        $sites = $graph->findRootNodeAggregateByType(NodeTypeNameFactory::forSites());
        $site = $sites ? $graph->findChildNodeAggregateByName($sites->nodeAggregateId, NodeName::fromString($siteName)) : null;
        if ($site === null) {
            $this->outputLine('<error>Site "%s" nicht gefunden - zuerst ./flow site:create ausführen.</error>', [$siteName]);
            $this->quit(1);
        }
        $homeMain = $graph->findChildNodeAggregateByName($site->nodeAggregateId, NodeName::fromString('main'));
        if (count($graph->findChildNodeAggregates($homeMain->nodeAggregateId)) > 0
            || count($graph->findChildNodeAggregates($site->nodeAggregateId)) > 1) {
            $this->outputLine('<comment>Die Site hat schon Inhalte - nichts angelegt.</comment>');
            return;
        }
        $home = $site->nodeAggregateId;

        // Bot-Links und Version auf der Startseite (Links leer = "coming soon")
        $this->cr->handle(SetNodeProperties::create($this->workspace, $home, $this->origin, PropertyValuesToWrite::fromArray([
            'title' => 'Salty Pirate',
            'version' => '1.5.0',
            'inviteUrl' => '',
            'supportUrl' => '',
        ])));

        // --- Seiten ------------------------------------------------------------
        [, $commandsMain] = $this->page($home, 'Commands', 'commands', 'Every slash command Salty Pirate knows, grouped by what you need on your voyage.');
        [, $changelogMain] = $this->page($home, 'Changelog', 'changelog', 'What’s new aboard – every release of Salty Pirate.');
        [, $privacyMain] = $this->page($home, 'Privacy', 'privacy', 'What Salty Pirate stores about your server and members – and how to remove it.');

        // --- Startseite --------------------------------------------------------
        $main = $homeMain->nodeAggregateId;
        $this->content($main, 'Hero', [
            'kicker' => 'Sea of Thieves companion for Discord',
            'headline' => 'Your crew’s <em>first mate</em> on Discord.',
            'text' => '<p>Riddle help, island lookups, cooking timers, a crew finder and a session planner – Salty Pirate keeps your voyages on course, so your crew can focus on the plunder.</p>',
            'mockCommand' => '/lfg ship:Galleon activity:Fort',
            'mockTitle' => '⚓ Galleon crew · Fort',
            'mockBody' => "Captain: Deckhand\nAboard: 2 / 4\nThe bot pings everyone as soon as the crew is full.",
            'mockButtons' => 'Join, Leave, Close',
            'mockColor' => 'blurple',
        ]);
        $this->content($main, 'Stats', [
            'value1' => '50+', 'label1' => 'slash commands',
            'value2' => '24', 'label2' => 'large islands with riddle help',
            'value3' => '7', 'label3' => 'factions in the reputation tracker',
            'value4' => '6', 'label4' => 'command categories',
        ]);
        $this->categories($main, false, 'features');

        $this->content($main, 'Showcase', [
            'kicker' => 'Crew finder',
            'headline' => 'Find a crew in seconds',
            'text' => '<p><code>/lfg</code> posts a crew card with <strong>Join</strong>, <strong>Leave</strong> and <strong>Close</strong> buttons and a live slot count – sloop 2, brigantine 3, galleon 4. When the crew is full, everyone gets a ping.</p><p>Optionally the bot opens a temporary voice channel for the crew and removes it again once everyone has left. Buttons keep working after a bot restart; posts expire after 6 hours.</p>',
            'mockCommand' => '/lfg ship:Brigantine activity:Fort',
            'mockTitle' => '⚓ Brigantine crew · Fort',
            'mockBody' => "Aboard: 3 / 3 – crew is full!\nVoice: 🔊 Brigantine – Fort",
            'mockButtons' => 'Join, Leave, Close',
            'mockColor' => 'teal',
        ]);
        $this->content($main, 'Showcase', [
            'kicker' => 'Session planner',
            'headline' => 'Plan tonight’s voyage',
            'text' => '<p><code>/session plan</code> posts a session card with <strong>Going</strong>, <strong>Maybe</strong> and <strong>Can’t</strong>. Everyone sees the start in their own timezone, and attendees are pinged 30 and 5 minutes before and at the start.</p><p>Type times the way you say them: <code>20:30</code>, <code>tomorrow 19:00</code>, <code>fri 21:00</code> or <code>in 2h</code>. If you like, sessions also show up under your server’s Events.</p>',
            'mockCommand' => '/session plan activity:Gold Hoarders when:fri 21:00',
            'mockTitle' => '🗓️ Gold Hoarders voyage',
            'mockBody' => "When: Friday, 21:00 – shown in your local time\nReminders: 30 min, 5 min and at the start",
            'mockButtons' => 'Going, Maybe, Can’t',
            'mockColor' => 'gold',
            'mockLeft' => true,
        ]);
        $this->content($main, 'Showcase', [
            'kicker' => 'Riddle help',
            'headline' => 'Stuck on a riddle?',
            'text' => '<p><code>/riddle</code> covers 24 of the 25 large islands: the landmarks plus what each riddle line points to – and general steps for lanterns, music, paces, directions and digging on any island.</p><p>Just type words from your riddle into <code>clue:</code>. Matching is fuzzy, so <code>skull lantern</code> or <code>10 paces north</code> work.</p>',
            'mockCommand' => '/riddle clue:10 paces north',
            'mockTitle' => '🗺️ Riddle help',
            'mockBody' => "Your clue: 10 paces north\nWhat it means: start at the landmark from the line before and count your paces facing north\nSteps: lantern · music · paces · directions · digging",
            'mockButtons' => '',
            'mockColor' => 'blue',
        ]);
        $this->content($main, 'Showcase', [
            'kicker' => 'Guild tools',
            'headline' => 'Run your guild like a fleet',
            'text' => '<p>Members store their gamertag, platform, roles on board and play times with <code>/profile set</code>; <code>/roster</code> lists everyone. Admins create crews with their own Discord role and pick captains.</p><p>The reputation tracker logs levels for Gold Hoarders, Merchant Alliance, Order of Souls, Reaper’s Bones, Hunter’s Call, Athena’s Fortune and the Smugglers’ League – with a weekly summary every Monday.</p>',
            'mockCommand' => '/rep week',
            'mockTitle' => '🏴‍☠️ Level-ups this week',
            'mockBody' => "Gold Hoarders: 2 pirates levelled up\nMerchant Alliance: 1 pirate levelled up\nPosted automatically every Monday at 09:00",
            'mockButtons' => '',
            'mockColor' => 'red',
            'mockLeft' => true,
        ]);
        $this->content($main, 'Permissions', [
            'kicker' => 'Fair winds, no surprises',
            'headline' => 'Only the permissions it needs',
            'text' => '<p>The invite link asks for exactly what the features use – nothing more. Server settings with <code>/settings</code> require the <strong>Manage Server</strong> permission.</p>',
            'items' => "View channels & read history | to answer where the command was used\nSend messages & messages in threads | to post replies, cards and reminders\nEmbed links & attach files | for rich cards and island images\nUse external emojis | for nicer cards\nManage events | only if you turn on server events for sessions\nManage channels | only for temporary crew voice channels",
        ]);
        $this->changelog($main, 3);
        $this->cta($main);

        // --- Unterseiten -------------------------------------------------------
        $this->categories($commandsMain, true, 'commands');
        $this->content($commandsMain, 'Text', [
            'kicker' => 'Good to know',
            'headline' => 'Prefix commands still work',
            'text' => '<p>Slash commands are the main way to use Salty Pirate. Prefix commands like <code>$fish</code> still work as a fallback – the world event timer <code>$event fort|fleet|ashen</code> is prefix-only.</p>',
        ]);
        $this->cta($commandsMain);

        $this->changelog($changelogMain, count(self::RELEASES));
        $this->cta($changelogMain);

        $this->content($privacyMain, 'Text', [
            'kicker' => 'Draft – to be reviewed',
            'headline' => 'What Salty Pirate stores',
            'text' => '<p>Salty Pirate runs on our own server. To make its features work, it stores per Discord server:</p><ul><li><strong>Server settings</strong> such as channels and timezone</li><li><strong>LFG posts, sessions</strong> and world event timers</li><li><strong>Pirate profiles</strong> (gamertag, platform, roles, play times) that members enter themselves</li><li><strong>Reputation levels and loot entries</strong> that members log</li><li><strong>Cooking reminders</strong> until they are sent</li></ul><p>You can delete your profile with <code>/profile clear</code> and fix loot entries with <code>/loot undo</code>. For anything else, contact us on the support server.</p>',
        ]);

        $this->outputLine('<success>Startinhalte angelegt.</success>');
    }

    private function categories(NodeAggregateId $parent, bool $full, string $anchor): void
    {
        [, $items] = $this->content($parent, 'Categories', [
            'kicker' => $full ? 'All commands' : 'What’s aboard',
            'headline' => $full ? 'The full command list' : 'Everything for the voyage',
            'text' => $full ? '<p>Click a command in Discord to use it, or start with <code>/discover</code> for a guided tour.</p>' : '<p>Six areas, one bot – from your first <code>/discover</code> to guild-wide leaderboards.</p>',
            'showCommands' => $full,
            'anchor' => $anchor,
        ], 'items');
        foreach (self::CATEGORIES as [$emoji, $title, $text, $accent, $commands]) {
            $this->content($items, 'Category', [
                'emoji' => $emoji,
                'title' => $title,
                'text' => $text,
                'accent' => $accent,
                'commands' => implode("\n", $commands),
            ]);
        }
    }

    private function changelog(NodeAggregateId $parent, int $limit): void
    {
        [, $items] = $this->content($parent, 'Changelog', [
            'kicker' => 'Changelog',
            'headline' => 'What’s new aboard',
            'anchor' => 'changelog',
        ], 'items');
        foreach (array_slice(self::RELEASES, 0, $limit) as [$version, $date, $lines]) {
            $this->content($items, 'Release', [
                'version' => $version,
                'date' => $date,
                'text' => '<ul><li>' . implode('</li><li>', $lines) . '</li></ul>',
            ]);
        }
    }

    private function cta(NodeAggregateId $parent): void
    {
        $this->content($parent, 'Cta', [
            'kicker' => 'All hands on deck',
            'headline' => 'Hoist Salty Pirate on your server',
            'text' => '<p>Add the bot to your Discord in a few clicks – the invite link and our community server are coming soon.</p>',
            'anchor' => 'invite',
        ]);
    }

    /** @return array{NodeAggregateId, NodeAggregateId} */
    private function page(NodeAggregateId $parent, string $title, string $segment, string $teaser): array
    {
        $id = NodeAggregateId::create();
        $main = NodeAggregateId::create();
        $this->cr->handle(
            CreateNodeAggregateWithNode::create(
                $this->workspace,
                $id,
                NodeTypeName::fromString('SaltyPirate.Site:Document.Page'),
                $this->origin,
                $parent,
                null,
                PropertyValuesToWrite::fromArray(['title' => $title, 'uriPathSegment' => $segment, 'teaser' => $teaser])
            )->withTetheredDescendantNodeAggregateIds(NodeAggregateIdsByNodePaths::fromArray(['main' => $main]))
        );
        $this->outputLine('Seite: %s', [$title]);
        return [$id, $main];
    }

    /** @return array{NodeAggregateId, ?NodeAggregateId} */
    private function content(NodeAggregateId $parent, string $type, array $props, ?string $tethered = null): array
    {
        $id = NodeAggregateId::create();
        $child = $tethered !== null ? NodeAggregateId::create() : null;
        $command = CreateNodeAggregateWithNode::create(
            $this->workspace,
            $id,
            NodeTypeName::fromString('SaltyPirate.Site:Content.' . $type),
            $this->origin,
            $parent,
            null,
            PropertyValuesToWrite::fromArray($props)
        );
        if ($tethered !== null) {
            $command = $command->withTetheredDescendantNodeAggregateIds(NodeAggregateIdsByNodePaths::fromArray([$tethered => $child]));
        }
        $this->cr->handle($command);
        return [$id, $child];
    }
}
