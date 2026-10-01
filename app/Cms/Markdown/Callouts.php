<?php

namespace App\Cms\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * GitHub's callouts, written as quotes that start with a marker:
 *
 *     > [!TIP] Optional title
 *     > The text.
 *
 * NOTE, TIP, IMPORTANT, WARNING and CAUTION are colored boxes. As in
 * Obsidian, a "-" after the marker makes one collapsible and closed
 * ([!TIP]-) and a "+" collapsible and open. DETAILS is a plain collapsible
 * section, closed unless written [!DETAILS]+.
 */
class Callouts implements ExtensionInterface, NodeRendererInterface
{
    public const array TYPES = ['note', 'tip', 'important', 'warning', 'caution', 'details'];

    // Icons from Tabler Icons (https://tabler.io/icons), MIT License.
    private const array ICONS = [
        'note' => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 9h.01"/><path d="M11 12h1v4h1"/>',
        'tip' => '<path d="M3 12h1m8 -9v1m8 8h1m-15.4 -6.4l.7 .7m12.1 -.7l-.7 .7"/><path d="M9 16a5 5 0 1 1 6 0a3.5 3.5 0 0 0 -1 3a2 2 0 0 1 -4 0a3.5 3.5 0 0 0 -1 -3"/><path d="M9.7 17l4.6 0"/>',
        'important' => '<path d="M8 9h8"/><path d="M8 13h6"/><path d="M15 18l-3 3l-3 -3h-3a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v5.5"/><path d="M19 16v3"/><path d="M19 22v.01"/>',
        'warning' => '<path d="M12 9v4"/><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/>',
        'caution' => '<path d="M12 8v4"/><path d="M12 16h.01"/><path d="M8.7 3h6.6c.3 0 .5 .1 .7 .3l4.7 4.7c.2 .2 .3 .4 .3 .7v6.6c0 .3 -.1 .5 -.3 .7l-4.7 4.7c-.2 .2 -.4 .3 -.7 .3h-6.6c-.3 0 -.5 -.1 -.7 -.3l-4.7 -4.7c-.2 -.2 -.3 -.4 -.3 -.7v-6.6c0 -.3 .1 -.5 .3 -.7l4.7 -4.7c.2 -.2 .4 -.3 .7 -.3z"/>',
        'details' => '<path d="M9 6l6 6l-6 6"/>',
    ];

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, $this->convert(...));
        $environment->addRenderer(Callout::class, $this);
        $environment->addRenderer(CalloutTitle::class, $this);
    }

    private function convert(DocumentParsedEvent $event): void
    {
        // Collected first: the document can not be changed while walking it.
        $quotes = [];
        $walker = $event->getDocument()->walker();

        while ($step = $walker->next()) {
            if ($step->isEntering() && $step->getNode() instanceof BlockQuote) {
                $quotes[] = $step->getNode();
            }
        }

        foreach ($quotes as $quote) {
            $this->toCallout($quote);
        }
    }

    private function toCallout(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();
        $first = $paragraph instanceof Paragraph ? $paragraph->firstChild() : null;

        if (! $first instanceof Text || ! preg_match('/^\[!('.implode('|', self::TYPES).')\]([+-]?)[ \t]*/i', $first->getLiteral(), $match)) {
            return;
        }

        $type = strtolower($match[1]);
        $callout = new Callout($type, match ($match[2]) {
            '-' => 'closed',
            '+' => 'open',
            default => $type === 'details' ? 'closed' : 'fixed',
        });

        // The rest of the first line, formatting included, is the title.
        $first->setLiteral(substr($first->getLiteral(), strlen($match[0])));

        if ($first->getLiteral() === '') {
            $first->detach();
        }

        $title = new CalloutTitle;

        foreach ($paragraph->children() as $node) {
            if ($node instanceof Newline) {
                $node->detach();
                break;
            }

            $title->appendChild($node);
        }

        if ($title->hasChildren()) {
            $callout->appendChild($title);
        }

        if (! $paragraph->hasChildren()) {
            $paragraph->detach();
        }

        foreach ($quote->children() as $child) {
            $callout->appendChild($child);
        }

        $quote->replaceWith($callout);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement|string
    {
        if ($node instanceof CalloutTitle) {
            return $childRenderer->renderNodes($node->children());
        }

        assert($node instanceof Callout);

        $title = $node->firstChild() instanceof CalloutTitle ? $node->firstChild() : null;
        $body = [];

        foreach ($node->children() as $child) {
            if (! $child instanceof CalloutTitle) {
                $body[] = $child;
            }
        }
        $label = $title ? $childRenderer->renderNodes($title->children()) : e(ucfirst($node->type));
        $icon = '<svg class="callout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.self::ICONS[$node->type].'</svg>';
        $content = new HtmlElement('div', ['class' => 'callout-body'], $childRenderer->renderNodes($body));
        $class = "callout callout-{$node->type}";

        if ($node->state === 'fixed') {
            return new HtmlElement('div', ['class' => $class, 'role' => 'note'], [
                new HtmlElement('p', ['class' => 'callout-title'], $icon.'<span>'.$label.'</span>'),
                $content,
            ]);
        }

        return new HtmlElement('details', ['class' => $class, 'open' => $node->state === 'open'], [
            new HtmlElement('summary', ['class' => 'callout-title'], $icon.'<span>'.$label.'</span>'),
            $content,
        ]);
    }
}

final class Callout extends AbstractBlock
{
    /**
     * @param  string  $state  "fixed", or "open"/"closed" when collapsible
     */
    public function __construct(public readonly string $type, public readonly string $state)
    {
        parent::__construct();
    }
}

final class CalloutTitle extends AbstractBlock {}
