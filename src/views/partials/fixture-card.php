<?php
/**
 * One fixture in a grid. Shared by the landing page and the fixtures listing
 * so the two cannot drift apart.
 *
 * Expects:
 *   $m          match row joined to stadium and both teams
 *   $remaining  ['vip' => int, ...] for this match
 *   $capacity   ['vip' => int, ...] for the ground (for the scarcity threshold)
 */
$totalLeft = array_sum($remaining);
?>
<article class="fixture-card" data-reveal>
    <?php require __DIR__ . '/fixture-art.php'; ?>

    <div class="fixture-body">
        <h3 class="fixture-title">
            <a href="<?= url('booking.php?match=' . (int) $m['mid']) ?>"><?= e($m['title']) ?></a>
        </h3>

        <div class="fixture-meta">
            <span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                </svg>
                <?= e(date('H:i', strtotime((string) $m['kickoff_time']))) ?>
            </span>
            <span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>
                </svg>
                <?= e($m['venue_name']) ?>
            </span>
        </div>

        <ul class="fixture-tiers">
            <?php foreach (SEAT_TIERS as $tier):
                [$state, $label] = seat_status($remaining[$tier], $capacity[$tier] ?? 0);
            ?>
                <li>
                    <span>
                        <span class="fixture-tier-name"><?= e(tier_label($tier)) ?></span>
                        <span class="fixture-tier-price">&pound;<?= e(money($m['price_' . $tier])) ?></span>
                    </span>
                    <span class="seat-pill seat-pill-<?= e($state) ?>"><?= e($label) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="fixture-cta">
            <?php if ($totalLeft === 0): ?>
                <button class="btn btn-ghost w-100" type="button" disabled>Sold out</button>
            <?php else: ?>
                <a class="btn btn-primary w-100" href="<?= url('booking.php?match=' . (int) $m['mid']) ?>">
                    Book a ticket
                </a>
            <?php endif; ?>
        </div>
    </div>
</article>
