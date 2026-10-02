<?php

namespace App\Cms\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Captions: an image alone on its line with a title,
 *
 *     ![A loaf of bread](bread.jpg "Fresh from the oven")
 *
 * becomes a <figure> with the title as its caption. The alt text stays for
 * screen readers, so it is not shown twice. Images within text, and images
 * without a title, are left as they are.
 */
class Figures implements ExtensionInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, $this->convert(...));
        $environment->addRenderer(Figure::class, $this);
    }

    private function convert(DocumentParsedEvent $event): void
    {
        // Collected first: the document can not be changed while walking it.
        $paragraphs = [];
        $walker = $event->getDocument()->walker();

        while ($step = $walker->next()) {
            if ($step->isEntering() && $step->getNode() instanceof Paragraph) {
                $paragraphs[] = $step->getNode();
            }
        }

        foreach ($paragraphs as $paragraph) {
            $image = self::onlyImage($paragraph);
            $caption = trim((string) $image?->getTitle());

            if ($image === null || $caption === '') {
                continue;
            }

            $image->setTitle(null);
            $figure = new Figure($caption);
            $figure->appendChild($image);
            $paragraph->replaceWith($figure);
        }
    }

    /**
     * The paragraph's image, when there is nothing else in it but spaces.
     */
    private static function onlyImage(Paragraph $paragraph): ?Image
    {
        $image = null;

        foreach ($paragraph->children() as $child) {
            if ($child instanceof Image && $image === null) {
                $image = $child;
            } elseif (! $child instanceof Text || trim($child->getLiteral()) !== '') {
                return null;
            }
        }

        return $image;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        assert($node instanceof Figure);

        return new HtmlElement(
            'figure',
            [],
            $childRenderer->renderNodes($node->children()).new HtmlElement('figcaption', [], e($node->caption)),
        );
    }
}

final class Figure extends AbstractBlock
{
    public function __construct(public readonly string $caption)
    {
        parent::__construct();
    }
}
