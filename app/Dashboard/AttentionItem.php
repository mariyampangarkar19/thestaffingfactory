<?php

namespace App\Dashboard;

/**
 * One line on the dashboard's "Needs your attention" list: how many, what it is, and where to deal with it.
 */
final readonly class AttentionItem
{
    public function __construct(
        public int $count,
        public string $title,
        public string $hint,
        public string $action,
        public string $url,
        public string $icon,
        public string $tone = 'warning', // warning | danger | info
    ) {}
}
