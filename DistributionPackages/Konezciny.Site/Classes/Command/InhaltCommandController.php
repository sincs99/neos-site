<?php

declare(strict_types=1);

namespace Konezciny\Site\Command;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeCreation\Dto\NodeAggregateIdsByNodePaths;
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
 * Füllt die Site "konezciny" mit den Startinhalten (Seiten, Projekte, Texte).
 */
class InhaltCommandController extends CommandController
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SecurityContext $securityContext;

    private ContentRepository $cr;
    private WorkspaceName $workspace;
    private OriginDimensionSpacePoint $origin;
    private string $kontaktLink = '#';
    private string $projekteLink = '#';

    /**
     * Startinhalte anlegen (nur wenn die Site noch leer ist)
     *
     * @param string $site Knotenname der Site
     * @param string $language Sprache (Dimension) der Inhalte
     */
    public function anlegenCommand(string $site = 'konezciny', string $language = 'de'): void
    {
        $this->securityContext->withoutAuthorizationChecks(function () use ($site, $language) {
            $this->seed($site, $language);
        });
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

        // --- Seiten ------------------------------------------------------------
        [$projekte, $projekteMain] = $this->page($home, 'Projekte', 'projekte',
            'Woran ich baue: selbst gehostete Dienste, Web-Apps und Werkzeuge – vom ersten Container bis zum Dauerbetrieb.');
        [, $aboutMain] = $this->page($home, 'Über mich', 'ueber-mich',
            'Ich baue und betreibe Technik, die im Alltag einfach funktionieren soll.');
        [$kontakt, $kontaktMain] = $this->page($home, 'Kontakt', 'kontakt',
            'Fragen zu einem Projekt oder eine Idee? Schreib mir.');
        $this->kontaktLink = 'node://' . $kontakt->value;
        $this->projekteLink = 'node://' . $projekte->value;

        // --- Projekte ----------------------------------------------------------
        $this->project($projekte, 'KONIFLIX', 'koniflix', 'live', 'film', 'violet',
            'Plex, Seerr, Tautulli, Kometa, Docker',
            'Mein Streaming-Dienst für Familie und Freunde – mit Wunschliste, automatischen Sammlungen und wöchentlichem Newsletter.',
            '',
            'KONIFLIX ist ein selbst gehosteter Medienserver auf Basis von Plex. Rund um den Server ist eine kleine Plattform entstanden: Zuschauer wünschen sich Filme und Serien über eine eigene Wunschliste, die Startseite stellt sich jeden Tag neu zusammen, und jeden Sonntag geht ein Newsletter mit den Neuheiten raus.',
            [
                ['film', 'Hardware-Transcoding', 'Filme laufen flüssig auf dem Fernseher, im Browser und unterwegs auf dem Handy.'],
                ['check', 'Wunschliste', 'Wünsche laufen über Seerr – mit Benachrichtigung, sobald etwas verfügbar ist.'],
                ['chart', 'Statistik & Newsletter', 'Tautulli zeigt, was geschaut wird, und verschickt den Wochen-Newsletter.'],
                ['globe', 'Automatische Sammlungen', 'Kometa baut Sammlungen wie Oscar-Gewinner, Filmreihen oder „Neu im Kino“.'],
                ['tv', 'Live-TV-Kanäle', 'Eigene Sender aus der Mediathek mit ErsatzTV – noch im Test.'],
                ['bell', 'Überwachung', 'Healthchecks alle 15 Minuten und Alarme per Discord, wenn etwas klemmt.'],
            ]);
        $this->project($projekte, 'Haushalt-App', 'haushalt-app', 'live', 'home', 'cyan',
            'Python, PostgreSQL, Docker, KI',
            'Eine Web-App, die unseren Alltag zu Hause organisiert – mit eigener Anmeldung und KI-Funktionen.',
            'https://casa.konezciny.com',
            'Die Haushalt-App ist eine eigene Web-Anwendung mit Frontend, Backend und PostgreSQL-Datenbank. Sie läuft in Docker-Containern hinter einem Reverse Proxy mit TLS. Neue Versionen kommen per Git und werden mit einem Befehl neu gebaut – Datenbank-Migrationen laufen beim Start automatisch.',
            [
                ['lock', 'Eigene Anmeldung', 'Geschützter Zugang mit eigenen Benutzerkonten.'],
                ['bot', 'KI-Funktionen', 'Mit Tageslimit pro Benutzer, damit die Kosten im Rahmen bleiben.'],
                ['database', 'Tägliche Backups', 'Die Datenbank wird jede Nacht gesichert und 14 Tage aufbewahrt.'],
                ['server', 'Docker Compose', 'Frontend, Backend und Datenbank als getrennte Container.'],
            ]);
        $this->project($projekte, 'Astra', 'astra', 'pilot', 'gamepad', 'green',
            'Game-Panel, Docker, Wings, Linux',
            'Mein eigenes Game-Server-Panel: Server anlegen, starten und verwalten – Minecraft, V Rising und mehr.',
            '',
            'Astra ist ein selbst entwickeltes Panel für Gameserver. Es spricht mit dem Wings-Daemon, startet jeden Server in einem eigenen Container und trennt die Gameserver sauber vom restlichen Netz. Der Pilot läuft in einer eigenen virtuellen Maschine.',
            [
                ['gamepad', 'Mehrere Spiele', 'Minecraft (Paper) und V Rising laufen bereits im Pilot.'],
                ['shield', 'Isolation', 'Gameserver-Container kommen nicht ins Heimnetz.'],
                ['server', 'Eigene VM', 'Ubuntu-VM unter Hyper-V mit Docker und Wings.'],
                ['code', 'Eigener Code', 'Panel und Installation entstehen in einem eigenen Git-Repository.'],
            ]);
        $this->project($projekte, 'Eileen Arts', 'eileen-arts', 'live', 'calendar', 'pink',
            'Booking, Docker, Nginx, Postiz',
            'Online-Buchungssystem und Social-Media-Planung für Eileen Arts.',
            'https://booking.eileen-arts.ch',
            'Für Eileen Arts betreibe ich ein Buchungssystem mit Web-Oberfläche, API und Datenbank sowie eine selbst gehostete Social-Media-Planung, mit der Beiträge für mehrere Kanäle vorbereitet und zeitgesteuert veröffentlicht werden.',
            [
                ['calendar', 'Online-Buchung', 'Termine direkt über booking.eileen-arts.ch.'],
                ['globe', 'Social-Media-Planung', 'Postiz plant Beiträge für mehrere Kanäle im Voraus.'],
                ['lock', 'TLS & Reverse Proxy', 'Eigene Domain mit Let’s-Encrypt-Zertifikat.'],
            ]);
        $this->project($projekte, 'Salty Pirate', 'salty-pirate', 'live', 'bot', 'amber',
            'Discord, SQLite, Docker',
            'Ein Discord-Bot mit Piratenhumor – läuft rund um die Uhr im eigenen Container.',
            '',
            'Salty Pirate ist ein Discord-Bot, der in einem Docker-Container läuft und seine Daten in einer SQLite-Datenbank ablegt. Updates kommen per Git: ziehen, neu bauen, fertig – der Bot ist dabei nur wenige Sekunden offline.',
            [
                ['bot', 'Discord-Bot', 'Läuft 24/7 auf dem eigenen Server.'],
                ['database', 'SQLite', 'Daten in einem eigenen Docker-Volume.'],
                ['code', 'Git-Deployment', 'Update in rund einer Minute.'],
            ]);
        $this->project($projekte, 'Homelab', 'homelab', 'live', 'server', 'red',
            'Windows Server, Docker, Nginx Proxy Manager, Uptime Kuma, PowerShell',
            'Das Fundament: ein eigener Serverraum mit mehreren Servern, Reverse Proxy, Monitoring und automatischen Backups.',
            '',
            'Alle Projekte laufen auf eigener Hardware in einem eigenen Serverraum. Weil ich selten vor Ort bin, ist alles auf Fernwartung ausgelegt: Zugriff per SSH-Schlüssel, Auto-Anmeldung nach Neustarts, Alarme nach aussen und nächtliche Backups.',
            [
                ['server', 'Sechs Server', 'Vom Mini-PC bis zum ProLiant – jeder mit klarer Aufgabe.'],
                ['lock', 'Reverse Proxy', 'Nginx Proxy Manager mit Let’s Encrypt für alle öffentlichen Dienste.'],
                ['chart', 'Monitoring', 'Uptime Kuma und eigene Healthchecks mit Discord-Alarmen.'],
                ['database', 'Backups', 'Nächtliche Sicherungen mit 14 Tagen Aufbewahrung.'],
                ['terminal', 'Automatisierung', 'PowerShell-Skripte und geplante Aufgaben für den Alltag.'],
                ['bot', 'KI-Assistent', 'Claude hilft bei Wartung, Fehlersuche und neuen Projekten.'],
            ]);
        $this->project($projekte, 'Diese Website', 'website', 'dev', 'code', 'cyan',
            'Neos 9, PHP 8.4, MariaDB, Docker',
            'konezciny.com selbst – gebaut mit Neos CMS, eigenem Design und selbst gehostet.',
            '',
            'Diese Website läuft mit Neos 9 in Docker auf einem eigenen Server. Inhalte werden direkt auf der Seite bearbeitet, das Design steckt in einem eigenen Site-Package mit Fusion/AFX-Templates und CSS. Die Projektliste und das Status-Terminal auf der Startseite füllen sich automatisch aus den Projektseiten.',
            [
                ['code', 'Eigenes Site-Package', 'Seitentypen, Bausteine und Design aus einer Hand.'],
                ['server', 'Docker', 'PHP 8.4 mit Apache und MariaDB als Container.'],
                ['globe', 'Inline-Editing', 'Texte direkt auf der Seite ändern, ohne Code.'],
            ]);

        // --- Startseite --------------------------------------------------------
        $main = $homeMain->nodeAggregateId;
        $this->content($main, 'Hero', [
            'kicker' => 'Pascal Konezciny',
            'headline' => 'Ich baue Systeme, die <em>einfach laufen.</em>',
            'text' => '<p>Homelab, Web-Apps und Automatisierung – selbst gehostet im eigenen Serverraum. Hier zeige ich, woran ich arbeite.</p>',
            'primaryLabel' => 'Projekte ansehen',
            'primaryLink' => '#projekte',
            'secondaryLabel' => 'Kontakt',
            'secondaryLink' => '#kontakt',
            'showTerminal' => true,
        ]);
        $this->content($main, 'Stats', [
            'value1' => '6', 'label1' => 'Server im eigenen Serverraum',
            'value2' => '25+', 'label2' => 'Docker-Container im Betrieb',
            'value3' => '7', 'label3' => 'eigene Projekte',
            'value4' => '24/7', 'label4' => 'überwacht mit Alarmen',
        ]);
        $this->content($main, 'ProjectList', [
            'kicker' => 'Projekte',
            'headline' => 'Woran ich arbeite',
            'text' => '<p>Jedes Projekt läuft auf eigener Hardware – vom Streaming-Dienst bis zum Discord-Bot.</p>',
            'anchor' => 'projekte',
        ]);
        [, $stack] = $this->content($main, 'Features', [
            'kicker' => 'Werkzeugkasten',
            'headline' => 'Womit ich arbeite',
            'text' => '<p>Bewährte Werkzeuge, sauber automatisiert – damit ich mich um Neues kümmern kann.</p>',
        ], 'items');
        $this->feature($stack, 'server', 'Self-Hosting & Docker', 'Dienste als Container, mit Compose beschrieben und reproduzierbar.');
        $this->feature($stack, 'code', 'Web-Apps', 'Python, PHP und JavaScript – vom Backend bis zur Oberfläche.');
        $this->feature($stack, 'terminal', 'Automatisierung', 'PowerShell-Skripte, geplante Aufgaben und APIs statt Handarbeit.');
        $this->feature($stack, 'shield', 'Sicherheit', 'SSH nur mit Schlüssel, Firewall-Regeln, TLS für alles Öffentliche.');
        $this->feature($stack, 'chart', 'Monitoring', 'Healthchecks, Uptime Kuma und Discord-Alarme rund um die Uhr.');
        $this->feature($stack, 'bot', 'KI im Alltag', 'Claude als Assistent für Wartung, Fehlersuche und neue Ideen.');

        $this->content($main, 'Text', [
            'kicker' => 'Über mich',
            'headline' => 'Vom Medienserver zum eigenen Serverraum',
            'text' => '<p>Angefangen hat alles mit einem Medienserver für die Familie. Heute steht ein kleiner Serverraum mit eigener Infrastruktur dahinter – und jedes neue Projekt bringt ein Stück mehr Erfahrung.</p><p>Mir ist wichtig, dass Technik im Alltag einfach funktioniert: automatisch gesichert, überwacht und aus der Ferne wartbar.</p>',
            'lead' => true,
        ]);
        $this->cta($main);

        // --- Projekte-Übersicht, Über mich, Kontakt ----------------------------
        $this->content($projekteMain, 'ProjectList', [
            'kicker' => 'Alle Projekte',
            'headline' => 'Live, im Pilot und in Arbeit',
            'text' => '<p>Ein Klick auf ein Projekt zeigt, wie es aufgebaut ist.</p>',
        ]);
        $this->cta($projekteMain);

        $this->content($aboutMain, 'Text', [
            'kicker' => 'Über mich',
            'headline' => 'Hallo, ich bin Pascal.',
            'text' => '<p>Platzhalter: ein paar Sätze zu dir – Beruf, Hintergrund, was dich an Technik reizt. Den Text passt du direkt hier auf der Seite an.</p>',
            'lead' => true,
        ]);
        [, $values] = $this->content($aboutMain, 'Features', [
            'kicker' => 'Grundsätze',
            'headline' => 'So baue ich',
        ], 'items');
        $this->feature($values, 'check', 'Einfach im Alltag', 'Was ich baue, soll ohne Anleitung funktionieren.');
        $this->feature($values, 'database', 'Erst sichern, dann ändern', 'Vor jeder grösseren Änderung gibt es ein Backup und einen Probelauf.');
        $this->feature($values, 'globe', 'Aus der Ferne wartbar', 'Alles muss sich ohne Besuch im Serverraum reparieren lassen.');
        $this->cta($aboutMain);

        $this->content($kontaktMain, 'Cta', [
            'kicker' => 'Kontakt',
            'headline' => 'Schreib mir',
            'text' => '<p>Platzhalter: Kontaktadresse folgt.</p>',
            'primaryLabel' => 'E-Mail schreiben',
            'primaryLink' => '#',
            'secondaryLabel' => 'GitHub',
            'secondaryLink' => 'https://github.com/sincs99',
        ]);

        $this->outputLine('<success>Startinhalte angelegt.</success>');
    }

    /** @return array{NodeAggregateId, NodeAggregateId} */
    private function page(NodeAggregateId $parent, string $title, string $segment, string $teaser, string $type = 'Page', array $extra = []): array
    {
        $id = NodeAggregateId::create();
        $main = NodeAggregateId::create();
        $this->cr->handle(
            CreateNodeAggregateWithNode::create(
                $this->workspace,
                $id,
                NodeTypeName::fromString('Konezciny.Site:Document.' . $type),
                $this->origin,
                $parent,
                null,
                PropertyValuesToWrite::fromArray(array_merge(['title' => $title, 'uriPathSegment' => $segment, 'teaser' => $teaser], $extra))
            )->withTetheredDescendantNodeAggregateIds(NodeAggregateIdsByNodePaths::fromArray(['main' => $main]))
        );
        $this->outputLine('Seite: %s', [$title]);
        return [$id, $main];
    }

    /** @param list<array{string,string,string}> $features */
    private function project(NodeAggregateId $parent, string $title, string $segment, string $status, string $icon, string $accent, string $tags, string $teaser, string $url, string $intro, array $features): void
    {
        [, $main] = $this->page($parent, $title, $segment, $teaser, 'Project', [
            'status' => $status,
            'icon' => $icon,
            'accent' => $accent,
            'tags' => $tags,
            'projectUrl' => $url,
        ]);
        $this->content($main, 'Text', [
            'kicker' => 'Worum es geht',
            'headline' => $title,
            'text' => '<p>' . $intro . '</p>',
            'lead' => true,
        ]);
        [, $list] = $this->content($main, 'Features', [
            'kicker' => 'Bausteine',
            'headline' => 'Was drinsteckt',
        ], 'items');
        foreach ($features as [$fIcon, $fTitle, $fText]) {
            $this->feature($list, $fIcon, $fTitle, $fText);
        }
    }

    /** @return array{NodeAggregateId, ?NodeAggregateId} */
    private function content(NodeAggregateId $parent, string $type, array $props, ?string $tethered = null): array
    {
        $id = NodeAggregateId::create();
        $child = $tethered !== null ? NodeAggregateId::create() : null;
        $command = CreateNodeAggregateWithNode::create(
            $this->workspace,
            $id,
            NodeTypeName::fromString('Konezciny.Site:Content.' . $type),
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

    private function feature(NodeAggregateId $parent, string $icon, string $title, string $text): void
    {
        $this->content($parent, 'Feature', ['icon' => $icon, 'title' => $title, 'text' => '<p>' . $text . '</p>']);
    }

    private function cta(NodeAggregateId $parent): void
    {
        $this->content($parent, 'Cta', [
            'kicker' => 'Kontakt',
            'headline' => 'Lust auf ein Projekt?',
            'text' => '<p>Du hast eine Idee, eine Frage oder willst wissen, wie etwas aufgebaut ist? Schreib mir.</p>',
            'primaryLabel' => 'Kontakt aufnehmen',
            'primaryLink' => $this->kontaktLink,
            'secondaryLabel' => 'Alle Projekte',
            'secondaryLink' => $this->projekteLink,
            'anchor' => 'kontakt',
        ]);
    }
}
