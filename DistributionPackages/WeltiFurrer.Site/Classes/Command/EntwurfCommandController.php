<?php

declare(strict_types=1);

namespace WeltiFurrer\Site\Command;

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
 * Füllt die Site "welti-furrer" mit den Entwurfsinhalten (Seiten + Inhaltselemente).
 */
class EntwurfCommandController extends CommandController
{
    private const IMG = 'https://www.welti-furrer.ch/wp-content/uploads/';

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SecurityContext $securityContext;

    private ContentRepository $cr;
    private WorkspaceName $workspace;
    private OriginDimensionSpacePoint $origin;

    /**
     * Entwurfsinhalte anlegen (nur wenn die Startseite noch leer ist)
     *
     * @param string $site Knotenname der Site
     * @param string $language Sprache (Dimension) der Inhalte
     */
    public function anlegenCommand(string $site = 'welti-furrer', string $language = 'de'): void
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
        if ($homeMain === null) {
            $this->outputLine('<error>Startseite hat keine Inhaltsspalte "main".</error>');
            $this->quit(1);
        }
        if (count($graph->findChildNodeAggregates($homeMain->nodeAggregateId)) > 0
            || count($graph->findChildNodeAggregates($site->nodeAggregateId)) > 1) {
            $this->outputLine('<comment>Die Site hat schon Inhalte - nichts angelegt.</comment>');
            return;
        }

        // --- Unterseiten -------------------------------------------------------
        $home = $site->nodeAggregateId;
        [$privat, $privatMain] = $this->page($home, 'Privatumzüge', 'privatumzuege',
            'Ob 1-Zimmerwohnung oder Villa: Wir sorgen für Ihren reibungslosen Umzug – in der Schweiz und weltweit.',
            self::IMG . '2018/11/Welti-Furrer_International-2560x1707.jpg');
        [$geschaeft, $geschaeftMain] = $this->page($home, 'Geschäftsumzüge', 'geschaeftsumzuege',
            'Firmenumzüge mit minimalem Unterbruch – vom Büro bis zum Labor.',
            self::IMG . '2019/10/gescha%CC%88ftsumzu%CC%88ge2-2560x1440.jpg');
        [$lager, $lagerMain] = $this->page($home, 'Lagerung', 'lagerung',
            'Selfstorage, Aktenlager, Container- und Palettenlager an vier Standorten.',
            self::IMG . '2018/04/Dielsdorf-2560x1440.jpg');
        [$kunst, $kunstMain] = $this->page($home, 'Kunstlogistik', 'kunstlogistik',
            'Welti-Furrer Fine Art: Transport, Lagerung und Installation von Kunst.',
            self::IMG . '2018/10/Kunstlogistik_Anlieferung-2560x1706.jpg');
        [$schwer, $schwerMain] = $this->page($home, 'Schwergutlogistik', 'schwergutlogistik',
            'Keine Ladung ist zu gross und keine Last ist zu schwer.',
            self::IMG . '2018/08/mobilkran-pneukran-7-2560x1570.jpg');
        [$bau, $bauMain] = $this->page($home, 'Baulogistik', 'baulogistik',
            'Von der Konzeption bis zur Entsorgung: Logistik auf der Baustelle aus einer Hand.',
            self::IMG . '2018/08/mobilkran-pneukran-7-2560x1570.jpg');
        [$about, $aboutMain] = $this->page($home, 'Über uns', 'ueber-uns',
            'Tradition und Innovation seit 1838.',
            self::IMG . '2018/04/Dielsdorf-2560x1440.jpg', true);
        [$kontakt, $kontaktMain] = $this->page($home, 'Kontakt', 'kontakt',
            'Wir sind für Sie da – telefonisch, per Offertenanfrage oder an unseren Standorten.',
            null, true);

