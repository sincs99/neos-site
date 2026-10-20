<?php

declare(strict_types=1);

namespace Astronomique\Site\Command;

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
 * Füllt die Site "astronomique" mit den Startinhalten (Texte aus der alten WordPress-Seite).
 */
class SterneCommandController extends CommandController
{
    private const IMG = 'https://astronomique.ch/wp-content/uploads/';
    private const SHOP = 'https://astronomique.ch/?page_id=7';
    private const FACEBOOK = 'https://www.facebook.com/balinightsgin/';

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SecurityContext $securityContext;

    private ContentRepository $cr;
    private WorkspaceName $workspace;
    private OriginDimensionSpacePoint $origin;

    /**
     * Startinhalte anlegen (nur wenn die Site noch leer ist)
     *
     * @param string $site Knotenname der Site
     * @param string $language Sprache (Dimension) der Inhalte
     */
    public function anlegenCommand(string $site = 'astronomique', string $language = 'de'): void
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
        [$produkte, $produkteMain] = $this->page($home, 'Produkte', 'produkte', 'Bali Nights Tropical Gin und Fleur de Sel aus Mauritius.');
        [, $storyMain] = $this->page($home, 'Geschichte', 'geschichte', 'Ausgedacht in warmen Nächten an den Stränden Balis.');
        [, $kontaktMain] = $this->page($home, 'Kontakt', 'kontakt', 'Fragen zu unseren Produkten oder zu einer Bestellung?');

        [$gin, $ginMain] = $this->page($produkte, 'Bali Nights Gin', 'bali-nights-gin',
            'Traditionelle Botanicals und frische tropische Früchte, abgerundet mit bestem Schweizer Quellwasser.',
            'Product', [
                'subtitle' => 'Tropical Dry Gin',
                'price' => 'CHF 45.00',
                'facts' => "Inhalt: 500 ml\nAlkohol: 42 % Vol.\nWasser: Schweizer Quellwasser\nCharakter: Botanicals und tropische Früchte",
                'availability' => 'instock',
                'shopUrl' => 'https://astronomique.ch/?product=bali-nights-tropical-gin',
                'imageUrl' => self::IMG . '2023/11/balinight-flasche-scaled-e1701795985478.jpg',
                'tone' => 'tropical',
            ]);
        [$salz, $salzMain] = $this->page($produkte, 'Fleur de Sel', 'fleur-de-sel',
            'Feinstes Fleur de Sel aus Mauritius – von Hand geerntet und an der Sonne getrocknet.',
            'Product', [
                'subtitle' => 'aus Mauritius',
                'price' => 'CHF 11.90',
                'facts' => "Inhalt: 100 g\nHerkunft: Mauritius\nZutaten: Meersalz (NaCl 99,5 %)\nHaltbarkeit: unbegrenzt, trocken gelagert",
                'availability' => 'soldout',
                'shopUrl' => 'https://astronomique.ch/?product=fleur-de-sel',
                'imageUrl' => self::IMG . '2024/01/balinight-salz-scaled-e1705166312288.jpg',
                'tone' => 'sea',
            ]);
        $ginLink = 'node://' . $gin->value;
        $salzLink = 'node://' . $salz->value;

        // --- Startseite --------------------------------------------------------
        $main = $homeMain->nodeAggregateId;
        $this->content($main, 'Hero', [
            'kicker' => 'Astronomique Liquor Company',
            'headline' => 'Bali <em>Nights</em> Gin',
            'text' => '<p>Traditionelle Botanicals, frische tropische Früchte und bestes Schweizer Quellwasser – inspiriert vom Sternenhimmel über den Stränden Balis.</p>',
            'imageUrl' => self::IMG . '2023/11/balinight-flasche-scaled-e1701795985478.jpg',
            'primaryLabel' => 'Gin entdecken',
            'primaryLink' => $ginLink,
            'secondaryLabel' => 'Zum Shop',
            'secondaryLink' => self::SHOP,
        ]);
        $this->content($main, 'Quote', [
            'quote' => 'Gönn dir einen Schluck tropische Ferne und lass dich in eine neue geschmackliche Dimension tragen.',
            'source' => 'Bali Nights Gin',
        ]);
        $this->content($main, 'ProductList', [
            'kicker' => 'Kollektion',
            'headline' => 'Unsere <em>Produkte</em>',
            'text' => '<p>Ein Gin für warme Nächte und ein Salz aus dem Indischen Ozean.</p>',
            'anchor' => 'produkte',
        ]);
        $this->ginFeatures($main);
        $this->content($main, 'TextImage', [
            'kicker' => 'Fleur de Sel',
            'headline' => 'Das Salz <em>des Meeres</em>',
            'text' => '<p>Feinstes Fleur de Sel aus Mauritius: von Hand geerntet und an der Sonne getrocknet. Reines Meersalz, trocken gelagert unbegrenzt haltbar.</p>',
            'imageUrl' => self::IMG . '2024/01/balinight-salz-scaled-e1705166312288.jpg',
            'imageRight' => true,
            'primaryLabel' => 'Mehr erfahren',
            'primaryLink' => $salzLink,
        ]);
        $this->shopCta($main);

