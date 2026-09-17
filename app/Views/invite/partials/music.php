<?php
/**
 * Background music.
 *
 * The audio element is never set to autoplay in markup: browsers block it and
 * a silent broken control is worse than a visible play button.
 *
 * @var App\Services\TemplateContext $c
 */
?>
<audio id="inv-audio" src="<?= e($c->musicUrl()) ?>" preload="none" loop></audio>

<button type="button" class="inv-music"
        data-inv-autoplay="<?= $c->musicAutoplay() ? '1' : '0' ?>"
        data-label-play="<?= e(__('invite.play_music')) ?>"
        data-label-pause="<?= e(__('invite.pause_music')) ?>"
        aria-pressed="false" aria-label="<?= e(__('invite.play_music')) ?>">
    <span class="inv-music__bars" aria-hidden="true">
        <svg viewBox="0 0 16 16" width="18" height="18" fill="currentColor">
            <rect x="2" y="5" width="2.5" height="6" rx="1"/>
            <rect x="6.7" y="2.5" width="2.5" height="11" rx="1"/>
            <rect x="11.4" y="6.5" width="2.5" height="3" rx="1"/>
        </svg>
    </span>
</button>