        // Zweite Ebene (für das Ausklappmenü)
        $sub = [
            [$privat, 'Wohnungsumzüge', 'wohnungsumzug'],
            [$privat, 'Umzüge international', 'internationale-umzuege'],
            [$privat, 'Umzüge im Alter', 'umzug-im-alter'],
            [$privat, 'Siedlungsumzüge', 'siedlungsumzug'],
            [$geschaeft, 'Büroumzüge', 'bueroumzuege'],
            [$geschaeft, 'Bibliotheksumzüge', 'bibliotheksumzuege'],
            [$geschaeft, 'Spital- und Laborumzüge', 'spital-laborumzuege'],
            [$lager, 'Selfstorage', 'selfstorage'],
            [$lager, 'Aktenlager', 'aktenlager'],
            [$lager, 'Lagercontainer', 'lagercontainer'],
            [$kunst, 'Kunstlagerung', 'kunstlager'],
            [$kunst, 'Kunstmessen', 'kunstmessen'],
            [$kunst, 'Museen & Ausstellungen', 'museen'],
            [$schwer, 'Krane', 'krane'],
            [$schwer, 'Spezialtransporte', 'spezialtransporte'],
            [$schwer, 'Industrieumzüge', 'industrieumzuege'],
        ];
        foreach ($sub as [$parent, $title, $segment]) {
            [, $m] = $this->page($parent, $title, $segment, null, null);
            $this->content($m, 'Text', [
                'headline' => $title,
                'text' => '<p>Platzhalter: Hier steht später der Inhalt zur Leistung «' . $title . '». Die Seite zeigt im Entwurf, wie das Menü und die Unterseiten aufgebaut sind.</p>',
                'lead' => true,
            ]);
            $this->cta($m);
        }

        // --- Startseite --------------------------------------------------------
        $main = $homeMain->nodeAggregateId;
        $this->content($main, 'Hero', [
            'kicker' => 'Umzug · Lagerung · Logistik',
            'headline' => 'Ihr Spezialist – seit 1838',
            'text' => '<p>Privat- und Geschäftsumzüge, Lagerung, Kunst- und Schwergutlogistik aus einer Hand. In der Schweiz und weltweit.</p>',
            'imageUrl' => self::IMG . '2018/11/Welti-Furrer_International-2560x1707.jpg',
            'primaryLabel' => 'Offerte anfragen',
            'primaryLink' => '#offerte',
            'secondaryLabel' => 'Unsere Leistungen',
            'secondaryLink' => '#leistungen',
            'showQuoteForm' => true,
        ]);
        $this->content($main, 'Facts', [
            'value1' => '1838', 'label1' => 'Gründungsjahr – seit über 185 Jahren im Einsatz',
            'value2' => '4', 'label2' => 'Lagerstandorte in der Schweiz',
            'value3' => '7', 'label3' => 'Umzugsregionen von Bern bis Winterthur',
            'value4' => 'weltweit', 'label4' => 'Umzüge und Kunsttransporte',
        ]);

        [, $cards] = $this->content($main, 'ServiceGrid', [
            'kicker' => 'Unsere Leistungen',
            'headline' => 'Alles aus einer Hand',
            'text' => '<p>Vom Wohnungsumzug bis zum Schwertransport: Für jeden Auftrag das passende Team, die passenden Fahrzeuge und das nötige Fachwissen.</p>',
            'anchor' => 'leistungen',
        ], 'items');
        $this->card($cards, 'Privatumzüge', 'Ob 1-Zimmerwohnung oder Villa: Wir sorgen für Ihren reibungslosen Umzug. In der Schweiz und weltweit.', '2018/11/Welti-Furrer_International-2560x1707.jpg', $privat);
        $this->card($cards, 'Geschäftsumzüge', 'Firmenumzüge sind komplex. Mit uns als Partner gehen Sie mit minimalem Unterbruch wieder Ihrem Tagesgeschäft nach.', '2019/10/gescha%CC%88ftsumzu%CC%88ge2-2560x1440.jpg', $geschaeft);
        $this->card($cards, 'Lagerung', 'Selfstorage, Akten-, Container- und Palettenlager – flexibel, sicher und an vier Standorten.', '2018/04/Dielsdorf-2560x1440.jpg', $lager);
        $this->card($cards, 'Kunstlogistik', 'Kunsttransporte und Kunstlagerungen sind heikel. Welti-Furrer Fine Art beherrscht dieses anspruchsvolle Metier.', '2018/10/Kunstlogistik_Anlieferung-2560x1706.jpg', $kunst);
        $this->card($cards, 'Schwergutlogistik', 'Keine Ladung ist zu gross und keine Last zu schwer – wir bewegen überdimensionale und unhandliche Güter.', '2018/08/mobilkran-pneukran-7-2560x1570.jpg', $schwer);
        $this->card($cards, 'Baulogistik', 'Konzeption, Baustellen-Infrastruktur, zentrale Logistik und Entsorgung – damit Ihre Baustelle läuft.', null, $bau);

