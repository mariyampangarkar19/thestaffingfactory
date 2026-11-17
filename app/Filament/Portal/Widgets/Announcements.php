<?php

namespace App\Filament\Portal\Widgets;

use App\Models\Announcement;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/** Agency announcements currently published (quote section 2, "Notifications"). */
class Announcements extends Widget
{
    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.portal.announcements';

    /** @return Collection<int, array{title: string, body: string, date: string}> */
    public function items(): Collection
    {
        $fr = app()->getLocale() === 'fr';

        return Announcement::query()
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('published_at')->limit(5)->get()
            ->map(fn (Announcement $a) => [
                'title' => (string) ($fr ? ($a->title_fr ?: $a->title_en) : ($a->title_en ?: $a->title_fr)),
                'body' => (string) ($fr ? ($a->body_fr ?: $a->body_en) : ($a->body_en ?: $a->body_fr)),
                'date' => $a->published_at->translatedFormat('j M Y'),
            ]);
    }
}