        // --- Produkte-Übersicht ------------------------------------------------
        $this->content($produkteMain, 'ProductList', [
            'kicker' => 'Kollektion',
            'headline' => 'Alle <em>Produkte</em>',
        ]);
        $this->shopCta($produkteMain);

        // --- Produktseiten -----------------------------------------------------
        $this->content($ginMain, 'Text', [
            'kicker' => 'Die Geschichte',
            'headline' => 'Unter Balis <em>Sternenhimmel</em>',
            'text' => '<p>Der Bali Nights Gin ist eine Kombination aus traditionellen Botanicals und frischen tropischen Früchten, abgerundet mit bestem Schweizer Quellwasser.</p><p>Ausgedacht in warmen Nächten an den Stränden Balis und inspiriert von dessen klaren, mit Sternen übersäten Nachthimmel, vermittelt er genau dieses Gefühl von unendlicher Frische und tiefem Geschmack.</p>',
        ]);
        $this->ginFeatures($ginMain);
        $this->content($ginMain, 'Quote', [
            'quote' => 'Gönn dir einen Schluck tropische Ferne und lass dich in eine neue geschmackliche Dimension tragen.',
            'source' => 'Bali Nights Gin · 500 ml · 42 % Vol.',
        ]);
        $this->shopCta($ginMain);

        $this->content($salzMain, 'Text', [
            'kicker' => 'Herkunft',
            'headline' => 'Aus dem <em>Indischen Ozean</em>',
            'text' => '<p>Unser Fleur de Sel stammt aus Mauritius. Die feinen Salzkristalle werden von Hand geerntet und an der Sonne getrocknet – ein Gourmetsalz für das Finish auf dem Teller.</p><p>Trocken gelagert ist Salz unbegrenzt haltbar.</p>',
        ]);
        $this->shopCta($salzMain);

        // --- Geschichte & Kontakt ----------------------------------------------
        $this->content($storyMain, 'Text', [
            'kicker' => 'Astronomique',
            'headline' => 'Ausgedacht in <em>warmen Nächten</em>',
            'text' => '<p>Platzhalter: Hier erzählst du, wie Astronomique entstanden ist – wer dahintersteht, wie die Idee unter dem Sternenhimmel Balis entstand und wie der Gin heute hergestellt wird. Den Text änderst du direkt auf der Seite.</p>',
        ]);
        $this->ginFeatures($storyMain);
        $this->shopCta($storyMain);

        $this->content($kontaktMain, 'Text', [
            'kicker' => 'Kontakt',
            'headline' => 'Schreib <em>uns</em>',
            'text' => '<p>Platzhalter: Kontaktangaben und Impressum folgen. Bis dahin erreichst du uns über Facebook.</p>',
        ]);
        $this->content($kontaktMain, 'Cta', [
            'kicker' => 'Folge uns',
            'headline' => 'Neuigkeiten unter dem <em>Sternenhimmel</em>',
            'text' => '<p>Neue Abfüllungen und Aktionen zuerst auf Facebook.</p>',
            'primaryLabel' => 'Facebook',
            'primaryLink' => self::FACEBOOK,
            'secondaryLabel' => 'Zum Shop',
            'secondaryLink' => self::SHOP,
        ]);

        $this->outputLine('<success>Startinhalte angelegt.</success>');
    }

    private function ginFeatures(NodeAggregateId $parent): void
    {
        [, $items] = $this->content($parent, 'Features', [
            'kicker' => 'Was ihn ausmacht',
            'headline' => 'Tropische Frische, <em>Schweizer</em> Wasser',
        ], 'items');
        $this->content($items, 'Feature', ['icon' => 'moon', 'title' => 'Bali-Nächte', 'text' => '<p>Inspiriert vom klaren, sternenübersäten Nachthimmel über Balis Stränden.</p>']);
        $this->content($items, 'Feature', ['icon' => 'leaf', 'title' => 'Botanicals', 'text' => '<p>Traditionelle Gin-Botanicals als Fundament.</p>']);
        $this->content($items, 'Feature', ['icon' => 'fruit', 'title' => 'Tropische Früchte', 'text' => '<p>Frische Frucht für unendliche Frische im Glas.</p>']);
        $this->content($items, 'Feature', ['icon' => 'drop', 'title' => 'Schweizer Quellwasser', 'text' => '<p>Abgerundet mit bestem Schweizer Quellwasser.</p>']);
    }

    private function shopCta(NodeAggregateId $parent): void
    {
        $this->content($parent, 'Cta', [
            'kicker' => 'Shop',
            'headline' => 'Hol dir die <em>Sterne</em> nach Hause',
            'text' => '<p>Bali Nights Gin und Fleur de Sel bestellst du direkt in unserem Shop.</p>',
            'primaryLabel' => 'Zum Shop',
            'primaryLink' => self::SHOP,
            'secondaryLabel' => 'Facebook',
            'secondaryLink' => self::FACEBOOK,
        ]);
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
                NodeTypeName::fromString('Astronomique.Site:Document.' . $type),
                $this->origin,
                $parent,
                null,
                PropertyValuesToWrite::fromArray(array_merge(['title' => $title, 'uriPathSegment' => $segment, 'teaser' => $teaser], $extra))
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
            NodeTypeName::fromString('Astronomique.Site:Content.' . $type),
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