        $this->content($main, 'TextImage', [
            'kicker' => 'Welti-Furrer Fine Art',
            'headline' => 'Für Kunst, die mehr als nur Transport braucht',
            'text' => '<p>Galerien, Auktionshäuser, Museen und private Sammlungen vertrauen auf unsere Kunstlogistik: klimatisierte Lager, eigene Schreinerei für Kisten nach Mass, Kurierbegleitung und Messelogistik bis zur Art Basel.</p><ul><li>Kunstlager und Schliessfächer</li><li>Besichtigungsräume und Atelier</li><li>Messen, Ausstellungen und Museum on Demand</li></ul>',
            'imageUrl' => self::IMG . '2018/10/Kunstlogistik_Anlieferung-2560x1706.jpg',
            'imageRight' => true,
            'variant' => 'dark',
            'link' => 'node://' . $kunst->value,
            'linkLabel' => 'Zur Kunstlogistik',
        ]);

        [, $features] = $this->content($main, 'Features', [
            'kicker' => 'Warum Welti-Furrer',
            'headline' => 'Tradition trifft Innovation',
            'text' => '<p>Platzhaltertexte für den Entwurf – die genauen Aussagen liefert der Kunde.</p>',
            'variant' => 'light',
        ], 'items');
        $this->feature($features, 'clock', 'Seit 1838', 'Erfahrung aus Generationen – und der Anspruch, jeden Auftrag besser zu machen als den letzten.');
        $this->feature($features, 'people', 'Eigene Fachleute', 'Umzugsprofis, Kunsthandwerker, Kranführer und Logistiker unter einem Dach.');
        $this->feature($features, 'leaf', 'Umweltversprechen', 'Wiederverwendbares Verpackungsmaterial und optimierte Touren schonen Ressourcen.');
        $this->feature($features, 'shield', 'Zertifiziert', 'Geprüfte Abläufe und Qualitätsstandards für Privat- und Firmenkunden.');

        [, $news] = $this->content($main, 'NewsList', [
            'headline' => 'Was gibt’s Neues bei Welti-Furrer?',
            'text' => '<p>Aktuelles rund um unsere tägliche Arbeit und besonders spektakuläre Aufträge.</p>',
        ], 'items');
        $this->news($news, '22.09.2023', 'Einblick', 'Einblick in die Schreinerei & die Geschichte des Ritamobils', '2018/10/Kunstlogistik_Anlieferung-2560x1706.jpg');
        $this->news($news, '24.07.2023', 'Kunst', 'Shadowman Van, Rückblick Art Basel, ein Hoch auf Canner!', '2018/11/Welti-Furrer_International-2560x1707.jpg');
        $this->news($news, '25.05.2023', 'Spezialtransport', 'Eine Giraffe auf grosser Fahrt – und Ausblick Art Basel 2023', '2018/08/mobilkran-pneukran-7-2560x1570.jpg');

        $this->cta($main);

