<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Communication\Services\AnnouncementService;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Services\EstimateService;
use App\Domain\Programs\Services\TargetProgressService;
use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use App\Support\LocalInput;

final class PublicTelegramBot
{
    /** @param array<string, mixed> $p */
    public function handle(BotContext $ctx, string $command, array $p, string $text): void
    {
        if ($command === '' && $ctx->conversation->state === 'estimate.weight') {
            $data = $ctx->conversation->data;
            $result = app(EstimateService::class)->calculate((int) $data['type'], (int) $data['condition'], LocalInput::decimal($text));
            $ctx->state('menu');
            $ctx->say('Perkiraan nilai: Rp '.number_format($result['estimated_value'], 0, ',', '.')."\n".$result['disclaimer'], [BotContext::button('Hitung lagi', 'public.catalog', ['estimate' => true]), BotContext::button('Menu', 'menu')]);

            return;
        }
        if ($command === 'estimate.condition') {
            $ctx->state('estimate.weight', ['type' => (int) $p['type'], 'condition' => (int) $p['condition']]);
            $ctx->say('Berapa perkiraan beratnya dalam kg? Contoh: 1,25.');

            return;
        }
        if ($command === 'estimate.type') {
            $type = app(PublicWasteCatalog::class)->visibleTypes()->with('conditions')->findOrFail((int) $p['id']);
            $ctx->say('Pilih kondisi '.$type->name.'.', $type->conditions->where('is_active', true)->map(fn ($condition): array => BotContext::button($condition->name, 'estimate.condition', ['type' => $type->id, 'condition' => $condition->id]))->values()->all());

            return;
        }
        $ctx->state('menu');
        if ($command === 'public.catalog') {
            $page = max(1, (int) ($p['page'] ?? 1));
            $types = app(PublicWasteCatalog::class)->types();
            $buttons = [];
            foreach ($types->forPage($page, 5) as $type) {
                $buttons[] = BotContext::button($type->name, ($p['estimate'] ?? false) ? 'estimate.type' : 'public.type', ['id' => $type->id]);
            }
            if ($types->count() > $page * 5) {
                $buttons[] = BotContext::button('Berikutnya', 'public.catalog', ['page' => $page + 1, 'estimate' => (bool) ($p['estimate'] ?? false)]);
            }
            $buttons[] = BotContext::button('Informasi', 'public.menu');
            $ctx->say($types->isEmpty() ? 'Belum ada jenis sampah aktif.' : 'Pilih jenis sampah.', $buttons);
        } elseif ($command === 'public.type') {
            $type = app(PublicWasteCatalog::class)->types()->firstWhere('id', (int) $p['id']);
            abort_unless($type !== null, 404);
            $lines = [$type->name];
            foreach ($type->prices as $price) {
                $lines[] = $price->condition->name.': Rp '.number_format($price->price, 0, ',', '.').'/'.$type->unit->symbol;
            }
            if (count($lines) === 1) {
                $lines[] = 'Harga belum tersedia.';
            }
            $ctx->say(implode("\n", $lines), [BotContext::button('Perkirakan nilai', 'estimate.type', ['id' => $type->id]), BotContext::button('Katalog', 'public.catalog')]);
            if (app(PublicWasteCatalog::class)->photoUrl($type) !== null) {
                $ctx->file(['kind' => 'catalog-photo', 'type_id' => $type->id, 'caption' => $type->name]);
            }
        } elseif ($command === 'public.packages') {
            $packages = GroceryPackage::query()->where('status', 'aktif')->where(fn ($q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', today()))->where(fn ($q) => $q->whereNull('active_until')->orWhereDate('active_until', '>=', today()))->limit(10)->get();
            $ctx->say($packages->isEmpty() ? 'Belum ada paket aktif.' : $packages->map(fn ($row): string => $row->name.' · Rp '.number_format($row->value, 0, ',', '.')."\n".$row->contents)->implode("\n\n"), [BotContext::button('Menu', 'menu')]);
        } elseif ($command === 'public.schedule') {
            $rows = app(MobileServiceService::class)->publicQuery()->orderBy('starts_at')->limit(8)->get();
            $ctx->say($rows->isEmpty() ? 'Belum ada jadwal keliling.' : $rows->map(fn ($row): string => $row->point."\n".$row->starts_at->translatedFormat('d M Y H:i').'–'.$row->ends_at->format('H:i'))->implode("\n\n"), [BotContext::button('Menu', 'menu')]);
        } elseif ($command === 'public.announcements') {
            $rows = app(AnnouncementService::class)->publicQuery()->limit(5)->get();
            $ctx->say($rows->isEmpty() ? 'Belum ada pengumuman aktif.' : $rows->map(fn ($row): string => $row->title."\n".strip_tags($row->body))->implode("\n\n"), [BotContext::button('Menu', 'menu')]);
        } elseif ($command === 'public.targets') {
            $progress = app(TargetProgressService::class);
            $rows = CollectionTarget::query()->with('scopes')->where('is_public', true)->where('status', 'aktif')->whereDate('period_start', '<=', today())->whereDate('period_end', '>=', today())->get();
            // A cron worker processes multiple updates; do not reuse a previous request's privacy aggregate.
            $aggregates = $progress->aggregateMany($rows);
            $rows = $rows->filter(fn ($row): bool => $aggregates[$row->id]['subject_count'] >= $row->public_min_subjects);
            $ctx->say($rows->isEmpty() ? 'Belum ada progres target yang dipublikasikan.' : $rows->take(8)->map(fn ($row): string => $row->name."\n".$aggregates[$row->id]['weight_kg'].' / '.$row->target_weight_kg.' kg')->implode("\n\n"), [BotContext::button('Menu', 'menu')]);
        } else {
            $ctx->say('Informasi Bank Sampah Sindangheula', [BotContext::button('Katalog dan harga', 'public.catalog'), BotContext::button('Perkirakan nilai', 'public.catalog', ['estimate' => true]), BotContext::button('Paket sembako', 'public.packages'), BotContext::button('Jadwal keliling', 'public.schedule'), BotContext::button('Pengumuman', 'public.announcements'), BotContext::button('Target sampah', 'public.targets'), ['label' => 'Panduan penggunaan', 'url' => route('public.tutorials')], BotContext::button('Menu', 'menu')]);
        }
    }
}
