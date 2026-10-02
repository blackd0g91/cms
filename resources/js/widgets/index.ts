import { startCopyButtons } from './copy';
import { startCountdowns } from './countdown';
import { startRecipes } from './recipe';
import { startSpoilers } from './spoiler';
import { startStopwatches } from './stopwatch';
import { startTemperatures } from './temperature';
import { startTimers } from './timer';
import { startVideos } from './youtube';

/**
 * Makes the {{ widgets }} in a part of the page work (their HTML comes from
 * app/Cms/Widgets). The site starts them once for the whole page. The control
 * panel's previews are drawn again while typing, so this returns a function
 * that stops everything started, a ringing timer included.
 *
 * Without it every widget still shows something useful, like the time or
 * both temperatures.
 */
export function startWidgets(root: ParentNode = document): () => void {
    const stops = [
        startTimers,
        startTemperatures,
        startCopyButtons,
        startVideos,
        startCountdowns,
        startSpoilers,
        startStopwatches,
        startRecipes,
    ].map((start) => start(root));

    return () => stops.forEach((stop) => stop());
}