        // --- Inhalte der Hauptseiten -------------------------------------------
        $this->servicePage($privatMain, 'Ihr Umzug in guten Händen',
            '<p>Ob Wohnung, Haus oder Villa: Unsere Teams planen Ihren Umzug persönlich, verpacken sorgfältig und transportieren sicher. Auf Wunsch übernehmen wir auch Reinigung, Entsorgung und Zwischenlagerung.</p>',
            [['truck', 'Wohnungsumzüge', 'In sieben Regionen von Bern bis Winterthur.'], ['globe', 'Umzüge international', 'Europa und weltweit, inklusive Relocation.'], ['people', 'Umzüge im Alter', 'Einfühlsame Begleitung beim Wechsel in eine neue Wohnform.'], ['building', 'Siedlungsumzüge', 'Koordinierte Umzüge ganzer Überbauungen.']]);
        $this->servicePage($geschaeftMain, 'Firmenumzüge ohne Stillstand',
            '<p>Wir planen Ihren Geschäftsumzug mit Ihnen gemeinsam – inklusive IT, Archiv und Mobiliar – damit Sie schnell wieder arbeitsfähig sind.</p>',
            [['building', 'Büroumzüge', 'Arbeitsplätze, Möbel und IT effizient verschieben.'], ['box', 'Bibliotheksumzüge', 'Bestände in der richtigen Reihenfolge am neuen Ort.'], ['shield', 'Spital- und Laborumzüge', 'Heikle Geräte und Proben sicher transportiert.'], ['clock', 'Gastronomielogistik', 'Termingerecht für Küche und Service.']]);
        $this->servicePage($lagerMain, 'Platz, wenn Sie ihn brauchen',
            '<p>Vom kleinen Abteil bis zur Palettenfläche: Unsere Lager in Dielsdorf, Zürich, Windisch und Bern sind sauber, sicher und gut erreichbar.</p>',
            [['box', 'Selfstorage', 'Abteile in verschiedenen Grössen.'], ['building', 'Aktenlager', 'Sichere Archivierung für Unternehmen.'], ['truck', 'Lagercontainer', 'Flexibel befüllen, wir holen ab.'], ['shield', 'Notfall-Lager', 'Schnell verfügbare Fläche im Ernstfall.']]);
        $this->servicePage($kunstMain, 'Kunstlogistik mit Fingerspitzengefühl',
            '<p>Welti-Furrer Fine Art transportiert, verpackt, lagert und installiert Kunstwerke für Galerien, Auktionshäuser, Museen und Sammler.</p>',
            [['art', 'Kunstlagerung', 'Klimatisierte Lager und Schliessfächer.'], ['globe', 'Kunstmessen', 'Logistik für Messen im In- und Ausland.'], ['building', 'Museen & Ausstellungen', 'Auf- und Abbau, Kurierbegleitung.'], ['people', 'Sammlungen', 'Betreuung privater und Firmensammlungen.']]);
        $this->servicePage($schwerMain, 'Wenn es schwer wird',
            '<p>Mit eigenen Kranen und Spezialfahrzeugen bewegen wir überdimensionale und unhandliche Güter – geplant mit Hebeplänen und Transportstudien.</p>',
            [['crane', 'Krane', 'Mobil-, Lastwagen-, Mobilbau- und Montagekrane.'], ['truck', 'Spezialtransporte', 'Inklusive Ausnahmetransport-Begleitung.'], ['building', 'Industrieumzüge', 'Maschinen und Anlagen sicher verschoben.'], ['box', 'Schwergutlager', 'Lagerfläche für grosse und schwere Güter.']]);
        $this->servicePage($bauMain, 'Logistik für Ihre Baustelle',
            '<p>Wir koordinieren Material, Betriebsmittel und Entsorgung, damit sich alle Gewerke auf ihre Arbeit konzentrieren können.</p>',
            [['check', 'Konzeption und Planung', 'Logistikkonzept vor Baubeginn.'], ['building', 'Baustellen-Infrastruktur', 'Zugänge, Lager und Wege.'], ['shield', 'Arbeits- und Brandschutz', 'Kontrollen nach Vorgabe.'], ['leaf', 'Entsorgung und Baureinigung', 'Sauber übergeben.']]);

        $this->content($aboutMain, 'Text', [
            'headline' => 'Tradition & Innovation',
            'text' => '<p>Platzhalter: Geschichte des Unternehmens seit 1838, Werte, Umweltversprechen und Zertifizierungen. Die Texte liefert der Kunde.</p>',
            'lead' => true,
        ]);
        $this->content($aboutMain, 'TextImage', [
            'kicker' => 'Unser Umweltversprechen',
            'headline' => 'Verantwortung für morgen',
            'text' => '<p>Platzhalter für Massnahmen im Bereich Umwelt und Nachhaltigkeit.</p>',
            'imageUrl' => self::IMG . '2018/04/Dielsdorf-2560x1440.jpg',
            'variant' => 'grey',
        ]);
        $this->cta($aboutMain);

        [, $locations] = $this->content($kontaktMain, 'LocationList', [
            'headline' => 'So erreichen Sie uns',
            'text' => '<p>Zentrale Telefonnummer +41 44 444 11 11. Adressen der Standorte folgen.</p>',
        ], 'items');
        foreach (['Dielsdorf', 'Zürich', 'Windisch', 'Bern'] as $ort) {
            $this->content($locations, 'Location', [
                'title' => $ort,
                'text' => '<p>Adresse folgt</p>',
                'phone' => '+41 44 444 11 11',
            ]);
        }
        $this->cta($kontaktMain);

