import { eachWidget } from './each';

/* {{ youtube }}: nothing is loaded from YouTube until the video is played. */

export function startVideos(root: ParentNode): () => void {
    return eachWidget<HTMLElement>(
        root,
        '.widget-youtube[data-youtube]',
        (widget, signal) => {
            widget.querySelector('.widget-youtube-play')?.addEventListener(
                'click',
                (event) => {
                    event.preventDefault();

                    const start = Number(widget.dataset.start) || 0;
                    const player = document.createElement('iframe');

                    player.src = `https://www.youtube-nocookie.com/embed/${widget.dataset.youtube}?autoplay=1${start ? `&start=${start}` : ''}`;
                    player.title =
                        widget.querySelector('.widget-youtube-title')
                            ?.textContent ?? 'YouTube video';
                    player.allow =
                        'autoplay; encrypted-media; picture-in-picture; fullscreen';
                    player.allowFullscreen = true;
                    player.className = 'widget-youtube-player';

                    widget.replaceChildren(player);
                    widget.dataset.playing = '';
                },
                { signal },
            );
        },
    );
}