        $this->outputLine('<success>Entwurfsinhalte angelegt.</success>');
    }

    /** @return array{NodeAggregateId, NodeAggregateId} Seite und ihre Inhaltsspalte */
    private function page(NodeAggregateId $parent, string $title, string $segment, ?string $subtitle, ?string $imageUrl, bool $hiddenInMenu = false): array
    {
        $id = NodeAggregateId::create();
        $main = NodeAggregateId::create();
        $props = ['title' => $title, 'uriPathSegment' => $segment];
        if ($subtitle !== null) {
            $props['heroSubtitle'] = $subtitle;
        }
        if ($imageUrl !== null) {
            $props['heroImageUrl'] = $imageUrl;
        }
        if ($hiddenInMenu) {
            $props['hiddenInMenu'] = true;
        }
        $this->cr->handle(
            CreateNodeAggregateWithNode::create(
                $this->workspace,
                $id,
                NodeTypeName::fromString('WeltiFurrer.Site:Document.Page'),
                $this->origin,
                $parent,
                null,
                PropertyValuesToWrite::fromArray($props)
            )->withTetheredDescendantNodeAggregateIds(NodeAggregateIdsByNodePaths::fromArray(['main' => $main]))
        );
        $this->outputLine('Seite: %s', [$title]);
        return [$id, $main];
    }

    /** @return array{NodeAggregateId, ?NodeAggregateId} Element und ggf. seine Unterspalte */
    private function content(NodeAggregateId $parent, string $type, array $props, ?string $tethered = null): array
    {
        $id = NodeAggregateId::create();
        $child = $tethered !== null ? NodeAggregateId::create() : null;
        $command = CreateNodeAggregateWithNode::create(
            $this->workspace,
            $id,
            NodeTypeName::fromString('WeltiFurrer.Site:Content.' . $type),
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

    private function card(NodeAggregateId $parent, string $title, string $text, ?string $image, NodeAggregateId $target): void
    {
        $props = [
            'title' => $title,
            'text' => '<p>' . $text . '</p>',
            'link' => 'node://' . $target->value,
            'linkLabel' => 'Mehr erfahren',
        ];
        if ($image !== null) {
            $props['imageUrl'] = self::IMG . $image;
        }
        $this->content($parent, 'ServiceCard', $props);
    }

    private function feature(NodeAggregateId $parent, string $icon, string $title, string $text): void
    {
        $this->content($parent, 'Feature', ['icon' => $icon, 'title' => $title, 'text' => '<p>' . $text . '</p>']);
    }

    private function news(NodeAggregateId $parent, string $date, string $category, string $title, string $image): void
    {
        $this->content($parent, 'NewsItem', [
            'date' => $date,
            'category' => $category,
            'title' => $title,
            'text' => '<p>Kurzer Anriss des Beitrags – im Entwurf als Platzhalter.</p>',
            'imageUrl' => self::IMG . $image,
            'link' => '#',
        ]);
    }

    private function cta(NodeAggregateId $parent): void
    {
        $this->content($parent, 'CtaBand', [
            'headline' => 'Bereit für den nächsten Schritt?',
            'text' => '<p>Rufen Sie uns an oder fordern Sie eine unverbindliche Offerte an – Ihr persönlicher Berater meldet sich bei Ihnen.</p>',
            'phone' => '+41 44 444 11 11',
            'link' => '#',
            'linkLabel' => 'Offerte anfragen',
            'anchor' => 'offerte',
        ]);
    }

    /** @param list<array{string,string,string}> $items */
    private function servicePage(NodeAggregateId $main, string $headline, string $text, array $items): void
    {
        $this->content($main, 'Text', ['headline' => $headline, 'text' => $text, 'lead' => true]);
        [, $features] = $this->content($main, 'Features', [
            'kicker' => 'Leistungen',
            'headline' => 'Was wir für Sie tun',
            'variant' => 'grey',
        ], 'items');
        foreach ($items as [$icon, $title, $desc]) {
            $this->feature($features, $icon, $title, $desc);
        }
        $this->cta($main);
    }
}
